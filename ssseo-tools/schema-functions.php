<?php
/**
 * Front‐end Schema Output
 *
 * Outputs JSON-LD for Organization, Local Business, or BlogPosts schemas
 * only on singular pages that have been “assigned” via the plugin’s admin settings.
 */

defined( 'ABSPATH' ) || exit;

/**
 * ORGANIZATION SCHEMA
 * Only output on singular pages whose ID is in the “organization” pages option.
 */
add_action( 'wp_head', 'ssseo_maybe_output_org_schema' );
function ssseo_maybe_output_org_schema() {
    if ( is_admin() || wp_doing_ajax() || ! is_singular() ) {
        return;
    }

    $post_id = get_queried_object_id();
    // Must match the option name used in the admin page
    $assigned_pages = get_option( 'ssseo_organization_schema_pages', [] );

    if ( ! is_array( $assigned_pages ) || ! in_array( $post_id, $assigned_pages, true ) ) {
        return;
    }

    $schema_markup = [];

    $builder = plugin_dir_path( __FILE__ ) . 'modules/schema/build-organization-schema.php';
    if ( file_exists( $builder ) ) {
        include_once $builder; 
        // build-organization-schema.php must populate $schema_markup
    }

    if ( ! empty( $schema_markup ) ) {
        echo "\n<!-- SSSEO Org Schema -->\n";
        echo '<script type="application/ld+json">'
             . wp_json_encode( $schema_markup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
             . '</script>' . "\n";
    }
}


add_action( 'wp_head', 'ssseo_maybe_output_localbusiness_schema' );
function ssseo_maybe_output_localbusiness_schema() {
    if ( is_admin() || wp_doing_ajax() || ! is_singular() ) {
        return;
    }

    $post_id = get_queried_object_id();

    // 1) Load all “Locations” from the option
    $locations = get_option( 'ssseo_localbusiness_locations', [] );
    if ( ! is_array( $locations ) || empty( $locations ) ) {
        return;
    }

    // 2) Find which location(s) include this page ID in their 'pages' array
    $matched_locations = [];
    foreach ( $locations as $loc ) {
        // Make sure 'pages' key exists and is an array
        $assigned = isset( $loc['pages'] ) && is_array( $loc['pages'] ) 
                    ? $loc['pages'] 
                    : [];

        if ( in_array( $post_id, $assigned, true ) ) {
            $matched_locations[] = $loc;
        }
    }

    // If no match, bail out
    if ( empty( $matched_locations ) ) {
        return;
    }

    // 3) For each matched location, include the builder (not include_once!)
    foreach ( $matched_locations as $location_data ) {
        // Reset schema array each loop
        $schema_markup = [];

        // Make the current location data available to the builder
        $ssseo_current_localbusiness_location = $location_data;

        $builder_file = plugin_dir_path( __FILE__ ) . 'modules/schema/build-localbusiness-schema.php';
        if ( file_exists( $builder_file ) ) {
            include $builder_file;
            /**
             * NOTES:
             *  - Use include (NOT include_once), so that if multiple locations match,
             *    the builder runs fresh each time.
             *  - Inside build-localbusiness-schema.php, read from 
             *    $ssseo_current_localbusiness_location and fill $schema_markup.
             */
        } else {
            // If the builder file is missing, you’ll see nothing. Feel free to log here.
            continue;
        }

        // Only output if builder actually populated $schema_markup
        if ( isset( $schema_markup ) && is_array( $schema_markup ) && ! empty( $schema_markup ) ) {
            echo "\n<!-- SSSEO LocalBusiness Schema for “"
                 . esc_html( $ssseo_current_localbusiness_location['name'] ?? 'Unknown' )
                 . "” -->\n";

            echo '<script type="application/ld+json">'
                 . wp_json_encode( $schema_markup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
                 . '</script>' . "\n";
        }
    }
}

/**
 * BLOGPOSTS SCHEMA
 * Only output on singular “post” pages if the Article Schema option is enabled.
 */
add_action( 'wp_head', 'ssseo_maybe_output_blogposts_schema' );
function ssseo_maybe_output_blogposts_schema() {
    // Don’t run in admin or AJAX, and only for singular “post” types
    if ( is_admin() || wp_doing_ajax() || ! is_singular( 'post' ) ) {
        return;
    }

    // Check whether Article Schema is globally enabled
    $enabled = get_option( 'ssseo_enable_article_schema', '0' );
    if ( '1' !== $enabled ) {
        return;
    }

    // Build (or include) the Article schema
    $schema_markup = [];

    $builder_file = plugin_dir_path( __FILE__ ) . 'modules/schema/build-article-schema.php';
    if ( file_exists( $builder_file ) ) {
        include $builder_file;
        // build-article-schema.php must populate $schema_markup
    }

    if ( ! empty( $schema_markup ) && is_array( $schema_markup ) ) {
        echo "\n<!-- SSSEO Article Schema -->\n";
        echo '<script type="application/ld+json">'
             . wp_json_encode( $schema_markup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
             . '</script>' . "\n";
    }
}

/**
 * WEBPAGE SCHEMA
 * Only output on singular “page” (not post or video).
 */
add_action( 'wp_head', 'ssseo_maybe_output_webpage_schema' );
function ssseo_maybe_output_webpage_schema() {
    if ( is_admin() || wp_doing_ajax() || ! is_singular( 'page' ) ) {
        return;
    }

    // Prepare an empty array for the builder to populate
    $schema_markup = [];

    // Include the builder (it should set $schema_markup)
    $builder = plugin_dir_path( __FILE__ ) . 'modules/schema/build-webpage-schema.php';
    if ( file_exists( $builder ) ) {
        include $builder;
        // build-webpage-schema.php must populate $schema_markup
    }

    // If the builder produced a schema, output it as JSON-LD
    if ( ! empty( $schema_markup ) && is_array( $schema_markup ) ) {
        echo "\n<!-- SSSEO WebPage Schema -->\n";
        echo '<script type="application/ld+json">'
             . wp_json_encode( $schema_markup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
             . '</script>' . "\n";
    }
}

/**
 * OUTPUT PAGE/POST TYPE AS META TAG (EVERY PAGE)
 *
 * Emits: <meta name="ssseo-post-type" content="{post_type}" />
 * Works on singular, archives, and the blog home.
 */
add_action( 'wp_head', 'ssseo_output_post_type_meta_any' );
function ssseo_output_post_type_meta_any() {
    $post_type = get_post_type();

    // 1) If not singular but on a CPT archive, grab the queried post_type
    if ( ! $post_type && is_post_type_archive() ) {
        $queried = get_query_var( 'post_type' );
        if ( is_array( $queried ) ) {
            $post_type = reset( $queried );
        } else {
            $post_type = $queried;
        }
    }

    // 2) If on the blog home (posts index), force 'post'
    if ( ! $post_type && is_home() ) {
        $post_type = 'post';
    }

    // 3) If still empty (e.g. homepage set to a static page), check if is_front_page()
    if ( ! $post_type && is_front_page() ) {
        // If a static page is assigned, grab its post_type
        $page_on_front = get_option( 'page_on_front' );
        if ( $page_on_front ) {
            $post_type = get_post_type( $page_on_front );
        }
    }

    // Only output if we have something
    if ( $post_type ) {
        echo '<meta name="ssseo-post-type" content="' . esc_attr( $post_type ) . "\" />\n";
    }
}



