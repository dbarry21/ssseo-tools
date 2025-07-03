<?php
/**
 * Admin UI: Site Options (Bootstrap + API Test Buttons + Result Storage)
 * Version: 1.5
 */

if (!defined('ABSPATH')) exit;

// Handle form submission
if (
    isset($_POST['ssseo_siteoptions_nonce']) &&
    wp_verify_nonce($_POST['ssseo_siteoptions_nonce'], 'ssseo_siteoptions_save') &&
    current_user_can('manage_options')
) {
    update_option('ssseo_enable_map_as_featured', isset($_POST['ssseo_enable_map_as_featured']) ? '1' : '0');
    update_option('ssseo_google_static_maps_api_key', sanitize_text_field($_POST['ssseo_google_static_maps_api_key'] ?? ''));
    update_option('ssseo_openai_api_key', sanitize_text_field($_POST['ssseo_openai_api_key'] ?? ''));

    echo '<div class="alert alert-success">Site Options saved.</div>';
}

// Fetch values
$enable_maps  = get_option('ssseo_enable_map_as_featured', '0');
$maps_api     = get_option('ssseo_google_static_maps_api_key', '');
$openai_key   = get_option('ssseo_openai_api_key', '');

$last_maps_test   = get_option('ssseo_maps_test_result', 'No test run yet.');
$last_openai_test = get_option('ssseo_openai_test_result', 'No test run yet.');
?>

<form method="post" class="mt-4">
    <?php wp_nonce_field('ssseo_siteoptions_save', 'ssseo_siteoptions_nonce'); ?>

    <h2 class="mb-4">Site Options</h2>

    <!-- Enable Maps as Featured Image -->
    <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" id="ssseo_enable_map_as_featured" name="ssseo_enable_map_as_featured" value="1" <?php checked('1', $enable_maps); ?>>
        <label class="form-check-label" for="ssseo_enable_map_as_featured">
            Enable Google Maps as Featured Image
        </label>
        <div class="form-text">Adds a "Generate Map" option to Service Area featured image box.</div>
    </div>

    <!-- Google Maps API Key -->
    <div class="mb-4">
        <label for="ssseo_google_static_maps_api_key" class="form-label">Google Static Maps API Key</label>
        <div class="input-group">
            <input type="text" class="form-control" id="ssseo_google_static_maps_api_key" name="ssseo_google_static_maps_api_key" value="<?php echo esc_attr($maps_api); ?>" placeholder="AIza...">
            <button class="btn btn-outline-secondary" type="button" id="test-maps-api">Test</button>
        </div>
        <div class="form-text">Used for generating featured static map images.</div>
        <div class="form-text text-muted">Last test: <?php echo esc_html($last_maps_test); ?></div>
        <div id="maps-api-test-result" class="mt-2"></div>
    </div>

    <!-- OpenAI API Key -->
    <div class="mb-4">
        <label for="ssseo_openai_api_key" class="form-label">OpenAI API Key</label>
        <div class="input-group">
            <input type="text" class="form-control" id="ssseo_openai_api_key" name="ssseo_openai_api_key" value="<?php echo esc_attr($openai_key); ?>" placeholder="sk-...">
            <button class="btn btn-outline-secondary" type="button" id="test-openai-api">Test</button>
        </div>
        <div class="form-text">Used for AI-powered content generation features.</div>
        <div class="form-text text-muted">Last test: <?php echo esc_html($last_openai_test); ?></div>
        <div id="openai-api-test-result" class="mt-2"></div>
    </div>

    <!-- Submit Button -->
    <button type="submit" class="btn btn-primary">Save Site Options</button>
</form>
<script>
jQuery(function($) {
  $('#test-openai-api').on('click', function() {
    const key = $('#ssseo_openai_api_key').val();
    const resultBox = $('#openai-api-test-result').html('Testing...');

    $.post(ajaxurl, {
      action: 'ssseo_test_openai_key',
      key: key
    }, function(response) {
      resultBox.html(response.success
        ? '<span class="text-success">' + response.data + '</span>'
        : '<span class="text-danger">' + response.data + '</span>'
      );
    });
  });

  $('#test-maps-api').on('click', function() {
    const key = $('#ssseo_google_static_maps_api_key').val();
    const resultBox = $('#maps-api-test-result').html('Testing...');

    $.post(ajaxurl, {
      action: 'ssseo_test_maps_key',
      key: key
    }, function(response) {
      resultBox.html(response.success
        ? '<span class="text-success">' + response.data + '</span>'
        : '<span class="text-danger">' + response.data + '</span>'
      );
    });
  });
});
</script>

