<?php
add_action('updated_post_meta', function($meta_id, $post_id, $meta_key, $meta_value) {
  if (in_array($meta_key, ['_yoast_wpseo_title', '_yoast_wpseo_metadesc'])) {
    $log = get_post_meta($post_id, '_ssseo_meta_log', true);
    if (!is_array($log)) $log = [];

    $log[] = [
      'time'  => current_time('mysql'),
      'field' => $meta_key,
      'value' => $meta_value,
      'user'  => get_current_user_id(),
    ];

    update_post_meta($post_id, '_ssseo_meta_log', $log);
  }
}, 10, 4);

