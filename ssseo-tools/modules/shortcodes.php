<?php
// ShortCode to display Service Areas

function service_area_shortcode() {
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
add_shortcode('service_area_list_all', 'service_area_shortcode_all');

function service_area_shortcode_all() {
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
add_shortcode('service_area_list_all', 'service_area_shortcode_all');
/**
 * Shortcode: [faq_schema_accordion]
 * 
 * Outputs a Bootstrap 5 accordion of your ACF repeater 'faq_items'.
 */
function faq_schema_accordion_shortcode( $atts ) {
    // Make sure ACF is active
    if ( ! function_exists( 'have_rows' ) || ! function_exists( 'get_sub_field' ) ) {
        return '<p><em>ACF not active or missing repeater support.</em></p>';
    }

    // Determine current post ID
    global $post;
    $post_id = $post->ID ?? get_the_ID();
    if ( ! $post_id ) {
        return '<p><em>Unable to determine post ID.</em></p>';
    }

    // Bail if no rows
    if ( ! have_rows( 'faq_items', $post_id ) ) {
        return '<p><em>No FAQs found.</em></p>';
    }

    // Unique container ID
    $accordion_id = 'faqAccordion_' . uniqid();

    ob_start();
    ?>
    <div class="accordion ssseo-accordion" id="<?php echo esc_attr( $accordion_id ); ?>">
        <?php
        $index = 0;
        while ( have_rows( 'faq_items', $post_id ) ) {
            the_row();

            // Pull sub-fields by their ACF *field names* (no punctuation)
            $raw_q = get_sub_field( 'question' );
            $raw_a = get_sub_field( 'answer' );

            // Sanitize
            $question = trim( sanitize_text_field( $raw_q ) );
            $answer   = trim( wp_kses_post(      $raw_a ) );

            // Skip empty
            if ( ! $question || ! $answer ) {
                continue;
            }

            // IDs for collapse targets
            $heading_id  = "{$accordion_id}_heading_{$index}";
            $collapse_id = "{$accordion_id}_collapse_{$index}";
        ?>
            <div class="ssseo-accordion-item">
                <h2 class="ssseo-accordion-header" id="<?php echo esc_attr( $heading_id ); ?>">
                    <button
                        class="accordion-button collapsed"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#<?php echo esc_attr( $collapse_id ); ?>"
                        aria-expanded="false"
                        aria-controls="<?php echo esc_attr( $collapse_id ); ?>"
                    >
                        <?php echo esc_html( $question ); ?>
                    </button>
                </h2>
                <div
                    id="<?php echo esc_attr( $collapse_id ); ?>"
                    class="accordion-collapse collapse"
                    aria-labelledby="<?php echo esc_attr( $heading_id ); ?>"
                    data-bs-parent="#<?php echo esc_attr( $accordion_id ); ?>"
                >
                    <div class="accordion-body">
                        <?php echo $answer; ?>
                    </div>
                </div>
            </div>
        <?php
            $index++;
        } // end while
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'faq_schema_accordion', 'faq_schema_accordion_shortcode' );

/**
 * Shortcode: [ssseo_category_list include_empty="0|1" min_count="0"]
 * Outputs a Bootstrap list-group of post categories, filtered by minimum post count.
 * 
 * @param array $atts {
 *     Shortcode attributes.
 *
 *     @type string $include_empty '1' to include empty categories, '0' (default) to hide them.
 *     @type int    $min_count     Minimum number of posts in a category to display (default 0).
 * }
 * @return string HTML markup for the category list.
 */
function ssseo_category_list_shortcode( $atts ) {
    // Merge user attributes with defaults.
    $atts = shortcode_atts( array(
        'include_empty' => '0',
        'min_count'     => 0,
    ), $atts, 'ssseo_category_list' );

    // Determine whether to hide empty categories.
    $hide_empty = ( '1' !== $atts['include_empty'] );

    // Ensure min_count is an integer ≥ 0.
    $min_count = max( 0, intval( $atts['min_count'] ) );

    // Fetch all categories based on hide_empty setting.
    $categories = get_categories( array(
        'hide_empty' => $hide_empty,
    ) );

    // Filter out those below min_count.
    $filtered = array_filter( $categories, function( $cat ) use ( $min_count ) {
        return $cat->count >= $min_count;
    } );

    if ( empty( $filtered ) ) {
        return '';
    }

    // Build the Bootstrap list-group.
    $output  = '<div class="list-group ssseo">';
    foreach ( $filtered as $category ) {
        $link = esc_url( get_category_link( $category->term_id ) );
        $name = esc_html( $category->name );

        $output .= sprintf(
            '<a href="%1$s" class="list-group-item list-group-item-action ssseo" style="background-color: var(--e-global-color-primary); color: var(--e-global-color-secondary);">%2$s <span class="badge badge-light">%3$d posts</span></a>',
            $link,
            $name,
            intval( $category->count )
        );
    }
    $output .= '</div>';

    return $output;
}
add_shortcode( 'ssseo_category_list', 'ssseo_category_list_shortcode' );


/**
 * Shortcode: [custom_blog_cards posts_per_page="12"]
 * Renders a Bootstrap card grid of your latest posts (default 12 per page).
 */
function custom_blog_cards_shortcode( $atts ) {
    // Allow user to override posts per page via shortcode attribute
    $atts = shortcode_atts(
        array(
            'posts_per_page' => 12,
        ),
        $atts,
        'custom_blog_cards'
    );

    // Figure out current pagination page
    $paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : ( get_query_var( 'page' ) ? get_query_var( 'page' ) : 1 );

    // Custom query for posts
    $query_args = array(
        'post_type'      => 'post',
        'paged'          => $paged,
        'posts_per_page' => intval( $atts['posts_per_page'] ),
    );
    $custom_query = new WP_Query( $query_args );

    ob_start();

    if ( $custom_query->have_posts() ) : ?>
        <div class="row">
        <?php
        while ( $custom_query->have_posts() ) :
            $custom_query->the_post(); ?>
            <div class="col-md-4 mb-4">
              <a href="<?php the_permalink(); ?>"
                 class="card h-100 text-decoration-none text-reset">
                <?php if ( has_post_thumbnail() ) : ?>
                  <img
                    src="<?php echo esc_url( get_the_post_thumbnail_url( get_the_ID(), 'medium' ) ); ?>"
                    class="card-img-top"
                    alt="<?php the_title_attribute(); ?>"
                  >
                <?php endif; ?>

                <div class="card-body">
                  <h5 class="card-title"><?php the_title(); ?></h5>
                  <div class="card-meta mb-2">
                    <span class="meta-date"><?php echo get_the_date( 'm/d/Y' ); ?></span>
                    <span class="meta-author">by <?php echo get_the_author(); ?></span>
                  </div>
                  <p class="card-text">
                    <?php echo wp_trim_words( get_the_excerpt(), 20 ); ?>
                  </p>
                </div>
              </a>
            </div>
        <?php endwhile; ?>
        </div> <!-- .row -->

        <?php
        // Temporarily swap in our custom query so pagination works
        global $wp_query;
        $orig_query = $wp_query;
        $wp_query   = $custom_query;

        // Output WP core pagination
        the_posts_pagination( array(
            'mid_size'           => 2,
            'prev_text'          => '&laquo; Prev',
            'next_text'          => 'Next &raquo;',
            'screen_reader_text' => 'Posts navigation',
        ) );

        // Restore original query object
        $wp_query = $orig_query;
        wp_reset_postdata();

    else : ?>
        <p><?php esc_html_e( 'Sorry, no posts found.', 'ssseo' ); ?></p>
    <?php
    endif;

    return ob_get_clean();
}
add_shortcode( 'custom_blog_cards', 'custom_blog_cards_shortcode' );


/**
 * Social Sharing Shortcode with Bootstrap Icons + Modal
 * Usage: [social_share]
 */

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
    // Load Bootstrap CSS
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');

    // ✅ Load Bootstrap Icons
    wp_enqueue_style('bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css');

    // Load Bootstrap JS
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], null, true);
});


// Register Shortcode
add_shortcode('social_share', 'ssseo_social_sharing_shortcode');

function ssseo_social_sharing_shortcode($atts) {
    ob_start();

    $share_url   = urlencode(get_permalink());
    $share_title = urlencode(get_the_title());

    ?>
<style>
  .modal-dialog {
    margin: 1.75rem auto;
  }

  .modal-content {
    padding: 1rem;
  }

  .btn-outline-* {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
</style>

    <!-- Trigger Button -->
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#socialShareModal">
        <i class="bi bi-share-fill"></i> Share
    </button>

    <!-- Modal -->
<div class="modal fade" id="socialShareModal" tabindex="-1" aria-labelledby="socialShareModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;"> <!-- ✅ limit modal width -->
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header">
        <h5 class="modal-title" id="socialShareModalLabel">Share This Page</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body text-center">
        <p class="mb-3">Choose a platform:</p>

        <!-- ✅ Tighter icon layout -->
        <div class="d-flex justify-content-center flex-wrap gap-2">
          <a class="btn btn-outline-primary" href="https://www.facebook.com/sharer/sharer.php?u=<?= $share_url ?>" target="_blank" title="Facebook" data-bs-toggle="tooltip">
            <i class="bi bi-facebook fs-4"></i>
          </a>
          <a class="btn btn-outline-info" href="https://twitter.com/intent/tweet?text=<?= $share_title ?>&url=<?= $share_url ?>" target="_blank" title="Twitter/X" data-bs-toggle="tooltip">
            <i class="bi bi-twitter-x fs-4"></i>
          </a>
          <a class="btn btn-outline-success" href="https://api.whatsapp.com/send?text=<?= $share_title ?>%20<?= $share_url ?>" target="_blank" title="WhatsApp" data-bs-toggle="tooltip">
            <i class="bi bi-whatsapp fs-4"></i>
          </a>
          <a class="btn btn-outline-danger" href="mailto:?subject=<?= $share_title ?>&body=<?= $share_url ?>" target="_blank" title="Email" data-bs-toggle="tooltip">
            <i class="bi bi-envelope-fill fs-4"></i>
          </a>
          <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= get_permalink() ?>'); this.innerHTML='<i class=\'bi bi-clipboard-check-fill\'></i>';" title="Copy Link" data-bs-toggle="tooltip">
            <i class="bi bi-clipboard fs-4"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>


    <!-- Tooltip Activation -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
    <?php

    return ob_get_clean();
}

add_shortcode('about_the_area', 'ssseo_shortcode_about_the_area');
function ssseo_shortcode_about_the_area($atts) {
    global $post;
    if (! $post || $post->post_type !== 'service_area') return '';

    $content = get_post_meta($post->ID, '_about_the_area', true);
    return wpautop(do_shortcode($content));
}

/**
 * Shortcode: [service_grid]
 * Displays 'service' posts in a responsive Bootstrap grid.
 * Each service is wrapped in a div with class 'service-box'.
 */
function ssseo_service_grid_shortcode() {
    ob_start();

    $services = new WP_Query([
        'post_type'      => 'service',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ]);

    if ( $services->have_posts() ) :
        echo '<div class="row">';

        while ( $services->have_posts() ) : $services->the_post();
            $post_id    = get_the_ID();
            $title      = get_the_title();
            $permalink  = get_permalink();
            $thumb_url  = get_the_post_thumbnail_url( $post_id, 'large' );

            echo '<div class="col-md-6 col-lg-3 mb-4">';
            echo '<div class="service-box h-100">';

            if ( $thumb_url ) {
                echo '<a href="' . esc_url( $permalink ) . '">';
                echo '<img src="' . esc_url( $thumb_url ) . '" alt="' . esc_attr( $title ) . '" class="img-fluid mb-3 rounded">';
                echo '</a>';
            }

            echo '<h4><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></h4>';
            echo '</div></div>';
        endwhile;

        echo '</div>';
        wp_reset_postdata();
    else :
        echo '<p>No services found.</p>';
    endif;

    return ob_get_clean();
}
add_shortcode( 'service_grid', 'ssseo_service_grid_shortcode' );

// [city_only] – extract the "City" part from an ACF/meta "city_state" field.
// Attributes:
//  - post_id:   (int)     specific post id; default: current post
//  - from:      (string)  'self' | 'parent' | 'ancestor' (nearest); default: 'self'
//  - field:     (string)  meta/ACF field name; default: 'city_state'
//  - delimiter: (string)  character between city/state; default: ','
//  - fallback:  (string)  text to return when no city found; default: ''
if ( ! function_exists('ssseo_register_city_only_shortcode') ) {
    add_action('init', 'ssseo_register_city_only_shortcode');
    function ssseo_register_city_only_shortcode() {
        add_shortcode('city_only', 'ssseo_shortcode_city_only');
    }
}

if ( ! function_exists('ssseo_shortcode_city_only') ) {
    function ssseo_shortcode_city_only( $atts = [], $content = null, $tag = '' ) {
        $atts = shortcode_atts([
            'post_id'   => 0,
            'from'      => 'self',     // self|parent|ancestor
            'field'     => 'city_state',
            'delimiter' => ',',
            'fallback'  => '',
        ], $atts, $tag );

        // Resolve base post id
        $post_id = (int) $atts['post_id'];
        if ( $post_id <= 0 ) {
            $post_id = get_the_ID();
        }
        if ( ! $post_id ) {
            return esc_html( $atts['fallback'] );
        }

        // Determine which post to read from (self/parent/ancestor)
        $target_id = $post_id;
        if ( $atts['from'] === 'parent' || $atts['from'] === 'ancestor' ) {
            $ancestor = ($atts['from'] === 'parent')
                ? (int) get_post_field('post_parent', $post_id)
                : ssseo_city_only_find_nearest_ancestor_with_value( $post_id, $atts['field'] );
            if ( $ancestor > 0 ) {
                $target_id = $ancestor;
            }
        }

        // Cache key (per post+args)
        $ckey = 'ssseo_city_only:' . md5( implode('|', [
            $target_id, $atts['field'], $atts['delimiter']
        ]) );

        $cached = wp_cache_get( $ckey, 'ssseo' );
        if ( is_string($cached) ) {
            return $cached !== '' ? $cached : esc_html( $atts['fallback'] );
        }

        // 1) Resolve raw city_state (ACF or meta), tolerating arrays
        $raw = ssseo_city_only_read_field_value( $target_id, $atts['field'] );

        // 2) Parse "City" from raw value
        $city = ssseo_city_only_parse_city( $raw, $atts['delimiter'] );

        // Allow customization via filter
        $city = apply_filters( 'ssseo_city_only_output', $city, [
            'raw'       => $raw,
            'target_id' => $target_id,
            'atts'      => $atts
        ]);

        // Cache (cache even empty string so we don't re-compute)
        $safe = is_string($city) ? $city : '';
        wp_cache_set( $ckey, $safe, 'ssseo', 5 * MINUTE_IN_SECONDS );

        if ( $safe === '' ) {
            return esc_html( $atts['fallback'] );
        }

        return esc_html( $safe );
    }
}

/** Read a field value robustly (ACF unformatted -> formatted -> raw meta). Accept arrays. */
if ( ! function_exists('ssseo_city_only_read_field_value') ) {
    function ssseo_city_only_read_field_value( $post_id, $field_name ) {
        $normalize = function($v) {
            if ( is_array($v) ) {
                // Convert array scalars to strings; serialize nested arrays/objects
                $flat = array_filter(array_map(function($x){
                    if ( is_scalar($x) ) return (string) $x;
                    if ( is_array($x) || is_object($x) ) return wp_json_encode($x);
                    return '';
                }, $v));
                return implode(', ', $flat);
            }
            return is_scalar($v) ? (string) $v : '';
        };

        // ACF: unformatted first (more predictable)
        if ( function_exists('get_field') ) {
            $v = get_field( $field_name, $post_id, false );
            $s = $normalize( $v );
            if ( $s !== '' ) return $s;

            // Then formatted
            $v2 = get_field( $field_name, $post_id, true );
            $s2 = $normalize( $v2 );
            if ( $s2 !== '' ) return $s2;
        }

        // Raw meta
        $m = get_post_meta( $post_id, $field_name, true );
        return $normalize( $m );
    }
}

/** Parse the city from a "City, ST" (or "City ST") string. */
if ( ! function_exists('ssseo_city_only_parse_city') ) {
    function ssseo_city_only_parse_city( $value, $delimiter = ',' ) {
        $value = trim( (string) $value );
        if ( $value === '' ) return '';

        // 1) If delimiter is present, take everything before first delimiter
        if ( $delimiter !== '' && strpos( $value, $delimiter ) !== false ) {
            $parts = explode( $delimiter, $value, 2 );
            return trim( $parts[0] );
        }

        // 2) Tolerate "City ST" (no comma). If the string ends with a two-letter state, strip it.
        //    e.g. "San Diego CA" -> "San Diego"
        if ( preg_match( '/^(.*?)[\s,]+([A-Z]{2})$/', $value, $m ) ) {
            // Be careful not to cut legit two-letter city names; require space/comma before state
            $maybe_city = trim( $m[1] );
            if ( $maybe_city !== '' ) return $maybe_city;
        }

        // 3) Fallback: return the full string as "city"
        return $value;
    }
}

/** Find nearest ancestor that has a non-empty field value (ACF/meta). */
if ( ! function_exists('ssseo_city_only_find_nearest_ancestor_with_value') ) {
    function ssseo_city_only_find_nearest_ancestor_with_value( $post_id, $field_name = 'city_state' ) {
        $seen = [];
        $pid  = (int) $post_id;
        while ( $pid > 0 && ! isset($seen[$pid]) ) {
            $seen[$pid] = true;
            $parent_id = (int) get_post_field( 'post_parent', $pid );
            if ( $parent_id <= 0 ) break;

            $val = ssseo_city_only_read_field_value( $parent_id, $field_name );
            if ( trim((string)$val) !== '' ) {
                return $parent_id;
            }
            $pid = $parent_id;
        }
        return 0;
    }
}

// ----- [city_state] shortcode -----------------------------------------------
// Works with/without ACF. Accepts arrays. Parent/ancestor fallback. Normalization.
// Attributes:
//  - post_id:     (int) post id to read from; default current post
//  - from:        (string) 'self' | 'parent' | 'ancestor'; default 'self'
//  - field:       (string) field/meta name; default 'city_state'
//  - delimiter:   (string) expected delimiter between city and state; default ','
//  - normalize:   (bool/int) when truthy, normalize spacing to "City, ST"; default 0
//  - state_upper: (bool/int) when truthy, uppercase the state part; default 0
//  - fallback:    (string) value if nothing found; default ''
if ( ! function_exists('ssseo_register_city_state_shortcode') ) {
  add_action('init', 'ssseo_register_city_state_shortcode');
  function ssseo_register_city_state_shortcode() {
    add_shortcode('city_state', 'ssseo_shortcode_city_state');
  }
}

if ( ! function_exists('ssseo_shortcode_city_state') ) {
  function ssseo_shortcode_city_state( $atts = [], $content = null, $tag = '' ) {
    $atts = shortcode_atts([
      'post_id'     => 0,
      'from'        => 'self',      // self|parent|ancestor
      'field'       => 'city_state',
      'delimiter'   => ',',
      'normalize'   => 0,
      'state_upper' => 0,
      'fallback'    => '',
    ], $atts, $tag );

    // Resolve base post
    $post_id = (int) $atts['post_id'];
    if ( $post_id <= 0 ) {
      $post_id = get_the_ID();
    }
    if ( ! $post_id ) {
      return esc_html( $atts['fallback'] );
    }

    // Determine target post (self/parent/ancestor)
    $target_id = $post_id;
    if ( $atts['from'] === 'parent' || $atts['from'] === 'ancestor' ) {
      $ancestor = ($atts['from'] === 'parent')
        ? (int) get_post_field('post_parent', $post_id)
        : (int) ssseo_city_value_find_nearest_ancestor_with_value( $post_id, $atts['field'] );
      if ( $ancestor > 0 ) {
        $target_id = $ancestor;
      }
    }

    // Cache key (per post + params)
    $ckey = 'ssseo_city_state:' . md5( implode('|', [
      $target_id, $atts['field'], $atts['delimiter'],
      (int) !empty($atts['normalize']), (int) !empty($atts['state_upper'])
    ]) );
    $cached = wp_cache_get( $ckey, 'ssseo' );
    if ( is_string($cached) ) {
      return $cached !== '' ? $cached : esc_html( $atts['fallback'] );
    }

    // Read value robustly
    $raw = ssseo_city_value_read_field( $target_id, $atts['field'] );
    $val = ssseo_city_state_normalize(
      $raw,
      $atts['delimiter'],
      !empty($atts['normalize']),
      !empty($atts['state_upper'])
    );

    // Filter for customization
    $val = apply_filters('ssseo_city_state_output', $val, [
      'raw'       => $raw,
      'target_id' => $target_id,
      'atts'      => $atts,
    ]);

    $safe = is_string($val) ? trim($val) : '';
    wp_cache_set( $ckey, $safe, 'ssseo', 5 * MINUTE_IN_SECONDS );

    if ( $safe === '' ) {
      return esc_html( $atts['fallback'] );
    }
    return esc_html( $safe );
  }
}

/** Helper: robust field read (ACF unformatted → formatted → raw meta); tolerates arrays. */
if ( ! function_exists('ssseo_city_value_read_field') ) {
  function ssseo_city_value_read_field( $post_id, $field_name ) {
    $normalize_any = function($v){
      if ( is_array($v) ) {
        $flat = array_filter(array_map(function($x){
          if ( is_scalar($x) ) return (string)$x;
          if ( is_array($x) || is_object($x) ) return wp_json_encode($x);
          return '';
        }, $v));
        return implode(', ', $flat);
      }
      return is_scalar($v) ? (string)$v : '';
    };

    if ( function_exists('get_field') ) {
      $v = get_field( $field_name, $post_id, false ); // unformatted
      $s = $normalize_any($v);
      if ( $s !== '' ) return $s;

      $v2 = get_field( $field_name, $post_id, true ); // formatted
      $s2 = $normalize_any($v2);
      if ( $s2 !== '' ) return $s2;
    }

    $m = get_post_meta( $post_id, $field_name, true );
    return $normalize_any($m);
  }
}

/** Helper: find nearest ancestor with non-empty field value. */
if ( ! function_exists('ssseo_city_value_find_nearest_ancestor_with_value') ) {
  function ssseo_city_value_find_nearest_ancestor_with_value( $post_id, $field_name = 'city_state' ) {
    $seen = [];
    $pid  = (int) $post_id;
    while ( $pid > 0 && ! isset($seen[$pid]) ) {
      $seen[$pid] = true;
      $parent_id = (int) get_post_field('post_parent', $pid);
      if ( $parent_id <= 0 ) break;
      $val = ssseo_city_value_read_field( $parent_id, $field_name );
      if ( trim((string)$val) !== '' ) return $parent_id;
      $pid = $parent_id;
    }
    return 0;
  }
}

/** Helper: normalize "City, ST" formatting & optionally uppercase state. */
if ( ! function_exists('ssseo_city_state_normalize') ) {
  function ssseo_city_state_normalize( $value, $delimiter = ',', $do_normalize = false, $state_upper = false ) {
    $value = trim( (string) $value );
    if ( $value === '' ) return '';

    // If normalization requested, ensure single space after delimiter.
    if ( $do_normalize && $delimiter !== '' ) {
      // Replace any spaces around delimiter with a single ", "
      $quoted = preg_quote($delimiter, '/');
      $value  = preg_replace('/\s*' . $quoted . '\s*/', $delimiter . ' ', $value);
    }

    // Optionally uppercase 2-letter state at end (handles both "City, ST" and "City ST")
    if ( $state_upper ) {
      if ( preg_match('/^(.*?)(?:\s*' . preg_quote($delimiter, '/') . '\s*|\s+)([A-Za-z]{2})$/', $value, $m) ) {
        $city  = rtrim($m[1]);
        $state = strtoupper($m[2]);
        $sep   = ($delimiter !== '') ? $delimiter . ' ' : ' ';
        $value = $city . $sep . $state;
      }
    }

    return $value;
  }
}

/**
 * Shortcode: [service_area_roots_children]
 * Goal: On ANY page (including Home), list ALL top-level "service_area" posts (post_parent = 0)
 *       and, under each, list their direct children (first level deep).
 *
 * Usage:
 *   [service_area_roots_children]                                  // show all roots + their children
 *   [service_area_roots_children hide_empty="yes"]                 // skip roots that have no children
 *   [service_area_roots_children wrapper_class="my-custom-class"]  // add classes to outer container
 *
 * Output:
 *   <div class="container service-areas ...">
 *     <div class="row"><div class="col-lg-12">
 *       <ul class="service-area-roots list-unstyled">
 *         <li class="service-area-root">
 *           <a href="...">Root Title</a>
 *           <ul class="service-area-children list-unstyled">
 *             <li class="service-area-child"><a href="...">Child Title</a></li>
 *             ...
 *           </ul>
 *         </li>
 *         ...
 *       </ul>
 *     </div></div>
 *   </div>
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'ssseo_service_area_roots_children_shortcode' ) ) {
	function ssseo_service_area_roots_children_shortcode( $atts = [] ) {
		$atts = shortcode_atts( [
			'hide_empty'   => 'no',  // 'yes' to skip roots with no children
			'wrapper_class'=> '',    // extra classes for outer wrapper
		], $atts, 'service_area_roots_children' );

		$hide_empty    = ( 'yes' === strtolower( (string) $atts['hide_empty'] ) );
		$wrapper_class = trim( preg_replace( '/[^A-Za-z0-9 _-]/', '', (string) $atts['wrapper_class'] ) );

		// 1) Fetch all ROOTS (top-level service_areas)
		$roots = get_posts( [
			'post_type'        => 'service_area',
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'post_parent'      => 0,           // only top-level
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => true,
			'no_found_rows'    => true,
		] );

		if ( empty( $roots ) ) {
			return '<p class="service-area-list" style="margin:0;">' . esc_html__( 'No top-level service areas found.', 'ssseo' ) . '</p>';
		}

		// 2) Fetch ALL first-level children for those roots in a single query, then group by parent
		$root_ids = wp_list_pluck( $roots, 'ID' );

		$children = get_posts( [
			'post_type'        => 'service_area',
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'post_parent__in'  => array_map( 'intval', $root_ids ),
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => true,
			'no_found_rows'    => true,
		] );

		$children_by_parent = [];
		foreach ( $children as $child ) {
			$pid = (int) $child->post_parent;
			if ( ! isset( $children_by_parent[ $pid ] ) ) {
				$children_by_parent[ $pid ] = [];
			}
			$children_by_parent[ $pid ][] = $child;
		}

		// 3) Build markup
		$wrapper_classes = trim( 'container service-areas ' . $wrapper_class );
		$out  = '<div class="' . esc_attr( $wrapper_classes ) . '"><div class="row">';
		$out .= '<div class="col-lg-12">';
		$out .= '<ul class="service-area-list list-unstyled">';

		foreach ( $roots as $root ) {
			$root_id    = (int) $root->ID;
			$root_title = get_the_title( $root_id );
			$root_link  = get_permalink( $root_id );

			// If hide_empty=yes and this root has no children, skip it.
			$root_children = isset( $children_by_parent[ $root_id ] ) ? $children_by_parent[ $root_id ] : [];
			if ( $hide_empty && empty( $root_children ) ) {
				continue;
			}

			$out .= '<li class="service-area-child">';
			$out .= '  <i class="fa fa-map-marker ssseo-icon"></i>';
			$out .= '  <a class="service-area-root-link" href="' . esc_url( $root_link ) . '">' . esc_html( $root_title ) . '</a>';
			$out .= '</li>';

			// Children list (if any)
			if ( ! empty( $root_children ) ) {
				foreach ( $root_children as $child ) {
					$child_id    = (int) $child->ID;
					$child_title = get_the_title( $child_id );
					$child_link  = get_permalink( $child_id );

					$out .= '    <li class="service-area-child">';
					$out .= '                    <i class="fa fa-map-marker ssseo-icon"></i>';
					$out .= '      <a class="service-area-link" href="' . esc_url( $child_link ) . '">' . esc_html( $child_title ) . '</a>';
					$out .= '    </li>';
				}
			}
			
		}

		$out .= '</ul>';
		$out .= '</div></div></div>';

		return $out;
	}
}

// Register the shortcode
add_shortcode( 'service_area_roots_children', 'ssseo_service_area_roots_children_shortcode' );


/**
 * Shortcode: [service_area_children]
 * Purpose: List direct child Service Areas of the CURRENT Service Area post.
 *          Works on a single service_area page. You can also override the parent via parent_id.
 *
 * Usage:
 *   [service_area_children]                          // on a single service_area → lists its children
 *   [service_area_children parent_id="123"]          // anywhere → lists children of post ID 123
 *   [service_area_children show_parent="yes"]        // include a heading/link to the parent at top
 *   [service_area_children wrapper_class="py-3"]     // add classes to outer wrapper
 *
 * Notes:
 * - Assumes CPT 'service_area' is hierarchical.
 * - Orders children alphabetically by title.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'ssseo_service_area_children_shortcode' ) ) {
	function ssseo_service_area_children_shortcode( $atts = [] ) {
		$atts = shortcode_atts( [
			'parent_id'     => 0,              // optional: force a specific parent ID
			'orderby'       => 'title',        // title | menu_order | date, etc.
			'order'         => 'ASC',          // ASC | DESC
			'show_parent'   => 'no',           // yes | no — show parent heading/link
			'wrapper_class' => '',             // extra classes for outer wrapper
			'list_class'    => 'list-unstyled service-area-list',
			'empty_text'    => 'No service areas found.',
		], $atts, 'service_area_children' );

		$parent_id     = (int) $atts['parent_id'];
		$orderby       = sanitize_key( $atts['orderby'] );
		$order         = ( strtoupper( $atts['order'] ) === 'DESC' ) ? 'DESC' : 'ASC';
		$show_parent   = ( strtolower( $atts['show_parent'] ) === 'yes' );
		$wrapper_class = trim( preg_replace( '/[^A-Za-z0-9 _-]/', '', (string) $atts['wrapper_class'] ) );
		$list_class    = trim( preg_replace( '/[^A-Za-z0-9 _-]/', '', (string) $atts['list_class'] ) );
		$empty_text    = wp_kses_post( $atts['empty_text'] );

		// Infer parent from context if not given
		if ( $parent_id <= 0 && is_singular( 'service_area' ) ) {
			$parent_id = (int) get_the_ID();
		}

		if ( $parent_id <= 0 ) {
			return '<!-- [service_area_children]: No parent context available. Provide parent_id or place on a single service_area. -->';
		}

		// Fetch direct children of $parent_id
		$children = get_posts( [
			'post_type'        => 'service_area',
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'post_parent'      => $parent_id,
			'orderby'          => $orderby,
			'order'            => $order,
			'no_found_rows'    => true,
			'suppress_filters' => true,
		] );

		if ( empty( $children ) ) {
			return '<p class="service-area-list-empty" style="margin:0;">' . $empty_text . '</p>';
		}

		$wrapper_classes = trim( 'container service-areas ' . $wrapper_class );
		$out  = '<div class="' . esc_attr( $wrapper_classes ) . '"><div class="row">';
		$out .= '<div class="col-lg-12">';

		// Optional parent heading/link
		if ( $show_parent ) {
			$parent_title = get_the_title( $parent_id );
			$parent_link  = get_permalink( $parent_id );
			$out .= '<div class="service-area-parent mb-2">';
			$out .= '  <a class="service-area-parent-link fw-semibold" href="' . esc_url( $parent_link ) . '">' . esc_html( $parent_title ) . '</a>';
			$out .= '</div>';
		}

		$out .= '<ul class="' . esc_attr( $list_class ) . '">';

		foreach ( $children as $child ) {
			$child_id    = (int) $child->ID;
			$child_title = get_the_title( $child_id );
			$child_link  = get_permalink( $child_id );

			$out .= '<li class="service-area-child">';
			$out .= '  <i class="fa fa-map-marker ssseo-icon"></i>';
			$out .= '  <a class="service-area-link" href="' . esc_url( $child_link ) . '">' . esc_html( $child_title ) . '</a>';
			$out .= '</li>';
		}

		$out .= '</ul>';
		$out .= '</div></div></div>';

		return $out;
	}
}

add_shortcode( 'service_area_children', 'ssseo_service_area_children_shortcode' );
