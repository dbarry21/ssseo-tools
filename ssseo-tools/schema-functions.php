<?php
/**
 * Front‐end Schema Output
 *
 * Outputs JSON-LD for Organization, Local Business, BlogPosts, WebPage, and FAQ schemas
 */

defined( 'ABSPATH' ) || exit;

/**
 * ORGANIZATION SCHEMA
 */
add_action( 'wp_head', 'ssseo_maybe_output_org_schema' );
function ssseo_maybe_output_org_schema() {
    if ( is_admin() || wp_doing_ajax() || ! is_singular() ) {
        return;
    }

    $post_id = get_queried_object_id();
    $assigned_pages = get_option( 'ssseo_organization_schema_pages', [] );

    if ( ! is_array( $assigned_pages ) || ! in_array( $post_id, $assigned_pages, true ) ) {
        return;
    }

    $schema_markup = [];
    $builder = plugin_dir_path( __FILE__ ) . 'modules/schema/build-organization-schema.php';

    if ( file_exists( $builder ) ) {
        include_once $builder;
    }

    if ( ! empty( $schema_markup ) ) {
        echo "\n<!-- SSSEO Org Schema -->\n";
        echo '<script type="application/ld+json">'
            . wp_json_encode( $schema_markup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
            . '</script>' . "\n";
    }
}

/**
 * LOCAL BUSINESS SCHEMA
 */
add_action( 'wp_head', 'ssseo_maybe_output_localbusiness_schema' );
function ssseo_maybe_output_localbusiness_schema() {
    if ( is_admin() || wp_doing_ajax() || ! is_singular() ) {
        return;
    }

    $post_id = get_queried_object_id();
    $locations = get_option( 'ssseo_localbusiness_locations', [] );

    if ( ! is_array( $locations ) || empty( $locations ) ) {
        return;
    }

    foreach ( $locations as $loc ) {
        $assigned = isset( $loc['pages'] ) && is_array( $loc['pages'] ) ? $loc['pages'] : [];

        if ( in_array( $post_id, $assigned, true ) ) {
            $schema_markup = [];
            $ssseo_current_localbusiness_location = $loc;

            $builder_file = plugin_dir_path( __FILE__ ) . 'modules/schema/build-localbusiness-schema.php';
            if ( file_exists( $builder_file ) ) {
                include $builder_file;
            }

            if ( ! empty( $schema_markup ) ) {
                echo "\n<!-- SSSEO LocalBusiness Schema for “"
                    . esc_html( $ssseo_current_localbusiness_location['name'] ?? 'Unknown' )
                    . "” -->\n";

                echo '<script type="application/ld+json">'
                    . wp_json_encode( $schema_markup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
                    . '</script>' . "\n";
            }
        }
    }
}

/**
 * BLOGPOSTS SCHEMA
 */
add_action( 'wp_head', 'ssseo_maybe_output_blogposts_schema' );
function ssseo_maybe_output_blogposts_schema() {
    if ( is_admin() || wp_doing_ajax() || ! is_singular( 'post' ) ) {
        return;
    }

    $enabled = get_option( 'ssseo_enable_article_schema', '0' );
    if ( '1' !== $enabled ) {
        return;
    }

    $schema_markup = [];
    $builder_file = plugin_dir_path( __FILE__ ) . 'modules/schema/build-article-schema.php';

    if ( file_exists( $builder_file ) ) {
        include $builder_file;
    }

    if ( ! empty( $schema_markup ) ) {
        echo "\n<!-- SSSEO Article Schema -->\n";
        echo '<script type="application/ld+json">'
            . wp_json_encode( $schema_markup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
            . '</script>' . "\n";
    }
}

/**
 * WEBPAGE SCHEMA
 */
add_action( 'wp_head', 'ssseo_maybe_output_webpage_schema' );
function ssseo_maybe_output_webpage_schema() {
    if ( is_admin() || wp_doing_ajax() || ! is_singular( 'page' ) ) {
        return;
    }

    $schema_markup = [];
    $builder = plugin_dir_path( __FILE__ ) . 'modules/schema/build-webpage-schema.php';

    if ( file_exists( $builder ) ) {
        include $builder;
    }

    if ( ! empty( $schema_markup ) ) {
        echo "\n<!-- SSSEO WebPage Schema -->\n";
        echo '<script type="application/ld+json">'
            . wp_json_encode( $schema_markup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
            . '</script>' . "\n";
    }
}

/**
 * META: OUTPUT PAGE/POST TYPE AS <meta>
 */
add_action( 'wp_head', 'ssseo_output_post_type_meta_any' );
function ssseo_output_post_type_meta_any() {
    $post_type = get_post_type();

    if ( ! $post_type && is_post_type_archive() ) {
        $queried = get_query_var( 'post_type' );
        $post_type = is_array( $queried ) ? reset( $queried ) : $queried;
    }

    if ( ! $post_type && is_home() ) {
        $post_type = 'post';
    }

    if ( ! $post_type && is_front_page() ) {
        $page_on_front = get_option( 'page_on_front' );
        if ( $page_on_front ) {
            $post_type = get_post_type( $page_on_front );
        }
    }

    if ( $post_type ) {
        echo '<meta name="ssseo-post-type" content="' . esc_attr( $post_type ) . "\" />\n";
    }
}
/**
 * FAQ SCHEMA (auto-includes if faq_items exists or is selected in page_schemas)
 */
add_action( 'wp_head', 'ssseo_maybe_output_faq_schema' );
function ssseo_maybe_output_faq_schema() {
    if ( is_admin() || wp_doing_ajax() || ! is_singular() ) {
        return;
    }

    $post_id     = get_queried_object_id();
    $post_type   = get_post_type( $post_id );
    $valid_types = [ 'post', 'page', 'service_area' ];

    if ( ! in_array( $post_type, $valid_types, true ) ) {
        return;
    }

    $selected_schemas = get_field( 'page_schemas', $post_id );
    $should_output    = is_array( $selected_schemas ) && in_array( 'faq', $selected_schemas, true );

    // Also allow auto-output if faq_items exist
    if ( ! $should_output && have_rows( 'faq_items', $post_id ) ) {
        $should_output = true;
    }

    if ( ! $should_output ) {
        return;
    }

    $mainEntity = [];

    if ( have_rows( 'faq_items', $post_id ) ) {
        while ( have_rows( 'faq_items', $post_id ) ) {
            the_row();
            $question = trim( sanitize_text_field( get_sub_field( 'question' ) ) );
            $answer   = trim( wp_kses_post( get_sub_field( 'answer' ) ) );

            if ( $question && $answer ) {
                $mainEntity[] = [
                    '@type'          => 'Question',
                    'name'           => $question,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => $answer,
                    ],
                ];
            }
        }
    }

    if ( empty( $mainEntity ) ) {
        return;
    }

    $faq_schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $mainEntity,
    ];

    echo "\n<!-- SSSEO FAQ Schema -->\n";
    echo '<script type="application/ld+json">'
        . wp_json_encode( $faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
        . '</script>' . "\n";
}
