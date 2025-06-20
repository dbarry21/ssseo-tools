<?php
/**
 * Admin UI: Enable CPT Tab (Service, Service Area, Product)
 * Version: 1.1
 */

// Security check to prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ------------
// Handle form submission (run before fetching stored settings)
// ------------
if (
    isset( $_POST['ssseo_cpt_schema_nonce'] ) &&
    wp_verify_nonce( $_POST['ssseo_cpt_schema_nonce'], 'ssseo_cpt_schema_save' ) &&
    current_user_can( 'manage_options' )
) {
    $service_val      = isset( $_POST['ssseo_enable_service_cpt'] ) ? '1' : '0';
    $service_area_val = isset( $_POST['ssseo_enable_service_area_cpt'] ) ? '1' : '0';
    $product_val      = isset( $_POST['ssseo_enable_product_cpt'] ) ? '1' : '0';

    update_option( 'ssseo_enable_service_cpt', $service_val );
    update_option( 'ssseo_enable_service_area_cpt', $service_area_val );
    update_option( 'ssseo_enable_product_cpt', $product_val );

    do_action( 'ssseo_cpt_settings_updated', $service_val, $service_area_val, $product_val );

    echo '<div class="updated"><p>Settings saved.</p></div>';
}

// ------------
// Retrieve stored settings
// ------------
$enable_service      = get_option( 'ssseo_enable_service_cpt', '1' );
$enable_service_area = get_option( 'ssseo_enable_service_area_cpt', '1' );
$enable_product      = get_option( 'ssseo_enable_product_cpt', '1' );

// Render settings form
?>
<form method="post">
    <?php wp_nonce_field( 'ssseo_cpt_schema_save', 'ssseo_cpt_schema_nonce' ); ?>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="ssseo_enable_service_cpt">Enable "Service" CPT</label>
            </th>
            <td>
                <input
                    type="checkbox"
                    name="ssseo_enable_service_cpt"
                    id="ssseo_enable_service_cpt"
                    value="1"
                    <?php checked( '1', $enable_service ); ?>
                >
                <p class="description">If enabled, the "Service" custom post type will be available.</p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="ssseo_enable_service_area_cpt">Enable "Service Area" CPT</label>
            </th>
            <td>
                <input
                    type="checkbox"
                    name="ssseo_enable_service_area_cpt"
                    id="ssseo_enable_service_area_cpt"
                    value="1"
                    <?php checked( '1', $enable_service_area ); ?>
                >
                <p class="description">If enabled, the "Service Area" custom post type will be available.</p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="ssseo_enable_product_cpt">Enable "Product" CPT</label>
            </th>
            <td>
                <input
                    type="checkbox"
                    name="ssseo_enable_product_cpt"
                    id="ssseo_enable_product_cpt"
                    value="1"
                    <?php checked( '1', $enable_product ); ?>
                >
                <p class="description">If enabled, the "Product" custom post type will be available.</p>
            </td>
        </tr>
    </table>

    <p><input type="submit" class="button button-primary" value="Save Settings"></p>
</form>
