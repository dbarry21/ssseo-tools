<?php
if (!defined('ABSPATH')) exit;
/**
 * Canonical Bulk Updater Subtab
 *
 * Requires:
 *   - window.ssseoPostsByType (set by bulk.php)
 *   - window.SSSEO.nonce      (set by bulk.php; server handler should verify 'ssseo_bulk_ops')
 *
 * AJAX action expected server-side:
 *   add_action('wp_ajax_ssseo_bulk_set_canonical', 'ssseo_bulk_set_canonical_handler');
 *   // Handler should check: check_ajax_referer('ssseo_bulk_ops', 'nonce');
 *   // And then set Yoast canonical meta on each target ID to the source permalink.
 */
?>

<h4 class="mb-3">Bulk Update Canonical URLs</h4>
<p class="text-muted">Select a source page/post and apply its URL as the canonical URL to multiple target posts.</p>

<form id="ssseo-canonical-form" autocomplete="off">
  <div class="row g-4">

    <!-- Source Post -->
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title">Source Post</h5>

          <div class="mb-3">
            <label class="form-label" for="canonical_source_type">Post Type</label>
            <select class="form-select canonical-type" id="canonical_source_type" aria-label="Source post type"></select>
          </div>

          <div class="mb-3">
            <label class="form-label" for="canonical_source_search">Search</label>
            <input type="text" class="form-control mb-2" id="canonical_source_search" placeholder="Filter by title…">
            <select class="form-select" id="canonical_source_post" size="12" aria-label="Source post"></select>
            <div class="form-text">Pick one post. Its permalink becomes the canonical URL used for all targets.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Target Posts -->
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title">Target Posts</h5>

          <div class="mb-3">
            <label class="form-label" for="canonical_target_type">Post Type</label>
            <select class="form-select canonical-type" id="canonical_target_type" aria-label="Target post type"></select>
          </div>

          <div class="mb-3">
            <label class="form-label" for="canonical_target_search">Search</label>
            <input type="text" class="form-control mb-2" id="canonical_target_search" placeholder="Filter by title…">
            <select class="form-select" id="canonical_target_posts" size="12" multiple aria-label="Target posts"></select>
            <div class="form-text">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <button type="submit" class="btn btn-primary">Apply Canonical</button>
      <div id="ssseo-canonical-result" class="mt-3 alert alert-success d-none"></div>

      <div id="ssseo-canonical-live-log" class="mt-4 p-3 bg-light border rounded small" style="max-height: 300px; overflow-y: auto; display: none;">
        <strong>Update Log:</strong>
        <ul id="ssseo-canonical-log-list" class="mb-0"></ul>
      </div>
    </div>
  </div>
</form>

<script>
jQuery(function ($) {
  // Guard for data availability
  if (typeof window.ssseoPostsByType === 'undefined') {
    console.warn('ssseoPostsByType is not defined.');
    $('#ssseo-canonical-form').prepend('<div class="alert alert-warning mb-3">Post lists not available. Reload the page.</div>');
    return;
  }
  if (!window.SSSEO || !window.SSSEO.nonce) {
    console.warn('SSSEO.nonce is not defined.');
    $('#ssseo-canonical-form').prepend('<div class="alert alert-warning mb-3">Security nonce missing. Reload the page.</div>');
    return;
  }

  const types = Object.keys(ssseoPostsByType || {});
  const $srcType   = $('#canonical_source_type');
  const $srcSearch = $('#canonical_source_search');
  const $srcPost   = $('#canonical_source_post');

  const $tgtType   = $('#canonical_target_type');
  const $tgtSearch = $('#canonical_target_search');
  const $tgtPosts  = $('#canonical_target_posts');

  // Populate type dropdowns
  types.forEach(type => {
    $('<option>').val(type).text(type).appendTo($srcType);
    $('<option>').val(type).text(type).appendTo($tgtType);
  });

  function renderOptions(posts, $select, filter) {
    $select.empty();
    const f = (filter || '').toLowerCase();
    posts.forEach(post => {
      if (!f || (post.title && post.title.toLowerCase().includes(f))) {
        $('<option>')
          .val(post.id)
          .text(post.title || ('(no title) #' + post.id))
          .appendTo($select);
      }
    });
    if ($select.children().length === 0) {
      $('<option disabled>(No posts found.)</option>').appendTo($select);
    }
  }

  function bindType($typeSelect, $listSelect, $searchInput) {
    const type = $typeSelect.val();
    const posts = ssseoPostsByType[type] || [];
    $searchInput.off('input').on('input', function () {
      renderOptions(posts, $listSelect, $(this).val());
    });
    renderOptions(posts, $listSelect, $searchInput.val());
  }

  // Initial binds + triggers
  $srcType.on('change', () => bindType($srcType, $srcPost, $srcSearch));
  $tgtType.on('change', () => bindType($tgtType, $tgtPosts, $tgtSearch));
  $srcType.prop('selectedIndex', 0).trigger('change');
  $tgtType.prop('selectedIndex', 0).trigger('change');

  // Helpers for live log
  function showLog() {
    $('#ssseo-canonical-live-log').show();
  }
  function addLog(msg) {
    $('<li>').text(msg).appendTo('#ssseo-canonical-log-list');
  }

  // Submit
  $('#ssseo-canonical-form').on('submit', function (e) {
    e.preventDefault();

    const sourceId = $srcPost.val();
    const targetIds = $tgtPosts.val() || [];

    if (!sourceId) {
      alert('Please select a source post.');
      return;
    }
    if (targetIds.length === 0) {
      alert('Please select at least one target post.');
      return;
    }

    // UI
    $('#ssseo-canonical-result').addClass('d-none').empty();
    $('#ssseo-canonical-log-list').empty();
    showLog();
    addLog('Starting canonical update…');

    $.post(ajaxurl, {
      action: 'ssseo_bulk_set_canonical',
      nonce: SSSEO.nonce,
      source_id: sourceId,
      target_ids: targetIds
    }).done(function (res) {
      if (res && res.success) {
        const msg = (res.data && (res.data.message || res.data)) || 'Canonical updated.';
        $('#ssseo-canonical-result').removeClass('d-none').text(msg);
        addLog('Success: ' + msg);
        if (res.data && res.data.details && Array.isArray(res.data.details)) {
          res.data.details.forEach(line => addLog(line));
        }
      } else {
        const err = (res && res.data) ? res.data : 'Error processing request.';
        alert(err);
        addLog('Error: ' + err);
      }
    }).fail(function () {
      alert('Network error while applying canonical.');
      addLog('Network error.');
    });
  });
});
</script>
