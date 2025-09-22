<?php
// File: admin/tabs/meta-history.php
if ( ! defined( 'ABSPATH' ) ) exit;

// Collect public post types with UI
$pts = get_post_types( ['public' => true, 'show_ui' => true], 'objects' );
$default_pt = isset($pts['post']) ? 'post' : array_key_first($pts);

// Nonce strictly for this tab's actions
$ajax_nonce = wp_create_nonce('ssseo_meta_history');

// Admin AJAX URL (fallback to window.ajaxurl in JS when present)
$ajax_url = admin_url('admin-ajax.php');
?>
<h2><?php esc_html_e('Meta Tag Change History', 'ssseo'); ?></h2>
<p><?php esc_html_e('Select a post type and a post to view the history of Yoast SEO meta title and description changes.', 'ssseo'); ?></p>

<table class="form-table">
  <tr>
    <th><label for="ssseo_history_pt"><?php esc_html_e('Post Type', 'ssseo'); ?></label></th>
    <td>
      <select id="ssseo_history_pt" style="width:100%; max-width:300px;">
        <?php foreach ( $pts as $pt ) : ?>
          <option value="<?php echo esc_attr($pt->name); ?>" <?php selected($pt->name, $default_pt); ?>>
            <?php echo esc_html($pt->labels->singular_name); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </td>
  </tr>
  <tr>
    <th><label for="ssseo_history_search"><?php esc_html_e('Search Posts', 'ssseo'); ?></label></th>
    <td>
      <input type="text" id="ssseo_history_search" placeholder="<?php echo esc_attr__('Type to filter…', 'ssseo'); ?>" style="width:100%; max-width:400px; margin-bottom:8px;">
    </td>
  </tr>
  <tr>
    <th><label for="ssseo_history_post"><?php esc_html_e('Post', 'ssseo'); ?></label></th>
    <td>
      <select id="ssseo_history_post" style="width:100%; max-width:400px;">
        <option disabled selected><?php esc_html_e('Loading…', 'ssseo'); ?></option>
      </select>
    </td>
  </tr>
</table>

<div style="margin-bottom: 10px;">
  <button id="ssseo_export_meta_history" class="button button-secondary" style="display:none;">
    <?php esc_html_e('Export History to CSV', 'ssseo'); ?>
  </button>
</div>

<div id="ssseo_history_output" style="margin-top:10px; background:#f9f9f9; padding:12px; border:1px solid #ccc;"></div>

<script>
(function($){
  // Prefer WP's injected ajaxurl in admin; fallback to PHP-provided URL
  const ajaxUrl = (typeof window.ajaxurl !== 'undefined' && window.ajaxurl) ? window.ajaxurl : <?php echo wp_json_encode($ajax_url); ?>;
  const nonce   = <?php echo wp_json_encode($ajax_nonce); ?>;

  const $pt     = $('#ssseo_history_pt');
  const $search = $('#ssseo_history_search');
  const $post   = $('#ssseo_history_post');
  const $out    = $('#ssseo_history_output');
  const $export = $('#ssseo_export_meta_history');

  let searchTimer = null;

  function setPostSelectState(text) {
    $post.html('<option disabled selected>' + text + '</option>');
  }

  function populatePosts() {
    const postType = $pt.val();
    const query    = $search.val() || '';

    setPostSelectState('<?php echo esc_js(__('Loading…', 'ssseo')); ?>');

    $.post(ajaxUrl, {
      action: 'ssseo_get_posts_for_meta_history',
      _wpnonce: nonce,
      post_type: postType,
      s: query
    }).done(function(resp){
      if (!resp || resp.success !== true || !Array.isArray(resp.data)) {
        const msg = (resp && resp.data) ? String(resp.data) : '<?php echo esc_js(__('Unknown error', 'ssseo')); ?>';
        setPostSelectState('<?php echo esc_js(__('Error: ', 'ssseo')); ?>' + msg);
        return;
      }

      const list = resp.data;
      if (list.length === 0) {
        setPostSelectState('<?php echo esc_js(__('No results', 'ssseo')); ?>');
        return;
      }

      let html = '<option value="" disabled selected><?php echo esc_js(__('Select a post…', 'ssseo')); ?></option>';
      list.forEach(function(item){
        // Escape via jQuery then take HTML
        html += '<option value="'+ item.id +'">'+ $('<div>').text(item.title).html() +'</option>';
      });
      $post.html(html);
    }).fail(function(xhr){
      let body = '';
      try { body = xhr.responseText ? xhr.responseText.slice(0, 500) : ''; } catch(e){}
      setPostSelectState('AJAX ' + xhr.status + ' ' + xhr.statusText);
      $out.html(
        '<div style="color:#a00;"><strong><?php echo esc_js(__('Posts load failed', 'ssseo')); ?></strong><br>' +
        'Status: ' + xhr.status + ' ' + xhr.statusText + '<br>' +
        (body ? ('<pre style="white-space:pre-wrap; max-height:200px; overflow:auto;">' + $('<div>').text(body).html() + '</pre>') : '') +
        '</div>'
      );
    });
  }

  function loadHistory(postId){
    $out.html('<?php echo esc_js(__('Loading history…', 'ssseo')); ?>');
    $.post(ajaxUrl, {
      action: 'ssseo_get_meta_history',
      _wpnonce: nonce,
      post_id: postId
    }).done(function(resp){
      if (!resp || resp.success !== true) {
        const msg = (resp && resp.data) ? String(resp.data) : '<?php echo esc_js(__('Unknown error', 'ssseo')); ?>';
        $out.html('<div style="color:#a00;"><strong><?php echo esc_js(__('Error:', 'ssseo')); ?></strong> ' + msg + '</div>');
        return;
      }
      const html = resp.data && resp.data.html ? resp.data.html : '<em><?php echo esc_js(__('No history.', 'ssseo')); ?></em>';
      $out.html(html);
    }).fail(function(xhr){
      let body = '';
      try { body = xhr.responseText ? xhr.responseText.slice(0, 600) : ''; } catch(e){}
      $out.html(
        '<div style="color:#a00;"><strong><?php echo esc_js(__('AJAX failed', 'ssseo')); ?></strong><br>' +
        'Status: ' + xhr.status + ' ' + xhr.statusText + '<br>' +
        (body ? ('<pre style="white-space:pre-wrap; max-height:260px; overflow:auto;">' + $('<div>').text(body).html() + '</pre>') : '') +
        '</div>'
      );
    });
  }

  function exportHistory(postId){
    // (Optional) implement server endpoint 'ssseo_export_meta_history'
    const params = $.param({
      action: 'ssseo_export_meta_history',
      _wpnonce: nonce,
      post_id: postId
    });
    window.location.href = ajaxUrl + '?' + params;
  }

  // Events
  $pt.on('change', populatePosts);
  $search.on('input', function(){
    clearTimeout(searchTimer);
    searchTimer = setTimeout(populatePosts, 300);
  });
  $post.on('change', function(){
    const id = $(this).val();
    $export.toggle(!!id);
    if (id) loadHistory(id);
  });
  $export.on('click', function(e){
    e.preventDefault();
    const id = $post.val();
    if (id) exportHistory(id);
  });

  // Initial load
  $(document).ready(populatePosts);
})(jQuery);
</script>
