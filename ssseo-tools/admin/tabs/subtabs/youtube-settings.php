<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$api_key    = get_option( 'ssseo_youtube_api_key', '' );
$channel_id = get_option( 'ssseo_youtube_channel_id', '' );
$enabled    = get_option( 'ssseo_enable_youtube', '1' );

if ( isset( $_POST['ssseo_youtube_settings_submit'] ) && current_user_can( 'manage_options' ) ) {
    check_admin_referer( 'ssseo_save_youtube_settings' );

    update_option( 'ssseo_youtube_api_key', sanitize_text_field( $_POST['ssseo_youtube_api_key'] ?? '' ) );
    update_option( 'ssseo_youtube_channel_id', sanitize_text_field( $_POST['ssseo_youtube_channel_id'] ?? '' ) );
    update_option( 'ssseo_enable_youtube', isset( $_POST['ssseo_enable_youtube'] ) ? '1' : '0' );

    echo '<div class="alert alert-success">Settings saved successfully.</div>';
}
?>

<form method="post" class="mb-5">
    <?php wp_nonce_field( 'ssseo_save_youtube_settings' ); ?>
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="ssseo_youtube_api_key" class="form-label">YouTube API Key</label>
            <input type="text" class="form-control" id="ssseo_youtube_api_key" name="ssseo_youtube_api_key"
                   value="<?php echo esc_attr( $api_key ); ?>" placeholder="AIza...">
            <div class="form-text">Enter your YouTube API key (starts with AIza...)</div>
        </div>
        <div class="col-md-6">
            <label for="ssseo_youtube_channel_id" class="form-label">YouTube Channel ID</label>
            <input type="text" class="form-control" id="ssseo_youtube_channel_id" name="ssseo_youtube_channel_id"
                   value="<?php echo esc_attr( $channel_id ); ?>" placeholder="UC...">
            <div class="form-text">Channel ID (starts with UC...)</div>
        </div>
    </div>

    <div class="mb-3 row">
        <label class="col-sm-3 col-form-label">Enable YouTube Integration</label>
        <div class="col-sm-9">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="ssseo_enable_youtube" name="ssseo_enable_youtube"
                       value="1" <?php checked( '1', $enabled ); ?> />
                <label class="form-check-label" for="ssseo_enable_youtube">Active</label>
            </div>
            <small class="form-text text-muted">
                Uncheck to disable all YouTube features (shortcodes, CPT, schema, AJAX).
            </small>
        </div>
    </div>

    <button type="submit" name="ssseo_youtube_settings_submit" class="btn btn-primary">Save Settings</button>
</form>

<hr class="my-4">

<h4 class="mb-3">Import Videos</h4>
<?php if ( current_user_can( 'manage_options' ) ) : ?>
    <button type="button" class="btn btn-success ssseo-full-import-btn"
            data-nonce="<?php echo esc_attr( wp_create_nonce( 'ssseo_batch_import_nonce' ) ); ?>">
        Fetch All Videos & Create Drafts
    </button>
    <p class="text-muted mt-2">
        This will fetch all videos from the channel and create Video CPT drafts (if they don’t already exist).
    </p>
    <div class="ssseo-video-import-log mt-3 border rounded p-3 bg-light text-sm"
         style="max-height:400px; overflow-y:auto; font-family: monospace;"></div>
<?php endif; ?>

