<?php
if (!defined('ABSPATH')) exit;
?>
<h4 class="mb-3">Google Maps — Bulk Featured Image Generator</h4>
<p class="text-muted">
  Generate Static Maps and set them as the featured image for selected <strong>Service Area</strong> posts.
</p>

<div class="row g-3 mb-3">
  <div class="col-md-6">
    <label for="ssseo_gmaps_post_list" class="form-label">Select Service Areas</label>
    <select class="form-select" id="ssseo_gmaps_post_list" multiple size="14" aria-describedby="ssseo_gmaps_help"></select>
    <div id="ssseo_gmaps_help" class="form-text">
      Tip: Click then type to jump; hold Ctrl/Cmd to multi-select.
    </div>
    <div class="mt-2">
      <button id="ssseo_gmaps_select_all" type="button" class="btn btn-link p-0 me-3">Select all</button>
      <button id="ssseo_gmaps_clear" type="button" class="btn btn-link p-0">Clear</button>
      <span id="ssseo_gmaps_count" class="ms-2 text-muted"></span>
    </div>
  </div>

  <div class="col-md-6">
    <label class="form-label">Options</label>
    <div class="form-check">
      <input class="form-check-input" type="checkbox" id="ssseo_gmaps_force" value="1">
      <label class="form-check-label" for="ssseo_gmaps_force">
        Regenerate even if a featured image already exists
      </label>
    </div>

    <div class="mt-3 d-flex align-items-center gap-2">
      <button id="ssseo_gmaps_run" class="btn btn-primary">
        Generate Featured Maps
      </button>
      <span id="ssseo_gmaps_status" class="text-muted"></span>
    </div>

    <div class="mt-3" id="ssseo_gmaps_result" style="display:none;">
      <div class="alert alert-info" id="ssseo_gmaps_summary" role="status"></div>
      <pre class="border p-2 bg-light" id="ssseo_gmaps_log" style="max-height:260px; overflow:auto;"></pre>
    </div>
  </div>
</div>

<script>
jQuery(function($){
  const POST_URL = (typeof ajaxurl !== 'undefined' && ajaxurl) ? ajaxurl : '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';

  function getBulkNonce() {
    // 1) Preferred: bulk.php export
    if (window.SSSEO && SSSEO.bulkNonce) return SSSEO.bulkNonce;
    // 2) Hidden input (canonical tab emits one)
    const fromInput = document.getElementById('ssseo_bulk_nonce');
    if (fromInput && fromInput.value) return fromInput.value;
    // 3) Last resort: page-scoped fresh nonce (same action key)
    return '<?php echo esc_js( wp_create_nonce('ssseo_bulk_ops') ); ?>';
  }
  const nonce = getBulkNonce();

  const $list    = $('#ssseo_gmaps_post_list');
  const $run     = $('#ssseo_gmaps_run');
  const $force   = $('#ssseo_gmaps_force');
  const $status  = $('#ssseo_gmaps_status');
  const $wrap    = $('#ssseo_gmaps_result');
  const $sum     = $('#ssseo_gmaps_summary');
  const $log     = $('#ssseo_gmaps_log');
  const $selAll  = $('#ssseo_gmaps_select_all');
  const $clear   = $('#ssseo_gmaps_clear');
  const $count   = $('#ssseo_gmaps_count');

  function updateCount(){
    const n = ($list.val() || []).length;
    $count.text(n ? (n + ' selected') : '');
  }

  function renderOptions(items) {
    $list.empty();
    if (!items || !items.length) {
      $list.append($('<option>').text('No items found'));
      updateCount();
      return;
    }
    items.forEach(function(row){
      $('<option>')
        .val(row.id)
        .text(row.title || ('(no title) #' + row.id))
        .appendTo($list);
    });
    updateCount();
  }

  // Prefer server-provided items (same pattern other tabs use)
  if (window.SSSEO && Array.isArray(window.SSSEO.gmapsItems)) {
    renderOptions(window.SSSEO.gmapsItems);
  } else {
    // Fallback to AJAX
    $list.empty().append($('<option>').text('Loading…'));
    $.post(POST_URL, { action:'ssseo_sa_all_published', nonce: nonce })
      .done(function(resp){
        if (resp && resp.success && resp.data && resp.data.items) {
          renderOptions(resp.data.items);
        } else {
          $list.empty().append($('<option>').text('No items found'));
        }
      })
      .fail(function(xhr){
        let msg = 'Failed to load';
        if (xhr && xhr.status === 403) msg = 'Failed to load (forbidden – check nonce)';
        $list.empty().append($('<option>').text(msg));
      })
      .always(updateCount);
  }

  // Select all / clear
  $selAll.on('click', function(e){
    e.preventDefault();
    $('#ssseo_gmaps_post_list option').prop('selected', true);
    $list.trigger('change');
    updateCount();
  });
  $clear.on('click', function(e){
    e.preventDefault();
    $('#ssseo_gmaps_post_list option').prop('selected', false);
    $list.trigger('change');
    updateCount();
  });
  $list.on('change', updateCount);

  // Run bulk generation
  $run.on('click', function(){
    const raw = ($list.val() || []);
    const ids = raw.map(v => parseInt(v,10) || 0).filter(Boolean);

    if (!ids.length) {
      alert('Select at least one Service Area.');
      return;
    }

    $run.prop('disabled', true).addClass('disabled');
    $status.text('Running…');
    $wrap.hide(); $log.text(''); $sum.text('');

    $.post(POST_URL, {
      action:  'ssseo_bulk_generate_maps',
      nonce:   nonce,
      post_ids: ids,
      force:   $force.is(':checked') ? 1 : 0
    })
    .done(function(resp){
      if (resp && resp.success && resp.data) {
        const d = resp.data;
        $sum.text('Done: ' + d.ok + ' succeeded, ' + d.err + ' failed.');
        $log.text((d.log || []).join('\n'));
      } else {
        $sum.text('Operation failed.');
        $log.text(JSON.stringify(resp || {}, null, 2));
      }
      $wrap.show();
    })
    .fail(function(xhr){
      $wrap.show();
      $sum.text(xhr && xhr.status === 403 ? 'Forbidden (bad or missing nonce)' : 'Network error during bulk generation.');
      $log.text(xhr && xhr.responseText ? xhr.responseText : 'No details');
    })
    .always(function(){
      $status.text('');
      $run.prop('disabled', false).removeClass('disabled');
    });
  });
});
</script>
