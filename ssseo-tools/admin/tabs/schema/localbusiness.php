<?php
/**
 * Admin UI: Local Business Schema – Tabbed Locations Interface
 *
 * Displays and saves multiple “Location” entries, each with:
 * - Business Name
 * - Phone
 * - Price Range
 * - Street, City, State, ZIP, Country
 * - Latitude, Longitude
 * - Opening Hours (repeater)
 * - Assigned Pages (multi-select)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SAVE HANDLER
 */
if ( isset( $_POST['ssseo_localbusiness_nonce'] ) 
     && wp_verify_nonce( $_POST['ssseo_localbusiness_nonce'], 'ssseo_localbusiness_save' ) ) {

    $raw_locations    = $_POST['ssseo_locations'] ?? [];
    $clean_locations  = [];

    if ( is_array( $raw_locations ) ) {
        foreach ( $raw_locations as $loc ) {
            // Sanitize basic string fields
            $clean = [
                'name'    => sanitize_text_field( $loc['name']    ?? '' ),
                'phone'   => sanitize_text_field( $loc['phone']   ?? '' ),
                'price'   => sanitize_text_field( $loc['price']   ?? '' ),
                'street'  => sanitize_text_field( $loc['street']  ?? '' ),
                'city'    => sanitize_text_field( $loc['city']    ?? '' ),
                'state'   => sanitize_text_field( $loc['state']   ?? '' ),
                'zip'     => sanitize_text_field( $loc['zip']     ?? '' ),
                'country' => sanitize_text_field( $loc['country'] ?? '' ),
                'lat'     => sanitize_text_field( $loc['lat']     ?? '' ),
                'lng'     => sanitize_text_field( $loc['lng']     ?? '' ),
            ];

            // Sanitize opening hours repeater
            $clean_hours = [];
            if ( isset( $loc['hours'] ) && is_array( $loc['hours'] ) ) {
                foreach ( $loc['hours'] as $hour_row ) {
                    $day   = sanitize_text_field( $hour_row['day']   ?? '' );
                    $open  = sanitize_text_field( $hour_row['open']  ?? '' );
                    $close = sanitize_text_field( $hour_row['close'] ?? '' );
                    if ( $day || $open || $close ) {
                        $clean_hours[] = [
                            'day'   => $day,
                            'open'  => $open,
                            'close' => $close,
                        ];
                    }
                }
            }
            $clean['hours'] = $clean_hours;

            // Sanitize assigned pages
            $clean_pages = [];
            if ( isset( $loc['pages'] ) && is_array( $loc['pages'] ) ) {
                foreach ( $loc['pages'] as $pid ) {
                    $clean_pages[] = absint( $pid );
                }
            }
            $clean['pages'] = $clean_pages;

            $clean_locations[] = $clean;
        }
    }

    update_option( 'ssseo_localbusiness_locations', $clean_locations );
    echo '<div class="notice notice-success is-dismissible"><p>Local Business locations saved.</p></div>';
}

// -----------------------------------------------------------------------------
// LOAD CURRENT LOCATIONS
// -----------------------------------------------------------------------------
$locations = get_option( 'ssseo_localbusiness_locations', [] );
if ( ! is_array( $locations ) ) {
    $locations = [];
}
if ( empty( $locations ) ) {
    $locations[] = [];
}

// Defaults inherited from Organization settings
$org_defaults = [
    'name'    => get_option( 'ssseo_organization_name', '' ),
    'phone'   => get_option( 'ssseo_organization_phone', '' ),
    'price'   => '', 
    'lat'     => get_option( 'ssseo_organization_latitude', '' ),
    'lng'     => get_option( 'ssseo_organization_longitude', '' ),
    'street'  => get_option( 'ssseo_organization_address', '' ),
    'city'    => get_option( 'ssseo_organization_locality', '' ),
    'state'   => get_option( 'ssseo_organization_state', '' ),
    'zip'     => get_option( 'ssseo_organization_postal_code', '' ),
    'country' => get_option( 'ssseo_organization_country', '' ),
];
$selectable_pages = get_posts([
    'post_type'   => ['page', 'service_area'],
    'post_status' => 'publish',
    'orderby'     => 'title',
    'order'       => 'ASC',
    'numberposts' => -1
]);
?>

<form method="post">
    <?php wp_nonce_field( 'ssseo_localbusiness_save', 'ssseo_localbusiness_nonce' ); ?>

    <ul class="nav nav-tabs" id="ssseo-location-tabs" role="tablist">
        <?php foreach ( $locations as $i => $loc ) : ?>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link <?php echo $i === 0 ? 'active' : ''; ?>"
                    id="location-tab-<?php echo $i; ?>"
                    data-bs-toggle="tab"
                    data-bs-target="#location-<?php echo $i; ?>"
                    type="button"
                    role="tab"
                    aria-controls="location-<?php echo $i; ?>"
                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                >Location #<?php echo $i + 1; ?></button>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content pt-3" id="ssseo-location-tab-content">
        <?php foreach ( $locations as $i => $loc ) :
            // Merge defaults for any missing keys
            foreach ( $org_defaults as $key => $val ) {
                if ( empty( $loc[ $key ] ) ) {
                    $loc[ $key ] = $val;
                }
            }
            $prefix = "ssseo_locations[$i]";
        ?>
            <div
                class="tab-pane fade <?php echo $i === 0 ? 'show active' : ''; ?>"
                id="location-<?php echo $i; ?>"
                role="tabpanel"
                aria-labelledby="location-tab-<?php echo $i; ?>"
            >
                <table class="form-table">
                    <!-- Business Name -->
                    <tr>
                        <th><label>Business Name</label></th>
                        <td>
                            <input
                                type="text"
                                name="<?php echo $prefix; ?>[name]"
                                value="<?php echo esc_attr( $loc['name'] ?? '' ); ?>"
                                class="regular-text"
                            >
                        </td>
                    </tr>

                    <!-- Phone -->
                    <tr>
                        <th><label>Phone</label></th>
                        <td>
                            <input
                                type="text"
                                name="<?php echo $prefix; ?>[phone]"
                                value="<?php echo esc_attr( $loc['phone'] ?? '' ); ?>"
                                class="regular-text"
                            >
                        </td>
                    </tr>

                    <!-- Price Range -->
                    <tr>
                        <th><label>Price Range</label></th>
                        <td>
                            <input
                                type="text"
                                name="<?php echo $prefix; ?>[price]"
                                value="<?php echo esc_attr( $loc['price'] ?? '' ); ?>"
                                class="regular-text"
                                placeholder="e.g. $20–$50"
                            >
                            <p class="description">Enter a price range (e.g. “$20–$50”).</p>
                        </td>
                    </tr>

                    <!-- Street -->
                    <tr>
                        <th><label>Street</label></th>
                        <td>
                            <input
                                type="text"
                                name="<?php echo $prefix; ?>[street]"
                                value="<?php echo esc_attr( $loc['street'] ?? '' ); ?>"
                                class="regular-text"
                            >
                        </td>
                    </tr>

                    <!-- City -->
                    <tr>
                        <th><label>City</label></th>
                        <td>
                            <input
                                type="text"
                                name="<?php echo $prefix; ?>[city]"
                                value="<?php echo esc_attr( $loc['city'] ?? '' ); ?>"
                                class="regular-text"
                            >
                        </td>
                    </tr>

                    <!-- State -->
                    <tr>
                        <th><label>State</label></th>
                        <td>
                            <input
                                type="text"
                                name="<?php echo $prefix; ?>[state]"
                                value="<?php echo esc_attr( $loc['state'] ?? '' ); ?>"
                                class="regular-text"
                            >
                        </td>
                    </tr>

                    <!-- ZIP -->
                    <tr>
                        <th><label>ZIP</label></th>
                        <td>
                            <input
                                type="text"
                                name="<?php echo $prefix; ?>[zip]"
                                value="<?php echo esc_attr( $loc['zip'] ?? '' ); ?>"
                                class="regular-text"
                            >
                        </td>
                    </tr>

                    <!-- Country -->
                    <tr>
                        <th><label>Country</label></th>
                        <td>
                            <input
                                type="text"
                                name="<?php echo $prefix; ?>[country]"
                                value="<?php echo esc_attr( $loc['country'] ?? '' ); ?>"
                                class="regular-text"
                            >
                        </td>
                    </tr>

                    <!-- Latitude -->
                    <tr>
                        <th><label>Latitude</label></th>
                        <td>
                            <input
                                type="text"
                                name="<?php echo $prefix; ?>[lat]"
                                value="<?php echo esc_attr( $loc['lat'] ?? '' ); ?>"
                                class="regular-text"
                            >
                        </td>
                    </tr>

                    <!-- Longitude -->
                    <tr>
                        <th><label>Longitude</label></th>
                        <td>
                            <input
                                type="text"
                                name="<?php echo $prefix; ?>[lng]"
                                value="<?php echo esc_attr( $loc['lng'] ?? '' ); ?>"
                                class="regular-text"
                            >
                        </td>
                    </tr>

                    <!-- Opening Hours -->
                    <tr>
                        <th><label>Opening Hours</label></th>
                        <td>
                            <div class="ssseo-opening-hours-container" data-index="<?php echo $i; ?>">
                                <?php
                                $hours = $loc['hours'] ?? [];
                                if ( empty( $hours ) ) {
                                    $hours[] = [];
                                }
                                foreach ( $hours as $j => $hour_row ) :
                                ?>
                                    <div class="ssseo-opening-row" style="margin-bottom:10px;">
                                        <select name="<?php echo $prefix; ?>[hours][<?php echo $j; ?>][day]">
                                            <option value="">-- Day --</option>
                                            <?php foreach ( [ "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday" ] as $day ) : ?>
                                                <option value="<?php echo esc_attr( $day ); ?>" <?php selected( $hour_row['day'] ?? '', $day ); ?>>
                                                    <?php echo esc_html( $day ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input
                                            type="time"
                                            name="<?php echo $prefix; ?>[hours][<?php echo $j; ?>][open]"
                                            value="<?php echo esc_attr( $hour_row['open'] ?? '' ); ?>"
                                        > to
                                        <input
                                            type="time"
                                            name="<?php echo $prefix; ?>[hours][<?php echo $j; ?>][close]"
                                            value="<?php echo esc_attr( $hour_row['close'] ?? '' ); ?>"
                                        >
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button
                                type="button"
                                class="button ssseo-add-hours"
                                data-index="<?php echo $i; ?>"
                            >+ Add another</button>
                        </td>
                    </tr>

                  <!-- Assign to Pages/Service Areas -->
<tr>
    <th><label>Assign to Pages/Service Areas</label></th>
    <td>
        <input type="text" placeholder="Search..." class="ssseo-page-filter" style="margin-bottom: 8px; width: 100%; padding: 6px;">
        <?php
        $selected_pages = $loc['pages'] ?? [];
        $items = get_posts( [
            'post_type'   => [ 'page', 'service_area' ],
            'post_status' => 'publish',
            'orderby'     => 'title',
            'order'       => 'ASC',
            'numberposts' => -1,
        ] );

        echo '<select class="ssseo-page-select" name="' . $prefix . '[pages][]" multiple size="5" style="width:100%;">';
        foreach ( $items as $item ) {
            $sel = in_array( $item->ID, (array) $selected_pages, true ) ? 'selected' : '';
            $label = get_post_type( $item->ID ) === 'service_area' ? 'Service Area: ' : '';
            echo '<option value="' . esc_attr( $item->ID ) . '" ' . $sel . '>' . esc_html( $label . $item->post_title ) . '</option>';
        }
        echo '</select>';
        ?>
        <p class="description">Search or hold Ctrl (Windows) or Cmd (Mac) to select multiple items.</p>
    </td>
</tr>


                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <p style="margin-top:20px;">
        <input type="submit" class="button button-primary" value="Save Locations">
        <button type="button" class="button" id="ssseo-add-location-tab">+ Add Location</button>
    </p>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Add new “Opening Hours” row for a given location tab
    document.querySelectorAll('.ssseo-add-hours').forEach(function(button) {
        button.addEventListener('click', function() {
            var index    = this.dataset.index;
            var container = document.querySelector('.ssseo-opening-hours-container[data-index="' + index + '"]');
            var count     = container.children.length;
            var days      = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

            var div = document.createElement('div');
            div.className = 'ssseo-opening-row';
            div.style.marginBottom = '10px';

            var inner = '<select name="ssseo_locations[' + index + '][hours][' + count + '][day]">';
            inner    += '<option value="">-- Day --</option>';
            days.forEach(function(d) {
                inner += '<option value="' + d + '">' + d + '</option>';
            });
            inner += '</select>';
            inner += '<input type="time" name="ssseo_locations[' + index + '][hours][' + count + '][open]"> to ';
            inner += '<input type="time" name="ssseo_locations[' + index + '][hours][' + count + '][close]">';
            div.innerHTML = inner;
            container.appendChild(div);
        });
    });

    // Add a new Location tab and panel
    document.getElementById('ssseo-add-location-tab').addEventListener('click', function() {
        var tabsContainer   = document.getElementById('ssseo-location-tabs');
        var panelsContainer = document.getElementById('ssseo-location-tab-content');

        var currentCount = tabsContainer.querySelectorAll('.nav-link').length;
        var newIndex     = currentCount;
        var newTabId     = 'location-tab-' + newIndex;
        var newPanelId   = 'location-' + newIndex;

        // Create new tab button
        var li = document.createElement('li');
        li.className = 'nav-item';
        li.setAttribute('role', 'presentation');

        var btn = document.createElement('button');
        btn.className     = 'nav-link';
        btn.id            = newTabId;
        btn.setAttribute('data-bs-toggle', 'tab');
        btn.setAttribute('data-bs-target', '#' + newPanelId);
        btn.type          = 'button';
        btn.setAttribute('role', 'tab');
        btn.setAttribute('aria-controls', newPanelId);
        btn.setAttribute('aria-selected', 'false');
        btn.textContent   = 'Location #' + (newIndex + 1);

        li.appendChild(btn);
        tabsContainer.appendChild(li);

        // Create new panel
        var panel = document.createElement('div');
        panel.className = 'tab-pane fade';
        panel.id        = newPanelId;
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', newTabId);

        // Build HTML for the new panel, including price field
        var html  = '<table class="form-table">';
        html += '<tr><th><label>Business Name</label></th><td><input type="text" name="ssseo_locations[' + newIndex + '][name]" value="" class="regular-text"></td></tr>';
        html += '<tr><th><label>Phone</label></th><td><input type="text" name="ssseo_locations[' + newIndex + '][phone]" value="" class="regular-text"></td></tr>';
        html += '<tr><th><label>Price Range</label></th><td><input type="text" name="ssseo_locations[' + newIndex + '][price]" value="" class="regular-text" placeholder="e.g. $20–$50"></td></tr>';
        html += '<tr><th><label>Street</label></th><td><input type="text" name="ssseo_locations[' + newIndex + '][street]" value="" class="regular-text"></td></tr>';
        html += '<tr><th><label>City</label></th><td><input type="text" name="ssseo_locations[' + newIndex + '][city]" value="" class="regular-text"></td></tr>';
        html += '<tr><th><label>State</label></th><td><input type="text" name="ssseo_locations[' + newIndex + '][state]" value="" class="regular-text"></td></tr>';
        html += '<tr><th><label>ZIP</label></th><td><input type="text" name="ssseo_locations[' + newIndex + '][zip]" value="" class="regular-text"></td></tr>';
        html += '<tr><th><label>Country</label></th><td><input type="text" name="ssseo_locations[' + newIndex + '][country]" value="" class="regular-text"></td></tr>';
        html += '<tr><th><label>Latitude</label></th><td><input type="text" name="ssseo_locations[' + newIndex + '][lat]" value="" class="regular-text"></td></tr>';
        html += '<tr><th><label>Longitude</label></th><td><input type="text" name="ssseo_locations[' + newIndex + '][lng]" value="" class="regular-text"></td></tr>';
        html += '<tr><th><label>Opening Hours</label></th><td>';
        html += '<div class="ssseo-opening-hours-container" data-index="' + newIndex + '">';
        html += '<div class="ssseo-opening-row" style="margin-bottom:10px;">';
        html += '<select name="ssseo_locations[' + newIndex + '][hours][0][day]">';
        html += '<option value="">-- Day --</option>';
        ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'].forEach(function(d) {
            html += '<option value="' + d + '">' + d + '</option>';
        });
        html += '</select>';
        html += '<input type="time" name="ssseo_locations[' + newIndex + '][hours][0][open]"> to ';
        html += '<input type="time" name="ssseo_locations[' + newIndex + '][hours][0][close]">';
        html += '</div>';
        html += '</div>';
        html += '<button type="button" class="button ssseo-add-hours" data-index="' + newIndex + '">+ Add another</button>';
        html += '</td></tr>';
        html += '<tr><th><label>Assign to Pages</label></th><td>';
        html += '<select name="ssseo_locations[' + newIndex + '][pages][]" multiple size="5" style="width:100%;">';
        <?php
        // Pre-generate options for pages so JS can inject them
        $pages_js = '';
        $all_pages = get_pages( [ 'sort_column' => 'post_title', 'sort_order' => 'asc' ] );
        foreach ( $all_pages as $page ) {
            $pages_js .= '<option value="' . esc_js( $page->ID ) . '">' . esc_js( $page->post_title ) . '</option>';
        }
        echo "html += `" . $pages_js . "`;\n";
        ?>
        html += '</select>';
        html += '<p class="description">Hold Ctrl (Windows) or Cmd (Mac) to select multiple pages.</p>';
        html += '</td></tr>';
        html += '</table>';

        panel.innerHTML = html;
        panelsContainer.appendChild(panel);

        // Show the new tab
        var tabTrigger = new bootstrap.Tab(btn);
        tabTrigger.show();

        // Re-bind “Add Hours” for the new index
        btn.addEventListener('shown.bs.tab', function () {
            var newAddHoursBtn = panel.querySelector('.ssseo-add-hours');
            if ( newAddHoursBtn ) {
                newAddHoursBtn.addEventListener('click', function() {
                    var idx       = this.dataset.index;
                    var cont      = document.querySelector('.ssseo-opening-hours-container[data-index="' + idx + '"]');
                    var cnt       = cont.children.length;
                    var dayList   = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                    var divRow    = document.createElement('div');
                    divRow.className = 'ssseo-opening-row';
                    divRow.style.marginBottom = '10px';

                    var inner = '<select name="ssseo_locations[' + idx + '][hours][' + cnt + '][day]">';
                    inner    += '<option value="">-- Day --</option>';
                    dayList.forEach(function(d) {
                        inner += '<option value="' + d + '">' + d + '</option>';
                    });
                    inner += '</select>';
                    inner += '<input type="time" name="ssseo_locations[' + idx + '][hours][' + cnt + '][open]"> to ';
                    inner += '<input type="time" name="ssseo_locations[' + idx + '][hours][' + cnt + '][close]">';
                    divRow.innerHTML = inner;
                    cont.appendChild(divRow);
                });
            }
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.ssseo-page-filter').forEach(function(input) {
    input.addEventListener('input', function() {
      const term = this.value.toLowerCase();
      const select = this.nextElementSibling;

      if (!select || !select.options) return;

      Array.from(select.options).forEach(function(option) {
        option.style.display = option.text.toLowerCase().includes(term) ? '' : 'none';
      });
    });
  });
});
</script>
