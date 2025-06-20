<?php
/**
 * Admin UI: Shortcodes Subtab (Under SSSEO Tools)
 * Version: 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo '<h2>Available SSSEO Shortcodes</h2>';
echo '<p>Copy and paste any of the following shortcodes into your content:</p>';

// 1) service_area_list
echo '<h3><code>[service_area_list]</code></h3>';
echo '<p>Outputs a Bootstrap‐styled list of all “service_area” CPT entries (excluding the current one). Usage example:</p>';
echo '<pre><code> [service_area_list] </code></pre>';

// 2) faq_schema_accordion
echo '<h3><code>[faq_schema_accordion]</code></h3>';
echo '<p>Displays a Bootstrap 5 accordion from the ACF “faq_items” repeater on the current post. Usage example:</p>';
echo '<pre><code> [faq_schema_accordion] </code></pre>';

// 3) ssseo_category_list
echo '<h3><code>[ssseo_category_list include_empty="0|1" min_count="0"]</code></h3>';
echo '<p>Outputs a Bootstrap list‐group of categories. Attributes:</p>';
echo '<ul>
        <li><code>include_empty</code> (0 or 1, default 0) – show empty categories.</li>
        <li><code>min_count</code> (integer, default 0) – only show categories with at least that many posts.</li>
      </ul>';
echo '<pre><code> [ssseo_category_list include_empty="1" min_count="5"] </code></pre>';

// 4) custom_blog_cards
echo '<h3><code>[custom_blog_cards posts_per_page="12"]</code></h3>';
echo '<p>Renders a Bootstrap card grid of latest posts. You can override <code>posts_per_page</code>.</p>';
echo '<pre><code> [custom_blog_cards posts_per_page="6"] </code></pre>';
