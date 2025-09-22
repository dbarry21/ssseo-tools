<?php
/**
 * Module: YouTube shortcodes (server-side)
 * Registers:
 *   [youtube_channel_list]
 *   [youtube_channel_list_detailed]
 *   [youtube_with_transcript]   (moved here so all video shortcodes live together)
 *
 * Uses Site Options keys:
 *   - ssseo_youtube_api_key
 *   - ssseo_youtube_channel_id
 *
 * Optional debug flags (boolean options):
 *   - ssseo_youtube_debug
 *   - ssseo_youtube_debug_log (array ring buffer written only when debug enabled)
 */

if (!defined('ABSPATH')) exit;

/* ----------------- helpers: options + logging ----------------- */
if (!function_exists('ssseo_youtube_get_api_key')) {
  function ssseo_youtube_get_api_key() { return get_option('ssseo_youtube_api_key', ''); }
}
if (!function_exists('ssseo_youtube_get_channel')) {
  function ssseo_youtube_get_channel() { return get_option('ssseo_youtube_channel_id', ''); }
}
if (!function_exists('ssseo_youtube_debug_enabled')) {
  function ssseo_youtube_debug_enabled() { return (bool) get_option('ssseo_youtube_debug', false); }
}
if (!function_exists('ssseo_youtube_log')) {
  function ssseo_youtube_log($msg) {
    if (!ssseo_youtube_debug_enabled()) return;
    $log = get_option('ssseo_youtube_debug_log', array());
    $log[] = '['.current_time('mysql').'] '.(is_string($msg) ? $msg : wp_json_encode($msg));
    if (count($log) > 500) $log = array_slice($log, -500);
    update_option('ssseo_youtube_debug_log', $log, false);
  }
}

/* ----------------- helpers: HTTP + API ----------------- */
function ssseo_http_get_robust_youtube($url, $args = array()) {
  $defaults = array(
    'timeout'     => 15,
    'sslverify'   => true,
    'httpversion' => '1.1',
    'headers'     => array('Referer' => home_url('/')),
  );
  $args = wp_parse_args($args, $defaults);

  $r = wp_remote_get($url, $args);
  if (!is_wp_error($r) && wp_remote_retrieve_response_code($r)) return $r;

  // IPv4 retry (hosting quirk)
  $ipv4 = function($h){ if (defined('CURL_IPRESOLVE_V4')) @curl_setopt($h, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); };
  add_filter('http_api_curl', $ipv4, 10, 1);
  $r2 = wp_remote_get($url, $args);
  remove_filter('http_api_curl', $ipv4, 10);
  return $r2;
}

/** channels → uploads playlist id (cached) */
function ssseo_youtube_get_uploads_playlist_id($channel_id, $api_key) {
  $cache_key = 'ssseo_yt_uploads_'.$channel_id;
  $cached    = get_transient($cache_key);
  if (is_string($cached) && $cached !== '') return $cached;

  $url = add_query_arg(array(
    'part' => 'contentDetails',
    'id'   => $channel_id,
    'key'  => $api_key,
  ), 'https://www.googleapis.com/youtube/v3/channels');

  $resp = ssseo_http_get_robust_youtube($url);
  if (is_wp_error($resp) || 200 !== (int) wp_remote_retrieve_response_code($resp)) {
    ssseo_youtube_log(array('where'=>'channels','err'=> is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_response_code($resp)));
    return '';
  }
  $data = json_decode(wp_remote_retrieve_body($resp), true);
  $uploads = $data['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';
  if ($uploads) set_transient($cache_key, $uploads, HOUR_IN_SECONDS);
  ssseo_youtube_log(array('uploads'=>$uploads));
  return $uploads;
}

/** fetch up to first 50 playlist items (title, id, thumb, publishedAt, description) — cached 10min */
function ssseo_youtube_fetch_uploads_batch($playlist_id, $api_key) {
  $cache_key = 'ssseo_yt_plitems_'.$playlist_id;
  $cached    = get_transient($cache_key);
  if (is_array($cached)) return $cached;

  $items = array();
  $next  = '';
  $loops = 0;

  do {
    $args = array(
      'part'       => 'snippet',
      'playlistId' => $playlist_id,
      'maxResults' => 50,
      'key'        => $api_key,
    );
    if ($next) $args['pageToken'] = $next;

    $url  = add_query_arg($args, 'https://www.googleapis.com/youtube/v3/playlistItems');
    $resp = ssseo_http_get_robust_youtube($url);

    if (is_wp_error($resp) || 200 !== (int) wp_remote_retrieve_response_code($resp)) {
      ssseo_youtube_log(array('where'=>'playlistItems','err'=> is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_response_code($resp)));
      break;
    }

    $data  = json_decode(wp_remote_retrieve_body($resp), true);
    $batch = $data['items'] ?? array();
    $next  = $data['nextPageToken'] ?? '';

    foreach ($batch as $row) {
      $sn = $row['snippet'] ?? array();
      $vid = $sn['resourceId']['videoId'] ?? '';
      if (!$vid) continue;
      $items[] = array(
        'videoId'     => $vid,
        'title'       => $sn['title'] ?? $vid,
        'description' => $sn['description'] ?? '',
        'publishedAt' => $sn['publishedAt'] ?? '',
        'thumb'       => $sn['thumbnails']['medium']['url'] ?? ($sn['thumbnails']['default']['url'] ?? ''),
      );
    }

    $loops++;
    if ($loops > 2) break; // safety; first batch is 50 anyway
  } while (!empty($next));

  set_transient($cache_key, $items, 10 * MINUTE_IN_SECONDS);
  return $items;
}

/** find a WP 'video' CPT permalink by videoId (meta) or by sanitized title slug */
function ssseo_youtube_find_video_post_url($video_id, $title = '') {
  // meta match first (fast when indexed)
  $q = get_posts(array(
    'post_type'        => 'video',
    'post_status'      => array('publish','draft','pending','future','private'),
    'meta_key'         => '_ssseo_video_id',
    'meta_value'       => $video_id,
    'posts_per_page'   => 1,
    'fields'           => 'ids',
    'no_found_rows'    => true,
    'suppress_filters' => true,
  ));
  if (!empty($q)) return get_permalink($q[0]);

  if ($title !== '') {
    $slug = sanitize_title($title);
    $p = get_page_by_path($slug, OBJECT, 'video');
    if ($p) return get_permalink($p);
  }
  return '';
}

/** iframe */
function ssseo_youtube_embed_html($video_id) {
  $id = esc_attr($video_id);
  return '<div class="ssseo-video-embed-wrapper" style="margin-bottom:1rem; max-width:900px; margin-left:auto; margin-right:auto;">'
       . '  <div class="ratio ratio-16x9">'
       . '    <iframe src="https://www.youtube.com/embed/'.$id.'" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>'
       . '  </div>'
       . '</div>';
}

/* ----------------- [youtube_channel_list] ----------------- */
add_shortcode('youtube_channel_list', function($atts){
  $a = shortcode_atts(array(
    'channel'  => '',
    'pagesize' => '4',
    'max'      => '0',
    'paging'   => 'true', // (not used in this server-side v1; kept for future AJAX pager)
  ), $atts, 'youtube_channel_list');

  $api_key = ssseo_youtube_get_api_key();
  $channel = $a['channel'] !== '' ? sanitize_text_field($a['channel']) : ssseo_youtube_get_channel();

  if ($api_key === '' || $channel === '') {
    return '<div class="alert alert-warning">YouTube not configured. Add API key and Channel ID in Site Options.</div>';
  }

  $pagesize = max(1, min(50, (int)$a['pagesize']));
  $max_items= max(0, min(50, (int)$a['max']));

  $uploads = ssseo_youtube_get_uploads_playlist_id($channel, $api_key);
  if ($uploads === '') return '<div class="alert alert-danger">Could not resolve channel uploads playlist.</div>';

  $items = ssseo_youtube_fetch_uploads_batch($uploads, $api_key);
  if (!$items) return '<div class="alert alert-info">No videos found for this channel.</div>';

  if ($max_items > 0) $items = array_slice($items, 0, $max_items);
  $items = array_slice($items, 0, $pagesize);

  ob_start();
  ?>
  <div class="container ssseo-youtube-grid">
    <div class="row row-cols-1 row-cols-md-3 g-4">
      <?php foreach ($items as $it): 
        $vid   = $it['videoId'];
        $title = $it['title'];
        $thumb = $it['thumb'];
        $perma = ssseo_youtube_find_video_post_url($vid, $title);
        $yturl = 'https://www.youtube.com/watch?v=' . rawurlencode($vid);
      ?>
        <div class="col">
          <div class="card h-100 shadow-sm">
            <?php if ($thumb): ?>
              <a class="ratio ratio-16x9" href="<?php echo esc_url($yturl); ?>" target="_blank" rel="noopener">
                <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($title); ?>" class="card-img-top" loading="lazy" />
              </a>
            <?php else: ?>
              <div class="ratio ratio-16x9 bg-light d-flex align-items-center justify-content-center">
                <span class="text-muted">No thumbnail</span>
              </div>
            <?php endif; ?>
            <div class="card-body d-flex flex-column">
              <h5 class="card-title" style="font-size:1rem;"><?php echo esc_html($title); ?></h5>
              <div class="mt-auto d-flex gap-2">
                <a class="btn btn-sm btn-primary" href="<?php echo esc_url($yturl); ?>" target="_blank" rel="noopener">Watch on YouTube</a>
                <?php if ($perma): ?>
                  <a class="btn btn-sm btn-outline-secondary" href="<?php echo esc_url($perma); ?>">Visit Post</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php
  return ob_get_clean();
});

/* ----------------- [youtube_channel_list_detailed] ----------------- */
add_shortcode('youtube_channel_list_detailed', function($atts){
  $a = shortcode_atts(array(
    'channel' => '',
    'max'     => '5',
  ), $atts, 'youtube_channel_list_detailed');

  $api_key = ssseo_youtube_get_api_key();
  $channel = $a['channel'] !== '' ? sanitize_text_field($a['channel']) : ssseo_youtube_get_channel();

  if ($api_key === '' || $channel === '') {
    return '<div class="alert alert-warning">YouTube not configured. Add API key and Channel ID in Site Options.</div>';
  }

  $max = max(1, min(50, (int)$a['max']));
  $uploads = ssseo_youtube_get_uploads_playlist_id($channel, $api_key);
  if ($uploads === '') return '<div class="alert alert-danger">Could not resolve channel uploads playlist.</div>';

  $items = array_slice(ssseo_youtube_fetch_uploads_batch($uploads, $api_key), 0, $max);

  ob_start(); ?>
  <div class="container ssseo-youtube-detailed">
    <div class="row g-4">
      <?php foreach ($items as $it):
        $vid   = $it['videoId']; $title = $it['title']; $desc = $it['description']; $thumb = $it['thumb'];
        $yturl = 'https://www.youtube.com/watch?v=' . rawurlencode($vid);
      ?>
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="row g-0">
            <div class="col-md-4">
              <a class="ratio ratio-16x9 d-block" href="<?php echo esc_url($yturl); ?>" target="_blank" rel="noopener">
                <?php if ($thumb): ?>
                  <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($title); ?>" class="img-fluid rounded-start" loading="lazy">
                <?php endif; ?>
              </a>
            </div>
            <div class="col-md-8">
              <div class="card-body">
                <h5 class="card-title"><?php echo esc_html($title); ?></h5>
                <?php if ($desc): ?>
                  <div class="card-text" style="white-space:pre-wrap;"><?php echo esc_html($desc); ?></div>
                <?php endif; ?>
                <a class="btn btn-sm btn-primary mt-2" href="<?php echo esc_url($yturl); ?>" target="_blank" rel="noopener">Watch on YouTube</a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php
  return ob_get_clean();
});

/* ----------------- [youtube_with_transcript] (moved here) ----------------- */
add_shortcode('youtube_with_transcript', function($atts){
  $atts = shortcode_atts(array('url' => ''), $atts, 'youtube_with_transcript');
  if (empty($atts['url'])) return '<p><em>No YouTube URL provided.</em></p>';

  if (!preg_match('%(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})%i', $atts['url'], $m)) {
    return '<p><em>Invalid YouTube URL.</em></p>';
  }
  $video_id = $m[1];
  $html = '<div class="ssseo-youtube-wrapper">';
  $html .= ssseo_youtube_embed_html($video_id);

  // Optional description via API
  $api_key = ssseo_youtube_get_api_key();
  if ($api_key) {
    $resp = ssseo_http_get_robust_youtube(add_query_arg(array(
      'part' => 'snippet',
      'id'   => $video_id,
      'key'  => $api_key,
    ), 'https://www.googleapis.com/youtube/v3/videos'));
    if (!is_wp_error($resp) && 200 === (int) wp_remote_retrieve_response_code($resp)) {
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      $desc = $data['items'][0]['snippet']['description'] ?? '';
      if ($desc) $html .= '<div class="ssseo-youtube-description">'.wpautop(esc_html($desc)).'</div>';
    }
  }

  // Public transcript (best-effort)
  $lines = array();
  $list  = wp_remote_get("https://video.google.com/timedtext?type=list&v={$video_id}");
  if (!is_wp_error($list) && 200 === (int) wp_remote_retrieve_response_code($list)) {
    $xml = simplexml_load_string(wp_remote_retrieve_body($list));
    if (isset($xml->track[0]['lang_code'])) {
      $lang = (string) $xml->track[0]['lang_code'];
      $tts  = wp_remote_get("https://video.google.com/timedtext?lang={$lang}&v={$video_id}");
      if (!is_wp_error($tts) && 200 === (int) wp_remote_retrieve_response_code($tts)) {
        $tts_xml = simplexml_load_string(wp_remote_retrieve_body($tts));
        foreach ($tts_xml->text as $text) {
          $lines[] = esc_html(html_entity_decode((string) $text));
        }
      }
    }
  }
  if ($lines) {
    $html .= '<div class="accordion mt-3" id="ssseoTranscriptAccordion">'
          .    '<div class="accordion-item">'
          .      '<h2 class="accordion-header" id="ssseoTranscriptHeading">'
          .        '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ssseoTranscriptCollapse" aria-expanded="false" aria-controls="ssseoTranscriptCollapse">'
          .          esc_html__('Transcript', 'ssseo')
          .        '</button>'
          .      '</h2>'
          .      '<div id="ssseoTranscriptCollapse" class="accordion-collapse collapse" aria-labelledby="ssseoTranscriptHeading" data-bs-parent="#ssseoTranscriptAccordion">'
          .        '<div class="accordion-body"><p>' . implode('</p><p>', $lines) . '</p></div>'
          .      '</div>'
          .    '</div>'
          .  '</div>';
  }
  $html .= '</div>';
  return $html;
});
/**
 * [youtube_channel_list pagesize="12"]
 * Lists latest published/draft "video" posts with links.
 */
add_shortcode('youtube_channel_list', function($atts){
    $a = shortcode_atts(['pagesize' => '12'], $atts, 'youtube_channel_list');
    $ppp = max(1, (int)$a['pagesize']);

    $rows = get_posts([
        'post_type'        => 'video',
        'post_status'      => ['publish','draft'],
        'posts_per_page'   => $ppp,
        'orderby'          => 'date',
        'order'            => 'DESC',
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ]);

    if (!$rows) return '<p><em>No videos yet.</em></p>';

    ob_start();
    echo '<div class="row row-cols-1 row-cols-md-3 g-3 ssseo-video-list">';
    foreach ($rows as $p) {
        $title = get_the_title($p) ?: get_post_meta($p->ID, '_ssseo_video_id', true);
        $link  = get_permalink($p) ?: '#';
        echo '<div class="col"><div class="card h-100 shadow-sm">';
        echo '<div class="card-body">';
        echo '<h5 class="card-title">'.esc_html($title).'</h5>';
        echo '<a class="btn btn-primary" href="'.esc_url($link).'">View</a>';
        echo '</div></div></div>';
    }
    echo '</div>';
    return ob_get_clean();
});
