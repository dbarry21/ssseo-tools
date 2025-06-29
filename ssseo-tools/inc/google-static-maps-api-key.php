<?php
/**
 * Map as Featured
 *
 * Generates a Google Static Map thumbnail based on a service_area post's address
 * when you click the "Generate Map" button in the editor.
 *
 * @package MapAsFeatured
 * @version 3.7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$map_as_featured_marker_color = 'EC8107';

function map_as_featured_customize_register( WP_Customize_Manager $wp_customize ) {
    $wp_customize->add_section( 'map_as_featured_maps_section', array(
        'title'       => __( 'Map as Featured', 'map-as-featured' ),
        'description' => __( 'Configure Google Static Maps API settings.', 'map-as-featured' ),
        'priority'    => 30,
    ) );

    $wp_customize->add_setting( 'google_static_maps_api_key', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ) );

    $wp_customize->add_control( 'google_static_maps_api_key_control', array(
        'label'    => __( 'Static Maps API Key', 'map-as-featured' ),
        'section'  => 'map_as_featured_maps_section',
        'settings' => 'google_static_maps_api_key',
        'type'     => 'text',
    ) );
}
add_action( 'customize_register', 'map_as_featured_customize_register' );

function map_as_featured_get_api_key() {
    return trim( get_theme_mod( 'google_static_maps_api_key', '' ) );
}

function map_as_featured_get_location( $post_id ) {
    return sanitize_text_field( function_exists( 'get_field' ) ? get_field( 'city_state', $post_id ) : get_post_meta( $post_id, 'city_state', true ) );
}

function map_as_featured_get_zoom( $post_id ) {
    $zoom = get_post_meta( $post_id, '_map_as_featured_zoom', true );
    return is_numeric( $zoom ) ? intval( $zoom ) : 13;
}

function map_as_featured_get_static_map_url( $location, $zoom = 14, $post_id = 0 ) {
    global $map_as_featured_marker_color;
    $api_key = map_as_featured_get_api_key();
    if ( ! $api_key ) return false;

    $params = [
        'center'  => $location,
        'zoom'    => $zoom,
        'size'    => '640x640',
        'scale'   => 2,
        'markers' => 'color:0x' . $map_as_featured_marker_color . '|' . $location,
        'key'     => $api_key,
    ];

    return 'https://maps.googleapis.com/maps/api/staticmap?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

add_action( 'add_meta_boxes', function() {
    add_meta_box( 'map_as_featured_metabox', 'Map as Featured', function( $post ) {
        $default_location = map_as_featured_get_location( $post->ID );
        $zoom = map_as_featured_get_zoom( $post->ID );
        $map_url = map_as_featured_get_static_map_url( $default_location, $zoom, $post->ID );
        ?>
        <p><label for="map_as_featured_search">Search for a location:</label><br>
        <input type="text" id="map_as_featured_search" placeholder="Start typing a location..." style="width:100%; margin-bottom:8px;"></p>

        <p><label for="map_as_featured_location">Location (used for map):</label><br>
        <input type="text" name="map_as_featured_location" id="map_as_featured_location" value="<?php echo esc_attr( $default_location ); ?>" style="width:100%;" readonly></p>

        <p><label for="map_as_featured_zoom">Zoom Level (1–20):</label><br>
        <input type="number" name="map_as_featured_zoom" id="map_as_featured_zoom" value="<?php echo esc_attr( $zoom ); ?>" min="1" max="20" style="width:100%;"></p>

        <p><strong>Test Map:</strong><br><a id="map_as_featured_test_link" href="<?php echo esc_url( $map_url ); ?>" target="_blank"><?php echo esc_html( urldecode( $map_url ) ); ?></a></p>

        <p><img id="map_as_featured_preview" src="<?php echo esc_url( $map_url ); ?>" style="max-width:100%; height:auto; border:1px solid #ccc;"></p>

        <button type="button" id="map-as-featured-generate" class="button button-primary">Generate Map</button>
        <span id="map-as-featured-status" style="margin-left:10px;"></span>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const zoomInput = document.getElementById('map_as_featured_zoom');
            const locationInput = document.getElementById('map_as_featured_location');
            const previewImg = document.getElementById('map_as_featured_preview');
            const testLink = document.getElementById('map_as_featured_test_link');
            const generateBtn = document.getElementById('map-as-featured-generate');
            const status = document.getElementById('map-as-featured-status');
            const apiKey = <?php echo json_encode( map_as_featured_get_api_key() ); ?>;
            const markerColor = '<?php echo esc_js( $GLOBALS['map_as_featured_marker_color'] ); ?>';

            function updateMap() {
                const location = encodeURIComponent(locationInput.value);
                const zoom = zoomInput.value;
                const base = 'https://maps.googleapis.com/maps/api/staticmap';
                const params = [
                    'center=' + location,
                    'zoom=' + zoom,
                    'size=640x640',
                    'scale=2',
                    'markers=color:' + markerColor + '|' + location,
                    'key=' + apiKey
                ].join('&');

                const mapUrl = base + '?' + params;
                previewImg.src = mapUrl;
                testLink.href = mapUrl;
                testLink.textContent = decodeURIComponent(mapUrl);
            }

            zoomInput.addEventListener('input', updateMap);
            locationInput.addEventListener('input', updateMap);

            // Load Google Places Autocomplete
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places`;
            script.async = true;
            script.onload = function () {
                const searchInput = document.getElementById('map_as_featured_search');
                const autocomplete = new google.maps.places.Autocomplete(searchInput);
                autocomplete.addListener('place_changed', function () {
                    const place = autocomplete.getPlace();
                    if (place.geometry && place.formatted_address) {
                        locationInput.value = place.formatted_address;
                        updateMap();
                    }
                });
            };
            document.head.appendChild(script);

            generateBtn.addEventListener('click', function () {
                generateBtn.disabled = true;
                status.textContent = 'Generating...';
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'map_as_featured_generate',
                        nonce: '<?php echo wp_create_nonce('map_as_featured_generate'); ?>',
                        post_id: '<?php echo $post->ID; ?>'
                    })
                })
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        status.textContent = 'Done!';
                        location.reload();
                    } else {
                        status.textContent = 'Error: ' + response.data;
                        generateBtn.disabled = false;
                    }
                });
            });
        });
        </script>
        <?php
    }, 'service_area', 'side' );
});

add_action( 'save_post_service_area', function( $post_id ) {
    if ( isset( $_POST['map_as_featured_zoom'] ) ) {
        update_post_meta( $post_id, '_map_as_featured_zoom', intval( $_POST['map_as_featured_zoom'] ) );
    }
    if ( isset( $_POST['map_as_featured_location'] ) ) {
        update_post_meta( $post_id, 'city_state', sanitize_text_field( $_POST['map_as_featured_location'] ) );
    }
});

add_action( 'wp_ajax_map_as_featured_generate', function() {
    check_ajax_referer( 'map_as_featured_generate', 'nonce' );
    $post_id = intval( $_POST['post_id'] );
    if ( ! in_array( get_post_type( $post_id ), [ 'service_area', 'service' ], true ) ) wp_send_json_error( 'Invalid post type' );

    $location = map_as_featured_get_location( $post_id );
    $zoom     = map_as_featured_get_zoom( $post_id );
    $map_url  = map_as_featured_get_static_map_url( $location, $zoom, $post_id );

    $response = wp_remote_get( $map_url );
    if ( is_wp_error( $response ) ) wp_send_json_error( 'Failed to retrieve map image' );

    $image_data = wp_remote_retrieve_body( $response );
    $mime_type  = wp_remote_retrieve_header( $response, 'content-type' );
    $ext        = str_replace( 'image/', '', $mime_type ) ?: 'png';
    $filename   = 'map-' . $post_id . '.' . $ext;

    $upload = wp_upload_bits( $filename, null, $image_data );
    if ( $upload['error'] ) wp_send_json_error( $upload['error'] );

    $site_title = get_bloginfo( 'name' );
    $city_state = map_as_featured_get_location( $post_id );
    $alt_text   = $site_title . ' in ' . $city_state;
    $img_title  = $city_state . ' ' . $site_title;

    $attachment = [
        'guid'           => $upload['url'],
        'post_mime_type' => $mime_type,
        'post_title'     => $img_title,
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];
    $attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
    wp_update_attachment_metadata( $attach_id, $attach_data );
    update_post_meta( $attach_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
    set_post_thumbnail( $post_id, $attach_id );

    wp_send_json_success();
});
