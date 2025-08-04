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
add_shortcode('service_area_list', 'service_area_shortcode');

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