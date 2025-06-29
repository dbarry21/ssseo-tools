<?php
// File: modules/ai-functions.php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * -------------------------------------------------------------------------
 * 1) Generate Meta Description via ChatGPT
 * -------------------------------------------------------------------------
 */
add_action( 'wp_ajax_ssseo_ai_generate_meta', 'ssseo_ai_generate_meta_callback' );
function ssseo_ai_generate_meta_callback() {
    if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ssseo_ai_generate' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Insufficient permissions' );
    }

    $post_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $post_id || ! $pt = get_post_type( $post_id ) ) {
        wp_send_json_error( 'Invalid post ID' );
    }

    $title   = get_the_title( $post_id );
    $content = get_post_field( 'post_content', $post_id );

    $default_meta_prompt = "Write a concise, SEO-friendly meta description (max 160 characters) for a %s titled “%s”. Use this content as context:\n\n%s";
    $meta_prompt_template = get_option( 'ssseo_ai_meta_prompt', $default_meta_prompt );

    $prompt = sprintf( $meta_prompt_template, $pt, $title, wp_strip_all_tags( $content ) );

    $api_key = get_option( 'ssseo_openai_api_key', '' );
    if ( empty( $api_key ) ) {
        wp_send_json_error( 'OpenAI API key not set' );
    }

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . trim( $api_key ),
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'       => 'gpt-4o',
            'messages'    => [
                [ 'role' => 'system', 'content' => 'You are an expert SEO assistant and Copywriter that specializes in marketing.' ],
                [ 'role' => 'user',   'content' => $prompt ],
            ],
            'max_tokens'  => 160,
            'temperature' => 0.8,
        ] ),
        'timeout' => 30,
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'OpenAI request failed: ' . $response->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code !== 200 || empty( $data['choices'][0]['message']['content'] ) ) {
        $error_message = $data['error']['message'] ?? 'Unexpected API response';
        wp_send_json_error( 'OpenAI API error: ' . $error_message );
    }

    $raw       = $data['choices'][0]['message']['content'];
    $generated = trim( trim( $raw ), '"' );
    wp_send_json_success( [ 'generated' => $generated ] );
}


/**
 * -------------------------------------------------------------------------
 * 2) Save Meta Description into Yoast
 * -------------------------------------------------------------------------
 */
add_action( 'wp_ajax_ssseo_ai_save_meta', 'ssseo_ai_save_meta_callback' );
function ssseo_ai_save_meta_callback() {
    if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ssseo_ai_generate' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Insufficient permissions' );
    }

    $post_id      = intval( $_POST['post_id'] ?? 0 );
    $meta_content = sanitize_text_field( wp_unslash( $_POST['text'] ?? '' ) );
    if ( ! $post_id || empty( $meta_content ) ) {
        wp_send_json_error( 'Invalid input' );
    }

    update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_content );
    wp_send_json_success();
}


/**
 * -------------------------------------------------------------------------
 * 3) Generate Yoast Title via ChatGPT
 * -------------------------------------------------------------------------
 */
add_action( 'wp_ajax_ssseo_ai_generate_title', 'ssseo_ai_generate_title_callback' );
function ssseo_ai_generate_title_callback() {
    if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ssseo_ai_generate' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Insufficient permissions' );
    }

    $post_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $post_id || ! $pt = get_post_type( $post_id ) ) {
        wp_send_json_error( 'Invalid post ID' );
    }

    $title   = get_the_title( $post_id );
    $content = get_post_field( 'post_content', $post_id );

    $default_title_prompt = "Write a concise, SEO-friendly title (between 90 and 120 characters) for a %s. Use this existing title “%s” and content as context:\n\n%s";
    $title_prompt_template = get_option( 'ssseo_ai_title_prompt', $default_title_prompt );

    $prompt = sprintf( $title_prompt_template, $pt, $title, wp_strip_all_tags( $content ) );

    $api_key = get_option( 'ssseo_openai_api_key', '' );
    if ( empty( $api_key ) ) {
        wp_send_json_error( 'OpenAI API key not set' );
    }

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . trim( $api_key ),
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'       => 'gpt-4o',
            'messages'    => [
                [ 'role' => 'system', 'content' => 'You are an expert SEO assistant and Copywriter that specializes in marketing.' ],
                [ 'role' => 'user',   'content' => $prompt ],
            ],
            'max_tokens'  => 160,
            'temperature' => 0.8,
        ] ),
        'timeout' => 30,
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'OpenAI request failed: ' . $response->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code !== 200 || empty( $data['choices'][0]['message']['content'] ) ) {
        $error_message = $data['error']['message'] ?? 'Unexpected API response';
        wp_send_json_error( 'OpenAI API error: ' . $error_message );
    }

    $raw = $data['choices'][0]['message']['content'];
    $generated_title = trim( trim( $raw ), '"' );
    wp_send_json_success( [ 'generated' => $generated_title ] );
}


/**
 * -------------------------------------------------------------------------
 * 4) Save Yoast Title into Yoast
 * -------------------------------------------------------------------------
 */
add_action( 'wp_ajax_ssseo_ai_save_title', 'ssseo_ai_save_title_callback' );
function ssseo_ai_save_title_callback() {
    if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ssseo_ai_generate' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Insufficient permissions' );
    }

    $post_id       = intval( $_POST['post_id'] ?? 0 );
    $title_content = sanitize_text_field( wp_unslash( $_POST['text'] ?? '' ) );
    if ( ! $post_id || empty( $title_content ) ) {
        wp_send_json_error( 'Invalid input' );
    }

    update_post_meta( $post_id, '_yoast_wpseo_title', $title_content );
    wp_send_json_success();
}
