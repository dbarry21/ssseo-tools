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
 * Legacy: Return <option> HTML for a post type (kept for back-compat)
 * action: ssseo_get_posts_by_type
 */
add_action( 'wp_ajax_ssseo_get_posts_by_type', function() {
    ssseo_check_cap_and_nonce(); // no specific nonce in legacy usage
    $pt = sanitize_key( $_POST['post_type'] ?? '' );
    if ( ! $pt ) wp_send_json_success( '' );

    $ids = get_posts( [
        'post_type'        => $pt,
        'post_status'      => ['publish'],
        'posts_per_page'   => -1,
        'orderby'          => 'title',
        'order'            => 'ASC',
        'fields'           => 'ids',
        'no_found_rows'    => true,
        'suppress_filters' => true,
    ] );

    ob_start();
    foreach ( $ids as $pid ) {
        $title = get_the_title( $pid );
        if ($title === '') $title = "(no title) #$pid";
        echo '<option value="'.esc_attr( $pid ).'">'.esc_html( $title ).'</option>';
    }
    wp_send_json_success( ob_get_clean() );
});

/**
 * NEW: JSON list for the Meta History post dropdown (secure + searchable)
 * action: ssseo_get_posts_for_meta_history
 * POST: _wpnonce (ssseo_meta_history), post_type, s (optional)
 */
add_action('wp_ajax_ssseo_get_posts_for_meta_history', function () {
    if ( empty($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], 'ssseo_meta_history') ) {
        wp_send_json_error('Unauthorized (bad nonce)');
    }
    if ( ! current_user_can('edit_posts') ) {
        wp_send_json_error('Insufficient permissions');
    }

    $pt = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : '';
    if (empty($pt) || ! post_type_exists($pt)) {
        wp_send_json_error('Invalid post type');
    }

    $search = isset($_POST['s']) ? sanitize_text_field( wp_unslash($_POST['s']) ) : '';

    $ids = get_posts([
        'post_type'        => $pt,
        'post_status'      => ['publish','draft','pending','future','private'],
        'posts_per_page'   => 200,
        'orderby'          => 'title',
        'order'            => 'ASC',
        's'                => $search,
        'fields'           => 'ids',
        'no_found_rows'    => true,
        'suppress_filters' => true,
    ]);

    $out = [];
    foreach ($ids as $id) {
        $title = get_the_title($id);
        if ($title === '') $title = "(no title) #$id";
        $out[] = ['id' => (int)$id, 'title' => $title];
    }
    wp_send_json_success($out);
});

// Get Meta History → returns HTML from _ssseo_meta_history
add_action('wp_ajax_ssseo_get_meta_history', function () {
    try {
        if ( empty($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], 'ssseo_meta_history') ) {
            wp_send_json_error('Unauthorized (bad nonce)');
        }
        if ( ! current_user_can('edit_posts') ) {
            wp_send_json_error('Insufficient permissions');
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if ( ! $post_id ) wp_send_json_error('Invalid post ID');

        $rows = get_post_meta($post_id, '_ssseo_meta_history', true);
        if ( ! is_array($rows) ) $rows = [];

        // Optional: limit output rows for performance
        $MAX_SHOW = 300;
        if (count($rows) > $MAX_SHOW) {
          $rows = array_slice($rows, 0, $MAX_SHOW);
        }

        ob_start();
        if (empty($rows)) {
            echo '<em>No history found for this post.</em>';
        } else {
            ?>
            <table class="widefat striped">
              <thead>
                <tr>
                  <th style="width:160px;">Date</th>
                  <th style="width:200px;">User</th>
                  <th style="width:180px;">Field</th>
                  <th>Old</th>
                  <th>New</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?php echo esc_html($r['ts'] ?? ''); ?></td>
                  <td><?php echo esc_html($r['user'] ?? ''); ?></td>
                  <td><?php echo esc_html($r['field'] ?? ''); ?></td>
                  <td><?php echo esc_html($r['old'] ?? ''); ?></td>
                  <td><?php echo esc_html($r['new'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <?php
        }
        wp_send_json_success(['html' => ob_get_clean()]);
    } catch (Throwable $e) {
        if (function_exists('error_log')) error_log('[SSSEO get_meta_history] ' . $e->getMessage());
        wp_send_json_error('Server error: ' . $e->getMessage());
    }
});

// CSV export for meta history
add_action('wp_ajax_ssseo_export_meta_history', function () {
    if ( empty($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], 'ssseo_meta_history') ) {
        wp_die('Unauthorized (bad nonce)');
    }
    if ( ! current_user_can('edit_posts') ) {
        wp_die('Insufficient permissions');
    }
    $post_id = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
    if ( ! $post_id ) wp_die('Invalid post ID');

    $rows = get_post_meta($post_id, '_ssseo_meta_history', true);
    if ( ! is_array($rows) ) $rows = [];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=meta-history-' . $post_id . '.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date','User','Field','Old','New']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['ts']    ?? '',
            $r['user']  ?? '',
            $r['field'] ?? '',
            $r['old']   ?? '',
            $r['new']   ?? '',
        ]);
    }
    fclose($out);
    exit;
});

// ===================== CLONE SERVICE AREAS (Yoast-aware + ACF-key + title shortcode render) =====================
add_action('wp_ajax_ssseo_clone_sa_to_parents', function () {
    try {
        // --- Capability + unified nonce (matches bulk.php: SSSEO.bulkNonce / 'ssseo_bulk_ops') ---
        if ( ! current_user_can('edit_posts') ) {
            wp_send_json_error('Insufficient permissions');
        }
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ( empty($nonce) || ! wp_verify_nonce($nonce, 'ssseo_bulk_ops') ) {
            wp_send_json_error('Invalid nonce');
        }

        // --- Inputs ---
        $source_id  = isset($_POST['source_id']) ? (int) $_POST['source_id'] : 0;
        $targets_in = isset($_POST['target_parent_ids']) ? (array) $_POST['target_parent_ids'] : [];
        $targets    = array_values(array_unique(array_map('intval', $targets_in)));

        $as_draft      = ! empty($_POST['as_draft']);
        $skip_existing = ! empty($_POST['skip_existing']);
        $debug         = ! empty($_POST['debug']);

        $new_slug_raw = isset($_POST['new_slug']) ? wp_unslash($_POST['new_slug']) : '';
        $new_slug     = $new_slug_raw !== '' ? sanitize_title($new_slug_raw) : '';
        $focus_base   = isset($_POST['focus_base']) ? sanitize_text_field(wp_unslash($_POST['focus_base'])) : '';

        if ( ! $source_id || empty($targets) ) {
            wp_send_json_error('Missing source or targets');
        }

        $src = get_post($source_id);
        if ( ! $src || $src->post_type !== 'service_area' ) {
            wp_send_json_error('Invalid source service_area');
        }

        $status       = $as_draft ? 'draft' : 'publish';
        $src_title    = get_the_title($source_id);
        $src_excerpt  = get_post_field('post_excerpt', $source_id);
        $src_content  = get_post_field('post_content', $source_id);
        $src_order    = (int) get_post_field('menu_order', $source_id);
        $src_thumb_id = get_post_thumbnail_id($source_id);

        // Taxonomies (used by manual fallback)
        $taxes     = get_object_taxonomies('service_area');
        $src_terms = [];
        foreach ($taxes as $tx) {
            $t = wp_get_object_terms($source_id, $tx, ['fields' => 'ids']);
            $src_terms[$tx] = is_wp_error($t) ? [] : $t;
        }

        $log = [];
        $log[] = "Source #{$source_id} “{$src_title}” → cloning to " . count($targets) . " parent(s).";

        // --- Helpers ---
        $duplicate_via_yoast = function($source_id, $status) {
            // Classic public API in Yoast Duplicate Post
            if ( function_exists('duplicate_post_create_duplicate') ) {
                $dup = duplicate_post_create_duplicate( get_post($source_id), $status );
                if ( is_wp_error($dup) ) return $dup;
                return is_object($dup) ? (int) $dup->ID : (int) $dup;
            }
            return 0; // trigger manual fallback
        };

        $get_parent_city_state = function($parent_id) {
            $val = '';
            if ( function_exists('get_field') ) {
                $val = (string) get_field('city_state', $parent_id);
            }
            if ($val === '') {
                $val = get_post_meta($parent_id, 'city_state', true);
            }
            return is_string($val) ? $val : '';
        };

        $get_city_state_field_key = function($context_post_id = 0) {
            // Best chance: ask ACF for the object by field name tied to a post (resolves field group)
            if ( function_exists('get_field_object') ) {
                $fo = get_field_object('city_state', $context_post_id);
                if ( is_array($fo) && ! empty($fo['key']) ) return $fo['key'];
            }
            // Global fallback if available
            if ( function_exists('acf_get_field') ) {
                $f = acf_get_field('city_state');
                if ( is_array($f) && ! empty($f['key']) ) return $f['key'];
            }
            return '';
        };

        foreach ($targets as $parent_id) {
            if ($parent_id === 0) {
                $log[] = "Skip parent #0: invalid parent.";
                continue;
            }
            $parent = get_post($parent_id);
            if ( ! $parent || $parent->post_type !== 'service_area' ) {
                $log[] = "Skip parent #{$parent_id}: not a service_area.";
                continue;
            }

            // Skip if a child with SAME title already exists under this parent
            if ( $skip_existing ) {
                $siblings = get_posts([
                    'post_type'        => 'service_area',
                    'post_status'      => ['publish','draft','pending','future','private'],
                    'posts_per_page'   => -1,
                    'post_parent'      => $parent_id,
                    'fields'           => 'ids',
                    'suppress_filters' => true,
                    'no_found_rows'    => true,
                ]);
                $dup = false;
                foreach ($siblings as $sid) {
                    if ( get_the_title($sid) === $src_title ) { $dup = true; break; }
                }
                if ($dup) {
                    $log[] = "Skip: child with same title already under parent #{$parent_id}.";
                    continue;
                }
            }

            // 1) Try Yoast Duplicate Post
            $new_id = $duplicate_via_yoast($source_id, $status);

            // 2) Manual fallback if plugin not present
            if ( ! $new_id ) {
                $new_id = wp_insert_post([
                    'post_type'    => 'service_area',
                    'post_status'  => $status,
                    'post_parent'  => $parent_id,
                    'post_title'   => $src_title,
                    'post_content' => $src_content,
                    'post_excerpt' => $src_excerpt,
                    'menu_order'   => $src_order,
                    'post_name'    => $new_slug, // let WP uniquify if duplicate/empty
                ], true);

                if ( is_wp_error($new_id) ) {
                    $log[] = "Error creating child under #{$parent_id}: " . $new_id->get_error_message();
                    continue;
                }

                // Copy taxonomies
                foreach ($taxes as $tx) {
                    if ( ! empty($src_terms[$tx]) ) {
                        wp_set_object_terms($new_id, $src_terms[$tx], $tx, false);
                    }
                }
                // Copy featured image
                if ( $src_thumb_id ) {
                    set_post_thumbnail($new_id, $src_thumb_id);
                }
                // Copy meta (conservative)
                $skip_meta = ['_edit_lock','_edit_last','_wp_old_slug'];
                $all_meta  = get_post_meta($source_id);
                foreach ($all_meta as $k => $vals) {
                    if ( in_array($k, $skip_meta, true) ) continue;
                    foreach ((array) $vals as $v) {
                        update_post_meta($new_id, $k, maybe_unserialize($v));
                    }
                }

            } else {
                // Yoast Duplicate created the clone. Enforce our parent/status/slug.
                $update = [
                    'ID'          => $new_id,
                    'post_parent' => $parent_id,
                    'post_status' => $status,
                ];
                if ($new_slug !== '') $update['post_name'] = $new_slug;
                wp_update_post($update);
            }

            // --- Set ACF city_state on the clone (from the selected parent) using FIELD KEY when possible ---
            $parent_city_state = $get_parent_city_state($parent_id);
            $field_key         = $get_city_state_field_key($parent_id);

            if ( function_exists('update_field') ) {
                if ( $field_key ) {
                    @update_field( $field_key, $parent_city_state, (int) $new_id );
                } else {
                    @update_field( 'city_state', $parent_city_state, (int) $new_id );
                }
            } else {
                // ACF not active: write raw meta; also set the hidden key if discovered
                update_post_meta( (int) $new_id, 'city_state', $parent_city_state );
                if ( $field_key ) {
                    update_post_meta( (int) $new_id, '_city_state', $field_key );
                }
            }
            clean_post_cache( (int) $new_id );

            // --- Optional: Yoast focus keyphrase from UI base + parent city_state ---
            if ( $focus_base !== '' ) {
                $clean_city = str_replace(',', '', $parent_city_state);
                $focus_kw   = trim($focus_base . ' ' . $clean_city);
                update_post_meta($new_id, '_yoast_wpseo_focuskw', $focus_kw);
                update_post_meta($new_id, 'yoast_head_json', null); // invalidate Yoast cache if present
            }

            // --- Render shortcodes in the clone's TITLE AFTER setting ACF/meta ---
            $old_title = get_post_field('post_title', (int) $new_id);
            if ($old_title !== '' && strpos($old_title, '[') !== false && strpos($old_title, ']') !== false) {
                global $post;
                $prev_post = $post;
                $post = get_post((int) $new_id);
                setup_postdata($post);
                $rendered = do_shortcode($old_title); // resolves [acf field="city_state"], etc.
                wp_reset_postdata();
                $post = $prev_post;

                if (is_string($rendered) && $rendered !== $old_title) {
                    wp_update_post([
                        'ID'         => (int) $new_id,
                        'post_title' => $rendered,
                    ]);
                }
            }

            $log[] = "Cloned to #{$new_id} under parent #{$parent_id} ({$status}).";
        }

        wp_send_json_success(['log' => $log]);

    } catch (Throwable $e) {
        if (function_exists('error_log')) error_log('[SSSEO clone] ' . $e->getMessage());
        wp_send_json_error('Server error: ' . $e->getMessage());
    }
});


/* ============================================================
 * NEW: Site Options — Robust API Testers (fixes "Network error")
 * Matches UI actions in admin/tabs/site-options.php:
 *   - ssseo_test_maps_key
 *   - ssseo_test_openai_key
 *   - ssseo_test_youtube_api
 *   - ssseo_test_gsc_client
 * ============================================================ */

/**
 * Robust GET wrapper: Referer header + IPv4 retry + wp_remote_get (not safe_*)
 */
if ( ! function_exists('ssseo_http_get_robust') ) {
    function ssseo_http_get_robust( $url, $args = [] ) {
        $defaults = [
            'timeout'     => 15,
            'sslverify'   => true,
            'httpversion' => '1.1',
            'headers'     => [ 'Referer' => home_url('/') ],
        ];
        $args = wp_parse_args( $args, $defaults );

        // Try normally
        $resp = wp_remote_get( $url, $args );
        if ( ! is_wp_error($resp) && wp_remote_retrieve_response_code($resp) ) {
            return $resp;
        }

        // Retry forcing IPv4 (common host quirk)
        $ipv4 = function( $handle ) {
            if ( defined('CURL_IPRESOLVE_V4') ) {
                @curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
            }
        };
        add_filter( 'http_api_curl', $ipv4, 10, 1 );
        $resp2 = wp_remote_get( $url, $args );
        remove_filter( 'http_api_curl', $ipv4, 10 );

        return $resp2;
    }
}

/**
 * Helper: standardize JSON test response and store "last test" strings
 */
if ( ! function_exists('ssseo_send_test_string') ) {
    function ssseo_send_test_string( $ok, $msg, $option_key_to_remember ) {
        $stamp = date_i18n( 'Y-m-d H:i:s' );
        $line  = ($ok ? 'OK' : 'ERROR') . " @ {$stamp} — {$msg}";
        if ( $option_key_to_remember ) {
            update_option( $option_key_to_remember, $line );
        }
        if ( $ok ) {
            wp_send_json_success( $line );
        } else {
            wp_send_json_error( $line );
        }
    }
}

/**
 * AJAX: Test Google Static Maps key
 * UI calls: $.post({ action:'ssseo_test_maps_key', key, nonce? })
 */
add_action('wp_ajax_ssseo_test_maps_key', function () {
    // Accept either legacy (no nonce) or new (nonce) calls; always require capability
    if ( ! current_user_can('edit_posts') ) {
        wp_send_json_error('Insufficient permissions');
    }
    // Prefer provided key; fallback to saved option
    $key = sanitize_text_field( $_POST['key'] ?? '' );
    if ( $key === '' ) {
        $key = get_option('ssseo_google_static_maps_api_key', '');
    }
    if ( $key === '' ) {
        ssseo_send_test_string(false, 'Missing API key', 'ssseo_maps_test_result');
    }

    $url = add_query_arg([
        'center'  => 'Tampa,FL',
        'zoom'    => 13,
        'size'    => '600x300',
        'markers' => rawurlencode('Tampa,FL'),
        'key'     => $key,
    ], 'https://maps.googleapis.com/maps/api/staticmap');

    $resp = ssseo_http_get_robust($url);

    if ( is_wp_error($resp) ) {
        ssseo_send_test_string(false, 'WP_Error: '.$resp->get_error_message(), 'ssseo_maps_test_result');
    }

    $code = wp_remote_retrieve_response_code($resp);
    $ct   = (string) wp_remote_retrieve_header($resp, 'content-type');
    $body = wp_remote_retrieve_body($resp);

    if ( $code === 200 && stripos($ct, 'image/') === 0 ) {
        ssseo_send_test_string(true, 'Static Maps reachable (HTTP 200, image)', 'ssseo_maps_test_result');
    } else {
        $snippet = substr($body ?? '', 0, 300);
        ssseo_send_test_string(false, "HTTP {$code} {$ct} — {$snippet}", 'ssseo_maps_test_result');
    }
});

/**
 * AJAX: Test OpenAI key
 * UI calls: $.post({ action:'ssseo_test_openai_key', key, nonce? })
 */
add_action('wp_ajax_ssseo_test_openai_key', function () {
    if ( ! current_user_can('edit_posts') ) {
        wp_send_json_error('Insufficient permissions');
    }
    $key = sanitize_text_field( $_POST['key'] ?? '' );
    if ( $key === '' ) {
        $key = get_option('ssseo_openai_api_key', '');
    }
    if ( $key === '' ) {
        ssseo_send_test_string(false, 'Missing OpenAI key', 'ssseo_openai_test_result');
    }

    $url  = 'https://api.openai.com/v1/models';
    $args = [
        'timeout'     => 15,
        'sslverify'   => true,
        'httpversion' => '1.1',
        'headers'     => [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
            'Referer'       => home_url('/'),
        ],
    ];
    $resp = ssseo_http_get_robust($url, $args);

    if ( is_wp_error($resp) ) {
        ssseo_send_test_string(false, 'WP_Error: '.$resp->get_error_message(), 'ssseo_openai_test_result');
    }
    $code = wp_remote_retrieve_response_code($resp);
    $ct   = (string) wp_remote_retrieve_header($resp, 'content-type');
    $body = wp_remote_retrieve_body($resp);

    if ( $code >= 200 && $code < 300 ) {
        ssseo_send_test_string(true, "OpenAI reachable (HTTP {$code})", 'ssseo_openai_test_result');
    } else {
        $snippet = substr($body ?? '', 0, 300);
        ssseo_send_test_string(false, "HTTP {$code} {$ct} — {$snippet}", 'ssseo_openai_test_result');
    }
});

/**
 * AJAX: Test YouTube Data API (channels.list)
 * UI calls: $.post({ action:'ssseo_test_youtube_api', nonce: <ssseo_siteoptions_ajax> })
 */
add_action('wp_ajax_ssseo_test_youtube_api', function () {
    if ( empty($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'ssseo_siteoptions_ajax') ) {
        wp_send_json_error('Unauthorized (bad nonce)');
    }
    if ( ! current_user_can('edit_posts') ) {
        wp_send_json_error('Insufficient permissions');
    }

    $key        = get_option('ssseo_youtube_api_key', '');
    $channel_id = get_option('ssseo_youtube_channel_id', '');
    if ( $key === '' ) {
        ssseo_send_test_string(false, 'Missing YouTube API key', 'ssseo_youtube_test_result');
    }

    $url = add_query_arg([
        'part' => 'snippet',
        'id'   => $channel_id ?: 'UC_x5XG1OV2P6uZZ5FSM9Ttw', // Google Developers fallback
        'key'  => $key,
    ], 'https://www.googleapis.com/youtube/v3/channels');

    $resp = ssseo_http_get_robust($url);
    if ( is_wp_error($resp) ) {
        ssseo_send_test_string(false, 'WP_Error: '.$resp->get_error_message(), 'ssseo_youtube_test_result');
    }

    $code = wp_remote_retrieve_response_code($resp);
    $ct   = (string) wp_remote_retrieve_header($resp, 'content-type');
    $body = wp_remote_retrieve_body($resp);

    if ( $code >= 200 && $code < 300 ) {
        ssseo_send_test_string(true, "YouTube reachable (HTTP {$code})", 'ssseo_youtube_test_result');
    } else {
        $snippet = substr($body ?? '', 0, 300);
        ssseo_send_test_string(false, "HTTP {$code} {$ct} — {$snippet}", 'ssseo_youtube_test_result');
    }
});

/**
 * AJAX: Check GSC OAuth fields (simple validation)
 * UI calls: $.post({ action:'ssseo_test_gsc_client', nonce: <ssseo_siteoptions_ajax> })
 */
add_action('wp_ajax_ssseo_test_gsc_client', function () {
    if ( empty($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'ssseo_siteoptions_ajax') ) {
        wp_send_json_error('Unauthorized (bad nonce)');
    }
    if ( ! current_user_can('edit_posts') ) {
        wp_send_json_error('Insufficient permissions');
    }

    $cid  = trim( (string) get_option('ssseo_gsc_client_id', '') );
    $sec  = trim( (string) get_option('ssseo_gsc_client_secret', '') );
    $redir= trim( (string) get_option('ssseo_gsc_redirect_uri', '') );

    if ( $cid && $sec && $redir ) {
        ssseo_send_test_string(true, 'Client configured (Client ID, Secret, Redirect URI present)', 'ssseo_gsc_test_result');
    } else {
        $miss = [];
        if (!$cid)  $miss[] = 'Client ID';
        if (!$sec)  $miss[] = 'Client Secret';
        if (!$redir)$miss[] = 'Redirect URI';
        ssseo_send_test_string(false, 'Missing: ' . implode(', ', $miss), 'ssseo_gsc_test_result');
    }
});

// ===================== BULK: Generate Static Map featured images =====================
add_action('wp_ajax_ssseo_bulk_generate_maps', function () {
    if ( ! current_user_can('edit_posts') ) {
        wp_send_json_error('Insufficient permissions');
    }
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if ( empty($nonce) || ! wp_verify_nonce($nonce, 'ssseo_bulk_ops') ) {
        wp_send_json_error('Invalid nonce');
    }

    $ids   = isset($_POST['post_ids']) ? (array) $_POST['post_ids'] : [];
    $force = ! empty($_POST['force']); // if false, skip posts that already have a featured image

    $ids = array_values(array_unique(array_map('intval', $ids)));
    if ( empty($ids) ) {
        wp_send_json_error('No posts provided');
    }

    if ( ! function_exists('ssseo_mapasfeatured_generate_for_post') ) {
        wp_send_json_error('Generator function not available');
    }

    $result = ['ok'=>0,'err'=>0,'log'=>[]];

    foreach ( $ids as $pid ) {
        if ( get_post_type($pid) !== 'service_area' ) {
            $result['err']++; $result['log'][] = "ID {$pid}: skip (not service_area)";
            continue;
        }
        if ( ! $force && has_post_thumbnail($pid) ) {
            $result['log'][] = "ID {$pid}: skipped (already has featured image)";
            continue;
        }
        $r = ssseo_mapasfeatured_generate_for_post( $pid );
        if ( is_wp_error($r) ) {
            $result['err']++; $result['log'][] = "ID {$pid}: ERROR — " . $r->get_error_message();
        } else {
            $result['ok']++;  $result['log'][] = "ID {$pid}: OK (attachment #{$r})";
        }
    }

    wp_send_json_success($result);
});

// ===== Service Areas lists (global) =====
if ( ! function_exists('ssseo_ajax_sa_all_published') ) {
  add_action('wp_ajax_ssseo_sa_all_published', 'ssseo_ajax_sa_all_published');
  function ssseo_ajax_sa_all_published() {
    if ( ! current_user_can('edit_posts') ) wp_send_json_error('Insufficient permissions');

    // Accept our bulk nonce (same as bulk tabs)
    $nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash($_POST['nonce']) ) : '';
    if ( empty($nonce) || ! wp_verify_nonce($nonce, 'ssseo_bulk_ops') ) {
      wp_send_json_error('Invalid nonce');
    }

    $ids = get_posts([
      'post_type'        => 'service_area',
      'posts_per_page'   => -1,
      'post_status'      => 'publish',
      'orderby'          => 'title',
      'order'            => 'ASC',
      'suppress_filters' => true,
      'fields'           => 'ids',
      'no_found_rows'    => true,
    ]);

    $items = [];
    foreach ($ids as $id) {
      $items[] = ['id' => (int)$id, 'title' => get_the_title($id) ?: "(no title) #$id"];
    }
    wp_send_json_success(['items' => $items]);
  }
}

if ( ! function_exists('ssseo_ajax_sa_tree_published') ) {
  add_action('wp_ajax_ssseo_sa_tree_published', 'ssseo_ajax_sa_tree_published');
  function ssseo_ajax_sa_tree_published() {
    if ( ! current_user_can('edit_posts') ) wp_send_json_error('Insufficient permissions');

    $nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash($_POST['nonce']) ) : '';
    if ( empty($nonce) || ! wp_verify_nonce($nonce, 'ssseo_bulk_ops') ) {
      wp_send_json_error('Invalid nonce');
    }

    $rows = get_posts([
      'post_type'        => 'service_area',
      'posts_per_page'   => -1,
      'post_status'      => 'publish',
      'orderby'          => 'menu_order title',
      'order'            => 'ASC',
      'suppress_filters' => true,
      'fields'           => 'all',
      'no_found_rows'    => true,
    ]);

    $by_parent = [];
    foreach ($rows as $p) {
      $by_parent[(int)$p->post_parent][] = $p;
    }

    $items = [];
    $walk = function($parent_id, $depth) use (&$walk, &$by_parent, &$items) {
      if (empty($by_parent[$parent_id])) return;
      foreach ($by_parent[$parent_id] as $node) {
        $items[] = [
          'id'    => (int)$node->ID,
          'title' => get_the_title($node) ?: '(no title)',
          'depth' => (int)$depth,
        ];
        $walk((int)$node->ID, $depth + 1);
      }
    };
    $walk(0, 0);

    wp_send_json_success(['items' => $items]);
  }
}

/* ============================================================
 * BULK: AI HTML Summaries for service_area (robust AJAX hook)
 * Action: ssseo_bulk_ai_generate_summaries
 * - Decodes cfg_hex / cfg_b64
 * - Verifies nonce
 * - Builds & logs final prompts
 * - Calls ssseo_ai_generate_sa_summary() with the exact prompts
 * ============================================================ */
if ( ! function_exists('ssseo_ajax_bulk_ai_generate_summaries') ) {
  add_action('wp_ajax_ssseo_bulk_ai_generate_summaries', 'ssseo_ajax_bulk_ai_generate_summaries');
  function ssseo_ajax_bulk_ai_generate_summaries() {
    if ( ! current_user_can('edit_posts') ) {
      wp_send_json_error('Insufficient permissions');
    }

    // ---- Read compact config (HEX → JSON | b64 → JSON | legacy POST) ----
    $cfg = null;

    if ( isset($_POST['cfg_hex']) ) {
      $hex = preg_replace('/[^0-9a-f]/i', '', (string) $_POST['cfg_hex']);
      if ($hex !== '' && function_exists('hex2bin')) {
        $json = hex2bin($hex);
        if ($json !== false) $cfg = json_decode($json, true);
      }
    }
    if ($cfg === null && isset($_POST['cfg_b64'])) {
      $json = base64_decode( (string) $_POST['cfg_b64'], true );
      if ($json !== false) $cfg = json_decode($json, true);
    }
    if ($cfg === null) {
      // Fallback for dev/testing
      $cfg = [
        'n'   => isset($_POST['nonce']) ? sanitize_text_field( wp_unslash($_POST['nonce']) ) : '',
        'ids' => isset($_POST['post_ids']) ? array_map('intval', (array) $_POST['post_ids']) : [],
        'f'   => ! empty($_POST['force']) ? 1 : 0,
        't'   => isset($_POST['tone'])  ? sanitize_text_field( wp_unslash($_POST['tone']) ) : 'professional, friendly',
        'w'   => isset($_POST['words']) ? (int) $_POST['words'] : 160,
        'x'   => isset($_POST['temp'])  ? (float) $_POST['temp']  : 0.6,
        'ps'  => isset($_POST['sys_prompt'])  ? (string) wp_unslash($_POST['sys_prompt'])  : '',
        'pu'  => isset($_POST['user_prompt']) ? (string) wp_unslash($_POST['user_prompt']) : '',
        're'  => ! empty($_POST['remember_prompts']) ? 1 : 0,
        'dg'  => ! empty($_POST['debug_prompts']) ? 1 : 0,
        'dgs' => ! empty($_POST['debug_include_sys']) ? 1 : 0,
        'dgl' => isset($_POST['debug_len']) ? (int) $_POST['debug_len'] : 1200,
      ];
    }

    // ---- Nonce ----
    $nonce = (string) ($cfg['n'] ?? '');
    if ( empty($nonce) || ! wp_verify_nonce($nonce, 'ssseo_bulk_ops') ) {
      wp_send_json_error('Invalid nonce');
    }

    // ---- Debug flags ----
    $debug_prompts     = ! empty($cfg['dg']);   // show final user prompt preview
    $debug_include_sys = ! empty($cfg['dgs']);  // include system prompt preview
    $debug_len         = isset($cfg['dgl']) ? (int)$cfg['dgl'] : 1200;
    if ($debug_len < 200)  $debug_len = 200;
    if ($debug_len > 4000) $debug_len = 4000;

    // ---- Ensure AI generator module is loaded ----
    if ( ! function_exists('ssseo_ai_generate_sa_summary') ) {
      $module_paths = [
        plugin_dir_path(__DIR__) . 'modules/ai-service-area-summary.php',
        dirname(__DIR__) . '/modules/ai-service-area-summary.php',
        dirname(__FILE__, 2) . '/modules/ai-service-area-summary.php',
      ];
      foreach ($module_paths as $p) {
        if ( file_exists($p) ) { require_once $p; break; }
      }
    }
    if ( ! function_exists('ssseo_ai_generate_sa_summary') ) {
      wp_send_json_error('AI module unavailable (generator function missing)');
    }

    // ---- Inputs ----
    $ids   = array_values( array_unique( array_map('intval', (array) ($cfg['ids'] ?? []) ) ) );
    if ( empty($ids) ) wp_send_json_error('No posts provided');

    $force = ! empty($cfg['f']);
    $tone  = (string) ($cfg['t'] ?? 'professional, friendly');
    $words = max(80, (int)($cfg['w'] ?? 160));
    $temp  = isset($cfg['x']) ? (float) $cfg['x'] : 0.6;

    // ---- Prompts: UI → options → hard defaults ----
    $ui_sys  = (string) ($cfg['ps'] ?? '');
    $ui_user = (string) ($cfg['pu'] ?? '');

    $opt_sys  = (string) get_option('ssseo_ai_prompt_sys',  '');
    $opt_user = (string) get_option('ssseo_ai_prompt_user', '');

    $DEFAULT_SYS  =
"You are a professional SEO and copywriter.
- Write a concise, conversion-focused HTML summary for a local service area page.
- Aim for ~{target_words} words.
- Use clear paragraphs (<p>) and optionally one short <ul> with up to 3 bullet items.
- Naturally incorporate the focus keyword without stuffing; avoid repeating the post title verbatim.
- No phone numbers, no contact info, no guarantees.
- If linking, use <a rel=\"nofollow noopener\" target=\"_blank\">.
- Output ONLY HTML (no markdown, no headings).";

    $DEFAULT_USER =
"Post Title: {title}
City/State: {city_state}
Focus Keyword: {focuskw}
Tone: {tone}

Existing WP Excerpt (if any):
{excerpt}

Existing HTML Excerpt (if any):
{html_excerpt}

Optional About/Notes:
{about}

Content snippet:
{content_snip}

Write an HTML summary suitable for the page intro, using the focus keyword naturally.";

    $used_sys_prompt  = $ui_sys  !== '' ? $ui_sys  : ( $opt_sys  !== '' ? $opt_sys  : $DEFAULT_SYS  );
    $used_user_tpl    = $ui_user !== '' ? $ui_user : ( $opt_user !== '' ? $opt_user : $DEFAULT_USER );

    // Save prompts (when asked)
    $remember = ! empty($cfg['re']);
    if ( $remember ) {
      update_option('ssseo_ai_prompt_sys',  $used_sys_prompt,  false);
      update_option('ssseo_ai_prompt_user', $used_user_tpl,    false);
    }

    // ---- Helper: Build final user prompt per post ----
    $build_user_prompt = function($pid) use ($tone, $words, $used_user_tpl) {
      $title   = get_the_title($pid) ?: '';
      $content = (string) get_post_field('post_content', $pid);
      $content_snip = wp_strip_all_tags($content);
      if ( function_exists('mb_substr') ) { $content_snip = mb_substr($content_snip, 0, 800); }
      else { $content_snip = substr($content_snip, 0, 800); }

      $city_state = '';
      if ( function_exists('get_field') ) $city_state = (string) get_field('city_state', $pid);
      if ($city_state === '') $city_state = (string) get_post_meta($pid, 'city_state', true);

      $focuskw = (string) get_post_meta($pid, '_yoast_wpseo_focuskw', true);
      if ($focuskw === '') $focuskw = (string) get_post_meta($pid, 'yoast_focus_keyword', true);

      $excerpt = get_the_excerpt($pid);

      $html_excerpt = '';
      if ( function_exists('get_field') ) $html_excerpt = (string) get_field('html_excerpt', $pid);
      if ($html_excerpt === '') $html_excerpt = (string) get_post_meta($pid, 'html_excerpt', true);

      $about = (string) get_post_meta($pid, '_about_the_area', true);

      $map = [
        '{title}'        => $title,
        '{city_state}'   => $city_state,
        '{focuskw}'      => $focuskw,
        '{tone}'         => $tone,
        '{excerpt}'      => (string) $excerpt,
        '{html_excerpt}' => $html_excerpt,
        '{about}'        => $about,
        '{content_snip}' => $content_snip,
        '{target_words}' => (string) $words,
      ];
      return strtr($used_user_tpl, $map);
    };

    // ---- Run ----
    $ok=0; $err=0; $log=[];
    foreach ($ids as $pid) {
      if ( get_post_type($pid) !== 'service_area' ) {
        $err++; $log[] = "ID {$pid}: skip (not service_area)"; continue;
      }

      // Skip if we already have an HTML excerpt and not forcing
      $existing = '';
      if ( function_exists('get_field') ) $existing = (string) get_field('html_excerpt', $pid);
      if ($existing === '') $existing = (string) get_post_meta($pid, 'html_excerpt', true);
      if ($existing !== '' && ! $force) { $ok++; $log[] = "ID {$pid}: kept existing"; continue; }

      // Build final user prompt (exactly what we'll send)
      $final_user_prompt = $build_user_prompt($pid);

      // DEBUG: Log prompt preview(s)
      if ($debug_prompts) {
        $preview_user = function_exists('mb_substr') ? mb_substr($final_user_prompt, 0, $debug_len) : substr($final_user_prompt, 0, $debug_len);
        $log[] = "ID {$pid}: PROMPT (user, first {$debug_len} chars)\n" . $preview_user;

        if ($debug_include_sys) {
          $preview_sys = function_exists('mb_substr') ? mb_substr($used_sys_prompt, 0, $debug_len) : substr($used_sys_prompt, 0, $debug_len);
          $log[] = "ID {$pid}: PROMPT (system, first {$debug_len} chars)\n" . $preview_sys;
        }
      }

      // Call generator with the SAME prompts we logged
      $res = ssseo_ai_generate_sa_summary($pid, [
        'tone'        => $tone,
        'words'       => $words,
        'temperature' => $temp,
        'sys_prompt'  => $used_sys_prompt,
        'user_prompt' => $final_user_prompt,
      ]);

      if ( is_wp_error($res) ) { $err++; $log[] = "ID {$pid}: ERROR - " . $res->get_error_message(); }
      else { $ok++; $log[] = "ID {$pid}: OK"; }
    }

    wp_send_json_success(['ok'=>$ok,'err'=>$err,'log'=>$log]);
  }
}

// Test a specific Google Places Place ID with the provided/saved key.
add_action('wp_ajax_ssseo_test_places_pid', function () {
  if ( ! current_user_can('manage_options') ) {
    wp_send_json_error(['message' => 'Insufficient permissions']);
  }
  $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
  if ( empty($nonce) || ! wp_verify_nonce($nonce, 'ssseo_siteoptions_ajax') ) {
    wp_send_json_error(['message' => 'Invalid nonce']);
  }

  $key  = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';
  $pid  = isset($_POST['place_id']) ? sanitize_text_field(wp_unslash($_POST['place_id'])) : '';

  if ($key === '')  $key = get_option('ssseo_google_places_api_key', '');
  if ($pid === '')  $pid = get_option('ssseo_google_places_place_id', '');

  if ($key === '') {
    update_option('ssseo_places_pid_test_result', 'No key provided.');
    wp_send_json_error(['message' => 'No key provided']);
  }
  if ($pid === '') {
    update_option('ssseo_places_pid_test_result', 'No Place ID provided.');
    wp_send_json_error(['message' => 'No Place ID provided']);
  }

  // Query a few small fields so it’s cheap but meaningful.
  $endpoint = add_query_arg([
    'place_id' => $pid,
    'fields'   => 'name,formatted_address,url',
    'key'      => $key,
  ], 'https://maps.googleapis.com/maps/api/place/details/json');

  $resp = wp_remote_get($endpoint, ['timeout' => 20, 'sslverify' => true]);
  if (is_wp_error($resp)) {
    $msg = 'Network error: ' . $resp->get_error_message();
    update_option('ssseo_places_pid_test_result', $msg);
    wp_send_json_error(['message' => $msg]);
  }

  $code = wp_remote_retrieve_response_code($resp);
  $body = json_decode(wp_remote_retrieve_body($resp), true);
  $status = $body['status'] ?? 'UNKNOWN';
  $err = !empty($body['error_message']) ? $body['error_message'] : '';

  if ($code === 200 && $status === 'OK') {
    $name = $body['result']['name'] ?? '';
    $addr = $body['result']['formatted_address'] ?? '';
    $msg  = 'OK' . ($name ? " – {$name}" : '') . ($addr ? " ({$addr})" : '');
    update_option('ssseo_places_pid_test_result', $msg);
    wp_send_json_success(['message' => $msg]);
  }

  $msg = 'HTTP ' . intval($code) . ' / ' . $status . ($err ? ' – ' . $err : '');
  update_option('ssseo_places_pid_test_result', $msg);
  wp_send_json_error(['message' => $msg]);
});

/**
 * AJAX: Test Google Places / GBP API key
 * UI calls: $.post({ action:'ssseo_test_places_key', key, nonce:<ssseo_siteoptions_ajax> })
 */
add_action('wp_ajax_ssseo_test_places_key', function () {
    // Require nonce + capability (same pattern as other testers)
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if ( empty($nonce) || ! wp_verify_nonce($nonce, 'ssseo_siteoptions_ajax') ) {
        wp_send_json_error('Unauthorized (bad nonce)');
    }
    if ( ! current_user_can('edit_posts') ) {
        wp_send_json_error('Insufficient permissions');
    }

    // Prefer the typed key; fallback to saved option
    $key = sanitize_text_field($_POST['key'] ?? '');
    if ($key === '') {
        $key = get_option('ssseo_google_places_api_key', '');
    }
    if ($key === '') {
        ssseo_send_test_string(false, 'Missing Places API key', 'ssseo_places_test_result');
    }

    // Small, cheap test against a known Place ID (Google Sydney), asking for one tiny field
    $url = add_query_arg([
        'place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
        'fields'   => 'name',
        'key'      => $key,
    ], 'https://maps.googleapis.com/maps/api/place/details/json');

    // Use robust transport (normal -> IPv4 retry, Referer header, TLS verify)
    $resp = ssseo_http_get_robust($url, [
        'timeout'     => 20,
        'sslverify'   => true,
        'httpversion' => '1.1',
        'headers'     => [ 'Referer' => home_url('/') ],
    ]);

    if (is_wp_error($resp)) {
        ssseo_send_test_string(false, 'WP_Error: '.$resp->get_error_message(), 'ssseo_places_test_result');
    }

    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);
    $status = $body['status'] ?? 'UNKNOWN';
    $gerr = isset($body['error_message']) ? $body['error_message'] : '';

    if ($code === 200 && $status === 'OK') {
        $name = $body['result']['name'] ?? 'OK';
        ssseo_send_test_string(true, 'Places reachable (HTTP 200, '.$name.')', 'ssseo_places_test_result');
    } else {
        // Show helpful detail for debugging (restrictions, disabled API, etc.)
        $snippet = $gerr ?: substr((string)wp_remote_retrieve_body($resp), 0, 160);
        ssseo_send_test_string(false, "HTTP {$code} / {$status}".($snippet ? " — {$snippet}" : ''), 'ssseo_places_test_result');
    }
});
