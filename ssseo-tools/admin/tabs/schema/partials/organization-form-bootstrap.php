<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="container-fluid px-4">
  <form method="post" novalidate>
    <?php wp_nonce_field( 'ssseo_org_schema_save', 'ssseo_org_schema_nonce' ); ?>

    <div class="row gx-5">
      <!-- Left Column -->
      <div class="col-lg-8">
        <h3 class="mb-3">Organization Schema</h3>
        <p>Enter your organization's information to generate schema.</p>

        <?php
        foreach ([
          'name' => 'Organization Name',
          'url' => 'Organization URL',
          'phone' => 'Phone Number',
          'email' => 'Email',
          'address' => 'Street Address',
          'locality' => 'Locality',
          'postal_code' => 'Postal Code',
          'latitude' => 'Latitude',
          'longitude' => 'Longitude'
        ] as $key => $label) {
          echo '<div class="mb-4">';
          echo "<label for='ssseo_organization_{$key}' class='form-label fw-bold'>{$label}</label>";
          echo "<input type='text' class='form-control' id='ssseo_organization_{$key}' name='ssseo_organization_{$key}' value='" . esc_attr( $org_fields[$key] ) . "'>";
          echo '</div>';
        }
        ?>

        <div class="row g-4">
          <div class="col-md-6">
            <label for="ssseo_organization_state" class="form-label fw-bold">State</label>
            <select id="ssseo_organization_state" name="ssseo_organization_state" class="form-select">
              <?php foreach ( $states as $state ) : ?>
                <option value="<?php echo esc_attr( $state ); ?>" <?php selected( $org_fields['state'], $state ); ?>><?php echo esc_html( $state ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="ssseo_organization_country" class="form-label fw-bold">Country</label>
            <select id="ssseo_organization_country" name="ssseo_organization_country" class="form-select">
              <?php foreach ( $countries as $country ) : ?>
                <option value="<?php echo esc_attr( $country ); ?>" <?php selected( $org_fields['country'], $country ); ?>><?php echo esc_html( $country ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="mb-4 mt-4">
          <label for="ssseo_organization_description" class="form-label fw-bold">Description</label>
          <textarea id="ssseo_organization_description" name="ssseo_organization_description" class="form-control" rows="4"><?php echo esc_textarea( $org_fields['description'] ); ?></textarea>
        </div>

        <div class="mb-4">
          <label for="ssseo_organization_areas_served" class="form-label fw-bold">Areas Served</label>
          <textarea id="ssseo_organization_areas_served" name="ssseo_organization_areas_served" class="form-control" rows="3"><?php echo esc_textarea( $org_fields['areas_served'] ); ?></textarea>
        </div>

        <div class="mb-4">
          <label class="form-label fw-bold">Organization Logo</label>
          <div id="ssseo-logo-preview" class="mb-2">
            <?php if ( $logo_url ) : ?>
              <img src="<?php echo esc_url( $logo_url ); ?>" style="max-height:80px" alt="Logo Preview">
            <?php endif; ?>
          </div>
          <input type="hidden" id="ssseo_organization_logo" name="ssseo_organization_logo" value="<?php echo esc_attr( $logo_id ); ?>">
          <button type="button" class="btn btn-secondary" id="ssseo-upload-logo">Select Logo</button>
        </div>

        <div class="mb-4">
          <label class="form-label fw-bold">Social Profiles</label>
          <div id="ssseo-social-wrapper">
            <?php foreach ( $socials as $url ) : ?>
              <div class="d-flex mb-2 align-items-center ssseo-social-row">
                <input type="url" name="ssseo_organization_social_profiles[]" class="form-control me-2" value="<?php echo esc_url( $url ); ?>" placeholder="https://example.com">
                <button type="button" class="btn btn-outline-danger ssseo-remove-social">&times;</button>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-outline-primary mt-2" id="ssseo-add-social">+ Add Social Profile</button>
        </div>
      </div>

      <!-- Right Column -->
      <div class="col-lg-4">
        <h5 class="mb-3">Display on Pages</h5>
        <label for="ssseo_organization_schema_pages" class="form-label fw-bold">Select Pages</label>
        <select name="ssseo_organization_schema_pages[]" id="ssseo_organization_schema_pages" class="form-select" multiple size="16">
          <?php foreach ( $all_pages as $page ) : ?>
            <option value="<?php echo esc_attr( $page->ID ); ?>" <?php echo in_array( $page->ID, $pages, true ) ? 'selected' : ''; ?>><?php echo esc_html( $page->post_title ); ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-muted mt-2 small">Hold Ctrl (Windows) or Cmd (Mac) to select multiple pages.</p>
      </div>
    </div>

    <div class="mt-4">
      <button type="submit" class="btn btn-primary btn-lg">Save Organization Schema</button>
    </div>
  </form>
</div>

<script>
jQuery(function($){
  $('#ssseo-upload-logo').on('click', function(e){
    e.preventDefault();
    var frame = wp.media({ title: 'Select Logo', button: { text: 'Use this logo' }, multiple: false });
    frame.on('select', function(){
      var attachment = frame.state().get('selection').first().toJSON();
      $('#ssseo_organization_logo').val(attachment.id);
      $('#ssseo-logo-preview').html('<img src="'+attachment.url+'" style="max-height:80px" alt="Logo Preview">');
    });
    frame.open();
  });

  $('#ssseo-add-social').on('click', function(e){
    e.preventDefault();
    $('#ssseo-social-wrapper').append(
      '<div class="d-flex mb-2 align-items-center ssseo-social-row">' +
        '<input type="url" name="ssseo_organization_social_profiles[]" class="form-control me-2" placeholder="https://example.com" />' +
        '<button type="button" class="btn btn-outline-danger ssseo-remove-social">&times;</button>' +
      '</div>'
    );
  });

  $(document).on('click', '.ssseo-remove-social', function(){
    $(this).closest('.ssseo-social-row').remove();
  });
});
</script>
