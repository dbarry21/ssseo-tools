<?php
/**
 * Admin UI: Enable CPT Tab (Service, Service Area, Product) – Bootstrap Toggle Version
 */

if (!defined('ABSPATH')) exit;

// ---------------------------
// Handle form submission
// ---------------------------
if (
    isset($_POST['ssseo_cpt_schema_nonce']) &&
    wp_verify_nonce($_POST['ssseo_cpt_schema_nonce'], 'ssseo_cpt_schema_save') &&
    current_user_can('manage_options')
) {
    $fields = [
        'service'      => 'ssseo_enable_service_cpt',
        'service_area' => 'ssseo_enable_service_area_cpt',
        'product'      => 'ssseo_enable_product_cpt',
    ];

    foreach ($fields as $key => $field) {
        $enabled     = isset($_POST[$field]) ? '1' : '0';
        $has_archive = sanitize_text_field($_POST["{$field}_hasarchive"] ?? '');
        $slug        = sanitize_text_field($_POST["{$field}_slug"] ?? '');

        update_option($field, $enabled);
        update_option("{$field}_hasarchive", $has_archive);
        update_option("{$field}_slug", $slug);
    }

    flush_rewrite_rules(); // ✅ Ensure updated slugs/archives are registered

    do_action('ssseo_cpt_settings_updated');
    echo '<div class="alert alert-success mt-3">Settings saved successfully.</div>';
}

// ---------------------------
// Load stored settings
// ---------------------------
$fields = [
    'service'      => 'ssseo_enable_service_cpt',
    'service_area' => 'ssseo_enable_service_area_cpt',
    'product'      => 'ssseo_enable_product_cpt',
];

$settings = [];

foreach ($fields as $key => $field) {
    $settings[$key] = [
        'enabled'     => get_option($field, '0'), // default unchecked
        'has_archive' => get_option("{$field}_hasarchive", ''),
        'slug'        => get_option("{$field}_slug", ''),
    ];
}
?>

<form method="post" class="container-fluid mt-4">
    <?php wp_nonce_field('ssseo_cpt_schema_save', 'ssseo_cpt_schema_nonce'); ?>

    <div class="row">
        <?php foreach ($fields as $key => $field_id):
            $label = ucwords(str_replace('_', ' ', $key));
            $s = $settings[$key];
        ?>
        <div class="col-md-4">
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <strong><?php echo esc_html($label); ?> CPT</strong>
                </div>
                <div class="card-body">

                    <div class="form-check form-switch mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="<?php echo $field_id; ?>"
                            name="<?php echo $field_id; ?>"
                            value="1"
                            <?php checked('1', $s['enabled']); ?>
                        >
                        <label class="form-check-label" for="<?php echo $field_id; ?>">
                            Enable "<?php echo esc_html($label); ?>" CPT
                        </label>
                    </div>

                    <div class="mb-3">
                        <label for="<?php echo $field_id; ?>_hasarchive" class="form-label">Has Archive</label>
                        <input
                            type="text"
                            class="form-control"
                            id="<?php echo $field_id; ?>_hasarchive"
                            name="<?php echo $field_id; ?>_hasarchive"
                            value="<?php echo esc_attr($s['has_archive']); ?>"
                            placeholder="e.g. services"
                        >
                        <div class="form-text">Leave blank to disable archive support.</div>
                    </div>

                    <div class="mb-3">
                        <label for="<?php echo $field_id; ?>_slug" class="form-label">Slug</label>
                        <input
                            type="text"
                            class="form-control"
                            id="<?php echo $field_id; ?>_slug"
                            name="<?php echo $field_id; ?>_slug"
                            value="<?php echo esc_attr($s['slug']); ?>"
                            placeholder="e.g. service"
                        >
                        <div class="form-text">Enter the CPT URL slug (no slashes).</div>
                    </div>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <p>
        <input type="submit" class="btn btn-primary" value="Save Settings">
    </p>
</form>
