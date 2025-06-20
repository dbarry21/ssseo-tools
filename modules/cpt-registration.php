<?php
/**
 * Conditional CPT Registration: Services, Service Areas, Products
 * Registers each CPT only if its “Enable” checkbox is checked in admin settings.
 */

function ssseo_register_custom_post_types() {

    /**
     * 1. Service CPT
     */
    if ( get_option( 'ssseo_enable_service_cpt', '0' ) === '1' ) {
        $service_labels = [
            'name'                  => _x( 'Services', 'Post Type General Name', 'ssseo' ),
            'singular_name'         => _x( 'Service', 'Post Type Singular Name', 'ssseo' ),
            'menu_name'             => __( 'Services', 'ssseo' ),
            'name_admin_bar'        => __( 'Service', 'ssseo' ),
            'archives'              => __( 'Service Archives', 'ssseo' ),
            'attributes'            => __( 'Service Attributes', 'ssseo' ),
            'parent_item_colon'     => __( 'Parent Service:', 'ssseo' ),
            'all_items'             => __( 'All Services', 'ssseo' ),
            'add_new_item'          => __( 'Add New Service', 'ssseo' ),
            'add_new'               => __( 'Add New', 'ssseo' ),
            'new_item'              => __( 'New Service', 'ssseo' ),
            'edit_item'             => __( 'Edit Service', 'ssseo' ),
            'update_item'           => __( 'Update Service', 'ssseo' ),
            'view_item'             => __( 'View Service', 'ssseo' ),
            'view_items'            => __( 'View Services', 'ssseo' ),
            'search_items'          => __( 'Search Service', 'ssseo' ),
            'not_found'             => __( 'Not found', 'ssseo' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'ssseo' ),
            'featured_image'        => __( 'Featured Image', 'ssseo' ),
            'set_featured_image'    => __( 'Set featured image', 'ssseo' ),
            'remove_featured_image' => __( 'Remove featured image', 'ssseo' ),
            'use_featured_image'    => __( 'Use as featured image', 'ssseo' ),
            'insert_into_item'      => __( 'Insert into Service', 'ssseo' ),
            'uploaded_to_this_item' => __( 'Uploaded to this Service', 'ssseo' ),
            'items_list'            => __( 'Services list', 'ssseo' ),
            'items_list_navigation' => __( 'Services list navigation', 'ssseo' ),
            'filter_items_list'     => __( 'Filter Services list', 'ssseo' ),
        ];

        $service_args = [
            'label'               => __( 'Service', 'ssseo' ),
            'description'         => __( 'Service Description', 'ssseo' ),
            'labels'              => $service_labels,
            'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'page-attributes' ],
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => 21,
            'show_in_admin_bar'   => true,
            'show_in_nav_menus'   => true,
            'can_export'          => true,
            'has_archive'         => true,
            'hierarchical'        => true,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'show_in_rest'        => true,
            'rewrite'             => [ 'slug' => 'service', 'with_front' => false ],
            'capability_type'     => 'page',
        ];

        register_post_type( 'service', $service_args );
    }

    /**
     * 2. Service Area CPT
     */
    if ( get_option( 'ssseo_enable_service_area_cpt', '0' ) === '1' ) {
         $service_area_labels = array(
        'name'                  => _x('Service Areas', 'Post Type General Name', 'ssseo'),
        'singular_name'         => _x('Service Area', 'Post Type Singular Name', 'ssseo'),
        'menu_name'             => __('Service Areas', 'ssseo'),
        'name_admin_bar'        => __('Service Area', 'ssseo'),
        'archives'              => __('Service Area Archives', 'ssseo'),
        'attributes'            => __('Service Area Attributes', 'ssseo'),
        'parent_item_colon'     => __('Parent Service Area:', 'ssseo'),
        'all_items'             => __('All Service Areas', 'ssseo'),
        'add_new_item'          => __('Add New Service Area', 'ssseo'),
        'add_new'               => __('Add New', 'ssseo'),
        'new_item'              => __('New Service Area', 'ssseo'),
        'edit_item'             => __('Edit Service Area', 'ssseo'),
        'update_item'           => __('Update Service Area', 'ssseo'),
        'view_item'             => __('View Service Area', 'ssseo'),
        'view_items'            => __('View Service Areas', 'ssseo'),
        'search_items'          => __('Search Service Area', 'ssseo'),
        'not_found'             => __('Not found', 'ssseo'),
        'not_found_in_trash'    => __('Not found in Trash', 'ssseo'),
        'featured_image'        => __('Featured Image', 'ssseo'),
        'set_featured_image'    => __('Set featured image', 'ssseo'),
        'remove_featured_image' => __('Remove featured image', 'ssseo'),
        'use_featured_image'    => __('Use as featured image', 'ssseo'),
        'insert_into_item'      => __('Insert into Service Area', 'ssseo'),
        'uploaded_to_this_item' => __('Uploaded to this Service Area', 'ssseo'),
        'items_list'            => __('Service Areas list', 'ssseo'),
        'items_list_navigation' => __('Service Areas list navigation', 'ssseo'),
        'filter_items_list'     => __('Filter Service Areas list', 'ssseo'),
    );

    // Arguments for the Service Areas custom post type
    $service_area_args = array(
        'label'                 => __('Service Area', 'ssseo'),
        'description'           => __('Service Area Description', 'ssseo'),
        'labels'                => $service_area_labels,
        'supports'              => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'page-attributes', 'tags'),
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 22, // You might want to change this to avoid menu position conflict
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'hierarchical'          => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'rewrite'               => array('slug' => 'service-area', 'with_front' => false),
        'capability_type'       => 'page',
    );

// Register the Service Areas custom post type
register_post_type('service_area', $service_area_args);
    }

    /**
     * 3. Product CPT
     */
    if ( get_option( 'ssseo_enable_product_cpt', '0' ) === '1' ) {
        $product_labels = [
            'name'                  => _x( 'Products', 'Post Type General Name', 'ssseo' ),
            'singular_name'         => _x( 'Product', 'Post Type Singular Name', 'ssseo' ),
            'menu_name'             => __( 'Products', 'ssseo' ),
            'name_admin_bar'        => __( 'Product', 'ssseo' ),
            'archives'              => __( 'Product Archives', 'ssseo' ),
            'attributes'            => __( 'Product Attributes', 'ssseo' ),
            'parent_item_colon'     => __( 'Parent Product:', 'ssseo' ),
            'all_items'             => __( 'All Products', 'ssseo' ),
            'add_new_item'          => __( 'Add New Product', 'ssseo' ),
            'add_new'               => __( 'Add New', 'ssseo' ),
            'new_item'              => __( 'New Product', 'ssseo' ),
            'edit_item'             => __( 'Edit Product', 'ssseo' ),
            'update_item'           => __( 'Update Product', 'ssseo' ),
            'view_item'             => __( 'View Product', 'ssseo' ),
            'view_items'            => __( 'View Products', 'ssseo' ),
            'search_items'          => __( 'Search Product', 'ssseo' ),
            'not_found'             => __( 'Not found', 'ssseo' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'ssseo' ),
            'featured_image'        => __( 'Featured Image', 'ssseo' ),
            'set_featured_image'    => __( 'Set featured image', 'ssseo' ),
            'remove_featured_image' => __( 'Remove featured image', 'ssseo' ),
            'use_featured_image'    => __( 'Use as featured image', 'ssseo' ),
            'insert_into_item'      => __( 'Insert into Product', 'ssseo' ),
            'uploaded_to_this_item' => __( 'Uploaded to this Product', 'ssseo' ),
            'items_list'            => __( 'Products list', 'ssseo' ),
            'items_list_navigation' => __( 'Products list navigation', 'ssseo' ),
            'filter_items_list'     => __( 'Filter Products list', 'ssseo' ),
        ];

        $product_args = [
            'label'               => __( 'Product', 'ssseo' ),
            'description'         => __( 'Product Description', 'ssseo' ),
            'labels'              => $product_labels,
            'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'page-attributes' ],
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => 23,
            'show_in_admin_bar'   => true,
            'show_in_nav_menus'   => true,
            'can_export'          => true,
            'has_archive'         => true,
            'hierarchical'        => true,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'show_in_rest'        => true,
            'rewrite'             => [ 'slug' => 'products', 'with_front' => false ],
            'capability_type'     => 'page',
        ];

        register_post_type( 'product', $product_args );
    }
}

// Hook into 'init' so that CPTs register after options load
add_action( 'init', 'ssseo_register_custom_post_types', 0 );
