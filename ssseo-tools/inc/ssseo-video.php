<?php


if ( ! get_option( 'ssseo_enable_youtube', true ) ) {

    return;

}





/**

 * 2) HELPER: Fetch & Render a Single Page of Videos

 *

 * @param string $channel_id YouTube Channel ID

 * @param int    $per_page   Videos per page

 * @param int    $page       Current page number (1-indexed)

 * @param int    $max_items  Total maximum videos to display (0 = no limit)

 * @return array             [ 'grid' => HTML, 'pagination' => HTML ]

 */

function ssseo_render_channel_page( $channel_id, $per_page = 4, $page = 1, $max_items = 0 ) {

    $api_key = get_theme_mod( 'ssseo_youtube_api_key', '' );

    if ( ! $api_key || ! $channel_id ) {

        return [

            'grid'       => '<p><em>API key or Channel ID missing.</em></p>',

            'pagination' => '',

        ];

    }

    // Retrieve uploads playlist ID

    $chan_resp = wp_remote_get( add_query_arg( [

        'part' => 'contentDetails',

        'id'   => $channel_id,

        'key'  => $api_key,

    ], 'https://www.googleapis.com/youtube/v3/channels' ) );

    if ( is_wp_error( $chan_resp ) || 200 !== wp_remote_retrieve_response_code( $chan_resp ) ) {

        return [

            'grid'       => '<p><em>Unable to fetch channel info.</em></p>',

            'pagination' => '',

        ];

    }

    $chan_data = json_decode( wp_remote_retrieve_body( $chan_resp ), true );

    $uploads   = $chan_data['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';

    if ( ! $uploads ) {

        return [

            'grid'       => '<p><em>No uploads playlist found.</em></p>',

            'pagination' => '',

        ];

    }

    // Fetch up to 50 items from uploads playlist

    $pl_resp = wp_remote_get( add_query_arg( [

        'part'       => 'snippet',

        'playlistId' => $uploads,

        'maxResults' => 50,

        'key'        => $api_key,

    ], 'https://www.googleapis.com/youtube/v3/playlistItems' ) );

    if ( is_wp_error( $pl_resp ) || 200 !== wp_remote_retrieve_response_code( $pl_resp ) ) {

        return [

            'grid'       => '<p><em>Unable to fetch playlist items.</em></p>',

            'pagination' => '',

        ];

    }

    $all_items = json_decode( wp_remote_retrieve_body( $pl_resp ), true )['items'] ?? [];

    if ( empty( $all_items ) ) {

        return [

            'grid'       => '<p><em>No videos found.</em></p>',

            'pagination' => '',

        ];

    }

    // Enforce max_items limit

    if ( $max_items > 0 && count( $all_items ) > $max_items ) {

        $all_items = array_slice( $all_items, 0, $max_items );

    }

    // Pagination logic

    $total       = count( $all_items );

    $total_pages = (int) ceil( $total / $per_page );

    $page        = max( 1, min( $page, $total_pages ) );

    $start_index = ( $page - 1 ) * $per_page;

    $slice       = array_slice( $all_items, $start_index, $per_page );

    // Determine if we need to exclude the "current" video (when on a single 'video' CPT)

    $current_video_id = '';

    if ( is_singular( 'video' ) ) {

        $post_id = get_the_ID();

        $current_video_id = get_post_meta( $post_id, '_ssseo_video_id', true );

        if ( empty( $current_video_id ) ) {

            // Fallback: if slug equals video ID

            global $post;

            $current_video_id = $post->post_name;

        }

    }



// === Build YouTube Grid by Matching Posts by Title ===
ob_start();
echo '<div class="ssseo-card-grid">';

foreach ( $slice as $item ) {
    $vid     = esc_attr( $item['snippet']['resourceId']['videoId'] );
    $title   = $item['snippet']['title'] ?? '';
    $desc    = $item['snippet']['description'] ?? '';
    $short   = esc_html( wp_trim_words( strip_tags( $title ), 6, '…' ) );
    $thumb   = esc_url( $item['snippet']['thumbnails']['medium']['url'] );
    $id_vid  = 'ytModal_' . $vid;
    $id_desc = 'ytDescModal_' . $vid;

    // Exclude current video if applicable
    if ( ! empty( $current_video_id ) && $vid === $current_video_id ) continue;

    // ✅ Match post by title, not slug or video ID
    $video_post = get_page_by_title( $title, OBJECT, 'video' );
    // if no post by title try video id
    if (!$video_post) {
		        $video_post = get_page_by_path( $vid, OBJECT, 'video' );
	}
    $visit_url  = $video_post ? get_permalink( $video_post->ID ) : '';

    // --- Card markup ---
    echo '<div class="card h-100">';
    if ( $thumb ) {
        printf(
            '<img src="%1$s" class="card-img-top" alt="%2$s">',
            $thumb, esc_attr( $short )
        );
    }

    echo '<div class="card-body d-flex flex-column">';
    printf( '<h5 class="card-title mb-3">%1$s</h5>', $short );

    printf(
        '<button type="button" class="btn btn-primary mt-auto" data-bs-toggle="modal" data-bs-target="#%1$s">%2$s</button>',
        esc_attr( $id_vid ),
        esc_html__( 'Watch Video', 'ssseo' )
    );

    printf(
        '<button type="button" class="btn btn-secondary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#%1$s">%2$s</button>',
        esc_attr( $id_desc ),
        esc_html__( 'Read Description', 'ssseo' )
    );

    // ✅ Show Visit Post button if match by title exists
    if ( $visit_url ) {
        printf(
            '<a href="%1$s" class="btn btn-outline-secondary btn-sm mt-2">%2$s</a>',
            esc_url( $visit_url ),
            esc_html__( 'Visit Post', 'ssseo' )
        );
    }

    echo '</div>'; // .card-body
    echo '</div>'; // .card

    // --- Modal: Video Embed ---
    echo '<div class="modal fade" id="' . esc_attr( $id_vid ) . '" tabindex="-1" aria-labelledby="' . esc_attr( $id_vid ) . 'Label" aria-hidden="true">';
    echo '  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">';
    echo '    <div class="modal-content">';
    echo '      <div class="modal-header">';
    printf( '<h5 class="modal-title" id="%1$sLabel">%2$s</h5>', esc_attr( $id_vid ), $short );
    echo '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . esc_attr__( 'Close', 'ssseo' ) . '"></button>';
    echo '      </div>';
    echo '      <div class="modal-body">';
    printf(
        '<div class="ratio ratio-16x9"><iframe src="https://www.youtube.com/embed/%1$s?autoplay=1" frameborder="0" allowfullscreen></iframe></div>',
        $vid
    );
    echo '      </div>';
    echo '      <div class="modal-footer">';
    echo '        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . esc_html__( 'Close', 'ssseo' ) . '</button>';
    echo '      </div>';
    echo '    </div>';
    echo '  </div>';
    echo '</div>';

    // --- Modal: Description ---
    $desc_html = wp_kses_post( wpautop( esc_html( $desc ) ) );
    echo '<div class="modal fade" id="' . esc_attr( $id_desc ) . '" tabindex="-1" aria-labelledby="' . esc_attr( $id_desc ) . 'Label" aria-hidden="true">';
    echo '  <div class="modal-dialog modal-dialog-centered" role="document">';
    echo '    <div class="modal-content">';
    echo '      <div class="modal-header">';
    printf( '<h5 class="modal-title" id="%1$sLabel">%2$s</h5>', esc_attr( $id_desc ), $short );
    echo '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . esc_attr__( 'Close', 'ssseo' ) . '"></button>';
    echo '      </div>';
    echo '      <div class="modal-body">' . $desc_html . '</div>';
    echo '      <div class="modal-footer">';
    echo '        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . esc_html__( 'Close', 'ssseo' ) . '</button>';
    echo '      </div>';
    echo '    </div>';
    echo '  </div>';
    echo '</div>';
}

echo '</div>'; // .ssseo-card-grid
$grid_html = ob_get_clean();

    // Build pagination HTML

    ob_start();

    if ( $total_pages > 1 ) {

        echo '<nav class="ssseo-pagination" aria-label="YouTube channel pagination"><ul class="pagination justify-content-center">';

        // Prev link

        if ( $page > 1 ) {

            $prev_page = $page - 1;

            printf(

                '<li class="page-item"><a href="#" class="page-link" data-page="%1$d" rel="prev">%2$s</a></li>',

                $prev_page,

                esc_html__( 'Prev', 'ssseo' )

            );

        }

        // Numbered page links

        for ( $i = 1; $i <= $total_pages; $i++ ) {

            $active = ( $i === $page ) ? ' active' : '';

            printf(

                '<li class="page-item%1$s"><a href="#" class="page-link" data-page="%2$d">%2$d</a></li>',

                $active,

                $i

            );

        }

        // Next link

        if ( $page < $total_pages ) {

            $next_page = $page + 1;

            printf(

                '<li class="page-item"><a href="#" class="page-link" data-page="%1$d" rel="next">%2$s</a></li>',

                $next_page,

                esc_html__( 'Next', 'ssseo' )

            );

        }

        echo '</ul></nav>';

    }

    $pag_html = ob_get_clean();

    return [

        'grid'       => $grid_html,

        'pagination' => $pag_html,

    ];

}

/**

 * 3) AJAX HANDLER FOR PAGING

 */

add_action( 'wp_ajax_nopriv_ssseo_youtube_pager', 'ssseo_ajax_youtube_pager' );

add_action( 'wp_ajax_ssseo_youtube_pager',      'ssseo_ajax_youtube_pager' );

function ssseo_ajax_youtube_pager() {

    check_ajax_referer( 'ssseo_youtube_nonce', 'nonce' );

    $channel = sanitize_text_field( wp_unslash( $_POST['channel'] ?? '' ) );

    $page    = max( 1, intval( $_POST['page'] ?? 1 ) );

    $per     = max( 1, intval( $_POST['pagesize'] ?? 4 ) );

    $max     = max( 0, intval( $_POST['max'] ?? 0 ) ); // 0 = no limit

    $out = ssseo_render_channel_page( $channel, $per, $page, $max );

    wp_send_json_success( $out );

}

/**

 * 4) ENQUEUE JS (and localize data)

 */

add_action( 'wp_enqueue_scripts', function() {

    $plugin_main_file = dirname(__DIR__) . '/ssseo-tools.php';

    wp_enqueue_script(

        'ssseo-youtube-ajax',

        plugins_url( 'assets/js/ssseo-video.js', $plugin_main_file ),

        array( 'jquery' ),

        filemtime( plugin_dir_path( $plugin_main_file ) . 'assets/js/ssseo-video.js' ),

        true

    );

    wp_localize_script( 'ssseo-youtube-ajax', 'ssseoYTAjax', array(

        'ajax_url' => admin_url( 'admin-ajax.php' ),

        'nonce'    => wp_create_nonce( 'ssseo_youtube_nonce' ),

        'channel'  => get_option( 'ssseo_youtube_channel_id', '' ),

        'pagesize' => 4,

        'max'      => 0,

    ) );

} );

/**

 * 5) ENQUEUE CUSTOMIZER JS for “Generate Video Posts” button

 */

add_action( 'customize_controls_enqueue_scripts', function() {

    $plugin_main_file = dirname(__DIR__) . '/ssseo-tools.php';

    wp_enqueue_script(

        'ssseo-generate-videos-script',

        plugins_url( 'assets/js/ssseo-generate-videos.js', $plugin_main_file ),

        array( 'jquery', 'customize-controls' ),

        filemtime( plugin_dir_path( $plugin_main_file ) . 'assets/js/ssseo-generate-videos.js' ),

        true

    );

    wp_localize_script( 'ssseo-generate-videos-script', 'ssseoGenerateAjax', array(

        'ajax_url' => admin_url( 'admin-ajax.php' ),

    ) );

} );

/**

 * 6) Bulk‐generate Video CPT posts from a YouTube channel

 *

 * @param string $channel_id YouTube Channel ID

 * @return array|WP_Error    Summary or error

 */

if ( ! function_exists( 'ssseo_generate_video_posts_from_channel' ) ) {

    function ssseo_generate_video_posts_from_channel( $channel_id ) {

        if ( ! current_user_can( 'manage_options' ) ) {

            return new WP_Error( 'forbidden', 'You do not have permission to run this function.' );

        }

        $channel_id = sanitize_text_field( $channel_id );

        if ( empty( $channel_id ) ) {

            return new WP_Error( 'no_channel', 'No channel ID provided.' );

        }

        $api_key = get_theme_mod( 'ssseo_youtube_api_key', '' );

        if ( empty( $api_key ) ) {

            return new WP_Error( 'no_api_key', 'YouTube API key not configured.' );

        }

        // Fetch channel’s uploads playlist ID

        $channel_resp = wp_remote_get( add_query_arg( array(

            'part' => 'contentDetails',

            'id'   => $channel_id,

            'key'  => $api_key,

        ), 'https://www.googleapis.com/youtube/v3/channels' ) );

        if ( is_wp_error( $channel_resp ) || 200 !== wp_remote_retrieve_response_code( $channel_resp ) ) {

            return new WP_Error( 'channel_fetch_failed', 'Unable to fetch channel info.' );

        }

        $channel_data      = json_decode( wp_remote_retrieve_body( $channel_resp ), true );

        $uploads_playlist  = $channel_data['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';

        if ( empty( $uploads_playlist ) ) {

            return new WP_Error( 'no_uploads_playlist', 'No uploads playlist found for this channel.' );

        }

        // Loop through playlist pages

        $nextPageToken   = '';

        $new_posts       = 0;

        $existing_posts  = 0;

        $errors          = array();

        do {

            $args = array(

                'part'       => 'snippet',

                'playlistId' => $uploads_playlist,

                'maxResults' => 50,

                'key'        => $api_key,

            );

            if ( $nextPageToken ) {

                $args['pageToken'] = $nextPageToken;

            }

            $playlist_resp = wp_remote_get( add_query_arg( $args, 'https://www.googleapis.com/youtube/v3/playlistItems' ) );

            if ( is_wp_error( $playlist_resp ) ) {

                $errors[] = 'Error fetching playlist page: ' . $playlist_resp->get_error_message();

                break;

            }

            if ( 200 !== wp_remote_retrieve_response_code( $playlist_resp ) ) {

                $errors[] = 'Non‐200 status when fetching playlist page: ' . wp_remote_retrieve_response_code( $playlist_resp );

                break;

            }

            $playlist_data = json_decode( wp_remote_retrieve_body( $playlist_resp ), true );

            $items         = $playlist_data['items'] ?? array();

            if ( empty( $items ) ) {

                break;

            }

            foreach ( $items as $item ) {

                $snippet    = $item['snippet'] ?? array();

                $resourceId = $snippet['resourceId'] ?? array();

                $video_id   = $resourceId['videoId'] ?? '';

                if ( empty( $video_id ) ) {

                    continue;

                }

                // Check if Video CPT exists with this slug

                $existing = get_page_by_path( $video_id, OBJECT, 'video' );

                if ( $existing ) {

                    $existing_posts++;

                    continue;

                }

                // Use YouTube video title instead of ID for post_title

                $video_title = sanitize_text_field( $snippet['title'] ?? $video_id );

                $description = $snippet['description'] ?? '';

                $postarr = array(

                    'post_title'   => $video_title,

                    'post_name'    => $video_id,

                    'post_content' => wp_slash( $description ),

                    'post_status'  => 'draft',

                    'post_type'    => 'video',

                );

                $new_id = wp_insert_post( $postarr, true );

                if ( is_wp_error( $new_id ) ) {

                    $errors[] = 'Error creating post for video ID ' . $video_id . ': ' . $new_id->get_error_message();

                    continue;

                }

                update_post_meta( $new_id, '_ssseo_video_id', $video_id );

                $new_posts++;

            }

            $nextPageToken = $playlist_data['nextPageToken'] ?? '';

        } while ( $nextPageToken );

        return array(

            'new_posts'      => $new_posts,

            'existing_posts' => $existing_posts,

            'errors'         => $errors,

        );

    }

}

/**

 * 7) AJAX handler for “Generate Video Posts” button

 */

add_action( 'wp_ajax_ssseo_generate_videos', 'ssseo_ajax_generate_videos_handler' );

function ssseo_ajax_generate_videos_handler() {

    // Nonce check

    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ssseo_generate_videos_nonce' ) ) {

        wp_send_json_error( array( 'message' => 'Invalid nonce.' ) );

    }

    // Capability check

    if ( ! current_user_can( 'manage_options' ) ) {

        wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );

    }

    // Channel ID from POST

    $channel_id = sanitize_text_field( wp_unslash( $_POST['channel'] ?? '' ) );

    if ( empty( $channel_id ) ) {

        wp_send_json_error( array( 'message' => 'No channel ID provided.' ) );

    }

    // Call the bulk‐create function

    $result = ssseo_generate_video_posts_from_channel( $channel_id );

    if ( is_wp_error( $result ) ) {

        wp_send_json_error( array( 'message' => $result->get_error_message() ) );

    }

    wp_send_json_success( $result );

}

/**

 * 8) SHORTCODE: [youtube_with_transcript url="..."]

 */

add_shortcode( 'youtube_with_transcript', function( $atts ) {

    $atts = shortcode_atts( array( 'url' => '' ), $atts, 'youtube_with_transcript' );

    if ( empty( $atts['url'] ) ) {

        return '<p><em>No YouTube URL provided.</em></p>';

    }

    if ( ! preg_match( '%(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})%i', $atts['url'], $m ) ) {

        return '<p><em>Invalid YouTube URL.</em></p>';

    }

    $video_id = $m[1];

    $html     = '<div class="ssseo-youtube-wrapper">';

    $html    .= sprintf(

        '<div class="ssseo-youtube-container"><iframe width="560" height="315" src="https://www.youtube.com/embed/%s" frameborder="0" allowfullscreen></iframe></div>',

        esc_attr( $video_id )

    );

    $api_key = get_theme_mod( 'ssseo_youtube_api_key', '' );

    if ( $api_key ) {

        $response = wp_remote_get( add_query_arg( array(

            'part' => 'snippet',

            'id'   => $video_id,

            'key'  => $api_key,

        ), 'https://www.googleapis.com/youtube/v3/videos' ) );

        if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {

            $data = json_decode( wp_remote_retrieve_body( $response ), true );

            $desc = $data['items'][0]['snippet']['description'] ?? '';

            if ( $desc ) {

                $html .= '<div class="ssseo-youtube-description">' . wpautop( esc_html( $desc ) ) . '</div>';

            }

        }

    }

    // Transcript

    $lines = array();

    $list  = wp_remote_get( "https://video.google.com/timedtext?type=list&v={$video_id}" );

    if ( ! is_wp_error( $list ) && 200 === wp_remote_retrieve_response_code( $list ) ) {

        $xml = simplexml_load_string( wp_remote_retrieve_body( $list ) );

        if ( isset( $xml->track[0]['lang_code'] ) ) {

            $lang = (string) $xml->track[0]['lang_code'];

            $tts  = wp_remote_get( "https://video.google.com/timedtext?lang={$lang}&v={$video_id}" );

            if ( ! is_wp_error( $tts ) && 200 === wp_remote_retrieve_response_code( $tts ) ) {

                $tts_xml = simplexml_load_string( wp_remote_retrieve_body( $tts ) );

                foreach ( $tts_xml->text as $text ) {

                    $lines[] = esc_html( html_entity_decode( (string) $text ) );

                }

            }

        }

    }

    if ( $lines ) {

        $html .= '<div class="accordion mt-3" id="ssseoTranscriptAccordion">'

              .    '<div class="accordion-item">'

              .      '<h2 class="accordion-header" id="ssseoTranscriptHeading">'

              .        '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ssseoTranscriptCollapse" aria-expanded="false" aria-controls="ssseoTranscriptCollapse">'

              .          esc_html__( 'Transcript', 'ssseo' )

              .        '</button>'

              .      '</h2>'

              .      '<div id="ssseoTranscriptCollapse" class="accordion-collapse collapse" aria-labelledby="ssseoTranscriptHeading" data-bs-parent="#ssseoTranscriptAccordion">'

              .        '<div class="accordion-body"><p>' . implode( '</p><p>', $lines ) . '</p></div>'

              .      '</div>'

              .    '</div>'

              .  '</div>';

    }

    $html .= '</div>'; // .ssseo-youtube-wrapper

    return $html;

} );

/**

 * 9) SHORTCODE: [youtube_channel_list]

 *

 * Attributes:

 *   - channel  : override Customizer channel ID

 *   - pagesize : videos per page (default 4)

 *   - max      : total maximum videos to fetch (0 = no limit)

 *   - paging   : 'true' or 'false' (default 'true')

 */

add_shortcode( 'youtube_channel_list', function( $atts ) {

    $defaults = array(

        'channel'  => get_theme_mod( 'ssseo_youtube_channel_id', '' ),

        'pagesize' => 4,

        'max'      => 0,

        'paging'   => 'true',

    );

    $atts = shortcode_atts( $defaults, $atts, 'youtube_channel_list' );

    $channel = sanitize_text_field( $atts['channel'] );

    $per     = max( 1, intval( $atts['pagesize'] ) );

    $max     = max( 0, intval( $atts['max'] ) );

    $paging  = filter_var( $atts['paging'], FILTER_VALIDATE_BOOLEAN );

    if ( ! $channel ) {

        return '<p><em>No channel ID provided.</em></p>';

    }

    // Render first page

    $first = ssseo_render_channel_page( $channel, $per, 1, $max );

    ob_start();

    ?>

    <div

      class="ssseo-youtube-wrapper-container"

      data-channel="<?php echo esc_attr( $channel ); ?>"

      data-pagesize="<?php echo esc_attr( $per ); ?>"

      data-max="<?php echo esc_attr( $max ); ?>"

      data-paging="<?php echo $paging ? 'true' : 'false'; ?>"

    >

        <?php

        echo $first['grid'];

        if ( $paging ) {

            echo $first['pagination'];

        }

        ?>

    </div>

    <?php

    return ob_get_clean();

} );

/**

 * 10) HELPER: Fetch & Render Detailed List of Videos (no AJAX)

 */

if ( ! function_exists( 'ssseo_get_channel_videos_with_details' ) ) {

    function ssseo_get_channel_videos_with_details( $channel_id, $max_results = 5 ) {

        $api_key = get_theme_mod( 'ssseo_youtube_api_key', '' );

        if ( ! $api_key ) {

            return '<p><em>YouTube API key not configured.</em></p>';

        }

        if ( ! $channel_id ) {

            return '<p><em>No channel ID provided.</em></p>';

        }

        // Fetch uploads playlist

        $resp = wp_remote_get( add_query_arg( [

            'part' => 'contentDetails',

            'id'   => $channel_id,

            'key'  => $api_key,

        ], 'https://www.googleapis.com/youtube/v3/channels' ) );

        if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {

            return '<p><em>Unable to retrieve channel info.</em></p>';

        }

        $data    = json_decode( wp_remote_retrieve_body( $resp ), true );

        $uploads = $data['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';

        if ( ! $uploads ) {

            return '<p><em>No uploads playlist found.</em></p>';

        }

        // Fetch up to 50 videos, then slice

        $resp = wp_remote_get( add_query_arg( [

            'part'       => 'snippet',

            'playlistId' => $uploads,

            'maxResults' => min( 50, intval( $max_results ) ),

            'key'        => $api_key,

        ], 'https://www.googleapis.com/youtube/v3/playlistItems' ) );

        if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {

            return '<p><em>Unable to retrieve playlist videos.</em></p>';

        }

        $items = json_decode( wp_remote_retrieve_body( $resp ), true )['items'] ?? [];

        if ( empty( $items ) ) {

            return '<p><em>No videos found.</em></p>';

        }

        // Build HTML

        ob_start();

        echo '<div class="row">';

        foreach ( $items as $item ) {

            $vid   = esc_attr( $item['snippet']['resourceId']['videoId'] );

            $title = esc_html( $item['snippet']['title'] );

            $desc  = esc_html( $item['snippet']['description'] );

            $thumb = esc_url( $item['snippet']['thumbnails']['medium']['url'] );

            // Attempt to retrieve transcript lines

            $lines = array();

            $list  = wp_remote_get( "https://video.google.com/timedtext?type=list&v={$vid}" );

            if ( ! is_wp_error( $list ) && 200 === wp_remote_retrieve_response_code( $list ) ) {

                $xml = simplexml_load_string( wp_remote_retrieve_body( $list ) );

                if ( isset( $xml->track[0]['lang_code'] ) ) {

                    $lang = (string) $xml->track[0]['lang_code'];

                    $tts  = wp_remote_get( "https://video.google.com/timedtext?lang={$lang}&v={$vid}" );

                    if ( ! is_wp_error( $tts ) && 200 === wp_remote_retrieve_response_code( $tts ) ) {

                        $tts_xml = simplexml_load_string( wp_remote_retrieve_body( $tts ) );

                        foreach ( $tts_xml->text as $t ) {

                            $lines[] = esc_html( html_entity_decode( (string) $t ) );

                        }

                    }

                }

            }

            echo '<div class="col-12 col-md-6 mb-4">';

            echo '  <div class="card h-100">';

            if ( $thumb ) {

                printf(

                    '<img src="%1$s" class="card-img-top" alt="%2$s">',

                    $thumb, $title

                );

            }

            echo '    <div class="card-body">';

            printf(

                '<h5 class="card-title">%1$s</h5>',

                $title

            );

            printf(

                '<div class="yt-desc"><strong>Description:</strong><p>%1$s</p></div>',

                nl2br( $desc )

            );

            if ( $lines ) {

                printf(

                    '<div class="yt-transcript mt-3"><strong>Transcript:</strong><p>%1$s</p></div>',

                    implode( '</p><p>', $lines )

                );

            }

            echo '    </div>'; // .card-body

            echo '  </div>';   // .card

            echo '</div>';     // .col

        }

        echo '</div>'; // .row

        return ob_get_clean();

    }

}

/**

 * 11) SHORTCODE: [youtube_channel_list_detailed]

 *

 * Outputs a simple list of up to "max" videos (no AJAX paging).

 */

add_shortcode( 'youtube_channel_list_detailed', function( $atts ) {

    $defaults = array(

        'channel' => get_theme_mod( 'ssseo_youtube_channel_id', '' ),

        'max'     => 5,

    );

    $atts = shortcode_atts( $defaults, $atts, 'youtube_channel_list_detailed' );

    $channel = sanitize_text_field( $atts['channel'] );

    $max     = max( 1, intval( $atts['max'] ) );

    if ( ! $channel ) {

        return '<p><em>No channel ID provided.</em></p>';

    }

    return ssseo_get_channel_videos_with_details( $channel, $max );

} );

/**

 * 12) REGISTER “Video” CUSTOM POST TYPE

 *

 * Archive URL: /videos/

 * Single  URL: /video/{post-slug}/

 */

function ssseo_register_video_cpt() {

    $labels = array(

        'name'                  => _x( 'Videos', 'Post Type General Name', 'ssseo' ),

        'singular_name'         => _x( 'Video', 'Post Type Singular Name', 'ssseo' ),

        'menu_name'             => __( 'Videos', 'ssseo' ),

        'name_admin_bar'        => __( 'Video', 'ssseo' ),

        'archives'              => __( 'Video Archives', 'ssseo' ),

        'attributes'            => __( 'Video Attributes', 'ssseo' ),

        'parent_item_colon'     => __( 'Parent Video:', 'ssseo' ),

        'all_items'             => __( 'All Videos', 'ssseo' ),

        'add_new_item'          => __( 'Add New Video', 'ssseo' ),

        'add_new'               => __( 'Add New', 'ssseo' ),

        'new_item'              => __( 'New Video', 'ssseo' ),

        'edit_item'             => __( 'Edit Video', 'ssseo' ),

        'update_item'           => __( 'Update Video', 'ssseo' ),

        'view_item'             => __( 'View Video', 'ssseo' ),

        'view_items'            => __( 'View Videos', 'ssseo' ),

        'search_items'          => __( 'Search Video', 'ssseo' ),

        'not_found'             => __( 'Not found', 'ssseo' ),

        'not_found_in_trash'    => __( 'Not found in Trash', 'ssseo' ),

        'featured_image'        => __( 'Featured Image', 'ssseo' ),

        'set_featured_image'    => __( 'Set featured image', 'ssseo' ),

        'remove_featured_image' => __( 'Remove featured image', 'ssseo' ),

        'use_featured_image'    => __( 'Use as featured image', 'ssseo' ),

        'insert_into_item'      => __( 'Insert into video', 'ssseo' ),

        'uploaded_to_this_item' => __( 'Uploaded to this video', 'ssseo' ),

        'items_list'            => __( 'Videos list', 'ssseo' ),

        'items_list_navigation' => __( 'Videos list navigation', 'ssseo' ),

        'filter_items_list'     => __( 'Filter videos list', 'ssseo' ),

    );

    $args = array(

        'label'                 => __( 'Video', 'ssseo' ),

        'description'           => __( 'A custom post type to store individual videos', 'ssseo' ),

        'labels'                => $labels,

        'supports'              => array(

            'title',

            'editor',

            'thumbnail',

            'excerpt',

            'custom-fields',

            'page-attributes',

            'tags',

        ),

        'taxonomies'            => array( 'post_tag' ),

        'hierarchical'          => true,

        'public'                => true,

        'show_ui'               => true,

        'show_in_menu'          => true,

        'menu_position'         => 21,

        'menu_icon'             => 'dashicons-video-alt3',

        'show_in_admin_bar'     => true,

        'show_in_nav_menus'     => true,

        'can_export'            => true,

        // Archive at /videos/

        'has_archive'           => 'videos',

        // Single posts at /video/{post-slug}/

        'rewrite'               => array(

            'slug'       => 'video',

            'with_front' => false

        ),

        'exclude_from_search'   => false,

        'publicly_queryable'    => true,

        'show_in_rest'          => true,

    );

    register_post_type( 'video', $args );

}

add_action( 'init', 'ssseo_register_video_cpt' );

/**

 * 13) ADD “YouTube Video ID” META-BOX FOR CPT “video”

 */

add_action('add_meta_boxes', function() {
    add_meta_box('ssseo_video_id_box', 'YouTube Video Integration', 'ssseo_video_id_metabox_callback', 'video', 'normal', 'high');
});
function ssseo_add_video_id_metabox() {

    add_meta_box(

        'ssseo_video_id',                   // HTML id attribute

        __( 'YouTube Video ID', 'ssseo' ), // Meta box title

        'ssseo_video_id_metabox_
  
  
  
  
  
  ',  // Callback to render the box

        'video',                            // Post type

        'normal',                           // Context

        'high'                              // Priority

    );

}

function ssseo_video_id_metabox_callback( $post ) {
    wp_nonce_field( 'ssseo_save_video_id', 'ssseo_video_id_nonce' );

    $video_id  = get_post_meta( $post->ID, '_ssseo_video_id', true );
    $transcript = get_post_meta( $post->ID, '_ssseo_video_transcript', true );
    $captions   = get_post_meta( $post->ID, '_ssseo_video_captions', true );

    echo '<label for="ssseo_video_id_field">' . esc_html__( 'Enter the YouTube Video ID:', 'ssseo' ) . '</label><br>';
    printf(
        '<input type="text" id="ssseo_video_id_field" name="ssseo_video_id_field" value="%1$s" size="25" placeholder="e.g. dQw4w9WgXcQ" class="regular-text" />',
        esc_attr( $video_id )
    );

    echo '<br><br>';
    echo '<button type="button" class="button button-primary" id="ssseo-generate-transcript" data-post-id="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Generate Transcript', 'ssseo' ) . '</button> ';
    echo '<button type="button" class="button" id="ssseo-fetch-captions" data-post-id="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Fetch Captions', 'ssseo' ) . '</button> ';
    echo '<button type="button" class="button" id="ssseo-fetch-ai-captions" data-post-id="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Fetch AI Captions', 'ssseo' ) . '</button>';

    echo '<div id="ssseo-transcript-result" style="margin-top:15px;">';
    if ( $transcript ) {
        echo '<strong>Transcript Preview:</strong>';
        echo '<textarea readonly style="width:100%;height:200px;">' . esc_textarea( $transcript ) . '</textarea>';
    }
    echo '</div>';

    echo '<div id="ssseo-captions-result" style="margin-top:15px;">';
    echo '<strong>Captions:</strong>';
    echo '<textarea readonly style="width:100%;height:150px;">' .
         esc_textarea( $captions ?: 'No captions found yet. Click a Fetch button.' ) .
         '</textarea>';
    echo '</div>';
}



add_action( 'save_post', 'ssseo_save_video_id_metabox_data' );

function ssseo_save_video_id_metabox_data( $post_id ) {

    if ( ! isset( $_POST['ssseo_video_id_nonce'] ) ) {

        return;

    }

    if ( ! wp_verify_nonce( $_POST['ssseo_video_id_nonce'], 'ssseo_save_video_id' ) ) {

        return;

    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {

        return;

    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {

        return;

    }

    if ( isset( $_POST['ssseo_video_id_field'] ) ) {

        $new_id = sanitize_text_field( wp_unslash( $_POST['ssseo_video_id_field'] ) );

        update_post_meta( $post_id, '_ssseo_video_id', $new_id );

    }

}

/**

 * 14) Prepend YouTube embed to the_content for 'video' CPT

 */

add_filter( 'the_content', 'ssseo_prepend_video_embed_to_content' );

function ssseo_prepend_video_embed_to_content( $content ) {

    if ( is_singular( 'video' ) && in_the_loop() && is_main_query() ) {

        $video_id = get_post_meta( get_the_ID(), '_ssseo_video_id', true );

        if ( $video_id ) {

            $embed  = '<div class="ssseo-video-embed-wrapper" style="margin-bottom:2rem; max-width:800px; width:100%; margin-left:auto; margin-right:auto;">';

            $embed .= '  <div class="ratio ratio-16x9">';

            $embed .= '    <iframe src="https://www.youtube.com/embed/' . esc_attr( $video_id ) . '" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';

            $embed .= '  </div>';

            $embed .= '</div>';

            return $embed . $content;

        }

    }

    return $content;

}

/**

 * 15) BULK‐GENERATE “Video” CPT POSTS FROM A YOUTUBE CHANNEL

 *

 * @param string $channel_id YouTube Channel ID

 * @return array|WP_Error    Summary or error

 */

if ( ! function_exists( 'ssseo_generate_video_posts_from_channel' ) ) {

    function ssseo_generate_video_posts_from_channel( $channel_id ) {

        if ( ! current_user_can( 'manage_options' ) ) {

            return new WP_Error( 'forbidden', 'You do not have permission to run this function.' );

        }

        $channel_id = sanitize_text_field( $channel_id );

        if ( empty( $channel_id ) ) {

            return new WP_Error( 'no_channel', 'No channel ID provided.' );

        }

        $api_key = get_theme_mod( 'ssseo_youtube_api_key', '' );

        if ( empty( $api_key ) ) {

            return new WP_Error( 'no_api_key', 'YouTube API key not configured.' );

        }

        // Fetch channel’s uploads playlist ID

        $channel_resp = wp_remote_get( add_query_arg( array(

            'part' => 'contentDetails',

            'id'   => $channel_id,

            'key'  => $api_key,

        ), 'https://www.googleapis.com/youtube/v3/channels' ) );

        if ( is_wp_error( $channel_resp ) || 200 !== wp_remote_retrieve_response_code( $channel_resp ) ) {

            return new WP_Error( 'channel_fetch_failed', 'Unable to fetch channel info.' );

        }

        $channel_data      = json_decode( wp_remote_retrieve_body( $channel_resp ), true );

        $uploads_playlist  = $channel_data['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';

        if ( empty( $uploads_playlist ) ) {

            return new WP_Error( 'no_uploads_playlist', 'No uploads playlist found for this channel.' );

        }

        // Loop through playlist pages

        $nextPageToken   = '';

        $new_posts       = 0;

        $existing_posts  = 0;

        $errors          = array();

        do {

            $args = array(

                'part'       => 'snippet',

                'playlistId' => $uploads_playlist,

                'maxResults' => 50,

                'key'        => $api_key,

            );

            if ( $nextPageToken ) {

                $args['pageToken'] = $nextPageToken;

            }

            $playlist_resp = wp_remote_get( add_query_arg( $args, 'https://www.googleapis.com/youtube/v3/playlistItems' ) );

            if ( is_wp_error( $playlist_resp ) ) {

                $errors[] = 'Error fetching playlist page: ' . $playlist_resp->get_error_message();

                break;

            }

            if ( 200 !== wp_remote_retrieve_response_code( $playlist_resp ) ) {

                $errors[] = 'Non‐200 status when fetching playlist page: ' . wp_remote_retrieve_response_code( $playlist_resp );

                break;

            }

            $playlist_data = json_decode( wp_remote_retrieve_body( $playlist_resp ), true );

            $items         = $playlist_data['items'] ?? array();

            if ( empty( $items ) ) {

                break;

            }

            foreach ( $items as $item ) {

                $snippet    = $item['snippet'] ?? array();

                $resourceId = $snippet['resourceId'] ?? array();

                $video_id   = $resourceId['videoId'] ?? '';

                if ( empty( $video_id ) ) {

                    continue;

                }

                // Check if Video CPT exists with this slug

                $existing = get_page_by_path( $video_id, OBJECT, 'video' );

                if ( $existing ) {

                    $existing_posts++;

                    continue;

                }

                // Use YouTube video title instead of ID for post_title

                $video_title = sanitize_text_field( $snippet['title'] ?? $video_id );

                $description = $snippet['description'] ?? '';

                $postarr = array(

                    'post_title'   => $video_title,

                    'post_name'    => $video_id,

                    'post_content' => wp_slash( $description ),

                    'post_status'  => 'draft',

                    'post_type'    => 'video',

                );

                $new_id = wp_insert_post( $postarr, true );

                if ( is_wp_error( $new_id ) ) {

                    $errors[] = 'Error creating post for video ID ' . $video_id . ': ' . $new_id->get_error_message();

                    continue;

                }

                update_post_meta( $new_id, '_ssseo_video_id', $video_id );

                $new_posts++;

            }

            $nextPageToken = $playlist_data['nextPageToken'] ?? '';

        } while ( $nextPageToken );

        return array(

            'new_posts'      => $new_posts,

            'existing_posts' => $existing_posts,

            'errors'         => $errors,

        );

    }

}

/**

 * 19) ARCHIVE SCHEMA: Enhanced ItemList for /videos/ (ordered by modified DESC)

 *

 * Outputs an ItemList JSON-LD on the “video” CPT archive, sorted by post_modified DESC.

 * Hooked to wp_head at priority 20 so it appears after most other head content.

 */

add_action( 'wp_head', 'ssseo_insert_video_archive_itemlist_schema', 20 );

function ssseo_insert_video_archive_itemlist_schema() {

    // Only run on the "video" post-type archive (/videos/)

    if ( ! is_post_type_archive( 'video' ) ) {

        return;

    }

    // Fetch all published "video" CPT posts, ordered by modified date descending

    $videos = get_posts( array(

        'post_type'      => 'video',

        'post_status'    => 'publish',

        'posts_per_page' => -1,

        'orderby'        => 'modified',

        'order'          => 'DESC',

    ) );

    if ( empty( $videos ) ) {

        return;

    }

    // Build the unique @id: archive URL + #videoList

    $archive_url = get_post_type_archive_link( 'video' );

    $list_id     = trailingslashit( $archive_url ) . '#videoList';

    // Construct the base ItemList schema array

    $itemList = array(

        '@context'        => 'https://schema.org',

        '@type'           => 'ItemList',

        '@id'             => esc_url( $list_id ),

        'name'            => get_bloginfo( 'name' ) . ' – Video Gallery',

        'description'     => 'Browse all video posts on ' . get_bloginfo( 'name' ),

        'publisher'       => array(

            '@type' => 'Organization',

            'name'  => get_bloginfo( 'name' ),

            'logo'  => array(

                '@type' => 'ImageObject',

                'url'   => get_option( 'ssseo_schema_org_logo', '' ) ?: '',

            ),

            'url'   => home_url(),

        ),

        'numberOfItems'   => count( $videos ),

        'itemListOrder'   => 'https://schema.org/ItemListOrderDescending',

        'dateModified'    => current_time( 'c' ),

        'itemListElement' => array(),

    );

    // Populate each ListItem with a nested VideoObject

    foreach ( $videos as $index => $video_post ) {

        $post_id     = $video_post->ID;

        $permalink   = get_permalink( $post_id );

        $title       = get_the_title( $post_id );

        $publish_c   = get_the_date( 'c', $post_id );

        $video_id    = get_post_meta( $post_id, '_ssseo_video_id', true );

        $thumbnail   = $video_id ? 'https://i.ytimg.com/vi/' . esc_attr( $video_id ) . '/mqdefault.jpg' : '';

        $embed_url   = $video_id ? 'https://www.youtube.com/embed/' . esc_attr( $video_id ) : '';

        $description = get_the_excerpt( $post_id );

        if ( empty( $description ) ) {

            // Fallback to the title if excerpt is empty

            $description = $title;

        }

        $itemList['itemListElement'][] = array(

            '@type'    => 'ListItem',

            'position' => $index + 1,

            'item'     => array_filter( array(

                '@type'             => 'VideoObject',

                'mainEntityOfPage'  => esc_url( $permalink ),

                'url'               => esc_url( $permalink ),

                'name'              => sanitize_text_field( $title ),

                'description'       => sanitize_text_field( $description ),

                'thumbnailUrl'      => $thumbnail ? array( esc_url( $thumbnail ) ) : '',

                'uploadDate'        => $publish_c,

                'embedUrl'          => esc_url( $embed_url ),

            ) ),

        );

    }

    // Wrap in HTML comments so we can spot it in source

    echo "\n<!-- BEGIN Video ItemList JSON-LD -->\n";

    echo '<script type="application/ld+json">' . "\n";

    echo wp_json_encode( $itemList, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n";

    echo '</script>' . "\n";

    echo "<!-- END Video ItemList JSON-LD -->\n";

}

/**

 * 20) SINGLE VIDEO SCHEMA: Enhanced VideoObject for /video/{slug}/

 *

 * Outputs a VideoObject JSON-LD on the single “video” CPT page, including

 * all required + recommended properties (name, description, thumbnailUrl,

 * uploadDate, duration, embedUrl, interactionStatistic, publisher, etc.).

 *

 * Hooked into wp_head at priority 20 so it appears after most other head content.

 */

add_action( 'wp_head', 'ssseo_insert_single_video_schema', 20 );

function ssseo_insert_single_video_schema() {

    if ( ! is_singular( 'video' ) ) {

        return;

    }

    global $post;

    $post_id   = $post->ID;

    // 1) Grab the stored YouTube video ID from post meta

    $video_id = get_post_meta( $post_id, '_ssseo_video_id', true );

    if ( empty( $video_id ) ) {

        return; // No valid video ID; bail out

    }

    // 2) Title & Description

    $name        = get_the_title( $post_id );

    $description = get_the_excerpt( $post_id );

    if ( empty( $description ) ) {

        $description = $name; // fallback to title if no excerpt

    }

    // 3) Thumbnail URL (YouTube medium quality)

    $thumbnailUrl = "https://i.ytimg.com/vi/{$video_id}/mqdefault.jpg";

    // 4) Embed URL (YouTube)

    $embedUrl = "https://www.youtube.com/embed/{$video_id}";

    // 5) Fetch duration, uploadDate, viewCount via YouTube Data API

    $api_key    = get_theme_mod( 'ssseo_youtube_api_key', '' );

    $duration   = '';

    $uploadDate = '';

    $viewCount  = '';

    if ( ! empty( $api_key ) ) {

        $yt_response = wp_remote_get( add_query_arg( array(

            'part' => 'snippet,contentDetails,statistics',

            'id'   => $video_id,

            'key'  => $api_key,

        ), 'https://www.googleapis.com/youtube/v3/videos' ) );

        if ( ! is_wp_error( $yt_response ) && 200 === wp_remote_retrieve_response_code( $yt_response ) ) {

            $yt_data = json_decode( wp_remote_retrieve_body( $yt_response ), true );

            if ( ! empty( $yt_data['items'][0] ) ) {

                $item        = $yt_data['items'][0];

                $duration    = $item['contentDetails']['duration'] ?? '';

                $uploadDate  = $item['snippet']['publishedAt'] ?? '';

                $viewCount   = $item['statistics']['viewCount'] ?? '';

            }

        }

    }

    // 6) Publisher (Organization) – pull from Schema Settings in Admin

    $org_name     = get_option( 'ssseo_schema_org_name', get_bloginfo( 'name' ) );

    $org_logo_url = get_option( 'ssseo_schema_org_logo', '' );

    $org_website  = get_option( 'ssseo_schema_org_url', home_url() );

    // 7) Build the VideoObject array

    $videoObject = array_filter( array(

        '@context'             => 'https://schema.org',

        '@type'                => 'VideoObject',

        'mainEntityOfPage'     => esc_url( get_permalink( $post_id ) ),

        'url'                  => esc_url( get_permalink( $post_id ) ),

        'name'                 => sanitize_text_field( $name ),

        'description'          => sanitize_text_field( $description ),

        'thumbnailUrl'         => array( esc_url( $thumbnailUrl ) ),

        'uploadDate'           => $uploadDate,         // e.g. "2025-01-15T10:30:00Z"

        'duration'             => $duration,           // e.g. "PT2M15S"

        'embedUrl'             => esc_url( $embedUrl ),

        'interactionStatistic' => array_filter( array(

            '@type'              => 'InteractionCounter',

            'interactionType'    => array(

                '@type' => 'http://schema.org/WatchAction',

            ),

            'userInteractionCount' => intval( $viewCount ),

        ) ),

        'publisher'            => array_filter( array(

            '@type' => 'Organization',

            'name'  => sanitize_text_field( $org_name ),

            'logo'  => array_filter( array(

                '@type' => 'ImageObject',

                'url'   => esc_url( $org_logo_url ),

            ) ),

            'url'   => esc_url( $org_website ),

        ) ),

        'isFamilyFriendly'     => 'true',

        // Optional: if you want to include a content rating, uncomment below

        // 'contentRating'        => 'PG-13',

    ) );

    // 8) Output the JSON-LD in the <head>

    echo "\n<!-- BEGIN Single VideoObject JSON-LD -->\n";

    echo '<script type="application/ld+json">' . "\n";

    echo wp_json_encode( $videoObject, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n";

    echo '</script>' . "\n";

    echo "<!-- END Single VideoObject JSON-LD -->\n";

}


