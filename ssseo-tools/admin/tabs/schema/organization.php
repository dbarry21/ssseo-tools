<?php
/**
 * Admin Page: Organization Schema Settings
 *
 * Refactored using Bootstrap and wide field layout.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Static list of example Service Types (schema.org compliant subset)
$default_service_types = [
    '',
    'Plumber',
    'Electrician',
    'HVACBusiness',
    'RoofingContractor',
    'PestControl',
    'LegalService',
    'CleaningService',
    'AutoRepair',
    'MedicalBusiness',
    'Locksmith',
    'MovingCompany',
    'RealEstateAgent',
    'ITService',
];

// Save handler
if (
    isset( $_POST['ssseo_org_schema_nonce'] ) &&
    wp_verify_nonce( $_POST['ssseo_org_schema_nonce'], 'ssseo_org_schema_save' )
) {
    update_option( 'ssseo_organization_name', sanitize_text_field( $_POST['ssseo_organization_name'] ?? '' ) );
    update_option( 'ssseo_organization_url', esc_url_raw( $_POST['ssseo_organization_url'] ?? '' ) );
    update_option( 'ssseo_organization_phone', sanitize_text_field( $_POST['ssseo_organization_phone'] ?? '' ) );
    update_option( 'ssseo_organization_address', sanitize_text_field( $_POST['ssseo_organization_address'] ?? '' ) );
    update_option( 'ssseo_organization_locality', sanitize_text_field( $_POST['ssseo_organization_locality'] ?? '' ) );
    update_option( 'ssseo_organization_state', sanitize_text_field( $_POST['ssseo_organization_state'] ?? '' ) );
    update_option( 'ssseo_organization_postal_code', sanitize_text_field( $_POST['ssseo_organization_postal_code'] ?? '' ) );
    update_option( 'ssseo_organization_country', sanitize_text_field( $_POST['ssseo_organization_country'] ?? '' ) );
    update_option( 'ssseo_organization_description', sanitize_textarea_field( $_POST['ssseo_organization_description'] ?? '' ) );
    update_option( 'ssseo_organization_email', sanitize_email( $_POST['ssseo_organization_email'] ?? '' ) );
    update_option( 'ssseo_organization_areas_served', sanitize_textarea_field( $_POST['ssseo_organization_areas_served'] ?? '' ) );
    update_option( 'ssseo_organization_latitude', sanitize_text_field( $_POST['ssseo_organization_latitude'] ?? '' ) );
    update_option( 'ssseo_organization_longitude', sanitize_text_field( $_POST['ssseo_organization_longitude'] ?? '' ) );
    update_option( 'ssseo_organization_logo', absint( $_POST['ssseo_organization_logo'] ?? 0 ) );
    update_option( 'ssseo_default_service_label', sanitize_text_field( $_POST['ssseo_default_service_label'] ?? '' ) );

    $raw_social = $_POST['ssseo_organization_social_profiles'] ?? [];
    update_option( 'ssseo_organization_social_profiles', is_array( $raw_social ) ? array_filter( array_map( 'esc_url_raw', $raw_social ) ) : [] );

    $raw_pages = $_POST['ssseo_organization_schema_pages'] ?? [];
    update_option( 'ssseo_organization_schema_pages', is_array( $raw_pages ) ? array_map( 'intval', $raw_pages ) : [] );

    echo '<div class="notice notice-success is-dismissible"><p>Organization schema settings saved.</p></div>';
}

$org_fields = [
    'name'        => get_option( 'ssseo_organization_name', '' ),
    'url'         => get_option( 'ssseo_organization_url', '' ),
    'phone'       => get_option( 'ssseo_organization_phone', '' ),
    'address'     => get_option( 'ssseo_organization_address', '' ),
    'locality'    => get_option( 'ssseo_organization_locality', '' ),
    'state'       => get_option( 'ssseo_organization_state', '' ),
    'postal_code' => get_option( 'ssseo_organization_postal_code', '' ),
    'country'     => get_option( 'ssseo_organization_country', '' ),
    'description' => get_option( 'ssseo_organization_description', '' ),
    'email'       => get_option( 'ssseo_organization_email', '' ),
    'areas_served'=> get_option( 'ssseo_organization_areas_served', '' ),
    'latitude'    => get_option( 'ssseo_organization_latitude', '' ),
    'longitude'   => get_option( 'ssseo_organization_longitude', '' ),
    'default_service_label' => get_option( 'ssseo_default_service_label', '' ),
];

$logo_id   = get_option( 'ssseo_organization_logo', 0 );
$logo_url  = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
$socials   = (array) get_option( 'ssseo_organization_social_profiles', [] );
$pages     = (array) get_option( 'ssseo_organization_schema_pages', [] );
$all_pages = get_pages( [ 'sort_column' => 'post_title', 'sort_order' => 'asc' ] );
$states    = [ '', 'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY' ];
$countries = [ '', 'US','CA','MX','UK','BR','AR','CL','CO','FR','DE','IN','AU' ];

include __DIR__ . '/partials/organization-form-bootstrap.php';
