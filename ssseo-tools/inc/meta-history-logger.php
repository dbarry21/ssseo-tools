<?php
// File: includes/meta-history-logger.php
if ( ! defined('ABSPATH') ) exit;

/**
 * Keys we track by default.
 * Filterable via 'ssseo_meta_hist_tracked_keys'.
 */
function ssseo_meta_hist_tracked_keys_default() {
  return [ '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ];
}
function ssseo_meta_hist_tracked_keys() {
  $keys = ssseo_meta_hist_tracked_keys_default();
  /**
   * Allow 3rd-parties to add/remove keys.
   * Example:
   *   add_filter('ssseo_meta_hist_tracked_keys', function($k){ $k[] = '_yoast_wpseo_bctitle'; return $k; });
   */
  return apply_filters('ssseo_meta_hist_tracked_keys', $keys);
}

/**
 * Guard to avoid infinite loops when writing our own history meta.
 */
function ssseo_meta_hist_guard( $set = null ) {
  static $busy = false;
  if ($set === null) return $busy;
  $busy = (bool) $set;
  return $busy;
}

/**
 * Append one row to history (newest first) with soft cap.
 */
function ssseo_meta_hist_append( $post_id, $field, $old, $new ) {
  if ( ssseo_meta_hist_guard() ) return; // don't re-enter
  $post_id = (int) $post_id;
  if ($post_id <= 0) return;

  $o = is_string($old) ? trim($old) : (is_scalar($old) ? (string)$old : '');
  $n = is_string($new) ? trim($new) : (is_scalar($new) ? (string)$new : '');
  if ($o === $n) return;

  // Ignore revisions/autosaves
  if ( wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) ) return;
  // Only posts (skip terms/users/etc.)
  if ( get_post_type($post_id) === '' ) return;

  $uid = get_current_user_id();
  $user_label = $uid ? ((get_user_by('id', $uid)->display_name ?? 'User') . ' (' . $uid . ')') : 'Unknown';

  $row = [
    'ts'    => current_time('mysql'),
    'user'  => $user_label,
    'field' => (string)$field,
    'old'   => (string)$o,
    'new'   => (string)$n,
  ];

  $key  = '_ssseo_meta_history';
  $hist = get_post_meta($post_id, $key, true);
  if ( ! is_array($hist) ) $hist = [];
  array_unshift($hist, $row);

  // Cap growth
  $CAP = (int) apply_filters('ssseo_meta_hist_cap', 500);
  if (count($hist) > $CAP) {
    $hist = array_slice($hist, 0, $CAP);
  }

  // Write without recursing our hooks
  ssseo_meta_hist_guard(true);
  update_post_meta($post_id, $key, $hist);
  ssseo_meta_hist_guard(false);
}

/**
 * Should we track this key?
 */
function ssseo_meta_hist_should_track( $key ) {
  return in_array( $key, ssseo_meta_hist_tracked_keys(), true );
}

/**
 * 1) PRE-UPDATE filter: fires before DB write, so we can read the "old" value safely.
 *    IMPORTANT: must return $check (first param) to avoid short-circuiting the update.
 */
add_filter('update_post_metadata', function( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
  if ( ssseo_meta_hist_guard() ) return $check;
  if ( ! ssseo_meta_hist_should_track($meta_key) ) return $check;

  // Only posts
  if ( get_post_type($object_id) === '' ) return $check;

  // Old value: read before core updates it
  $old = get_post_meta( $object_id, $meta_key, true );
  $new = is_array($meta_value) ? reset($meta_value) : $meta_value;

  ssseo_meta_hist_append( $object_id, $meta_key, $old, $new );
  return $check; // DO NOT short-circuit
}, 10, 5);

/**
 * 2) ADDED action: log when a tracked key is first added.
 */
add_action('added_post_meta', function( $meta_id, $object_id, $meta_key, $meta_value ) {
  if ( ssseo_meta_hist_guard() ) return;
  if ( ! ssseo_meta_hist_should_track($meta_key) ) return;
  if ( get_post_type($object_id) === '' ) return;

  $new = is_array($meta_value) ? reset($meta_value) : $meta_value;
  ssseo_meta_hist_append( $object_id, $meta_key, '', $new );
}, 10, 4);

/**
 * 3) DELETED action: log when a tracked key is deleted.
 *    This fires after deletion; WP passes the deleted $meta_value, so we can log "old -> ''".
 */
add_action('deleted_post_meta', function( $meta_ids, $object_id, $meta_key, $meta_value ) {
  if ( ssseo_meta_hist_guard() ) return;
  if ( ! ssseo_meta_hist_should_track($meta_key) ) return;
  if ( get_post_type($object_id) === '' ) return;

  $old = is_array($meta_value) ? reset($meta_value) : $meta_value;
  ssseo_meta_hist_append( $object_id, $meta_key, $old, '' );
}, 10, 4);

/**
 * 4) Fallback: on save_post (editor writes), compare previous vs incoming POST values
 *    in case a builder bypasses the metadata API hooks.
 */
add_action('save_post', function( $post_id, $post, $update ){
  if ( ssseo_meta_hist_guard() ) return;
  if ( wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) ) return;

  foreach ( ssseo_meta_hist_tracked_keys() as $k ) {
    if ( ! isset($_POST[$k]) ) continue;
    $prev = get_post_meta($post_id, $k, true);
    $incoming = wp_unslash( (string) $_POST[$k] );
    if ( trim((string)$prev) !== trim((string)$incoming) ) {
      ssseo_meta_hist_append( $post_id, $k, (string)$prev, (string)$incoming );
    }
  }
}, 10, 3);
