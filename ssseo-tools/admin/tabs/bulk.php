<?php
// File: admin/tabs/bulk.php
if (!defined('ABSPATH')) exit;

// Load post types and group posts
$post_types = get_post_types(['public' => true], 'objects');

$posts_by_type = [];
foreach ($post_types as $pt) {
  $all_posts = get_posts([
    'post_type'      => $pt->name,
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'title',
    'order'          => 'ASC',
  ]);
  foreach ($all_posts as $p) {
    $posts_by_type[$pt->name][] = [
      'id'    => $p->ID,
      'title' => $p->post_title,
    ];
  }
}
$default_pt = array_key_first($posts_by_type);

// Get root-level services and service areas
$top_services = get_posts([
  'post_type'      => 'service',
  'post_status'    => 'publish',
  'post_parent'    => 0,
  'orderby'        => 'title',
  'order'          => 'ASC',
  'posts_per_page' => -1,
]);

$top_service_areas = get_posts([
  'post_type'      => 'service_area',
  'post_status'    => 'publish',
  'post_parent'    => 0,
  'orderby'        => 'title',
  'order'          => 'ASC',
  'posts_per_page' => -1,
]);

// Build a full list of service_area posts for the "source" selector
$all_service_areas = get_posts([
  'post_type'      => 'service_area',
  'post_status'    => 'publish',
  'orderby'        => 'title',
  'order'          => 'ASC',
  'posts_per_page' => -1,
]);
?>
<div class="container mt-4">
  <h2>Bulk Operations</h2>

  <ul class="nav nav-tabs" id="ssseoBulkTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="yoast-tab" data-bs-toggle="tab" data-bs-target="#yoast" type="button" role="tab">Yoast Operations</button>
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
                <?php foreach ($all_service_areas as $p): ?>
                  <option value="<?php echo esc_attr($p->ID); ?>">
                    <?php echo esc_html($p->post_title . ' (ID ' . $p->ID . ')'); ?>
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
                <?php foreach ($top_service_areas as $p): ?>
                  <option value="<?php echo esc_attr($p->ID); ?>">
                    <?php echo esc_html($p->post_title . ' (ID ' . $p->ID . ')'); ?>
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
