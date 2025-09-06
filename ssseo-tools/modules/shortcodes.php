/**
 * SSSEO Tools – Google Places "Open Now" Shortcode
 * (shows next opening when closed, closing time when open)
 * + styles only the words "Open" (green) / "Closed" (red) in text output
 *
 * Shortcode:
 *   [ssseo_places_status output="boolean|text|badge" refresh="900" fallback="unknown" show_next="1" show_day="abbr"]
 *
 * Examples:
 *   [ssseo_places_status]                          → "true" or "false"
 *   [ssseo_places_status output="text"]            → "<span class='ssseo-status-open'>Open</span> now – closes 6:00 PM"
 *                                                    or "<span class='ssseo-status-closed'>Closed</span> now – opens Tue 9:00 AM"
 *   [ssseo_places_status output="badge"]           → colored badge variant
 *   [ssseo_places_status refresh="300"]            → cache for 5 minutes
 *   [ssseo_places_status show_next="0"]            → hide next-opening time when closed
 *   [ssseo_places_status show_day="full"]          → "… opens Tuesday 9:00 AM" / "… closes Tuesday 6:00 PM"
 *
 * Reads saved options:
 *   - ssseo_google_places_api_key
 *   - ssseo_google_place_id
 *
 * Notes:
 * - Server-side only; no frontend AJAX; works for all visitors.
 * - API response cached via transients.
 * - Uses Places Details (legacy JSON).
 */


/**
 * MAIN SHORTCODE: [ssseo_places_status]
 */
if ( ! function_exists('ssseo_places_status_shortcode') ) {
  function ssseo_places_status_shortcode( $atts ) {

    // ---------- Attributes ----------
    $atts = shortcode_atts([
      'output'   => 'boolean', // boolean|text|badge
      'refresh'  => '900',     // transient lifetime (seconds), min 60
      'fallback' => 'unknown', // for text/badge when data unavailable
      'show_next'=> '1',       // when closed, append "– opens …"
      'show_day' => 'abbr',    // none|abbr|full for weekday label
    ], $atts, 'ssseo_places_status');

    $output    = strtolower(trim($atts['output']));
    $refresh   = max(60, (int) $atts['refresh']);
    $fallback  = (string) $atts['fallback'];
    $show_next = $atts['show_next'] === '1' || $atts['show_next'] === 'true';
    $show_day  = in_array($atts['show_day'], ['none','abbr','full'], true) ? $atts['show_day'] : 'abbr';

    // ---------- Options ----------
    $api_key  = trim((string) get_option('ssseo_google_places_api_key', ''));
    $place_id = trim((string) get_option('ssseo_google_place_id', ''));

    if ($api_key === '' || $place_id === '') {
      if ($output === 'boolean') return 'false';
      return esc_html($fallback);
    }

    // ---------- Cache ----------
    $t_key = 'ssseo_places_status_' . md5($place_id);
    $cached = get_transient($t_key);

    if (is_array($cached) && array_key_exists('open_now', $cached)) {
      $open_now = $cached['open_now']; // true|false|null
      $name     = $cached['name'] ?? '';
      $addr     = $cached['addr'] ?? '';
      $periods  = $cached['periods'] ?? [];
    } else {
      // ---------- Fetch fresh ----------
      $fields = [
        'name',
        'formatted_address',
        'opening_hours'
      ];

      $url = add_query_arg([
        'place_id' => $place_id,
        'fields'   => implode(',', $fields),
        'key'      => $api_key,
      ], 'https://maps.googleapis.com/maps/api/place/details/json');

      $resp = wp_remote_get($url, [
        'timeout' => 12,
        'headers' => [ 'Accept' => 'application/json' ],
      ]);

      $open_now = null;
      $name = $addr = '';
      $periods = [];

      if ( ! is_wp_error($resp) ) {
        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $json = json_decode($body, true);

        if ($code === 200 && is_array($json) && ($json['status'] ?? '') === 'OK') {
          $res      = $json['result'] ?? [];
          $name     = $res['name'] ?? '';
          $addr     = $res['formatted_address'] ?? '';
          $open_now = $res['opening_hours']['open_now'] ?? null;
          $periods  = $res['opening_hours']['periods'] ?? [];
        }
      }

      set_transient($t_key, [
        'open_now' => $open_now,
        'name'     => $name,
        'addr'     => $addr,
        'periods'  => $periods,
        'ts'       => time(),
      ], $refresh);
    }

    // ---------- Render per output ----------
    if ($output === 'boolean') {
      return ($open_now === true) ? 'true' : 'false';
    }

    if ($output === 'text' || $output === 'badge') {
      $label_text = 'Hours unavailable';
      $class = 'ssseo-hours-unknown';

      if ($open_now === true) {
        $close_str = ssseo_compute_current_closing_label($periods, $show_day);
        // Style only the word "Open" as green
        $label_text = '<span class="ssseo-status-open">Open</span> ' . esc_html__('now', 'ssseo');
        if ($close_str) {
          $label_text .= ' – ' . esc_html__('closes', 'ssseo') . ' ' . esc_html($close_str);
        }
        $class = 'ssseo-open-badge';

      } elseif ($open_now === false) {
        $next_str = $show_next ? ssseo_compute_next_open_label($periods, $show_day) : '';
        // Style only the word "Closed" as red
        $label_text = '<span class="ssseo-status-closed">Closed</span> ' . esc_html__('now', 'ssseo');
        if ($next_str) {
          $label_text .= ' – ' . esc_html__('opens', 'ssseo') . ' ' . esc_html($next_str);
        }
        $class = 'ssseo-closed-badge';
      }

      if ($output === 'badge') {
        $aria = $name ? ' aria-label="'.esc_attr($name.' status: '.wp_strip_all_tags($label_text)).'"' : '';
        return '<span class="'.esc_attr($class).'"'.$aria.'>'.$label_text.'</span>';
      }

      // output=text (HTML with just the status word colored)
      return $label_text;
    }

    return esc_html($fallback);
  }
  add_shortcode('ssseo_places_status', 'ssseo_places_status_shortcode');
}

/**
 * Compute the next opening time label using Places "periods" (weekly schedule).
 *
 * @param array  $periods  opening_hours.periods
 * @param string $show_day 'none'|'abbr'|'full'
 * @return string e.g. "Tue 9:00 AM" / "tomorrow 9:00 AM"
 */
if ( ! function_exists('ssseo_compute_next_open_label') ) {
  function ssseo_compute_next_open_label(array $periods, $show_day = 'abbr') {
    if (empty($periods)) return '';

    $now_ts  = current_time('timestamp');
    $now_w   = (int) date('w', $now_ts);
    $now_Hi  = (int) date('Hi', $now_ts);
    $now_y   = (int) date('Y', $now_ts);
    $now_m   = (int) date('n', $now_ts);
    $now_d   = (int) date('j', $now_ts);

    $norm = [];
    foreach ($periods as $p) {
      if (empty($p['open']['day']) || empty($p['open']['time'])) continue;
      $od = (int) $p['open']['day'];
      $ot = (string) $p['open']['time'];
      $cd = isset($p['close']['day'])  ? (int) $p['close']['day']  : null;
      $ct = isset($p['close']['time']) ? (string) $p['close']['time'] : null;
      $norm[] = [$od, $ot, $cd, $ct];
    }
    if (empty($norm)) return '';

    $best_ts = null;

    for ($i = 0; $i <= 7; $i++) {
      $day_idx = ($now_w + $i) % 7;

      foreach ($norm as [$od, $ot, $cd, $ct]) {
        if ($od !== $day_idx) continue;
        if ($i === 0 && (int) $ot <= $now_Hi) continue;

        $open_hour = (int) substr($ot, 0, 2);
        $open_min  = (int) substr($ot, 2, 2);

        $open_ts = mktime($open_hour, $open_min, 0, $now_m, $now_d + $i, $now_y);

        if ($best_ts === null || $open_ts < $best_ts) {
          $best_ts = $open_ts;
        }
      }
      if ($i === 0 && $best_ts !== null) break;
    }

    if ($best_ts === null) return '';

    $today_start = strtotime('today', $now_ts);
    $diff_days   = (int) floor( ($best_ts - $today_start) / DAY_IN_SECONDS );
    $prefix = '';

    if ($show_day === 'none') {
      $prefix = '';
    } else {
      if ($diff_days === 0) {
        $prefix = __('today', 'ssseo') . ' ';
      } elseif ($diff_days === 1) {
        $prefix = __('tomorrow', 'ssseo') . ' ';
      } else {
        $fmt = $show_day === 'full' ? 'l' : 'D';
        $prefix = date_i18n($fmt, $best_ts) . ' ';
      }
    }

    $time_str = date_i18n('g:i A', $best_ts);
    return trim($prefix . $time_str);
  }
}

/**
 * Compute the *current* closing time label if we are presently within one of the open periods.
 * Handles overnight intervals where close.day != open.day.
 *
 * @param array  $periods  opening_hours.periods
 * @param string $show_day 'none'|'abbr'|'full'
 * @return string e.g. "6:00 PM" / "tomorrow 1:00 AM" / "Tue 6:00 PM"
 */
if ( ! function_exists('ssseo_compute_current_closing_label') ) {
  function ssseo_compute_current_closing_label(array $periods, $show_day = 'abbr') {
    if (empty($periods)) return '';

    $now_ts   = current_time('timestamp');
    $now_w    = (int) date('w', $now_ts);
    $today_start = strtotime('today', $now_ts);

    // Normalize
    $norm = [];
    foreach ($periods as $p) {
      if (empty($p['open']['day']) || empty($p['open']['time'])) continue;
      if (!isset($p['close']['day'], $p['close']['time'])) continue; // need a close to compute
      $od = (int) $p['open']['day'];
      $ot = (string) $p['open']['time'];
      $cd = (int) $p['close']['day'];
      $ct = (string) $p['close']['time'];
      $norm[] = [$od, $ot, $cd, $ct];
    }
    if (empty($norm)) return '';

    $build_ts = function($base_ts, $offset_days, $HHMM) {
      $h = (int) substr($HHMM, 0, 2);
      $m = (int) substr($HHMM, 2, 2);
      return mktime($h, $m, 0,
        (int) date('n', $base_ts),
        (int) date('j', $base_ts) + $offset_days,
        (int) date('Y', $base_ts)
      );
    };

    $active_close = null;

    // Scan yesterday..next 7 days to catch overnight windows
    for ($k = -1; $k <= 7; $k++) {
      $scan_day_w = (int) date('w', $today_start + $k * DAY_IN_SECONDS);

      foreach ($norm as [$od, $ot, $cd, $ct]) {
        if ($scan_day_w !== $od) continue;

        $open_ts  = $build_ts($today_start, $k, $ot);
        $day_diff = ($cd - $od + 7) % 7;
        $close_ts = $build_ts($open_ts, $day_diff, $ct);

        if ($close_ts <= $open_ts) {
          $close_ts += 7 * DAY_IN_SECONDS;
        }

        if ($now_ts >= $open_ts && $now_ts < $close_ts) {
          $active_close = $close_ts;
          break 2;
        }
      }
    }

    if ($active_close === null) return '';

    $diff_days = (int) floor( ($active_close - $today_start) / DAY_IN_SECONDS );
    $prefix = '';
    if ($show_day === 'none' || $diff_days === 0) {
      $prefix = '';
    } else {
      if ($diff_days === 1) {
        $prefix = __('tomorrow', 'ssseo') . ' ';
      } else {
        $fmt = $show_day === 'full' ? 'l' : 'D';
        $prefix = date_i18n($fmt, $active_close) . ' ';
      }
    }

    $time_str = date_i18n('g:i A', $active_close);
    return trim($prefix . $time_str);
  }
}

/**
 * Styles:
 * - .ssseo-status-open   → green text (only the word "Open")
 * - .ssseo-status-closed → red text (only the word "Closed")
 * - badge styles kept from earlier implementation
 */
if ( ! function_exists('ssseo_places_badge_css') ) {
  function ssseo_places_badge_css() {
    $css = '
    /* Word-only coloring for text output */
    .ssseo-status-open{ color:green; font-weight:600; }
    .ssseo-status-closed{ color:red; font-weight:600; }

    /* Badge variants (unchanged) */
    .ssseo-open-badge{display:inline-block;padding:.15rem .5rem;border-radius:.35rem;font-weight:600;background:#e7f7ec;color:#1e7e34;border:1px solid #cfead7}
    .ssseo-closed-badge{display:inline-block;padding:.15rem .5rem;border-radius:.35rem;font-weight:600;background:#fdeeee;color:#b02a37;border:1px solid #f5c2c7}
    .ssseo-hours-unknown{display:inline-block;padding:.15rem .5rem;border-radius:.35rem;font-weight:600;background:#eef2f7;color:#495057;border:1px solid #d0d7e2}
    ';
    wp_register_style('ssseo-places-badge', false);
    wp_enqueue_style('ssseo-places-badge');
    wp_add_inline_style('ssseo-places-badge', $css);
  }
  add_action('wp_enqueue_scripts', 'ssseo_places_badge_css');
}

/**
 * (Optional) Tiny alias for boolean-only use:
 *   [gbp_open_now] → "true"|"false"
 */
if ( ! function_exists('ssseo_gbp_open_now_alias') ) {
  function ssseo_gbp_open_now_alias($atts) {
    $atts = shortcode_atts(['refresh' => '900'], $atts, 'gbp_open_now');
    return do_shortcode('[ssseo_places_status output="boolean" refresh="'.intval($atts['refresh']).'"]');
  }
  add_shortcode('gbp_open_now', 'ssseo_gbp_open_now_alias');
}


/**
 * Shortcode: [gmb_hours place_id="YOUR_PLACE_ID"]
 *
 * Footer-friendly hours list pulled from Google Places Details API.
 * - Uses the plugin’s saved Google key (via ssseo_get_google_places_api_key()).
 * - Renders compact markup ideal for a footer/widget.
 *
 * Attributes:
 *  - place_id          (string, REQUIRED)  Google Place ID for the business
 *  - show              (string, optional)  "week" (default) or "today"
 *  - show_today_first  (0|1, optional)     Reorder so today is first (default 0)
 *  - highlight_today   (0|1, optional)     Add .today class to today’s row (default 1)
 *  - compact           (0|1, optional)     Adds small, muted classes (default 1)
 *  - class             (string, optional)  Extra classes on wrapper <div> (default "")
 *  - list_class        (string, optional)  Extra classes on the <ul class="hours-list"> (default "")
 *  - cache             (int, optional)     Cache minutes (default 60)
 *  - debug             (0|1, optional)     Show API errors (default 0)
 *
 * Examples:
 *   [gmb_hours place_id="ChIJxxxxxxxxxxxx"]
 *   [gmb_hours place_id="ChIJxxxxxxxxxxxx" show="today"]
 *   [gmb_hours place_id="ChIJxxxxxxxxxxxx" show="week" show_today_first="1" highlight_today="1"]
 *   [gmb_hours place_id="ChIJxxxxxxxxxxxx" list_class="my-custom-ul gap-1"]
 */
if ( ! function_exists( 'ssseo_gmb_hours_shortcode' ) ) {
	function ssseo_gmb_hours_shortcode( $atts ) {
		$atts = shortcode_atts(
			[
				'place_id'         => '',
				'show'             => 'week', // 'week' or 'today'
				'show_today_first' => '0',
				'highlight_today'  => '1',
				'compact'          => '1',
				'class'            => '',     // wrapper extra classes
				'list_class'       => '',     // NEW: <ul> extra classes
				'cache'            => '60',
				'debug'            => '0',
			],
			$atts,
			'gmb_hours'
		);

		$place_id         = trim( (string) $atts['place_id'] );
		$show             = strtolower( trim( (string) $atts['show'] ) );
		$show_today_first = $atts['show_today_first'] === '1';
		$highlight_today  = $atts['highlight_today'] === '1';
		$compact          = $atts['compact'] === '1';
		$extra_class      = trim( (string) $atts['class'] );
		$list_extra_class = trim( (string) $atts['list_class'] ); // NEW
		$cache_min        = max( 0, (int) $atts['cache'] );
		$debug            = $atts['debug'] === '1';

		if ( $place_id === '' ) {
			return '<div class="gmb-hours error small text-danger">Missing place_id</div>';
		}

		// 🔑 Get Google key from SSSEO Tools options (helper defined below if not already).
		if ( ! function_exists( 'ssseo_get_google_places_api_key' ) ) {
			function ssseo_get_google_places_api_key(): string {
				$candidates = [
					'ssseo_google_places_api_key',
					'ssseo_google_api_key',
					'ssseo_google_static_maps_api_key',
				];
				foreach ( $candidates as $opt ) {
					$val = trim( (string) get_option( $opt, '' ) );
					if ( $val !== '' ) {
						return (string) apply_filters( 'ssseo_google_places_api_key', $val, $opt );
					}
				}
				if ( defined( 'SSSEO_GOOGLE_API_KEY' ) && SSSEO_GOOGLE_API_KEY ) {
					return (string) apply_filters( 'ssseo_google_places_api_key', SSSEO_GOOGLE_API_KEY, 'constant' );
				}
				return (string) apply_filters( 'ssseo_google_places_api_key', '', 'none' );
			}
		}
		$api_key = ssseo_get_google_places_api_key();
		if ( $api_key === '' ) {
			return '<div class="gmb-hours error small text-danger">Missing Google API key (Places). Save it in SSSEO Tools settings.</div>';
		}

		// 🧠 Cache
		$transient_key = 'ssseo_gmb_hours_' . md5( $place_id );
		if ( $cache_min > 0 ) {
			$cached = get_transient( $transient_key );
			if ( is_array( $cached ) ) {
				return ssseo_render_hours_box(
					$cached,
					compact( 'show', 'show_today_first', 'highlight_today', 'compact', 'extra_class', 'list_extra_class' )
				);
			}
		}

		// 📡 Request: ask for both opening_hours and current_opening_hours (some places return one or the other).
		$url = add_query_arg(
			[
				'place_id' => $place_id,
				'fields'   => 'opening_hours,current_opening_hours',
				'key'      => $api_key,
			],
			'https://maps.googleapis.com/maps/api/place/details/json'
		);

		$response = wp_remote_get( $url, [ 'timeout' => 10 ] );
		if ( is_wp_error( $response ) ) {
			return $debug
				? '<div class="gmb-hours error small text-danger">HTTP error: ' . esc_html( $response->get_error_message() ) . '</div>'
				: '<div class="gmb-hours error small text-danger">Service unavailable</div>';
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 || ! is_array( $data ) ) {
			return $debug
				? '<div class="gmb-hours error small text-danger">Bad HTTP: ' . esc_html( (string) $code ) . '</div>'
				: '<div class="gmb-hours error small text-danger">Service error</div>';
		}

		$status = $data['status'] ?? 'UNKNOWN';
		if ( $status !== 'OK' ) {
			$err = $data['error_message'] ?? 'API error';
			return $debug
				? '<div class="gmb-hours error small text-danger">API error: ' . esc_html( $status . ' - ' . $err ) . '</div>'
				: '<div class="gmb-hours error small text-danger">Service error</div>';
		}

		// Prefer current_opening_hours if present (reflects special hours), else opening_hours.
		$hours = $data['result']['current_opening_hours'] ?? $data['result']['opening_hours'] ?? [];
		$weekday_text = isset( $hours['weekday_text'] ) && is_array( $hours['weekday_text'] )
			? $hours['weekday_text']
			: [];

		$normalized = [
			'weekday_text' => $weekday_text, // array like ["Monday: 9 AM–5 PM", ...]
		];

		if ( $cache_min > 0 ) {
			set_transient( $transient_key, $normalized, $cache_min * MINUTE_IN_SECONDS );
		}

		return ssseo_render_hours_box(
			$normalized,
			compact( 'show', 'show_today_first', 'highlight_today', 'compact', 'extra_class', 'list_extra_class' )
		);
	}
	add_shortcode( 'gmb_hours', 'ssseo_gmb_hours_shortcode' );
}

/**
 * Renderer for compact hours box.
 *
 * @param array $data ['weekday_text' => [ "Monday: …", … ]]
 * @param array $opts ['show','show_today_first','highlight_today','compact','extra_class','list_extra_class']
 * @return string HTML
 */
if ( ! function_exists( 'ssseo_render_hours_box' ) ) {
	function ssseo_render_hours_box( array $data, array $opts ): string {
		$weekday_text     = is_array( $data['weekday_text'] ?? null ) ? $data['weekday_text'] : [];
		$show             = $opts['show'] ?? 'week';
		$show_today_first = (bool) ( $opts['show_today_first'] ?? false );
		$highlight_today  = (bool) ( $opts['highlight_today']  ?? true );
		$compact          = (bool) ( $opts['compact'] ?? true );
		$extra_class      = trim( (string) ( $opts['extra_class'] ?? '' ) );
		$list_extra_class = trim( (string) ( $opts['list_extra_class'] ?? '' ) ); // NEW

		// Wrapper classes
		$wrapper_classes = [ 'gmb-hours' ];
		if ( $compact ) {
			$wrapper_classes[] = 'small';
			$wrapper_classes[] = 'text-muted';
		}
		if ( $extra_class !== '' ) {
			$wrapper_classes = array_merge( $wrapper_classes, preg_split( '/\s+/', $extra_class ) );
		}
		$wrapper_classes = array_values( array_filter( array_unique( $wrapper_classes ) ) );
		$wrapper_classes = apply_filters( 'ssseo_gmb_hours_wrapper_classes', $wrapper_classes, $opts, $data );

		// Build associative array Day => Hours string. Google returns Monday..Sunday.
		$week = [];
		foreach ( $weekday_text as $line ) {
			if ( strpos( $line, ':' ) !== false ) {
				[ $day, $hours ] = array_map( 'trim', explode( ':', $line, 2 ) );
				$week[ $day ] = $hours;
			}
		}

		if ( empty( $week ) ) {
			return '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '"><em>Hours unavailable</em></div>';
		}

		// Local today label (English day to match Google's output)
		$today_label = wp_date( 'l' ); // e.g., "Tuesday"

		// Optionally reorder so today appears first
		if ( $show === 'week' && $show_today_first && isset( $week[ $today_label ] ) ) {
			$today_pair = [ $today_label => $week[ $today_label ] ];
			unset( $week[ $today_label ] );
			$week = $today_pair + $week; // Today first, then rest
		}

		// UL classes (includes fixed 'hours-list' plus any extra classes passed in)
		$ul_classes = [ 'list-unstyled', 'mb-0', 'hours-list' ];
		if ( $list_extra_class !== '' ) {
			$ul_classes = array_merge( $ul_classes, preg_split( '/\s+/', $list_extra_class ) );
		}
		$ul_classes = array_values( array_filter( array_unique( $ul_classes ) ) );
		$ul_classes = apply_filters( 'ssseo_gmb_hours_ul_classes', $ul_classes, $opts, $data ); // NEW filter

		// Render
		ob_start();
		echo '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '">';

		if ( $show === 'today' ) {
			$today_hours = $week[ $today_label ] ?? '';
			$today_hours = $today_hours === '' ? 'Closed' : $today_hours;
			echo '<div class="today-hours"><strong>Today:</strong> ' . esc_html( $today_hours ) . '</div>';
			echo '</div>';
			return ob_get_clean();
		}

		// Full week list
		echo '<ul class="' . esc_attr( implode( ' ', $ul_classes ) ) . '">';
		foreach ( $week as $day => $hours ) {
			$is_today = ( $day === $today_label );
			$li_class = $is_today && $highlight_today ? ' class="today fw-semibold"' : '';
			echo '<li' . $li_class . '>';
			echo '<span class="day">' . esc_html( $day ) . ':</span> ';
			echo '<span class="hours">' . esc_html( $hours ?: 'Closed' ) . '</span>';
			echo '</li>';
		}
		echo '</ul>';
		echo '</div>';

		return ob_get_clean();
	}
}

//Deprecated, Preserve to work with old shortcodes
//
function service_area_list_shortcode() {
    // Get the current post ID
    if (is_singular('service_area')) {
        $current_post_id = get_the_ID();
    } else {
        $current_post_id = 0;
    }

   // Query for 'service_area' posts with post_parent of '0' and alphabetize the post listing
$args = array(
    'post_type'      => 'service_area',
    'post_parent'    => 0,
    'posts_per_page' => -1,
    'orderby'        => 'title',  // Order by the title
    'order'          => 'ASC',    // Order in ascending order
);

    // Exclude the current post if on a service_area post page
    if ($current_post_id) {
        $args['post__not_in'] = array($current_post_id);
    }

    $service_areas = new WP_Query($args);

    // Check if there are any posts
    if ($service_areas->have_posts()) {
        // Initialize output variable
        $output = '<div class="container service-areas"><div class="row">';
        $output .= '<div class="col-lg-12">';
        $output .= '<ul class="list-unstyled service-area-list">';

        // Loop through posts and build the list items
        while ($service_areas->have_posts()) {
            $service_areas->the_post();
            $output .= '
                <li>
                    <i class="fa fa-map-marker ssseo-icon"></i>
                    <a href="' . get_permalink() . '" class="service-area-link">' . get_the_title() . '</a>
                </li>';
        }

        $output .= '</ul></div></div></div>';

        // Reset post data
        wp_reset_postdata();

        return $output;
    } else {
        return '<p>No service areas found.</p>';
    }
}

// Register the shortcode
add_shortcode('service_area_list', 'service_area_list_shortcode');