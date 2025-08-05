<?php
/**
 * Admin Tab: Video Blog (modular with subtabs)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Permission check
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'You do not have permission to access this page.' );
}
wp_enqueue_style('bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css', [], '1.11.3');

$subtab = $_GET['subtab'] ?? 'youtube';
?>

<div class="container-fluid">

  <ul class="nav nav-tabs mb-4 border-bottom border-primary" role="tablist">
  <li class="nav-item" role="presentation">
    <a class="nav-link <?php echo $subtab === 'youtube' ? 'active fw-semibold text-primary border-primary border-bottom-0' : ''; ?> rounded-top"
       href="?page=ssseo-tools&tab=videoblog&subtab=youtube">
      <i class="bi bi-gear-fill me-1"></i> YouTube Integration
    </a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link <?php echo $subtab === 'videoid' ? 'active fw-semibold text-primary border-primary border-bottom-0' : ''; ?> rounded-top"
       href="?page=ssseo-tools&tab=videoblog&subtab=videoid">
      <i class="bi bi-ui-checks-grid me-1"></i> Video ID Tools
    </a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link <?php echo $subtab === 'auth' ? 'active fw-semibold text-primary border-primary border-bottom-0' : ''; ?> rounded-top"
       href="?page=ssseo-tools&tab=videoblog&subtab=auth">
      <i class="bi bi-key me-1"></i> YouTube Auth
    </a>
  </li>

	  <!--
	  <li class="nav-item">
  <a class="nav-link <?php echo $subtab === 'captions' ? 'active' : ''; ?>"
     href="?page=ssseo-tools&tab=videoblog&subtab=captions">
    YouTube Captions
  </a>
</li>

<li class="nav-item">
  <a class="nav-link <?php echo $subtab === 'mycaptions' ? 'active' : ''; ?>"
     href="?page=ssseo-tools&tab=videoblog&subtab=mycaptions">
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

  case 'youtube':
  default:
    include __DIR__ . '/subtabs/youtube-settings.php';
    break;
			
  case 'auth':
  include __DIR__ . '/subtabs/youtube-auth.php';
  break;

  case 'mycaptions':
  	include __DIR__ . '/subtabs/youtube-my-captions.php';
  	break;
}

    ?>
  </div>

</div>
<script>
const ssseoPostsByType = <?= wp_json_encode($posts_by_type); ?>;
const ssseoDefaultType = "<?= esc_js($default_pt); ?>";
</script>
