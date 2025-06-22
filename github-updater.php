<?php
/**
 * GitHub Plugin Updater for SSSEO Tools
 * Author: dbarry21
 */

if (!defined('ABSPATH')) exit;

add_filter('pre_set_site_transient_update_plugins', function ($transient) {
    if (empty($transient->checked)) return $transient;

    $plugin_slug = 'ssseo-tools';
    $plugin_file = 'ssseo-tools/ssseo-tools.php';
    $repo_user   = 'dbarry21';
    $repo_name   = 'ssseo-tools';

    // Get current plugin version
    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
    $current_version = $plugin_data['Version'];

    // Call GitHub API for latest release
    $response = wp_remote_get("https://api.github.com/repos/{$repo_user}/{$repo_name}/releases/latest", [
        'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress Plugin Updater']
    ]);

    if (is_wp_error($response)) return $transient;

    $release = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($release['tag_name']) || empty($release['assets'][0]['browser_download_url'])) {
        return $transient;
    }

    $remote_version = ltrim($release['tag_name'], 'v');
    $download_url   = $release['assets'][0]['browser_download_url']; // Your uploaded zip

    // Compare versions
    if (version_compare($current_version, $remote_version, '<')) {
        $transient->response[$plugin_file] = (object) [
            'slug'        => $plugin_slug,
            'plugin'      => $plugin_file,
            'new_version' => $remote_version,
            'url'         => "https://github.com/{$repo_user}/{$repo_name}",
            'package'     => $download_url,
        ];
    }

    return $transient;
});

add_filter('plugins_api', function ($result, $action, $args) {
    if ($action !== 'plugin_information' || $args->slug !== 'ssseo-tools') {
        return false;
    }

    return (object) [
        'name'        => 'SSSEO Tools',
        'slug'        => 'ssseo-tools',
        'version'     => '1.1.0',
        'author'      => '<a href="https://stevescottseo.com">Steve Scott SEO</a>',
        'homepage'    => 'https://github.com/dbarry21/ssseo-tools',
        'sections'    => [
            'description' => '<p>SEO plugin tools for structured data, Yoast controls, AI, and more.</p>',
        ],
    ];
}, 10, 3);
