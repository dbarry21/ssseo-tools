<?php
// File: includes/gsc-url-inspection.php
if (!defined('ABSPATH')) exit;

/** Option key maps */
function ssseo_gsc_default_option_keys() {
    return [
        'client_id'      => 'ssseo_gsc_client_id',
        'client_secret'  => 'ssseo_gsc_client_secret',
        'access_token'   => 'ssseo_gsc_token',
        'refresh_token'  => 'ssseo_gsc_refresh_token',
        'expires_at'     => 'ssseo_gsc_token_expires',
    ];
}
function ssseo_gsc_default_siteoptions_candidates() { return [ 'ssseo_site_options', 'ssseo_siteoptions', 'ssseo_settings' ]; }
function ssseo_gsc_default_siteoptions_keys() {
    return [
        'client_id'      => ['gsc_client_id','ssseo_gsc_client_id'],
        'client_secret'  => ['gsc_client_secret','ssseo_gsc_client_secret'],
        'access_token'   => ['gsc_access_token','ssseo_gsc_token'],
        'refresh_token'  => ['gsc_refresh_token','ssseo_gsc_refresh_token'],
        'expires_at'     => ['gsc_token_expires','ssseo_gsc_token_expires'],
    ];
}

/** Read config — supports nested token array */
function ssseo_gsc_get_oauth_config($want_debug = false) {
    $flat_keys   = apply_filters('ssseo_gsc_option_keys', ssseo_gsc_default_option_keys());
    $candidates  = apply_filters('ssseo_gsc_siteoptions_candidates', ssseo_gsc_default_siteoptions_candidates());
    $array_keys  = apply_filters('ssseo_gsc_siteoptions_keys', ssseo_gsc_default_siteoptions_keys());

    $flat_access_raw = get_option($flat_keys['access_token']);
    $flat = [
        'client_id'     => get_option($flat_keys['client_id']),
        'client_secret' => get_option($flat_keys['client_secret']),
        'access_token'  => is_array($flat_access_raw) ? ($flat_access_raw['access_token'] ?? '') : $flat_access_raw,
        'refresh_token' => get_option($flat_keys['refresh_token']),
        'expires_at'    => (int) get_option($flat_keys['expires_at']),
        '__flat_raw'    => $flat_access_raw,
    ];
    if (empty($flat['refresh_token']) && is_array($flat_access_raw) && !empty($flat_access_raw['refresh_token'])) $flat['refresh_token'] = $flat_access_raw['refresh_token'];
    if (empty($flat['expires_at'])    && is_array($flat_access_raw) && !empty($flat_access_raw['expires_at']))    $flat['expires_at']    = (int) $flat_access_raw['expires_at'];

    $flat_has_core = !empty($flat['client_id']) && !empty($flat['client_secret']) && !empty($flat['refresh_token']);

    $container_name = null; $site = null;
    foreach ($candidates as $opt_name) { $val = get_option($opt_name); if (is_array($val) && !empty($val)) { $container_name = $opt_name; $site = $val; break; } }
    $from_site = ['client_id'=>'','client_secret'=>'','access_token'=>'','refresh_token'=>'','expires_at'=>0,'__site_raw'=>$site];
    if ($site) {
        foreach ($array_keys as $k => $alts) {
            foreach ($alts as $keyname) { if (isset($site[$keyname]) && $site[$keyname] !== '') { $from_site[$k] = $site[$keyname]; break; } }
        }
        if (is_array($from_site['access_token'])) {
            $tok = $from_site['access_token'];
            $from_site['access_token']  = $tok['access_token']  ?? '';
            if (empty($from_site['refresh_token']) && !empty($tok['refresh_token'])) $from_site['refresh_token'] = $tok['refresh_token'];
            if (empty($from_site['expires_at'])    && !empty($tok['expires_at']))    $from_site['expires_at']    = (int) $tok['expires_at'];
        }
    }
    $site_has_core = $site && !empty($from_site['client_id']) && !empty($from_site['client_secret']) && !empty($from_site['refresh_token']);

    if ($flat_has_core) {
        $cfg = [
            'client_id'     => (string) $flat['client_id'],
            'client_secret' => (string) $flat['client_secret'],
            'access_token'  => (string) $flat['access_token'],
            'refresh_token' => (string) $flat['refresh_token'],
            'expires_at'    => (int)    $flat['expires_at'],
            '__source'      => 'flat',
            '__container'   => '',
        ];
    } elseif ($site_has_core) {
        $cfg = [
            'client_id'     => (string) $from_site['client_id'],
            'client_secret' => (string) $from_site['client_secret'],
            'access_token'  => (string) $from_site['access_token'],
            'refresh_token' => (string) $from_site['refresh_token'],
            'expires_at'    => (int)    $from_site['expires_at'],
            '__source'      => 'siteoptions',
            '__container'   => $container_name,
        ];
    } else {
        return new WP_Error('oauth_misconfig','OAuth client not configured. Please reconnect Search Console.',[
            'flat_keys'=>$flat_keys,'flat_values'=>$flat,'site_option_container'=>$container_name?:'(none found)','site_values'=>$from_site,
            'missing_core'=>['client_id','client_secret','refresh_token'],
            'hint'=>'Your token may be stored as an array; this loader supports it and writes back flat values after refresh.',
        ]);
    }

    if ($want_debug) { $cfg['__flat_values']=$flat; $cfg['__site_values']=$from_site; $cfg['__flat_keys']=$flat_keys; $cfg['__site_keys']=$array_keys; $cfg['__container_raw']=$site?:[]; }
    return $cfg;
}

/** Persist refreshed tokens back to flat (and mirror into siteoptions if used) */
function ssseo_gsc_store_tokens($access_token, $expires_in, $maybe_refresh_token = null) {
    $cfg = ssseo_gsc_get_oauth_config(); if (is_wp_error($cfg)) return $cfg;
    $expires_at = time() + (int) $expires_in;

    $flat_keys = apply_filters('ssseo_gsc_option_keys', ssseo_gsc_default_option_keys());
    update_option($flat_keys['access_token'],  sanitize_text_field($access_token));
    update_option($flat_keys['expires_at'],    $expires_at);
    if (!empty($maybe_refresh_token)) update_option($flat_keys['refresh_token'], sanitize_text_field($maybe_refresh_token));

    if ($cfg['__source'] === 'siteoptions' && $cfg['__container']) {
        $container = get_option($cfg['__container']); if (!is_array($container)) $container = [];
        $keys_map  = ssseo_gsc_default_siteoptions_keys();
        foreach ($keys_map['access_token'] as $k) { $container[$k] = $access_token; }
        foreach ($keys_map['expires_at']  as $k) { $container[$k] = $expires_at; }
        if (!empty($maybe_refresh_token)) { foreach ($keys_map['refresh_token'] as $k) { $container[$k] = $maybe_refresh_token; } }
        update_option($cfg['__container'], $container);
    }
    return true;
}

/** Access token (refresh if expiring) */
function ssseo_gsc_get_access_token() {
    $cfg = ssseo_gsc_get_oauth_config(); if (is_wp_error($cfg)) return $cfg;
    if (empty($cfg['access_token']) || (int)$cfg['expires_at'] < (time() + 60)) {
        $ref = ssseo_gsc_refresh_access_token(); if (is_wp_error($ref)) return $ref;
        $cfg = ssseo_gsc_get_oauth_config(); if (is_wp_error($cfg)) return $cfg;
    }
    return $cfg['access_token'] ?: new WP_Error('no_token','Missing OAuth token after refresh.');
}

/** Refresh access token */
function ssseo_gsc_refresh_access_token() {
    $cfg = ssseo_gsc_get_oauth_config(); if (is_wp_error($cfg)) return $cfg;
    $client_id = trim((string)$cfg['client_id']); $client_secret = trim((string)$cfg['client_secret']); $refresh_token = trim((string)$cfg['refresh_token']);
    if (!$client_id || !$client_secret || !$refresh_token) return new WP_Error('oauth_misconfig','OAuth client not configured. Please reconnect Search Console.');

    $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
        'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
        'body'    => [ 'grant_type'=>'refresh_token','client_id'=>$client_id,'client_secret'=>$client_secret,'refresh_token'=>$refresh_token ],
        'timeout' => 20,
    ]);
    if (is_wp_error($resp)) return $resp;
    $code = wp_remote_retrieve_response_code($resp);
    $raw  = wp_remote_retrieve_body($resp);
    $json = json_decode($raw, true);
    if ($code !== 200 || empty($json['access_token'])) return new WP_Error('oauth_refresh_failed','Failed to refresh token', ['status'=>$code,'body'=>$raw]);

    return ssseo_gsc_store_tokens($json['access_token'], (int)($json['expires_in'] ?? 3600), $json['refresh_token'] ?? null);
}

/** Call URL Inspection API */
function ssseo_gsc_inspect_url($site_url, $inspection_url, $language = 'en-US') {
    $access = ssseo_gsc_get_access_token(); if (is_wp_error($access)) return $access;
    $payload = [ 'inspectionUrl'=>$inspection_url, 'siteUrl'=>$site_url, 'languageCode'=>$language ];
    $resp = wp_remote_post('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
        'headers' => [ 'Content-Type'=>'application/json', 'Authorization'=>'Bearer ' . $access ],
        'body'    => wp_json_encode($payload),
        'timeout' => 30,
    ]);
    if (is_wp_error($resp)) return $resp;
    $code = wp_remote_retrieve_response_code($resp);
    $raw  = wp_remote_retrieve_body($resp);
    $json = json_decode($raw, true);
    if ($code !== 200) {
        $message = !empty($json['error']['message']) ? $json['error']['message'] : 'URL Inspection API error';
        return new WP_Error('gsc_api_error', $message, [ 'status'=>$code, 'body'=>$raw ]);
    }
    return $json;
}

/** Shape response for UI (STRICT is_indexed) */
function ssseo_gsc_extract_fields($api_json) {
    $out = [
        'inspectionUrl'   => '',
        'coverage'        => '',
        'indexingState'   => '',
        'lastCrawlTime'   => '',
        'pageFetchState'  => '',
        'robotsTxtState'  => '',
        'googleCanonical' => '',
        'userCanonical'   => '',
        'sitemaps'        => [],
        'is_indexed'      => false,
        'raw'             => $api_json,
        'openInGsc'       => '',
        'verdict'         => '',
    ];
    if (!is_array($api_json)) return $out;

    $res = $api_json['inspectionResult'] ?? [];
    $idx = $res['indexStatusResult'] ?? [];

    $out['inspectionUrl']   = $res['inspectionUrl'] ?? '';
    $out['verdict']         = $res['verdict'] ?? '';
    $out['coverage']        = $idx['coverageState'] ?? '';
    $out['indexingState']   = $idx['indexingState'] ?? '';
    $out['lastCrawlTime']   = $idx['lastCrawlTime'] ?? '';
    $out['pageFetchState']  = $idx['pageFetchState'] ?? '';
    $out['robotsTxtState']  = $idx['robotsTxtState'] ?? '';
    $out['googleCanonical'] = $idx['googleCanonical'] ?? '';
    $out['userCanonical']   = $idx['userCanonical'] ?? '';
    $out['sitemaps']        = $idx['referringSitemaps'] ?? [];

    // STRICT: Indexed only when verdict PASS AND coverage contains "indexed" but NOT "not indexed"
    $coverage_norm = preg_replace('/\s+/', ' ', strtolower((string) $out['coverage']));
    $verdict_low   = strtolower((string) $out['verdict']);
    $has_indexed   = (strpos($coverage_norm, 'indexed') !== false);
    $has_not_idx   = (strpos($coverage_norm, 'not indexed') !== false);
    $out['is_indexed'] = ($verdict_low === 'pass' && $has_indexed && !$has_not_idx);

    if (!empty($out['inspectionUrl'])) {
        $resource = $GLOBALS['_ssseo_gsc_current_site_url_for_link'] ?? '';
        $out['openInGsc'] = 'https://search.google.com/search-console/inspect?resource_id=' . rawurlencode($resource) . '&url=' . rawurlencode($out['inspectionUrl']);
    }
    return $out;
}

/** AJAX: options for selector */
add_action('wp_ajax_ssseo_get_posts_by_type', function () {
    if (!current_user_can('edit_posts')) wp_send_json_error('Insufficient permissions');
    $pt = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : 'post';
    $posts = get_posts([ 'post_type'=>$pt, 'posts_per_page'=>200, 'post_status'=>['publish'], 'orderby'=>'date', 'order'=>'DESC', 'suppress_filters'=>true, 'fields'=>['ids'], 'no_found_rows'=>true ]);
    if (!$posts) wp_send_json_success('<option value="">No posts found.</option>');
    $buf = '';
    foreach ($posts as $pid) { $buf .= sprintf('<option value="%d">%s (ID %d)</option>', (int)$pid, esc_html(get_the_title($pid) ?: '(no title)'), (int)$pid ); }
    wp_send_json_success($buf);
});

/** AJAX: Inspect WP post */
add_action('wp_ajax_ssseo_gsc_inspect_post', function () {
    if (!current_user_can('edit_posts')) wp_send_json_error('Insufficient permissions');
    check_ajax_referer('ssseo_gsc_ops', 'nonce');

    $post_id  = isset($_POST['post_id'])  ? (int) $_POST['post_id'] : 0;
    $site_url = isset($_POST['site_url']) ? esc_url_raw(wp_unslash($_POST['site_url'])) : '';
    if (!$post_id) wp_send_json_error('Missing post_id');
    if (!$site_url) wp_send_json_error('Missing site_url');

    $url = get_permalink($post_id); if (!$url) wp_send_json_error('Could not resolve permalink for post.');
    $GLOBALS['_ssseo_gsc_current_site_url_for_link'] = $site_url;

    $api = ssseo_gsc_inspect_url($site_url, $url, 'en-US');
    if (is_wp_error($api)) { wp_send_json_error([ 'message'=>$api->get_error_message(), 'detail'=>$api->get_error_data() ]); }

    $out = ssseo_gsc_extract_fields($api);
    if (empty($out['inspectionUrl'])) $out['inspectionUrl'] = $url; // fallback so URL never blank
    wp_send_json_success($out);
});

/** AJAX: Inspect manual URL */
add_action('wp_ajax_ssseo_gsc_inspect_manual', function () {
    if (!current_user_can('edit_posts')) wp_send_json_error('Insufficient permissions');
    check_ajax_referer('ssseo_gsc_ops', 'nonce');

    $manual_url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
    $site_url   = isset($_POST['site_url']) ? esc_url_raw(wp_unslash($_POST['site_url'])) : '';
    if (!$manual_url) wp_send_json_error('Missing URL to inspect.');
    if (!$site_url)   wp_send_json_error('Missing site_url (GSC property).');
    if (!wp_http_validate_url($manual_url)) wp_send_json_error('URL is not valid.');

    $GLOBALS['_ssseo_gsc_current_site_url_for_link'] = $site_url;

    $api = ssseo_gsc_inspect_url($site_url, $manual_url, 'en-US');
    if (is_wp_error($api)) { wp_send_json_error([ 'message'=>$api->get_error_message(), 'detail'=>$api->get_error_data() ]); }

    $out = ssseo_gsc_extract_fields($api);
    if (empty($out['inspectionUrl'])) $out['inspectionUrl'] = $manual_url; // fallback so URL never blank
    wp_send_json_success($out);
});
