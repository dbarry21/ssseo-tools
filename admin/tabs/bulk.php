<?php
// File: admin/tabs/bulk.php
if ( ! defined( 'ABSPATH' ) ) exit;

$post_types = get_post_types( [], 'objects' );

$posts_by_type = [];
foreach ( $post_types as $pt ) {
  $all_posts = get_posts( [
    'post_type'      => $pt->name,
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'title',
    'order'          => 'ASC',
  ] );
  $arr = [];
  foreach ( $all_posts as $p ) {
    $arr[] = [
      'id'    => $p->ID,
      'title' => $p->post_title,
    ];
  }
  $posts_by_type[ $pt->name ] = $arr;
}

$default_pt = array_key_first( $posts_by_type );
?>

<h2><?php esc_html_e( 'Bulk Operations – Yoast', 'ssseo' ); ?></h2>
<p>
  <?php esc_html_e( 'Use the controls below to apply bulk SEO functions to selected posts.', 'ssseo' ); ?>
</p>

<table class="form-table">
  <tr>
    <th><label for="ssseo_bulk_pt_filter">Post Type</label></th>
    <td>
      <select id="ssseo_bulk_pt_filter" style="width:100%; max-width:300px;">
        <?php foreach ( $post_types as $pt ) : ?>
          <option
            value="<?php echo esc_attr( $pt->name ); ?>"
            <?php selected( $pt->name, $default_pt ); ?>
          >
            <?php echo esc_html( $pt->labels->singular_name ); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </td>
  </tr>

  <tr>
    <th><label for="ssseo_bulk_post_search">Search Posts</label></th>
    <td>
      <input
        type="text"
        id="ssseo_bulk_post_search"
        placeholder="Type to filter…"
        style="width:100%; max-width:400px; margin-bottom:8px;"
      >
    </td>
  </tr>

  <tr>
    <th><label for="ssseo_bulk_post_id">Choose Post(s)</label></th>
    <td>
      <select
        id="ssseo_bulk_post_id"
        name="ssseo_bulk_post_id[]"
        multiple
        size="8"
        style="width:100%; max-width:400px;"
      >
        <!-- JS will inject options -->
      </select>
      <p class="description">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</p>
    </td>
  </tr>
</table>

<h3>Yoast Meta Robots Functions</h3>
<div class="ssseo-bulk-functions" style="margin-bottom:20px;">
  <button id="ssseo_bulk_indexfollow" class="button button-primary">
    Set to Index, Follow (Selected)
  </button>
  <button id="ssseo_bulk_reset_canonical" class="button button-secondary">
    Reset Canonical to Page URL (Selected)
  </button>
  <button id="ssseo_bulk_clear_canonical" class="button">
    Clear Canonical (Selected)
  </button>
</div>

<div id="ssseo_bulk_result" style="display:none; background:#f9f9f9; padding:12px; border:1px solid #ccc; max-width:500px;"></div>

<script>
var ssseoPostsByType = <?php echo wp_json_encode( $posts_by_type ); ?>;
var ssseoDefaultType = "<?php echo esc_js( $default_pt ); ?>";

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
        let html = `<strong>${selected.length} ${successMessage}:</strong><ul style='margin:8px 0;'>`;
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

  // Initial render
  updateSelectOptions(ssseoDefaultType);
});
</script>
