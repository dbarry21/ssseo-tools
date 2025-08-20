<?php
/**
 * Admin UI: Site Options
 * - Google Static Maps key (+ test)
 * - OpenAI key (+ test)
 * - YouTube API key + Channel ID (moved here) (+ test)
 * - Google Search Console OAuth fields (+ check)
 *
 * Version: 2.1
 */
if (!defined('ABSPATH')) exit;

// ----- Save -----
if (
  isset($_POST['ssseo_siteoptions_nonce']) &&
  wp_verify_nonce($_POST['ssseo_siteoptions_nonce'], 'ssseo_siteoptions_save') &&
  current_user_can('manage_options')
) {
  // Existing
  update_option('ssseo_enable_map_as_featured', isset($_POST['ssseo_enable_map_as_featured']) ? '1' : '0');
  update_option('ssseo_google_static_maps_api_key', sanitize_text_field($_POST['ssseo_google_static_maps_api_key'] ?? ''));
  update_option('ssseo_openai_api_key', sanitize_text_field($_POST['ssseo_openai_api_key'] ?? ''));

  // NEW: YouTube (moved from Video Blog)
  update_option('ssseo_youtube_api_key', sanitize_text_field($_POST['ssseo_youtube_api_key'] ?? ''));
  update_option('ssseo_youtube_channel_id', sanitize_text_field($_POST['ssseo_youtube_channel_id'] ?? ''));

  // NEW: Google Search Console OAuth
  update_option('ssseo_gsc_client_id', sanitize_text_field($_POST['ssseo_gsc_client_id'] ?? ''));
  update_option('ssseo_gsc_client_secret', sanitize_text_field($_POST['ssseo_gsc_client_secret'] ?? ''));
  update_option('ssseo_gsc_redirect_uri', esc_url_raw($_POST['ssseo_gsc_redirect_uri'] ?? ''));

  echo '<div class="notice notice-success is-dismissible"><p>Site Options saved.</p></div>';
}

// ----- Fetch -----
$enable_maps = get_option('ssseo_enable_map_as_featured', '0');
$maps_api    = get_option('ssseo_google_static_maps_api_key', '');
$openai_key  = get_option('ssseo_openai_api_key', '');

$yt_api_key  = get_option('ssseo_youtube_api_key', '');
$yt_channel  = get_option('ssseo_youtube_channel_id', '');

$gsc_id      = get_option('ssseo_gsc_client_id', '');
$gsc_secret  = get_option('ssseo_gsc_client_secret', '');
$gsc_redirect = get_option('ssseo_gsc_redirect_uri', admin_url('admin-post.php?action=ssseo_gsc_oauth_cb'));


// Last test strings (stored by AJAX handlers)
$last_maps_test    = get_option('ssseo_maps_test_result', 'No test run yet.');
$last_openai_test  = get_option('ssseo_openai_test_result', 'No test run yet.');
$last_youtube_test = get_option('ssseo_youtube_test_result', 'No test run yet.');
$last_gsc_test     = get_option('ssseo_gsc_test_result', 'No check run yet.');

// Nonce for AJAX tests on this page
$siteoptions_ajax_nonce = wp_create_nonce('ssseo_siteoptions_ajax');

// Small helper to mask secrets in UI (no dependencies)
function ssseo_mask_key_simple($k) {
  $k = (string) $k;
  if ($k === '') return '';
  $len = strlen($k);
  if ($len <= 8) return str_repeat('•', max(0, $len-2)) . substr($k, -2);
  return substr($k, 0, 4) . str_repeat('•', $len - 8) . substr($k, -4);
}
?>
<div class="wrap">
  <h1 class="wp-heading-inline">Site Options</h1>
  <hr class="wp-header-end">

  <form method="post" class="mt-4">
    <?php wp_nonce_field('ssseo_siteoptions_save', 'ssseo_siteoptions_nonce'); ?>

    <!-- Enable Maps as Featured Image -->
    <div class="card" style="max-width: 980px;">
      <div class="card-body">
        <h2 class="title">General</h2>
        <label class="form-check mb-3" style="display:block;">
          <input class="form-check-input" type="checkbox" id="ssseo_enable_map_as_featured" name="ssseo_enable_map_as_featured" value="1" <?php checked('1', $enable_maps); ?>>
          <span class="form-check-label">Enable Google Maps as Featured Image</span>
        </label>
        <p class="description">Adds a “Generate Map” option to the Service Area featured image box.</p>
      </div>
    </div>

    <div class="row" style="display:flex; gap:16px; flex-wrap:wrap; margin-top:16px; max-width: 980px;">
      <!-- Google Static Maps -->
      <div class="card" style="flex:1 1 460px; min-width:460px;">
        <div class="card-body">
          <h2 class="title">Google Static Maps</h2>
          <label for="ssseo_google_static_maps_api_key" class="form-label">API Key</label>
          <div class="input-group" style="display:flex; gap:8px;">
            <input type="text" class="regular-text" id="ssseo_google_static_maps_api_key" name="ssseo_google_static_maps_api_key" value="<?php echo esc_attr($maps_api); ?>" placeholder="AIza...">
            <button type="button" class="button" id="test-maps-api">Test</button>
          </div>
          <p class="description">Used for generating featured static map images.</p>
          <p class="description">Last test: <em><?php echo esc_html($last_maps_test); ?></em></p>
          <div id="maps-api-test-result" class="notice inline" style="margin-top:8px;"></div>
        </div>
      </div>

      <!-- OpenAI -->
      <div class="card" style="flex:1 1 460px; min-width:460px;">
        <div class="card-body">
          <h2 class="title">OpenAI</h2>
          <label for="ssseo_openai_api_key" class="form-label">API Key</label>
          <div class="input-group" style="display:flex; gap:8px;">
            <input type="text" class="regular-text" id="ssseo_openai_api_key" name="ssseo_openai_api_key" value="<?php echo esc_attr($openai_key); ?>" placeholder="sk-...">
            <button type="button" class="button" id="test-openai-api">Test</button>
          </div>
          <p class="description">Used for AI-powered content generation features.</p>
          <p class="description">Last test: <em><?php echo esc_html($last_openai_test); ?></em></p>
          <div id="openai-api-test-result" class="notice inline" style="margin-top:8px;"></div>
        </div>
      </div>

      <!-- YouTube (moved here) -->
      <div class="card" style="flex:1 1 460px; min-width:460px;">
        <div class="card-body">
          <h2 class="title">YouTube</h2>
          <div class="row" style="display:flex; gap:8px; flex-wrap:wrap;">
            <div style="flex:1 1 60%;">
              <label class="form-label" for="ssseo_youtube_api_key">API Key</label>
              <input type="text" class="regular-text" id="ssseo_youtube_api_key" name="ssseo_youtube_api_key" value="<?php echo esc_attr($yt_api_key); ?>" placeholder="AIza...">
              <?php if ($yt_api_key) : ?>
                <p class="description">Current: <code><?php echo esc_html(ssseo_mask_key_simple($yt_api_key)); ?></code></p>
              <?php endif; ?>
            </div>
            <div style="flex:1 1 38%;">
              <label class="form-label" for="ssseo_youtube_channel_id">Channel ID</label>
              <input type="text" class="regular-text" id="ssseo_youtube_channel_id" name="ssseo_youtube_channel_id" value="<?php echo esc_attr($yt_channel); ?>" placeholder="UCxxxxxxxxxxxx">
            </div>
          </div>
          <div style="margin-top:8px;">
            <button type="button" class="button" id="test-youtube-api">Test YouTube</button>
          </div>
          <p class="description" style="margin-top:8px;">Last test: <em><?php echo esc_html($last_youtube_test); ?></em></p>
          <div id="youtube-api-test-result" class="notice inline" style="margin-top:8px;"></div>
        </div>
      </div>

      <!-- Google Search Console -->
      <div class="card" style="flex:1 1 460px; min-width:460px;">
        <div class="card-body">
          <h2 class="title">Google Search Console</h2>
          <div class="row" style="display:flex; gap:8px; flex-wrap:wrap;">
            <div style="flex:1 1 48%;">
              <label class="form-label" for="ssseo_gsc_client_id">Client ID</label>
              <input type="text" class="regular-text" id="ssseo_gsc_client_id" name="ssseo_gsc_client_id" value="<?php echo esc_attr($gsc_id); ?>" placeholder="xxxx.apps.googleusercontent.com">
            </div>
            <div style="flex:1 1 48%;">
              <label class="form-label" for="ssseo_gsc_client_secret">Client Secret</label>
              <input type="text" class="regular-text" id="ssseo_gsc_client_secret" name="ssseo_gsc_client_secret" value="<?php echo esc_attr($gsc_secret); ?>" placeholder="••••••••••••••">
            </div>
            <div style="flex:1 1 100%;">
              <label class="form-label" for="ssseo_gsc_redirect_uri">OAuth Redirect URI</label>
              <input type="url" class="regular-text" id="ssseo_gsc_redirect_uri" name="ssseo_gsc_redirect_uri" value="<?php echo esc_attr($gsc_redirect); ?>">
              <p class="description">Add this exact URI to your OAuth client in Google Cloud Console.</p>
            </div>
          </div>
          <div style="margin-top:8px;">
            <button type="button" class="button" id="test-gsc-api">Check GSC Setup</button>
          </div>
          <p class="description" style="margin-top:8px;">Last check: <em><?php echo esc_html($last_gsc_test); ?></em></p>
          <div id="gsc-api-test-result" class="notice inline" style="margin-top:8px;"></div>
        </div>
      </div>
    </div>

    <p class="submit" style="margin-top:16px;">
      <button type="submit" class="button button-primary">Save Site Options</button>
    </p>
  </form>
</div>

<script>
jQuery(function($){
  // Use explicit admin-ajax URL; also backfill window.ajaxurl just in case.
  const POST_URL = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
  if (typeof window.ajaxurl === 'undefined') window.ajaxurl = POST_URL;

  // Existing tests (post the key directly)
  $('#test-openai-api').on('click', function() {
    const key = $('#ssseo_openai_api_key').val();
    const $box = $('#openai-api-test-result').removeClass('notice-success notice-error').html('<em>Testing…</em>');
    $.post(POST_URL, { action: 'ssseo_test_openai_key', key: key }, function(r){
      if (r && r.success) {
        $box.addClass('notice-success').html('<p>' + (r.data || 'OpenAI OK') + '</p>');
      } else {
        $box.addClass('notice-error').html('<p>' + ((r && r.data) || 'OpenAI test failed') + '</p>');
      }
    }).fail(function(){
      $box.addClass('notice-error').html('<p>Network error during OpenAI test</p>');
    });
  });

  $('#test-maps-api').on('click', function() {
    const key = $('#ssseo_google_static_maps_api_key').val();
    const $box = $('#maps-api-test-result').removeClass('notice-success notice-error').html('<em>Testing…</em>');
    $.post(POST_URL, { action: 'ssseo_test_maps_key', key: key }, function(r){
      if (r && r.success) {
        $box.addClass('notice-success').html('<p>' + (r.data || 'Maps OK') + '</p>');
      } else {
        $box.addClass('notice-error').html('<p>' + ((r && r.data) || 'Maps test failed') + '</p>');
      }
    }).fail(function(){
      $box.addClass('notice-error').html('<p>Network error during Maps test</p>');
    });
  });

  // New tests (server reads options; we pass a nonce)
  const ajaxNonce = '<?php echo esc_js( $siteoptions_ajax_nonce ); ?>';

  $('#test-youtube-api').on('click', function() {
    const $box = $('#youtube-api-test-result').removeClass('notice-success notice-error').html('<em>Testing…</em>');
    $.post(POST_URL, { action: 'ssseo_test_youtube_api', nonce: ajaxNonce }, function(r){
      if (r && r.success) {
        $box.addClass('notice-success').html('<p>' + (r.data || 'YouTube OK') + '</p>');
      } else {
        $box.addClass('notice-error').html('<p>' + ((r && r.data) || 'YouTube test failed') + '</p>');
      }
    }).fail(function(){
      $box.addClass('notice-error').html('<p>Network error during YouTube test</p>');
    });
  });

  $('#test-gsc-api').on('click', function() {
    const $box = $('#gsc-api-test-result').removeClass('notice-success notice-error').html('<em>Checking…</em>');
    $.post(POST_URL, { action: 'ssseo_test_gsc_client', nonce: ajaxNonce }, function(r){
      if (r && r.success) {
        $box.addClass('notice-success').html('<p>' + (r.data || 'GSC client configured') + '</p>');
      } else {
        $box.addClass('notice-error').html('<p>' + ((r && r.data) || 'GSC check failed') + '</p>');
      }
    }).fail(function(){
      $box.addClass('notice-error').html('<p>Network error during GSC check</p>');
    });
  });
});
</script>

