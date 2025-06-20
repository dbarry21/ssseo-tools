<?php
/**
 * Admin UI: Site Options Subtab (Under SSSEO Tools)
 * Version: 1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Handle form submission
if (
    isset( $_POST['ssseo_siteoptions_nonce'] ) &&
    wp_verify_nonce( $_POST['ssseo_siteoptions_nonce'], 'ssseo_siteoptions_save' ) &&
    current_user_can( 'manage_options' )
) {
    update_option( 'ssseo_enable_map_as_featured', isset( $_POST['ssseo_enable_map_as_featured'] ) ? '1' : '0' );

    update_option(
        'ssseo_google_static_maps_api_key',
        sanitize_text_field( $_POST['ssseo_google_static_maps_api_key'] ?? '' )
    );

    update_option(
        'ssseo_openai_api_key',
        sanitize_text_field( $_POST['ssseo_openai_api_key'] ?? '' )
    );

    update_option(
        'ssseo_ai_meta_prompt',
        sanitize_textarea_field( $_POST['ssseo_ai_meta_prompt'] ?? '' )
    );

    update_option(
        'ssseo_ai_title_prompt',
        sanitize_textarea_field( $_POST['ssseo_ai_title_prompt'] ?? '' )
    );

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Site Options saved.', 'ssseo' ) . '</p></div>';
}

// Fetch values
$enable_maps  = get_option( 'ssseo_enable_map_as_featured', '0' );
$maps_api     = get_option( 'ssseo_google_static_maps_api_key', '' );
$openai_key   = get_option( 'ssseo_openai_api_key', '' );

$default_meta_prompt  = "Write a concise, SEO-friendly meta description (max 160 characters) for a %s titled “%s”. Use this content as context:\n\n%s";
$default_title_prompt = "Write a concise, SEO-friendly title (max 60 characters) for a %s. Use this existing title “%s” and content as context:\n\n%s";

$meta_prompt  = get_option( 'ssseo_ai_meta_prompt', $default_meta_prompt );
$title_prompt = get_option( 'ssseo_ai_title_prompt', $default_title_prompt );
?>

<form method="post">
    <?php wp_nonce_field( 'ssseo_siteoptions_save', 'ssseo_siteoptions_nonce' ); ?>

    <h2><?php esc_html_e( 'Site Options', 'ssseo' ); ?></h2>
    <table class="form-table">

        <!-- Enable Google Maps as Featured -->
        <tr>
            <th scope="row"><label for="ssseo_enable_map_as_featured"><?php esc_html_e( 'Enable Google Maps as Featured Image', 'ssseo' ); ?></label></th>
            <td>
                <input type="checkbox" id="ssseo_enable_map_as_featured" name="ssseo_enable_map_as_featured" value="1" <?php checked( '1', $enable_maps ); ?>>
                <p class="description"><?php esc_html_e( 'Adds a “Generate Map” option to Service Area featured image box.', 'ssseo' ); ?></p>
            </td>
        </tr>

        <!-- Google Static Maps API Key -->
        <tr>
            <th><label for="ssseo_google_static_maps_api_key"><?php esc_html_e( 'Google Static Maps API Key', 'ssseo' ); ?></label></th>
            <td>
                <input type="text" id="ssseo_google_static_maps_api_key" name="ssseo_google_static_maps_api_key" value="<?php echo esc_attr( $maps_api ); ?>" class="regular-text">
                <p class="description"><?php esc_html_e( 'Used for generating featured static map images.', 'ssseo' ); ?></p>
            </td>
        </tr>

        <!-- OpenAI API Key -->
        <tr>
            <th><label for="ssseo_openai_api_key"><?php esc_html_e( 'OpenAI API Key', 'ssseo' ); ?></label></th>
            <td>
                <input type="text" id="ssseo_openai_api_key" name="ssseo_openai_api_key" value="<?php echo esc_attr( $openai_key ); ?>" class="regular-text">
                <p class="description"><?php esc_html_e( 'Used for AI-powered content generation features.', 'ssseo' ); ?></p>
            </td>
        </tr>

        <!-- Meta Description Prompt -->
        <tr>
            <th><label for="ssseo_ai_meta_prompt"><?php esc_html_e( 'Meta Description Prompt', 'ssseo' ); ?></label></th>
            <td>
                <textarea id="ssseo_ai_meta_prompt" name="ssseo_ai_meta_prompt" rows="5" class="large-text"><?php echo esc_textarea( $meta_prompt ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Use %s placeholders: (1) post type, (2) title, (3) content.', 'ssseo' ); ?></p>
            </td>
        </tr>

        <!-- Yoast Title Prompt -->
        <tr>
            <th><label for="ssseo_ai_title_prompt"><?php esc_html_e( 'Yoast Title Prompt', 'ssseo' ); ?></label></th>
            <td>
                <textarea id="ssseo_ai_title_prompt" name="ssseo_ai_title_prompt" rows="5" class="large-text"><?php echo esc_textarea( $title_prompt ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Use %s placeholders: (1) post type, (2) title, (3) content.', 'ssseo' ); ?></p>
            </td>
        </tr>

    </table>

    <p>
        <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Site Options', 'ssseo' ); ?>">
    </p>
</form>
