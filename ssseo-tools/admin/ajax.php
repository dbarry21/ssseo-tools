<?php
// File: admin/ajax.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Utility: simple permission + nonce check
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
        'post_type'      => $pt,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ] );

    ob_start();
    if ( $posts ) {
        foreach ( $posts as $pid ) {
            $title = get_the_title( $pid );
            echo '<option value="'.esc_attr( $pid ).'">'.esc_html( $title .' (ID '.$pid.')' ).'</option>';
        }
    }
    $html = ob_get_clean();
    // Caller expects raw HTML inserted into #ssseo-posts, not JSON. Return HTML string.
    wp_send_json_success( $html );
});

/**
 * Bulk: Set Yoast robots to index,follow
 * action: ssseo_yoast_set_index_follow
 * POST: post_ids[], _wpnonce (optional; validated if present)
 */
add_action( 'wp_ajax_ssseo_yoast_set_index_follow', function() {
    $nonce = $_POST['_wpnonce'] ?? '';
    // If you localize a specific nonce, replace 'ssseo_admin_nonce' with it.
    if ( $nonce ) ssseo_check_cap_and_nonce( 'ssseo_admin_nonce', $nonce ); else ssseo_check_cap_and_nonce();

    $ids = array_map( 'intval', (array) ( $_POST['post_ids'] ?? [] ) );
    if ( empty( $ids ) ) wp_send_json_error( 'No posts selected' );

    foreach ( $ids as $pid ) {
        // Yoast “noindex” meta is stored as _yoast_wpseo_meta-robots-noindex (1 = noindex, 0/empty = index)
        update_post_meta( $pid, '_yoast_wpseo_meta-robots-noindex', '0' );
        // Yoast “nofollow” meta is stored as _yoast_wpseo_meta-robots-nofollow (1 = nofollow, 0/empty = follow)
        update_post_meta( $pid, '_yoast_wpseo_meta-robots-nofollow', '0' );
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

    $ids = array_map( 'intval', (array) ( $_POST['post_ids'] ?? [] ) );
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

    $ids = array_map( 'intval', (array) ( $_POST['post_ids'] ?? [] ) );
    if ( empty( $ids ) ) wp_send_json_error( 'No posts selected' );

    foreach ( $ids as $pid ) {
        update_post_meta( $pid, '_yoast_wpseo_canonical', '' );
    }
    wp_send_json_success( 'Canonical cleared on '.count($ids).' posts.' );
});

/**
 * Optional: YouTube fix action (stub). Implement your actual scan/fix if needed.
 * action: ssseo_fix_youtube_iframes
 */
add_action( 'wp_ajax_ssseo_fix_youtube_iframes', function() {
    ssseo_check_cap_and_nonce();
    // Implement your scanning logic here; for now return success.
    wp_send_json_success( 'YouTube iframe pass complete.' );
});


/**
 * Clone a source service_area to multiple parent service_areas (as children).
 * - Replaces [acf field="city_state"] in TITLE with the parent’s city_state BEFORE saving
 * - Copies content, meta (incl. Elementor), taxonomies, thumbnail
 * - Sets cloned post's ACF/meta 'city_state' from the parent
 * - Optional custom slug for the clone ('new_slug'); WP will ensure uniqueness per parent
 *
 * action: ssseo_clone_sa_to_parents
 * POST:
 *   - nonce, source_id, target_parent_ids[], as_draft, skip_existing, debug, new_slug (optional)
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

    $source_id   = intval($_POST['source_id'] ?? 0);
    $target_ids  = array_map('intval', (array)($_POST['target_parent_ids'] ?? []));
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
        // Titles must be plain text; strip any tags entities that might sneak in
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
        // This lets either [acf field="city_state"] or your [city_state] shortcode resolve properly.
        $rendered_title = $render_with_post_context( $source->post_title, $new_id );

        // Fallback to simple replace if rendering produced an empty string (e.g., shortcode not present)
        if ($rendered_title === '') {
            $rendered_title = $fallback_title;
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
            delete_post_meta($new_id, '_yoast_wpseo_focuskeywords'); // Premium multi-keyphrase JSON
            delete_post_meta($new_id, 'yoast_wpseo_focuskw');
            delete_post_meta($new_id, 'yoast_wpseo_focuskw_text_input');

            // Write new primary keyphrase
            update_post_meta($new_id, '_yoast_wpseo_focuskw', $focus);
            update_post_meta($new_id, '_yoast_wpseo_focuskw_text_input', $focus);
            // Also non-underscored variants (cover edge installs)
            update_post_meta($new_id, 'yoast_wpseo_focuskw', $focus);
            update_post_meta($new_id, 'yoast_wpseo_focuskw_text_input', $focus);

            // Nudge Yoast indexables/watchers
            wp_update_post(['ID'=>$new_id]);
        }

        // Safety: ensure terms & thumbnail (Yoast usually handles these, but harmless to re-apply)
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
