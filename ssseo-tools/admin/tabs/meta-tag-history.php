<?php
// File: admin/tabs/meta-history.php
if ( ! defined( 'ABSPATH' ) ) exit;

$post_types = get_post_types(['public' => true], 'objects');
$default_pt = array_key_first($post_types);
?>

<h2><?php esc_html_e('Meta Tag Change History', 'ssseo'); ?></h2>
<p><?php esc_html_e('Select a post type and a post to view the history of Yoast SEO meta title and description changes.', 'ssseo'); ?></p>

<table class="form-table">
  <tr>
    <th><label for="ssseo_history_pt">Post Type</label></th>
    <td>
      <select id="ssseo_history_pt" style="width:100%; max-width:300px;">
        <?php foreach ( $post_types as $pt ) : ?>
          <option value="<?php echo esc_attr($pt->name); ?>" <?php selected($pt->name, $default_pt); ?>>
            <?php echo esc_html($pt->labels->singular_name); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </td>
  </tr>
  <tr>
    <th><label for="ssseo_history_search">Search Posts</label></th>
    <td>
      <input type="text" id="ssseo_history_search" placeholder="Type to filter…" style="width:100%; max-width:400px; margin-bottom:8px;">
    </td>
  </tr>
  <tr>
    <th><label for="ssseo_history_post">Post</label></th>
    <td>
      <select id="ssseo_history_post" style="width:100%; max-width:400px;"><option disabled>Loading...</option></select>
    </td>
  </tr>
</table>

<div style="margin-bottom: 10px;">
  <button id="ssseo_export_meta_history" class="button button-secondary" style="display:none;">
    Export History to CSV
  </button>
</div>

<div id="ssseo_history_output" style="margin-top:10px; background:#f9f9f9; padding:12px; border:1px solid #ccc;"></div>

<script>
jQuery(document).ready(function($) {
  function loadPosts(pt, search = '') {
    $('#ssseo_history_post').html('<option disabled>Loading...</option>');
    $.post(ssseo_admin.ajaxurl, {
      action: 'ssseo_get_posts_by_type',
      post_type: pt,
      search: search
    }, function(response) {
      $('#ssseo_history_post').html(response);
    });
  }

  $('#ssseo_history_pt').on('change', function() {
    loadPosts(this.value, $('#ssseo_history_search').val());
    $('#ssseo_history_output').html('');
    $('#ssseo_export_meta_history').hide();
  });

  $('#ssseo_history_search').on('input', function() {
    const pt = $('#ssseo_history_pt').val();
    loadPosts(pt, this.value);
  });

  $('#ssseo_history_post').on('change', function() {
    const post_id = $(this).val();
    $('#ssseo_history_output').html('<em>Loading history...</em>');
    $('#ssseo_export_meta_history').hide();
    $.post(ssseo_admin.ajaxurl, {
      action: 'ssseo_get_meta_history',
      post_id: post_id,
      _wpnonce: ssseo_admin.nonce
    }, function(response) {
      if (response.success) {
        $('#ssseo_history_output').html(response.data.html || '<p>No history found.</p>');
        if (response.data.csv) {
          $('#ssseo_export_meta_history')
            .show()
            .data('csv', response.data.csv)
            .data('filename', response.data.filename);
        }
      } else {
        $('#ssseo_history_output').html('<p>Error loading history.</p>');
      }
    });
  });

  $('#ssseo_export_meta_history').on('click', function() {
    const csv = $(this).data('csv');
    const filename = $(this).data('filename') || 'meta-history.csv';
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  });

  loadPosts($('#ssseo_history_pt').val());
});
</script>
