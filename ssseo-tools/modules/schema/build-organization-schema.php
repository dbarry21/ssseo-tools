<?php
// --- Organization Schema ---
$org_name        = get_option('ssseo_organization_name');
$org_url         = get_option('ssseo_organization_url');
$org_logo_id     = get_option('ssseo_organization_logo');
$org_phone       = get_option('ssseo_organization_phone');
$org_description = get_option('ssseo_organization_description');
$org_email       = get_option('ssseo_organization_email');
$org_sameas      = get_option('ssseo_organization_social_profiles', []);
$org_area_served = get_option('ssseo_organization_areas_served');
$org_lat         = get_option('ssseo_organization_latitude');
$org_lng         = get_option('ssseo_organization_longitude');

$org_schema = array(
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => $org_name,
    'url'      => $org_url,
);

// Logo (from attachment ID)
if ( is_numeric($org_logo_id) ) {
    $img_data = wp_get_attachment_image_src($org_logo_id, 'full');
    if ( $img_data ) {
        $org_schema['logo']  = $img_data[0];
        $org_schema['image'] = $img_data[0];
    }
}

if ( $org_phone ) {
    $org_schema['telephone'] = $org_phone;
}
if ( $org_description ) {
    $org_schema['description'] = $org_description;
}
if ( $org_email ) {
    $org_schema['email'] = $org_email;
}

// Address block
$street   = get_option('ssseo_organization_address');
$locality = get_option('ssseo_organization_locality');
$region   = get_option('ssseo_organization_state');
$zip      = get_option('ssseo_organization_postal_code');
$country  = get_option('ssseo_organization_country');

if ( $street || $locality || $region || $zip || $country ) {
    $org_schema['address'] = array(
        '@type'           => 'PostalAddress',
        'streetAddress'   => $street,
        'addressLocality' => $locality,
        'addressRegion'   => $region,
        'postalCode'      => $zip,
        'addressCountry'  => $country,
    );
}

// Social profiles (sameAs)
if ( is_array($org_sameas) && ! empty($org_sameas) ) {
    $org_schema['sameAs'] = array_values(array_filter($org_sameas));
}

// Area Served (multiline → array)
if ( $org_area_served ) {
    $areas = array_filter(array_map('trim', explode("\n", $org_area_served)));
    if ( ! empty($areas) ) {
        $org_schema['areaServed'] = $areas;
    }
}

// Geo Coordinates
if ( $org_lat && $org_lng ) {
    $org_schema['geo'] = array(
        '@type'     => 'GeoCoordinates',
        'latitude'  => $org_lat,
        'longitude' => $org_lng,
    );
}

// Add to global schema array
$schema_markup[] = $org_schema;
