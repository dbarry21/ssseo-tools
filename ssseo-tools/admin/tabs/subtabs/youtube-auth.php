<?php
if (!current_user_can('manage_options')) {
  wp_die('Unauthorized');
}

$client_id = get_option('ssseo_google_client_id', '');
$client_secret = get_option('ssseo_google_client_secret', '');
$redirect_uri = admin_url('admin.php?page=ssseo-tools&tab=videoblog&subtab=auth');
$access_token = get_option('ssseo_google_access_token');
$refresh_token = get_option('ssseo_google_refresh_token');
?>

<h4 class="mb-3">Connect Your YouTube Account</h4>

<?php if (!empty($_GET['code'])): ?>
  <div class="alert alert-info">Authorization code received. Attempting to exchange for token...</div>
  <?php
    $response = wp_remote_post('https://oauth2.googleapis.com/token', [
      'body' => [
        'code' => sanitize_text_field($_GET['code']),
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code',
      ]
    ]);

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($body['access_token'])) {
      update_option('ssseo_google_access_token', $body['access_token']);
      if (!empty($body['refresh_token'])) {
        update_option('ssseo_google_refresh_token', $body['refresh_token']);
      }
      echo '<div class="alert alert-success">✅ Access token saved.</div>';
    } else {
      echo '<div class="alert alert-danger">❌ Failed to retrieve token.</div>';
    }
  ?>
<?php endif; ?>

<form method="post">
  <?php wp_nonce_field('ssseo_save_google_creds'); ?>
  <div class="mb-3">
    <label class="form-label">Client ID</label>
    <input type="text" name="ssseo_google_client_id" class="form-control" value="<?php echo esc_attr($client_id); ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Client Secret</label>
    <input type="text" name="ssseo_google_client_secret" class="form-control" value="<?php echo esc_attr($client_secret); ?>">
  </div>
  <button type="submit" name="ssseo_save_creds" class="btn btn-primary">Save Credentials</button>
</form>

<?php
if (isset($_POST['ssseo_save_creds']) && check_admin_referer('ssseo_save_google_creds')) {
  update_option('ssseo_google_client_id', sanitize_text_field($_POST['ssseo_google_client_id']));
  update_option('ssseo_google_client_secret', sanitize_text_field($_POST['ssseo_google_client_secret']));
  echo '<div class="alert alert-success mt-3">Saved!</div>';
}

if ($client_id && $client_secret) {
  $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => 'https://www.googleapis.com/auth/youtube.force-ssl',
    'access_type' => 'offline',
    'prompt' => 'consent',
  ]);
  echo '<a href="' . esc_url($auth_url) . '" class="btn btn-success mt-4">Connect with Google</a>';
}
?>
