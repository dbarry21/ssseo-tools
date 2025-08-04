<?php
if (!defined('ABSPATH')) exit;
?>

<h4 class="mb-3">Bulk Update Canonical URLs</h4>
<p>Select a source page/post and apply its URL as the canonical URL to multiple target posts.</p>

<form id="ssseo-canonical-form">
  <div class="row g-4">

    <!-- Source Post -->
    <div class="col-md-6">
      <h5>Source Post</h5>

      <div class="mb-3">
        <label class="form-label">Post Type</label>
        <select class="form-select canonical-type" id="canonical_source_type"></select>
      </div>

      <div class="mb-3">
        <label class="form-label">Search</label>
        <input type="text" class="form-control mb-2" id="canonical_source_search" placeholder="Filter by title...">
        <select class="form-select" id="canonical_source_post" size="10"></select>
      </div>
    </div>

    <!-- Target Posts -->
    <div class="col-md-6">
      <h5>Target Posts</h5>

      <div class="mb-3">
        <label class="form-label">Post Type</label>
        <select class="form-select canonical-type" id="canonical_target_type"></select>
      </div>

      <div class="mb-3">
        <label class="form-label">Search</label>
        <input type="text" class="form-control mb-2" id="canonical_target_search" placeholder="Filter by title...">
        <select class="form-select" id="canonical_target_posts" size="10" multiple></select>
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
jQuery(document).ready(function ($) {
  const types = Object.keys(ssseoPostsByType);

  types.forEach(type => {
    $('#canonical_source_type, #canonical_target_type').append(
      $('<option>').val(type).text(type)
    );
  });

  function renderOptions(posts, $select, filter = '') {
    $select.empty();
    posts.forEach(post => {
      if (!filter || post.title.toLowerCase().includes(filter.toLowerCase())) {
        const $opt = $('<option>').val(post.id).text(post.title);
        $select.append($opt);
      }
    });
  }

  function handleTypeChange(typeSelect, targetSelect, searchInput, multi = false) {
    const type = typeSelect.val();
    const posts = ssseoPostsByType[type] || [];

    searchInput.off().on('input', function () {
      const filter = $(this).val();
      renderOptions(posts, targetSelect, filter);
    });

    renderOptions(posts, targetSelect);
  }

  $('#canonical_source_type').on('change', function () {
    handleTypeChange($(this), $('#canonical_source_post'), $('#canonical_source_search'));
  });

  $('#canonical_target_type').on('change', function () {
    handleTypeChange($(this), $('#canonical_target_posts'), $('#canonical_target_search'), true);
  });

  $('#canonical_source_type').trigger('change');
  $('#canonical_target_type').trigger('change');

  $('#ssseo-canonical-form').on('submit', function (e) {
    e.preventDefault();
    const sourceId = $('#canonical_source_post').val();
    const targetIds = $('#canonical_target_posts').val();

    if (!sourceId || targetIds.length === 0) {
      alert('Please select both source and target posts.');
      return;
    }

    $.post(ajaxurl, {
      action: 'ssseo_bulk_set_canonical',
      nonce: SSSEO.nonce,
      source_id: sourceId,
      target_ids: targetIds,
    }, function (res) {
      if (res.success) {
        $('#ssseo-canonical-result').removeClass('d-none').text(res.data);
      } else {
        alert(res.data || 'Error processing request.');
      }
    });
  });
});

</script>