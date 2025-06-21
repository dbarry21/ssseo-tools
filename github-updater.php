<?php
/**
 * GitHub Plugin Updater for SSSEO Tools
 * Author: dbarry21
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Configuration
$ssseo_plugin_slug = 'ssseo-tools/ssseo-tools.php'; // plugin folder and main file
$ssseo_repo_user   = 'dbarry21';
$ssseo_repo_name   = 'ssseo-tools';

// GitHub Update Checker
add_filter('pre_set_site_transient_update_plugins', function($transient) use ($ssseo_plugin_slug, $ssseo_repo_user, $ssseo_repo_name) {
    if (empty($transient->checked)) return $transient;

    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $ssseo_plugin_slug);
    $current_version = $plugin_data['Version'];

    $api_url = "https://api.github.com/repos/$ssseo_repo_user/$ssseo_repo_name/releases/latest";
    $response = wp_remote_get($api_url, [
        'headers' => ['User-Agent' => 'WordPress/' . get_bloginfo('version')]
    ]);

    if (is_wp_error($response)) return $transient;

    $release = json_decode(wp_remote_retrieve_body($response));
    if (empty($release->tag_name)) return $transient;

    $new_version = ltrim($release->tag_name, 'v');
    if (version_compare($current_version, $new_version, '>=')) return $transient;

    $transient->response[$ssseo_plugin_slug] = (object)[
        'slug'        => 'ssseo-tools',
        'plugin'      => $ssseo_plugin_slug,
        'new_version' => $new_version,
        'url'         => $release->html_url,
        'package'     => $release->zipball_url,
    ];

    return $transient;
}, 10, 1);

// Plugin Info for "View Details" Modal
add_filter('plugins_api', function($result, $action, $args) use ($ssseo_repo_user, $ssseo_repo_name) {
    if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'ssseo-tools') {
        return false;
    }

    $repo_url = "https://api.github.com/repos/$ssseo_repo_user/$ssseo_repo_name";
    $response = wp_remote_get($repo_url, [
        'headers' => ['User-Agent' => 'WordPress/' . get_bloginfo('version')]
    ]);

    if (is_wp_error($response)) return false;

    $repo = json_decode(wp_remote_retrieve_body($response));
    if (empty($repo)) return false;

    return (object)[
        'name'          => $repo->name ?? 'SSSEO Tools',
        'slug'          => 'ssseo-tools',
        'version'       => $repo->tag_name ?? 'unknown',
        'author'        => '<a href="' . esc_url($repo->owner->html_url) . '">' . esc_html($repo->owner->login) . '</a>',
        'homepage'      => $repo->html_url,
        'sections'      => [
            'description' => $repo->description ?? 'GitHub-hosted plugin.',
        ],
        'download_link' => $repo->zipball_url,
    ];
}, 20, 3);
