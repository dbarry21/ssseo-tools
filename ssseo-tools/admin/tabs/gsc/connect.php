<?php
// File: admin/tabs/gsc/connect.php
if (!defined('ABSPATH')) exit;

if ( ! current_user_can('manage_options') ) {
  wp_die('Insufficient permissions');
}

// Helper to compute a solid default redirect URI with the right scheme.
function ssseo_gsc_recommended_redirect() {
  $url = admin_url('admin-post.php?action=ssseo_gsc_oauth_cb');
  // If your site/home is HTTPS, force HTTPS on the admin URL too (helps behind proxies/CDNs)
  $home_is_https = (stripos(home_url(), 'https://') === 0);
  return set_url_scheme($url, $home_is_https ? 'https' : 'http');
}

$cid   = trim(get_option('ssseo_gsc_client_id', ''));
$csec  = trim(get_option('ssseo_gsc_client_secret', ''));
$redir_opt = trim(get_option('ssseo_gsc_redirect_uri', ''));
$recommended_redirect = ssseo_gsc_recommended_redirect();

// Prefer the option if set; otherwise use the recommended redirect.
$redir = $redir_opt ?: $recommended_redirect;

// Save a fresh state for CSRF protection
$state = wp_generate_password(16, false);
update_user_meta(get_current_user_id(), 'ssseo_gsc_oauth_state', $state);

// Determine connection status
$token = get_option('ssseo_gsc_token');
$is_connected = (is_array($token) && !empty($token['access_token']));

// Build Google OAuth URL only if we have the essentials
$auth_url = '';
if ($cid && $redir) {
  $params = [
    'client_id'              => $cid,
    'redirect_uri'           => $redir,
    'response_type'          => 'code',
    'access_type'            => 'offline', // need refresh_token
    'include_granted_scopes' => 'true',
    'prompt'                 => 'select_account consent', // force account picker + consent
    'scope'                  => 'https://www.googleapis.com/auth/webmasters.readonly',
    'state'                  => $state,
  ];
  $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

$nonce = wp_create_nonce('ssseo_siteoptions_ajax'); // reuse for Ping
$site_options_url = admin_url('admin.php?page=ssseo-tools&tab=site-options');

// Detect a likely mismatch to help the user
$mismatch_warning = ($redir_opt && $redir_opt !== $recommended_redirect);
?>
<div class="card shadow-sm">
  <div class="card-body">
    <h4 class="card-title mb-3">Connect to Google Search Console</h4>

    <?php if (!$cid || !$csec): ?>
      <div class="alert alert-warning">
        Enter your <strong>GSC Client ID</strong> and <strong>Client Secret</strong> in
        <a class="alert-link" href="<?php echo esc_url($site_options_url); ?>">Site Options</a>, then return here.
      </div>
    <?php endif; ?>

    <?php if ($mismatch_warning): ?>
      <div class="alert alert-info">
        <strong>Heads up:</strong> Your saved Redirect URI differs from the recommended one below. If you see
        <em>“Error 400: redirect_uri_mismatch”</em>, copy the recommended URI into both
        <em>Google Cloud Console → OAuth Client</em> and <em>Site Options</em>.
      </div>
    <?php endif; ?>

    <div class="mb-3">
      <label class="form-label">Your Current Redirect URI (saved in Site Options)</label>
      <input type="url" class="form-control" value="<?php echo esc_attr($redir); ?>" readonly>
    </div>

    <div class="mb-3">
      <label class="form-label">Recommended Redirect URI (copy this into Google Cloud Console)</label>
      <input type="url" class="form-control" value="<?php echo esc_attr($recommended_redirect); ?>" readonly>
      <div class="form-text">
        In Google Cloud Console: APIs &amp; Services → Credentials → your OAuth 2.0 Client → <em>Authorized redirect URIs</em>.
        Paste this exact value. Scheme/host/path must match exactly (e.g., <code>https://</code> vs <code>http://</code>, <code>www</code> vs non-<code>www</code>).
      </div>
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center">
      <?php if ($auth_url && !$is_connected): ?>
        <a class="button button-primary" href="<?php echo esc_url($auth_url); ?>">
          <i class="bi bi-google"></i> Connect with Google
        </a>
      <?php endif; ?>

      <?php if ($is_connected): ?>
        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline;">
          <?php wp_nonce_field('ssseo_gsc_disconnect', 'nonce'); ?>
          <input type="hidden" name="action" value="ssseo_gsc_disconnect">
          <button type="submit" class="button button-secondary">
            <i class="bi bi-x-circle"></i> Disconnect
          </button>
        </form>
        <button type="button" id="ssseo-gsc-ping" class="button">
          <i class="bi bi-cloud-check"></i> Ping GSC API
        </button>
      <?php endif; ?>
      <a class="button" href="<?php echo esc_url($site_options_url); ?>">
        <i class="bi bi-gear"></i> Site Options
      </a>
    </div>

    <?php if ( isset($_GET['gerr']) ): ?>
      <div class="alert alert-danger mt-3"><?php echo esc_html( wp_unslash($_GET['gerr']) ); ?></div>
    <?php elseif ( isset($_GET['gok']) ): ?>
      <div class="alert alert-success mt-3">Google connection updated.</div>
    <?php endif; ?>

    <div id="ssseo-gsc-connect-msg" class="mt-3 small text-muted">
      Status: <?php echo $is_connected ? 'Connected' : 'Not connected'; ?>
    </div>
    <pre id="ssseo-gsc-ping-out" class="mt-3" style="white-space:pre-wrap;word-break:break-word;"></pre>
  </div>
</div>

<script>
jQuery(function($){
  const POST_URL = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
  $('#ssseo-gsc-ping').on('click', function(){
    const $msg = $('#ssseo-gsc-connect-msg').text('Pinging…');
    const $out = $('#ssseo-gsc-ping-out').text('');
    $.post(POST_URL, { action: 'ssseo_gsc_ping', nonce: '<?php echo esc_js($nonce); ?>' }, function(res){
      if (res && res.success) {
        $msg.text('Ping OK');
        $out.text(JSON.stringify(res.data || {}, null, 2));
      } else {
        $msg.text('Ping failed');
        $out.text((res && res.data) ? res.data : 'Unknown error');
      }
    }).fail(function(){
      $msg.text('Ping failed: network error.');
    });
  });
});
</script>
