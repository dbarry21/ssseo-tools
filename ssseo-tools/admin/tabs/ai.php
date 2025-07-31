<?php

/**

 * Admin UI: AI Tab (Generate Meta Descriptions & Titles via ChatGPT)

 * Refactored for Bootstrap UI

 */



if (!defined('ABSPATH')) exit;

if (empty($_GET['tab']) || $_GET['tab'] !== 'ai') return;

add_action( 'admin_enqueue_scripts', 'ssseo_enqueue_ai_assets' );

function ssseo_enqueue_ai_assets( $hook ) {
    // Optional: limit only to our plugin page
    if ( isset($_GET['tab']) && $_GET['tab'] !== 'ai' ) return;

    wp_register_script(
        'ssseo-ai',
        plugin_dir_url(__FILE__) . 'assets/js/ssseo-ai.js',
        ['jquery'],
        '1.0.0',
        true
    );

    wp_enqueue_script('ssseo-ai');

    wp_localize_script('ssseo-ai', 'SSSEO_AI', [
        'nonce'    => wp_create_nonce('ssseo_ai_generate'),
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);
}


$post_types = get_post_types(['public' => true], 'objects');

$posts_by_type = [];

foreach ($post_types as $pt) {

    $all_posts = get_posts([

        'post_type'      => $pt->name,

        'posts_per_page' => -1,

        'post_status'    => 'publish',

        'orderby'        => 'title',

        'order'          => 'ASC',

    ]);

    foreach ($all_posts as $p) {

        $posts_by_type[$pt->name][] = [

            'id'    => $p->ID,

            'title' => $p->post_title,

        ];

    }

}

$default_pt = in_array('video', array_keys($posts_by_type), true) ? 'video' : array_key_first($posts_by_type);

?>



<div class="container mt-4">

  <h2>AI-Powered SEO Generators</h2>

  <p class="text-muted">

    Choose a post type, filter the list, then:<br>

    • Select individual posts and click <strong>"Generate & Save All Selected"</strong><br>

    • Or click <strong>"Generate & Save All of This Type"</strong><br>

    Results for both descriptions and titles will appear in the log below.

  </p>



  <div class="row mb-4">

    <div class="col-md-4">

      <label for="ssseo_ai_pt_filter" class="form-label">Filter by Post Type</label>

      <select id="ssseo_ai_pt_filter" class="form-select">

        <?php foreach ($post_types as $pt) : ?>

          <option value="<?php echo esc_attr($pt->name); ?>" <?php selected($pt->name, $default_pt); ?>>

            <?php echo esc_html($pt->labels->singular_name); ?>

          </option>

        <?php endforeach; ?>

      </select>

    </div>



    <div class="col-md-4">

      <label for="ssseo_ai_post_search" class="form-label">Search Posts</label>

      <input type="text" id="ssseo_ai_post_search" class="form-control" placeholder="Type to filter...">

    </div>



    <div class="col-md-4">
  <label for="ssseo_ai_post_id" class="form-label">Choose Post(s)</label>
<select id="ssseo_ai_post_id" name="ssseo_ai_post_id[]" multiple size="8" class="form-select">
  <option disabled>Loading…</option>
</select>


  <div class="form-text">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</div>
</div>




  <div class="mb-4">

    <h4>Meta Description Functions</h4>

    <button id="ssseo_ai_selected_generate" class="btn btn-primary me-2">Generate & Save Descriptions (Selected)</button>

    <button id="ssseo_ai_type_generate" class="btn btn-secondary">Generate & Save Descriptions (This Type)</button>

  </div>



  <div class="mb-4">

    <h4>Yoast Title Functions</h4>

    <button id="ssseo_ai_selected_generate_title" class="btn btn-primary me-2">Generate & Save Titles (Selected)</button>

    <button id="ssseo_ai_type_generate_title" class="btn btn-secondary">Generate & Save Titles (This Type)</button>

  </div>

  <div id="ssseo_ai_status" class="mb-3"></div>

<div class="col-12 mt-3">
  <button type="button" class="btn btn-primary" id="ssseo-generate-multiple-about">
    <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true" id="ssseo-about-spinner"></span>
    Generate About the Area (selected)
  </button>
</div>


<div class="col-12 mt-4" id="ssseo-about-area-status"></div>


  <h4>Results Log</h4>

  <div id="ssseo_ai_results" class="border p-3 bg-light" style="max-height:350px; overflow:auto;"></div>



  <h5 class="mt-4">Current Prompt (Meta Description)</h5>

  <pre class="bg-light p-3 border"><?php echo esc_html(get_option('ssseo_ai_meta_prompt', 'No prompt saved yet.')); ?></pre>



  <h5 class="mt-3">Current Prompt (SEO Title)</h5>

  <pre class="bg-light p-3 border"><?php echo esc_html(get_option('ssseo_ai_title_prompt', 'No prompt saved yet.')); ?></pre>

</div>



<script>

var ssseoPostsByType = <?php echo wp_json_encode($posts_by_type); ?>;

var ssseoDefaultType = "<?php echo esc_js($default_pt); ?>";

</script>

<hr class="my-5">



<h4>llms.txt Preview</h4>

<p class="text-muted">This is a live preview of the dynamically generated <code>llms.txt</code> file based on your current schema and settings.</p>

<a href="<?php echo esc_url( home_url('/llms.txt') ); ?>" target="_blank" class="btn btn-outline-primary mt-3">

    View Full llms.txt in New Tab

</a>



<?php

if (function_exists('ssseo_output_llms_to_string')) {

    echo '<pre style="background:#f9f9f9;border:1px solid #ccc;padding:15px;font-size:14px;max-height:500px;overflow:auto;">';

    echo esc_html(ssseo_output_llms_to_string());

    echo '</pre>';

} else {

    echo '<div class="alert alert-warning">llms.txt preview function not available.</div>';

}
