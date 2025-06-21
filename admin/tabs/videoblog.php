<?php
if (!defined('ABSPATH')) exit;

echo '<h2 class="mb-4">YouTube Integration</h2>';

// Save Settings
if (isset($_POST['ssseo_youtube_settings_submit'])) {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('ssseo_save_youtube_settings');

    update_option('ssseo_youtube_api_key', sanitize_text_field($_POST['ssseo_youtube_api_key'] ?? ''));
    update_option('ssseo_youtube_channel_id', sanitize_text_field($_POST['ssseo_youtube_channel_id'] ?? ''));

    echo '<div class="alert alert-success">Settings saved successfully.</div>';
}

// Retrieve options
$api_key = get_option('ssseo_youtube_api_key', '');
$channel_id = get_option('ssseo_youtube_channel_id', '');
?>

<form method="post" class="mb-5">
    <?php wp_nonce_field('ssseo_save_youtube_settings'); ?>

    <div class="row mb-3">
        <div class="col-md-6">
            <label for="ssseo_youtube_api_key" class="form-label">YouTube API Key</label>
            <input type="text" class="form-control" id="ssseo_youtube_api_key" name="ssseo_youtube_api_key" value="<?php echo esc_attr($api_key); ?>" placeholder="AIza...">
            <div class="form-text">Enter your YouTube API key (starts with AIza...)</div>
        </div>
        <div class="col-md-6">
            <label for="ssseo_youtube_channel_id" class="form-label">YouTube Channel ID</label>
            <input type="text" class="form-control" id="ssseo_youtube_channel_id" name="ssseo_youtube_channel_id" value="<?php echo esc_attr($channel_id); ?>" placeholder="UC...">
            <div class="form-text">Channel ID (starts with UC...)</div>
        </div>
    </div>

    <button type="submit" name="ssseo_youtube_settings_submit" class="btn btn-primary">Save Settings</button>
</form>

<hr class="my-4">

<h2 class="mb-3">Import Videos</h2>
<?php if (!current_user_can('manage_options')) : ?>
    <div class="alert alert-warning">You do not have permission to generate video posts.</div>
<?php else : ?>
    <?php $nonce = wp_create_nonce('ssseo_generate_videos_nonce'); ?>
    <button type="button" class="btn btn-success ssseo-generate-videos-btn" data-nonce="<?php echo esc_attr($nonce); ?>">
        <?php esc_html_e('Fetch & Create Drafts', 'ssseo'); ?>
    </button>
    <p class="text-muted mt-2">Click to fetch all videos from the channel and create Video CPT drafts (if they don’t already exist).</p>
    <div class="ssseo-generate-videos-result mt-3"></div>
<?php endif; ?>

<hr class="my-4">

<h3>Debug: Current Settings</h3>
<ul class="list-group mb-4">
    <li class="list-group-item"><strong>API Key:</strong> <?php echo esc_html($api_key); ?></li>
    <li class="list-group-item"><strong>Channel ID:</strong> <?php echo esc_html($channel_id); ?></li>
</ul>

<h2 class="mb-3">📌 Available Shortcodes</h2>
<ul class="list-group list-group-flush">
    <li class="list-group-item">
        <code>[youtube_with_transcript id="VIDEO_ID"]</code><br>
        <small class="text-muted">→ Displays a YouTube video with its transcript, if available.</small>
    </li>
    <li class="list-group-item">
        <code>[youtube_channel_list max="6" paging="true"]</code><br>
        <small class="text-muted">→ Displays a list of videos from your channel with optional pagination and max items.</small>
    </li>
    <li class="list-group-item">
        <code>[youtube_channel_list_detailed]</code><br>
        <small class="text-muted">→ Displays detailed video cards (thumbnail, title, excerpt, link).</small>
    </li>
</ul>

<p class="mt-3"><strong>Note:</strong> Your channel ID and API key must be set above for these shortcodes to work.</p>
