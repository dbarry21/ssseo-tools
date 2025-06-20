<?php
/**
 * Admin UI: AI Tab (Generate Meta Descriptions & Titles via ChatGPT)
 *
 * Only outputs when ?page=ssseo-tools&tab=ai.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Bail if this isn’t the “AI” tab
if ( empty( $_GET['tab'] ) || $_GET['tab'] !== 'ai' ) {
    return;
}

// 1) Get all public post types
$post_types = get_post_types( [ 'public' => true ], 'objects' );

// 2) Gather all published posts per type (ID + title)
$posts_by_type = [];
foreach ( $post_types as $pt ) {
    $all_posts = get_posts( [
        'post_type'      => $pt->name,
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );
    $arr = [];
    foreach ( $all_posts as $p ) {
        $arr[] = [
            'id'    => $p->ID,
            'title' => $p->post_title,
        ];
    }
    $posts_by_type[ $pt->name ] = $arr;
}

// Determine a “default” post type—use 'video' if available, otherwise first public type
$default_pt = in_array( 'video', array_keys( $posts_by_type ), true )
    ? 'video'
    : array_key_first( $posts_by_type );
?>

<h2><?php esc_html_e( 'AI-Powered SEO Generators', 'ssseo' ); ?></h2>
<p>
  <?php
    esc_html_e(
      'Choose a post type, filter the list below, then either:<br>
       • Select individual posts and click “Generate & Save All Selected” for meta descriptions or titles.<br>
       • Or click “Generate & Save All of This Type” to process every post of the chosen post type.<br>
       Results for both descriptions and titles will appear in the same log below.',
      'ssseo'
    );
  ?>
</p>

<table class="form-table">
  <!-- Post Type Selector -->
  <tr>
    <th><label for="ssseo_ai_pt_filter"><?php esc_html_e( 'Filter by Post Type', 'ssseo' ); ?></label></th>
    <td>
      <select id="ssseo_ai_pt_filter" style="width:100%; max-width:300px;">
        <?php foreach ( $post_types as $pt ) : ?>
          <option
            value="<?php echo esc_attr( $pt->name ); ?>"
            <?php selected( $pt->name, $default_pt ); ?>
          >
            <?php echo esc_html( $pt->labels->singular_name ); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </td>
  </tr>

  <!-- Search Box -->
  <tr>
    <th><label for="ssseo_ai_post_search"><?php esc_html_e( 'Search Posts', 'ssseo' ); ?></label></th>
    <td>
      <input
        type="text"
        id="ssseo_ai_post_search"
        placeholder="<?php esc_attr_e( 'Type to filter…', 'ssseo' ); ?>"
        style="width:100%; max-width:400px; margin-bottom:8px;"
      >
    </td>
  </tr>

  <!-- Multi‐Select Posts -->
  <tr>
    <th><label for="ssseo_ai_post_id"><?php esc_html_e( 'Choose Post(s)', 'ssseo' ); ?></label></th>
    <td>
      <select
        id="ssseo_ai_post_id"
        name="ssseo_ai_post_id[]"
        multiple
        size="8"
        style="width:100%; max-width:400px;"
      >
        <!-- JS will inject options -->
      </select>
      <p class="description"><?php esc_html_e( 'Hold Ctrl (Windows) or Cmd (Mac) to select multiple.', 'ssseo' ); ?></p>
    </td>
  </tr>
</table>

<h3><?php esc_html_e( 'Meta Description Functions', 'ssseo' ); ?></h3>
<div class="ssseo-ai-functions" style="margin-bottom:20px;">
  <button id="ssseo_ai_selected_generate"     class="button button-primary">
    <?php esc_html_e( 'Generate & Save Descriptions (Selected)', 'ssseo' ); ?>
  </button>
  <button id="ssseo_ai_type_generate"         class="button button-secondary">
    <?php esc_html_e( 'Generate & Save Descriptions (This Type)', 'ssseo' ); ?>
  </button>
</div>

<h3><?php esc_html_e( 'Yoast Title Functions', 'ssseo' ); ?></h3>
<div class="ssseo-ai-title-functions" style="margin-bottom:20px;">
  <button id="ssseo_ai_selected_generate_title" class="button button-primary">
    <?php esc_html_e( 'Generate & Save Titles (Selected)', 'ssseo' ); ?>
  </button>
  <button id="ssseo_ai_type_generate_title"     class="button button-secondary">
    <?php esc_html_e( 'Generate & Save Titles (This Type)', 'ssseo' ); ?>
  </button>
</div>

<span id="ssseo_ai_status" style="margin-left:0; display:block; margin-bottom:10px;"></span>

<h3><?php esc_html_e( 'Results Log', 'ssseo' ); ?></h3>
<div
  id="ssseo_ai_results"
  style="padding:10px; border:1px solid #ccc;
         max-height:350px; overflow:auto; background:#f9f9f9;"
>
  <!-- Log lines appear here -->
</div>
<h3>Current Prompt (Meta Description)</h3>
<pre style="background:#f8f8f8; padding:10px; border:1px solid #ccc; white-space:pre-wrap;">
<?php echo esc_html( get_option( 'ssseo_ai_meta_prompt', 'No prompt saved yet.' ) ); ?>
</pre>
<h3>Current Prompt (SEO Title)</h3>
<pre style="background:#f8f8f8; padding:10px; border:1px solid #ccc; white-space:pre-wrap;">
<?php echo esc_html( get_option( 'ssseo_ai_title_prompt', 'No prompt saved yet.' ) ); ?>
</pre>

<!-- 3) Make the PHP array of posts_by_type available to JS -->
<script>
var ssseoPostsByType = <?php echo wp_json_encode( $posts_by_type ); ?>;
var ssseoDefaultType = "<?php echo esc_js( $default_pt ); ?>";
</script>
