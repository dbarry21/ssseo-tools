<?php
/**
 * Conditional CPT Registration: Services, Service Areas, Products
 * Uses stored admin options for enable, has_archive, and slug values.
 */

function ssseo_register_custom_post_types() {

    $cpts = [
        'service' => [
            'option_key' => 'ssseo_enable_service_cpt',
            'default_slug' => 'service',
            'default_archive' => 'services',
            'menu_position' => 21,
            'labels' => [
                'name' => 'Services',
                'singular' => 'Service',
            ],
        ],
        'service_area' => [
            'option_key' => 'ssseo_enable_service_area_cpt',
            'default_slug' => 'service-area',
            'default_archive' => 'service-areas',
            'menu_position' => 22,
            'labels' => [
                'name' => 'Service Areas',
                'singular' => 'Service Area',
            ],
        ],
        'product' => [
            'option_key' => 'ssseo_enable_product_cpt',
            'default_slug' => 'product',
            'default_archive' => 'products',
            'menu_position' => 23,
            'labels' => [
                'name' => 'Products',
                'singular' => 'Product',
            ],
        ],
    ];

    foreach ( $cpts as $post_type => $config ) {
        $enabled = get_option( $config['option_key'], '0' );
        if ( $enabled !== '1' ) continue;

        $has_archive = get_option( $config['option_key'] . '_hasarchive', $config['default_archive'] );
        $slug        = get_option( $config['option_key'] . '_slug', $config['default_slug'] );

        // Fallbacks
        $has_archive = $has_archive !== '' ? $has_archive : $config['default_archive'];
        $slug        = $slug !== '' ? $slug : $config['default_slug'];

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
            'has_archive'         => $has_archive,
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
