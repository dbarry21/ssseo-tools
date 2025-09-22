<?php
// File: admin/ajax-video.php
if ( ! defined('ABSPATH') ) exit;

/**
 * Debug toggles + logger (guarded)
 */
if ( ! function_exists('ssseo_youtube_debug_enabled') ) {
  function ssseo_youtube_debug_enabled() { return (bool) get_option('ssseo_youtube_debug', false); }
}
if ( ! function_exists('ssseo_youtube_log') ) {
  function ssseo_youtube_log( $msg ) {
    if ( ! ssseo_youtube_debug_enabled() ) return;
    $log = get_option('ssseo_youtube_debug_log', array());
    $log[] = '[' . current_time('mysql') . '] ' . ( is_string($msg) ? $msg : wp_json_encode($msg) );
    if ( count($log) > 500 ) $log = array_slice($log, -500);
    update_option('ssseo_youtube_debug_log', $log, false);
  }
}

/**
 * Robust GET wrapper (guarded)
 */
if ( ! function_exists('ssseo_http_get_robust') ) {
  function ssseo_http_get_robust( $url, $args = [] ) {
    $defaults = [
      'timeout'     => 25,
      'sslverify'   => true,
      'httpversion' => '1.1',
      'headers'     => [ 'Referer' => home_url('/') ],
    ];
    $args = wp_parse_args( $args, $defaults );

    $resp = wp_remote_get( $url, $args );
    if ( ! is_wp_error($resp) && wp_remote_retrieve_response_code($resp) ) return $resp;

    $ipv4 = function( $handle ) {
      if ( defined('CURL_IPRESOLVE_V4') ) @curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
    };
    add_filter( 'http_api_curl', $ipv4, 10, 1 );
    $resp2 = wp_remote_get( $url, $args );
    remove_filter( 'http_api_curl', $ipv4, 10 );
    return $resp2;
  }
}

/**
 * Small iframe helper — unique name (NO clash)
 */
if ( ! function_exists('ssseo_video_embed_iframe') ) {
  function ssseo_video_embed_iframe( $video_id ) {
    $id = esc_attr( $video_id );
    return '<div class="ssseo-video-embed-wrapper" style="margin-bottom:1rem;max-width:900px;width:100%;margin-left:auto;margin-right:auto;">'
         . '  <div class="ratio ratio-16x9">'
         . '    <iframe src="https://www.youtube.com/embed/'.$id.'" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>'
         . '  </div>'
         . '</div>';
  }
}

/**
 * channels.list → uploads playlist id (guarded)
 */
if ( ! function_exists('ssseo_youtube_get_uploads_playlist') ) {
  function ssseo_youtube_get_uploads_playlist( $channel_id, $api_key ) {
    $cache_key = 'ssseo_yt_uploads_' . md5($channel_id);
    $cached = get_transient($cache_key);
    if ( is_string($cached) && $cached !== '' ) return $cached;

    $url = add_query_arg([
      'part' => 'contentDetails',
      'id'   => $channel_id,
      'key'  => $api_key,
    ], 'https://www.googleapis.com/youtube/v3/channels');

    $resp = ssseo_http_get_robust($url);
    if ( is_wp_error($resp) || 200 !== (int) wp_remote_retrieve_response_code($resp) ) {
      ssseo_youtube_log(['step'=>'channels','error'=> is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_response_code($resp)]);
      return '';
    }
    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    $uploads = $data['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';
    if ( $uploads ) set_transient($cache_key, $uploads, HOUR_IN_SECONDS);
    ssseo_youtube_log(['uploads_playlist'=>$uploads]);
    return $uploads;
  }
}

/**
 * Debug: toggle / fetch / clear
 */
add_action('wp_ajax_ssseo_youtube_toggle_debug', function () {
  if ( empty($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'ssseo_siteoptions_ajax') ) {
    wp_send_json_error('Unauthorized (bad nonce)');
  }
  if ( ! current_user_can('edit_posts') ) {
    wp_send_json_error('Insufficient permissions');
  }
  $on = ! empty($_POST['enabled']);
  update_option('ssseo_youtube_debug', $on ? 1 : 0, false);
  wp_send_json_success(['enabled' => (bool)$on]);
});

add_action('wp_ajax_ssseo_youtube_get_log', function () {
  if ( empty($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'ssseo_siteoptions_ajax') ) {
    wp_send_json_error('Unauthorized (bad nonce)');
  }
  if ( ! current_user_can('edit_posts') ) wp_send_json_error('Insufficient permissions');
  $log = get_option('ssseo_youtube_debug_log', array());
  wp_send_json_success(['log' => array_values((array)$log)]);
});

add_action('wp_ajax_ssseo_youtube_clear_log', function () {
  if ( empty($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'ssseo_siteoptions_ajax') ) {
    wp_send_json_error('Unauthorized (bad nonce)');
  }
  if ( ! current_user_can('edit_posts') ) wp_send_json_error('Insufficient permissions');
  update_option('ssseo_youtube_debug_log', array(), false);
  wp_send_json_success(true);
});

/**
 * MAIN: Fetch & Create Drafts — videoId as slug
 * action: ssseo_youtube_generate_drafts
 * POST:
 *   - nonce (ssseo_siteoptions_ajax)
 *   - dry_run: 0|1
 *   - limit: int (default 500)
 *   - status: draft|publish (default draft)
 *   - force_repair: 0|1 (revive trashed/auto-draft matches)
 */
add_action('wp_ajax_ssseo_youtube_generate_drafts', function () {
  try {
    if ( empty($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'ssseo_siteoptions_ajax') ) {
      wp_send_json_error(['message' => 'Unauthorized (bad nonce)']);
    }
    if ( ! current_user_can('edit_posts') ) {
      wp_send_json_error(['message' => 'Insufficient permissions']);
    }
    if ( ! post_type_exists('video') ) {
      wp_send_json_error(['message' => 'Post type "video" is not registered.']);
    }

    $api_key = (string) get_option('ssseo_youtube_api_key', '');
    $channel = (string) get_option('ssseo_youtube_channel_id', '');
    if ( $api_key === '' ) wp_send_json_error(['message' => 'YouTube API key not configured.']);
    if ( $channel === '' ) wp_send_json_error(['message' => 'YouTube Channel ID not configured.']);

    $dry_run      = ! empty($_POST['dry_run']);
    $limit        = isset($_POST['limit']) ? max(1, (int) $_POST['limit']) : 500;
    $post_status  = (isset($_POST['status']) && $_POST['status'] === 'publish') ? 'publish' : 'draft';
    $force_repair = ! empty($_POST['force_repair']);

    ignore_user_abort(true);
    @set_time_limit(180);

    $uploads = ssseo_youtube_get_uploads_playlist( $channel, $api_key );
    if ( $uploads === '' ) wp_send_json_error(['message' => 'No uploads playlist found for this channel.']);

    $slug_from_id = function($vid){
      // Keep id-ish slug stable and collision-free
      $v = preg_replace('/[^A-Za-z0-9_\-]/', '-', (string)$vid);
      $v = strtolower($v);
      $v = trim(preg_replace('/-+/', '-', $v), '-');
      return $v !== '' ? $v : strtolower((string)$vid);
    };

    // Counters
    $fetched = 0; $created = 0; $updated = 0; $skipped = 0; $exist_meta = 0; $exist_slug = 0; $repaired = 0;

    $next = '';
    while ( true ) {
      $args = [
        'part'       => 'snippet',
        'playlistId' => $uploads,
        'maxResults' => 50,
        'key'        => $api_key,
      ];
      if ( $next ) $args['pageToken'] = $next;

      $resp = ssseo_http_get_robust( add_query_arg($args, 'https://www.googleapis.com/youtube/v3/playlistItems') );
      if ( is_wp_error($resp) || 200 !== (int) wp_remote_retrieve_response_code($resp) ) {
        $msg = 'Playlist fetch failed: ' . ( is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_response_code($resp) );
        ssseo_youtube_log($msg);
        wp_send_json_error(['message' => $msg]);
      }

      $data  = json_decode( wp_remote_retrieve_body( $resp ), true );
      $items = $data['items'] ?? [];
      $next  = $data['nextPageToken'] ?? '';

      foreach ( $items as $it ) {
        if ( $fetched >= $limit ) { $next = ''; break; }

        $sn  = $it['snippet'] ?? [];
        $rid = $sn['resourceId']['videoId'] ?? '';
        if ( $rid === '' ) { $skipped++; continue; }
        $fetched++;

        $title = sanitize_text_field( $sn['title'] ?? '' );
        if ( $title === '' ) $title = $rid;
        if ( function_exists('mb_strlen') && mb_strlen($title) > 200 ) $title = mb_substr($title, 0, 200);
        elseif ( strlen($title) > 200 ) $title = substr($title, 0, 200);

        $desc  = (string) ( $sn['description'] ?? '' );
        $slug  = $slug_from_id($rid); // ← videoId slug

        // 1) Existing by META (fast path)
        $existing = get_posts([
          'post_type'        => 'video',
          'post_status'      => ['publish','draft','pending','future','private'],
          'meta_key'         => '_ssseo_video_id',
          'meta_value'       => $rid,
          'fields'           => 'ids',
          'posts_per_page'   => 1,
          'no_found_rows'    => true,
          'suppress_filters' => true,
        ]);

        if ( ! empty($existing) ) {
          $exist_meta++;
          $pid = (int) $existing[0];

          // optional maintenance
          $update = ['ID' => $pid]; $needs = false;
          $p = get_post($pid);
          if ( $p ) {
            // slug enforcement to id
            if ( $p->post_name !== $slug ) {
              $conflict = get_page_by_path( $slug, OBJECT, 'video' );
              if ( ! $conflict || (int)$conflict->ID === $pid ) { $update['post_name'] = $slug; $needs = true; }
            }
            // title cleanup
            if ( $p->post_title === '' || $p->post_title === $rid ) { $update['post_title'] = $title; $needs = true; }
            // embed on top
            $content = (string) get_post_field('post_content', $pid);
            if ( stripos($content, 'youtube.com/embed/'.$rid) === false ) {
              $nc = ssseo_video_embed_iframe($rid);
              if ( $desc ) $nc .= "\n\n" . wpautop( esc_html($desc) );
              $nc .= "\n\n" . $content;
              $update['post_content'] = $nc; $needs = true;
            }
            // status repair (if asked)
            if ( $force_repair && $p->post_status !== $post_status ) { $update['post_status'] = $post_status; $needs = true; }
          }
          if ( $needs && ! $dry_run ) {
            $res = wp_update_post( $update, true );
            if ( ! is_wp_error($res) ) { $updated++; ssseo_youtube_log(['updated'=>$pid,'video'=>$rid]); }
          } elseif ( $needs && $dry_run ) {
            $updated++; ssseo_youtube_log(['DRYRUN_update'=>$pid,'video'=>$rid]);
          } else {
            ssseo_youtube_log(['existing_meta_nochange'=>$pid,'video'=>$rid]);
          }
          continue;
        }

        // 1b) If not found in normal statuses, optionally repair from trash/auto-draft when forced
        if ( $force_repair ) {
          $any_status = array_values( get_post_stati() ); // includes trash/auto-draft/revision
          $maybe = get_posts([
            'post_type'        => 'video',
            'post_status'      => $any_status,
            'meta_key'         => '_ssseo_video_id',
            'meta_value'       => $rid,
            'fields'           => 'ids',
            'posts_per_page'   => 1,
            'no_found_rows'    => true,
            'suppress_filters' => true,
          ]);
          if ( ! empty($maybe) ) {
            $pid = (int) $maybe[0];
            if ( ! $dry_run ) {
              // untrash if needed + set proper status
              @wp_untrash_post( $pid );
              wp_update_post(['ID'=>$pid, 'post_status'=>$post_status]);
            }
            $repaired++;
            ssseo_youtube_log(['repaired_from_any'=>$pid,'video'=>$rid]);
            continue;
          }
        }

        // 2) Existing by SLUG (videoId-based)
        $by_slug = get_page_by_path( $slug, OBJECT, 'video' );
        if ( $by_slug ) {
          $exist_slug++;
          // attach missing meta + content corrections
          if ( ! $dry_run ) {
            if ( ! get_post_meta( $by_slug->ID, '_ssseo_video_id', true ) ) {
              update_post_meta( $by_slug->ID, '_ssseo_video_id', $rid );
            }
            $upd = ['ID' => $by_slug->ID]; $n = false;
            if ( $by_slug->post_title === '' || $by_slug->post_title === $rid ) { $upd['post_title'] = $title; $n = true; }
            $content2 = (string) get_post_field('post_content', $by_slug->ID);
            if ( stripos($content2, 'youtube.com/embed/'.$rid) === false ) {
              $nc = ssseo_video_embed_iframe($rid);
              if ( $desc ) $nc .= "\n\n" . wpautop( esc_html($desc) );
              $nc .= "\n\n" . $content2;
              $upd['post_content'] = $nc; $n = true;
            }
            if ( $post_status && $by_slug->post_status !== $post_status ) { $upd['post_status'] = $post_status; $n = true; }
            if ( $n ) {
              $r2 = wp_update_post($upd, true);
              if ( ! is_wp_error($r2) ) $updated++;
            }
          } else {
            $updated++;
            ssseo_youtube_log(['DRYRUN_attach_meta_or_fix'=>$by_slug->ID,'video'=>$rid]);
          }
          continue;
        }

        // 3) Brand new → insert (videoId as slug)
        $content = ssseo_video_embed_iframe( $rid );
        if ( $desc ) $content .= "\n\n" . wp_kses_post( wpautop( esc_html( $desc ) ) );

        if ( $dry_run ) {
          $created++;
          ssseo_youtube_log(['DRYRUN_create'=> ['slug'=>$slug,'title'=>$title,'video'=>$rid]]);
        } else {
          $postarr = [
            'post_title'   => $title,
            'post_name'    => $slug,           // videoId slug
            'post_content' => $content,
            'post_status'  => $post_status,    // draft (default) or publish
            'post_type'    => 'video',
          ];
          $new_id = wp_insert_post( $postarr, true );
          if ( is_wp_error( $new_id ) ) {
            ssseo_youtube_log( 'Insert failed for ' . $rid . ': ' . $new_id->get_error_message() );
            $skipped++;
          } else {
            update_post_meta( $new_id, '_ssseo_video_id', $rid );
            $created++;
            ssseo_youtube_log(['created_post'=>$new_id,'video'=>$rid,'slug'=>$slug,'status'=>$post_status]);
          }
        }
      }

      if ( ! $next ) break;
    } // while

    $sig = 'ytgen-' . current_time('Y-m-d H:i:s');
    ssseo_youtube_log(['run_complete'=> compact('fetched','created','updated','exist_meta','exist_slug','repaired','skipped') + ['sig'=>$sig,'dry'=>$dry_run,'status'=>$post_status]]);

    wp_send_json_success([
      'fetched'         => $fetched,
      'new_posts'       => $created,
      'updated_posts'   => $updated,
      'existing_posts'  => ($exist_meta + $exist_slug),
      'existing_meta'   => $exist_meta,
      'existing_slug'   => $exist_slug,
      'repaired'        => $repaired,
      'skipped'         => $skipped,
      'dry_run'         => $dry_run ? 1 : 0,
      'status'          => $post_status,
      'sig'             => $sig,
    ]);

  } catch (Throwable $e) {
    ssseo_youtube_log(['fatal'=>$e->getMessage(),'line'=>$e->getLine()]);
    wp_send_json_error(['message' => 'Server error: '.$e->getMessage()]);
  }
});
