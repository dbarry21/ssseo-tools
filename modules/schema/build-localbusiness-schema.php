<?php
/**
 * Builder: LocalBusiness Schema
 *
 * Expects $ssseo_current_localbusiness_location to exist (an associative array).
 * Populates $schema_markup for that location.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Make sure we have location data with at least a name
$loc = $ssseo_current_localbusiness_location ?? [];
if ( ! is_array( $loc ) || empty( $loc['name'] ) ) {
    return;
}

// Sanitize & pull values
$name      = sanitize_text_field( $loc['name'] );
$telephone = sanitize_text_field( $loc['phone'] ?? '' );
$price     = sanitize_text_field( $loc['price'] ?? '' );

// Address
$street  = sanitize_text_field( $loc['street'] ?? '' );
$city    = sanitize_text_field( $loc['city'] ?? '' );
$region  = sanitize_text_field( $loc['state'] ?? '' );
$postal  = sanitize_text_field( $loc['zip'] ?? '' );
$country = sanitize_text_field( $loc['country'] ?? '' );

// Geo coordinates
$latitude  = sanitize_text_field( $loc['lat'] ?? '' );
$longitude = sanitize_text_field( $loc['lng'] ?? '' );

// Begin building the schema array
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

// Telephone
if ( $telephone ) {
    $schema_markup['telephone'] = $telephone;
}

// priceRange
if ( $price ) {
    $schema_markup['priceRange'] = $price;
}

// Featured image if available
if ( has_post_thumbnail() ) {
    $img_url = get_the_post_thumbnail_url( get_the_ID(), 'full' );
    if ( $img_url ) {
        $schema_markup['image'] = esc_url_raw( $img_url );
    }
}

// GeoCoordinates
if ( $latitude !== '' && $longitude !== '' ) {
    $schema_markup['geo'] = [
        '@type'     => 'GeoCoordinates',
        'latitude'  => $latitude,
        'longitude' => $longitude,
    ];
}

// Opening Hours Specification
if ( isset( $loc['hours'] ) && is_array( $loc['hours'] ) ) {
    $openingHours = [];
    foreach ( $loc['hours'] as $row ) {
        $day   = sanitize_text_field( $row['day']   ?? '' );
        $open  = sanitize_text_field( $row['open']  ?? '' );
        $close = sanitize_text_field( $row['close'] ?? '' );
        if ( $day && $open && $close ) {
            $openingHours[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => $day,
                'opens'     => $open,
                'closes'    => $close,
            ];
        }
    }
    if ( ! empty( $openingHours ) ) {
        $schema_markup['openingHoursSpecification'] = $openingHours;
    }
}

// At this point, $schema_markup is complete. The caller (ssseo_maybe_output_localbusiness_schema) will JSON-encode and output it.
