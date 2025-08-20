<?php
/**
 * Bulk → Clone Service Areas to Parents
 *
 * Select one source service_area, select multiple parent service_areas (parent = 0),
 * clone the source under each parent, and set the cloned post's ACF 'city_state'
 * using the parent's 'city_state'.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Build lists (include multiple statuses + suppress_filters; fast ID lookups)
$source_posts = get_posts([
    'post_type'        => 'service_area',
    'posts_per_page'   => -1,
    'post_status'      => ['publish','draft','pending','future','private'],
    'orderby'          => 'title',
    'order'            => 'ASC',
    'suppress_filters' => true,
    'fields'           => 'ids',
    'no_found_rows'    => true,
]);

$target_parents = get_posts([
    'post_type'        => 'service_area',
    'posts_per_page'   => -1,
    'post_status'      => ['publish','draft','pending','future','private'],
    'orderby'          => 'title',
    'order'            => 'ASC',
    'post_parent'      => 0,
    'suppress_filters' => true,
    'fields'           => 'ids',
    'no_found_rows'    => true,
]);
?>
<div class="container-fluid mt-4" id="ssseo-bulk-clone-sa">
  <h3 class="mb-3">Clone Service Areas to Parents</h3>
  <p class="text-muted">
    Choose one <strong>source</strong> Service Area and one or more <strong>parent</strong> Service Areas (parent = 0).
    For each selected parent, we'll clone the source as its child and set the clone's ACF <code>city_state</code> to the parent's <code>city_state</code>.
  </p>

  <?php wp_nonce_field( 'ssseo_bulk_clone_sa', 'ssseo_bulk_clone_sa_nonce' ); ?>

  <div class="row g-4">
    <!-- Source (single) -->
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">1) Choose Source Service Area (single)</h5>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="ssseo-reload-source">Reload</button>
          </div>

          <label for="ssseo-clone-sa-source-filter" class="form-label mt-3">Filter source by title</label>
          <input type="text" id="ssseo-clone-sa-source-filter" class="form-control mb-2" placeholder="Type to filter…">

          <select id="ssseo-clone-sa-source" class="form-select" size="12" aria-label="Source service area">
            <?php foreach ( $source_posts as $pid ): ?>
              <option value="<?php echo esc_attr( $pid ); ?>">
                <?php echo esc_html( get_the_title($pid) . ' (ID ' . $pid . ')' ); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <?php if ( empty($source_posts) ) : ?>
            <div class="text-danger small mt-2">No Service Areas found. We’ll try to load them via AJAX.</div>
          <?php endif; ?>

          <div class="form-text mt-2">Copies content, meta (incl. Elementor), taxonomies, and featured image.</div>
        </div>
      </div>
    </div>

    <!-- Targets (multiple) -->
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">2) Choose Target Parents (multiple)</h5>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="ssseo-reload-targets">Reload</button>
          </div>

          <label for="ssseo-clone-sa-target-filter" class="form-label mt-3">Filter targets by title</label>
          <input type="text" id="ssseo-clone-sa-target-filter" class="form-control mb-2" placeholder="Type to filter…">

          <select id="ssseo-clone-sa-targets" class="form-select" multiple size="12" aria-label="Target parent service areas">
            <?php foreach ( $target_parents as $pid ): ?>
              <option value="<?php echo esc_attr( $pid ); ?>">
                <?php echo esc_html( get_the_title($pid) . ' (ID ' . $pid . ')' ); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <?php if ( empty($target_parents) ) : ?>
            <div class="text-danger small mt-2">No top‑level Service Areas (parent=0) found. We’ll try to load them via AJAX.</div>
          <?php endif; ?>

          <div class="form-text mt-2">Only top‑level Service Areas (parent = 0) are listed.</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Extra options -->
  <div class="row g-4 mt-1">
    <div class="col-md-6">
      <label for="ssseo-clone-sa-slug" class="form-label">Slug for new clones (optional)</label>
      <input type="text" id="ssseo-clone-sa-slug" class="form-control mb-2" placeholder="e.g. roofing-in-tampa (leave empty to auto-generate)">
      <div class="form-text">If set, each clone will use this slug (sanitized). WP will suffix if needed.</div>
    </div>
    <div class="col-md-6">
      <label for="ssseo-clone-sa-focus-base" class="form-label">Yoast Focus Keyphrase (base)</label>
      <input type="text" id="ssseo-clone-sa-focus-base" class="form-control mb-2" placeholder="e.g. Pool screen cleaning">
      <div class="form-text">We’ll append the parent’s <code>city_state</code> (commas removed).</div>
    </div>
  </div>

  <!-- Options -->
  <div class="form-check mt-3">
    <input class="form-check-input" type="checkbox" id="ssseo-clone-sa-draft" checked>
    <label class="form-check-label" for="ssseo-clone-sa-draft">
      Create clones as <strong>drafts</strong>
    </label>
  </div>
  <div class="form-check mt-2">
    <input class="form-check-input" type="checkbox" id="ssseo-clone-sa-skip-existing" checked>
    <label class="form-check-label" for="ssseo-clone-sa-skip-existing">
      Skip if a child with the <em>same title</em> already exists under that parent
    </label>
  </div>
  <div class="form-check mt-2">
    <input class="form-check-input" type="checkbox" id="ssseo-clone-sa-debug">
    <label class="form-check-label" for="ssseo-clone-sa-debug">
      Show debug details
    </label>
  </div>

  <!-- Action -->
  <div class="mt-3">
    <button type="button" class="button button-primary" id="ssseo-clone-sa-run">
      Clone Now
    </button>
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
  const nonce  = (window.SSSEO && SSSEO.nonce) ? SSSEO.nonce : '';

  function optionRow(id, title){
    return $('<option>').val(id).text((title || '(no title)') + ' (ID ' + id + ')');
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

  // AJAX loaders (resilient to filters)
  function loadSourcesViaAjax() {
    if (!nonce) { console.warn('Missing SSSEO.nonce for source list'); return; }
    $src.prop('disabled', true);
    $.post(ajaxurl, { action: 'ssseo_sa_all', nonce: nonce }).done(function(res){
      if (res && res.success && res.data && Array.isArray(res.data.items)) {
        $src.empty();
        res.data.items.forEach(function(it){ $src.append(optionRow(it.id, it.title)); });
      }
    }).always(function(){ $src.prop('disabled', false); });
  }
  function loadTargetsViaAjax() {
    if (!nonce) { console.warn('Missing SSSEO.nonce for target list'); return; }
    $tgt.prop('disabled', true);
    $.post(ajaxurl, { action: 'ssseo_sa_top_parents', nonce: nonce }).done(function(res){
      if (res && res.success && res.data && Array.isArray(res.data.items)) {
        $tgt.empty();
        res.data.items.forEach(function(it){ $tgt.append(optionRow(it.id, it.title)); });
      }
    }).always(function(){ $tgt.prop('disabled', false); });
  }

  // Manual reload buttons
  $('#ssseo-reload-source').on('click', loadSourcesViaAjax);
  $('#ssseo-reload-targets').on('click', loadTargetsViaAjax);

  // Auto‑backfill if server rendered empty lists
  if ($src.find('option').length === 0) loadSourcesViaAjax();
  if ($tgt.find('option').length === 0) loadTargetsViaAjax();

  // Clone action
  $('#ssseo-clone-sa-run').on('click', function(){
    var $btn     = $(this);
    var $spin    = $('#ssseo-clone-sa-spinner');
    var $results = $('#ssseo-clone-sa-results');

    var nonceLocal = $('#ssseo_bulk_clone_sa_nonce').val() || $('[name="ssseo_bulk_clone_sa_nonce"]').val();
    var source_id  = $src.val();
    var target_ids = $tgt.val() || [];

    if (!source_id) { alert('Select a source Service Area.'); return; }
    if (!target_ids.length) { alert('Select at least one target parent.'); return; }

    var payload = {
      action: 'ssseo_clone_sa_to_parents',
      nonce: nonceLocal,
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
    $results.empty().append('<div>Running…</div>');

    $.post(ajaxurl, payload).done(function(res){
      if (res && res.success) {
        var lines = (res.data && Array.isArray(res.data.log)) ? res.data.log : [];
        if (!lines.length) {
          $results.html('<div class="text-success">Done (no log returned).</div>');
        } else {
          var list = $('<ul class="mb-0"></ul>');
          lines.forEach(function(line){ $('<li>').text(line).appendTo(list); });
          $results.empty().append(list);
        }
      } else {
        var msg = (res && res.data) ? res.data : 'Unknown error.';
        $results.html('<div class="text-danger">Error: '+ msg +'</div>');
      }
    }).fail(function(){
      $results.html('<div class="text-danger">Network error.</div>');
    }).always(function(){
      $btn.prop('disabled', false);
      $spin.hide();
    });
  });
});
</script>
