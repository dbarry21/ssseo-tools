<?php
/**
 * Service Area Schema Builder
 * Populates JSON-LD schema for single 'service_area' post type
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( get_post_type() !== 'service_area' ) return;

$post_id = get_queried_object_id();

// Retrieve organization details from plugin options
$org_name     = get_option( 'ssseo_organization_name', get_bloginfo( 'name' ) );
$org_url      = get_option( 'ssseo_organization_url', home_url() );
$org_phone    = get_option( 'ssseo_organization_phone', '' );
$org_logo     = get_option( 'ssseo_organization_logo', '' );
$org_lat      = get_option( 'ssseo_organization_latitude', '' );
$org_lng      = get_option( 'ssseo_organization_longitude', '' );
$default_service_label = get_option( 'ssseo_default_service_label', '' );

$org_address  = [
    '@type'           => 'PostalAddress',
    'streetAddress'   => get_option( 'ssseo_organization_address', '' ),
    'addressLocality' => get_option( 'ssseo_organization_locality', '' ),
    'addressRegion'   => get_option( 'ssseo_organization_state', '' ),
    'postalCode'      => get_option( 'ssseo_organization_postal_code', '' ),
    'addressCountry'  => get_option( 'ssseo_organization_country', '' ),
];

$org_address_clean = [];
foreach ( $org_address as $key => $val ) {
    if ( ! empty( $val ) ) {
        $org_address_clean[ $key ] = $val;
    }
}

// Social profiles
$org_sameas = get_option( 'ssseo_organization_social_profiles', [] );
if ( ! is_array( $org_sameas ) ) $org_sameas = [];
$org_sameas = array_filter( array_map( 'esc_url_raw', $org_sameas ) );

$provider = [
    '@type' => 'Organization',
    'name'  => $org_name,
    'url'   => $org_url,
];

if ( $org_phone ) {
    $provider['telephone'] = $org_phone;
}
if ( $org_logo ) {
    $provider['logo'] = $org_logo;
}
if ( ! empty( $org_address_clean ) ) {
    $provider['address'] = $org_address_clean;
}
if ( ! empty( $org_sameas ) ) {
    $provider['sameAs'] = array_values( $org_sameas );
}

$area_served = [
    '@type' => 'Place',
    'name'  => get_the_title( $post_id ),
];

// Add geo if lat/lng exist
if ( $org_lat && $org_lng ) {
    $area_served['geo'] = [
        '@type'     => 'GeoCoordinates',
        'latitude'  => $org_lat,
        'longitude' => $org_lng,
    ];
}

// Get selected service type from post meta
$service_type = get_post_meta( $post_id, '_ssseo_service_area_type', true );

// Define valid schema.org service types and their labels
$service_type_labels = [
    'LegalService'               => 'Legal Service',
    'FinancialService'           => 'Financial Service',
    'FoodService'                => 'Food Service',
    'MedicalBusiness'            => 'Medical Service',
    'HomeAndConstructionBusiness'=> 'Home/Construction Service',
    'EmergencyService'           => 'Emergency Service',
    'AutomotiveBusiness'         => 'Automotive Service',
    'ChildCare'                  => 'Child Care',
    'CleaningService'            => 'Cleaning Service',
    'Electrician'                => 'Electrician',
    'Plumber'                    => 'Plumber',
    'HVACBusiness'               => 'HVAC Service',
    'RoofingContractor'          => 'Roofing Contractor',
    'MovingCompany'              => 'Moving Company',
    'PestControl'                => 'Pest Control',
];

// Get label for schema name
$name_label = $service_type_labels[ $service_type ] ?? $default_service_label;

$schema = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Service',
    'name'        => $name_label ?: get_the_title( $post_id ),
    'description' => get_the_excerpt( $post_id ),
    'url'         => get_permalink( $post_id ),
    'areaServed'  => $area_served,
    'provider'    => $provider,
];

if ( $service_type ) {
    $schema['serviceType'] = $service_type;
}

$schema_markup[] = $schema;
