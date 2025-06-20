<?php
echo '<h3>❓ FAQ Schema</h3>';
echo '<p>Generate FAQ structured data using question/answer pairs.</p>';
echo '<p>This is accomplished at the bottom of default Wordpress Editor</p>';

if (!defined('ABSPATH')) exit;

$active_tab = $_GET['tab'] ?? 'faq';



// Display shortcode notice
echo '<div class="notice notice-info" style="margin-top:20px;"><p><strong>FAQ Shortcode:</strong> Use <code>[faq_schema_accordion]</code> to display your FAQ content with schema.</p></div>';
