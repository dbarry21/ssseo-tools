<?php
// Get and list child Service Areas
function service_area_children_list() {
    global $post; // Declare global $post variable

    // Check if we're inside a post or page
    if (isset($post)) {

        // Use WP_Query to fetch child pages/posts of the current post/page
        $child_pages_query = new WP_Query(array(
            'post_type'      => 'service_area', // Your custom post type
            'posts_per_page' => -1,             // Fetch all posts
            'post_parent'    => $post->ID,      // Current post ID as parent
            'order'          => 'ASC',          // Order by ascending
            'orderby'        => 'menu_order'    // Order by menu order
        ));

        // Initialize empty output string
        $output = '';

        // Check if there are child pages/posts
        if ($child_pages_query->have_posts()) {
            $output .= '<ul>';
            while ($child_pages_query->have_posts()) {
                $child_pages_query->the_post(); // Set up post data
                $output .= '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
            }
            $output .= '</ul>';

            // Reset post data to the main query's current post
            wp_reset_postdata();
        }

        return $output;
    }

    return ''; // Return empty string if not inside a post or page
}

function TopLevelPosts($args, $widgetData) {
    // Check if we're dealing with a post type that supports hierarchical relationships
    if (isset($args['post_type']) && post_type_supports($args['post_type'], 'page-attributes')) {
        // Set post_parent to 0 to get top-level items only
        $args['post_parent'] = 0;
    }

    return $args;
}

add_filter("parent_only", "TopLevelPosts", 10, 2);

function getServiceAreaChildPages($args, $widgetData) {
    global $post; // Access the global post object
	$parentid = get_the_id();
    // Ensure we have a valid post object and are within a singular 'service_area' post
    if (is_a($post, 'WP_Post') && is_singular('service_area')) {
        // Check if the post type is 'service_area'
        if (isset($args['post_type']) && $args['post_type'] === 'service_area') {
            // Set post_parent to the ID of the current post to get its child items
            $args['post_parent'] = $post->ID;
            $args['orderby'] = 'menu_order'; // Ensure the posts are ordered by their menu order
			$args['posts_per_page'] = -1;
        } else {
            // If the post type is not 'service_area', return empty results
            $args['post__in'] = array(0); // Setting post__in to an array with ID 0 returns no posts
        }
    } else {
        // If conditions are not met, return empty results
        $args['post__in'] = array(0); // Setting post__in to an array with ID 0 returns no posts
    }

    return $args;
}

add_filter("child_posts", "getServiceAreaChildPages", 10, 2);


function getServiceChildOrSiblingPages($args, $widgetData) {
    global $post;
    
    // Ensure we have a valid post object and that we're on a singular 'service' post
    if ( is_a( $post, 'WP_Post' ) && is_singular( 'service' ) ) {
        // Confirm the query is for 'service' posts
        if ( isset( $args['post_type'] ) && $args['post_type'] === 'service' ) {
            // Set initial query parameters to fetch child pages
            $args['post_parent']     = $post->ID;
            $args['orderby']         = 'menu_order';
            $args['posts_per_page']  = -1;
            
            // Check if the current service post has any children
            $child_posts = get_children( array(
                'post_parent'    => $post->ID,
                'post_type'      => 'service',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'menu_order',
            ) );
            
            // If there are no child posts, query for sibling service posts
            if ( empty( $child_posts ) ) {
                // Determine the parent ID (if the current post has a parent)
                $parent_id = $post->post_parent;
                
                // If the current post is not a top-level post, get its siblings
                if ( $parent_id ) {
                    $args['post_parent'] = $parent_id;
                } else {
                    // For top-level posts, fetch other top-level service posts
                    $args['post_parent'] = 0;
                }
                
                // Exclude the current post from the results
                $args['post__not_in'] = array( $post->ID );
            }
        } else {
            // If the post type isn't 'service', force no results
            $args['post__in'] = array( 0 );
        }
    } else {
        // If conditions are not met, force no results
        $args['post__in'] = array( 0 );
    }
    
    return $args;
}

// Attach our function to the 'service_child_posts' filter with a priority of 10 and two accepted arguments.
add_filter( 'service_child_posts', 'getServiceChildOrSiblingPages', 10, 2 );

function getServiceAreaChildOrSiblingPages($args, $widgetData) {
    global $post;
    
    // Ensure we have a valid post object and that we're on a singular 'service' post
    if ( is_a( $post, 'WP_Post' ) && is_singular( 'service_area' ) ) {
        // Confirm the query is for 'service_area' posts
        if ( isset( $args['post_type'] ) && $args['post_type'] === 'service_area' ) {
            // Set initial query parameters to fetch child pages
            $args['post_parent']     = $post->ID;
            $args['orderby']         = 'menu_order';
            $args['posts_per_page']  = -1;
            
            // Check if the current service post has any children
            $child_posts = get_children( array(
                'post_parent'    => $post->ID,
                'post_type'      => 'service_area',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'menu_order',
            ) );
            
            // If there are no child posts, query for sibling service posts
            if ( empty( $child_posts ) ) {
                // Determine the parent ID (if the current post has a parent)
                $parent_id = $post->post_parent;
                
                // If the current post is not a top-level post, get its siblings
                if ( $parent_id ) {
                    $args['post_parent'] = $parent_id;
                } else {
                    // For top-level posts, fetch other top-level service posts
                    $args['post_parent'] = 0;
                }
                
                // Exclude the current post from the results
                $args['post__not_in'] = array( $post->ID );
            }
        } else {
            // If the post type isn't 'service', force no results
            $args['post__in'] = array( 0 );
        }
    } else {
        // If conditions are not met, force no results
        $args['post__in'] = array( 0 );
    }
    
    return $args;
}

// Attach our function to the 'service_child_posts' filter with a priority of 10 and two accepted arguments.
add_filter( 'service_area_child_posts', 'getServiceAreaChildOrSiblingPages', 10, 2 );

function active_years_filter($args) {
    // Check if on an archive page and the post type is 'post'
    if (is_archive() && (isset($args['post_type']) && $args['post_type'] === 'post')) {
        // Get the distinct years where posts were published
        global $wpdb;
        $years = $wpdb->get_col("SELECT DISTINCT YEAR(post_date) FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'post' ORDER BY post_date DESC");

        // If there are years with posts, modify the query to filter by these years
        if (!empty($years)) {
            $args['date_query'] = array(
                'relation' => 'OR' // Use OR relation to include posts from all the active years
            );
            
            // Add each year to the date query
            foreach ($years as $year) {
                $args['date_query'][] = array(
                    'year' => $year
                );
            }
        }
    }

    return $args;
}

// Hook the function to 'pre_get_posts' to modify the main query on archive pages
add_filter('post_years', 'active_years_filter');


//Shortcodes for content areas by Dave Barry
function post_title_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'id' => get_the_ID(),
    ), $atts, 'post_title' );

    $post_title = get_the_title( $atts['id'] );
    return $post_title;
}
add_shortcode( 'post_title', 'post_title_shortcode' );

function post_author_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'id' => get_the_ID(),
    ), $atts, 'post_title' );

    $post_author = get_the_author( $atts['id'] );
    return $post_author;
}
add_shortcode( 'post_author', 'post_author_shortcode' );

function generate_yearly_archive_links() {
    // Use WP_Query to fetch distinct years from published posts
    $years_query = new WP_Query(array(
        'post_type'      => 'post', // Change to your specific post type if needed
        'posts_per_page' => -1,     // Fetch all posts
        'orderby'        => 'date', // Order by post date
        'order'          => 'DESC', // Start with the most recent
        'date_query'     => array(
            array(
                'column' => 'post_date',
                'after'  => 'January 1st, 1970', // Adjust as needed
            ),
        ),
        'fields'         => 'year', // Fetch only the year (for performance)
    ));

    // Initialize empty output string
    $output = '';

    // Track years to avoid duplicates
    $years = array();

    // Check if there are posts
    if ($years_query->have_posts()) {
        $output .= '<ul>';
        while ($years_query->have_posts()) {
            $years_query->the_post();
            $year = get_the_date('Y'); // Get the year of the post

            // Check if this year has already been listed
            if (!in_array($year, $years)) {
                $years[] = $year; // Add year to the array to avoid duplicates
                $archive_link = get_year_link($year); // Get the archive link for the year
                $output .= '<li><a href="' . esc_url($archive_link) . '">' . esc_html($year) . '</a></li>';
            }
        }
        $output .= '</ul>';

        // Reset post data to the main query's current post
        wp_reset_postdata();
    }

    return $output; // Return the list of years with links
}
add_shortcode( 'yearly_archives', 'generate_yearly_archive_links' );

// Disable Yoast Schema output so Schema Pro can work - by Dave Barry
function disable_yoast_json_ld_output() {
    return array(); // Return an empty array to disable Yoast's JSON-LD output.
}
// Add the filter to disable Yoast SEO JSON-LD output.
add_filter('wpseo_json_ld_output', 'disable_yoast_json_ld_output', 10, 1);

// Get child posts of current post page
add_action('elementor/query/children_filter', function($query) {
    $current_pageID = get_queried_object_id(); // Get the current page ID
    
    // Modify the query
    $query->set('post_parent', $current_pageID);
    $query->set('orderby', 'menu_order'); // Sort by menu order
    $query->set('order', 'ASC'); // Ascending order (change to 'DESC' if needed)
});

// Get same level posts that share a parent
add_action('elementor/query/same_parent_filter', function($query) {
    global $post; // Access the global post object

    if (is_a($post, 'WP_Post')) {
        $parent_id = $post->post_parent; // Get the parent ID of the current post

        if ($parent_id) {
            // Modify the query to get posts with the same parent ID
            $query->set('post_parent', $parent_id);
            $query->set('orderby', 'menu_order'); // Sort by menu order
            $query->set('order', 'ASC'); // Ascending order (change to 'DESC' if needed)
        } else {
            // If no parent ID, return empty results
            $query->set('post__in', array(0)); // Setting post__in to an array with ID 0 returns no posts
        }
    } else {
        // If no valid post object, return empty results
        $query->set('post__in', array(0)); // Setting post__in to an array with ID 0 returns no posts
    }
});

// Get child posts of the current page, 1 level deep only
add_action('elementor/query/children_filter_one_level', function($query) {
    $current_pageID = get_queried_object_id(); // Get the current page ID
    
    // Modify the query to only get direct children (1 level deep)
    $query->set('post_parent', $current_pageID); // Only get direct children
    $query->set('post_type', 'page'); // Ensure only pages are retrieved
    $query->set('orderby', 'menu_order'); // Sort by menu order
    $query->set('order', 'ASC'); // Ascending order (change to 'DESC' if needed)
    $query->set('posts_per_page', 4); // Retrieve all child pages

    // Ensure no deeper levels are included by setting hierarchical to false
    $query->set('hierarchical', false);
});

/**
 * Override the Unlimited Elements Post Grid "Read More" button link.
 *
 * This function targets the anchor tag with the class "uc_more_btn" and replaces its href attribute 
 * with the string '/contact-us/'.
 *
 * @param string $html    The original HTML output for the read more button.
 * @param int    $post_id The ID of the current post.
 * @return string         The modified HTML with the new URL.
 */
function custom_ue_read_more_link_override( $html, $post_id ) {
    $new_url = '/contact-us/';

    // Regex pattern explanation:
    // - (<a[^>]+class="[^"]*uc_more_btn[^"]*"[^>]+href=") matches the start of the anchor tag that has 'uc_more_btn' in its class and captures everything up to the href attribute's opening quote.
    // - [^"]* matches the original URL (any characters except a double quote).
    // - (") captures the closing double quote.
    $pattern = '/(<a[^>]+class="[^"]*uc_more_btn[^"]*"[^>]+href=")[^"]*(")/';
    $replacement = '$1' . esc_url( $new_url ) . '$2';

    $html = preg_replace( $pattern, $replacement, $html );
    return $html;
}
add_filter( 'ue_post_grid_read_more', 'custom_ue_read_more_link_override', 10, 2 );

/**
 * Conditionally override the "Get a Quote Now!" button link in the Unlimited Elements post grid.
 *
 * This snippet enqueues an inline JavaScript snippet in the footer. The script selects all
 * anchor elements with the class "uc_more_btn" and checks if their inner text contains the
 * keywords "quote" or "estimate". If so, it updates their href attribute to '/contact-us/'.
 */
function override_uc_more_btn_link_conditionally() {
    ?>
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            // Select all buttons with the class 'uc_more_btn'
            var buttons = document.querySelectorAll('.uc_more_btn');
            buttons.forEach(function(button) {
                // Get the button text content and convert it to lower case for a case-insensitive comparison
                var buttonText = button.textContent.toLowerCase();
                // Check if the text contains 'quote' or 'estimate'
                if (buttonText.includes('quote') || buttonText.includes('estimate')) {
                    button.setAttribute('href', '/contact-us/');
                }
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'override_uc_more_btn_link_conditionally');