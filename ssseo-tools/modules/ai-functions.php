<?php
// File: modules/ai-functions.php

if ( ! defined( 'ABSPATH' ) ) exit;

// Back-compat action name: do NOT change your JS
add_action( 'wp_ajax_ssseo_ai_generate_meta', 'ssseo_ai_generate_meta_callback' );
/**
 * Improved meta description generator (Yoast-aware, shortcode-aware).
 * Returns: { success: true, data: { generated: "..." } }
 */
function ssseo_ai_generate_meta_callback() {
	if (
		empty($_POST['nonce']) ||
		! wp_verify_nonce($_POST['nonce'], 'ssseo_ai_generate') ||
		! current_user_can('edit_posts')
	) {
		wp_send_json_error('Unauthorized');
	}

	$post_id = (int) ($_POST['post_id'] ?? 0);
	if ( ! $post_id || ! $pt = get_post_type($post_id) ) {
		wp_send_json_error('Invalid post ID');
	}

	$title   = get_the_title($post_id);
	$content = (string) get_post_field('post_content', $post_id);
	$excerpt = (string) get_post_field('post_excerpt', $post_id);

	// Expand shortcodes in context? default true; supports your earlier filter name
	$expand = (bool) apply_filters('ssseo_ai_meta_expand_shortcodes', true, $post_id);
	if ($expand && function_exists('myls_do_shortcode_in_post_context')) {
		$content = myls_do_shortcode_in_post_context($content, $post_id);
	}
	$content = wp_strip_all_tags($content);
	if ($excerpt) $content .= "\n\nExcerpt:\n" . wp_strip_all_tags($excerpt);

	// Yoast focus keyphrase (optional)
	$focus = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
	if (! $focus) $focus = get_post_meta($post_id, '_yoast_wpseo_focuskw_text_input', true);

	// Current Yoast description (for refinement, optional)
	$current = (string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true);

	// Human label for post type
	$pt_obj = get_post_type_object($pt);
	$pt_hum = $pt_obj && ! empty($pt_obj->labels->singular_name) ? $pt_obj->labels->singular_name : $pt;

	// Length rules (defaults safe for SERP)
	$min_len = (int) apply_filters('ssseo_ai_desc_min_length', (int) get_option('ssseo_ai_desc_min_length', 145), $post_id);
	$max_len = (int) apply_filters('ssseo_ai_desc_max_length', (int) get_option('ssseo_ai_desc_max_length', 160), $post_id);

	$default_template =
		"Write a single, compelling, SEO-friendly meta description between {$min_len}–{$max_len} characters (no emojis, no HTML). ".
		"Focus on search intent and a subtle call to action. Avoid keyword stuffing.\n\n".
		"Content type: %post_type%\n".
		"Title: %current_title%\n".
		"%maybe_focus_kw%".
		"%maybe_current_desc%".
		"Content (for context):\n%content%\n\n".
		"Rules:\n".
		"- Prefer one sentence; two short sentences allowed if within length.\n".
		"- Do not wrap in quotes. No decorative symbols.\n".
		"- Return ONLY the description text.";

	// Back-compat: prefer your original option name if set
	$template = get_option('ssseo_ai_meta_prompt');
	if (! $template || ! is_string($template)) {
		$template = get_option('ssseo_ai_desc_prompt', $default_template);
	}
	// Back-compat filter + new filter name
	$template = apply_filters('ssseo_ai_meta_prompt_template', $template, $post_id);
	$template = apply_filters('ssseo_ai_desc_prompt_template', $template, $post_id);

	$vars = [
		'%post_type%'          => $pt_hum,
		'%current_title%'      => $title,
		'%content%'            => $content,
		'%maybe_focus_kw%'     => $focus   ? "Yoast Focus Keyphrase: {$focus}\n" : "",
		'%maybe_current_desc%' => $current ? "Current Yoast Meta Description: {$current}\n" : "",
	];
	$vars = apply_filters('ssseo_ai_meta_prompt_vars', $vars, $post_id);
	$vars = apply_filters('ssseo_ai_desc_prompt_vars', $vars, $post_id);

	$prompt     = strtr($template, $vars);
	$max_tokens = (int) apply_filters('ssseo_ai_desc_max_tokens', 160, $post_id);
	$max_tokens = (int) apply_filters('ssseo_ai_meta_max_tokens', $max_tokens, $post_id);

	$result = ssseo_send_openai_request($prompt, $max_tokens);
	if (is_wp_error($result)) {
		wp_send_json_error($result->get_error_message());
	}

	$generated = trim((string) $result);
	$generated = preg_replace('/^(["\'“”‘’])(.*)\1$/u', '$2', $generated); // strip surrounding quotes
	$generated = wp_strip_all_tags($generated);
	$generated = rtrim($generated, " \t\n\r\0\x0B-–—|:;•·");
	$generated = preg_replace('/\s{2,}/', ' ', $generated);
	$generated = ssseo_ai_smart_truncate($generated, $max_len);
	$generated = rtrim($generated, ",;:|•·");
	$generated = sanitize_textarea_field($generated);

	wp_send_json_success(['generated' => $generated]);
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
	$text = do_shortcode($text);

    if ( ! $post_id || empty( $text ) ) {
        wp_send_json_error( 'Invalid input' );
    }

    update_post_meta( $post_id, '_yoast_wpseo_metadesc', $text );
    wp_send_json_success();
}


/**
 * AJAX: Generate Yoast SEO Title (Improved)
 *
 * - Adds Yoast Focus Keyphrase if available
 * - Optionally expands shortcodes in content for richer context
 * - Prompt template is customizable via option and filters
 * - Enforces length and tidy formatting on the result
 *
 * Requirements:
 * - Function ssseo_send_openai_request( string $prompt, int $max_tokens ): string|WP_Error
 * - Optional helper myls_do_shortcode_in_post_context( $string, $post_id )
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_ssseo_ai_generate_title', 'ssseo_ai_generate_title_callback' );
function ssseo_ai_generate_title_callback() {
	// --- Security checks ---
	if (
		empty( $_POST['nonce'] ) ||
		! wp_verify_nonce( $_POST['nonce'], 'ssseo_ai_generate' ) ||
		! current_user_can( 'edit_posts' )
	) {
		wp_send_json_error( 'Unauthorized' );
	}

	// --- Inputs ---
	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	if ( ! $post_id || ! $pt = get_post_type( $post_id ) ) {
		wp_send_json_error( 'Invalid post ID' );
	}

	// --- Core post context ---
	$post_title   = get_the_title( $post_id );
	$raw_content  = (string) get_post_field( 'post_content', $post_id );

	// Optionally expand shortcodes in the post context to give the model better info.
	// If you already have myls_do_shortcode_in_post_context(), this will use it.
	$expand_shortcodes = (bool) apply_filters( 'ssseo_ai_title_expand_shortcodes', true );
	if ( $expand_shortcodes && function_exists( 'myls_do_shortcode_in_post_context' ) ) {
		$context_content = myls_do_shortcode_in_post_context( $raw_content, $post_id );
	} else {
		$context_content = $raw_content;
	}

	// Strip tags to avoid HTML noise in the prompt, but keep line breaks-ish.
	$context_content = wp_strip_all_tags( $context_content );

	// --- Yoast Focus Keyphrase (if Yoast is active) ---
	$yoast_focus_kw = '';
	// Classic Yoast meta keys seen in the wild:
	$yoast_focus_kw = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
	if ( ! $yoast_focus_kw ) {
		$yoast_focus_kw = get_post_meta( $post_id, '_yoast_wpseo_focuskw_text_input', true );
	}

	// --- Brand suffix (optional) ---
	// Let users configure a brand suffix once and re-use it (e.g., " | Brooks Law Group")
	$brand_suffix = (string) get_option( 'ssseo_ai_brand_suffix', '' );
	$brand_suffix = apply_filters( 'ssseo_ai_title_brand_suffix', $brand_suffix, $post_id );

	// --- Length rules ---
	// Your original default was 90–120 chars; Google typically shows ~50–60,
	// but you can keep your preference. These are used for the prompt *and* final enforcement.
	$min_len = (int) apply_filters( 'ssseo_ai_title_min_length', (int) get_option( 'ssseo_ai_title_min_length', 90 ) );
	$max_len = (int) apply_filters( 'ssseo_ai_title_max_length', (int) get_option( 'ssseo_ai_title_max_length', 120 ) );

	// --- Post type label (friendlier than the slug) ---
	$pt_obj        = get_post_type_object( $pt );
	$post_type_hum = $pt_obj && ! empty( $pt_obj->labels->singular_name ) ? $pt_obj->labels->singular_name : $pt;

	// --- Build the template ---
	// Option (string) can be overridden in WP options page; filter allows full control in code.
	$default_template =
		"Write a single, SEO-friendly page title between {$min_len}–{$max_len} characters (no emojis). ".
		"Prioritize clarity and click intent. Use sentence case, avoid pipes unless the brand suffix is provided.\n\n".
		"Content type: %post_type%\n".
		"Existing title: %current_title%\n".
		"%maybe_focus_kw%".
		"Content (for context):\n%content%\n\n".
		"Rules:\n".
		"- If a brand suffix is provided, append it at the end once.\n".
		"- Do not repeat the brand inside the main title.\n".
		"- Do not wrap the title in quotes.\n".
		"- Avoid trailing punctuation.\n".
		"- Return ONLY the title string.";

	$template = get_option( 'ssseo_ai_title_prompt', $default_template );
	$template = apply_filters( 'ssseo_ai_title_prompt_template', $template, $post_id );

	// --- Prepare replaceable variables for the template ---
	$vars = [
		'%post_type%'      => $post_type_hum,
		'%current_title%'  => $post_title,
		'%content%'        => $context_content,
		'%brand_suffix%'   => $brand_suffix,
		// Conditionally include focus keyphrase line so we don't give empty noise
		'%maybe_focus_kw%' => $yoast_focus_kw ? "Yoast Focus Keyphrase: {$yoast_focus_kw}\n" : "",
	];

	// Allow you to inject more variables, e.g., city/state from ACF:
	// add_filter('ssseo_ai_title_prompt_vars', function($vars, $post_id){ $vars['%city_state%'] = get_field('city_state', $post_id); return $vars; }, 10, 2);
	$vars = apply_filters( 'ssseo_ai_title_prompt_vars', $vars, $post_id );

	// --- Build prompt by replacement (supports custom placeholders easily) ---
	$prompt = strtr( $template, $vars );

	// --- Max tokens for model output (filterable) ---
	$max_tokens = (int) apply_filters( 'ssseo_ai_title_max_tokens', 160, $post_id );

	// --- Send to OpenAI (expects a plain string response) ---
	$result = ssseo_send_openai_request( $prompt, $max_tokens );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	// --- Post-process: clean and enforce length/style ---
	$generated = sanitize_text_field( trim( (string) $result ) );

	// 1) Remove surrounding quotes if the model returns them
	$generated = preg_replace( '/^(["\'“”‘’])(.*)\1$/u', '$2', $generated );

	// 2) Remove trailing punctuation we don’t want to end with
	$generated = rtrim( $generated, " \t\n\r\0\x0B-–—|:;,.•·" );

	// 3) Enforce max length *without* chopping mid-word (then add brand suffix once if configured)
	$generated = ssseo_ai_smart_truncate( $generated, $max_len );

	if ( $brand_suffix ) {
		// Add suffix *once* if not already present (case-insensitive)
		if ( stripos( $generated, $brand_suffix ) === false ) {
			// If adding suffix would exceed $max_len, try trimming the main title a bit
			$room = $max_len - mb_strlen( $generated ) - mb_strlen( $brand_suffix );
			if ( $room < 0 ) {
				$generated = ssseo_ai_smart_truncate( $generated, max( 0, $max_len - mb_strlen( $brand_suffix ) ) );
			}
			$generated .= $brand_suffix;
			$generated = rtrim( $generated );
		}
	}

	// 4) Enforce minimum length (optional gentle nudge). If too short, we won't try to pad—just return as-is.
	// You can implement a second pass if you want.

	// Final tidy: no double spaces
	$generated = preg_replace( '/\s{2,}/', ' ', $generated );

	wp_send_json_success( [ 'generated' => $generated ] );
}

/**
 * Word-safe truncation helper.
 * Trims to $max chars without cutting words when possible.
 * Falls back to hard cut if the first word itself exceeds limit.
 */
function ssseo_ai_smart_truncate( $text, $max ) {
	$text = (string) $text;
	if ( mb_strlen( $text ) <= $max ) return $text;

	$soft = mb_substr( $text, 0, $max + 1 );
	// Find last space within limit
	$pos = mb_strrpos( $soft, ' ' );
	if ( $pos !== false && $pos >= (int) floor( $max * 0.6 ) ) {
		return rtrim( mb_substr( $soft, 0, $pos ) );
	}
	// Hard cut if no good break
	return rtrim( mb_substr( $text, 0, $max ) );
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
