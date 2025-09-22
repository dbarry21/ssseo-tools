<?php
/**
 * SSSEO: YouTube integration (Video Blog)
 * - Draft generator: slug = sanitized video title, _ssseo_video_id meta saved
 * - post_content starts with the YouTube iframe, then description
 * - Options used: ssseo_youtube_api_key, ssseo_youtube_channel_id
 * - Debug: ssseo_youtube_debug (bool), ssseo_youtube_debug_log (array)
 * - AJAX (admin): 
 *     ssseo_youtube_generate_drafts
 *     ssseo_youtube_toggle_debug
 *     ssseo_youtube_get_log
 *     ssseo_youtube_clear_log
 * - Nonce action reused from Site Options: ssseo_siteoptions_ajax
 */
if ( ! defined( 'ABSPATH' ) ) exit;

final class SSSEO_YT_Module {
    /* ---------- boot ---------- */
    public static function init() {
        if ( is_admin() ) {
            add_action('wp_ajax_ssseo_youtube_generate_drafts', [__CLASS__, 'ajax_generate']);
            add_action('wp_ajax_ssseo_youtube_toggle_debug',    [__CLASS__, 'ajax_toggle_debug']);
            add_action('wp_ajax_ssseo_youtube_get_log',         [__CLASS__, 'ajax_get_log']);
            add_action('wp_ajax_ssseo_youtube_clear_log',       [__CLASS__, 'ajax_clear_log']);
        }
        add_shortcode('youtube_with_transcript', [__CLASS__, 'shortcode_with_transcript']);
    }

    /* ---------- options ---------- */
    private static function api_key()   { return get_option('ssseo_youtube_api_key', ''); }
    private static function channel_id(){ return get_option('ssseo_youtube_channel_id', ''); }
    private static function debug_on()  { return (bool) get_option('ssseo_youtube_debug', false ); }

    private static function log($msg){
        if ( ! self::debug_on() ) return;
        $log = (array) get_option('ssseo_youtube_debug_log', []);
        $log[] = '[' . current_time('mysql') . '] ' . (is_string($msg) ? $msg : wp_json_encode($msg));
        if (count($log) > 500) $log = array_slice($log, -500);
        update_option('ssseo_youtube_debug_log', $log, false);
    }

    /* ---------- security ---------- */
    private static function verify_ajax_or_fail() {
        if ( ! current_user_can('manage_options') ) {
            self::log(['auth'=>'no-manage-options']);
            wp_send_json_error(['message'=>'Insufficient permissions.']);
        }
        $nonce = '';
        foreach (['nonce','security','_ajax_nonce'] as $k) {
            if (isset($_POST[$k])) { $nonce = sanitize_text_field( wp_unslash($_POST[$k]) ); break; }
        }
        $valid = wp_verify_nonce($nonce, 'ssseo_siteoptions_ajax');
        self::log(['nonce_received'=>$nonce ? 'yes':'no', 'nonce_valid'=>$valid?'yes':'no']);
        if ( ! $valid ) wp_send_json_error(['message'=>'Invalid nonce.']);
    }

    /* ---------- API helpers ---------- */
    private static function uploads_playlist($channel, $key) {
        $resp = wp_remote_get( add_query_arg([
            'part' => 'contentDetails',
            'id'   => $channel,
            'key'  => $key,
        ], 'https://www.googleapis.com/youtube/v3/channels') );
        if (is_wp_error($resp) || 200 !== wp_remote_retrieve_response_code($resp)) {
            self::log(['step'=>'channels','error'=> is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_response_code($resp) ]);
            return '';
        }
        $data = json_decode( wp_remote_retrieve_body($resp), true );
        $uploads = $data['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';
        self::log(['step'=>'uploads_playlist','playlist'=>$uploads]);
        return $uploads;
    }

    private static function embed($video_id) {
        $id = esc_attr($video_id);
        return '<div class="ssseo-video-embed-wrapper" style="margin-bottom:2rem;max-width:800px;width:100%;margin-left:auto;margin-right:auto;">'
             . '  <div class="ratio ratio-16x9">'
             . '    <iframe src="https://www.youtube.com/embed/'.$id.'" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>'
             . '  </div>'
             . '</div>';
    }

    /* ---------- generator ---------- */
    public static function generate($channel_id) {
        if ( ! current_user_can('manage_options') ) {
            return new WP_Error('forbidden','You do not have permission to run this function.');
        }
        $channel = sanitize_text_field($channel_id ?: self::channel_id());
        if (empty($channel)) return new WP_Error('no_channel','No channel ID provided.');

        $key = self::api_key();
        if (empty($key)) return new WP_Error('no_api_key','YouTube API key not configured.');

        $uploads = self::uploads_playlist($channel, $key);
        if (empty($uploads)) return new WP_Error('no_uploads_playlist','No uploads playlist found for this channel.');

        $next = ''; $created=0; $skipped=0; $errors=[];
        do {
            $args = ['part'=>'snippet','playlistId'=>$uploads,'maxResults'=>50,'key'=>$key];
            if ($next) $args['pageToken'] = $next;

            $pl = wp_remote_get( add_query_arg($args,'https://www.googleapis.com/youtube/v3/playlistItems') );
            if (is_wp_error($pl) || 200 !== wp_remote_retrieve_response_code($pl)) {
                $errors[] = 'Playlist fetch failed: ' . (is_wp_error($pl) ? $pl->get_error_message() : wp_remote_retrieve_response_code($pl));
                self::log(end($errors));
                break;
            }
            $data  = json_decode( wp_remote_retrieve_body($pl), true );
            $items = $data['items'] ?? [];
            $next  = $data['nextPageToken'] ?? '';

            foreach ($items as $it) {
                $sn  = $it['snippet'] ?? [];
                $vid = $sn['resourceId']['videoId'] ?? '';
                if (empty($vid)) continue;

                $raw_title = $sn['title'] ?? $vid;
                $title     = sanitize_text_field($raw_title);
                $slug      = sanitize_title($raw_title);     // slug == title
                $desc      = $sn['description'] ?? '';

                // skip if a 'video' post with same slug exists
                if ( get_page_by_path($slug, OBJECT, 'video') ) { $skipped++; continue; }

                $content = self::embed($vid);
                if ($desc) $content .= "\n\n" . wp_kses_post( wpautop( make_clickable( esc_html($desc) ) ) );

                $postarr = [
                    'post_title'   => $title,
                    'post_name'    => $slug,
                    'post_content' => $content,
                    'post_status'  => 'draft',
                    'post_type'    => 'video',
                ];
                $new_id = wp_insert_post($postarr, true);
                if (is_wp_error($new_id)) {
                    $errors[] = 'Insert failed for '.$vid.': '.$new_id->get_error_message();
                    self::log(end($errors));
                    continue;
                }
                update_post_meta($new_id, '_ssseo_video_id', $vid);
                $created++;
                self::log(['created_post'=>$new_id,'video_id'=>$vid,'slug'=>$slug]);
            }
        } while (!empty($next));

        return ['new_posts'=>$created,'existing_posts'=>$skipped,'errors'=>$errors];
    }

    /* ---------- AJAX handlers ---------- */
    public static function ajax_generate() {
        self::verify_ajax_or_fail();
        $result = self::generate( self::channel_id() );
        if (is_wp_error($result)) wp_send_json_error(['message'=>$result->get_error_message()]);
        wp_send_json_success($result);
    }
    public static function ajax_toggle_debug() {
        self::verify_ajax_or_fail();
        $enabled = ! empty($_POST['enabled']) ? 1 : 0;
        update_option('ssseo_youtube_debug', $enabled, false);
        wp_send_json_success(['enabled'=>$enabled]);
    }
    public static function ajax_get_log() {
        self::verify_ajax_or_fail();
        $log = (array) get_option('ssseo_youtube_debug_log', []);
        wp_send_json_success(['log'=>$log]);
    }
    public static function ajax_clear_log() {
        self::verify_ajax_or_fail();
        delete_option('ssseo_youtube_debug_log');
        wp_send_json_success('cleared');
    }

    /* ---------- shortcode ---------- */
    public static function shortcode_with_transcript($atts) {
        $atts = shortcode_atts(['url'=>''], $atts, 'youtube_with_transcript');
        if ( empty($atts['url']) ) return '<p><em>No YouTube URL provided.</em></p>';
        if ( ! preg_match('%(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})%i', $atts['url'], $m) ) {
            return '<p><em>Invalid YouTube URL.</em></p>';
        }
        $video_id = $m[1];
        $html = '<div class="ssseo-youtube-wrapper">' . self::embed($video_id);

        // optional description via API
        $key = self::api_key();
        if ($key) {
            $r = wp_remote_get( add_query_arg(['part'=>'snippet','id'=>$video_id,'key'=>$key],'https://www.googleapis.com/youtube/v3/videos') );
            if ( ! is_wp_error($r) && 200 === wp_remote_retrieve_response_code($r) ) {
                $data = json_decode( wp_remote_retrieve_body($r), true );
                $desc = $data['items'][0]['snippet']['description'] ?? '';
                if ($desc) $html .= '<div class="ssseo-youtube-description">'. wpautop( esc_html($desc) ) .'</div>';
            }
        }

        // best-effort transcript
        $lines = [];
        $list  = wp_remote_get("https://video.google.com/timedtext?type=list&v={$video_id}");
        if ( ! is_wp_error($list) && 200 === wp_remote_retrieve_response_code($list) ) {
            $xml = simplexml_load_string( wp_remote_retrieve_body($list) );
            if ( isset($xml->track[0]['lang_code']) ) {
                $lang = (string) $xml->track[0]['lang_code'];
                $tts  = wp_remote_get("https://video.google.com/timedtext?lang={$lang}&v={$video_id}");
                if ( ! is_wp_error($tts) && 200 === wp_remote_retrieve_response_code($tts) ) {
                    $tts_xml = simplexml_load_string( wp_remote_retrieve_body($tts) );
                    foreach ($tts_xml->text as $t) $lines[] = esc_html( html_entity_decode( (string) $t ) );
                }
            }
        }
        if ($lines) {
            $html .= '<div class="accordion mt-3" id="ssseoTranscriptAccordion">'
                  .    '<div class="accordion-item">'
                  .      '<h2 class="accordion-header" id="ssseoTranscriptHeading">'
                  .        '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ssseoTranscriptCollapse" aria-expanded="false" aria-controls="ssseoTranscriptCollapse">'
                  .          esc_html__('Transcript','ssseo')
                  .        '</button>'
                  .      '</h2>'
                  .      '<div id="ssseoTranscriptCollapse" class="accordion-collapse collapse" aria-labelledby="ssseoTranscriptHeading" data-bs-parent="#ssseoTranscriptAccordion">'
                  .        '<div class="accordion-body"><p>' . implode('</p><p>',$lines) . '</p></div>'
                  .      '</div>'
                  .    '</div>'
                  .  '</div>';
        }
        return $html . '</div>';
    }
}
SSSEO_YT_Module::init();
