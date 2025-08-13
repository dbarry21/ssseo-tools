<?php
// File: modules/ai-functions.php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Generate Meta Description via ChatGPT
 */
add_action( 'wp_ajax_ssseo_ai_generate_meta', 'ssseo_ai_generate_meta_callback' );
function ssseo_ai_generate_meta_callback() {
    if (
        empty( $_POST['nonce'] ) ||
        ! wp_verify_nonce( $_POST['nonce'], 'ssseo_ai_generate' ) ||
        ! current_user_can( 'edit_posts' )
    ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $post_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $post_id || ! $pt = get_post_type( $post_id ) ) {
        wp_send_json_error( 'Invalid post ID' );
    }

    $title   = get_the_title( $post_id );
    $content = get_post_field( 'post_content', $post_id );

    $template = get_option(
        'ssseo_ai_meta_prompt',
        "Write a concise, SEO-friendly meta description (max 160 characters) for a %s titled “%s”. Use this content as context:\n\n%s"
    );
    $prompt = sprintf( $template, $pt, $title, wp_strip_all_tags( $content ) );

    $result = ssseo_send_openai_request( $prompt, 160 );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    wp_send_json_success([ 'generated' => $result ]);
}

/**
 * Save Meta Description into Yoast
 */
add_action( 'wp_ajax_ssseo_ai_save_meta', 'ssseo_ai_save_meta_callback' );
function ssseo_ai_save_meta_callback() {
    if (
        empty( $_POST['nonce'] ) ||
        ! wp_verify_nonce( $_POST['nonce'], 'ssseo_ai_generate' ) ||
        ! current_user_can( 'edit_posts' )
    ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $post_id = intval( $_POST['post_id'] ?? 0 );
    $text    = sanitize_text_field( wp_unslash( $_POST['text'] ?? '' ) );

    if ( ! $post_id || empty( $text ) ) {
        wp_send_json_error( 'Invalid input' );
    }

    update_post_meta( $post_id, '_yoast_wpseo_metadesc', $text );
    wp_send_json_success();
}

/**
 * Generate Yoast Title via ChatGPT
 */
add_action( 'wp_ajax_ssseo_ai_generate_title', 'ssseo_ai_generate_title_callback' );
function ssseo_ai_generate_title_callback() {
    if (
        empty( $_POST['nonce'] ) ||
        ! wp_verify_nonce( $_POST['nonce'], 'ssseo_ai_generate' ) ||
        ! current_user_can( 'edit_posts' )
    ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $post_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $post_id || ! $pt = get_post_type( $post_id ) ) {
        wp_send_json_error( 'Invalid post ID' );
    }

    $title   = get_the_title( $post_id );
    $content = get_post_field( 'post_content', $post_id );

    $template = get_option(
        'ssseo_ai_title_prompt',
        "Write a concise, SEO-friendly title (between 90 and 120 characters) for a %s. Use this existing title “%s” and content as context:\n\n%s"
    );
    $prompt = sprintf( $template, $pt, $title, wp_strip_all_tags( $content ) );

    $result = ssseo_send_openai_request( $prompt, 160 );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    wp_send_json_success([ 'generated' => $result ]);
}

/**
 * Save Yoast Title into Yoast
 */
add_action( 'wp_ajax_ssseo_ai_save_title', 'ssseo_ai_save_title_callback' );
function ssseo_ai_save_title_callback() {
    if (
        empty( $_POST['nonce'] ) ||
        ! wp_verify_nonce( $_POST['nonce'], 'ssseo_ai_generate' ) ||
        ! current_user_can( 'edit_posts' )
    ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $post_id = intval( $_POST['post_id'] ?? 0 );
    $text    = sanitize_text_field( wp_unslash( $_POST['text'] ?? '' ) );

    if ( ! $post_id || empty( $text ) ) {
        wp_send_json_error( 'Invalid input' );
    }

    update_post_meta( $post_id, '_yoast_wpseo_title', $text );
    wp_send_json_success();
}


/**
 * Shared Function: Send Prompt to OpenAI and Return Result
 */
function ssseo_send_openai_request( $prompt, $max_tokens = 160 ) {
    $api_key = get_option( 'ssseo_openai_api_key' );
    if ( empty( $api_key ) ) {
        return new WP_Error( 'missing_key', 'OpenAI API key not set' );
    }

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . trim( $api_key ),
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode([
            'model'     => 'gpt-4o',
            'messages'  => [
                [ 'role' => 'system', 'content' => 'You are an expert SEO assistant and Copywriter that specializes in marketing.' ],
                [ 'role' => 'user',   'content' => $prompt ],
            ],
            'max_tokens'  => $max_tokens,
            'temperature' => 0.7,
        ]),
        'timeout' => 30,
    ]);

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code !== 200 || empty( $body['choices'][0]['message']['content'] ) ) {
        $msg = $body['error']['message'] ?? 'Unexpected API response';
        return new WP_Error( 'openai_error', 'OpenAI API error: ' . $msg );
    }

    return trim( trim( $body['choices'][0]['message']['content'] ), '"' );
}

/**
 * Generate and Save "About the Area" AI Content
 */
add_action('wp_ajax_ssseo_ai_generate_about_area', 'ssseo_ai_generate_about_area_callback');

function ssseo_ai_generate_about_area_callback() {
    if (
        empty($_POST['nonce']) ||
        !wp_verify_nonce($_POST['nonce'], 'ssseo_ai_generate') ||
        !current_user_can('edit_posts')
    ) {
        //wp_send_json_error('Unauthorized');
    }

    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) wp_send_json_error('Invalid post ID');

    $area_name     = function_exists('get_field') ? get_field('city_state', $post_id) : '';
    $service_title = get_the_title($post_id);

    // Pull plugin-wide options
    $service_label = get_option('ssseo_default_service_label', 'local services');
    $org_desc      = get_option('ssseo_org_description', '');

    if (empty($area_name) || empty($service_title)) {
        wp_send_json_error('Missing input data');
    }

    // Construct the OpenAI prompt
    $prompt = sprintf(
        "Write a compelling, SEO-optimized HTML section about %s for a local %s business page titled \"%s\".\n\n" .
        "%s\n\n" .
        "Include local landmarks, community relevance, related businesses, and surrounding areas. Use natural, engaging language.\n" .
        "Format with HTML headings and paragraphs. Bold key phrases, places of interest, and neighborhoods with <strong> html tags. Do not include ```html or ```.\n" .
        "Avoid adding a conclusion.",
        $area_name,
        $service_label,
        $service_title,
        $org_desc ? "Business description: $org_desc" : ''
    );

    $result = ssseo_send_openai_request($prompt, 900);

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    update_post_meta($post_id, '_about_the_area', wp_kses_post($result));

    wp_send_json_success([
        'generated' => wpautop($result)
    ]);
}

add_action('wp_ajax_ssseo_get_city_state', function () {
    header('Content-Type: application/json');

    $post_id = intval($_POST['post_id'] ?? 0);
    $nonce   = $_POST['nonce'] ?? '';

    // Debug log
    error_log("AJAX Request: post_id=$post_id, nonce=$nonce");

    if (empty($nonce) || !wp_verify_nonce($nonce, 'ssseo_ai_generate')) {
        error_log("❌ Nonce failed or empty");
        wp_send_json_error('Unauthorized');
    }

    if (!$post_id || get_post_status($post_id) === false) {
        error_log("❌ Invalid post ID: $post_id");
        wp_send_json_error('Invalid post ID');
    }

    // Retrieve city_state using ACF only
    $area_name = function_exists('get_field') ? get_field('city_state', $post_id) : '';

    if (empty($area_name)) {
        error_log("❌ city_state field not found or empty for post ID: $post_id");
        wp_send_json_error('city_state field is empty or not found');
    }

    error_log("✅ Retrieved city_state: $area_name");
    wp_send_json_success($area_name);
});


add_action('wp_ajax_ssseo_get_posts_by_type', function () {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Unauthorized');
    }

    $post_type = sanitize_text_field($_POST['post_type'] ?? '');

    if (!$post_type || !post_type_exists($post_type)) {
        wp_send_json_error('Invalid post type');
    }

    $posts = get_posts([
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => ['ID', 'post_title'],
    ]);

    $results = array_map(function ($p) {
        return [
            'id'    => $p->ID,
            'title' => $p->post_title,
        ];
    }, $posts);

    wp_send_json_success($results);
});
