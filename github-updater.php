<?php
/**
 * GitHub Plugin Updater for SSSEO Tools
 * Author: dbarry21
 */

if (!defined('ABSPATH')) exit;

// Config
$ssseo_plugin_file = 'ssseo-tools/ssseo-tools.php';
$ssseo_repo_user   = 'dbarry21';
$ssseo_repo_name   = 'ssseo-tools';

// Add update check
add_filter('pre_set_site_transient_update_plugins', function ($transient) use ($ssseo_plugin_file, $ssseo_repo_user, $ssseo_repo_name) {
    if (empty($transient->checked)) return $transient;

    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $ssseo_plugin_file);
    $current_version = $plugin_data['Version'];

    $url = "https://api.github.com/repos/$ssseo_repo_user/$ssseo_repo_name/releases/latest";

    $response = wp_remote_get($url, [
        'headers' => ['User-Agent' => 'WordPress/' . get_bloginfo('version')],
    ]);

    if (is_wp_error($response)) return $transient;

    $release = json_decode(wp_remote_retrieve_body($response));

    if (empty($release->tag_name)) return $transient;

    $new_version = ltrim($release->tag_name, 'v');

    if (version_compare($current_version, $new_version, '>=')) return $transient;

    $transient->response[$ssseo_plugin_file] = (object)[
        'slug'        => $ssseo_repo_name,
        'plugin'      => $ssseo_plugin_file,
        'new_version' => $new_version,
        'url'         => $release->html_url,
        'package'     => $release->zipball_url,
    ];

    return $transient;
});

// Add plugin info screen
add_filter('plugins_api', function ($res, $action, $args) use ($ssseo_repo_user, $ssseo_repo_name) {
    if ($action !== 'plugin_information' || $args->slug !== $ssseo_repo_name) return false;

    $repo_url = "https://api.github.com/repos/$ssseo_repo_user/$ssseo_repo_name";

    $response = wp_remote_get($repo_url, [
        'headers' => ['User-Agent' => 'WordPress/' . get_bloginfo('version')],
    ]);

    if (is_wp_error($response)) return false;

    $repo = json_decode(wp_remote_retrieve_body($response));

    return (object)[
        'name'          => $repo->name ?? $ssseo_repo_name,
        'slug'          => $ssseo_repo_name,
        'version'       => $repo->tag_name ?? 'unknown',
        'author'        => '<a href="' . esc_url($repo->owner->html_url) . '">' . esc_html($repo->owner->login) . '</a>',
        'homepage'      => $repo->html_url,
        'sections'      => [
            'description' => $repo->description ?? 'GitHub-hosted plugin.',
        ],
        'download_link' => $repo->zipball_url,
    ];
}, 20, 3);
