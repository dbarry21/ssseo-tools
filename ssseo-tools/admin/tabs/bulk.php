<?php
// File: admin/tabs/bulk.php
if (!defined('ABSPATH')) exit;

$post_types = get_post_types([], 'objects');
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
?>

<div class="container mt-4">
  <h2>Bulk Operations &mdash; Yoast</h2>
  <p class="text-muted">Use the controls below to apply bulk SEO functions to selected posts.</p>

  <div class="row mb-4">
    <div class="col-md-4">
      <label for="ssseo_bulk_pt_filter" class="form-label">Post Type</label>
      <select id="ssseo_bulk_pt_filter" class="form-select">
        <?php foreach ($post_types as $pt) : ?>
          <option value="<?php echo esc_attr($pt->name); ?>" <?php selected($pt->name, $default_pt); ?>>
            <?php echo esc_html($pt->labels->singular_name); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label for="ssseo_bulk_post_search" class="form-label">Search Posts</label>
      <input type="text" id="ssseo_bulk_post_search" class="form-control" placeholder="Type to filter…">
    </div>

    <div class="col-md-4">
      <label for="ssseo_bulk_post_id" class="form-label">Choose Post(s)</label>
      <select id="ssseo_bulk_post_id" name="ssseo_bulk_post_id[]" multiple size="8" class="form-select">
        <!-- Injected by JS -->
      </select>
      <div class="form-text">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</div>
    </div>
  </div>

  <h4>Yoast Meta Robots Functions</h4>
  <div class="mb-4">
    <button id="ssseo_bulk_indexfollow" class="btn btn-primary me-2">Set to Index, Follow (Selected)</button>
    <button id="ssseo_bulk_reset_canonical" class="btn btn-secondary me-2">Reset Canonical to Page URL (Selected)</button>
    <button id="ssseo_bulk_clear_canonical" class="btn btn-outline-secondary">Clear Canonical (Selected)</button>
  </div>

  <div id="ssseo_bulk_result" class="border p-3 bg-light" style="display:none; max-width:700px;"></div>
</div>

<script>
var ssseoPostsByType = <?php echo wp_json_encode($posts_by_type); ?>;
var ssseoDefaultType = "<?php echo esc_js($default_pt); ?>";

jQuery(document).ready(function($) {
  const $select = $('#ssseo_bulk_post_id');
  const $results = $('#ssseo_bulk_result');

  function updateSelectOptions(pt, search = '') {
    const posts = ssseoPostsByType[pt] || [];
    const searchLower = search.toLowerCase();
    const matches = posts.filter(p => p.title.toLowerCase().includes(searchLower));

    $select.empty();
    if (matches.length) {
      matches.forEach(p => {
        $select.append(`<option value="${p.id}">${p.title} (#${p.id})</option>`);
      });
    } else {
      $select.append('<option disabled>No matching posts found</option>');
    }
  }

  function handleBulkAction(action, successMessage) {
    const selected = $select.val();
    if (!selected || !selected.length) {
      alert('Please select at least one post.');
      return;
    }

    $.post(ssseo_admin.ajaxurl, {
      action: action,
      post_ids: selected,
      _wpnonce: ssseo_admin.nonce
    }, function(response) {
      if (response.success) {
        let html = `<strong>${selected.length} ${successMessage}:</strong><ul class='mt-2 ps-3'>`;
        selected.forEach(id => {
          const label = $select.find(`option[value='${id}']`).text();
          html += `<li>${label}</li>`;
        });
        html += '</ul>';
        $results.html(html).show();
      } else {
        alert(response.data || 'An error occurred');
        $results.hide();
      }
    });
  }

  $('#ssseo_bulk_pt_filter').on('change', function() {
    updateSelectOptions(this.value, $('#ssseo_bulk_post_search').val());
  });

  $('#ssseo_bulk_post_search').on('input', function() {
    const pt = $('#ssseo_bulk_pt_filter').val();
    updateSelectOptions(pt, this.value);
  });

  $('#ssseo_bulk_indexfollow').on('click', function() {
    handleBulkAction('ssseo_yoast_set_index_follow', 'post(s) updated');
  });

  $('#ssseo_bulk_reset_canonical').on('click', function() {
    handleBulkAction('ssseo_yoast_reset_canonical', 'canonical URL(s) reset');
  });

  $('#ssseo_bulk_clear_canonical').on('click', function() {
    handleBulkAction('ssseo_yoast_clear_canonical', 'canonical URL(s) cleared');
  });

  updateSelectOptions(ssseoDefaultType);
});
</script>
