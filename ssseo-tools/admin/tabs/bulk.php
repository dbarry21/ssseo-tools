<?php
// File: admin/tabs/bulk.php
if (!defined('ABSPATH')) exit;

// Load public post types and prefetch posts grouped by type (title ASC)
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

// Get root-level services and service areas (for Clone tab)
$top_services = get_posts([
  'post_type'        => 'service',
  'post_status'      => ['publish','draft','pending','future','private'],
  'post_parent'      => 0,
  'orderby'          => 'title',
  'order'            => 'ASC',
  'posts_per_page'   => -1,
  'suppress_filters' => true,   // guard against WPML/Polylang/admin filters
  'fields'           => 'ids',  // faster
  'no_found_rows'    => true,
]);

$top_service_areas = get_posts([
  'post_type'        => 'service_area',
  'post_status'      => ['publish','draft','pending','future','private'],
  'post_parent'      => 0,
  'orderby'          => 'title',
  'order'            => 'ASC',
  'posts_per_page'   => -1,
  'suppress_filters' => true,
  'fields'           => 'ids',
  'no_found_rows'    => true,
]);

// Build a full list of service_area posts for the "source" selector
$all_service_areas = get_posts([
  'post_type'        => 'service_area',
  'post_status'      => ['publish','draft','pending','future','private'],
  'orderby'          => 'title',
  'order'            => 'ASC',
  'posts_per_page'   => -1,
  'suppress_filters' => true,
  'fields'           => 'ids',
  'no_found_rows'    => true,
]);


// Prepare a nonce for all Bulk operations (incl. canonical)
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
      <?php
        // Load the canonical subtab markup. Adjust this include path if you relocate the file.
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
      <p class="text-muted">
        Select one <strong>source</strong> Service Area and one or more <strong>parent</strong> Service Areas (parent = 0).
        For each selected parent, a child clone of the source will be created, its title will replace
        <code>[acf field="city_state"]</code>, and its ACF <code>city_state</code> will be set from the parent.
      </p>

      <?php wp_nonce_field('ssseo_bulk_clone_sa', 'ssseo_bulk_clone_sa_nonce'); ?>

      <div class="row g-4">
        <!-- Source (single) -->
        <div class="col-md-6">
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="card-title">1) Choose Source Service Area (single)</h5>
              <input type="text" id="ssseo-clone-sa-source-filter" class="form-control mb-2" placeholder="Filter source by title…">
              <select id="ssseo-clone-sa-source" class="form-select" size="12" aria-label="Source service area">
                <?php foreach ($all_service_areas as $pid): ?>
  <option value="<?php echo esc_attr($pid); ?>">
    <?php echo esc_html(get_the_title($pid) . ' (ID ' . $pid . ')'); ?>
  </option>
<?php endforeach; ?>

              </select>
              <div class="form-text mt-2">Copies content, meta (incl. Elementor), taxonomies, and featured image.</div>
            </div>
          </div>
        </div>

        <!-- Targets (multiple) -->
        <div class="col-md-6">
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="card-title">2) Choose Target Parents (multiple)</h5>

              <label for="ssseo-clone-sa-slug" class="form-label">Slug for new clones (optional)</label>
              <input type="text" id="ssseo-clone-sa-slug" class="form-control mb-2" placeholder="e.g. roofing-in-tampa (leave empty to auto-generate)">
              <div class="form-text mb-2">If set, each clone will use this slug (sanitized). WP will suffix if needed.</div>

              <!-- NEW: Yoast Focus Keyphrase base -->
              <label for="ssseo-clone-sa-focus-base" class="form-label">Yoast Focus Keyphrase (base)</label>
              <input type="text" id="ssseo-clone-sa-focus-base" class="form-control mb-2" placeholder="e.g. Pool screen cleaning">
              <div class="form-text mb-2">We’ll append the parent’s <code>city_state</code> (e.g., “Pool screen cleaning Bradenton, FL”).</div>

              <select id="ssseo-clone-sa-targets" class="form-select" multiple size="12" aria-label="Target parent service areas">
                <?php foreach ($top_service_areas as $pid): ?>
  <option value="<?php echo esc_attr($pid); ?>">
    <?php echo esc_html(get_the_title($pid) . ' (ID ' . $pid . ')'); ?>
  </option>
<?php endforeach; ?>

              </select>
              <div class="form-text mt-2">Only top‑level Service Areas (parent = 0) are listed.</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Options -->
      <div class="form-check mt-3">
        <input class="form-check-input" type="checkbox" id="ssseo-clone-sa-draft" checked>
        <label class="form-check-label" for="ssseo-clone-sa-draft">Create clones as <strong>drafts</strong></label>
      </div>
      <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" id="ssseo-clone-sa-skip-existing" checked>
        <label class="form-check-label" for="ssseo-clone-sa-skip-existing">Skip if a child with the <em>same title</em> already exists under that parent</label>
      </div>
      <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" id="ssseo-clone-sa-debug">
        <label class="form-check-label" for="ssseo-clone-sa-debug">Show debug details</label>
      </div>

      <!-- Action -->
      <div class="mt-3">
        <button type="button" class="button button-primary" id="ssseo-clone-sa-run">Clone Now</button>
        <span class="spinner" id="ssseo-clone-sa-spinner" style="float:none; margin-left:8px; display:none;"></span>
      </div>

      <!-- Results -->
      <div class="mt-4">
        <h5 class="mb-2">Results</h5>
        <div id="ssseo-clone-sa-results" class="card card-body" style="min-height:80px; overflow:auto;"></div>
      </div>
    </div>
  </div>
</div>

<!-- Expose data & nonce for JS (incl. canonical subtab) -->
<script>
window.ssseoPostsByType = <?php echo wp_json_encode( $posts_by_type, JSON_UNESCAPED_UNICODE ); ?>;
window.SSSEO = Object.assign(window.SSSEO || {}, {
  nonce: '<?php echo esc_js( $bulk_nonce ); ?>'
});
</script>
<script>
jQuery(function($){
  // --- 1) Yoast Ops list population from ssseoPostsByType ---
  (function initYoastOps(){
    if (typeof window.ssseoPostsByType === 'undefined') return;

    const $pt    = $('#ssseo_bulk_pt_filter');
    const $list  = $('#ssseo_bulk_post_id');
    const $search= $('#ssseo_bulk_post_search');

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

    // initial
    renderForType($pt.val(), $search.val());
    // changes
    $pt.on('change', function(){ renderForType($(this).val(), $search.val()); });
    $search.on('input', function(){ renderForType($pt.val(), $(this).val()); });
  })();

  // --- 2) Clone Service Areas: backfill selections via AJAX if empty ---
  (function initCloneTab(){
    const $src = $('#ssseo-clone-sa-source');
    const $tgt = $('#ssseo-clone-sa-targets');

    // If server-side lists are empty (affected by filters), fetch via AJAX
    const needSrc = $src.length && $src.find('option').length === 0;
    const needTgt = $tgt.length && $tgt.find('option').length === 0;

    function opt(id, title){ 
      return $('<option>').val(id).text((title || '(no title)') + ' (ID ' + id + ')'); 
    }

    function fillSource(items){
      $src.empty();
      items.forEach(it => { $src.append(opt(it.id, it.title)); });
    }
    function fillTargets(items){
      $tgt.empty();
      items.forEach(it => { $tgt.append(opt(it.id, it.title)); });
    }

    const nonce = (window.SSSEO && SSSEO.nonce) ? SSSEO.nonce : '';

    if (needSrc) {
      $.post(ajaxurl, { action: 'ssseo_sa_all', nonce: nonce }).done(function(res){
        if (res && res.success && res.data && Array.isArray(res.data.items)) {
          fillSource(res.data.items);
        }
      });
    }
    if (needTgt) {
      $.post(ajaxurl, { action: 'ssseo_sa_top_parents', nonce: nonce }).done(function(res){
        if (res && res.success && res.data && Array.isArray(res.data.items)) {
          fillTargets(res.data.items);
        }
      });
    }

    // local filter UX (works for both server or ajax-filled lists)
    function filterSelect($input, $select) {
      var needle = ($input.val() || '').toLowerCase();
      $select.find('option').each(function(){
        var txt = $(this).text().toLowerCase();
        $(this).toggle(txt.indexOf(needle) !== -1);
      });
    }
    $('#ssseo-clone-sa-source-filter').on('input', function(){
      filterSelect($(this), $('#ssseo-clone-sa-source'));
    });
    $('#ssseo-clone-sa-target-filter').on('input', function(){
      filterSelect($(this), $('#ssseo-clone-sa-targets'));
    });
  })();
});
</script>
