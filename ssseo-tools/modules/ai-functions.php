<?php
/**
 * File: modules/ai-functions.php
 * Description:
 * - AI generators for Yoast Title and Meta Description.
 * - Shortcode-aware helpers so strings (Yoast fields, titles) can include shortcodes like [city_state].
 * - Yoast meta description filter to expand shortcodes in the actual <meta name="description"> tag.
 *
 * Notes:
 * - Keep ONE definition of each helper in your plugin.
 * - The AJAX endpoints expect a valid nonce 'ssseo_ai_generate' and editor capability.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
 * Helper: Evaluate shortcodes in the context of a specific post
 * ============================================================ */
if ( ! function_exists( 'myls_do_shortcode_in_post_context' ) ) {
	/**
	 * Ensures shortcodes that rely on global $post (e.g., [city_state]) render correctly.
	 *
	 * @param string $string
	 * @param int    $post_id
	 * @return string
	 */
	function myls_do_shortcode_in_post_context( $string, $post_id ) {
		if ( ! is_string( $string ) || $string === '' ) {
			return $string;
		}

		global $post;
		$__prev_post = $post;

		$post = get_post( $post_id );
		if ( $post instanceof WP_Post ) {
			setup_postdata( $post );
		}

		$out = do_shortcode( $string );

		wp_reset_postdata();
		$post = $__prev_post;

		return $out;
	}
}

/* ============================================================
 * Helper: Get Yoast Focus Keyphrase (+ synonyms if available)
 * ============================================================ */
if ( ! function_exists( 'ssseo_get_yoast_focus_keyphrase' ) ) {
	/**
	 * Returns: ['keyphrase' => string, 'synonyms' => string]
	 *
	 * @param int $post_id
	 * @return array
	 */
	function ssseo_get_yoast_focus_keyphrase( $post_id ) {
		$keyphrase = '';
		$synonyms  = '';

		// Preferred: Yoast API
		if ( function_exists( 'WPSEO_Meta' ) && class_exists( 'WPSEO_Meta' ) ) {
			$keyphrase = (string) WPSEO_Meta::get_value( 'focuskw', $post_id );
			// Yoast Premium can store synonyms as JSON or a delimited string
			$synonyms  = (string) WPSEO_Meta::get_value( 'focuskeywords', $post_id );
		}

		// Fallback direct meta
		if ( $keyphrase === '' ) {
			$keyphrase = (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
		}
		if ( $synonyms === '' ) {
			$synonyms = (string) get_post_meta( $post_id, '_yoast_wpseo_focuskeywords', true );
			if ( $synonyms === '' ) {
				$synonyms = (string) get_post_meta( $post_id, 'focuskeywords', true );
			}
		}

		// Flatten JSON if present
		if ( $synonyms !== '' && substr( $synonyms, 0, 1 ) === '[' ) {
			$decoded = json_decode( $synonyms, true );
			if ( is_array( $decoded ) ) {
				$flat = [];
				foreach ( $decoded as $item ) {
					if ( is_array( $item ) && isset( $item['keyword'] ) ) {
						$flat[] = (string) $item['keyword'];
					} elseif ( is_string( $item ) ) {
						$flat[] = $item;
					}
				}
				$synonyms = implode( ', ', array_filter( array_map( 'trim', $flat ) ) );
			}
		}

		return [
			'keyphrase' => trim( (string) $keyphrase ),
			'synonyms'  => trim( (string) $synonyms ),
		];
	}
}

/* ============================================================
 * Optional Shortcode: [yoast_description] (front-end helper)
 * - Expands shortcodes inside Yoast meta description using correct post context.
 * ============================================================ */
if ( ! shortcode_exists( 'yoast_description' ) ) {
	add_shortcode( 'yoast_description', function( $atts = [] ) {
		$post_id = get_the_ID();
		if ( ! $post_id ) return '';

		$desc = '';
		if ( function_exists( 'WPSEO_Meta' ) && class_exists( 'WPSEO_Meta' ) ) {
			$desc = (string) WPSEO_Meta::get_value( 'metadesc', $post_id );
		}

		// Expand shortcodes like [city_state] in correct context; strip tags for safety.
		$desc = myls_do_shortcode_in_post_context( $desc, $post_id );
		return wp_strip_all_tags( $desc, true );
	} );
}

/* ============================================================
 * Filter: Expand shortcodes in Yoast <meta name="description">
 * ============================================================ */
add_filter( 'wpseo_metadesc', function( $desc ) {
	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		$desc    = myls_do_shortcode_in_post_context( (string) $desc, $post_id );
		$desc    = wp_strip_all_tags( $desc, true );
	}
	return $desc;
}, 99 );

/* ============================================================
 * AJAX: Generate Meta Description (AI) — Enhanced
 * - Evaluates shortcodes in current title.
 * - Includes Yoast Focus Keyphrase (+ synonyms).
 * - Includes existing Yoast meta desc (expanded) as context.
 * ============================================================ */
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
	$pt      = $post_id ? get_post_type( $post_id ) : '';
	if ( ! $post_id || ! $pt ) {
		wp_send_json_error( 'Invalid post ID' );
	}

	// Title with shortcodes evaluated in correct context (e.g., [city_state])
	$title_raw  = get_the_title( $post_id );
	$title_eval = myls_do_shortcode_in_post_context( $title_raw, $post_id );

	// Body content (stripped for prompt)
	$content = get_post_field( 'post_content', $post_id );
	$content = is_string( $content ) ? wp_strip_all_tags( $content ) : '';

	// Yoast Focus Keyphrase (+ synonyms)
	$yk       = ssseo_get_yoast_focus_keyphrase( $post_id );
	$focus_kw = $yk['keyphrase'];
	$syns_kw  = $yk['synonyms'];

	// Existing Yoast meta (expanded for more faithful context)
	$yoast_desc = '';
	if ( function_exists( 'WPSEO_Meta' ) && class_exists( 'WPSEO_Meta' ) ) {
		$yoast_desc = (string) WPSEO_Meta::get_value( 'metadesc', $post_id );
	}
	$yoast_desc_eval = myls_do_shortcode_in_post_context( $yoast_desc, $post_id );
	$yoast_desc_eval = wp_strip_all_tags( $yoast_desc_eval, true );

	// Prompt template (option-based; fallback default)
	$template = get_option(
		'ssseo_ai_meta_prompt',
		"Write a concise, compelling, SEO-friendly meta description (max 160 characters) for a %s.\n".
		"Use this evaluated title: “%s”.\n".
		"Primary focus keyphrase: %s\n".
		"Keyphrase synonyms (optional): %s\n".
		"Current meta description (if any): %s\n\n".
		"Context:\n%s\n\n".
		"Rules:\n- Stay within 160 characters.\n- Natural language (no keyword stuffing).\n- Encourage a click with a clear value proposition."
	);

	$prompt = sprintf(
		$template,
		$pt,
		$title_eval !== '' ? $title_eval : $title_raw,
		$focus_kw !== '' ? $focus_kw : '(none provided)',
		$syns_kw  !== '' ? $syns_kw  : '(none)',
		$yoast_desc_eval !== '' ? $yoast_desc_eval : '(none)',
		$content
	);

	$result = ssseo_send_openai_request( $prompt, 160 );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( [
		'generated'        => $result,
		'title_input'      => $title_eval,
		'focus_keyphrase'  => $focus_kw,
		'keyphrase_syns'   => $syns_kw,
		'existing_meta'    => $yoast_desc_eval,
	] );
}

/* ============================================================
 * AJAX: Save Meta Description into Yoast
 * - Store RAW text (do NOT expand shortcodes here).
 * - Expansion happens at render time via filter above.
 * ============================================================ */
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

	if ( ! $post_id || $text === '' ) {
		wp_send_json_error( 'Invalid input' );
	}

	// Save RAW so shortcodes can change dynamically over time.
	update_post_meta( $post_id, '_yoast_wpseo_metadesc', $text );

	wp_send_json_success();
}

/* ============================================================
 * AJAX: Generate SEO Title (AI) — shortcode-aware + keyphrase
 * ============================================================ */
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
	$pt      = $post_id ? get_post_type( $post_id ) : '';
	if ( ! $post_id || ! $pt ) {
		wp_send_json_error( 'Invalid post ID' );
	}

	// Current title with shortcodes evaluated in context
	$title_raw  = get_the_title( $post_id );
	$title_eval = myls_do_shortcode_in_post_context( $title_raw, $post_id );

	// Content context (stripped)
	$content = get_post_field( 'post_content', $post_id );
	$content = is_string( $content ) ? wp_strip_all_tags( $content ) : '';

	// Yoast focus keyphrase (+ synonyms)
	$yk    = ssseo_get_yoast_focus_keyphrase( $post_id );
	$focus = $yk['keyphrase'];
	$syns  = $yk['synonyms'];

	// Prompt template (option-based; fallback default)
	$template = get_option(
		'ssseo_ai_title_prompt',
		"Write a concise, SEO-friendly title (between 90 and 120 characters) for a %s.\n".
		"Use this existing title “%s” and content as context.\n".
		"Primary focus keyphrase: %s\n".
		"Keyphrase synonyms (optional): %s\n\n".
		"Context:\n%s"
	);

	$prompt = sprintf(
		$template,
		$pt,
		$title_eval !== '' ? $title_eval : $title_raw,
		$focus !== '' ? $focus : '(none provided)',
		$syns  !== '' ? $syns  : '(none)',
		$content
	);

	$result = ssseo_send_openai_request( $prompt, 160 );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( [
		'generated'       => $result,
		'title_input'     => $title_eval,
		'focus_keyphrase' => $focus,
		'keyphrase_syns'  => $syns,
	] );
}

/* ============================================================
 * AJAX: Save Yoast Title
 * - Store RAW text (no do_shortcode); render-time expansion is safer.
 * ============================================================ */
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

	if ( ! $post_id || $text === '' ) {
		wp_send_json_error( 'Invalid input' );
	}

	// Save RAW so shortcodes in title (if any) can be expanded later if you decide to filter wpseo_title.
	update_post_meta( $post_id, '_yoast_wpseo_title', $text );

	wp_send_json_success();
}

/* ============================================================
 * Shared: Send Prompt to OpenAI and Return Result
 * ============================================================ */
if ( ! function_exists( 'ssseo_send_openai_request' ) ) {
	/**
	 * @param string $prompt
	 * @param int    $max_tokens
	 * @return string|WP_Error
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
			'body' => wp_json_encode( [
				'model'     => 'gpt-4o',
				'messages'  => [
					[ 'role' => 'system', 'content' => 'You are an expert SEO assistant and Copywriter that specializes in marketing.' ],
					[ 'role' => 'user',   'content' => $prompt ],
				],
				'max_tokens'  => (int) $max_tokens,
				'temperature' => 0.7,
			] ),
			'timeout' => 30,
		] );

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
}

/* ============================================================
 * BONUS: Hardened [city_state] (optional, if not already registered)
 * - Works even when global $post isn’t set by allowing id="".
 * ============================================================ */
if ( ! shortcode_exists( 'city_state' ) ) {
	add_shortcode( 'city_state', function( $atts ) {
		$atts    = shortcode_atts( [ 'id' => 0, 'meta' => 'city_state' ], $atts, 'city_state' );
		$post_id = intval( $atts['id'] );
		if ( ! $post_id ) {
			$post_id = get_the_ID() ?: ( isset( $GLOBALS['post']->ID ) ? intval( $GLOBALS['post']->ID ) : 0 );
		}
		$val = $post_id ? get_post_meta( $post_id, $atts['meta'], true ) : '';
		return $val !== '' ? esc_html( $val ) : '';
	} );
}
