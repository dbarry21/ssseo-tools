<?php
/**
 * Module: Map as Featured Image
 *
 * Moves “Map as Featured” logic into SSSEO Tools.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ------------------------------
// 1) HELPER: Read API key & toggle
// ------------------------------
function ssseo_mapasfeatured_is_enabled() {
    return '1' === get_option( 'ssseo_enable_map_as_featured', '0' );
}

function ssseo_mapasfeatured_get_api_key() {
    return trim( get_option( 'ssseo_google_static_maps_api_key', '' ) );
}

// ------------------------------
// 2) HELPER: Get location & zoom
// ------------------------------
function ssseo_mapasfeatured_get_location( $post_id ) {
    // First try ACF
    if ( function_exists( 'get_field' ) ) {
        $geo = get_field( 'geo_coordinates', $post_id );
        if ( is_array( $geo ) 
             && ! empty( $geo['latitude'] ) 
             && ! empty( $geo['longitude'] ) ) 
        {
            return sanitize_text_field( $geo['latitude'] . ',' . $geo['longitude'] );
        }

        // Fallback to city_state ACF field
        return sanitize_text_field( get_field( 'city_state', $post_id ) ?: '' );
    }

    // If no ACF, fall back to raw post meta
    $meta = get_post_meta( $post_id, 'geo_coordinates', true );
    if ( is_array( $meta ) 
         && ! empty( $meta['latitude'] ) 
         && ! empty( $meta['longitude'] ) ) 
    {
        return sanitize_text_field( $meta['latitude'] . ',' . $meta['longitude'] );
    }

    return sanitize_text_field( get_post_meta( $post_id, 'city_state', true ) ?: '' );
}

function ssseo_mapasfeatured_get_zoom( $post_id ) {
    $zoom = get_post_meta( $post_id, '_ssseo_map_zoom', true );
    return is_numeric( $zoom ) ? intval( $zoom ) : 14;
}

// ------------------------------
// 3) HELPER: Build Static Map URL
// ------------------------------
function ssseo_mapasfeatured_get_static_map_url( $location, $zoom = 14 ) {
    $api_key = ssseo_mapasfeatured_get_api_key();
    if ( ! $api_key || ! $location ) {
        return false;
    }

    $marker_color = 'EC8107'; // hex, no “#”
    $params = [
        'center'  => $location,
        'zoom'    => $zoom,
        'size'    => '640x640',
        'scale'   => 2,
        'markers' => 'color:0x' . $marker_color . '|' . $location,
        'key'     => $api_key,
    ];

    return 'https://maps.googleapis.com/maps/api/staticmap?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
}

// ------------------------------
// 4) METABOX: “Map as Featured” on service_area
// ------------------------------
add_action( 'add_meta_boxes', function() {
    if ( ! ssseo_mapasfeatured_is_enabled() ) {
        return;
    }

    add_meta_box(
        'ssseo_mapasfeatured_metabox',
        __( 'Map as Featured', 'ssseo' ),
        function( $post ) {
            $location = ssseo_mapasfeatured_get_location( $post->ID );
            $zoom     = ssseo_mapasfeatured_get_zoom( $post->ID );
            $map_url  = ssseo_mapasfeatured_get_static_map_url( $location, $zoom );

            // Nonce for AJAX
            $ajax_nonce = wp_create_nonce( 'ssseo_mapasfeatured_generate' );
            ?>
            <p>
                <label for="ssseo_mapasfeatured_zoom"><?php esc_html_e( 'Zoom Level (1–20):', 'ssseo' ); ?></label><br>
                <input
                    type="number"
                    name="ssseo_mapasfeatured_zoom"
                    id="ssseo_mapasfeatured_zoom"
                    value="<?php echo esc_attr( $zoom ); ?>"
                    min="1" max="20"
                    style="width:100%;"
                >
            </p>

            <p>
                <strong><?php esc_html_e( 'Preview Map:', 'ssseo' ); ?></strong><br>
                <?php if ( $map_url ) : ?>
                    <a href="<?php echo esc_url( $map_url ); ?>" target="_blank">
                        <?php echo esc_html( urldecode( $map_url ) ); ?>
                    </a>
                <?php else : ?>
                    <em><?php esc_html_e( 'No API key or no location available.', 'ssseo' ); ?></em>
                <?php endif; ?>
            </p>

            <?php if ( $map_url ) : ?>
                <p>
                    <img
                        id="ssseo_mapasfeatured_preview"
                        src="<?php echo esc_url( $map_url ); ?>"
                        style="max-width:100%; height:auto; border:1px solid #ccc;"
                        alt="<?php esc_attr_e( 'Map preview', 'ssseo' ); ?>"
                    >
                </p>
                <button
                    type="button"
                    id="ssseo-mapasfeatured-generate"
                    class="button button-primary"
                >
                    <?php esc_html_e( 'Generate Map', 'ssseo' ); ?>
                </button>
                <span id="ssseo-mapasfeatured-status" style="margin-left:10px;"></span>

                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const zoomInput   = document.getElementById('ssseo_mapasfeatured_zoom');
                    const previewImg  = document.getElementById('ssseo_mapasfeatured_preview');
                    const baseUrl     = <?php echo json_encode( $map_url ); ?>;
                    const btn         = document.getElementById('ssseo-mapasfeatured-generate');
                    const statusSpan  = document.getElementById('ssseo-mapasfeatured-status');

                    // Update preview when zoom changes
                    zoomInput.addEventListener('input', function () {
                        const newUrl = baseUrl.replace( /zoom=\d+/, 'zoom=' + zoomInput.value );
                        previewImg.src = newUrl;
                    });

                    // AJAX request on “Generate Map”
                    btn.addEventListener('click', function () {
                        btn.disabled = true;
                        statusSpan.textContent = '<?php echo esc_js( __( 'Generating…', 'ssseo' ) ); ?>';

                        fetch( ajaxurl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                action: 'ssseo_mapasfeatured_generate',
                                nonce: '<?php echo esc_js( $ajax_nonce ); ?>',
                                post_id: '<?php echo esc_js( $post->ID ); ?>'
                            })
                        })
                        .then( res => res.json() )
                        .then( response => {
                            if ( response.success ) {
                                statusSpan.textContent = '<?php echo esc_js( __( 'Done!', 'ssseo' ) ); ?>';
                                location.reload();
                            } else {
                                statusSpan.textContent = '<?php echo esc_js( __( 'Error:', 'ssseo' ) ); ?> ' + response.data;
                                btn.disabled = false;
                            }
                        })
                        .catch( () => {
                            statusSpan.textContent = '<?php echo esc_js( __( 'Request failed.', 'ssseo' ) ); ?>';
                            btn.disabled = false;
                        });
                    });
                });
                </script>
            <?php endif;
        },
        'service_area',   // only on service_area CPT
        'side',
        'high'
    );
} );

// ------------------------------
// 5) SAVE ZOOM VALUE
// ------------------------------
add_action( 'save_post_service_area', function( $post_id ) {
    if ( isset( $_POST['ssseo_mapasfeatured_zoom'] ) ) {
        $zoom = intval( $_POST['ssseo_mapasfeatured_zoom'] );
        update_post_meta( $post_id, '_ssseo_map_zoom', $zoom );
    }
} );

// ------------------------------
// 6) AJAX: Generate featured image
// ------------------------------
add_action( 'wp_ajax_ssseo_mapasfeatured_generate', function() {
    check_ajax_referer( 'ssseo_mapasfeatured_generate', 'nonce' );

    $post_id = intval( $_POST['post_id'] );
    if ( get_post_type( $post_id ) !== 'service_area' ) {
        wp_send_json_error( __( 'Invalid post type', 'ssseo' ) );
    }

    $location = ssseo_mapasfeatured_get_location( $post_id );
    $zoom     = ssseo_mapasfeatured_get_zoom( $post_id );
    $map_url  = ssseo_mapasfeatured_get_static_map_url( $location, $zoom );

    if ( ! $map_url ) {
        wp_send_json_error( __( 'API key missing or no location.', 'ssseo' ) );
    }

    $response = wp_remote_get( $map_url );
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( __( 'Failed to retrieve map image', 'ssseo' ) );
    }

    $image_data = wp_remote_retrieve_body( $response );
    $mime_type  = wp_remote_retrieve_header( $response, 'content-type' );
    $ext        = str_replace( 'image/', '', $mime_type ) ?: 'png';
    $filename   = 'map-' . $post_id . '.' . $ext;

    $upload = wp_upload_bits( $filename, null, $image_data );
    if ( $upload['error'] ) {
        wp_send_json_error( $upload['error'] );
    }

    $attachment = [
        'guid'           => $upload['url'],
        'post_mime_type' => $mime_type,
        'post_title'     => sanitize_text_field( 'Map for Service Area ' . $post_id ),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];
    $attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
    wp_update_attachment_metadata( $attach_id, $attach_data );
    set_post_thumbnail( $post_id, $attach_id );

    wp_send_json_success();
} );
