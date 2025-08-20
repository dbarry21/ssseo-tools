<?php
if ( ! defined('ABSPATH') ) exit;

$yt_api_key  = get_option('ssseo_youtube_api_key', '');
$yt_channel  = get_option('ssseo_youtube_channel_id', '');
$last_test   = get_option('ssseo_youtube_test_result', 'No test run yet.');
$nonce_ajax  = wp_create_nonce('ssseo_siteoptions_ajax'); // reuse same action for tests
$site_options_url = admin_url('admin.php?page=ssseo-tools&tab=site-options');

function ssseo_mask_key($k) {
  $k = (string) $k;
  if ($k === '') return '';
  $len = strlen($k);
  if ($len <= 8) return str_repeat('•', max(0,$len-2)) . substr($k, -2);
  return substr($k, 0, 4) . str_repeat('•', $len - 8) . substr($k, -4);
}
?>
<div class="card shadow-sm">
  <div class="card-body">
    <h4 class="card-title mb-3">YouTube Integration</h4>

    <?php if ( empty($yt_api_key) || empty($yt_channel) ) : ?>
      <div class="alert alert-warning">
        <strong>Missing configuration.</strong> Add your YouTube API Key and Channel ID in
        <a class="alert-link" href="<?php echo esc_url($site_options_url); ?>">Site Options</a>.
      </div>
    <?php else: ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">API Key</label>
          <div class="form-control bg-light" style="user-select:text"><?php echo esc_html( ssseo_mask_key($yt_api_key) ); ?></div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Channel ID</label>
          <div class="form-control bg-light" style="user-select:text"><code><?php echo esc_html($yt_channel); ?></code></div>
        </div>
      </div>
      <div class="mt-3 d-flex gap-2 align-items-center">
        <button type="button" id="ssseo-test-youtube-here" class="btn btn-outline-primary">
          <i class="bi bi-plug"></i> Test YouTube
        </button>
        <a class="btn btn-outline-secondary" href="<?php echo esc_url($site_options_url); ?>">
          <i class="bi bi-gear"></i> Edit in Site Options
        </a>
        <div id="ssseo-youtube-test-result" class="small text-muted ms-2">Last test: <?php echo esc_html($last_test); ?></div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
jQuery(function($){
  $('#ssseo-test-youtube-here').on('click', function(){
    const $out = $('#ssseo-youtube-test-result').removeClass('text-danger text-success').text('Testing YouTube…');
    $.post(ajaxurl, { action: 'ssseo_test_youtube_api', nonce: '<?php echo esc_js($nonce_ajax); ?>' }, function(res){
      if (res && res.success) {
        $out.addClass('text-success').text(res.data || 'YouTube OK');
      } else {
        $out.addClass('text-danger').text((res && res.data) ? res.data : 'YouTube test failed');
      }
    }).fail(function(){
      $out.addClass('text-danger').text('Network error during YouTube test');
    });
  });
});
</script>
