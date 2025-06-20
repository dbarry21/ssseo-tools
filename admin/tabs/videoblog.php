<?php
echo '<h2>YouTube Integration</h2>';

if (isset($_POST['ssseo_youtube_settings_submit'])) {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('ssseo_save_youtube_settings');

    update_option('ssseo_youtube_api_key', sanitize_text_field($_POST['ssseo_youtube_api_key'] ?? ''));
    update_option('ssseo_youtube_channel_id', sanitize_text_field($_POST['ssseo_youtube_channel_id'] ?? ''));

    echo '<div class="updated"><p><strong>Settings saved.</strong></p></div>';
}

$api_key = get_option('ssseo_youtube_api_key', '');
$channel_id = get_option('ssseo_youtube_channel_id', '');
?>

<form method="post">
    <?php wp_nonce_field('ssseo_save_youtube_settings'); ?>
    <table class="form-table">
        <tr>
            <th scope="row"><label for="ssseo_youtube_api_key">YouTube API Key</label></th>
            <td>
                <input name="ssseo_youtube_api_key" type="text" id="ssseo_youtube_api_key" class="regular-text" value="<?php echo esc_attr($api_key); ?>">
                <p class="description">Enter your YouTube API key (starts with AIza...)</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="ssseo_youtube_channel_id">YouTube Channel ID</label></th>
            <td>
                <input name="ssseo_youtube_channel_id" type="text" id="ssseo_youtube_channel_id" class="regular-text" value="<?php echo esc_attr($channel_id); ?>">
                <p class="description">Channel ID (starts with UC...)</p>
            </td>
        </tr>
    </table>
    <p><input type="submit" name="ssseo_youtube_settings_submit" class="button button-primary" value="Save Settings"></p>
</form>

<hr>
<h2>Import Videos</h2>

<?php
if (!current_user_can('manage_options')) {
    echo '<p><em>You do not have permission to generate video posts.</em></p>';
} else {
    $nonce = wp_create_nonce('ssseo_generate_videos_nonce');
    ?>
    <button type="button" class="button button-primary ssseo-generate-videos-btn" data-nonce="<?php echo esc_attr($nonce); ?>">
        <?php esc_html_e('Fetch & Create Drafts', 'ssseo'); ?>
    </button>
    <p class="description">Click to fetch all videos from the channel and create Video CPT drafts (if they don’t already exist).</p>
    <div class="ssseo-generate-videos-result" style="margin-top:10px;"></div>
    <?php
}
?>
<?php
echo '<hr>';
echo '<h3>Debug: Current Settings</h3>';
echo '<p><strong>API Key:</strong> ' . esc_html($api_key) . '</p>';
echo '<p><strong>Channel ID:</strong> ' . esc_html($channel_id) . '</p>';
?>
<hr>
<h2>📌 Available Shortcodes</h2>
<ul>
  <li>
    <code>[youtube_with_transcript id="VIDEO_ID"]</code><br>
    <small>→ Displays a YouTube video with its transcript, if available.</small>
  </li>
  <li>
    <code>[youtube_channel_list max="6" paging="true"]</code><br>
    <small>→ Displays a list of videos from your channel with optional pagination and max items.</small>
  </li>
  <li>
    <code>[youtube_channel_list_detailed]</code><br>
    <small>→ Displays detailed video cards (thumbnail, title, excerpt, link).</small>
  </li>
</ul>

<p><strong>Note:</strong> Your channel ID and API key must be set above for these shortcodes to work.</p>

