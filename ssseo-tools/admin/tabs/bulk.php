<?php
// File: admin/tabs/bulk.php
if (!defined('ABSPATH')) exit;

/**
 * -------------------------------------------------
 * AJAX: Sources (flat, publish) & Targets (tree, publish)
 * -------------------------------------------------
 * Lives here so subtab includes stay modular.
 */
if ( ! function_exists('ssseo_ajax_sa_all_published') ) {
  add_action('wp_ajax_ssseo_sa_all_published', 'ssseo_ajax_sa_all_published');
  function ssseo_ajax_sa_all_published() {
    check_ajax_referer('ssseo_bulk_ops', 'nonce');

    $ids = get_posts([
      'post_type'        => 'service_area',
      'posts_per_page'   => -1,
      'post_status'      => 'publish',
      'orderby'          => 'title',
      'order'            => 'ASC',
      'suppress_filters' => true,
      'fields'           => 'ids',
      'no_found_rows'    => true,
    ]);

    $items = [];
    foreach ($ids as $id) {
      $items[] = ['id' => (int)$id, 'title' => get_the_title($id)];
    }
    wp_send_json_success(['items' => $items]);
  }
}

if ( ! function_exists('ssseo_ajax_sa_tree_published') ) {
  add_action('wp_ajax_ssseo_sa_tree_published', 'ssseo_ajax_sa_tree_published');
  function ssseo_ajax_sa_tree_published() {
    check_ajax_referer('ssseo_bulk_ops', 'nonce');

    $rows = get_posts([
      'post_type'        => 'service_area',
      'posts_per_page'   => -1,
      'post_status'      => 'publish',
      'orderby'          => 'menu_order title',
      'order'            => 'ASC',
      'suppress_filters' => true,
      'fields'           => 'all',
      'no_found_rows'    => true,
    ]);

    $by_parent = [];
    foreach ($rows as $p) {
      $pp = (int)$p->post_parent;
      if (!isset($by_parent[$pp])) $by_parent[$pp] = [];
      $by_parent[$pp][] = $p;
    }

    $items = [];
    $walk = function($parent_id, $depth) use (&$walk, &$by_parent, &$items) {
      if (empty($by_parent[$parent_id])) return;
      foreach ($by_parent[$parent_id] as $node) {
        $items[] = [
          'id'    => (int)$node->ID,
          'title' => get_the_title($node) ?: '(no title)',
          'depth' => (int)$depth,
        ];
        $walk((int)$node->ID, $depth + 1);
      }
    };
    $walk(0, 0);

    wp_send_json_success(['items' => $items]);
  }
}

/**
 * -------------------------------------------------
 * Prefetch (Yoast tab) – unchanged
 * -------------------------------------------------
 */
$post_types = get_post_types(['public' => true], 'objects');

$posts_by_type = [];
foreach ($post_types as $pt) {
  $all_posts = get_posts([
    'post_type'      => $pt->name,
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'title',
    'order'          => 'ASC',
    'fields'         => 'ids',
  ]);
  $posts_by_type[$pt->name] = [];
  foreach ($all_posts as $pid) {
    $posts_by_type[$pt->name][] = [
      'id'    => $pid,
      'title' => get_the_title($pid),
    ];
  }
}
$default_pt = array_key_first($posts_by_type);

/**
 * -------------------------------------------------
 * Server-render for Clone tab initial lists
 * -------------------------------------------------
 */
$all_service_areas = get_posts([
  'post_type'        => 'service_area',
  'post_status'      => 'publish',
  'orderby'          => 'title',
  'order'            => 'ASC',
  'posts_per_page'   => -1,
  'suppress_filters' => true,
  'fields'           => 'ids',
  'no_found_rows'    => true,
]);

$tree_posts = get_posts([
  'post_type'        => 'service_area',
  'post_status'      => 'publish',
  'orderby'          => 'menu_order title',
  'order'            => 'ASC',
  'posts_per_page'   => -1,
  'suppress_filters' => true,
  'fields'           => 'all',
  'no_found_rows'    => true,
]);
$by_parent = [];
foreach ($tree_posts as $p) {
  $pp = (int)$p->post_parent;
  if (!isset($by_parent[$pp])) $by_parent[$pp] = [];
  $by_parent[$pp][] = $p;
}
$target_tree_items = [];
$walk = function($parent_id, $depth) use (&$walk, &$by_parent, &$target_tree_items) {
  if (empty($by_parent[$parent_id])) return;
  foreach ($by_parent[$parent_id] as $node) {
    $target_tree_items[] = [
      'id'    => (int)$node->ID,
      'title' => get_the_title($node) ?: '(no title)',
      'depth' => (int)$depth,
    ];
    $walk((int)$node->ID, $depth + 1);
  }
};
$walk(0, 0);

// Single canonical nonce for ALL bulk ops
$bulk_nonce = wp_create_nonce('ssseo_bulk_ops');
?>
<div class="container mt-4">
  <h2>Bulk Operations</h2>

  <ul class="nav nav-tabs" id="ssseoBulkTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="yoast-tab" data-bs-toggle="tab" data-bs-target="#yoast" type="button" role="tab">Yoast Operations</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="canonical-tab" data-bs-toggle="tab" data-bs-target="#canonical" type="button" role="tab">Canonical Functions</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="youtube-tab" data-bs-toggle="tab" data-bs-target="#youtube" type="button" role="tab">YouTube Fix</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="clone-sa-tab" data-bs-toggle="tab" data-bs-target="#clone-sa" type="button" role="tab">Clone Service Areas to Parents</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="googlemaps-tab" data-bs-toggle="tab" data-bs-target="#googlemaps" type="button" role="tab">Google Maps</button>
    </li>
	<li class="nav-item" role="presentation">
	  <button class="nav-link" id="ai-summaries-tab" data-bs-toggle="tab" data-bs-target="#ai-summaries" type="button" role="tab">AI Summaries</button>
	</li>

  </ul>

  <div class="tab-content border border-top-0 p-4">
    <!-- Yoast Tab -->
    <div class="tab-pane fade show active" id="yoast" role="tabpanel" aria-labelledby="yoast-tab">
      <p class="text-muted">Apply Yoast SEO functions to selected posts.</p>
      <div class="row mb-4">
        <div class="col-md-4">
          <label for="ssseo_bulk_pt_filter" class="form-label">Post Type</label>
          <select id="ssseo_bulk_pt_filter" class="form-select">
            <?php foreach ($post_types as $pt): ?>
              <option value="<?php echo esc_attr($pt->name); ?>"><?php echo esc_html($pt->labels->singular_name); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label for="ssseo_bulk_post_search" class="form-label">Search Posts</label>
          <input type="text" id="ssseo_bulk_post_search" class="form-control" placeholder="Type to filter…">
        </div>
        <div class="col-md-4">
          <label for="ssseo_bulk_post_id" class="form-label">Choose Post(s)</label>
          <select id="ssseo_bulk_post_id" name="ssseo_bulk_post_id[]" multiple size="8" class="form-select"></select>
          <div class="form-text">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</div>
        </div>
      </div>

      <h4>Yoast Meta Robots</h4>
      <div class="mb-4">
        <button id="ssseo_bulk_indexfollow" class="btn btn-primary me-2">Set to Index, Follow</button>
        <button id="ssseo_bulk_reset_canonical" class="btn btn-secondary me-2">Reset Canonical</button>
        <button id="ssseo_bulk_clear_canonical" class="btn btn-outline-secondary">Clear Canonical</button>
      </div>

      <div id="ssseo_bulk_result" class="border p-3 bg-light" style="display:none; max-width:700px;"></div>
    </div>

    <!-- Canonical Tab -->
    <div class="tab-pane fade" id="canonical" role="tabpanel" aria-labelledby="canonical-tab">
      <div class="mb-3">
        <?php
          // Primary nonce (and back-compat)
          wp_nonce_field('ssseo_bulk_ops', 'ssseo_bulk_ops_nonce');
          wp_nonce_field('ssseo_canonical_ops', 'ssseo_canonical_ops_nonce');
          echo '<input type="hidden" id="ssseo_bulk_nonce" value="'. esc_attr( $bulk_nonce ) .'">';
          echo '<input type="hidden" id="ssseo_canonical_nonce" value="'. esc_attr( wp_create_nonce('ssseo_canonical_ops') ) .'">';
        ?>
      </div>
      <?php
        $canonical_file = __DIR__ . '/bulk/canonical.php';
        if ( file_exists( $canonical_file ) ) {
          include $canonical_file;
        } else {
          echo '<div class="alert alert-warning">canonical.php not found. Place it next to bulk.php.</div>';
        }
      ?>
    </div>

    <!-- YouTube Tab -->
    <div class="tab-pane fade" id="youtube" role="tabpanel" aria-labelledby="youtube-tab">
      <p class="text-muted">This operation will scan all posts for YouTube iframes and wrap them responsively.</p>
      <button id="ssseo_fix_youtube_iframes" class="btn btn-danger">Fix YouTube Embeds</button>
      <div id="ssseo_youtube_result" class="alert alert-info mt-3 d-none"></div>
      <ul id="ssseo_youtube_log" class="mt-3 list-group d-none"></ul>
    </div>

    <!-- Clone Service Areas to Parents Tab -->
    <div class="tab-pane fade" id="clone-sa" role="tabpanel" aria-labelledby="clone-sa-tab">
      <?php
        $clone_file = __DIR__ . '/bulk/clone-service-areas.php';
        if ( file_exists( $clone_file ) ) {
          // Provide initial lists + nonce context to subtab scope
          $SSSEO_CLONE_CTX = [
            'all_service_areas' => $all_service_areas,
            'target_tree_items' => $target_tree_items,
            'bulk_nonce'        => $bulk_nonce,
          ];
          include $clone_file;
        } else {
          echo '<div class="alert alert-warning">clone-service-areas.php not found. Place it in admin/tabs/bulk/.</div>';
        }
      ?>
    </div>
	  
	      <!-- Google Maps Tab -->
    <div class="tab-pane fade" id="googlemaps" role="tabpanel" aria-labelledby="googlemaps-tab">
      <?php
        $gmaps_file = __DIR__ . '/bulk/google-maps.php';
        if ( file_exists( $gmaps_file ) ) {
          include $gmaps_file;
        } else {
          echo '<div class="alert alert-warning">google-maps.php not found. Place it in admin/tabs/bulk/.</div>';
        }
      ?>
    </div>
	<!-- AI Summaries Tab -->
	<div class="tab-pane fade" id="ai-summaries" role="tabpanel" aria-labelledby="ai-summaries-tab">
	  <?php
		$ai_file = __DIR__ . '/bulk/ai-summaries.php';
		if ( file_exists( $ai_file ) ) {
		  include $ai_file;
		} else {
		  echo '<div class="alert alert-warning">ai-summaries.php not found. Place it in admin/tabs/bulk/.</div>';
		}
	  ?>
	</div>


  </div>
</div>

<script>
window.ssseoPostsByType = <?php echo wp_json_encode( $posts_by_type, JSON_UNESCAPED_UNICODE ); ?>;
window.SSSEO = Object.assign(window.SSSEO || {}, {
  bulkNonce: '<?php echo esc_js( $bulk_nonce ); ?>',
  nonce: '<?php echo esc_js( $bulk_nonce ); ?>'
});

// >>> Add this block: publish a simple id/title list for service areas
window.SSSEO.gmapsItems = <?php
  $gmaps_items = [];
  foreach ($all_service_areas as $sid) {
    $gmaps_items[] = ['id' => (int)$sid, 'title' => get_the_title($sid) ?: "(no title) #$sid"];
  }
  echo wp_json_encode($gmaps_items, JSON_UNESCAPED_UNICODE);
?>;
</script>


<!-- Expose data & nonce for JS (incl. canonical/clone subtabs) -->
<script>
window.ssseoPostsByType = <?php echo wp_json_encode( $posts_by_type, JSON_UNESCAPED_UNICODE ); ?>;
window.SSSEO = Object.assign(window.SSSEO || {}, {
  bulkNonce: '<?php echo esc_js( $bulk_nonce ); ?>',
  // Back-compat
  nonce: '<?php echo esc_js( $bulk_nonce ); ?>'
});
</script>

<script>
jQuery(function($){
  // ---------- Yoast tab ----------
  (function initYoastOps(){
    if (typeof window.ssseoPostsByType === 'undefined') return;

    const $pt     = $('#ssseo_bulk_pt_filter');
    const $list   = $('#ssseo_bulk_post_id');
    const $search = $('#ssseo_bulk_post_search');

    function renderForType(type, filter){
      const posts = (window.ssseoPostsByType[type] || []);
      const f = (filter || '').toLowerCase();
      $list.empty();
      posts.forEach(p => {
        const t = (p.title || '').toLowerCase();
        if (!f || t.indexOf(f) !== -1) {
          $('<option>').val(p.id).text(p.title || ('(no title) #' + p.id)).appendTo($list);
        }
      });
    }
    renderForType($pt.val(), $search.val());
    $pt.on('change', function(){ renderForType($(this).val(), $search.val()); });
    $search.on('input', function(){ renderForType($pt.val(), $(this).val()); });
  })();
});
</script>
