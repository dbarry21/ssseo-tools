<?php
/**
 * Builder: FAQPage Schema
 *
 * Populates $schema_markup for any singular page/post/service_area with ACF faq_items.
 * To be included by the main schema-output callback.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $post;

// Only run on frontend and for supported post types
if ( is_admin() || wp_doing_ajax() || ! is_singular() ) {
    return;
}

$valid_types = [ 'page', 'post', 'service_area' ];
if ( ! in_array( get_post_type( $post ), $valid_types, true ) ) {
    return;
}

// Determine if schema should be rendered
$post_id          = $post->ID;
$selected_schemas = get_field( 'page_schemas', $post_id );
$should_output    = is_array( $selected_schemas ) && in_array( 'faq', $selected_schemas, true );

if ( ! $should_output && ! have_rows( 'faq_items', $post_id ) ) {
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

// Final schema array
$schema_markup = [
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => $mainEntity,
];

// $schema_markup is ready for JSON-LD output by the caller.
