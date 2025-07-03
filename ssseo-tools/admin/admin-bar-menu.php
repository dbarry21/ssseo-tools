<?php
/**
 * SEO Stuff Admin Bar Menu
 *
 * Adds an "SEO Stuff" top‐level node with sub‐items for:
 *   - Google Rich Results Test  (with url + source params)
 *   - PageSpeed Insights (Mobile)
 *   - PageSpeed Insights (Desktop)
 *   - GTmetrix Performance Test (with url + page_url params)
 *
 * @package ssseo
 */

add_action( 'admin_bar_menu', 'ssseo_add_seo_stuff_admin_bar', 100 );
function ssseo_add_seo_stuff_admin_bar( $wp_admin_bar ) {
    // Only show when the admin bar is visible & user can edit posts.
    if ( ! is_admin_bar_showing() || ! current_user_can( 'edit_posts' ) ) {
        return;
    }

    // 1. Determine the URL to test
    if ( is_admin() && ! empty( $_GET['post'] ) ) {
        $post_id  = absint( $_GET['post'] );
        $test_url = get_permalink( $post_id ) ?: home_url( '/' );
    } elseif ( is_singular() ) {
        $test_url = get_permalink();
    } else {
        $test_url = home_url( '/' );
    }
    // Normalize & sanitize
    $test_url = esc_url_raw( untrailingslashit( $test_url ) );

    // 2. Google Rich Results Test — now sending BOTH "url" and "source"
    $rich_results_base = 'https://search.google.com/test/rich-results';
    $rich_results_url  = add_query_arg(
        [
            'url'    => $test_url,
        ],
        $rich_results_base
    );

    // 3. PageSpeed Insights URLs (unchanged)
    $psi_base = 'https://pagespeed.web.dev/analysis';
    $psi_urls = [
        'Mobile'  => add_query_arg( [ 'url' => $test_url, 'form_factor' => 'mobile' ],  $psi_base ),
        'Desktop' => add_query_arg( [ 'url' => $test_url, 'form_factor' => 'desktop' ], $psi_base ),
    ];

    // 4. GTmetrix Test URL — now sending BOTH "url" and "page_url"
    $gtmetrix_base = 'https://gtmetrix.com/analyze.html';
    $gtmetrix_url  = add_query_arg(
        [
            'url'      => $test_url,
        ],
        $gtmetrix_base
    );

    // 5. Parent node: SEO Stuff
    $wp_admin_bar->add_node( [
        'id'    => 'ssseo-seo-stuff',
        'title' => __( 'SEO Stuff', 'ssseo' ),
        'href'  => false,
    ] );

    // 6. Child: Google Rich Results
    $wp_admin_bar->add_node( [
        'id'     => 'ssseo-test-schema',
        'parent' => 'ssseo-seo-stuff',
        'title'  => __( 'Test Schema', 'ssseo' ),
        'href'   => esc_url( $rich_results_url ),
        'meta'   => [
            'target' => '_blank',
            'title'  => __( 'Run Google Rich Results Test (url + source)', 'ssseo' ),
        ],
    ] );

    // 7. Children: PageSpeed Insights (Mobile & Desktop)
    foreach ( $psi_urls as $label => $url ) {
        $wp_admin_bar->add_node( [
            'id'     => 'ssseo-page-speed-' . strtolower( $label ),
            'parent' => 'ssseo-seo-stuff',
            'title'  => sprintf( __( 'Page Speed (%s)', 'ssseo' ), $label ),
            'href'   => esc_url( $url ),
            'meta'   => [
                'target' => '_blank',
                'title'  => sprintf( __( 'Run PageSpeed Insights (%s)', 'ssseo' ), $label ),
            ],
        ] );
    }

    // 8. Child: GTmetrix Performance Test
    $wp_admin_bar->add_node( [
        'id'     => 'ssseo-page-speed-gtmetrix',
        'parent' => 'ssseo-seo-stuff',
        'title'  => __( 'GTmetrix Test', 'ssseo' ),
        'href'   => esc_url( $gtmetrix_url ),
        'meta'   => [
            'target' => '_blank',
            'title'  => __( 'Run GTmetrix Performance Test (url + page_url)', 'ssseo' ),
        ],
    ] );
}
