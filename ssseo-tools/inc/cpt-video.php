<?php
// inc/cpt-video.php
if ( ! defined('ABSPATH') ) exit;

/**
 * Registers the "video" CPT if enabled in CPT settings.
 * Uses options:
 *   - ssseo_enable_video_cpt           (1|0)
 *   - ssseo_enable_video_cpt_slug      (string, default "video")
 *   - ssseo_enable_video_cpt_hasarchive(string, default "videos", blank disables)
 */
function ssseo_register_video_cpt_from_options() {
    // Respect the toggle
    if ( get_option('ssseo_enable_video_cpt', '0') !== '1' ) {
        return; // disabled in admin
    }

    // Avoid double-registration if another module already did it
    if ( post_type_exists('video') ) {
        return;
    }

    $slug_opt      = trim( (string) get_option('ssseo_enable_video_cpt_slug', '') );
    $archive_opt   = trim( (string) get_option('ssseo_enable_video_cpt_hasarchive', '') );

    $rewrite_slug  = $slug_opt !== '' ? $slug_opt : 'video';
    $has_archive   = $archive_opt !== '' ? $archive_opt : 'videos'; // blank disables archives

    if ( $archive_opt === '' ) {
        $has_archive = false; // user explicitly disabled archive
    }

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
        'search_items'          => __( 'Search Videos', 'ssseo' ),
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
        'supports'              => array( 'title','editor','thumbnail','excerpt','custom-fields','page-attributes' ),
        'taxonomies'            => array( 'post_tag' ),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 21,
        'menu_icon'             => 'dashicons-video-alt3',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => $has_archive, // string or false
        'rewrite'               => array(
            'slug'       => $rewrite_slug,  // base, e.g. "video"
            'with_front' => false,
        ),
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'show_in_rest'          => true,
        'map_meta_cap'          => true,
    );

    register_post_type( 'video', $args );
}
add_action( 'init', 'ssseo_register_video_cpt_from_options', 9 );
