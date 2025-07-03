<?php
/**
 * Conditional CPT Registration: Services, Service Areas, Products
 * Uses stored admin options for enable, has_archive, and slug values.
 */

function ssseo_register_custom_post_types() {
    $cpts = [
        'service' => [
            'option_key'       => 'ssseo_enable_service_cpt',
            'default_slug'     => 'service',
            'default_archive'  => 'services',
            'menu_position'    => 21,
            'labels'           => [
                'name'     => 'Services',
                'singular' => 'Service',
            ],
        ],
        'service_area' => [
            'option_key'       => 'ssseo_enable_service_area_cpt',
            'default_slug'     => 'service-area',
            'default_archive'  => 'service-areas',
            'menu_position'    => 22,
            'labels'           => [
                'name'     => 'Service Areas',
                'singular' => 'Service Area',
            ],
        ],
        'product' => [
            'option_key'       => 'ssseo_enable_product_cpt',
            'default_slug'     => 'product',
            'default_archive'  => 'products',
            'menu_position'    => 23,
            'labels'           => [
                'name'     => 'Products',
                'singular' => 'Product',
            ],
        ],
    ];

    foreach ( $cpts as $post_type => $config ) {
        $enabled = get_option( $config['option_key'], '0' );
        if ( $enabled !== '1' ) continue;

        // Get admin-defined slug and archive
        $slug        = trim( get_option( $config['option_key'] . '_slug', '' ) );
        $has_archive = trim( get_option( $config['option_key'] . '_hasarchive', '' ) );

        // Apply defaults if missing
        $slug        = $slug !== '' ? $slug : $config['default_slug'];
        $has_archive = $has_archive !== '' ? $has_archive : false;

        // Register labels
        $labels = [
            'name'                  => _x( $config['labels']['name'], 'Post Type General Name', 'ssseo' ),
            'singular_name'         => _x( $config['labels']['singular'], 'Post Type Singular Name', 'ssseo' ),
            'menu_name'             => __( $config['labels']['name'], 'ssseo' ),
            'name_admin_bar'        => __( $config['labels']['singular'], 'ssseo' ),
            'archives'              => __( $config['labels']['singular'] . ' Archives', 'ssseo' ),
            'attributes'            => __( $config['labels']['singular'] . ' Attributes', 'ssseo' ),
            'parent_item_colon'     => __( 'Parent ' . $config['labels']['singular'] . ':', 'ssseo' ),
            'all_items'             => __( 'All ' . $config['labels']['name'], 'ssseo' ),
            'add_new_item'          => __( 'Add New ' . $config['labels']['singular'], 'ssseo' ),
            'add_new'               => __( 'Add New', 'ssseo' ),
            'new_item'              => __( 'New ' . $config['labels']['singular'], 'ssseo' ),
            'edit_item'             => __( 'Edit ' . $config['labels']['singular'], 'ssseo' ),
            'update_item'           => __( 'Update ' . $config['labels']['singular'], 'ssseo' ),
            'view_item'             => __( 'View ' . $config['labels']['singular'], 'ssseo' ),
            'view_items'            => __( 'View ' . $config['labels']['name'], 'ssseo' ),
            'search_items'          => __( 'Search ' . $config['labels']['singular'], 'ssseo' ),
            'not_found'             => __( 'Not found', 'ssseo' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'ssseo' ),
            'featured_image'        => __( 'Featured Image', 'ssseo' ),
            'set_featured_image'    => __( 'Set featured image', 'ssseo' ),
            'remove_featured_image' => __( 'Remove featured image', 'ssseo' ),
            'use_featured_image'    => __( 'Use as featured image', 'ssseo' ),
            'insert_into_item'      => __( 'Insert into ' . $config['labels']['singular'], 'ssseo' ),
            'uploaded_to_this_item' => __( 'Uploaded to this ' . $config['labels']['singular'], 'ssseo' ),
            'items_list'            => __( $config['labels']['name'] . ' list', 'ssseo' ),
            'items_list_navigation' => __( $config['labels']['name'] . ' list navigation', 'ssseo' ),
            'filter_items_list'     => __( 'Filter ' . $config['labels']['name'] . ' list', 'ssseo' ),
        ];

        // Register post type
        $args = [
            'label'               => __( $config['labels']['singular'], 'ssseo' ),
            'description'         => __( $config['labels']['singular'] . ' Description', 'ssseo' ),
            'labels'              => $labels,
            'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'page-attributes' ],
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => $config['menu_position'],
            'show_in_admin_bar'   => true,
            'show_in_nav_menus'   => true,
            'can_export'          => true,
            'has_archive'         => $has_archive, // ✅ now set to false if blank
            'hierarchical'        => true,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'show_in_rest'        => true,
            'rewrite'             => [ 'slug' => $slug, 'with_front' => false ],
            'capability_type'     => 'page',
        ];

        register_post_type( $post_type, $args );
    }
}
add_action( 'init', 'ssseo_register_custom_post_types', 0 );

add_action('add_meta_boxes', 'ssseo_add_about_the_area_metabox');
function ssseo_add_about_the_area_metabox() {
    add_meta_box(
        'ssseo_about_the_area',
        'About the Area',
        'ssseo_render_about_the_area_metabox',
        'service_area',
        'normal',
        'high'
    );
}

function ssseo_render_about_the_area_metabox($post) {
    $content = get_post_meta($post->ID, '_about_the_area', true);
    wp_nonce_field('ssseo_save_about_the_area', 'ssseo_about_the_area_nonce');
    wp_editor($content, 'about_the_area_editor', [
        'textarea_name' => 'about_the_area',
        'media_buttons' => true,
        'textarea_rows' => 8,
    ]);
}

add_action('save_post', 'ssseo_save_about_the_area_metabox');
function ssseo_save_about_the_area_metabox($post_id) {
    if (!isset($_POST['ssseo_about_the_area_nonce']) || !wp_verify_nonce($_POST['ssseo_about_the_area_nonce'], 'ssseo_save_about_the_area')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['about_the_area'])) {
        update_post_meta($post_id, '_about_the_area', wp_kses_post($_POST['about_the_area']));
    }
}

