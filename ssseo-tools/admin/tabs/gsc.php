<?php
// File: admin/tabs/gsc.php
if (!defined('ABSPATH')) exit;

if ( ! current_user_can('manage_options') ) {
  wp_die('You do not have permission to access this page.');
}

wp_enqueue_style(
  'bootstrap-icons',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
  [],
  '1.11.3'
);

$subtab = isset($_GET['subtab']) ? sanitize_key($_GET['subtab']) : 'page-check';
?>
<style>
/* Make the ENTIRE GSC area full-bleed across WP admin content */
#wpbody-content .ssseo-gsc-full {
  margin-left: -20px;
  margin-right: -20px;
}
@media (max-width: 782px) {
  #wpbody-content .ssseo-gsc-full {
    margin-left: -12px;
    margin-right: -12px;
  }
}

/* Add comfy padding back inside */
.ssseo-gsc-full .ssseo-gsc-pad {
  padding: 16px 20px;
}

/* Tabs span the full width; keep readable padding */
.ssseo-gsc-full .nav-tabs {
  padding: 0 20px;
  margin-bottom: 0;
  border-bottom: 1px solid #c3c4c7;
}

/* Cards go edge-to-edge inside the full-bleed container */
.ssseo-gsc-full .card {
  border-radius: 0;
  width: 100%;
  max-width: none;
}

/* Neutralize any per-subtab "fullbleed" hacks to avoid double negatives */
.ssseo-gsc-full .ssseo-fullbleed {
  margin-left: 0 !important;
  margin-right: 0 !important;
}

/* Optional: make inner tables breathe a bit across the full width */
.ssseo-gsc-full table {
  width: 100%;
}
</style>

<div class="ssseo-gsc-full">
  <div class="ssseo-gsc-pad">
    <h2 class="mb-3">Google Search Console</h2>
  </div>

  <ul class="nav nav-tabs border-bottom border-primary" role="tablist">
    <li class="nav-item" role="presentation">
      <a class="nav-link <?php echo $subtab === 'page-check' ? 'active fw-semibold text-primary border-primary border-bottom-0' : ''; ?> rounded-top"
         href="<?php echo esc_url( add_query_arg(['page'=>'ssseo-tools','tab'=>'gsc','subtab'=>'page-check'], admin_url('admin.php')) ); ?>">
        <i class="bi bi-search me-1"></i> Page Check
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?php echo $subtab === 'opportunities' ? 'active fw-semibold text-primary border-primary border-bottom-0' : ''; ?> rounded-top"
         href="<?php echo esc_url( add_query_arg(['page'=>'ssseo-tools','tab'=>'gsc','subtab'=>'opportunities'], admin_url('admin.php')) ); ?>">
        <i class="bi bi-lightbulb me-1"></i> Opportunities
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?php echo $subtab === 'indexing' ? 'active fw-semibold text-primary border-primary border-bottom-0' : ''; ?> rounded-top"
         href="<?php echo esc_url( add_query_arg(['page'=>'ssseo-tools','tab'=>'gsc','subtab'=>'indexing'], admin_url('admin.php')) ); ?>">
        <i class="bi bi-bug me-1"></i> Indexing Issues
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?php echo $subtab === 'rich-results' ? 'active fw-semibold text-primary border-primary border-bottom-0' : ''; ?> rounded-top"
         href="<?php echo esc_url( add_query_arg(['page'=>'ssseo-tools','tab'=>'gsc','subtab'=>'rich-results'], admin_url('admin.php')) ); ?>">
        <i class="bi bi-stars me-1"></i> Rich Results
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?php echo $subtab === 'discover' ? 'active fw-semibold text-primary border-primary border-bottom-0' : ''; ?> rounded-top"
         href="<?php echo esc_url( add_query_arg(['page'=>'ssseo-tools','tab'=>'gsc','subtab'=>'discover'], admin_url('admin.php')) ); ?>">
        <i class="bi bi-compass me-1"></i> Discover
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?php echo $subtab === 'connect' ? 'active fw-semibold text-primary border-primary border-bottom-0' : ''; ?> rounded-top"
         href="<?php echo esc_url( add_query_arg(['page'=>'ssseo-tools','tab'=>'gsc','subtab'=>'connect'], admin_url('admin.php')) ); ?>">
        <i class="bi bi-plug me-1"></i> Connect
      </a>
    </li>
  </ul>

  <div class="tab-content ssseo-gsc-pad">
    <?php
      $base = __DIR__ . '/gsc/';
      switch ($subtab) {
        case 'opportunities': $f = $base.'opportunities.php'; break;
        case 'indexing':      $f = $base.'indexing.php'; break;
        case 'rich-results':  $f = $base.'rich-results.php'; break;
        case 'discover':      $f = $base.'discover.php'; break;
        case 'connect':       $f = $base.'connect.php'; break;
        case 'page-check':
        default:              $f = $base.'page-check.php'; break;
      }
      if (file_exists($f)) { include $f; } else {
        echo '<div class="alert alert-warning">Missing file: '.esc_html(basename($f)).'</div>';
      }
    ?>
  </div>
</div>
<script>
/* Global page context shared across subtabs */
window.SSSEO_GSC = (function(){
  const KEY = 'ssseo_gsc_current_page';
  function get(){ try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch(e){ return null; } }
  function set(obj){
    if (!obj || typeof obj !== 'object') return;
    // normalize
    const out = {
      post_id: obj.post_id ? parseInt(obj.post_id, 10) : null,
      post_type: obj.post_type || '',
      url: obj.url || '',
      title: obj.title || ''
    };
    localStorage.setItem(KEY, JSON.stringify(out));
    // notify any open subtabs
    window.dispatchEvent(new CustomEvent('ssseo:gsc:context', { detail: out }));
  }
  function clear(){ localStorage.removeItem(KEY); window.dispatchEvent(new CustomEvent('ssseo:gsc:context', { detail: null })); }
  return { getCurrentPage: get, setCurrentPage: set, clearCurrentPage: clear };
})();
</script>
