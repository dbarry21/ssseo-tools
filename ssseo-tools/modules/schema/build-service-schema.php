<?php
/**
 * Service Schema Builder
 * Outputs Product schema for single 'service' posts with child offers
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( get_post_type() !== 'service' ) return;

$post_id = get_queried_object_id();

// Retrieve organization details
$org_name    = get_option( 'ssseo_organization_name', get_bloginfo( 'name' ) );
$org_url     = get_option( 'ssseo_organization_url', home_url() );
$org_phone   = get_option( 'ssseo_organization_phone', '' );
$org_logo_id = get_option( 'ssseo_organization_logo', 0 );
$org_logo    = $org_logo_id ? wp_get_attachment_image_url( $org_logo_id, 'full' ) : '';

// Clean organization address
$org_address_raw = [
    'streetAddress'   => get_option( 'ssseo_organization_address', '' ),
    'addressLocality' => get_option( 'ssseo_organization_locality', '' ),
    'addressRegion'   => get_option( 'ssseo_organization_state', '' ),
    'postalCode'      => get_option( 'ssseo_organization_postal_code', '' ),
    'addressCountry'  => get_option( 'ssseo_organization_country', '' ),
];
$org_address = array_filter( $org_address_raw );
if ( ! empty( $org_address ) ) {
    $org_address = array_merge( [ '@type' => 'PostalAddress' ], $org_address );
}

// Clean social profiles
$org_sameas = get_option( 'ssseo_organization_social_profiles', [] );
$org_sameas = is_array( $org_sameas ) ? array_filter( array_map( 'esc_url_raw', $org_sameas ) ) : [];

// Build provider object
$provider = [
    '@type' => 'Organization',
    'name'  => $org_name,
    'url'   => $org_url,
];
if ( $org_phone )     $provider['telephone'] = $org_phone;
if ( $org_logo )      $provider['logo'] = $org_logo;
if ( ! empty( $org_address ) ) $provider['address'] = $org_address;
if ( ! empty( $org_sameas ) )  $provider['sameAs']  = array_values( $org_sameas );

// Get service name from dropdown or fallback to post title
$service_label = get_option( 'ssseo_default_service_label', '' );
$product_name  = $service_label ?: get_the_title( $post_id );

// Get excerpt for description
$description = get_the_excerpt( $post_id );

// Gather top-level services as offers (post_parent = 0)
$offers = [];
$top_level_services = get_posts([
    'post_type'      => 'service',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'post_parent'    => 0,
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
]);

foreach ( $top_level_services as $service ) {
    $offers[] = [
        '@type'       => 'Offer',
        'name'        => get_the_title( $service->ID ),
        'url'         => get_permalink( $service->ID ),
        'description' => get_the_excerpt( $service->ID ),
		'price'	=> 0,
		'priceCurrency' => 'USD',
		'availability' => 'InStock',
    ];
}


// Build schema
$schema = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $product_name,
    'description' => $description,
    'url'         => get_permalink( $post_id ),
	'image'       => $org_logo ?: '',
    'brand'       => [
        '@type' => 'Organization',
        'name'  => $org_name,
    ],
    'provider'    => $provider,
];

if ( ! empty( $offers ) ) {
    $schema['offers'] = $offers;
}

// Output as JSON-LD
echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
