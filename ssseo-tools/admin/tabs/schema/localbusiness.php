<?php
/**
 * Admin UI: Local Business Schema – Bootstrap 5 Two-Column Layout
 *
 * Refactored to match Organization tab UI
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Save handler
if ( isset($_POST['ssseo_localbusiness_nonce']) && wp_verify_nonce($_POST['ssseo_localbusiness_nonce'], 'ssseo_localbusiness_save') ) {
    $existing = get_option('ssseo_localbusiness_locations', []);

    // Handle delete request
    if ( isset($_POST['ssseo_delete_location']) ) {
        $delete_index = (int) $_POST['ssseo_delete_location'];
        if ( isset($existing[$delete_index]) ) {
            unset($existing[$delete_index]);
            $existing = array_values($existing); // reindex
            update_option('ssseo_localbusiness_locations', $existing);
            echo '<div class="alert alert-warning">Location #' . ($delete_index + 1) . ' deleted.</div>';
            return;
        }
    }
    $raw = $_POST['ssseo_locations'] ?? [];
    $clean = [];

    foreach ( $raw as $loc ) {
        $out = [
            'name'    => sanitize_text_field($loc['name'] ?? ''),
            'phone'   => sanitize_text_field($loc['phone'] ?? ''),
            'price'   => sanitize_text_field($loc['price'] ?? ''),
            'street'  => sanitize_text_field($loc['street'] ?? ''),
            'city'    => sanitize_text_field($loc['city'] ?? ''),
            'state'   => sanitize_text_field($loc['state'] ?? ''),
            'zip'     => sanitize_text_field($loc['zip'] ?? ''),
            'country' => sanitize_text_field($loc['country'] ?? ''),
            'lat'     => sanitize_text_field($loc['lat'] ?? ''),
            'lng'     => sanitize_text_field($loc['lng'] ?? ''),
        ];

        $out['hours'] = [];
        if ( isset($loc['hours']) && is_array($loc['hours']) ) {
            foreach ( $loc['hours'] as $h ) {
                $d = sanitize_text_field($h['day'] ?? '');
                $o = sanitize_text_field($h['open'] ?? '');
                $c = sanitize_text_field($h['close'] ?? '');
                if ( $d || $o || $c ) $out['hours'][] = [ 'day' => $d, 'open' => $o, 'close' => $c ];
            }
        }

        $out['pages'] = array_map('absint', $loc['pages'] ?? []);
        $clean[] = $out;
    }

    update_option('ssseo_localbusiness_locations', $clean);
    echo '<div class="alert alert-success">Local Business locations saved.</div>';
}

$locations = get_option('ssseo_localbusiness_locations', []) ?: [[]];
$defaults = [
    'name' => get_option('ssseo_organization_name', ''),
    'phone' => get_option('ssseo_organization_phone', ''),
    'price' => '',
    'lat' => get_option('ssseo_organization_latitude', ''),
    'lng' => get_option('ssseo_organization_longitude', ''),
    'street' => get_option('ssseo_organization_address', ''),
    'city' => get_option('ssseo_organization_locality', ''),
    'state' => get_option('ssseo_organization_state', ''),
    'zip' => get_option('ssseo_organization_postal_code', ''),
    'country' => get_option('ssseo_organization_country', ''),
];
$pages = get_posts([ 'post_type' => ['page','service_area'], 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'asc' ]);

?>
<form method="post">
<?php wp_nonce_field('ssseo_localbusiness_save', 'ssseo_localbusiness_nonce'); ?>

<div class="accordion" id="locationAccordion">
<?php foreach ($locations as $i => $loc):
    $loc = array_merge($defaults, $loc);
    $prefix = "ssseo_locations[$i]";
?>
<div class="accordion-item">
  <h2 class="accordion-header" id="heading<?php echo $i; ?>">
    <button class="accordion-button <?php echo $i === 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $i; ?>">
      Location #<?php echo $i + 1; ?>
    </button>
  </h2>
  <div id="collapse<?php echo $i; ?>" class="accordion-collapse collapse <?php echo $i === 0 ? 'show' : ''; ?>" data-bs-parent="#locationAccordion">
    <div class="text-end mb-3">
        <button type="submit" name="ssseo_delete_location" value="<?php echo $i; ?>" class="btn btn-danger btn-sm">Delete This Location</button>
      </div>
      <div class="row">
        <div class="col-md-8">
          <div class="row">
          <?php
          foreach ([
            'name' => 'Business Name', 'phone' => 'Phone', 'price' => 'Price Range',
            'street' => 'Street', 'city' => 'City', 'state' => 'State',
            'zip' => 'ZIP Code', 'country' => 'Country', 'lat' => 'Latitude', 'lng' => 'Longitude'
          ] as $field => $label):
          ?>
          <div class="col-md-6 mb-3">
            <label class="form-label"><?php echo $label; ?></label>
            <input type="text" class="form-control" name="<?php echo $prefix; ?>[<?php echo $field; ?>]" value="<?php echo esc_attr($loc[$field]); ?>">
          </div>
          <?php endforeach; ?>

          <div class="mb-4">
            <label class="form-label">Opening Hours</label>
            <div class="row g-2 align-items-center">
              <?php
              $loc['hours'] = isset($loc['hours']) && is_array($loc['hours']) && count($loc['hours']) > 0 ? $loc['hours'] : [['day' => '', 'open' => '', 'close' => '']];
				foreach ( $loc['hours'] as $j => $hour ):
              ?>
              <div class="col-md-4">
                <select class="form-select" name="<?php echo $prefix; ?>[hours][<?php echo $j; ?>][day]">
                  <option value="">-- Day --</option>
                  <?php foreach (["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"] as $day): ?>
                    <option value="<?php echo $day; ?>" <?php selected($hour['day'] ?? '', $day); ?>><?php echo $day; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <input type="time" class="form-control" name="<?php echo $prefix; ?>[hours][<?php echo $j; ?>][open]" value="<?php echo esc_attr($hour['open'] ?? ''); ?>">
              </div>
              <div class="col-md-4">
                <input type="time" class="form-control" name="<?php echo $prefix; ?>[hours][<?php echo $j; ?>][close]" value="<?php echo esc_attr($hour['close'] ?? ''); ?>">
              </div>
              <?php endforeach; ?>
				<div class="col-12 mt-2">
  <button type="button" class="btn btn-sm btn-outline-secondary add-hours" data-index="<?php echo $i; ?>">
    + Add Hours Row
  </button>
</div>

            </div>
          </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="mb-3">
            <label class="form-label">Assign to Pages/Service Areas</label>
            <select class="form-select" name="<?php echo $prefix; ?>[pages][]" multiple size="12">
              <?php
              $sel = $loc['pages'] ?? [];
              foreach ( $pages as $p ) {
                $selected = in_array($p->ID, $sel) ? 'selected' : '';
                $label = get_post_type($p->ID) === 'service_area' ? 'Service Area: ' : '';
                echo "<option value=\"{$p->ID}\" $selected>{$label}{$p->post_title}</option>";
              }
              ?>
            </select>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<p class="mt-4">
  <button type="submit" class="btn btn-primary">Save Locations</button>
  <button type="button" class="btn btn-secondary ms-2" id="addLocation">+ Add Location</button>
</p>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.getElementById('addLocation')?.addEventListener('click', function () {
    const form = document.querySelector('form');
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'ssseo_locations[' + document.querySelectorAll('.accordion-item').length + '][__new]';
    input.value = '1';
    form.appendChild(input);
    form.submit();
  });

  document.querySelectorAll('.add-hours').forEach(function(button) {
    button.addEventListener('click', function() {
      const index = this.getAttribute('data-index');
      const container = this.closest('.row');
      const rowCount = container.querySelectorAll('select[name^="ssseo_locations[' + index + '][hours]"]').length;
      const row = document.createElement('div');
      row.className = 'row g-2 align-items-center mt-2';
      row.innerHTML = `
        <div class="col-md-4">
          <select class="form-select" name="ssseo_locations[${index}][hours][${rowCount}][day]">
            <option value="">-- Day --</option>
            ${["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"].map(day => `<option value="${day}">${day}</option>`).join('')}
          </select>
        </div>
        <div class="col-md-4">
          <input type="time" class="form-control" name="ssseo_locations[${index}][hours][${rowCount}][open]">
        </div>
        <div class="col-md-4">
          <input type="time" class="form-control" name="ssseo_locations[${index}][hours][${rowCount}][close]">
        </div>
      `;
      container.appendChild(row);
    });
  });
});

</script>
</form>
