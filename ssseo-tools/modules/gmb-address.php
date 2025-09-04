<?php
/**
 * Shortcode: [gmb_address place_id="PLACE_ID" company="Company Name City" parts="street,suite,city,state,postal" join=", " link="0" schema="0" class="" cache="1440" region="us" language="en"]
 *
 * - If place_id is missing and company is provided, uses Find Place From Text to resolve the Place ID.
 * - Caches both the resolved Place ID and the address details via transients.
 * - Supports semantic <address> + JSON-LD when schema="1".
 *
 * Attributes:
 *  - place_id (string)   Preferred; if present this is used directly.
 *  - company  (string)   Optional free-text (name + city best). Used only if place_id is empty.
 *  - parts    (string)   Comma list: street, suite, city, state, postal, country. Default: street,suite,city,state,postal
 *  - join     (string)   Separator between parts. Default: ", "
 *  - link     (0|1)      Wrap the result in Google Maps URL. Default: 0
 *  - schema   (0|1)      Output <address> + JSON-LD (PostalAddress). Default: 0
 *  - class    (string)   Extra classes for wrapper.
 *  - cache    (int)      Cache minutes for address + ID resolution. Default: 1440
 *  - region   (string)   Region bias for Find Place (e.g., "us"). Default: "us"
 *  - language (string)   Language hint for both APIs (e.g., "en"). Default: "en"
 */

if ( ! defined('ABSPATH') ) exit;

if ( ! function_exists('ssseo_get_google_places_api_key') ) {
  // Fallback: align with your plugin’s stored option if named differently.
  function ssseo_get_google_places_api_key() {
    return get_option('ssseo_google_places_api_key', '');
  }
}

/**
 * Resolve a free-text company string to a Place ID using Find Place From Text.
 */
if ( ! function_exists('ssseo_gmb_resolve_place_id') ) {
  function ssseo_gmb_resolve_place_id( $company, $api_key, $region = 'us', $language = 'en', $cache_minutes = 1440 ) {
    $company = trim( (string) $company );
    if ( $company === '' || $api_key === '' ) return '';

    $cache_minutes = max(1, (int) $cache_minutes);
    $tkey = 'ssseo_gmb_pid_' . md5( strtolower($company) . '|' . strtolower($region) . '|' . strtolower($language) . '|v1' );
    $cached = get_transient($tkey);
    if ( is_string($cached) && $cached !== '' ) {
      return $cached;
    }

    $endpoint = add_query_arg([
      'input'      => rawurlencode($company),
      'inputtype'  => 'textquery',
      'fields'     => 'place_id,name,formatted_address',
      'region'     => $region,
      'language'   => $language,
      'key'        => $api_key,
    ], 'https://maps.googleapis.com/maps/api/place/findplacefromtext/json');

    $response = wp_remote_get( $endpoint, [ 'timeout' => 25 ] );
    if ( is_wp_error($response) ) return '';

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode( wp_remote_retrieve_body($response), true );
    if ( (int)$code !== 200 || !is_array($body) || !isset($body['status']) ) return '';

    if ( $body['status'] !== 'OK' || empty($body['candidates'][0]['place_id']) ) {
      return ''; // Not found / ambiguous
    }

    $place_id = (string) $body['candidates'][0]['place_id'];
    if ( $place_id !== '' ) {
      set_transient( $tkey, $place_id, $cache_minutes * MINUTE_IN_SECONDS );
    }
    return $place_id;
  }
}

add_shortcode('gmb_address', function( $atts ) {
  $atts = shortcode_atts([
    'place_id' => '',
    'company'  => '',
    'parts'    => 'street,suite,city,state,postal',
    'join'     => ', ',
    'class'    => '',
    'link'     => '0',
    'schema'   => '0',
    'cache'    => 1440,
    'region'   => 'us',
    'language' => 'en',
  ], $atts, 'gmb_address');

  $place_id = trim( sanitize_text_field( $atts['place_id'] ) );
  $company  = trim( sanitize_text_field( $atts['company'] ) );
  $api_key  = trim( ssseo_get_google_places_api_key() );

  if ( $api_key === '' ) {
    return '<em>gmb_address: API key not set</em>';
  }

  // Resolve place_id if not provided but company text is.
  if ( $place_id === '' && $company !== '' ) {
    $place_id = ssseo_gmb_resolve_place_id(
      $company,
      $api_key,
      sanitize_text_field($atts['region']),
      sanitize_text_field($atts['language']),
      (int) $atts['cache']
    );
  }

  if ( $place_id === '' ) {
    return '<em>gmb_address: missing place_id (or could not resolve from company)</em>';
  }

  $cache_minutes = max( 1, (int) $atts['cache'] );
  $transient_key = 'ssseo_gmb_addr_' . md5( $place_id . '|' . strtolower($atts['language']) . '|v2' );
  $cached = get_transient( $transient_key );

  if ( is_array($cached) && isset($cached['components']) ) {
    $components = $cached['components'];
    $maps_url   = $cached['maps_url'];
    $name       = $cached['name'];
  } else {
    // Fetch address details
    $endpoint = add_query_arg([
      'place_id' => rawurlencode($place_id),
      'fields'   => 'address_components,formatted_address,url,name',
      'language' => $atts['language'],
      'key'      => $api_key,
    ], 'https://maps.googleapis.com/maps/api/place/details/json');

    $response = wp_remote_get( $endpoint, [ 'timeout' => 25 ] );
    if ( is_wp_error($response) ) {
      return '<em>gmb_address error: '.esc_html($response->get_error_message()).'</em>';
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode( wp_remote_retrieve_body($response), true );
    if ( (int)$code !== 200 || !is_array($body) || ($body['status'] ?? '') !== 'OK' ) {
      $msg = isset($body['status']) ? $body['status'] : 'unknown';
      return '<em>gmb_address API status: '.esc_html($msg).'</em>';
    }

    $result  = $body['result'] ?? [];
    $ac      = $result['address_components'] ?? [];
    $name    = $result['name'] ?? '';
    $maps_url = $result['url'] ?? '';

    // Normalize components
    $components = [
      'street_number' => '',
      'route'         => '',
      'subpremise'    => '',
      'locality'      => '',
      'admin_level_1' => '',
      'postal_code'   => '',
      'country'       => '',
    ];
    foreach ( $ac as $c ) {
      $types = $c['types'] ?? [];
      $val   = $c['long_name'] ?? '';
      if ( in_array('street_number', $types, true) )               $components['street_number'] = $val;
      if ( in_array('route', $types, true) )                       $components['route']         = $val;
      if ( in_array('subpremise', $types, true) )                  $components['subpremise']    = $val;
      if ( in_array('locality', $types, true) )                    $components['locality']      = $val;
      if ( in_array('administrative_area_level_1', $types, true) ) $components['admin_level_1'] = $val;
      if ( in_array('postal_code', $types, true) )                 $components['postal_code']   = $val;
      if ( in_array('country', $types, true) )                     $components['country']       = $val;
    }

    set_transient( $transient_key, [
      'components' => $components,
      'maps_url'   => $maps_url,
      'name'       => $name,
    ], $cache_minutes * MINUTE_IN_SECONDS );
  }

  // Build output pieces
  $want_parts = array_filter( array_map( 'trim', explode(',', strtolower($atts['parts'])) ) );
  if ( empty($want_parts) ) {
    $want_parts = ['street','suite','city','state','postal'];
  }

  $street_line = trim( implode(' ', array_filter([
    $components['street_number'],
    $components['route'],
  ])));

  // Customize the suite label if you prefer ("Ste", "#", etc.)
  $suite = $components['subpremise'] ? 'Suite ' . $components['subpremise'] : '';

  $map = [
    'street'  => $street_line,
    'suite'   => $suite,
    'city'    => $components['locality'],
    'state'   => $components['admin_level_1'],
    'postal'  => $components['postal_code'],
    'country' => $components['country'],
  ];

  $pieces = [];
  foreach ( $want_parts as $key ) {
    if ( isset($map[$key]) && $map[$key] !== '' ) {
      $pieces[] = $map[$key];
    }
  }

  $joiner  = (string) $atts['join'];
  $content = implode( $joiner, array_map( 'esc_html', $pieces ) );

  // Schema mode
  if ( $atts['schema'] === '1' ) {
    $schema = [
      '@context'        => 'https://schema.org',
      '@type'           => 'PostalAddress',
      'streetAddress'   => trim($street_line . ( $suite ? ' ' . $suite : '' )),
      'addressLocality' => $components['locality'],
      'addressRegion'   => $components['admin_level_1'],
      'postalCode'      => $components['postal_code'],
      'addressCountry'  => $components['country'],
    ];
    $addr_html  = '<address class="gmb-address'. ( $atts['class'] ? ' '.esc_attr($atts['class']) : '' ) .'">';
    $addr_html .= esc_html( $content );
    $addr_html .= '</address>';
    $addr_html .= '<script type="application/ld+json">'. wp_json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) .'</script>';
    return $addr_html;
  }

  // Link mode
  $inner = $content;
  if ( $atts['link'] === '1' && ! empty($maps_url) ) {
    $inner = '<a href="'. esc_url($maps_url) .'" target="_blank" rel="noopener">'. $inner .'</a>';
  }

  $wrap_class = 'gmb-address'. ( $atts['class'] ? ' '.esc_attr($atts['class']) : '' );
  return '<span class="'. $wrap_class .'">'. $inner .'</span>';
});
