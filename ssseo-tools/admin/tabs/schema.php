<?php
defined('ABSPATH') || exit;

// Detect which subtab is active
$subtab = isset($_GET['subtab']) ? sanitize_text_field($_GET['subtab']) : 'organization';

// Render subtabs menu
echo '<h2>Structured Data / Schema</h2>';
echo '<nav class="nav-tab-wrapper ssseo-subtabs">';
$subtabs = [
    'organization' => 'Organization',
    'localbusiness' => 'Local Business',
    'faq' => 'FAQ',
	'blogposts' => 'Blog Schema',
];
foreach ($subtabs as $slug => $label) {
    $active = ($subtab === $slug) ? 'nav-tab-active' : '';
    echo '<a href="?page=ssseo-tools&tab=schema&subtab=' . esc_attr($slug) . '" class="nav-tab ' . $active . '">' . esc_html($label) . '</a>';
}
echo '</nav>';

// Load subtab content
$subtab_file = plugin_dir_path(__FILE__) . 'schema/' . $subtab . '.php';
if (file_exists($subtab_file)) {
    include $subtab_file;
} else {
    echo '<p>Section not found.</p>';
}
