<?php
/**
 * Bulk → Clone Service Areas to Parents
 *
 * Select one source service_area, select multiple parent service_areas (parent = 0),
 * clone the source under each parent, and set the cloned post's ACF 'city_state'
 * using the parent's 'city_state'.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Build lists
$source_posts = get_posts([
    'post_type'        => 'service_area',
    'posts_per_page'   => -1,
    'post_status'      => 'publish',
    'orderby'          => 'title',
    'order'            => 'ASC',
    'suppress_filters' => true,
]);

$target_parents = get_posts([
    'post_type'        => 'service_area',
    'posts_per_page'   => -1,
    'post_status'      => 'publish',
    'orderby'          => 'title',
    'order'            => 'ASC',
    'post_parent'      => 0,
    'suppress_filters' => true,
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
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title">1) Choose Source Service Area (single)</h5>
          <select id="ssseo-clone-sa-source" class="form-select" size="12" aria-label="Source service area">
            <?php foreach ( $source_posts as $p ): ?>
              <option value="<?php echo esc_attr( $p->ID ); ?>">
                <?php echo esc_html( $p->post_title . ' (ID ' . $p->ID . ')' ); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text mt-2">Copies content, meta (incl. Elementor), taxonomies, and featured image.</div>
        </div>
      </div>
    </div>

    <!-- Targets (multiple) -->
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title">2) Choose Target Parents (multiple)</h5>
          <select id="ssseo-clone-sa-targets" class="form-select" multiple size="12" aria-label="Target parent service areas">
            <?php foreach ( $target_parents as $p ): ?>
              <option value="<?php echo esc_attr( $p->ID ); ?>">
                <?php echo esc_html( $p->post_title . ' (ID ' . $p->ID . ')' ); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text mt-2">Only top‑level Service Areas (parent = 0) are listed.</div>
        </div>
      </div>
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
    <input class="form-check-input" type="checkbox" id="ssseo-clone-sa-debug" checked>
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
