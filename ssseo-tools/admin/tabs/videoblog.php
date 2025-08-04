<?php


if ( ! defined( 'ABSPATH' ) ) {


    exit;


}





echo '<h2 class="mb-4">YouTube Integration</h2>';





// --- Save Settings ---


if ( isset( $_POST['ssseo_youtube_settings_submit'] ) ) {


    if ( ! current_user_can( 'manage_options' ) ) {


        wp_die( 'Unauthorized' );


    }


    check_admin_referer( 'ssseo_save_youtube_settings' );





    update_option( 'ssseo_youtube_api_key', sanitize_text_field( wp_unslash( $_POST['ssseo_youtube_api_key'] ?? '' ) ) );


    update_option( 'ssseo_youtube_channel_id', sanitize_text_field( wp_unslash( $_POST['ssseo_youtube_channel_id'] ?? '' ) ) );





    // Save the new “enable” checkbox


    $enable = isset( $_POST['ssseo_enable_youtube'] ) ? '1' : '0';


    update_option( 'ssseo_enable_youtube', $enable );





    echo '<div class="alert alert-success">Settings saved successfully.</div>';


}





// --- Load current values ---


$api_key    = get_option( 'ssseo_youtube_api_key', '' );


$channel_id = get_option( 'ssseo_youtube_channel_id', '' );


$enabled    = get_option( 'ssseo_enable_youtube', '1' );


?>





<form method="post" class="mb-5">


    <?php wp_nonce_field( 'ssseo_save_youtube_settings' ); ?>





    <div class="row mb-3">


        <div class="col-md-6">


            <label for="ssseo_youtube_api_key" class="form-label">YouTube API Key</label>


            <input


              type="text"


              class="form-control"


              id="ssseo_youtube_api_key"


              name="ssseo_youtube_api_key"


              value="<?php echo esc_attr( $api_key ); ?>"


              placeholder="AIza..."


            >


            <div class="form-text">Enter your YouTube API key (starts with AIza...)</div>


        </div>


        <div class="col-md-6">


            <label for="ssseo_youtube_channel_id" class="form-label">YouTube Channel ID</label>


            <input


              type="text"


              class="form-control"


              id="ssseo_youtube_channel_id"


              name="ssseo_youtube_channel_id"


              value="<?php echo esc_attr( $channel_id ); ?>"


              placeholder="UC..."


            >


            <div class="form-text">Channel ID (starts with UC...)</div>


        </div>


    </div>





    <div class="mb-3 row">


        <label for="ssseo_enable_youtube" class="col-sm-3 col-form-label">


            <?php esc_html_e( 'Enable YouTube Integration', 'ssseo' ); ?>


        </label>


        <div class="col-sm-9">


            <div class="form-check">


                <input


                  class="form-check-input"


                  type="checkbox"


                  id="ssseo_enable_youtube"


                  name="ssseo_enable_youtube"


                  value="1"


                  <?php checked( '1', $enabled ); ?>


                />


                <label class="form-check-label" for="ssseo_enable_youtube">


                    <?php esc_html_e( 'Active', 'ssseo' ); ?>


                </label>


            </div>


            <small class="form-text text-muted">


                <?php esc_html_e( 'Uncheck to disable all YouTube video features (shortcodes, CPT, schema, AJAX).', 'ssseo' ); ?>


            </small>


        </div>


    </div>





    <button type="submit" name="ssseo_youtube_settings_submit" class="btn btn-primary">


      <?php esc_html_e( 'Save Settings', 'ssseo' ); ?>


    </button>


</form>





<hr class="my-4">

<hr class="my-4">

<h2 class="mb-3">Import Videos</h2>

<?php if ( ! current_user_can( 'manage_options' ) ) : ?>
    <div class="alert alert-warning">You do not have permission to generate video posts.</div>
<?php else : ?>
    <?php $full_nonce = wp_create_nonce( 'ssseo_batch_import_nonce' ); ?>
    <button
      type="button"
      class="btn btn-success ssseo-full-import-btn"
      data-nonce="<?php echo esc_attr( $full_nonce ); ?>"
    >
      Fetch All Videos & Create Drafts
    </button>

    <p class="text-muted mt-2">
      This will fetch all videos from the channel and create Video CPT drafts (if they don’t already exist).
    </p>

    <div class="ssseo-video-import-log mt-3 border rounded p-3 bg-light text-sm" style="max-height:400px; overflow-y:auto; font-family: monospace;"></div>
<?php endif; ?>

<script>
jQuery(document).ready(function($) {
  $('.ssseo-full-import-btn').on('click', function(e) {
    e.preventDefault();

    const $btn = $(this);
    const nonce = $btn.data('nonce');
    const $log = $('.ssseo-video-import-log');

    $btn.prop('disabled', true).text('Importing...');
    $log.empty().append('<div>Starting full import...</div>');

    $.post(ajaxurl, {
      action: 'ssseo_batch_import_videos',
      nonce: nonce
    }).done(function(response) {
      if (response.success && response.data && response.data.log) {
        response.data.log.forEach(line => {
          $log.append('<div>' + line + '</div>');
        });
        $log.append('<div class="mt-2 text-success fw-bold">✅ ' + response.data.message + '</div>');
      } else {
        $log.append('<div class="text-danger mt-2">❌ ' + (response.data?.error || 'Unknown error') + '</div>');
      }
    }).fail(function() {
      $log.append('<div class="text-danger mt-2">❌ AJAX failed.</div>');
    }).always(function() {
      $btn.prop('disabled', false).text('Fetch All Videos & Create Drafts');
    });
  });
});
</script>





