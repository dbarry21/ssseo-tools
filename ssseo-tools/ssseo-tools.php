<?php
/*
Plugin Name: SSSEO Tools
Description: Modular plugin for SEO and content enhancements.
Version: 1.2.0
Author: Dave Barry
Text Domain: ssseo
*/

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/github-updater.php';

// In ssseo-tools.php, where you include the video code:
if ( '1' === get_option( 'ssseo_enable_youtube', '1' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'inc/ssseo-video.php';
}

// -------------------------
// Load Admin Bar Menu
// -------------------------
add_action( 'after_setup_theme', function() {
    if ( is_admin_bar_showing() ) {
        require_once plugin_dir_path( __FILE__ ) . 'admin/admin-bar-menu.php';
    }
} );


// -------------------------
// Include YouTube module
// -------------------------
$youtube_path = plugin_dir_path( __FILE__ ) . 'modules/videoblog/youtube.php';
if ( file_exists( $youtube_path ) ) {
    //require_once $youtube_path;
}


// -------------------------
// Register admin menu
// -------------------------
add_action( 'admin_menu', 'ssseo_tools_add_admin_menu' );
function ssseo_tools_add_admin_menu() {
    add_menu_page(
        'SSSEO Tools',
        'SSSEO Tools',
        'manage_options',
        'ssseo-tools',
        'ssseo_tools_render_admin_page',
        'dashicons-chart-area',
        60
    );
}

function ssseo_tools_render_admin_page() {
    $active_tab = $_GET['tab'] ?? 'videoblog';

    echo '<div class="wrap">';
    echo '<h1>SSSEO Tools</h1>';

    // Render navigation tabs
    echo '<nav class="nav-tab-wrapper">';
    echo '<a href="?page=ssseo-tools&tab=videoblog"   class="nav-tab ' . ( $active_tab === 'videoblog'   ? 'nav-tab-active' : '' ) . '">Video Blog</a>';
    echo '<a href="?page=ssseo-tools&tab=schema"       class="nav-tab ' . ( $active_tab === 'schema'       ? 'nav-tab-active' : '' ) . '">Schema</a>';
    echo '<a href="?page=ssseo-tools&tab=cpt"          class="nav-tab ' . ( $active_tab === 'cpt'          ? 'nav-tab-active' : '' ) . '">CPT Setup</a>';
    echo '<a href="?page=ssseo-tools&tab=site-options" class="nav-tab ' . ( $active_tab === 'site-options' ? 'nav-tab-active' : '' ) . '">Site Options</a>';
    echo '<a href="?page=ssseo-tools&tab=shortcodes"   class="nav-tab ' . ( $active_tab === 'shortcodes'   ? 'nav-tab-active' : '' ) . '">Shortcodes</a>';
    echo '<a href="?page=ssseo-tools&tab=ai"           class="nav-tab ' . ( $active_tab === 'ai'           ? 'nav-tab-active' : '' ) . '">AI</a>';
	echo '<a href="?page=ssseo-tools&tab=bulk"           class="nav-tab ' . ( $active_tab === 'bulk'           ? 'nav-tab-active' : '' ) . '">Bulk Operations</a>';
	echo '<a href="?page=ssseo-tools&tab=meta-tag-history"           class="nav-tab ' . ( $active_tab === 'meta-tag-history'           ? 'nav-tab-active' : '' ) . '">Meta Tag History</a>';
    echo '</nav>';

    $tab_file = plugin_dir_path( __FILE__ ) . "admin/tabs/{$active_tab}.php";
    if ( file_exists( $tab_file ) ) {
        include $tab_file;
    } else {
        echo '<p><em>Module tab not found.</em></p>';
    }

    echo '</div>';
}


// Enqueue admin scripts and styles for SSSEO Tools
add_action( 'admin_enqueue_scripts', 'ssseo_tools_enqueue_admin_assets' );
function ssseo_tools_enqueue_admin_assets( $hook ) {
    // Only load our assets on the SSSEO Tools main page
    if ( $hook !== 'toplevel_page_ssseo-tools' ) {
        return;
    }

    wp_enqueue_media();

    // Video blog JS
    wp_enqueue_script(
        'ssseo-videoblog-js',
        plugin_dir_url( __FILE__ ) . 'assets/js/videoblog.js',
        [ 'jquery' ],
        '1.0',
        true
    );

    // Localize for bulk.php inline script usage
    wp_register_script( 'ssseo-admin-dummy', '' ); // dummy script handle for localization
    wp_enqueue_script( 'ssseo-admin-dummy' );

    wp_localize_script( 'ssseo-admin-dummy', 'ssseo_admin', [
        'nonce'   => wp_create_nonce( 'ssseo_admin_nonce' ),
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
    ]);
}



// -------------------------
// Enqueue AI tab script (only when tab=ai)
// -------------------------
add_action( 'admin_enqueue_scripts', 'ssseo_enqueue_ai_tab_script' );
function ssseo_enqueue_ai_tab_script( $hook ) {
    if ( false === strpos( $hook, 'ssseo-tools' ) ) {
        return;
    }
    if ( empty( $_GET['tab'] ) || $_GET['tab'] !== 'ai' ) {
        return;
    }

    $js_path = plugin_dir_path( __FILE__ ) . 'assets/js/ssseo-ai.js';
    wp_enqueue_script(
        'ssseo-ai-js',
        plugin_dir_url( __FILE__ ) . 'assets/js/ssseo-ai.js',
        [ 'jquery' ],
        file_exists( $js_path ) ? filemtime( $js_path ) : false,
        true
    );

    wp_localize_script( 'ssseo-ai-js', 'ssseoAI', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'ssseo_ai_generate' ),
    ] );
}


// -------------------------
// Schema functions
// -------------------------
include_once plugin_dir_path( __FILE__ ) . 'schema-functions.php';


// -------------------------
// Handle LocalBusiness save
// -------------------------
add_action( 'admin_init', 'ssseo_handle_localbusiness_save' );
function ssseo_handle_localbusiness_save() {
    if (
        ! isset( $_POST['ssseo_localbusiness_nonce'] ) ||
        ! wp_verify_nonce( $_POST['ssseo_localbusiness_nonce'], 'ssseo_localbusiness_save' )
    ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $fields = [
        'name', 'phone', 'street', 'city', 'state', 'zip',
        'country', 'latitude', 'longitude'
    ];

    foreach ( $fields as $field ) {
        $key   = 'ssseo_localbusiness_' . $field;
        $value = isset( $_POST[ $key ] ) ? sanitize_text_field( $_POST[ $key ] ) : '';
        update_option( $key, $value );
    }

    // Save opening hours
    $hours_raw   = $_POST['ssseo_localbusiness_hours'] ?? [];
    $hours_clean = [];
    foreach ( $hours_raw as $row ) {
        if ( ! empty( $row['day'] ) && ! empty( $row['open'] ) && ! empty( $row['close'] ) ) {
            $hours_clean[] = [
                'day'   => sanitize_text_field( $row['day'] ),
                'open'  => sanitize_text_field( $row['open'] ),
                'close' => sanitize_text_field( $row['close'] ),
            ];
        }
    }
    update_option( 'ssseo_localbusiness_hours', $hours_clean );

    // Save selected pages
    $selected_pages = isset( $_POST['ssseo_localbusiness_pages'] ) ? array_map( 'intval', (array) $_POST['ssseo_localbusiness_pages'] ) : [];
    update_option( 'ssseo_localbusiness_pages', $selected_pages );
}


// -------------------------
// Require module files
// -------------------------
require_once plugin_dir_path( __FILE__ ) . 'modules/cpt-registration.php';
require_once plugin_dir_path( __FILE__ ) . 'modules/shortcodes.php';
require_once plugin_dir_path( __FILE__ ) . 'modules/filters.php';
require_once plugin_dir_path( __FILE__ ) . 'modules/map-as-featured.php';
require_once plugin_dir_path( __FILE__ ) . 'modules/ai-functions.php';  // AJAX handlers for AI tab
require_once plugin_dir_path( __FILE__ ) . 'inc/yoast-log-hooks.php';

require_once plugin_dir_path(__FILE__) . 'admin/ajax.php';


// -------------------------
// Enqueue Video Stylesheet
// -------------------------
add_action( 'wp_enqueue_scripts', 'ssseo_enqueue_video_styles' );
function ssseo_enqueue_video_styles() {
    $css_url = plugin_dir_url( __FILE__ ) . 'assets/css/ssseo-video.css';
    $css_path = plugin_dir_path( __FILE__ ) . 'assets/css/ssseo-video.css';

    wp_enqueue_style(
        'ssseo-video-styles',
        esc_url( $css_url ),
        [],
        file_exists( $css_path ) ? filemtime( $css_path ) : false
    );
}

// 1a) Register the option in WP
add_action( 'admin_init', function() {
    register_setting( 'ssseo_videoblog', 'ssseo_enable_youtube', [
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => true,
    ] );
} );

add_action('admin_enqueue_scripts', 'ssseo_enqueue_admin_bootstrap');
function ssseo_enqueue_admin_bootstrap($hook) {
    // Optional: limit to your plugin settings page only
    if (!isset($_GET['page']) || $_GET['page'] !== 'ssseo-tools') {
        return;
    }

    // Bootstrap 5.3+ via CDN
    wp_enqueue_style(
        'ssseo-bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        [],
        '5.3.3'
    );

    wp_enqueue_script(
        'ssseo-bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
        [],
        '5.3.3',
        true
    );
}

add_action('wp_ajax_ssseo_test_openai_key', 'ssseo_test_openai_key');
add_action('wp_ajax_ssseo_test_maps_key', 'ssseo_test_maps_key');
