<?php
/**
 * Plugin Name: SSSEO Tools
 * Description: Modular plugin for SEO and content enhancements.
 * Version: 3.9
 * Author: Dave Barry
 * Text Domain: ssseo
 */

defined('ABSPATH') || exit;

// Core Includes
require_once __DIR__ . '/github-updater.php';
require_once __DIR__ . '/schema-functions.php';
// Only in admin
    require_once __DIR__ . '/admin/gsc-oauth-callback.php';
	require_once __DIR__ . '/admin/ajax.php';
	require_once __DIR__ . '/admin/gsc-url-inspection.php';
    //require_once __DIR__ . '/admin/ajax-siteoptions-tests.php';

require_once __DIR__ . '/inc/yoast-log-hooks.php';
require_once __DIR__ . '/inc/llms-output.php';

// Modules
require_once __DIR__ . '/modules/cpt-registration.php';
require_once __DIR__ . '/modules/shortcodes.php';
require_once __DIR__ . '/modules/shortcodes-card-grid.php';
require_once __DIR__ . '/modules/filters.php';
require_once __DIR__ . '/modules/social-sharing.php';

require_once __DIR__ . '/modules/map-as-featured.php';
require_once __DIR__ . '/modules/map-embed-shortcode.php';
require_once __DIR__ . '/modules/service-area-grid.php';
require_once __DIR__ . '/modules/gmb-address.php';


require_once __DIR__ . '/modules/ai-functions.php';

if (get_option('ssseo_enable_youtube', '1') === '1') {
    require_once __DIR__ . '/inc/ssseo-video.php';
}
// File: inc/meta-history-logger.php
    require_once __DIR__ . '/inc/meta-history-logger.php';
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
		'gsc'			=> 'Search Console',
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


add_action('admin_enqueue_scripts', 'ssseo_enqueue_ai_script');
function ssseo_enqueue_ai_script($hook) {
    if (isset($_GET['page'], $_GET['tab']) && $_GET['page'] === 'ssseo-tools' && $_GET['tab'] === 'ai') {

        wp_enqueue_script(
            'ssseo-ai',
            plugin_dir_url(__FILE__) . 'assets/js/ssseo-ai.js',
            ['jquery'],
            '1.0.0',
            true
        );

        // Get post types & posts
        $post_types = get_post_types(['public' => true], 'objects');
        $posts_by_type = [];

        foreach ($post_types as $pt) {
            $posts = get_posts([
                'post_type'      => $pt->name,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);
            foreach ($posts as $p) {
                $posts_by_type[$pt->name][] = [
                    'id'    => $p->ID,
                    'title' => $p->post_title,
                ];
            }
        }

        $default_type = array_key_exists('video', $posts_by_type) ? 'video' : array_key_first($posts_by_type);

        wp_localize_script('ssseo-ai', 'SSSEO_AI', [
            'nonce'          => wp_create_nonce('ssseo_ai_generate'),
            'ajax_url'       => admin_url('admin-ajax.php'),
            'posts_by_type'  => $posts_by_type,
            'default_type'   => $default_type,
        ]);
    }
}
add_action('admin_enqueue_scripts', 'ssseo_enqueue_bulk_script');
function ssseo_enqueue_bulk_script($hook) {
    if (isset($_GET['page'], $_GET['tab']) && $_GET['page'] === 'ssseo-tools' && $_GET['tab'] === 'bulk') {

        wp_enqueue_script(
            'ssseo-admin',
            plugin_dir_url(__FILE__) . 'assets/js/ssseo-admin.js',
            ['jquery'],
            '1.0.0',
            true
        );

        // Load posts by type
        $post_types = get_post_types(['public' => true], 'objects');
        $posts_by_type = [];

        foreach ($post_types as $pt) {
            $posts = get_posts([
                'post_type'      => $pt->name,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);
            foreach ($posts as $p) {
                $posts_by_type[$pt->name][] = [
                    'id'    => $p->ID,
                    'title' => $p->post_title,
                ];
            }
        }

        $default_type = array_key_exists('video', $posts_by_type) ? 'video' : array_key_first($posts_by_type);

        wp_localize_script('ssseo-admin', 'ssseoPostsByType', $posts_by_type);
        wp_localize_script('ssseo-admin', 'ssseoDefaultType', $default_type);

        // Optional: for history or nonce use
        wp_localize_script('ssseo-admin', 'ssseo_admin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ssseo_admin_nonce'),
        ]);
    }
}


// ---- City/State helpers ----
function ssseo_get_city_state_value($post_id=null){
  $post_id = $post_id ? intval($post_id) : get_the_ID();
  if(!$post_id) return '';
  if(function_exists('get_field')){
    $val = get_field('city_state', $post_id);
  }else{
    $val = get_post_meta($post_id, 'city_state', true);
  }
  return is_string($val) ? $val : '';
}

// [city_state] shortcode
// Usage: [city_state], [city_state post_id="123"], [city_state context="parent"]
add_shortcode('city_state', function($atts){
  $atts = shortcode_atts([
    'post_id' => '',
    'context' => '', // '', 'parent'
  ], $atts, 'city_state');

  $pid = $atts['post_id'] !== '' ? intval($atts['post_id']) : get_the_ID();
  if(!$pid) return '';

  if($atts['context'] === 'parent'){
    $parent_id = wp_get_post_parent_id($pid);
    if($parent_id) $pid = $parent_id;
  }

  $out = ssseo_get_city_state_value($pid);
  /**
   * Filter: ssseo_city_state_output
   * Allow last-mile tweaks, e.g., strip commas or add wrappers.
   */
  $out = apply_filters('ssseo_city_state_output', $out, $pid, $atts);
  return esc_html($out);
});

// Optional: generic ACF-like helper without colliding with ACF's own [acf] shortcode
// Usage: [ssseo_acf field="city_state"] or (tolerant) [ssseo_acf city_state]
add_shortcode('ssseo_acf', function($atts){
  // Tolerate both field="name" and positional [ssseo_acf name]
  $defaults = ['field' => '', 'post_id' => ''];
  // Merge; numeric keys (positional) become values—use the first as field if not provided
  $atts = shortcode_atts($defaults, $atts, 'ssseo_acf');
  if(!$atts['field']){
    foreach($atts as $k=>$v){ if(is_int($k) && $v){ $atts['field']=$v; break; } }
  }
  $field = sanitize_key($atts['field']);
  $pid = $atts['post_id'] !== '' ? intval($atts['post_id']) : get_the_ID();
  if(!$field || !$pid) return '';
  if(function_exists('get_field')){
    $val = get_field($field, $pid);
  }else{
    $val = get_post_meta($pid, $field, true);
  }
  return esc_html(is_string($val)?$val:'');
});

// (Optional) run shortcodes in widget text; titles are generally NOT recommended.
// If you truly want shortcodes in titles site-wide, uncomment the next line:
// add_filter('the_title', 'do_shortcode');

add_action( 'acf/init', function() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
	'key' => 'group_68b69ccacb01f',
	'title' => 'Featured Icon',
	'fields' => array(
		array(
			'key' => 'field_68b69ccba9d34',
			'label' => 'Featured Icon',
			'name' => 'featured_icon',
			'aria-label' => '',
			'type' => 'image',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'return_format' => 'array',
			'library' => 'all',
			'min_width' => '',
			'min_height' => '',
			'min_size' => '',
			'max_width' => '',
			'max_height' => '',
			'max_size' => '',
			'mime_types' => '',
			'allow_in_bindings' => 0,
			'preview_size' => 'medium',
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'service',
			),
		),
		array(
			array(
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'service_area',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'normal',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => '',
	'active' => true,
	'description' => '',
	'show_in_rest' => 0,
) );
} );
/** Point to your plugin’s main file (adjust if needed). */
if ( ! defined('SSSEO_PLUGIN_FILE') ) {
  define('SSSEO_PLUGIN_FILE', __FILE__);
}

/** Helpers for paths/urls */
function ssseo_assets_url( $rel = '' ) {
  return plugins_url( ltrim($rel, '/'), SSSEO_PLUGIN_FILE );
}
function ssseo_assets_path( $rel = '' ) {
  return plugin_dir_path( SSSEO_PLUGIN_FILE ) . ltrim($rel, '/');
}

/** Front-end stylesheet */
add_action('wp_enqueue_scripts', function () {
  $rel  = 'assets/css/ssseo.css';
  $path = ssseo_assets_path($rel);
  $ver  = file_exists($path) ? filemtime($path) : '1.0.0';
  wp_enqueue_style('ssseo-tools', ssseo_assets_url($rel), [], $ver);
});

/** Admin stylesheet (load only on SSSEO pages or service_area edit screens) */
add_action('admin_enqueue_scripts', function () {
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;

  $is_ssseo_page = isset($_GET['page']) && strpos((string) $_GET['page'], 'ssseo') !== false;
  $is_service_area_edit = $screen && $screen->post_type === 'service_area' && in_array($screen->base, ['post','edit'], true);

  if ( ! $is_ssseo_page && ! $is_service_area_edit ) return;

  $rel  = 'assets/css/ssseo.css';
  $path = ssseo_assets_path($rel);
  $ver  = file_exists($path) ? filemtime($path) : '1.0.0';
  wp_enqueue_style('ssseo-tools-admin', ssseo_assets_url($rel), [], $ver);
});


add_action( 'acf/include_fields', function() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
	'key' => 'group_673eb878db87c',
	'title' => 'Service Area',
	'fields' => array(
		array(
			'key' => 'field_673eb8877610b',
			'label' => 'City, State',
			'name' => 'city_state',
			'aria-label' => '',
			'type' => 'text',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'default_value' => '',
			'maxlength' => '',
			'allow_in_bindings' => 1,
			'placeholder' => '',
			'prepend' => '',
			'append' => '',
		),
		array(
			'key' => 'field_68b935726ec39',
			'label' => 'HTML Excerpt',
			'name' => 'html_excerpt',
			'aria-label' => '',
			'type' => 'wysiwyg',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'default_value' => '',
			'allow_in_bindings' => 0,
			'tabs' => 'all',
			'toolbar' => 'full',
			'media_upload' => 1,
			'delay' => 0,
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'service_area',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'normal',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => '',
	'active' => true,
	'description' => '',
	'show_in_rest' => 0,
) );
} );

