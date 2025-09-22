<?php
/**
 * Builder: WebPage Schema
 *
 * Populates $schema_markup for any singular “page” (excluding posts and videos).
 * To be included by the main schema‐output callback.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $post;

// Only run on front end, and only for singular “page” post type
if ( is_admin() || wp_doing_ajax() || ! is_singular( 'page' ) ) {
    return;
}

// Build the WebPage schema array
$schema_markup = [
    '@context'      => 'https://schema.org',
    '@type'         => 'WebPage',
    'name'          => get_the_title( $post ),
    'url'           => get_permalink( $post ),
    'description'   => get_the_excerpt( $post ) 
                       ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' ),
    'datePublished' => get_the_date( DATE_W3C, $post ),
    'dateModified'  => get_the_modified_date( DATE_W3C, $post ),
];

// Include the featured image if one exists
if ( has_post_thumbnail( $post ) ) {
    $schema_markup['primaryImageOfPage'] = get_the_post_thumbnail_url( $post, 'full' );
}

// At this point, $schema_markup is ready for JSON‐LD output by the caller.
