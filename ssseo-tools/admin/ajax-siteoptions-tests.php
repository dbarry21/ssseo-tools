<?php
/**
 * Admin AJAX: Site Options Testers
 * Implements the 6 testers used by the Site Options tab:
 *  - ssseo_test_places_key
 *  - ssseo_test_places_pid
 *  - ssseo_test_maps_key
 *  - ssseo_test_openai_key
 *  - ssseo_test_youtube_api
 *  - ssseo_test_gsc_client
 *
 * Saves human-readable results to:
 *  - ssseo_places_test_result
 *  - ssseo_places_pid_test_result
 *  - ssseo_maps_test_result
 *  - ssseo_openai_test_result
 *  - ssseo_youtube_test_result
 *  - ssseo_gsc_test_result
 *
 * Security:
 *  - Requires `edit_posts`
 *  - Nonce: `ssseo_siteoptions_ajax`
 */

if (!defined('ABSPATH')) exit;

/** Utility: uniform success/error JSON + store "last test" message. */
function ssseo_ajax_test__respond($ok, $message, $option_key_for_last_test) {
    if (is_string($option_key_for_last_test) && $option_key_for_last_test !== '') {
        update_option($option_key_for_last_test, $message);
    }
    if ($ok) {
        wp_send_json_success(['message' => $message]);
    } else {
        wp_send_json_error(['message' => $message]);
    }
}

function ssseo_ajax_test__check_caps_and_nonce() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Insufficient permissions.']);
    }
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'ssseo_siteoptions_ajax')) {
        wp_send_json_error(['message' => 'Invalid nonce. Reload and try again.']);
    }
}

/**
 * 1) Google Places API key test
 *    Quick, low-cost probe using Places Details with a known invalid place to check auth header behavior.
 */
add_action('wp_ajax_ssseo_test_places_key', function () {
    ssseo_ajax_test__check_caps_and_nonce();

    $key = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';
    if ($key === '') {
        ssseo_ajax_test__respond(false, 'Enter an API key first.', 'ssseo_places_test_result');
    }

    // Call a harmless endpoint to verify key validity; using a dummy place_id returns an error structure that still proves the key itself is accepted.
    $url = add_query_arg([
        'place_id' => 'ChIJFFFFFFFFFFFFFFFFFFFFFake', // bad on purpose
        'fields'   => 'name',
        'key'      => $key,
    ], 'https://maps.googleapis.com/maps/api/place/details/json');

    $resp = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($resp)) {
        ssseo_ajax_test__respond(false, 'Network error: '.$resp->get_error_message(), 'ssseo_places_test_result');
    }

    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);

    if ($code !== 200 || !is_array($body)) {
        ssseo_ajax_test__respond(false, 'Unexpected response from Places API (HTTP '.$code.').', 'ssseo_places_test_result');
    }

    // Good keys usually yield "NOT_FOUND" for fake place, while bad keys return "REQUEST_DENIED".
    $status = $body['status'] ?? 'UNKNOWN';
    if ($status === 'REQUEST_DENIED') {
        $reason = $body['error_message'] ?? 'REQUEST_DENIED';
        ssseo_ajax_test__respond(false, 'Key rejected: '.$reason, 'ssseo_places_test_result');
    }

    // Treat anything other than REQUEST_DENIED as “key accepted”
    ssseo_ajax_test__respond(true, 'Places API key looks valid (status: '.$status.').', 'ssseo_places_test_result');
});

/**
 * 2) Google Places default Place ID test
 *    Confirms that a given Place ID resolves with your key.
 */
add_action('wp_ajax_ssseo_test_places_pid', function () {
    ssseo_ajax_test__check_caps_and_nonce();

    $key = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';
    $pid = isset($_POST['place_id']) ? sanitize_text_field(wp_unslash($_POST['place_id'])) : '';

    if ($key === '')  ssseo_ajax_test__respond(false, 'Enter a Places API key first.', 'ssseo_places_pid_test_result');
    if ($pid === '')  ssseo_ajax_test__respond(false, 'Enter a Place ID to test.', 'ssseo_places_pid_test_result');

    $url = add_query_arg([
        'place_id' => $pid,
        'fields'   => 'name,formatted_address,opening_hours/weekday_text',
        'key'      => $key,
    ], 'https://maps.googleapis.com/maps/api/place/details/json');

    $resp = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($resp)) {
        ssseo_ajax_test__respond(false, 'Network error: '.$resp->get_error_message(), 'ssseo_places_pid_test_result');
    }

    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);

    if ($code !== 200 || !is_array($body)) {
        ssseo_ajax_test__respond(false, 'Unexpected response from Places API (HTTP '.$code.').', 'ssseo_places_pid_test_result');
    }

    $status = $body['status'] ?? 'UNKNOWN';
    if ($status !== 'OK') {
        $reason = $body['error_message'] ?? $status;
        ssseo_ajax_test__respond(false, 'Place ID failed: '.$reason, 'ssseo_places_pid_test_result');
    }

    $name = $body['result']['name'] ?? '(name unavailable)';
    $addr = $body['result']['formatted_address'] ?? '';
    ssseo_ajax_test__respond(true, 'Place ID OK: '.$name.($addr ? ' — '.$addr : ''), 'ssseo_places_pid_test_result');
});

/**
 * 3) Google Static Maps key test
 *    We request a tiny static map; if the key is bad, Google typically returns a non-image or an error overlay.
 */
add_action('wp_ajax_ssseo_test_maps_key', function () {
    ssseo_ajax_test__check_caps_and_nonce();

    $key = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';
    if ($key === '') ssseo_ajax_test__respond(false, 'Enter a Static Maps API key first.', 'ssseo_maps_test_result');

    $url = add_query_arg([
        'center' => '0,0',
        'zoom'   => '1',
        'size'   => '120x60',
        'scale'  => '1',
        'key'    => $key,
        'format' => 'png',
    ], 'https://maps.googleapis.com/maps/api/staticmap');

    $resp = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($resp)) {
        ssseo_ajax_test__respond(false, 'Network error: '.$resp->get_error_message(), 'ssseo_maps_test_result');
    }

    $code = wp_remote_retrieve_response_code($resp);
    $ctype = wp_remote_retrieve_header($resp, 'content-type');

    if ($code !== 200) {
        ssseo_ajax_test__respond(false, 'Static Maps responded HTTP '.$code.'.', 'ssseo_maps_test_result');
    }
    // Expect an image content-type; if not, key likely invalid or billing not enabled.
    if (stripos($ctype, 'image/') === false) {
        ssseo_ajax_test__respond(false, 'Unexpected content-type ('.$ctype.'). Verify key and billing.', 'ssseo_maps_test_result');
    }

    ssseo_ajax_test__respond(true, 'Static Maps key looks valid (image returned).', 'ssseo_maps_test_result');
});

/**
 * 4) OpenAI API key test
 *    Lightweight probe against /v1/models (header-only GET is enough to detect 401 vs 200/403).
 */
add_action('wp_ajax_ssseo_test_openai_key', function () {
    ssseo_ajax_test__check_caps_and_nonce();

    $key = isset($_POST['key']) ? trim((string) wp_unslash($_POST['key'])) : '';
    if ($key === '') ssseo_ajax_test__respond(false, 'Enter an OpenAI API key first.', 'ssseo_openai_test_result');

    $resp = wp_remote_get('https://api.openai.com/v1/models', [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
        ],
    ]);
    if (is_wp_error($resp)) {
        ssseo_ajax_test__respond(false, 'Network error: '.$resp->get_error_message(), 'ssseo_openai_test_result');
    }

    $code = wp_remote_retrieve_response_code($resp);
    if ($code === 401) {
        ssseo_ajax_test__respond(false, 'OpenAI rejected the key (401 Unauthorized).', 'ssseo_openai_test_result');
    } elseif ($code >= 200 && $code < 300) {
        ssseo_ajax_test__respond(true, 'OpenAI key looks valid (HTTP '.$code.').', 'ssseo_openai_test_result');
    } else {
        ssseo_ajax_test__respond(false, 'Unexpected OpenAI response (HTTP '.$code.').', 'ssseo_openai_test_result');
    }
});

/**
 * 5) YouTube API test (saved options)
 *    Validates API key + channel ID by fetching minimal channel data.
 */
add_action('wp_ajax_ssseo_test_youtube_api', function () {
    ssseo_ajax_test__check_caps_and_nonce();

    $api_key   = get_option('ssseo_youtube_api_key', '');
    $channelID = get_option('ssseo_youtube_channel_id', '');

    if ($api_key === '') {
        ssseo_ajax_test__respond(false, 'Save a YouTube API key first.', 'ssseo_youtube_test_result');
    }
    if ($channelID === '') {
        ssseo_ajax_test__respond(false, 'Save a YouTube Channel ID first.', 'ssseo_youtube_test_result');
    }

    $url = add_query_arg([
        'part'  => 'id',
        'id'    => $channelID,
        'key'   => $api_key,
        // ttl? no
    ], 'https://www.googleapis.com/youtube/v3/channels');

    $resp = wp_remote_get($url, ['timeout' => 20]);
    if (is_wp_error($resp)) {
        ssseo_ajax_test__respond(false, 'Network error: '.$resp->get_error_message(), 'ssseo_youtube_test_result');
    }

    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);

    if ($code !== 200) {
        $msg = isset($body['error']['message']) ? $body['error']['message'] : 'HTTP '.$code;
        ssseo_ajax_test__respond(false, 'YouTube error: '.$msg, 'ssseo_youtube_test_result');
    }

    $items = isset($body['items']) && is_array($body['items']) ? count($body['items']) : 0;
    if ($items < 1) {
        ssseo_ajax_test__respond(false, 'Channel not found. Check the Channel ID.', 'ssseo_youtube_test_result');
    }

    ssseo_ajax_test__respond(true, 'YouTube OK: Channel found.', 'ssseo_youtube_test_result');
});

/**
 * 6) GSC client config test (saved options)
 *    Validates presence + shape of required OAuth fields.
 *    (Does NOT call Google—just ensures you won’t get "OAuth client not configured".)
 */
add_action('wp_ajax_ssseo_test_gsc_client', function () {
    ssseo_ajax_test__check_caps_and_nonce();

    $cid  = trim((string) get_option('ssseo_gsc_client_id', ''));
    $sec  = trim((string) get_option('ssseo_gsc_client_secret', ''));
    $redir= trim((string) get_option('ssseo_gsc_redirect_uri', ''));

    $missing = [];
    if ($cid === '')  $missing[] = 'Client ID';
    if ($sec === '')  $missing[] = 'Client Secret';
    if ($redir === '')$missing[] = 'Redirect URI';

    if ($missing) {
        ssseo_ajax_test__respond(false, 'Missing: ' . implode(', ', $missing), 'ssseo_gsc_test_result');
    }

    // Basic shape checks to catch typos
    if (strpos($cid, '.apps.googleusercontent.com') === false) {
        ssseo_ajax_test__respond(false, 'Client ID format looks suspicious (expected *.apps.googleusercontent.com).', 'ssseo_gsc_test_result');
    }
    if (!wp_http_validate_url($redir)) {
        ssseo_ajax_test__respond(false, 'Redirect URI is not a valid URL.', 'ssseo_gsc_test_result');
    }

    // All good
    ssseo_ajax_test__respond(true, 'GSC client appears correctly configured.', 'ssseo_gsc_test_result');
});
