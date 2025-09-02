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
                    if ($debug) {
                        $log[] = " - title shortcode rendered: “{$old_title}” → “{$rendered}”.";
                    }
                } elseif ($debug) {
                    $log[] = " - title shortcode rendered: no change.";
                }
            }

            $log[] = "Cloned to #{$new_id} under parent #{$parent_id} ({$status}).";
            if ($debug) {
                $log[] = " - city_state set to: " . ($parent_city_state !== '' ? $parent_city_state : '(empty)');
            }
        }

        wp_send_json_success(['log' => $log]);

    } catch (Throwable $e) {
        if (function_exists('error_log')) error_log('[SSSEO clone] ' . $e->getMessage());
        wp_send_json_error('Server error: ' . $e->getMessage());
    }
});
