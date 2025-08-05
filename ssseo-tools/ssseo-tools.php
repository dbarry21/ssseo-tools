<?php
/**
 * Plugin Name: SSSEO Tools
 * Description: Modular plugin for SEO and content enhancements.
 * Version: 2.4.0
 * Author: Dave Barry
 * Text Domain: ssseo
 */

defined('ABSPATH') || exit;

// Core Includes
require_once __DIR__ . '/github-updater.php';
require_once __DIR__ . '/schema-functions.php';
require_once __DIR__ . '/admin/ajax.php';
require_once __DIR__ . '/inc/yoast-log-hooks.php';
require_once __DIR__ . '/inc/llms-output.php';

// Modules
require_once __DIR__ . '/modules/cpt-registration.php';
require_once __DIR__ . '/modules/shortcodes.php';
require_once __DIR__ . '/modules/filters.php';
require_once __DIR__ . '/modules/map-as-featured.php';
require_once __DIR__ . '/modules/ai-functions.php';

if (get_option('ssseo_enable_youtube', '1') === '1') {
    require_once __DIR__ . '/inc/ssseo-video.php';
}

// Admin Bar
add_action('after_setup_theme', function () {
    if (is_admin_bar_showing()) {
        require_once __DIR__ . '/admin/admin-bar-menu.php';
    }
});

// Admin Menu
add_action('admin_menu', function () {
    add_menu_page(
        'SSSEO Tools',
        'SSSEO Tools',
        'manage_options',
        'ssseo-tools',
        'ssseo_tools_render_admin_page',
        'dashicons-chart-area',
        60
    );
});

// Tab Loader
function ssseo_tools_render_admin_page() {
    $active_tab = $_GET['tab'] ?? 'videoblog';

    echo '<div class="wrap"><h1>SSSEO Tools</h1><nav class="nav-tab-wrapper">';

    $tabs = [
        'videoblog'        => 'Video Blog',
        'schema'           => 'Schema',
        'cpt'              => 'CPT Setup',
        'site-options'     => 'Site Options',
        'shortcodes'       => 'Shortcodes',
        'ai'               => 'AI',
        'bulk'             => 'Bulk Operations',
        'meta-tag-history' => 'Meta Tag History',
    ];

    foreach ($tabs as $slug => $label) {
        $active = $slug === $active_tab ? ' nav-tab-active' : '';
        echo "<a href='?page=ssseo-tools&tab=$slug' class='nav-tab$active'>$label</a>";
    }

    echo '</nav>';

    $tab_file = plugin_dir_path(__FILE__) . "admin/tabs/{$active_tab}.php";
    file_exists($tab_file) ? include $tab_file : print '<p><em>Module tab not found.</em></p>';
    echo '</div>';
}

// Admin Assets
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'ssseo-tools') === false) return;

    wp_enqueue_media();
    wp_enqueue_style('ssseo-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', [], '5.3.3');
    wp_enqueue_script('ssseo-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], '5.3.3', true);
    wp_enqueue_script('ssseo-admin-js', plugin_dir_url(__FILE__) . 'assets/js/ssseo-admin.js', ['jquery'], filemtime(__DIR__ . '/assets/js/ssseo-admin.js'), true);

    wp_localize_script('ssseo-admin-js', 'ssseo_admin', [
        'nonce'   => wp_create_nonce('ssseo_admin_nonce'),
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});

// Transcript Metabox Scripts
add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;

    wp_enqueue_script(
        'ssseo-video-transcript',
        plugin_dir_url(__FILE__) . 'assets/js/ssseo-transcript.js',
        ['jquery'],
        '1.0',
        true
    );

    wp_localize_script('ssseo-video-transcript', 'SSSEO_Transcript', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('ssseo_generate_transcript_nonce'),
    ]);
});

// OpenAI Transcript Generation
add_action('wp_ajax_ssseo_generate_transcript', function () {
    if (!current_user_can('edit_posts') || empty($_POST['post_id'])) {
        wp_send_json_error('Unauthorized or missing data.');
    }

    $post_id = absint($_POST['post_id']);
    $video_id = get_post_meta($post_id, '_ssseo_video_id', true);
    $title = get_the_title($post_id);
    $desc = get_the_excerpt($post_id);

    if (!$video_id || !$title) {
        wp_send_json_error('Missing video ID or title.');
    }

    $prompt = "Generate a summarized transcript for the YouTube video titled:\n\n\"$title\"\n\nDescription:\n$desc\n\nRecap the video in a helpful summary.";
    $api_key = get_option('ssseo_openai_api_key');
    if (!$api_key) wp_send_json_error('Missing API key');

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'model'    => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant summarizing YouTube videos.'],
                ['role' => 'user',   'content' => $prompt],
            ],
            'max_tokens'  => 1000,
            'temperature' => 0.7,
        ]),
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    $summary = $data['choices'][0]['message']['content'] ?? '';
    if (!$summary) {
        wp_send_json_error('No content returned.');
    }

    update_post_meta($post_id, '_ssseo_video_transcript', sanitize_textarea_field($summary));
    wp_send_json_success($summary);
});

// LocalBusiness Schema Form Save
add_action('admin_init', function () {
    if (!isset($_POST['ssseo_localbusiness_nonce']) || !wp_verify_nonce($_POST['ssseo_localbusiness_nonce'], 'ssseo_localbusiness_save')) return;
    if (!current_user_can('manage_options')) return;

    $fields = ['name', 'phone', 'street', 'city', 'state', 'zip', 'country', 'latitude', 'longitude'];
    foreach ($fields as $field) {
        $key = 'ssseo_localbusiness_' . $field;
        $val = sanitize_text_field($_POST[$key] ?? '');
        update_option($key, $val);
    }

    $hours = array_filter($_POST['ssseo_localbusiness_hours'] ?? [], function ($row) {
        return !empty($row['day']) && !empty($row['open']) && !empty($row['close']);
    });

    update_option('ssseo_localbusiness_hours', array_map(function ($row) {
        return [
            'day'   => sanitize_text_field($row['day']),
            'open'  => sanitize_text_field($row['open']),
            'close' => sanitize_text_field($row['close']),
        ];
    }, $hours));

    update_option('ssseo_localbusiness_pages', array_map('intval', $_POST['ssseo_localbusiness_pages'] ?? []));
});

// Register Video Settings Option
add_action('admin_init', function () {
    register_setting('ssseo_videoblog', 'ssseo_enable_youtube', [
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => true,
    ]);
});

// Register Metabox for Service Type
add_action('add_meta_boxes', function () {
    add_meta_box(
        'ssseo_service_area_type',
        'Service Type (Schema.org)',
        'ssseo_render_service_area_type_meta_box',
        'service_area',
        'side'
    );
});

function ssseo_render_service_area_type_meta_box($post) {
    $value = get_post_meta($post->ID, '_ssseo_service_area_type', true);
    $types = [
        '' => '-- Select Service Type --',
        'LegalService'             => 'Legal Service',
        'FinancialService'         => 'Financial Service',
        'FoodService'              => 'Food Service',
        'MedicalBusiness'          => 'Medical Service',
        'HomeAndConstructionBusiness' => 'Home/Construction Service',
        'EmergencyService'         => 'Emergency Service',
        'AutomotiveBusiness'       => 'Automotive Service',
        'ChildCare'                => 'Child Care',
        'CleaningService'          => 'Cleaning Service',
        'Electrician'              => 'Electrician',
        'Plumber'                  => 'Plumber',
        'HVACBusiness'             => 'HVAC Service',
        'RoofingContractor'        => 'Roofing Contractor',
        'MovingCompany'            => 'Moving Company',
        'PestControl'              => 'Pest Control',
    ];

    echo '<select name="ssseo_service_area_type" class="widefat">';
    foreach ($types as $key => $label) {
        $selected = selected($value, $key, false);
        echo "<option value='" . esc_attr($key) . "' $selected>" . esc_html($label) . "</option>";
    }
    echo '</select>';
}

add_action('save_post_service_area', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (isset($_POST['ssseo_service_area_type'])) {
        update_post_meta($post_id, '_ssseo_service_area_type', sanitize_text_field($_POST['ssseo_service_area_type']));
    }
});

add_action( 'admin_enqueue_scripts', function( $hook ) {
	if ( strpos($hook, 'ssseo-tools') === false ) return;

	// Global plugin assets
	wp_enqueue_media();
	wp_enqueue_style('ssseo-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', [], '5.3.3');
	wp_enqueue_script('ssseo-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], '5.3.3', true);

	// Always enqueue general admin JS
	wp_enqueue_script(
		'ssseo-admin-js',
		plugin_dir_url(__FILE__) . 'assets/js/ssseo-admin.js',
		['jquery'],
		filemtime(__DIR__ . '/assets/js/ssseo-admin.js'),
		true
	);

	wp_localize_script('ssseo-admin-js', 'ssseo_admin', [
		'nonce'   => wp_create_nonce('ssseo_admin_nonce'),
		'ajaxurl' => admin_url('admin-ajax.php'),
	]);

	// --- Conditionally load AI tab JS only when tab=ai
	if ( isset($_GET['tab']) && $_GET['tab'] === 'ai' ) {
		wp_enqueue_script(
			'ssseo-ai',
			plugin_dir_url(__FILE__) . 'assets/js/ssseo-ai.js',
			['jquery'],
			filemtime(plugin_dir_path(__FILE__) . 'assets/js/ssseo-ai.js'),
			true
		);

		wp_localize_script('ssseo-ai', 'SSSEO_AI', [
			'nonce'          => wp_create_nonce('ssseo_ai_generate'),
			'ajax_url'       => admin_url('admin-ajax.php'),
			'default_type'   => 'post',
			'posts_by_type'  => [],
		]);
	}

	// --- Conditionally load transcript fetch JS for the Video ID tools subtab
	if ( isset($_GET['tab'], $_GET['subtab']) && $_GET['tab'] === 'videoblog' && $_GET['subtab'] === 'videoid' ) {
		wp_enqueue_script(
			'ssseo-video-transcript',
			plugin_dir_url(__FILE__) . 'assets/js/ssseo-transcript.js',
			['jquery'],
			'1.0',
			true
		);

		wp_localize_script('ssseo-video-transcript', 'SSSEO_Transcript', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('ssseo_generate_transcript_nonce'),
		]);
	}
});


