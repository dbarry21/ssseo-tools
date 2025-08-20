<?php
// File: admin/ajax.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Utility: simple permission + nonce check
 * - Default cap: edit_posts (suitable for most bulk ops)
 * - Optionally pass a nonce key + value to verify
 */
function ssseo_check_cap_and_nonce( $nonce_action_key = '', $nonce_field_value = '' ) {
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Insufficient permissions' );
    }
    if ( $nonce_action_key ) {
        if ( empty( $nonce_field_value ) || ! wp_verify_nonce( $nonce_field_value, $nonce_action_key ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }
    }
}

/**
 * Return <option> list HTML for a given post type.
 * action: ssseo_get_posts_by_type
 * POST: post_type
 */
add_action( 'wp_ajax_ssseo_get_posts_by_type', function() {
    ssseo_check_cap_and_nonce(); // no specific nonce used by caller
    $pt = sanitize_key( $_POST['post_type'] ?? '' );
    if ( ! $pt ) wp_send_json_success( '' );

    $posts = get_posts( [
        'post_type'        => $pt,
        'post_status'      => 'publish',
        'posts_per_page'   => -1,
        'orderby'          => 'title',
        'order'            => 'ASC',
        'fields'           => 'ids',
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ] );

    ob_start();
    if ( $posts ) {
        foreach ( $posts as $pid ) {
            $title = get_the_title( $pid );
            echo '<option value="'.esc_attr( $pid ).'">'.esc_html( $title .' (ID '.$pid.')' ).'</option>';
        }
    }
    $html = ob_get_clean();
    wp_send_json_success( $html );
});

/**
 * Bulk: Set Yoast robots to index,follow
 * action: ssseo_yoast_set_index_follow
 * POST: post_ids[], _wpnonce (optional; validated if present)
 */
add_action( 'wp_ajax_ssseo_yoast_set_index_follow', function() {
    $nonce = $_POST['_wpnonce'] ?? '';
    if ( $nonce ) ssseo_check_cap_and_nonce( 'ssseo_admin_nonce', $nonce ); else ssseo_check_cap_and_nonce();

    $ids = isset($_POST['post_ids']) ? array_map( 'intval', (array) $_POST['post_ids'] ) : [];
    if ( empty( $ids ) ) wp_send_json_error( 'No posts selected' );

    foreach ( $ids as $pid ) {
        update_post_meta( $pid, '_yoast_wpseo_meta-robots-noindex', '0' );  // index
        update_post_meta( $pid, '_yoast_wpseo_meta-robots-nofollow', '0' ); // follow
    }
    wp_send_json_success( 'Updated index,follow on '.count($ids).' posts.' );
});

/**
 * Bulk: Reset canonical to Yoast default (delete custom canonical)
 * action: ssseo_bulk_reset_canonical
 * POST: post_ids[], _wpnonce (optional)
 */
add_action( 'wp_ajax_ssseo_bulk_reset_canonical', function() {
    $nonce = $_POST['_wpnonce'] ?? '';
    if ( $nonce ) ssseo_check_cap_and_nonce( 'ssseo_admin_nonce', $nonce ); else ssseo_check_cap_and_nonce();

    $ids = isset($_POST['post_ids']) ? array_map( 'intval', (array) $_POST['post_ids'] ) : [];
    if ( empty( $ids ) ) wp_send_json_error( 'No posts selected' );

    foreach ( $ids as $pid ) {
        delete_post_meta( $pid, '_yoast_wpseo_canonical' );
    }
    wp_send_json_success( 'Canonical reset on '.count($ids).' posts.' );
});

/**
 * Bulk: Clear canonical (explicitly set to empty)
 * action: ssseo_bulk_clear_canonical
 * POST: post_ids[], _wpnonce (optional)
 */
add_action( 'wp_ajax_ssseo_bulk_clear_canonical', function() {
    $nonce = $_POST['_wpnonce'] ?? '';
    if ( $nonce ) ssseo_check_cap_and_nonce( 'ssseo_admin_nonce', $nonce ); else ssseo_check_cap_and_nonce();

    $ids = isset($_POST['post_ids']) ? array_map( 'intval', (array) $_POST['post_ids'] ) : [];
    if ( empty( $ids ) ) wp_send_json_error( 'No posts selected' );

    foreach ( $ids as $pid ) {
        update_post_meta( $pid, '_yoast_wpseo_canonical', '' );
    }
    wp_send_json_success( 'Canonical cleared on '.count($ids).' posts.' );
});

/**
 * Optional: YouTube fix action (stub).
 * action: ssseo_fix_youtube_iframes
 */
add_action( 'wp_ajax_ssseo_fix_youtube_iframes', function() {
    ssseo_check_cap_and_nonce();
    wp_send_json_success( 'YouTube iframe pass complete.' );
});


/**
 * Clone a source service_area to multiple parent service_areas (as children).
 *
 * action: ssseo_clone_sa_to_parents
 * POST:
 *   - nonce, source_id, target_parent_ids[], as_draft, skip_existing, debug, new_slug (optional), focus_base (optional)
 */
add_action('wp_ajax_ssseo_clone_sa_to_parents','ssseo_clone_service_area_to_parents_handler');
function ssseo_clone_service_area_to_parents_handler(){
    $nonce = $_POST['nonce'] ?? '';
    if ( ! current_user_can('edit_posts') ) wp_send_json_error('Insufficient permissions');
    if ( empty($nonce) || ! wp_verify_nonce($nonce,'ssseo_bulk_clone_sa') ) wp_send_json_error('Invalid nonce');

    // Require Yoast Duplicate Post API
    if ( ! function_exists('duplicate_post_create_duplicate') ) {
        wp_send_json_error('Yoast Duplicate Post is not active. Please install/activate it to use this bulk clone.');
    }

    $source_id   = isset($_POST['source_id']) ? intval($_POST['source_id']) : 0;
    $target_ids  = isset($_POST['target_parent_ids']) ? array_map('intval', (array) $_POST['target_parent_ids']) : [];
    $as_draft    = !empty($_POST['as_draft']);
    $skip_exist  = !empty($_POST['skip_existing']);
    $debug       = !empty($_POST['debug']);
    $new_slug_in = isset($_POST['new_slug']) ? sanitize_title( wp_unslash($_POST['new_slug']) ) : '';
    $focus_base  = isset($_POST['focus_base']) ? sanitize_text_field( wp_unslash($_POST['focus_base']) ) : '';

    if (!$source_id || empty($target_ids)) wp_send_json_error('Missing source or targets');

    $source = get_post($source_id);
    if (!$source || $source->post_type !== 'service_area') wp_send_json_error('Invalid source post');

    // Helper: replace [acf field="city_state"] if shortcode can't run
    $replace_citystate_shortcode = function($text, $city){
        if(!is_string($text) || $text==='') return $text;
        return preg_replace('/\[acf\s+field\s*=\s*(["\'])city_state\1\s*\]/i', (string)$city, $text);
    };

    // Helper: render shortcodes as if $post_id is the current post
    $render_with_post_context = function($text, $post_id){
        if(!is_string($text) || $text==='') return $text;
        if(!function_exists('do_shortcode')) return $text;
        global $post;
        $old_post = isset($post) ? $post : null;
        $post = get_post($post_id);
        if($post){
            setup_postdata($post);
            $out = do_shortcode($text);
            wp_reset_postdata();
        }else{
            $out = $text;
        }
        $out = wp_strip_all_tags( $out, true );
        return trim( $out );
    };

    $log = [];

    foreach($target_ids as $parent_id){
        $parent = get_post($parent_id);
        if(!$parent || $parent->post_type !== 'service_area' || (int)$parent->post_parent !== 0){
            $log[] = "Skipping target $parent_id: not a top-level service_area.";
            continue;
        }

        // Parent city_state used for duplicate check and meta setting
        $parent_city_state = function_exists('get_field') ? get_field('city_state', $parent_id) : get_post_meta($parent_id, 'city_state', true);
        $parent_city_state = is_string($parent_city_state) ? $parent_city_state : '';

        // Precompute a fallback "final" title (used for duplicate check) by direct replace
        $fallback_title = $replace_citystate_shortcode($source->post_title, $parent_city_state);

        // Optional duplicate guard (by FINAL title under this parent)
        if ($skip_exist) {
            $exists = false;
            $existing = get_posts([
                'post_type'        => 'service_area',
                'post_status'      => 'any',
                'posts_per_page'   => 1,
                'post_parent'      => $parent_id,
                'title'            => $fallback_title,
                'fields'           => 'ids',
                'suppress_filters' => true,
            ]);
            if (!empty($existing)) {
                $exists = true;
            } else {
                $children = get_children([
                    'post_parent' => $parent_id,
                    'post_type'   => 'service_area',
                    'post_status' => 'any',
                    'fields'      => 'ids',
                ]);
                if ($children) {
                    foreach($children as $cid){
                        if (get_the_title($cid) === $fallback_title) { $exists = true; break; }
                    }
                }
            }
            if ($exists) {
                $log[] = "Skipped: Child with same final title already exists under parent {$parent->ID}.";
                continue;
            }
        }

        // Duplicate using Yoast engine
        $status = $as_draft ? 'draft' : $source->post_status;
        $new_id = duplicate_post_create_duplicate( $source, $status, $parent_id );
        if ( is_wp_error($new_id) || ! $new_id ) {
            $log[] = "Error cloning to parent {$parent->ID}: could not duplicate (Yoast).";
            continue;
        }

        // Set ACF/meta city_state from the parent on the clone (so shortcodes can resolve against the clone)
        if ($parent_city_state !== '') {
            if ( function_exists('update_field') ) update_field('city_state', $parent_city_state, $new_id);
            else update_post_meta($new_id, 'city_state', $parent_city_state);
        }

        // Render the SOURCE title's shortcodes **in the context of the NEW post**
        $rendered_title = $render_with_post_context( $source->post_title, $new_id );
        if ($rendered_title === '') {
            $rendered_title = $replace_citystate_shortcode($source->post_title, $parent_city_state);
        }

        // Update title/slug/parent/status on the clone
        $update = [
            'ID'          => $new_id,
            'post_title'  => $rendered_title,
            'post_parent' => $parent_id,
            'post_status' => $status,
        ];
        if ($new_slug_in) $update['post_name'] = $new_slug_in;
        wp_update_post($update);

        // ----- Yoast Focus Keyphrase (remove commas from city_state) -----
        if ($focus_base !== '') {
            $city_state_clean = str_replace(',', '', $parent_city_state);
            $focus = trim($focus_base . ' ' . ($city_state_clean ?: ''));

            // Clear possible carry-overs first
            delete_post_meta($new_id, '_yoast_wpseo_focuskw');
            delete_post_meta($new_id, '_yoast_wpseo_focuskw_text_input');
            delete_post_meta($new_id, '_yoast_wpseo_focuskeywords');
            delete_post_meta($new_id, 'yoast_wpseo_focuskw');
            delete_post_meta($new_id, 'yoast_wpseo_focuskw_text_input');

            // Write new primary keyphrase
            update_post_meta($new_id, '_yoast_wpseo_focuskw', $focus);
            update_post_meta($new_id, '_yoast_wpseo_focuskw_text_input', $focus);
            update_post_meta($new_id, 'yoast_wpseo_focuskw', $focus);
            update_post_meta($new_id, 'yoast_wpseo_focuskw_text_input', $focus);

            // Nudge Yoast indexables/watchers
            wp_update_post(['ID'=>$new_id]);
        }

        // Safety: ensure terms & thumbnail (Yoast usually handles these)
        $taxes = get_object_taxonomies('service_area');
        foreach ($taxes as $tax) {
            $terms = wp_get_object_terms($source_id, $tax, ['fields'=>'ids']);
            if (!is_wp_error($terms) && !empty($terms)) wp_set_object_terms($new_id, $terms, $tax, false);
        }
        $thumb_id = get_post_thumbnail_id($source_id);
        if ($thumb_id && !get_post_thumbnail_id($new_id)) set_post_thumbnail($new_id, $thumb_id);

        $log[] = sprintf(
            'Cloned via Yoast → New child ID %d under "%s" (ID %d). Final title="%s"%s',
            $new_id,
            $parent->post_title,
            $parent->ID,
            get_the_title($new_id),
            $new_slug_in ? " (slug: {$new_slug_in})" : ''
        );
    }

    wp_send_json_success(['log'=>$log]);
}

/**
 * Bulk: Apply canonical of one source post to many target posts.
 * action: ssseo_bulk_set_canonical
 * POST: nonce (ssseo_bulk_ops), source_id, target_ids[]
 */
add_action('wp_ajax_ssseo_bulk_set_canonical', function () {
    $nonce = $_POST['nonce'] ?? '';
    ssseo_check_cap_and_nonce( 'ssseo_bulk_ops', $nonce );

    $source_id  = isset($_POST['source_id']) ? intval($_POST['source_id']) : 0;
    $target_ids = isset($_POST['target_ids']) ? array_map('intval', (array) $_POST['target_ids']) : [];

    if ( ! $source_id || empty($target_ids) ) {
        wp_send_json_error('Missing source or targets.');
    }

    $canonical = get_permalink( $source_id );
    if ( ! $canonical ) {
        wp_send_json_error('Could not resolve source permalink.');
    }

    $canonical = esc_url_raw( $canonical );
    $meta_key  = '_yoast_wpseo_canonical';
    $details   = [];
    $updated   = 0;

    foreach ( $target_ids as $tid ) {
        if ( $tid <= 0 ) continue;
        update_post_meta( $tid, $meta_key, $canonical );
        $details[] = sprintf( 'Set canonical on #%d to %s', $tid, $canonical );
        $updated++;
    }

    wp_send_json_success([
        'message' => sprintf('Applied canonical from #%d to %d target(s).', $source_id, $updated),
        'details' => $details,
    ]);
});

/**
 * List ALL service_area posts (id, title, parent) – resilient to admin filters.
 * action: ssseo_sa_all
 * POST: nonce (ssseo_bulk_ops)
 */
add_action('wp_ajax_ssseo_sa_all', function () {
    $nonce = $_POST['nonce'] ?? '';
    ssseo_check_cap_and_nonce('ssseo_bulk_ops', $nonce);

    $ids = get_posts([
        'post_type'        => 'service_area',
        'post_status'      => ['publish','draft','pending','future','private'],
        'posts_per_page'   => -1,
        'orderby'          => 'title',
        'order'            => 'ASC',
        'suppress_filters' => true,
        'fields'           => 'ids',
        'no_found_rows'    => true,
    ]);

    $items = [];
    foreach ($ids as $pid) {
        $items[] = [
            'id'     => $pid,
            'title'  => get_the_title($pid),
            'parent' => (int) get_post_field('post_parent', $pid),
        ];
    }
    wp_send_json_success(['items' => $items]);
});

/**
 * List TOP-LEVEL service_area parents only (parent = 0)
 * action: ssseo_sa_top_parents
 * POST: nonce (ssseo_bulk_ops)
 */
add_action('wp_ajax_ssseo_sa_top_parents', function () {
    $nonce = $_POST['nonce'] ?? '';
    ssseo_check_cap_and_nonce('ssseo_bulk_ops', $nonce);

    $ids = get_posts([
        'post_type'        => 'service_area',
        'post_status'      => ['publish','draft','pending','future','private'],
        'post_parent'      => 0,
        'posts_per_page'   => -1,
        'orderby'          => 'title',
        'order'            => 'ASC',
        'suppress_filters' => true,
        'fields'           => 'ids',
        'no_found_rows'    => true,
    ]);

    $items = [];
    foreach ($ids as $pid) {
        $items[] = [
            'id'     => $pid,
            'title'  => get_the_title($pid),
            'parent' => 0,
        ];
    }
    wp_send_json_success(['items' => $items]);
});

/**
 * Test YouTube API Key + Channel ID by calling channels.list
 * action: ssseo_test_youtube_api
 * POST: nonce (ssseo_siteoptions_ajax)
 */
add_action('wp_ajax_ssseo_test_youtube_api', function () {
    // Use a stricter cap for settings tests
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Insufficient permissions');
    $nonce = $_POST['nonce'] ?? '';
    if ( empty($nonce) || ! wp_verify_nonce($nonce, 'ssseo_siteoptions_ajax') ) {
        wp_send_json_error('Invalid nonce');
    }

    $key  = trim(get_option('ssseo_youtube_api_key', ''));
    $chan = trim(get_option('ssseo_youtube_channel_id', ''));

    if (!$key || !$chan) {
        update_option('ssseo_youtube_test_result', 'Missing key/channel • ' . current_time('mysql'));
        wp_send_json_error('YouTube API Key or Channel ID is missing.');
    }

    $url = add_query_arg([
        'part' => 'id,snippet',
        'id'   => $chan,
        'key'  => $key,
    ], 'https://www.googleapis.com/youtube/v3/channels');

    $resp = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($resp)) {
        $msg = 'Request error: ' . $resp->get_error_message();
        update_option('ssseo_youtube_test_result', $msg . ' • ' . current_time('mysql'));
        wp_send_json_error($msg);
    }

    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);

    if ($code !== 200) {
        $err = isset($body['error']['message']) ? $body['error']['message'] : ('HTTP ' . $code);
        $msg = 'YouTube API error: ' . $err;
        update_option('ssseo_youtube_test_result', $msg . ' • ' . current_time('mysql'));
        wp_send_json_error($msg);
    }

    $items = isset($body['items']) ? (array)$body['items'] : [];
    if (!count($items)) {
        $msg = 'Channel not found. Check Channel ID.';
        update_option('ssseo_youtube_test_result', $msg . ' • ' . current_time('mysql'));
        wp_send_json_error($msg);
    }

    $title = $items[0]['snippet']['title'] ?? '';
    $okmsg = 'YouTube OK' . ($title ? (': “' . $title . '”') : '') . ' • ' . current_time('mysql');
    update_option('ssseo_youtube_test_result', $okmsg);
    wp_send_json_success($okmsg);
});

/**
 * Check GSC client configuration presence (no OAuth call yet)
 * action: ssseo_test_gsc_client
 * POST: nonce (ssseo_siteoptions_ajax)
 */
add_action('wp_ajax_ssseo_test_gsc_client', function () {
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Insufficient permissions');
    $nonce = $_POST['nonce'] ?? '';
    if ( empty($nonce) || ! wp_verify_nonce($nonce, 'ssseo_siteoptions_ajax') ) {
        wp_send_json_error('Invalid nonce');
    }

    $cid   = trim(get_option('ssseo_gsc_client_id', ''));
    $csec  = trim(get_option('ssseo_gsc_client_secret', ''));
    $redir = trim(get_option('ssseo_gsc_redirect_uri', ''));

    if (!$cid || !$csec || !$redir) {
        $msg = 'Enter Client ID, Client Secret, and Redirect URI.';
        update_option('ssseo_gsc_test_result', $msg . ' • ' . current_time('mysql'));
        wp_send_json_error($msg);
    }

    // Optional: reflect token presence if you add OAuth later
    $token = get_option('ssseo_gsc_token');
    if ($token && is_array($token) && !empty($token['access_token'])) {
        $ok = 'GSC client configured • Connected (token present) • ' . current_time('mysql');
        update_option('ssseo_gsc_test_result', $ok);
        wp_send_json_success($ok);
    } else {
        $ok = 'GSC client configured • Not connected yet (OAuth needed) • ' . current_time('mysql');
        update_option('ssseo_gsc_test_result', $ok);
        wp_send_json_success($ok);
    }
});

/**
 * Test OpenAI key (cheap endpoint)
 * action: ssseo_test_openai_key
 * POST: key
 */
add_action('wp_ajax_ssseo_test_openai_key', function(){
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error('Insufficient permissions');
    }

    $key = isset($_POST['key']) ? trim((string) $_POST['key']) : '';
    if ($key === '') {
        update_option('ssseo_openai_test_result', 'Missing key • ' . current_time('mysql'));
        wp_send_json_error('OpenAI API key is required.');
    }

    // Call a very cheap endpoint: list models
    $resp = wp_remote_get('https://api.openai.com/v1/models', [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
        ],
    ]);

    if (is_wp_error($resp)) {
        $msg = 'HTTP request failed: ' . $resp->get_error_message();
        update_option('ssseo_openai_test_result', $msg . ' • ' . current_time('mysql'));
        wp_send_json_error($msg);
    }

    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    $json = json_decode($body, true);

    if ($code === 200) {
        $ok = 'OpenAI OK • ' . current_time('mysql');
        update_option('ssseo_openai_test_result', $ok);
        wp_send_json_success($ok);
    }

    $detail = $json['error']['message'] ?? ('HTTP ' . $code);
    $msg = 'OpenAI API error: ' . $detail;
    update_option('ssseo_openai_test_result', $msg . ' • ' . current_time('mysql'));
    wp_send_json_error($msg);
});

/**
 * Test Google Static Maps API key (image probe)
 * action: ssseo_test_maps_key
 * POST: key
 */
add_action('wp_ajax_ssseo_test_maps_key', function(){
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error('Insufficient permissions');
    }

    $key = isset($_POST['key']) ? trim((string) $_POST['key']) : '';
    if ($key === '') {
        update_option('ssseo_maps_test_result', 'Missing key • ' . current_time('mysql'));
        wp_send_json_error('Google Static Maps API key is required.');
    }

    $url = add_query_arg([
        'center' => '0,0',
        'zoom'   => '1',
        'size'   => '100x100',
        'key'    => $key,
    ], 'https://maps.googleapis.com/maps/api/staticmap');

    $resp = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($resp)) {
        $msg = 'HTTP request failed: ' . $resp->get_error_message();
        update_option('ssseo_maps_test_result', $msg . ' • ' . current_time('mysql'));
        wp_send_json_error($msg);
    }

    $code  = wp_remote_retrieve_response_code($resp);
    $ctype = wp_remote_retrieve_header($resp, 'content-type');
    $body  = wp_remote_retrieve_body($resp);

    if ($code === 200 && is_string($ctype) && stripos($ctype, 'image/') !== false) {
        $ok = 'Maps OK • ' . current_time('mysql');
        update_option('ssseo_maps_test_result', $ok);
        wp_send_json_success($ok);
    }

    $json   = json_decode($body, true);
    $detail = $json['error_message'] ?? ('HTTP ' . $code);
    $msg    = 'Google Static Maps error: ' . $detail;
    update_option('ssseo_maps_test_result', $msg . ' • ' . current_time('mysql'));
    wp_send_json_error($msg);
});

// ===== GSC helpers + URL Inspection handler =====

if (!function_exists('ssseo_gsc_get_access_token')) {
  /**
   * Get a valid GSC access token; refresh if needed.
   * Expects options:
   *  - ssseo_gsc_token: array{access_token, refresh_token, expires_at?}
   *  - ssseo_gsc_client_id / ssseo_gsc_client_secret
   */
  function ssseo_gsc_get_access_token() {
    $tok = get_option('ssseo_gsc_token');
    if (!is_array($tok)) {
      return new WP_Error('no_token', 'No Google token stored. Connect in Site Options.');
    }

    $access  = $tok['access_token']  ?? '';
    $refresh = $tok['refresh_token'] ?? '';
    $exp_at  = isset($tok['expires_at']) ? intval($tok['expires_at']) : 0;

    // If expires_at not set but "created"+"expires_in" exist, derive it once.
    if (!$exp_at && isset($tok['created'], $tok['expires_in'])) {
      $exp_at = intval($tok['created']) + intval($tok['expires_in']) - 60;
    }

    $now = time();
    if ($access && $exp_at && $now < $exp_at) {
      return $access; // still valid
    }

    // Need refresh
    if (!$refresh) {
      return new WP_Error('no_refresh', 'Missing refresh token; re-connect Google.');
    }
    $cid  = trim(get_option('ssseo_gsc_client_id', ''));
    $csec = trim(get_option('ssseo_gsc_client_secret', ''));
    if (!$cid || !$csec) {
      return new WP_Error('no_client', 'Missing Client ID/Secret; update Site Options.');
    }

    $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
      'timeout' => 20,
      'body'    => [
        'grant_type'    => 'refresh_token',
        'client_id'     => $cid,
        'client_secret' => $csec,
        'refresh_token' => $refresh,
      ],
    ]);
    if (is_wp_error($resp)) {
      return $resp;
    }
    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code !== 200 || !isset($body['access_token'])) {
      $msg = $body['error_description'] ?? ($body['error'] ?? ('HTTP '.$code));
      return new WP_Error('refresh_fail', 'Token refresh failed: ' . $msg);
    }

    $access = $body['access_token'];
    $expires_in = isset($body['expires_in']) ? intval($body['expires_in']) : 3600;
    $tok['access_token'] = $access;
    $tok['expires_at']   = time() + $expires_in - 60;
    // Some responses don’t include refresh_token on refresh — keep the old one.
    update_option('ssseo_gsc_token', $tok);

    return $access;
  }
}

/**
 * Inspect a selected post’s URL using the GSC URL Inspection API
 * action: ssseo_gsc_inspect_post
 * POST: nonce (ssseo_gsc_ops), post_id, site_url
 */
add_action('wp_ajax_ssseo_gsc_inspect_post', function () {
  if ( ! current_user_can('manage_options') ) {
    wp_send_json_error('Insufficient permissions');
  }
  $nonce = $_POST['nonce'] ?? '';
  if ( empty($nonce) || ! wp_verify_nonce($nonce, 'ssseo_gsc_ops') ) {
    wp_send_json_error('Invalid nonce');
  }

  $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
  $siteUrl = isset($_POST['site_url']) ? esc_url_raw( wp_unslash($_POST['site_url']) ) : '';
  if (!$post_id || !$siteUrl) {
    wp_send_json_error('Missing post or property (siteUrl).');
  }

  $url = get_permalink($post_id);
  if (!$url) {
    wp_send_json_error('Could not resolve permalink for the selected post.');
  }

  // Get/refresh token
  $token = ssseo_gsc_get_access_token();
  if (is_wp_error($token)) {
    wp_send_json_error($token->get_error_message());
  }

  // Call URL Inspection API
  $endpoint = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
  $resp = wp_remote_post($endpoint, [
    'timeout' => 25,
    'headers' => [
      'Authorization' => 'Bearer ' . $token,
      'Content-Type'  => 'application/json',
    ],
    'body' => wp_json_encode([
      'inspectionUrl' => $url,
      'siteUrl'       => $siteUrl,
      // 'languageCode' => 'en-US', // optional
    ]),
  ]);

  if (is_wp_error($resp)) {
    wp_send_json_error('HTTP error: ' . $resp->get_error_message());
  }

  $code = wp_remote_retrieve_response_code($resp);
  $body = json_decode(wp_remote_retrieve_body($resp), true);

  if ($code !== 200) {
    $msg = $body['error']['message'] ?? ('HTTP ' . $code);
    wp_send_json_error('GSC API error: ' . $msg);
  }

  // Parse useful fields
  $res = $body['inspectionResult'] ?? [];
  $idx = $res['indexStatusResult'] ?? [];

  $coverage        = $idx['coverageState']    ?? '';
  $indexingState   = $idx['indexingState']    ?? '';
  $lastCrawl       = $idx['lastCrawlTime']    ?? '';
  $googleCanonical = $idx['googleCanonical']  ?? '';
  $userCanonical   = $idx['userCanonical']    ?? '';
  $pageFetchState  = $idx['pageFetchState']   ?? '';
  $robotsTxtState  = $idx['robotsTxtState']   ?? '';
  $sitemaps        = $idx['sitemaps']         ?? [];

  // Simple heuristic for "indexed"
  $is_indexed = (!empty($googleCanonical) && stripos((string)$coverage, 'index') !== false);

  wp_send_json_success([
    'inspectionUrl'  => $res['inspectionUrl'] ?? $url,
    'siteUrl'        => $res['siteUrl'] ?? $siteUrl,
    'coverage'       => $coverage,
    'indexingState'  => $indexingState,
    'lastCrawlTime'  => $lastCrawl,
    'googleCanonical'=> $googleCanonical,
    'userCanonical'  => $userCanonical,
    'pageFetchState' => $pageFetchState,
    'robotsTxtState' => $robotsTxtState,
    'sitemaps'       => $sitemaps,
    'is_indexed'     => $is_indexed ? true : false,
    'raw'            => $idx, // for the Raw panel
  ]);
});

// === OAuth: exchange code → token and store ===
if (!function_exists('ssseo_gsc_store_token')) {
  function ssseo_gsc_store_token($body) {
    $token = [
      'access_token'  => $body['access_token']  ?? '',
      'refresh_token' => $body['refresh_token'] ?? '',
      'expires_at'    => time() + (int)($body['expires_in'] ?? 3600) - 60,
      'scope'         => $body['scope'] ?? '',
      'token_type'    => $body['token_type'] ?? 'Bearer',
      'created'       => time(),
    ];
    update_option('ssseo_gsc_token', $token);
    return $token;
  }
}

/**
 * OAuth callback: /wp-admin/admin-post.php?action=ssseo_gsc_oauth_cb
 * GET: code, state | error
 */
add_action('admin_post_ssseo_gsc_oauth_cb', function () {
  if ( ! current_user_can('manage_options') ) {
    wp_die('Insufficient permissions');
  }

  if (isset($_GET['error'])) {
    wp_safe_redirect( add_query_arg([
      'page'=>'ssseo-tools','tab'=>'gsc','subtab'=>'connect','gerr'=>sanitize_text_field($_GET['error'])
    ], admin_url('admin.php')) );
    exit;
  }

  $code  = isset($_GET['code'])  ? sanitize_text_field($_GET['code'])  : '';
  $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
  $saved_state = get_user_meta(get_current_user_id(), 'ssseo_gsc_oauth_state', true);

  if (!$code || !$state || !$saved_state || ! hash_equals($saved_state, $state)) {
    wp_safe_redirect( add_query_arg([
      'page'=>'ssseo-tools','tab'=>'gsc','subtab'=>'connect','gerr'=>'invalid_state'
    ], admin_url('admin.php')) );
    exit;
  }
  delete_user_meta(get_current_user_id(), 'ssseo_gsc_oauth_state');

  $cid   = trim(get_option('ssseo_gsc_client_id', ''));
  $csec  = trim(get_option('ssseo_gsc_client_secret', ''));
  $redir = trim(get_option('ssseo_gsc_redirect_uri', admin_url('admin-post.php?action=ssseo_gsc_oauth_cb')));

  if (!$cid || !$csec || !$redir) {
    wp_safe_redirect( add_query_arg([
      'page'=>'ssseo-tools','tab'=>'gsc','subtab'=>'connect','gerr'=>'missing_client'
    ], admin_url('admin.php')) );
    exit;
  }

  // Exchange code for tokens
  $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
    'timeout' => 20,
    'body'    => [
      'grant_type'    => 'authorization_code',
      'code'          => $code,
      'redirect_uri'  => $redir,
      'client_id'     => $cid,
      'client_secret' => $csec,
    ],
  ]);

  if (is_wp_error($resp)) {
    wp_safe_redirect( add_query_arg([
      'page'=>'ssseo-tools','tab'=>'gsc','subtab'=>'connect','gerr'=>'http_error'
    ], admin_url('admin.php')) );
    exit;
  }

  $code_http = wp_remote_retrieve_response_code($resp);
  $body = json_decode(wp_remote_retrieve_body($resp), true);

  if ($code_http !== 200 || empty($body['access_token'])) {
    $err = isset($body['error_description']) ? $body['error_description'] : ($body['error'] ?? ('HTTP '.$code_http));
    wp_safe_redirect( add_query_arg([
      'page'=>'ssseo-tools','tab'=>'gsc','subtab'=>'connect','gerr'=>rawurlencode($err)
    ], admin_url('admin.php')) );
    exit;
  }

  ssseo_gsc_store_token($body);
  wp_safe_redirect( add_query_arg([
    'page'=>'ssseo-tools','tab'=>'gsc','subtab'=>'connect','gok'=>'1'
  ], admin_url('admin.php')) );
  exit;
});

/**
 * Disconnect: remove stored token
 * POST: nonce (ssseo_gsc_disconnect)
 */
add_action('admin_post_ssseo_gsc_disconnect', function(){
  if (! current_user_can('manage_options')) {
    wp_die('Insufficient permissions');
  }
  $nonce = $_POST['nonce'] ?? '';
  if (empty($nonce) || ! wp_verify_nonce($nonce, 'ssseo_gsc_disconnect')) {
    wp_die('Invalid nonce');
  }
  delete_option('ssseo_gsc_token');
  wp_safe_redirect( add_query_arg(['page'=>'ssseo-tools','tab'=>'gsc','subtab'=>'connect','gok'=>'0'], admin_url('admin.php')) );
  exit;
});

/**
 * Ping GSC with current access token (list sites)
 * action: ssseo_gsc_ping
 * POST: nonce (ssseo_siteoptions_ajax)
 */
add_action('wp_ajax_ssseo_gsc_ping', function(){
  if (! current_user_can('manage_options')) wp_send_json_error('Insufficient permissions');
  $nonce = $_POST['nonce'] ?? '';
  if (empty($nonce) || ! wp_verify_nonce($nonce, 'ssseo_siteoptions_ajax')) {
    wp_send_json_error('Invalid nonce');
  }

  // Reuse your token helper (added earlier)
  if (!function_exists('ssseo_gsc_get_access_token')) {
    wp_send_json_error('Token helper missing');
  }
  $tok = ssseo_gsc_get_access_token();
  if (is_wp_error($tok)) {
    wp_send_json_error($tok->get_error_message());
  }

  $resp = wp_remote_get('https://www.googleapis.com/webmasters/v3/sites', [
    'timeout' => 20,
    'headers' => ['Authorization' => 'Bearer ' . $tok],
  ]);

  if (is_wp_error($resp)) {
    wp_send_json_error('HTTP error: ' . $resp->get_error_message());
  }
  $code = wp_remote_retrieve_response_code($resp);
  $body = json_decode(wp_remote_retrieve_body($resp), true);

  if ($code !== 200) {
    $msg = $body['error']['message'] ?? ('HTTP ' . $code);
    wp_send_json_error('API error: ' . $msg);
  }
  wp_send_json_success($body);
});

// Helper: call Search Analytics (Performance) API
if (!function_exists('ssseo_gsc_perf_query')) {
  function ssseo_gsc_perf_query($siteUrl, $body) {
    if (!function_exists('ssseo_gsc_get_access_token')) {
      return new WP_Error('no_helper', 'Token helper missing');
    }
    $tok = ssseo_gsc_get_access_token();
    if (is_wp_error($tok)) return $tok;

    $endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/'. rawurlencode($siteUrl) .'/searchAnalytics/query';
    $resp = wp_remote_post($endpoint, [
      'timeout' => 30,
      'headers' => [
        'Authorization' => 'Bearer ' . $tok,
        'Content-Type'  => 'application/json',
      ],
      'body' => wp_json_encode($body),
    ]);
    if (is_wp_error($resp)) return $resp;
    $code = wp_remote_retrieve_response_code($resp);
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code !== 200) {
      $msg = $data['error']['message'] ?? ('HTTP ' . $code);
      return new WP_Error('gsc_api', $msg);
    }
    return $data;
  }
}

// ---- Striking Distance (positions 8–20, min impressions) ----
add_action('wp_ajax_ssseo_gsc_perf_striking_distance', function(){
  if (!current_user_can('manage_options')) wp_send_json_error('Insufficient permissions');
  $nonce = $_POST['nonce'] ?? ''; if (empty($nonce) || !wp_verify_nonce($nonce,'ssseo_gsc_ops')) wp_send_json_error('Invalid nonce');

  $site = isset($_POST['site_url']) ? esc_url_raw(wp_unslash($_POST['site_url'])) : '';
  $minImpr = max(0, intval($_POST['min_impr'] ?? 200));
  if (!$site) wp_send_json_error('Missing siteUrl');

  $end = date('Y-m-d');
  $start = date('Y-m-d', strtotime('-28 days'));

  $body = [
    'startDate'  => $start,
    'endDate'    => $end,
    'dimensions' => ['query', 'page'],
    'rowLimit'   => 25000,
    'searchType' => 'web',
    // 'dataState'   => 'all', // uncomment if you want freshest data (may be noisy)
  ];
  $resp = ssseo_gsc_perf_query($site, $body);
  if (is_wp_error($resp)) wp_send_json_error($resp->get_error_message());

  $rows = $resp['rows'] ?? [];
  $out = [];
  foreach ($rows as $r) {
    $keys = $r['keys'] ?? [];
    if (count($keys) < 2) continue;
    $query = $keys[0]; $page = $keys[1];
    $impr  = floatval($r['impressions'] ?? 0);
    $pos   = floatval($r['position'] ?? 0);
    if ($impr >= $minImpr && $pos >= 8 && $pos <= 20) {
      $out[] = [
        'query'       => $query,
        'page'        => $page,
        'clicks'      => intval($r['clicks'] ?? 0),
        'impressions' => intval($impr),
        'ctr'         => floatval($r['ctr'] ?? 0),
        'position'    => $pos,
      ];
    }
  }
  // sort by impressions desc
  usort($out, function($a,$b){ return $b['impressions'] <=> $a['impressions']; });
  wp_send_json_success(array_slice($out,0,200));
});

// ---- Decaying Content (compare periods by page) ----
add_action('wp_ajax_ssseo_gsc_perf_decay', function(){
  if (!current_user_can('manage_options')) wp_send_json_error('Insufficient permissions');
  $nonce = $_POST['nonce'] ?? ''; if (empty($nonce) || !wp_verify_nonce($nonce,'ssseo_gsc_ops')) wp_send_json_error('Invalid nonce');

  $site = isset($_POST['site_url']) ? esc_url_raw(wp_unslash($_POST['site_url'])) : '';
  $minImpr = max(0, intval($_POST['min_impr'] ?? 200));
  if (!$site) wp_send_json_error('Missing siteUrl');

  $end  = date('Y-m-d');
  $start= date('Y-m-d', strtotime('-28 days'));
  $pEnd = date('Y-m-d', strtotime('-29 days'));
  $pSta = date('Y-m-d', strtotime('-56 days'));

  $bodyNow = ['startDate'=>$start,'endDate'=>$end,'dimensions'=>['page'],'rowLimit'=>25000,'searchType'=>'web'];
  $bodyPrev= ['startDate'=>$pSta,'endDate'=>$pEnd,'dimensions'=>['page'],'rowLimit'=>25000,'searchType'=>'web'];

  $now  = ssseo_gsc_perf_query($site, $bodyNow);
  if (is_wp_error($now)) wp_send_json_error($now->get_error_message());
  $prev = ssseo_gsc_perf_query($site, $bodyPrev);
  if (is_wp_error($prev)) wp_send_json_error($prev->get_error_message());

  $mapPrev = [];
  foreach (($prev['rows'] ?? []) as $r){
    $page = $r['keys'][0] ?? '';
    if(!$page) continue;
    $mapPrev[$page] = [
      'clicks' => floatval($r['clicks'] ?? 0),
      'impr'   => floatval($r['impressions'] ?? 0),
    ];
  }

  $out = [];
  foreach (($now['rows'] ?? []) as $r){
    $page = $r['keys'][0] ?? '';
    if(!$page) continue;
    $imprNow = floatval($r['impressions'] ?? 0);
    if ($imprNow < $minImpr) continue;

    $clkNow = floatval($r['clicks'] ?? 0);
    $ctrNow = floatval($r['ctr'] ?? 0);
    $posNow = floatval($r['position'] ?? 0);

    $p = $mapPrev[$page] ?? ['clicks'=>0,'impr'=>0];
    $clkPrev = floatval($p['clicks']);
    $chgPct = ($clkPrev>0) ? ( ($clkNow-$clkPrev)/$clkPrev*100 ) : 0;

    if ($chgPct < -10) { // at least -10% drop
      $out[] = [
        'page' => $page,
        'clicks_prev' => round($clkPrev),
        'clicks_now'  => round($clkNow),
        'change_clicks_pct' => $chgPct,
        'impressions_now' => round($imprNow),
        'ctr_now' => $ctrNow,
        'position_now' => $posNow,
      ];
    }
  }
  usort($out, function($a,$b){ return $a['change_clicks_pct'] <=> $b['change_clicks_pct']; });
  wp_send_json_success(array_slice($out,0,200));
});

// ---- New Opportunities (queries that appeared in last 7d) ----
add_action('wp_ajax_ssseo_gsc_perf_new_opps', function(){
  if (!current_user_can('manage_options')) wp_send_json_error('Insufficient permissions');
  $nonce = $_POST['nonce'] ?? ''; if (empty($nonce) || !wp_verify_nonce($nonce,'ssseo_gsc_ops')) wp_send_json_error('Invalid nonce');

  $site = isset($_POST['site_url']) ? esc_url_raw(wp_unslash($_POST['site_url'])) : '';
  $minImpr = max(0, intval($_POST['min_impr'] ?? 30));
  if (!$site) wp_send_json_error('Missing siteUrl');

  $end  = date('Y-m-d');
  $start= date('Y-m-d', strtotime('-7 days'));
  $pEnd = date('Y-m-d', strtotime('-8 days'));
  $pSta = date('Y-m-d', strtotime('-15 days'));

  $dim = ['query','page'];
  $bodyNow  = ['startDate'=>$start,'endDate'=>$end,'dimensions'=>$dim,'rowLimit'=>25000,'searchType'=>'web'];
  $bodyPrev = ['startDate'=>$pSta,'endDate'=>$pEnd,'dimensions'=>$dim,'rowLimit'=>25000,'searchType'=>'web'];

  $now  = ssseo_gsc_perf_query($site, $bodyNow);
  if (is_wp_error($now)) wp_send_json_error($now->get_error_message());
  $prev = ssseo_gsc_perf_query($site, $bodyPrev);
  if (is_wp_error($prev)) wp_send_json_error($prev->get_error_message());

  $seenPrev = [];
  foreach (($prev['rows'] ?? []) as $r){
    $q = $r['keys'][0] ?? ''; $p = $r['keys'][1] ?? '';
    if ($q) $seenPrev[$q] = true; // track by query (not page)
  }

  $agg = [];
  foreach (($now['rows'] ?? []) as $r){
    $q = $r['keys'][0] ?? ''; $p = $r['keys'][1] ?? '';
    if (!$q || !$p) continue;
    $impr = floatval($r['impressions'] ?? 0);
    if ($impr < $minImpr) continue;
    if (isset($seenPrev[$q])) continue; // new query only

    $k = $q;
    // keep top page per query (by impressions)
    if (!isset($agg[$k]) || $impr > $agg[$k]['impressions']) {
      $agg[$k] = [
        'query' => $q,
        'page'  => $p,
        'clicks'=> intval($r['clicks'] ?? 0),
        'impressions'=> intval($impr),
        'ctr'   => floatval($r['ctr'] ?? 0),
        'position'=> floatval($r['position'] ?? 0),
      ];
    }
  }
  $out = array_values($agg);
  usort($out, function($a,$b){ return $b['impressions'] <=> $a['impressions']; });
  wp_send_json_success(array_slice($out,0,200));
});

// ---- Discover pages (last 28d) ----
add_action('wp_ajax_ssseo_gsc_discover_pages', function(){
  if (!current_user_can('manage_options')) wp_send_json_error('Insufficient permissions');
  $nonce = $_POST['nonce'] ?? ''; if (empty($nonce) || !wp_verify_nonce($nonce,'ssseo_gsc_ops')) wp_send_json_error('Invalid nonce');

  $site = isset($_POST['site_url']) ? esc_url_raw(wp_unslash($_POST['site_url'])) : '';
  if (!$site) wp_send_json_error('Missing siteUrl');

  $end  = date('Y-m-d');
  $start= date('Y-m-d', strtotime('-28 days'));

  $body = [
    'startDate'=>$start,'endDate'=>$end,
    'dimensions'=>['page'],
    'rowLimit'=>25000,
    'type'=>'discover', // alternative param name supported by API
    'searchType'=>'discover'
  ];
  $resp = ssseo_gsc_perf_query($site, $body);
  if (is_wp_error($resp)) wp_send_json_error($resp->get_error_message());

  $rows = [];
  foreach (($resp['rows'] ?? []) as $r){
    $p = $r['keys'][0] ?? '';
    if (!$p) continue;
    $rows[] = [
      'page' => $p,
      'clicks' => intval($r['clicks'] ?? 0),
      'impressions' => intval($r['impressions'] ?? 0),
      'ctr' => floatval($r['ctr'] ?? 0),
    ];
  }
  usort($rows,function($a,$b){ return $b['clicks'] <=> $a['clicks']; });
  wp_send_json_success(array_slice($rows,0,200));
});
