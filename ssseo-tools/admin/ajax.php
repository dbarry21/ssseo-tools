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
