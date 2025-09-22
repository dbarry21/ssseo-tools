<?php
/**
 * Local Business Shortcodes (Address / Hours / Status)
 * - [gmb_address]
 * - [gmb_hours]
 * - [ssseo_places_status]
 *
 * Requires Site Options keys:
 *   - ssseo_google_places_api_key
 *   - ssseo_google_places_place_id (default Place ID)
 */

if ( ! defined('ABSPATH') ) exit;

/** -----------------------------------------------------------------------
 * Helpers
 * --------------------------------------------------------------------- */

/** Get Places API Key from Site Options */
if ( ! function_exists('ssseo_get_google_places_api_key') ) {
	function ssseo_get_google_places_api_key() {
		return trim( (string) get_option('ssseo_google_places_api_key', '') );
	}
}

/** Strong default Place ID resolver (Site Options first, then ACF/legacy/constant/filter) */
if ( ! function_exists('ssseo_get_default_place_id') ) {
	function ssseo_get_default_place_id( $post_id = 0 ) {
		// 0) Site Options (your admin tab)
		$val = trim( (string) get_option('ssseo_google_places_place_id', '') );
		if ( $val !== '' ) return $val;

		// 1) Per-post ACF (optional)
		if ( ! $post_id ) $post_id = get_the_ID();
		if ( function_exists('get_field') ) {
			foreach ( ['gmb_place_id','google_place_id','place_id'] as $acf_key ) {
				$v = trim( (string) get_field( $acf_key, $post_id ) );
				if ( $v !== '' ) return $v;
			}
		}

		// 2) Legacy options (for safety)
		foreach ( ['ssseo_google_place_id','ssseo_default_place_id','ssseo_place_id','ssseo_places_place_id'] as $opt ) {
			$v = trim( (string) get_option( $opt, '' ) );
			if ( $v !== '' ) return $v;
		}

		// 3) Constant
		if ( defined('SSSEO_DEFAULT_PLACE_ID') && SSSEO_DEFAULT_PLACE_ID ) return SSSEO_DEFAULT_PLACE_ID;

		// 4) Filter
		$f = apply_filters( 'ssseo/default_place_id', '' );
		return is_string($f) ? trim($f) : '';
	}
}

/** Resolve a free-text company → Place ID via Find Place From Text (with caching) */
if ( ! function_exists('ssseo_gmb_resolve_place_id') ) {
	function ssseo_gmb_resolve_place_id( $company, $api_key, $region = 'us', $language = 'en', $cache_minutes = 1440 ) {
		$company = trim( (string) $company );
		if ( $company === '' || $api_key === '' ) return '';

		$cache_minutes = max(1, (int) $cache_minutes);
		$tkey = 'ssseo_gmb_pid_' . md5( strtolower($company) . '|' . strtolower($region) . '|' . strtolower($language) . '|v2' );
		$cached = get_transient($tkey);
		if ( is_string($cached) && $cached !== '' ) return $cached;

		// Do NOT pre-encode values; add_query_arg will handle encoding.
		$endpoint = add_query_arg([
			'input'      => $company,
			'inputtype'  => 'textquery',
			'fields'     => 'place_id,name,formatted_address',
			'region'     => $region,
			'language'   => $language,
			'key'        => $api_key,
		], 'https://maps.googleapis.com/maps/api/place/findplacefromtext/json');

		$response = wp_remote_get( $endpoint, [ 'timeout' => 25 ] );
		if ( is_wp_error($response) ) return '';

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = json_decode( wp_remote_retrieve_body($response), true );
		if ( $code !== 200 || !is_array($body) || !isset($body['status']) ) return '';

		if ( $body['status'] !== 'OK' || empty($body['candidates'][0]['place_id']) ) {
			return ''; // Not found / ambiguous
		}

		$place_id = (string) $body['candidates'][0]['place_id'];
		if ( $place_id !== '' ) set_transient( $tkey, $place_id, $cache_minutes * MINUTE_IN_SECONDS );
		return $place_id;
	}
}

/** Small util: build a debug tag (only when debug=1) */
function ssseo_dbg_tag( $label, $pairs = [] ) {
	$bits = [];
	foreach ( $pairs as $k => $v ) $bits[] = $k . '=' . esc_html( (string) $v );
	return '<small class="text-muted d-block">[' . esc_html($label) . ': ' . implode(', ', $bits) . ']</small>';
}

/** -----------------------------------------------------------------------
 * [gmb_address]
 * --------------------------------------------------------------------- */
/**
 * Shortcode: [gmb_address place_id="" company="" parts="street,suite,city,state,postal" join=", " link="0" schema="0" class="" cache="1440" region="us" language="en" debug="0"]
 */
remove_shortcode('gmb_address');
add_shortcode('gmb_address', function( $atts ) {
	$atts = shortcode_atts([
		'place_id'         => '',
		'company'          => '',
		'parts'            => 'street,suite,city,state,postal',
		'join'             => ', ',
		'class'            => '',
		'link'             => '0',
		'schema'           => '0',
		'cache'            => 1440,
		'region'           => 'us',
		'language'         => 'en',
		'debug'            => '0',
		// NEW: directions link controls
		'directions'       => '0',
		'directions_label' => 'Get Directions',
		'directions_mode'  => '',                 // driving|walking|bicycling|transit
		'directions_target'=> '_blank',
		'directions_class' => 'gmb-directions',
	], $atts, 'gmb_address');

	$api_key  = ssseo_get_google_places_api_key();
	if ( $api_key === '' ) return ( $atts['debug'] === '1' ) ? '<em>gmb_address: API key not set</em>' : '<!-- gmb_address: missing api key -->';

	// Resolve Place ID: attribute → company → default
	$pid_source = 'attr';
	$place_id   = trim( (string) $atts['place_id'] );
	if ( $place_id === '' && $atts['company'] !== '' ) {
		$place_id   = ssseo_gmb_resolve_place_id( sanitize_text_field($atts['company']), $api_key, sanitize_text_field($atts['region']), sanitize_text_field($atts['language']), (int)$atts['cache'] );
		$pid_source = 'company';
	}
	if ( $place_id === '' ) {
		$place_id   = ssseo_get_default_place_id();
		$pid_source = 'default';
	}
	if ( $place_id === '' ) return ( $atts['debug'] === '1' ) ? '<em>gmb_address: no place_id available</em>' : '<!-- gmb_address: missing place_id -->';

	$cache_minutes = max( 1, (int) $atts['cache'] );
	$transient_key = 'ssseo_gmb_addr_' . md5( $place_id . '|' . strtolower($atts['language']) . '|v3' );
	$cached = get_transient( $transient_key );

	if ( is_array($cached) && isset($cached['components']) ) {
		$components = $cached['components'];
		$maps_url   = $cached['maps_url'];
		$name       = $cached['name'];
		$hit        = true;
	} else {
		$hit = false;
		$endpoint = add_query_arg([
			'place_id' => $place_id,          // do not pre-encode
			'fields'   => 'address_components,formatted_address,url,name',
			'language' => $atts['language'],
			'key'      => $api_key,
		], 'https://maps.googleapis.com/maps/api/place/details/json');

		$response = wp_remote_get( $endpoint, [ 'timeout' => 25 ] );
		if ( is_wp_error($response) ) return ( $atts['debug'] === '1' ) ? '<em>gmb_address error: '.esc_html($response->get_error_message()).'</em>' : '<!-- gmb_address: api error -->';

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = json_decode( wp_remote_retrieve_body($response), true );
		if ( $code !== 200 || !is_array($body) || ($body['status'] ?? '') !== 'OK' ) {
			$msg = isset($body['status']) ? $body['status'] : 'unknown';
			return ( $atts['debug'] === '1' ) ? '<em>gmb_address API status: '.esc_html($msg).'</em>' : '<!-- gmb_address: api status ' . esc_html($msg) . ' -->';
		}

		$result   = $body['result'] ?? [];
		$ac       = $result['address_components'] ?? [];
		$name     = $result['name'] ?? '';
		$maps_url = $result['url'] ?? '';

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

	// Build address text
	$want_parts = array_filter( array_map( 'trim', explode(',', strtolower($atts['parts'])) ) );
	if ( empty($want_parts) ) $want_parts = ['street','suite','city','state','postal'];

	$street_line = trim( implode(' ', array_filter([
		$components['street_number'],
		$components['route'],
	])));
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
		if ( isset($map[$key]) && $map[$key] !== '' ) $pieces[] = $map[$key];
	}
	$joiner  = (string) $atts['join'];
	$content = implode( $joiner, array_map( 'esc_html', $pieces ) );

	// Build Directions URL if requested
	$directions_html = '';
	if ( $atts['directions'] === '1' ) {
		// Build Directions URL (Maps URLs require destination + destination_place_id)
$mode = strtolower( trim( (string) $atts['directions_mode'] ) );
$allowed_modes = ['driving','walking','bicycling','transit'];
$args = [
    'api'                    => '1',
    'destination'            => $name ?: $street_line ?: 'Destination',
    'destination_place_id'   => $place_id,
];
if ( in_array($mode, $allowed_modes, true) ) {
    $args['travelmode'] = $mode;
}
// optional: force nav UI on mobile
// $args['dir_action'] = 'navigate';

$dir_url = add_query_arg( $args, 'https://www.google.com/maps/dir/' );

$directions_html = '<div class="' . esc_attr( $atts['directions_class'] ) . '">'
  . '<a class="' . esc_attr( $atts['directions_link_class'] ) . '" href="' . esc_url( $dir_url ) . '" target="' . esc_attr( $atts['directions_target'] ) . '" rel="noopener">'
  . esc_html( $atts['directions_label'] )
  . '</a></div>';

	}

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
		$addr_html .= $directions_html; // show directions under the address
		$addr_html .= '<script type="application/ld+json">'. wp_json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) .'</script>';
		if ( $atts['debug'] === '1' ) $addr_html .= ssseo_dbg_tag('gmb_address', ['pid-source'=>$pid_source, 'cache'=>$hit?'hit':'miss']);
		return $addr_html;
	}

	// Link mode (wrap address text in place URL), then add directions link below
	$inner = $content;
	if ( $atts['link'] === '1' && ! empty($maps_url) ) {
		$inner = '<a href="'. esc_url($maps_url) .'" target="_blank" rel="noopener" class="btn btn-primary">'. $inner .'</a>';
	}
	//$wrap_class = 'gmb-address'. ( $atts['class'] ? ' '.esc_attr($atts['class']) : '' );
	$out = '<span class="'. $wrap_class .'">'. $inner .'</span>' . $directions_html;

	if ( $atts['debug'] === '1' ) $out .= ssseo_dbg_tag('gmb_address', ['pid-source'=>$pid_source, 'cache'=>$hit?'hit':'miss']);
	return $out;
});


/** -----------------------------------------------------------------------
 * [gmb_hours]
 * --------------------------------------------------------------------- */
/**
 * Shortcode: [gmb_hours place_id="" show="week|today" show_today_first="0|1" highlight_today="1" compact="1" class="" list_class="" cache="60" debug="0"]
 */
function ssseo_gmb_hours_shortcode( $atts ) {
	$atts = shortcode_atts( [
		'place_id'         => '',
		'show'             => 'week',
		'show_today_first' => 0,
		'highlight_today'  => 1,
		'compact'          => 1,
		'class'            => '',
		'list_class'       => '', // alias → class
		'cache'            => 60,
		'debug'            => 0,
	], $atts, 'gmb_hours' );

	// alias support
	if ( !$atts['class'] && $atts['list_class'] ) $atts['class'] = $atts['list_class'];

	// Resolve Place ID (attr → default helper)
	$place_id   = trim( (string) $atts['place_id'] );
	$pid_source = 'attr';
	if ( $place_id === '' && function_exists('ssseo_get_default_place_id') ) {
		$place_id   = ssseo_get_default_place_id();
		$pid_source = 'default';
	}
	if ( $place_id === '' ) return ( $atts['debug'] ? '<em>gmb_hours: missing place_id</em>' : '<!-- gmb_hours: missing place_id -->' );

	$api_key = function_exists('ssseo_get_google_places_api_key') ? ssseo_get_google_places_api_key() : '';
	if ( ! $api_key ) return ( $atts['debug'] ? '<em>gmb_hours: no API key</em>' : '<!-- gmb_hours: no api key -->' );

	$cache_key = 'ssseo_gmb_hours_' . md5( $place_id . '|' . $atts['show'] . '|v3' );
	$hours     = get_transient( $cache_key );
	$hit       = true;

	if ( false === $hours ) {
		$hit = false;
		$url = add_query_arg( [
			'place_id' => $place_id,
			'fields'   => 'opening_hours',
			'key'      => $api_key,
		], 'https://maps.googleapis.com/maps/api/place/details/json' );

		$resp = wp_remote_get( $url, [ 'timeout' => 10 ] );
		if ( is_wp_error( $resp ) ) return ( $atts['debug'] ? '<em>gmb_hours api error: '.esc_html($resp->get_error_message()).'</em>' : '<!-- gmb_hours: api error -->' );
		$data  = json_decode( wp_remote_retrieve_body( $resp ), true );
		$hours = $data['result']['opening_hours'] ?? [];
		set_transient( $cache_key, $hours, max(1,(int)$atts['cache']) * MINUTE_IN_SECONDS );
	}

	if ( empty( $hours['weekday_text'] ) ) return ( $atts['debug'] ? '<em>gmb_hours: no hours for this place</em>' : '<!-- gmb_hours: no hours -->' );

	$days = $hours['weekday_text'];

	// WordPress: current_time('w') → 0=Sun..6=Sat
	// Google weekday_text order is Monday..Sunday → map WP index to that order:
	$today_idx_monday_first = ( (int) current_time('w') + 6 ) % 7; // Sun(0)→6, Mon(1)→0, ... Sat(6)→5

	if ( $atts['show'] === 'today' ) {
		$days = [ $days[ $today_idx_monday_first ] ];
	}

	if ( ! $atts['show'] === 'today' && $atts['show_today_first'] ) {
		// Rotate so today is first
		$days = array_merge( array_slice( $days, $today_idx_monday_first ), array_slice( $days, 0, $today_idx_monday_first ) );
	}

	$classes = 'hours-list';
	if ( $atts['compact'] ) $classes .= ' small text-muted';
	if ( $atts['class'] )   $classes .= ' ' . esc_attr( $atts['class'] );

	$out = '<div class="' . esc_attr( $classes ) . '">';

	foreach ( $days as $i => $row ) {
		$is_today =
			( $atts['show'] === 'today' ) ? true :
			( $atts['show_today_first'] ? ( $i === 0 ) : ( $i === $today_idx_monday_first ) );

		if ( $is_today && $atts['highlight_today'] ) {
			$out .= '<div class="today"><strong>' . esc_html( $row ) . '</strong></div>';
		} else {
			$out .= '<div>' . esc_html( $row ) . '</div>';
		}
	}
	$out .= '</div>';

	if ( (int)$atts['debug'] === 1 ) {
		$out .= '<small class="text-muted d-block">[gmb_hours: pid-source=' . esc_html($pid_source) . ', cache=' . ( $hit ? 'hit' : 'miss' ) . ']</small>';
	}

	return $out;
}
remove_shortcode( 'gmb_hours' );
add_shortcode( 'gmb_hours', 'ssseo_gmb_hours_shortcode' );


/** -----------------------------------------------------------------------
 * [ssseo_places_status]
 * --------------------------------------------------------------------- */
/**
 * Shortcode: [ssseo_places_status place_id="" output="boolean|text|badge" refresh="900" fallback="unknown" show_next="1" show_day="abbr|full" debug="0"]
 */
if ( ! function_exists( 'ssseo_places_status_shortcode' ) ) {
	function ssseo_places_status_shortcode( $atts ) {
		$atts = shortcode_atts( [
			'place_id'  => '',
			'output'    => 'text',
			'refresh'   => 900,
			'fallback'  => 'unknown',
			'show_next' => 1,
			'show_day'  => 'abbr',   // abbr|full
			'debug'     => 0,
		], $atts, 'ssseo_places_status' );

		$debug = (int)$atts['debug'] === 1;

		// Resolve Place ID: attr → helper → site option (belt & suspenders)
		$place_id   = trim( (string) $atts['place_id'] );
		$pid_source = 'attr';

		if ( $place_id === '' && function_exists('ssseo_get_default_place_id') ) {
			$place_id   = ssseo_get_default_place_id();
			$pid_source = 'default';
		}
		if ( $place_id === '' ) {
			$opt_pid = trim( (string) get_option('ssseo_google_places_place_id', '') );
			if ( $opt_pid !== '' ) { $place_id = $opt_pid; $pid_source = 'siteoption'; }
		}
		if ( $place_id === '' ) {
			return $debug ? '<em>ssseo_places_status: no place_id (attr/helper/option empty)</em>' : esc_html( $atts['fallback'] );
		}

		$api_key = function_exists('ssseo_get_google_places_api_key') ? ssseo_get_google_places_api_key() : '';
		if ( ! $api_key ) {
			return $debug ? '<em>ssseo_places_status: missing API key</em>' : esc_html( $atts['fallback'] );
		}

		$cache_key = 'ssseo_places_status_' . md5( $place_id . '|v3' );
		$data = get_transient( $cache_key );
		$hit  = true;

		if ( false === $data ) {
			$hit = false;
			$url = add_query_arg( [
  'place_id' => $place_id,
  'fields'   => 'opening_hours,utc_offset', // <-- was utc_offset_minutes
  'key'      => $api_key,
], 'https://maps.googleapis.com/maps/api/place/details/json' );


			$resp = wp_remote_get( $url, [ 'timeout' => 12 ] );
			if ( is_wp_error( $resp ) ) {
				return $debug ? '<em>ssseo_places_status: API error ' . esc_html( $resp->get_error_message() ) . '</em>' : esc_html( $atts['fallback'] );
			}
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );
			if ( ! is_array( $body ) ) {
				return $debug ? '<em>ssseo_places_status: invalid API response</em>' : esc_html( $atts['fallback'] );
			}
			if ( ($body['status'] ?? '') !== 'OK' ) {
				return $debug ? '<em>ssseo_places_status: API status ' . esc_html( (string)($body['status'] ?? 'unknown') ) . '</em>' : esc_html( $atts['fallback'] );
			}

			$data = [
  'opening_hours'      => $body['result']['opening_hours'] ?? [],
  'utc_offset'         => $body['result']['utc_offset'] 
                          ?? ($body['result']['utc_offset_minutes'] ?? null),
];

$utc_offset = $data['utc_offset'] ?? null;

			set_transient( $cache_key, $data, max(1, (int)$atts['refresh']) * MINUTE_IN_SECONDS );
		}

		$oh         = $data['opening_hours'] ?? [];
		$open       = isset( $oh['open_now'] ) ? (bool) $oh['open_now'] : null;
		$periods    = $oh['periods'] ?? [];
		$utc_offset = $data['utc_offset_minutes'];

		// Local time at the place
		$now_ts = current_time( 'timestamp' );
		if ( is_int( $utc_offset ) ) {
			$dt = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
			$dt->modify( sprintf( '+%d minutes', $utc_offset ) );
			$now_ts = $dt->getTimestamp();
		}
		$now_dow     = (int) gmdate( 'w', $now_ts );
		$minutes_now = (int) gmdate( 'G', $now_ts ) * 60 + (int) gmdate( 'i', $now_ts );

		$to_minutes = static function( $hhmm ) {
			if ( ! is_string( $hhmm ) || strlen( $hhmm ) < 3 ) return null;
			return (int) substr( $hhmm, 0, 2 ) * 60 + (int) substr( $hhmm, -2 );
		};
		$day_label = static function( $idx, $style = 'abbr' ) {
			$abbr = [ 'Sun','Mon','Tue','Wed','Thu','Fri','Sat' ];
			$full = [ 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday' ];
			$idx = max(0, min(6, (int)$idx));
			return $style === 'full' ? $full[$idx] : $abbr[$idx];
		};
		$format_time = static function( $minutes, $ref_ts ) {
			$h = (int) floor( $minutes / 60 );
			$m = $minutes % 60;
			$base = (int) gmdate( 'Ymd', $ref_ts );
			$dt = DateTime::createFromFormat( 'Ymd H:i', sprintf( '%d %02d:%02d', $base, $h, $m ), new DateTimeZone( 'UTC' ) );
			return $dt ? date_i18n( get_option( 'time_format', 'g:i A' ), $dt->getTimestamp() ) : '';
		};
		$find_next = function( $need, $from_day, $from_min ) use ( $periods, $to_minutes ) {
			for ( $i = 0; $i < 7; $i++ ) {
				$day = ( $from_day + $i ) % 7;
				foreach ( $periods as $p ) {
					if ( ! isset( $p['open']['day'], $p['open']['time'] ) ) continue;
					$o_day = (int) $p['open']['day'];
					$o_min = $to_minutes( $p['open']['time'] );
					$c_day = isset( $p['close']['day'] ) ? (int) $p['close']['day'] : null;
					$c_min = isset( $p['close']['time'] ) ? $to_minutes( $p['close']['time'] ) : null;

					if ( $need === 'open' ) {
						if ( $o_day === $day && ( $i > 0 || $o_min >= $from_min ) ) {
							return [ 'type' => 'open', 'day' => $o_day, 'minutes' => $o_min ];
						}
					} else {
						if ( $o_day === $day && $c_day !== null && $c_min !== null ) {
							$span_same = ( $c_day === $o_day && $c_min > $o_min );
							$span_next = ( ( ( $c_day + 7 - $o_day ) % 7 ) >= 1 );
							if ( $i === 0 ) {
								if ( $span_same && $from_min >= $o_min && $from_min < $c_min ) return [ 'type' => 'close', 'day' => $c_day, 'minutes' => $c_min ];
								if ( $span_next && $from_min >= $o_min ) return [ 'type' => 'close', 'day' => $c_day, 'minutes' => $c_min ];
							}
						}
					}
				}
			}
			return null;
		};

		// Approximate open_now if API doesn’t include it
		if ( $open === null && ! empty( $periods ) ) {
			$approx = false;
			foreach ( $periods as $p ) {
				if ( ! isset( $p['open']['day'], $p['open']['time'], $p['close']['day'], $p['close']['time'] ) ) continue;
				$o_day = (int) $p['open']['day'];
				$c_day = (int) $p['close']['day'];
				$o_min = $to_minutes( $p['open']['time'] );
				$c_min = $to_minutes( $p['close']['time'] );
				if ( $o_day === $now_dow ) {
					if ( $c_day === $o_day ) { if ( $minutes_now >= $o_min && $minutes_now < $c_min ) { $approx = true; break; } }
					else { if ( $minutes_now >= $o_min ) { $approx = true; break; } }
				} elseif ( (( $now_dow + 6 ) % 7) === $o_day && $c_day === $now_dow ) {
					if ( $minutes_now < $c_min ) { $approx = true; break; }
				}
			}
			$open = $approx;
		}

		// Build output
		$is_open = (bool) $open;
		$next_text = '';
		if ( (int)$atts['show_next'] === 1 && ! empty( $periods ) ) {
			if ( $is_open ) {
				if ( $n = $find_next('close', $now_dow, $minutes_now) ) {
					$next_text = ' – ' . sprintf( esc_html__( 'closes %s', 'ssseo' ), esc_html( $format_time( $n['minutes'], $now_ts ) ) );
				}
			} else {
				if ( $n = $find_next('open', $now_dow, $minutes_now) ) {
					$day = $day_label( $n['day'], $atts['show_day'] === 'full' ? 'full' : 'abbr' );
					$next_text = ' – ' . sprintf( esc_html__( 'opens %s %s', 'ssseo' ), esc_html( $day ), esc_html( $format_time( $n['minutes'], $now_ts ) ) );
				}
			}
		}

		if ( $atts['output'] === 'boolean' ) {
			$out = $is_open ? 'true' : 'false';
		} elseif ( $atts['output'] === 'badge' ) {
			$badge_cls = $is_open ? 'bg-success' : 'bg-danger';
			$out = '<span class="ssseo-status badge ' . esc_attr( $badge_cls ) . '">' .
				( $is_open ? esc_html__('Open now','ssseo') : esc_html__('Closed','ssseo') ) .
			'</span>';
		} else {
			$word = $is_open
				? '<span class="ssseo-status-open" style="color:#198754">Open</span>'
				: '<span class="ssseo-status-closed" style="color:#dc3545">Closed</span>';
			$out = sprintf( '%s %s%s', $word, esc_html__('now','ssseo'), $next_text );
		}

		if ( $debug ) {
			$out .= '<small class="text-muted d-block">[ssseo_places_status: pid-source=' . esc_html($pid_source) . ', cache=' . ( $hit ? 'hit' : 'miss' ) . ']</small>';
		}
		return $out;
	}
}
remove_shortcode('ssseo_places_status');
add_shortcode('ssseo_places_status', 'ssseo_places_status_shortcode');

