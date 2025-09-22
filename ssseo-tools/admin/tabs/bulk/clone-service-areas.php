<?php
/**
 * Subtab: Clone Service Areas to Parents
 * - Uses context injected by bulk.php: $SSSEO_CLONE_CTX
 * - NO AJAX handlers here (keeps this file modular)
 */
if (!defined('ABSPATH')) exit;

$all_service_areas = isset($SSSEO_CLONE_CTX['all_service_areas']) ? (array)$SSSEO_CLONE_CTX['all_service_areas'] : [];
$target_tree_items = isset($SSSEO_CLONE_CTX['target_tree_items']) ? (array)$SSSEO_CLONE_CTX['target_tree_items'] : [];
$bulk_nonce        = isset($SSSEO_CLONE_CTX['bulk_nonce']) ? $SSSEO_CLONE_CTX['bulk_nonce'] : wp_create_nonce('ssseo_bulk_ops');
?>
<div class="container-fluid" id="ssseo-bulk-clone-sa">
  <p class="text-muted">
    Select one <strong>source</strong> Service Area and one or more <strong>parent</strong> Service Areas (any level).
    We’ll clone the source under each selected parent and set ACF <code>city_state</code> from the parent.
  </p>

  <?php // IMPORTANT: unified nonce for this subtab ?>
  <input type="hidden" id="ssseo_bulk_ops_nonce" value="<?php echo esc_attr($bulk_nonce); ?>">

  <div class="row g-4">
    <!-- Source (single) -->
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">1) Choose Source Service Area (single)</h5>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="ssseo-reload-source">Reload</button>
          </div>
          <label for="ssseo-clone-sa-source-filter" class="form-label mt-3">Filter source by title</label>
          <input type="text" id="ssseo-clone-sa-source-filter" class="form-control mb-2" placeholder="Type to filter…">

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

    <!-- Targets (multiple, hierarchical) -->
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">2) Choose Target Parents (multiple, hierarchical)</h5>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="ssseo-reload-targets">Reload</button>
          </div>

          <label for="ssseo-clone-sa-target-filter" class="form-label mt-3">Filter targets by title</label>
          <input type="text" id="ssseo-clone-sa-target-filter" class="form-control mb-2" placeholder="Type to filter…">

          <select id="ssseo-clone-sa-targets" class="form-select" multiple size="12" aria-label="Target parent service areas">
            <?php foreach ($target_tree_items as $node): ?>
              <?php $indent = str_repeat('— ', max(0, (int)$node['depth'])); ?>
              <option value="<?php echo esc_attr($node['id']); ?>">
                <?php echo esc_html($indent . $node['title'] . ' (ID ' . $node['id'] . ')'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <div class="form-text mt-2">All published Service Areas are listed in a hierarchical, indented tree.</div>

          <label for="ssseo-clone-sa-slug" class="form-label mt-3">Slug for new clones (optional)</label>
          <input type="text" id="ssseo-clone-sa-slug" class="form-control mb-2" placeholder="e.g. roofing-in-tampa (leave empty to auto-generate)">
          <div class="form-text mb-2">If set, each clone will use this slug (sanitized). WP will suffix if needed.</div>

          <label for="ssseo-clone-sa-focus-base" class="form-label">Yoast Focus Keyphrase (base)</label>
          <input type="text" id="ssseo-clone-sa-focus-base" class="form-control mb-2" placeholder="e.g. Pool screen cleaning">
          <div class="form-text">We’ll append the parent’s <code>city_state</code>.</div>
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

<script>
jQuery(function($){
  const $src   = $('#ssseo-clone-sa-source');
  const $tgt   = $('#ssseo-clone-sa-targets');
  const $srcF  = $('#ssseo-clone-sa-source-filter');
  const $tgtF  = $('#ssseo-clone-sa-target-filter');

  function optionRow(id, title){
    return $('<option>').val(id).text((title || '(no title)') + ' (ID ' + id + ')');
  }
  function optionRowIndented(id, title, depth){
    var indent = '';
    for (var i=0; i<depth; i++) indent += '— ';
    return $('<option>').val(id).text(indent + (title || '(no title)') + ' (ID ' + id + ')');
  }
  function filterSelect($input, $select) {
    var needle = ($input.val() || '').toLowerCase();
    $select.find('option').each(function(){
      var txt = $(this).text().toLowerCase();
      $(this).toggle(txt.indexOf(needle) !== -1);
    });
  }

  // Local filter UX
  $srcF.on('input', function(){ filterSelect($srcF, $src); });
  $tgtF.on('input', function(){ filterSelect($tgtF, $tgt); });

  // Reload buttons – AJAX backed by handlers in bulk.php
  function reloadSources(){
    const nonce = (window.SSSEO && (SSSEO.bulkNonce || SSSEO.nonce)) ? (SSSEO.bulkNonce || SSSEO.nonce) : ($('#ssseo_bulk_ops_nonce').val() || '');
    $src.prop('disabled', true);
    $.post(ajaxurl, { action: 'ssseo_sa_all_published', nonce: nonce }).done(function(res){
      if (res && res.success && res.data && Array.isArray(res.data.items)) {
        $src.empty();
        res.data.items.forEach(function(it){ $src.append(optionRow(it.id, it.title)); });
      }
    }).always(function(){ $src.prop('disabled', false); });
  }

  function reloadTargets(){
    const nonce = (window.SSSEO && (SSSEO.bulkNonce || SSSEO.nonce)) ? (SSSEO.bulkNonce || SSSEO.nonce) : ($('#ssseo_bulk_ops_nonce').val() || '');
    $tgt.prop('disabled', true);
    $.post(ajaxurl, { action: 'ssseo_sa_tree_published', nonce: nonce }).done(function(res){
      if (res && res.success && res.data && Array.isArray(res.data.items)) {
        $tgt.empty();
        res.data.items.forEach(function(it){
          $tgt.append(optionRowIndented(it.id, it.title, it.depth || 0));
        });
      }
    }).always(function(){ $tgt.prop('disabled', false); });
  }

  $('#ssseo-reload-source').on('click', reloadSources);
  $('#ssseo-reload-targets').on('click', reloadTargets);

  // Clone action
  $('#ssseo-clone-sa-run').on('click', function(){
    const $btn = $(this), $spin = $('#ssseo-clone-sa-spinner'), $results = $('#ssseo-clone-sa-results');
    const nonce = (window.SSSEO && (SSSEO.bulkNonce || SSSEO.nonce)) ? (SSSEO.bulkNonce || SSSEO.nonce) : ($('#ssseo_bulk_ops_nonce').val() || '');

    const source_id  = $src.val();
    const target_ids = $tgt.val() || [];
    if (!source_id) { alert('Select a source Service Area.'); return; }
    if (!target_ids.length) { alert('Select at least one target parent.'); return; }

    const payload = {
      action: 'ssseo_clone_sa_to_parents',
      nonce: nonce,                                // IMPORTANT: unified nonce
      source_id: source_id,
      target_parent_ids: target_ids,
      as_draft: $('#ssseo-clone-sa-draft').is(':checked') ? 1 : 0,
      skip_existing: $('#ssseo-clone-sa-skip-existing').is(':checked') ? 1 : 0,
      debug: $('#ssseo-clone-sa-debug').is(':checked') ? 1 : 0,
      new_slug: $('#ssseo-clone-sa-slug').val() || '',
      focus_base: $('#ssseo-clone-sa-focus-base').val() || ''
    };

    $btn.prop('disabled', true);
    $spin.show();
    $results.html('<div>Running…</div>');

    $.post(ajaxurl, payload).done(function(res){
      if (res && res.success) {
        const lines = (res.data && Array.isArray(res.data.log)) ? res.data.log : [];
        if (lines.length) {
          const list = $('<ul class="mb-0"></ul>');
          lines.forEach(function(line){ $('<li>').text(line).appendTo(list); });
          $results.html(list);
        } else {
          $results.html('<div class="text-success">Done (no log returned).</div>');
        }
      } else {
        $results.html('<div class="text-danger">Error: '+ (res && res.data ? res.data : 'Unknown error.') +'</div>');
      }
    }).fail(function(xhr){
      $results.html('<div class="text-danger">Network error.</div>');
      console.error('Clone AJAX failed', xhr && xhr.responseText);
    }).always(function(){
      $btn.prop('disabled', false);
      $spin.hide();
    });
  });
});
</script>
