<?php
/**
 * Builder: LocalBusiness Schema
 *
 * Expects $ssseo_current_localbusiness_location to exist (an associative array).
 * Populates $schema_markup for that location.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Ensure valid location data
$loc = $ssseo_current_localbusiness_location ?? [];
if ( ! is_array( $loc ) || empty( $loc['name'] ) ) return;

// Sanitize core values
$name      = sanitize_text_field( $loc['name'] );
$telephone = sanitize_text_field( $loc['phone'] ?? '' );
$price     = sanitize_text_field( $loc['price'] ?? '' );

// Address details
$street  = sanitize_text_field( $loc['street'] ?? '' );
$city    = sanitize_text_field( $loc['city'] ?? '' );
$region  = sanitize_text_field( $loc['state'] ?? '' );
$postal  = sanitize_text_field( $loc['zip'] ?? '' );
$country = sanitize_text_field( $loc['country'] ?? '' );

// Geo coordinates
$latitude  = sanitize_text_field( $loc['lat'] ?? '' );
$longitude = sanitize_text_field( $loc['lng'] ?? '' );

// Build the base schema
$schema_markup = [
    '@context' => 'https://schema.org',
    '@type'    => 'LocalBusiness',
    'name'     => $name,
    'url'      => esc_url_raw( get_permalink() ),
    'address'  => [
        '@type'           => 'PostalAddress',
        'streetAddress'   => $street,
        'addressLocality' => $city,
        'addressRegion'   => $region,
        'postalCode'      => $postal,
        'addressCountry'  => $country,
    ],
];

// Add optional fields
if ( $telephone ) {
    $schema_markup['telephone'] = $telephone;
}
if ( $price ) {
    $schema_markup['priceRange'] = $price;
}

// ✅ Use image from ssseo_organization_logo ACF image field (stored as attachment ID)
$logo_id  = get_option( 'ssseo_organization_logo' );
$logo_url = $logo_id ? wp_get_attachment_url( $logo_id ) : '';
if ( $logo_url ) {
    $schema_markup['image'] = esc_url_raw( $logo_url );
}

// ✅ Add areaServed from Organization schema settings
$org_area_served = get_option( 'ssseo_organization_areas_served', '' );
if ( ! empty( $org_area_served ) ) {
    $areas = array_filter( array_map( 'trim', explode( "\n", $org_area_served ) ) );
    if ( ! empty( $areas ) ) {
        $schema_markup['areaServed'] = $areas;
    }
}

// Add geo data if both latitude and longitude are set
if ( $latitude !== '' && $longitude !== '' ) {
    $schema_markup['geo'] = [
        '@type'     => 'GeoCoordinates',
        'latitude'  => $latitude,
        'longitude' => $longitude,
    ];
}

// Add opening hours if available
if ( isset( $loc['hours'] ) && is_array( $loc['hours'] ) ) {
    $opening_hours = [];

    foreach ( $loc['hours'] as $row ) {
        $day   = sanitize_text_field( $row['day']   ?? '' );
        $open  = sanitize_text_field( $row['open']  ?? '' );
        $close = sanitize_text_field( $row['close'] ?? '' );

        if ( $day && $open && $close ) {
            $opening_hours[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => $day,
                'opens'     => $open,
                'closes'    => $close,
            ];
        }
    }

    if ( ! empty( $opening_hours ) ) {
        $schema_markup['openingHoursSpecification'] = $opening_hours;
    }
}

// Final schema is now ready in $schema_markup
// The caller (ssseo_maybe_output_localbusiness_schema) will JSON-encode and echo this.
