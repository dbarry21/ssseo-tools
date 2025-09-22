<?php
// admin/tabs/subtabs/youtube-functions.php
if (!defined('ABSPATH')) exit;

$yt_api_key  = get_option('ssseo_youtube_api_key', '');
$yt_channel  = get_option('ssseo_youtube_channel_id', '');
$last_test   = get_option('ssseo_youtube_test_result', 'No test run yet.');
$debug_on    = (bool) get_option('ssseo_youtube_debug', false);

$nonce_ajax  = wp_create_nonce('ssseo_siteoptions_ajax');
$site_options_url = admin_url('admin.php?page=ssseo-tools&tab=site-options');

function ssseo_mask_key($k) {
  $k = (string)$k; if ($k==='') return '';
  $len = strlen($k);
  if ($len <= 8) return str_repeat('•', max(0,$len-2)) . substr($k, -2);
  return substr($k, 0, 4) . str_repeat('•', $len - 8) . substr($k, -4);
}
?>
<script>window.ajaxurl = window.ajaxurl || "<?php echo esc_js( admin_url('admin-ajax.php') ); ?>";</script>

<div class="card shadow-sm">
  <div class="card-body">
    <h4 class="card-title mb-3">YouTube Integration</h4>

    <?php if ( empty($yt_api_key) || empty($yt_channel) ) : ?>
      <div class="alert alert-warning">
        <strong>Missing configuration.</strong> Add your YouTube API Key and Channel ID in
        <a class="alert-link" href="<?php echo esc_url($site_options_url); ?>">Site Options</a>.
      </div>
    <?php endif; ?>

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

    <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
      <button type="button" id="ssseo-test-youtube" class="btn btn-outline-primary">
        <span class="dashicons dashicons-rest-api" style="vertical-align:middle"></span> Test YouTube
      </button>

      <button type="button" id="ssseo-fetch-create-drafts" class="btn btn-primary">
        <span class="dashicons dashicons-update-alt" style="vertical-align:middle"></span> Fetch &amp; Create Drafts
      </button>

      <div class="ms-2">
        <label class="form-label d-block mb-1">Slug Mode</label>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="yt-slug-mode" id="yt-slug-id" value="id" checked>
          <label class="form-check-label" for="yt-slug-id">videoId (legacy)</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="yt-slug-mode" id="yt-slug-title" value="title">
          <label class="form-check-label" for="yt-slug-title">title-based</label>
        </div>
      </div>

      <div class="form-check ms-3">
        <input class="form-check-input" type="checkbox" id="ssseo-youtube-dryrun">
        <label class="form-check-label" for="ssseo-youtube-dryrun">Dry run (don’t write)</label>
      </div>

      <div class="form-check ms-3">
        <input class="form-check-input" type="checkbox" id="ssseo-youtube-debug" <?php checked($debug_on); ?>>
        <label class="form-check-label" for="ssseo-youtube-debug">Enable Debug Log</label>
      </div>

      <a class="btn btn-outline-secondary ms-2" href="<?php echo esc_url($site_options_url); ?>">
        <span class="dashicons dashicons-admin-generic" style="vertical-align:middle"></span> Edit in Site Options
      </a>
    </div>

    <div id="ssseo-youtube-test-result" class="small text-muted mt-2">
      Last test: <?php echo esc_html($last_test); ?>
    </div>

    <hr class="my-3"/>

    <h5 class="mb-2">Generation Output</h5>
    <div id="ssseo-youtube-generate-output" class="border rounded p-2 bg-light" style="min-height:48px"></div>

    <h5 class="mt-4 mb-2">Debug Log</h5>
    <div class="d-flex gap-2 mb-2">
      <button id="ssseo-youtube-log-refresh" class="button">Refresh Log</button>
      <button id="ssseo-youtube-log-clear" class="button button-secondary">Clear Log</button>
    </div>
    <pre id="ssseo-youtube-log" class="border rounded p-2" style="max-height:260px; overflow:auto; background:#f8f9fa"></pre>
  </div>
</div>

<script>
jQuery(function($){
  const NONCE = '<?php echo esc_js($nonce_ajax); ?>';
  const $testOut = $('#ssseo-youtube-test-result');
  const $genOut  = $('#ssseo-youtube-generate-output');
  const $log     = $('#ssseo-youtube-log');
  const $debug   = $('#ssseo-youtube-debug');

  function showError($el, msg){ $el.removeClass('text-success').addClass('text-danger').text(msg); }
  function showOK($el, msg){ $el.removeClass('text-danger').addClass('text-success').text(msg); }
  function ajax(action, data, ok, fail){
    $.post(window.ajaxurl, $.extend({ action: action, nonce: NONCE }, data || {}))
      .done(function(res){ if (res && res.success) ok && ok(res); else fail && fail(res); })
      .fail(function(xhr){ fail && fail({success:false, data:'HTTP '+xhr.status}); });
  }

  // Test endpoint (uses your existing tester)
  $('#ssseo-test-youtube').on('click', function(){
    $testOut.removeClass('text-danger text-success').text('Testing YouTube…');
    ajax('ssseo_test_youtube_api', {}, function(res){
      showOK($testOut, res.data || 'YouTube OK');
    }, function(res){
      showError($testOut, (res && res.data) ? res.data : 'YouTube test failed');
    });
  });

  // Generate drafts (ONLY when you click)
  $('#ssseo-fetch-create-drafts').on('click', function(){
    const $btn = $(this).prop('disabled', true);
    $genOut.removeClass('text-danger text-success').text('Working…');
    const slugMode = $('input[name="yt-slug-mode"]:checked').val() || 'id';
    const dryRun   = $('#ssseo-youtube-dryrun').is(':checked') ? 1 : 0;

    ajax('ssseo_youtube_generate_drafts',
      { do_run: 1, slug_mode: slugMode, dry_run: dryRun, max: 500 },
      function(res){
        const d = res.data || {};
        const msg = `Fetched: ${d.fetched||0} · New: ${d.new_posts||0} · Updated: ${d.updated_posts||0} · Existing (total): ${d.existing_posts||0} · — by meta: ${d.existing_meta||0}, by slug: ${d.existing_slug||0} · Skipped: ${d.skipped||0} · Run: ${d.sig||''}${d.dry_run? ' · DRY RUN':''}`;
        showOK($genOut, msg);
      },
      function(res){
        showError($genOut, (res && res.data && res.data.message) ? res.data.message : (res && res.data ? res.data : 'Failed'));
      }
    ).always && $.noop();
    $btn.prop('disabled', false);
  });

  // Debug on/off
  $debug.on('change', function(){
    ajax('ssseo_youtube_toggle_debug', { enabled: this.checked ? 1 : 0 }, function(){}, function(){});
  });

  // Log helpers
  function refreshLog(){
    $log.text('Loading log…');
    ajax('ssseo_youtube_get_log', {}, function(res){
      const lines = (res.data && res.data.log) ? res.data.log : [];
      $log.text(lines.join("\n"));
    }, function(res){
      $log.text('Failed to load log');
    });
  }
  $('#ssseo-youtube-log-refresh').on('click', refreshLog);
  $('#ssseo-youtube-log-clear').on('click', function(){
    if (!confirm('Clear YouTube debug log?')) return;
    ajax('ssseo_youtube_clear_log', {}, function(){ refreshLog(); }, function(){});
  });
  refreshLog();
});
</script>
