<?php
/**
 * GitHub Plugin Updater for SSSEO Tools
 * Author: dbarry21
 */

if (!defined('ABSPATH')) exit;

// Configuration
$ssseo_plugin_file = 'ssseo-tools/ssseo-tools.php'; // Plugin path relative to plugins dir
$ssseo_repo_user   = 'dbarry21';
$ssseo_repo_name   = 'ssseo-tools';

// Hook into WordPress update system
add_filter('pre_set_site_transient_update_plugins', function ($transient) use ($ssseo_plugin_file, $ssseo_repo_user, $ssseo_repo_name) {
    if (empty($transient->checked)) return $transient;

    // Current installed version
    $plugin_data     = get_plugin_data(WP_PLUGIN_DIR . '/' . $ssseo_plugin_file);
    $current_version = $plugin_data['Version'];

    // Get latest release from GitHub API
    $api_url = "https://api.github.com/repos/{$ssseo_repo_user}/{$ssseo_repo_name}/releases/latest";

    // Set User-Agent or GitHub will reject the request
    $response = wp_remote_get($api_url, [
        'headers' => ['User-Agent' => 'WordPress Plugin Updater'],
        'timeout' => 15
    ]);

    if (is_wp_error($response)) return $transient;

    $release_data = json_decode(wp_remote_retrieve_body($response));

    if (!isset($release_data->tag_name)) return $transient;

    // Remove leading "v" if present
    $latest_version = ltrim($release_data->tag_name, 'v');

    // Compare versions
    if (version_compare($current_version, $latest_version, '<')) {
        $transient->response[$ssseo_plugin_file] = (object)[
            'slug'        => $ssseo_repo_name,
            'plugin'      => $ssseo_plugin_file,
            'new_version' => $latest_version,
            'url'         => $release_data->html_url ?? "https://github.com/{$ssseo_repo_user}/{$ssseo_repo_name}",
            'package'     => $release_data->zipball_url, // GitHub zip URL
        ];
    }

    return $transient;
});
