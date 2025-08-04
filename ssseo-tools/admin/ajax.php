<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX: Get posts by post type (for select dropdowns)
 */
add_action( 'wp_ajax_ssseo_get_posts_by_type', function() {
    $post_type = sanitize_text_field( $_POST['post_type'] ?? '' );
    $search    = sanitize_text_field( $_POST['search'] ?? '' );

    if ( empty( $post_type ) || ! post_type_exists( $post_type ) ) {
        echo '<option disabled>No post type selected</option>';
        wp_die();
    }

    $args = [
        'post_type'   => $post_type,
        'numberposts' => 100,
        'post_status' => 'any',
        'orderby'     => 'title',
        'order'       => 'ASC',
    ];

    if ( $search ) {
        $args['s'] = $search;
    }

    $posts = get_posts( $args );

    if ( empty($posts) ) {
        echo '<option disabled>No posts found</option>';
    } else {
        foreach ( $posts as $post ) {
            echo '<option value="' . esc_attr($post->ID) . '">' . esc_html($post->post_title) . ' (#' . $post->ID . ')</option>';
        }
    }

    wp_die();
});

/**
 * AJAX: Clone services as service_area posts (with Elementor + AI support)
 */
add_action('wp_ajax_ssseo_clone_services_to_area', 'ssseo_clone_services_to_area_handler');
function ssseo_clone_services_to_area_handler() {
    if (
        ! current_user_can('edit_posts') ||
        ! check_ajax_referer('ssseo_admin_nonce', '_wpnonce', false)
    ) {
        wp_send_json_error('Unauthorized');
    }

    $services    = $_POST['services'] ?? [];
    $target_area = intval($_POST['target_area'] ?? 0);
    $enable_ai   = ! empty($_POST['enable_ai']);

    if (empty($services) || ! is_array($services) || $target_area <= 0) {
        wp_send_json_error('Invalid input');
    }

    $cloned   = [];
    $ai_debug = [];

    foreach ($services as $sid) {
        $sid = intval($sid);
        $post = get_post($sid);
        if (! $post || $post->post_type !== 'service') continue;

        $new_post = [
            'post_type'   => 'service_area',
            'post_status' => 'draft',
            'post_parent' => $target_area,
            'post_title'  => $post->post_title,
            'post_excerpt'=> $post->post_excerpt,
            'post_content'=> $post->post_content,
            'post_author' => get_current_user_id(),
        ];

        $new_id = wp_insert_post($new_post);

        if ($new_id && ! is_wp_error($new_id)) {
            if ($thumb_id = get_post_thumbnail_id($post->ID)) {
                set_post_thumbnail($new_id, $thumb_id);
            }

            if (function_exists('get_fields')) {
                $fields = get_fields($post->ID);
                if (is_array($fields)) {
                    foreach ($fields as $key => $val) {
                        update_field($key, $val, $new_id);
                    }
                }
            }

            // Copy Elementor metadata
            $elementor_keys = [
                '_elementor_edit_mode',
                '_elementor_template_type',
                '_elementor_data',
                '_elementor_controls_usage',
                '_elementor_page_settings',
            ];

            foreach ( $elementor_keys as $key ) {
                $val = get_post_meta($post->ID, $key, true);
                if ( $val !== '' ) {
                    update_post_meta($new_id, $key, $val);
                }
            }

            // Generate AI content
            if ($enable_ai && function_exists('ssseo_generate_ai_about_area_html')) {
                $area_title = get_the_title($target_area);
                $prompt = sprintf(
                    "Write a 4–5 paragraph description (400–500 words) about %s for a local service area page. Highlight its relevance and value based on this service content:\n\n%s",
                    $area_title,
                    wp_strip_all_tags($post->post_content)
                );

                $ai_html = ssseo_generate_ai_about_area_html($prompt);

                if (! empty($ai_html)) {
                    update_post_meta($new_id, '_about_the_area', wp_kses_post($ai_html));
                    $ai_debug[] = "<h5>" . esc_html($post->post_title) . "</h5><div style='max-height:200px;overflow:auto;border:1px solid #ccc;padding:10px;margin-bottom:20px;'>" . wp_kses_post($ai_html) . "</div>";
                } else {
                    $ai_debug[] = "<h5>" . esc_html($post->post_title) . "</h5><p><em>AI generation failed or returned no content.</em></p>";
                }
            }

            $cloned[] = $post->post_title;
        }
    }

    if (! empty($cloned)) {
        $html = '<strong>Cloned the following:</strong><ul><li>' . implode('</li><li>', array_map('esc_html', $cloned)) . '</li></ul>';
        if ($enable_ai && ! empty($ai_debug)) {
            $html .= '<hr><div><strong>AI Debug Output:</strong>' . implode('', $ai_debug) . '</div>';
        }
        wp_send_json_success($html);
    } else {
        wp_send_json_error('No services were cloned.');
    }
}


/** Yoast Bulk Handlers **/
add_action( 'wp_ajax_ssseo_yoast_set_index_follow', function() {
    check_ajax_referer( 'ssseo_admin_nonce', '_wpnonce' );
    $ids = array_map('intval', $_POST['post_ids'] ?? []);
    foreach ($ids as $post_id) {
        update_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', '0');
        update_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', '0');
    }
    wp_send_json_success('Yoast set to index, follow for selected posts.');
});

add_action( 'wp_ajax_ssseo_yoast_reset_canonical', function () {
    check_ajax_referer( 'ssseo_admin_nonce', '_wpnonce' );
    $ids = array_map('intval', $_POST['post_ids'] ?? []);
    foreach ($ids as $post_id) {
        $permalink = get_permalink($post_id);
        update_post_meta($post_id, '_yoast_wpseo_canonical', $permalink);
    }
    wp_send_json_success();
});

add_action( 'wp_ajax_ssseo_yoast_clear_canonical', function () {
    check_ajax_referer( 'ssseo_admin_nonce', '_wpnonce' );
    $ids = array_map('intval', $_POST['post_ids'] ?? []);
    foreach ($ids as $post_id) {
        delete_post_meta($post_id, '_yoast_wpseo_canonical');
    }
    wp_send_json_success();
});


/** Meta History Viewer **/
add_action( 'wp_ajax_ssseo_get_meta_history', function () {
    check_ajax_referer( 'ssseo_admin_nonce', '_wpnonce' );
    $post_id = intval( $_POST['post_id'] ?? 0 );
    if (! $post_id) wp_send_json_error('Invalid post ID');

    $log = get_post_meta($post_id, '_ssseo_meta_log', true) ?: [];
    $current_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
    $current_desc  = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);

    ob_start();
    ?>
    <h3>Current Yoast Meta</h3>
    <table class="widefat striped"><thead><tr><th>Field</th><th>Current Value</th></tr></thead><tbody>
    <tr><td>Title</td><td><?= esc_html($current_title) ?></td></tr>
    <tr><td>Description</td><td><?= esc_html($current_desc) ?></td></tr>
    </tbody></table>
    <?php if (! empty($log)): ?>
        <h3>Change History</h3>
        <table class="widefat striped"><thead><tr><th>Time</th><th>Field</th><th>Value</th><th>User</th></tr></thead><tbody>
        <?php foreach (array_reverse($log) as $entry): ?>
            <tr>
                <td><?= esc_html($entry['time']) ?></td>
                <td><?= $entry['field'] === '_yoast_wpseo_title' ? 'Title' : 'Meta Description' ?></td>
                <td><?= esc_html($entry['value']) ?></td>
                <td><?= esc_html(get_userdata($entry['user'])->display_name ?? 'Unknown') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    <?php else: ?>
        <p>No historical changes recorded.</p>
    <?php endif;

    $html = ob_get_clean();

    $csv = "Time,Field,Value,User\n";
    foreach ($log as $entry) {
        $user = get_userdata($entry['user']);
        $csv .= sprintf("\"%s\",\"%s\",\"%s\",\"%s\"\n",
            $entry['time'],
            $entry['field'] === '_yoast_wpseo_title' ? 'Title' : 'Meta Description',
            str_replace('"', '\"', $entry['value']),
            $user ? $user->display_name : 'Unknown'
        );
    }

    wp_send_json_success([
        'html'     => $html,
        'csv'      => $csv,
        'filename' => 'meta-history-' . $post_id . '.csv',
    ]);
});


/** API Key Testers **/
add_action('wp_ajax_ssseo_test_openai_key', 'ssseo_test_openai_key');
function ssseo_test_openai_key() {
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $key = sanitize_text_field($_POST['key'] ?? '');
    if (! $key) wp_send_json_error('No key provided.');

    $response = wp_remote_get('https://api.openai.com/v1/models', [
        'headers' => [ 'Authorization' => 'Bearer ' . $key ],
        'timeout' => 10,
    ]);

    $timestamp = current_time('mysql');
    if (is_wp_error($response)) {
        $msg = '❌ ' . $response->get_error_message() . " (tested $timestamp)";
        update_option('ssseo_openai_test_result', $msg);
        wp_send_json_error($msg);
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code === 200) {
        $msg = "✅ OpenAI key is valid (tested $timestamp)";
        update_option('ssseo_openai_test_result', $msg);
        wp_send_json_success($msg);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $error = $body['error']['message'] ?? 'Unknown error';
    $msg = "❌ Invalid: $error (tested $timestamp)";
    update_option('ssseo_openai_test_result', $msg);
    wp_send_json_error($msg);
}

add_action('wp_ajax_ssseo_test_maps_key', 'ssseo_test_maps_key');
function ssseo_test_maps_key() {
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $key = sanitize_text_field($_POST['key'] ?? '');
    if (! $key) wp_send_json_error('No key provided.');

    $url = "https://maps.googleapis.com/maps/api/staticmap?center=New+York,NY&zoom=10&size=400x200&key=" . urlencode($key);
    $response = wp_remote_get($url, ['timeout' => 10]);
    $timestamp = current_time('mysql');

    if (is_wp_error($response)) {
        $msg = '❌ ' . $response->get_error_message() . " (tested $timestamp)";
        update_option('ssseo_maps_test_result', $msg);
        wp_send_json_error($msg);
    }

    if (wp_remote_retrieve_response_code($response) === 200) {
        $msg = "✅ Maps key is valid (tested $timestamp)";
        update_option('ssseo_maps_test_result', $msg);
        wp_send_json_success($msg);
    }

    $msg = "❌ Invalid or restricted Maps key (tested $timestamp)";
    update_option('ssseo_maps_test_result', $msg);
    wp_send_json_error($msg);
}

add_action( 'wp_ajax_ssseo_create_video_drafts', 'ssseo_create_video_drafts_handler' );

function ssseo_create_video_drafts_handler() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'error' => 'Unauthorized access.' ] );
    }

    if ( ! check_ajax_referer( 'ssseo_generate_videos_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'error' => 'Security check failed.' ] );
    }

    $api_key    = get_option( 'ssseo_youtube_api_key' );
    $channel_id = get_option( 'ssseo_youtube_channel_id' );

    if ( ! $api_key || ! $channel_id ) {
        wp_send_json_error( [ 'error' => 'Missing API key or Channel ID.' ] );
    }

    $created = 0;
    $page_token = '';
    $video_count = 0;
    $max_pages = 50; // Avoid infinite loop (YouTube cap is 1000 results total)

    while ( $max_pages-- ) {
        $url = add_query_arg( [
            'part'       => 'snippet',
            'channelId'  => $channel_id,
            'maxResults' => 50,
            'order'      => 'date',
            'type'       => 'video',
            'key'        => $api_key,
            'pageToken'  => $page_token,
        ], 'https://www.googleapis.com/youtube/v3/search' );

        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'error' => 'YouTube API error: ' . $response->get_error_message() ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['items'] ) ) {
            break;
        }

        foreach ( $body['items'] as $item ) {
            $video_id    = $item['id']['videoId'] ?? '';
            $title       = $item['snippet']['title'] ?? '';
            $video_id = $item['id']['videoId'] ?? '';
if ( ! $video_id ) continue;

// Get full video metadata from videos endpoint
$video_meta_url = add_query_arg( [
    'part' => 'snippet',
    'id'   => $video_id,
    'key'  => $api_key,
], 'https://www.googleapis.com/youtube/v3/videos' );

$video_meta_response = wp_remote_get( $video_meta_url );
$video_meta = json_decode( wp_remote_retrieve_body( $video_meta_response ), true );
$video_snippet = $video_meta['items'][0]['snippet'] ?? [];

$title       = $video_snippet['title'] ?? 'Untitled';
$description = $video_snippet['description'] ?? '';


            if ( ! $video_id || ! $title ) continue;

            // Skip if already imported
            $existing = get_posts( [
                'post_type'  => 'video',
                'meta_key'   => 'ssseo_youtube_video_id',
                'meta_value' => $video_id,
                'fields'     => 'ids',
            ] );
            if ( ! empty( $existing ) ) continue;

            // Attempt transcript fetch (unofficial API method)
            $transcript = ssseo_get_youtube_transcript( $video_id );

            // Build post content
            $content = '<div class="ratio ratio-16x9 mb-3"><iframe src="https://www.youtube.com/embed/' . esc_attr( $video_id ) . '" frameborder="0" allowfullscreen></iframe></div>';

            if ( $description ) {
                $content .= '<h4>Description</h4><p>' . esc_html( $description ) . '</p>';
            }

            if ( $transcript ) {
                $content .= '<h4>Transcript</h4><div style="white-space:pre-wrap;">' . esc_html( $transcript ) . '</div>';
            }

            $post_id = wp_insert_post( [
                'post_type'    => 'video',
                'post_title'   => wp_strip_all_tags( $title ),
                'post_content' => $content,
                'post_status'  => 'draft',
            ] );

            if ( $post_id && ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, 'ssseo_youtube_video_id', $video_id );
                $created++;
            }
        }

        if ( ! isset( $body['nextPageToken'] ) ) break;
        $page_token = $body['nextPageToken'];
    }

    wp_send_json_success( [
        'message' => "Imported $created new video draft(s)."
    ] );
}

add_action( 'wp_ajax_ssseo_batch_import_videos', 'ssseo_batch_import_videos_handler' );

function ssseo_batch_import_videos_handler() {
    check_ajax_referer( 'ssseo_batch_import_nonce', 'nonce' );

    $api_key    = get_option( 'ssseo_youtube_api_key' );
    $channel_id = get_option( 'ssseo_youtube_channel_id' );
    $imported   = 0;
    $log        = [];

    if ( empty( $api_key ) || empty( $channel_id ) ) {
        wp_send_json_error( [ 'error' => 'Missing API key or channel ID' ] );
    }

    $page_token = '';
    $base_url = 'https://www.googleapis.com/youtube/v3/search';

    do {
        $params = [
            'key'       => $api_key,
            'channelId' => $channel_id,
            'part'      => 'snippet',
            'maxResults'=> 50,
            'order'     => 'date',
            'type'      => 'video',
        ];

        if ( $page_token ) {
            $params['pageToken'] = $page_token;
        }

        $url      = $base_url . '?' . http_build_query( $params );
        $response = wp_remote_get( $url );
        $body     = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( is_wp_error( $response ) || empty( $body['items'] ) ) {
            $log[] = '❌ Error fetching data from YouTube API.';
            break;
        }

        foreach ( $body['items'] as $item ) {
            $video_id = $item['id']['videoId'] ?? '';
            $title    = sanitize_text_field( $item['snippet']['title'] ?? '' );
            $desc     = $item['snippet']['description'] ?? '';

            if ( ! $video_id || post_exists( $title ) ) {
                $log[] = "⏭️ Skipped: $title (already exists or invalid)";
                continue;
            }

            // --- Get transcript
            $transcript_html = ssseo_get_youtube_transcript( $video_id );

            // --- Build content
            $post_content = '<div class="ratio ratio-16x9 mb-3">
<iframe src="https://www.youtube.com/embed/' . esc_attr( $video_id ) . '" allowfullscreen></iframe>
</div>';

            if ( $desc ) {
                $post_content .= '<h3>Description</h3><div class="video-description">' . nl2br( esc_html( $desc ) ) . '</div>';
            }

            if ( $transcript_html ) {
                $post_content .= '<h3>Transcript</h3><div class="video-transcript">' . $transcript_html . '</div>';
            }

            $post_id = wp_insert_post( [
                'post_type'    => 'video',
                'post_status'  => 'draft',
                'post_title'   => $title,
                'post_content' => $post_content,
            ] );

            if ( $post_id && ! is_wp_error( $post_id ) ) {
                $imported++;
                $log[] = "✅ Imported: $title";
            } else {
                $log[] = "❌ Failed to import: $title";
            }
        }

        $page_token = $body['nextPageToken'] ?? '';
    } while ( $page_token );

    if ( $imported > 0 ) {
        wp_send_json_success( [
            'message' => "Imported $imported new video(s).",
            'log'     => $log
        ] );
    } else {
        wp_send_json_error( [
            'error' => 'No new videos were imported.',
            'log'   => $log
        ] );
    }
}


function ssseo_get_youtube_transcript( $video_id ) {
    $url = 'https://video.google.com/timedtext?lang=en&v=' . urlencode( $video_id );
    $response = wp_remote_get( $url );

    if ( is_wp_error( $response ) ) return '';

    $xml = wp_remote_retrieve_body( $response );
    if ( empty( $xml ) ) return '';

    try {
        $srt = new SimpleXMLElement( $xml );
    } catch ( Exception $e ) {
        return '';
    }

    $lines = [];
    foreach ( $srt->text as $line ) {
        $text = (string) $line;
        $text = str_replace( "\n", ' ', $text );
        $lines[] = esc_html( $text );
    }

    return '<p>' . implode( '</p><p>', $lines ) . '</p>';
}



