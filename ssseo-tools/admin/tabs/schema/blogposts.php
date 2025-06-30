<?php
/**
 * Admin UI: Blog Posts Schema Subtab (Under Schema)
 * Version: 1.4
 */

// Prevent direct access to the file for security
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ------------
// Saving logic (run before fetching $enabled)
// ------------
if (
    isset( $_POST['ssseo_article_schema_nonce'] ) &&
    wp_verify_nonce( $_POST['ssseo_article_schema_nonce'], 'ssseo_article_schema_save' ) &&
    current_user_can( 'manage_options' )
) {
    $new_value = isset( $_POST['ssseo_enable_article_schema'] ) ? '1' : '0';
    update_option( 'ssseo_enable_article_schema', $new_value );

    // Optional: Hook or log update event
    do_action( 'ssseo_article_schema_updated', $new_value );

    echo '<div class="updated"><p>Settings saved.</p></div>';
}

// Fetch the (possibly just‐updated) value
$enabled       = get_option( 'ssseo_enable_article_schema', '0' );
$active_subtab = $_GET['subtab'] ?? 'blogposts';
?>

<form method="post">
    <?php wp_nonce_field( 'ssseo_article_schema_save', 'ssseo_article_schema_nonce' ); ?>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="ssseo_enable_article_schema">Enable Article Schema for Blog Posts</label>
            </th>
            <td>
                <input
                    type="checkbox"
                    name="ssseo_enable_article_schema"
                    id="ssseo_enable_article_schema"
                    value="1"
                    <?php checked( '1', $enabled ); ?>
                >
                <p class="description">When enabled, article schema will be output for blog posts.</p>
            </td>
        </tr>
    </table>

    <p><input type="submit" class="button button-primary" value="Save Settings"></p>
</form>

<div class="notice notice-info" style="margin-top: 20px;">
    <p><strong>Note:</strong> This setting outputs schema for standard blog posts only. Video posts are excluded automatically.</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const accordionButtons = document.querySelectorAll('.accordion-button');

    accordionButtons.forEach((button, index) => {
        const labelMatch = button.innerText.match(/Location #[0-9]+/);
        const label = labelMatch ? labelMatch[0] : 'Location #' + (index + 1);

        const input = document.createElement('input');
        input.type = 'text';
        input.name = `ssseo_locations[${index}][name]`;
        input.className = 'regular-text';
        input.value = button.innerText.replace(/^Location #[0-9]+\s+[–-]\s+/, '').trim();
        input.style.marginLeft = '10px';

        button.appendChild(input);
    });
});
</script>
