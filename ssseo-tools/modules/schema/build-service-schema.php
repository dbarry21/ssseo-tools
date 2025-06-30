<?php
/**
 * Service Schema Builder
 * Populates JSON-LD schema for single 'service' post type
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( get_post_type() !== 'service' ) return;

$post_id = get_queried_object_id();

// Retrieve organization details from plugin options
$org_name     = get_option( 'ssseo_organization_name', get_bloginfo( 'name' ) );
$org_url      = get_option( 'ssseo_organization_url', home_url() );
$org_phone    = get_option( 'ssseo_organization_phone', '' );
$org_logo_id  = get_option( 'ssseo_organization_logo', 0 );
$org_logo     = $org_logo_id ? wp_get_attachment_image_url( $org_logo_id, 'full' ) : '';
$org_address  = [
    '@type'           => 'PostalAddress',
    'streetAddress'   => get_option( 'ssseo_organization_address', '' ),
    'addressLocality' => get_option( 'ssseo_organization_locality', '' ),
    'addressRegion'   => get_option( 'ssseo_organization_state', '' ),
    'postalCode'      => get_option( 'ssseo_organization_postal_code', '' ),
    'addressCountry'  => get_option( 'ssseo_organization_country', '' ),
];

// Clean up empty address fields
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

$schema_markup[] = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Service',
    'name'        => get_the_title( $post_id ),
    'description' => get_the_excerpt( $post_id ),
    'url'         => get_permalink( $post_id ),
    'provider'    => $provider,
];
