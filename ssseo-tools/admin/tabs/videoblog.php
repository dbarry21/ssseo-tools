<?php
/**
 * Admin Tab: Video Blog (modular with subtabs)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Permission check
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'You do not have permission to access this page.' );
}

wp_enqueue_style(
  'bootstrap-icons',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
  [],
  '1.11.3'
);

// Subtab selection
$subtab = isset($_GET['subtab']) ? sanitize_key($_GET['subtab']) : 'youtube';

// Read current Site Options (keys now live there)
$yt_api_key = get_option('ssseo_youtube_api_key', '');
$yt_channel = get_option('ssseo_youtube_channel_id', '');

// Site Options URL (adjust if your router differs)
$site_options_url = admin_url('admin.php?page=ssseo-tools&tab=site-options');
?>
<div class="container-fluid">
  <?php if ( empty($yt_api_key) || empty($yt_channel) ) : ?>
    <div class="alert alert-warning mt-3">
      <i class="bi bi-exclamation-triangle-fill me-1"></i>
      <strong>YouTube not configured.</strong>
      Add your <em>YouTube API Key</em> and <em>Channel ID</em> in
      <a class="alert-link" href="<?php echo esc_url($site_options_url); ?>">Site Options → API Keys & Connections</a>.
    </div>
  <?php else: ?>
    <div class="alert alert-info mt-3">
      <i class="bi bi-info-circle-fill me-1"></i>
      YouTube is configured via <em>Site Options</em>.
      Channel ID: <code><?php echo esc_html($yt_channel); ?></code>
    </div>
  <?php endif; ?>

  <ul class="nav nav-tabs mb-4 border-bottom border-primary" role="tablist">
    <li class="nav-item" role="presentation">
      <a class="nav-link <?php echo $subtab === 'youtube' ? 'active fw-semibold text-primary border-primary border-bottom-0' : ''; ?> rounded-top"
         href="<?php echo esc_url( add_query_arg(['page'=>'ssseo-tools','tab'=>'videoblog','subtab'=>'youtube'], admin_url('admin.php')) ); ?>">
        <i class="bi bi-gear-fill me-1"></i> YouTube Settings
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?php echo $subtab === 'functions' ? 'active text-primary border-primary border-bottom-0' : ''; ?> rounded-top"
         href="<?php echo esc_url( add_query_arg(['page'=>'ssseo-tools','tab'=>'videoblog','subtab'=>'functions'], admin_url('admin.php')) ); ?>">
        <i class="bi bi-magic me-1"></i> YouTube Functions
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?php echo $subtab === 'videoid' ? 'active fw-semibold text-primary border-primary border-bottom-0' : ''; ?> rounded-top"
         href="<?php echo esc_url( add_query_arg(['page'=>'ssseo-tools','tab'=>'videoblog','subtab'=>'videoid'], admin_url('admin.php')) ); ?>">
        <i class="bi bi-ui-checks-grid me-1"></i> Video ID Tools
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?php echo $subtab === 'auth' ? 'active fw-semibold text-primary border-primary border-bottom-0' : ''; ?> rounded-top"
         href="<?php echo esc_url( add_query_arg(['page'=>'ssseo-tools','tab'=>'videoblog','subtab'=>'auth'], admin_url('admin.php')) ); ?>">
        <i class="bi bi-key me-1"></i> YouTube Auth
      </a>
    </li>
    <!--
    <li class="nav-item" role="presentation">
      <a class="nav-link <?php echo $subtab === 'captions' ? 'active' : ''; ?>"
         href="<?php echo esc_url( add_query_arg(['page'=>'ssseo-tools','tab'=>'videoblog','subtab'=>'captions'], admin_url('admin.php')) ); ?>">
        YouTube Captions
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?php echo $subtab === 'functions' ? 'active text-primary border-primary border-bottom-0' : ''; ?> rounded-top"
         href="<?php echo esc_url( add_query_arg(['page'=>'ssseo-tools','tab'=>'videoblog','subtab'=>'functions'], admin_url('admin.php')) ); ?>">
        <i class="bi bi-magic me-1"></i> YouTube Functions
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?php echo $subtab === 'mycaptions' ? 'active' : ''; ?>"
         href="<?php echo esc_url( add_query_arg(['page'=>'ssseo-tools','tab'=>'videoblog','subtab'=>'mycaptions'], admin_url('admin.php')) ); ?>">
        My Video Captions
      </a>
    </li>
    -->
  </ul>

  <div class="tab-content pt-2">
    <?php
    switch ( $subtab ) {
      case 'videoid':
        include __DIR__ . '/subtabs/youtube-videoid.php';
        break;

      case 'captions':
        include __DIR__ . '/subtabs/youtube-captions.php';
        break;

      case 'auth':
        include __DIR__ . '/subtabs/youtube-auth.php';
        break;

      case 'functions':
        include __DIR__ . '/subtabs/youtube-functions.php';
        break;

      case 'mycaptions':
        include __DIR__ . '/subtabs/youtube-my-captions.php';
        break;

      case 'youtube':
      default:
        // NEW minimal settings subtab that reflects Site Options (no key inputs here)
        include __DIR__ . '/subtabs/youtube-settings.php';
        break;
    }
    ?>
  </div>
</div>
