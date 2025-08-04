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
?>

<div class="container mt-4">
  <h2>Bulk Operations</h2>
  <ul class="nav nav-tabs" id="ssseoBulkTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="yoast-tab" data-bs-toggle="tab" data-bs-target="#yoast" type="button" role="tab">Yoast Operations</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="services-tab" data-bs-toggle="tab" data-bs-target="#services" type="button" role="tab">Services Posts</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="youtube-tab" data-bs-toggle="tab" data-bs-target="#youtube" type="button" role="tab">YouTube Fix</button>
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
              <option value="<?= esc_attr($pt->name) ?>"><?= esc_html($pt->labels->singular_name) ?></option>
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

    <!-- Services Tab -->
    <div class="tab-pane fade" id="services" role="tabpanel" aria-labelledby="services-tab">
      <div class="row">
        <div class="col-md-6">
          <label class="form-label fw-bold">Top-Level Services</label>
          <select id="ssseo_service_root_posts" class="form-select" multiple size="10">
            <?php foreach ($top_services as $service): ?>
              <option value="<?= $service->ID ?>"><?= esc_html($service->post_title) ?> (#<?= $service->ID ?>)</option>
            <?php endforeach; ?>
          </select>
          <p class="text-muted small mt-2">Hold Ctrl (Windows) or Cmd (Mac) to select multiple services.</p>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">Assign to Service Area</label>
          <select id="ssseo_target_service_area" class="form-select">
            <option value="">Select a Service Area</option>
            <?php foreach ($top_service_areas as $area): ?>
              <option value="<?= $area->ID ?>"><?= esc_html($area->post_title) ?> (#<?= $area->ID ?>)</option>
            <?php endforeach; ?>
          </select>

          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" id="ssseo_generate_ai" value="1">
            <label class="form-check-label" for="ssseo_generate_ai">Generate AI "About the Area" Content?</label>
          </div>

          <button id="ssseo_clone_services_btn" class="btn btn-success mt-3 w-100">Clone Services as Service Areas</button>

          <div id="ssseo_clone_result" class="alert alert-info mt-3 d-none"></div>
        </div>
      </div>
    </div>

    <!-- YouTube Tab -->
    <div class="tab-pane fade" id="youtube" role="tabpanel" aria-labelledby="youtube-tab">
      <p class="text-muted">This operation will scan all posts for YouTube iframes and wrap them responsively.</p>
      <button id="ssseo_fix_youtube_iframes" class="btn btn-danger">Fix YouTube Embeds</button>
      <div id="ssseo_youtube_result" class="alert alert-info mt-3 d-none"></div>
      <ul id="ssseo_youtube_log" class="mt-3 list-group d-none"></ul>
    </div>
  </div>
<script>
const ssseoPostsByType = <?= wp_json_encode($posts_by_type); ?>;
const ssseoDefaultType = "<?= esc_js($default_pt); ?>";
</script>

</div>

