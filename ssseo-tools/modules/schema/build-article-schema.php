<?php
/**
 * Builder: Article Schema
 *
 * Expects:
 *  - $schema_markup to be declared by the caller (and overwritten here)
 *  - We already know we’re on a singular “post” with Article Schema enabled
 *
 * Populates $schema_markup with an Article JSON-LD array.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $post;

// Build the Article schema array
$schema_markup = [
    '@context'      => 'https://schema.org',
    '@type'         => 'Article',
    'headline'      => get_the_title( $post ),
    'datePublished' => get_the_date( DATE_W3C, $post ),
    'dateModified'  => get_the_modified_date( DATE_W3C, $post ),
    'author'        => [
        '@type' => 'Person',
        'name'  => get_the_author_meta( 'display_name', $post->post_author ),
    ],
    'publisher'     => [
        '@type' => 'Organization',
        'name'  => get_option( 'ssseo_organization_name', '' ),
        'logo'  => [
            '@type' => 'ImageObject',
            'url'   => wp_get_attachment_image_url( get_option( 'ssseo_organization_logo' ), 'full' ),
        ],
    ],
    'description'   => get_the_excerpt( $post ),
];

// Add featured image if it exists
if ( has_post_thumbnail( $post ) ) {
    $schema_markup['image'] = get_the_post_thumbnail_url( $post, 'full' );
}

// At this point, $schema_markup is ready. The caller will JSON‐encode and echo it.
