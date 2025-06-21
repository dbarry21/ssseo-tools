<?php

add_action( 'wp_ajax_ssseo_get_posts_by_type', function() {
    $post_type = sanitize_text_field( $_POST['post_type'] ?? '' );
    $search    = sanitize_text_field( $_POST['search'] ?? '' );

    if ( empty( $post_type ) || ! post_type_exists( $post_type ) ) {
        echo '<option disabled>No post type selected</option>';
        wp_die();
    }

    $args = [
        'post_type'   => $post_type,
        'numberposts' => 100,
        'post_status' => 'any',
        'orderby'     => 'title',
        'order'       => 'ASC',
    ];

    if ( $search ) {
        $args['s'] = $search;
    }

    $posts = get_posts( $args );

    if ( empty($posts) ) {
        echo '<option disabled>No posts found</option>';
    } else {
        foreach ( $posts as $post ) {
            echo '<option value="' . esc_attr($post->ID) . '">' . esc_html($post->post_title) . ' (#' . $post->ID . ')</option>';
        }
    }

    wp_die();
});

add_action( 'wp_ajax_ssseo_yoast_set_index_follow', function() {
    check_ajax_referer( 'ssseo_admin_nonce', '_wpnonce' );

    $ids = isset($_POST['post_ids']) ? array_map( 'intval', $_POST['post_ids'] ) : [];

    if ( empty( $ids ) ) {
        wp_send_json_error( 'No posts selected.' );
    }

    foreach ( $ids as $post_id ) {
        update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', '0' );
        update_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', '0' );
    }

    wp_send_json_success( 'Yoast set to index, follow for selected posts.' );
});

add_action( 'wp_ajax_ssseo_yoast_reset_canonical', function () {
    check_ajax_referer( 'ssseo_admin_nonce', '_wpnonce' );
    $ids = array_map( 'intval', $_POST['post_ids'] ?? [] );

    foreach ( $ids as $post_id ) {
        $permalink = get_permalink( $post_id );
        update_post_meta( $post_id, '_yoast_wpseo_canonical', $permalink );
    }

    wp_send_json_success();
});

add_action( 'wp_ajax_ssseo_yoast_clear_canonical', function () {
    check_ajax_referer( 'ssseo_admin_nonce', '_wpnonce' );
    $ids = array_map( 'intval', $_POST['post_ids'] ?? [] );

    foreach ( $ids as $post_id ) {
        delete_post_meta( $post_id, '_yoast_wpseo_canonical' );
    }

    wp_send_json_success();
});

add_action( 'wp_ajax_ssseo_get_meta_history', function () {
  check_ajax_referer( 'ssseo_admin_nonce', '_wpnonce' );
  $post_id = intval( $_POST['post_id'] ?? 0 );
  if ( ! $post_id ) {
    wp_send_json_error( 'Invalid post ID' );
  }

  $log = get_post_meta( $post_id, '_ssseo_meta_log', true );
  if ( ! is_array( $log ) ) {
    $log = [];
  }

  $current_title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
  $current_desc  = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );

  $html  = '<h3>Current Yoast Meta</h3>';
  $html .= '<table class="widefat striped"><thead><tr><th>Field</th><th>Current Value</th></tr></thead><tbody>';
  $html .= '<tr><td>Title</td><td>' . esc_html($current_title) . '</td></tr>';
  $html .= '<tr><td>Description</td><td>' . esc_html($current_desc) . '</td></tr>';
  $html .= '</tbody></table>';

  if ( empty($log) ) {
    $html .= '<p>No historical changes recorded.</p>';
  } else {
    $html .= '<h3>Change History</h3>';
    $html .= '<table class="widefat striped"><thead><tr><th>Time</th><th>Field</th><th>Value</th><th>User</th></tr></thead><tbody>';

    foreach ( array_reverse($log) as $entry ) {
      $html .= '<tr>';
      $html .= '<td>' . esc_html($entry['time']) . '</td>';
      $html .= '<td>' . ($entry['field'] === '_yoast_wpseo_title' ? 'Title' : 'Meta Description') . '</td>';
      $html .= '<td>' . esc_html($entry['value']) . '</td>';
      $html .= '<td>' . esc_html(get_userdata($entry['user'])->display_name ?? 'Unknown') . '</td>';
      $html .= '</tr>';
    }

    $html .= '</tbody></table>';
  }

  $csv = "Time,Field,Value,User\n";
  foreach ( $log as $entry ) {
    $user = get_userdata($entry['user']);
    $csv .= sprintf(
      "\"%s\",\"%s\",\"%s\",\"%s\"\n",
      $entry['time'],
      $entry['field'] === '_yoast_wpseo_title' ? 'Title' : 'Meta Description',
      str_replace('"', '\"', $entry['value']),
      $user ? $user->display_name : 'Unknown'
    );
  }

  wp_send_json_success([
    'html'     => $html,
    'csv'      => $csv,
    'filename' => 'meta-history-' . $post_id . '.csv',
  ]);
});


function ssseo_test_openai_key() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $key = sanitize_text_field($_POST['key'] ?? '');
    if (!$key) {
        wp_send_json_error('No key provided.');
    }

    $response = wp_remote_get('https://api.openai.com/v1/models', [
        'headers' => [
            'Authorization' => 'Bearer ' . $key,
        ],
        'timeout' => 10,
    ]);

    $timestamp = current_time('mysql');

    if (is_wp_error($response)) {
        $msg = '❌ ' . $response->get_error_message() . " (tested $timestamp)";
        update_option('ssseo_openai_test_result', $msg);
        wp_send_json_error($msg);
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code === 200) {
        $msg = "✅ OpenAI key is valid (tested $timestamp)";
        update_option('ssseo_openai_test_result', $msg);
        wp_send_json_success($msg);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $error = $body['error']['message'] ?? 'Unknown error';
    $msg = "❌ Invalid: $error (tested $timestamp)";
    update_option('ssseo_openai_test_result', $msg);
    wp_send_json_error($msg);
}

function ssseo_test_maps_key() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $key = sanitize_text_field($_POST['key'] ?? '');
    if (!$key) {
        wp_send_json_error('No key provided.');
    }

    $url = "https://maps.googleapis.com/maps/api/staticmap?center=New+York,NY&zoom=10&size=400x200&key=" . urlencode($key);
    $response = wp_remote_get($url, ['timeout' => 10]);
    $timestamp = current_time('mysql');

    if (is_wp_error($response)) {
        $msg = '❌ ' . $response->get_error_message() . " (tested $timestamp)";
        update_option('ssseo_maps_test_result', $msg);
        wp_send_json_error($msg);
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code === 200) {
        $msg = "✅ Maps key is valid (tested $timestamp)";
        update_option('ssseo_maps_test_result', $msg);
        wp_send_json_success($msg);
    } else {
        $msg = "❌ Invalid or restricted Maps key (tested $timestamp)";
        update_option('ssseo_maps_test_result', $msg);
        wp_send_json_error($msg);
    }
}
