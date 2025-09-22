<?php
/**
 * Admin Tab: Shortcodes Reference (HTML Documentation)
 * File: admin/tabs/shortcodes.php
 *
 * Display-only reference for shortcodes defined across the plugin, including:
 *   - modules/map-embed-shortcode.php
 *   - modules/shortcodes-video.php   ← NEW (YouTube shortcodes live here)
 *   - /mnt/data/shortcodes.php
 *   - /mnt/data/shortcodes-card-grid.php
 *   - social-sharing.php (social share modal + icon trigger)
 *
 * This tab does not register shortcodes. It checks availability with shortcode_exists().
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** helpers */
$esc_code = function( $s ) { return esc_html( $s ); };
$exists_chip = function( $tag ) {
    $exists = shortcode_exists( $tag );
    $cls    = $exists ? 'ssseo-chip ok' : 'ssseo-chip warn';
    $txt    = $exists ? 'Active' : 'Not Registered';
    return '<span class="'.esc_attr($cls).'" title="'.esc_attr($txt).'">'.$txt.'</span>';
};

/** catalog */
$shortcodes = [

/** =========================
 * SOCIAL / SHARING
 * ========================= */
[
  'title'    => 'Social Share (Button + Modal)',
  'tag'      => 'social_share',
  'desc'     => 'Renders a primary Share button that opens a Bootstrap 5 modal with quick-share actions (Facebook, X/Twitter, WhatsApp, Email, Copy Link). This shortcode <em>automatically enqueues</em> Bootstrap 5 CSS/JS and Bootstrap Icons on the front end.<br><br><strong>Modal ID:</strong> <code>#socialShareModal</code>. Place the shortcode once per page (unique ID). You can also trigger the same modal from any custom link or button using <code>data-bs-toggle="modal"</code> and <code>data-bs-target="#socialShareModal"</code>.',
  'attrs'    => [
      'No attributes. Style the output via CSS classes included in the shortcode template.',
  ],
  'examples' => [
      '[social_share]',
      // additional guidance example (non-shortcode):
      'HTML trigger example: <a href="#" data-bs-toggle="modal" data-bs-target="#socialShareModal">Open Share Modal</a>',
  ],
  'returns'  => 'Outputs a <code>&lt;button class="btn btn-primary"&gt;</code> trigger and a centered modal (<code>.modal.fade</code>) with share icons. The modal copies the current page URL/title for sharing.'
],
[
  'title'    => 'Social Share (Icon-Only Trigger)',
  'tag'      => 'social_share_icon',
  'desc'     => 'Renders a compact square icon button (uses Elementor’s <code>--e-global-color-primary</code> for background) that opens the same Share modal (<code>#socialShareModal</code>). Good for headers, cards, or tight spaces. Automatically enqueues Bootstrap 5 + Icons.',
  'attrs'    => [
      'No attributes. Style the icon via the included <code>.social-icon-button</code> class or your theme CSS.',
  ],
  'examples' => [
      '[social_share_icon]',
  ],
  'returns'  => 'Outputs a minimal <code>&lt;button class="social-icon-button"&gt;</code> trigger and the same modal markup as <code>[social_share]</code>.'
],

/** =========================
 * VIDEO / YOUTUBE
 * ========================= */
[
  'title'    => 'YouTube – Single Video + Transcript',
  'tag'      => 'youtube_with_transcript',
  'desc'     => 'Embeds a YouTube video and (when available) displays a collapsible transcript. Uses the YouTube Data API for the description when a key is set in <strong>Site Options → YouTube</strong>.',
  'attrs'    => [
      ['url', 'string (YouTube URL)', '', 'Required. Any standard YouTube URL format (watch, share, embed, shorts).'],
  ],
  'examples' => [
      '[youtube_with_transcript url="https://www.youtube.com/watch?v=dQw4w9WgXcQ"]',
  ],
  'returns'  => 'Responsive 16:9 iframe, optional description, and a Bootstrap accordion titled “Transcript” (if caption tracks are public).'
],
[
  'title'    => 'YouTube – Channel Grid (AJAX Pager)',
  'tag'      => 'youtube_channel_list',
  'desc'     => 'Renders a responsive grid of recent channel uploads with “Watch Video” and “Read Description” modals, plus an optional pager. Reads the default Channel ID and API key from <strong>Site Options → YouTube</strong>. Front-end pagination is powered by <code>assets/js/ssseo-video.js</code>.',
  'attrs'    => [
      ['channel',  'string (Channel ID)', 'Saved default', 'YouTube Channel ID. If blank, uses the Site Options value.'],
      ['pagesize', 'int',                  '4',             'Items per page (first 50 uploads are available to the grid).'],
      ['max',      'int',                  '0',             'Upper limit of total items shown (0 = no cap; up to 50).'],
      ['paging',   'true|false',           'true',          'Enable pagination UI. When false, only the first slice is rendered.'],
  ],
  'examples' => [
      '[youtube_channel_list]',
      '[youtube_channel_list pagesize="6" max="24"]',
      '[youtube_channel_list channel="UC_x5XG1OV2P6uZZ5FSM9Ttw" paging="false"]',
  ],
  'returns'  => 'A card grid with Bootstrap modals for each item. If a matching “video” CPT exists (slug equals sanitized YouTube title or meta <code>_ssseo_video_id</code> matches), a “Visit Post” button is shown.'
],
[
  'title'    => 'YouTube – Detailed List (with transcript excerpts)',
  'tag'      => 'youtube_channel_list_detailed',
  'desc'     => 'Lists recent uploads with title, thumbnail, description, and best-effort transcript text (first available caption track). Good for SEO content blocks.',
  'attrs'    => [
      ['channel', 'string (Channel ID)', 'Saved default', 'YouTube Channel ID.'],
      ['max',     'int',                 '5',             'Maximum items to render (max 50).'],
  ],
  'examples' => [
      '[youtube_channel_list_detailed]',
      '[youtube_channel_list_detailed max="8"]',
  ],
  'returns'  => 'A Bootstrap grid of cards (<code>.card</code>) with video thumbnail, title, description, and transcript paragraphs when available.'
],

/** =========================
 * MAPS / EMBED
 * ========================= */
[
  'title'    => 'Maps Embed (Google Maps)',
  'tag'      => 'ssseo_map_embed',
  'desc'     => 'Embeds an interactive Google Map via the <em>Maps Embed API</em>. Uses the same API key stored in <strong>Site Options → Google Static Maps</strong>. The iframe always has <code>class="ssseo-map"</code> for easy CSS overrides.',
  'attrs'    => [
      ['q',          'string',           '',          'Place or coordinates (e.g., <code>Pasco County, FL</code> or <code>28.271,-82.457</code>). If empty, falls back to <code>field</code> or <code>geo_coordinates</code> / <code>city_state</code>.'],
      ['field',      'string',           '',          'Override source field from ACF or post meta (works with ACF Google Map and plain text).'],
      ['ratio',      '16x9|4x3|1x1|21x9|AxB|none', '16x9', 'Aspect ratio wrapper (Bootstrap 5). When set (default 16×9), the iframe auto-fits and <em>height</em> is ignored. Use <code>ratio="none"</code> to control height manually.'],
      ['width',      'CSS length/%',     '100%',      'When ratio is used, applies to the wrapper (e.g., <code>600px</code>, <code>75%</code>). If <code>ratio="none"</code>, it is the iframe width.'],
      ['height',     'CSS length/num',   '400',       'Only used when <code>ratio="none"</code>.'],
      ['class',      'string',           '',          'Extra classes appended to the iframe (which already includes <code>ssseo-map</code>).'],
      ['mode',       'place|directions|search|streetview', 'place', 'Embed mode.'],
      ['origin',     'string',           '',          'For <code>mode="directions"</code>.'],
      ['destination','string',           '',          'For <code>mode="directions"</code>; defaults to <code>q</code> when not provided.'],
      ['mode_drive', 'driving|walking|bicycling|transit', 'driving', 'Travel mode for directions.'],
      ['location',   'lat,lng',          '',          'For <code>mode="streetview"</code>; defaults to <code>q</code>.'],
      ['heading',    'number',           '',          'Street View heading.'],
      ['pitch',      'number',           '',          'Street View pitch.'],
      ['fov',        'number',           '',          'Street View field of view.'],
  ],
  'examples' => [
      '[ssseo_map_embed field="city_state"]',
      '[ssseo_map_embed q="Pasco County, FL" ratio="4x3" class="rounded-3 shadow-sm"]',
      '[ssseo_map_embed field="city_state" ratio="none" width="100%" height="380"]',
      '[ssseo_map_embed mode="directions" origin="Tampa, FL" field="city_state"]',
  ],
  'returns'  => 'Either <code>&lt;div class="ratio ratio-XXxYY"&gt;&lt;iframe class="ssseo-map"&gt;…</code> (when ratio is set) or a sized <code>&lt;iframe class="ssseo-map" width="…" height="…" …&gt;</code> when <code>ratio="none"</code>.'
],

/** =========================
 * SERVICE AREA GRID (MAP + EXCERPT)
 * ========================= */
[
  'title'    => 'Service Area Grid (Map + Excerpt, Alternating)',
  'tag'      => 'service_area_grid',
  'desc'     => 'Outputs Service Areas in alternating 2-column Bootstrap rows: one column shows a Google Map (via <code>[ssseo_map_embed]</code> using <code>field="city_state"</code>), the other shows the ACF <code>html_excerpt</code> (with shortcode support). On mobile it collapses to one column with the <em>map first</em>. Requires Bootstrap 5 CSS and your Maps Embed API key configured.',
  'attrs'    => [
      ['posts_per_page', 'int',                          '-1',              'How many posts to show (<code>-1</code>=all).'],
      ['parent_id',      'int|string',                   '',                'Filter by parent. Use <code>0</code> for top-level only; empty to show any parent.'],
      ['orderby',        'string (space/comma separated)','menu_order title','Sort keys (e.g., <code>menu_order title</code>).'],
      ['order',          'ASC|DESC',                     'ASC',             'Sort direction.'],
      ['class',          'string',                       '',                'Extra classes on outer <code>.container</code>.'],
      ['show_title',     '0|1',                          '1',               'Show the post title above the excerpt column.'],
      ['map_ratio',      '16x9|4x3|1x1|21x9|AxB|none',   '16x9',            'Aspect ratio passed to <code>[ssseo_map_embed]</code>. Use <code>none</code> to control height manually.'],
  ],
  'examples' => [
      '[service_area_grid]',
      '[service_area_grid parent_id="0" posts_per_page="12"]',
      '[service_area_grid map_ratio="4x3" class="py-5"]',
      '[service_area_grid orderby="menu_order title" order="ASC"]',
      '[service_area_grid show_title="1"]',
  ],
  'returns'  => 'A <code>&lt;div class="container ssseo-service-area-grid"&gt;</code> wrapping multiple rows (<code>.row.g-4.align-items-center.ssseo-row</code>). Each row contains two <code>.col-md-6</code> columns that alternate via <code>.order-*</code> classes. The map column renders <code>[ssseo_map_embed field="city_state" ratio="16x9" width="100%"]</code> (iframe has <code>class="ssseo-map"</code>); the text column renders the title (if enabled) and the processed <code>html_excerpt</code>.'
],

/** =========================
 * SERVICE AREA LISTS
 * ========================= */
[
  'title'    => 'All Top-Level Service Areas',
  'tag'      => 'service_area_list_all',
  'desc'     => 'Lists all root-level <code>service_area</code> posts (post_parent = 0) in alphabetical order. When placed on a single service_area, the current one is excluded.',
  'attrs'    => [],
  'examples' => ['[service_area_list_all]'],
  'returns'  => 'Bootstrap-friendly list inside <code>&lt;div class="container service-areas"&gt;</code> with <code>.service-area-list</code> items.'
],

[
  'title'    => 'Service Area → Roots + Direct Children',
  'tag'      => 'service_area_roots_children',
  'desc'     => 'Prints every top-level Service Area and, under each, its first-level children. Works anywhere (Home, pages, etc.).',
  'attrs'    => [
      ['hide_empty',    'yes|no', 'no', 'Skip roots that have no children when "yes".'],
      ['wrapper_class', 'string', '',   'Extra classes for the outer wrapper.'],
  ],
  'examples' => [
      '[service_area_roots_children]',
      '[service_area_roots_children hide_empty="yes" wrapper_class="py-3"]',
  ],
  'returns'  => 'Nested <code>&lt;ul&gt;</code> lists using classes <code>.service-area-list</code> and <code>.service-area-child</code>.'
],

[
  'title'    => 'Service Area → Direct Children of a Parent',
  'tag'      => 'service_area_children',
  'desc'     => 'On a single Service Area, lists its direct children. Or specify a <code>parent_id</code> to use anywhere.',
  'attrs'    => [
      ['parent_id',     'int',      '0',      'If 0 on a service_area single, uses the current post ID.'],
      ['orderby',       'string',   'title',  'E.g., title | menu_order | date.'],
      ['order',         'ASC|DESC', 'ASC',    'Sort direction.'],
      ['show_parent',   'yes|no',   'no',     'Show a parent heading/link above the list.'],
      ['wrapper_class', 'string',   '',       'Extra classes for the outer wrapper.'],
      ['list_class',    'string',   'list-unstyled service-area-list', 'Class for the <code>&lt;ul&gt;</code>.'],
      ['empty_text',    'string',   'No service areas found.',         'Shown if there are no children.'],
  ],
  'examples' => [
      '[service_area_children]',
      '[service_area_children parent_id="123" show_parent="yes" order="DESC"]',
  ],
  'returns'  => 'Unordered list of children inside <code>&lt;div class="container service-areas"&gt;</code>.'
],

/** =========================
 * CARD GRID / FLIP GRID
 * ========================= */
[
  'title'    => 'Card Grid (Services with smart Service-Area fallback)',
  'tag'      => 'ssseo_card_grid',
  'desc'     => 'Builds a responsive card grid of top-level <code>service</code> posts. If viewing a <code>service_area</code> parent and it has a child whose title begins with a given Service title, that child replaces the Service for better locality relevance.',
  'attrs'    => [
      ['button_text', 'string',        'Learn More',   'CTA label in the flip/back area.'],
      ['image_size',  'string',        'medium_large', 'Any registered WordPress image size.'],
      ['use_icons',   '0|1',           '0',            'If 1 and no image available, render <code>icon_class</code>.'],
      ['icon_class',  'string (class)','bi bi-grid-3x3-gap', 'Bootstrap Icons class name.'],
  ],
  'examples' => [
      '[ssseo_card_grid]',
      '[ssseo_card_grid button_text="Explore" image_size="large" use_icons="1" icon_class="bi bi-lightning"]',
  ],
  'returns'  => 'Cards within <code>.ssseo-grid</code> using <code>.ssseo-card</code>, <code>.card-media</code>, <code>.flip-title</code>, <code>.flip-excerpt</code>, <code>.flip-button</code>.'
],
[
  'title'    => 'Flip Grid (Alias of Card Grid)',
  'tag'      => 'ssseo_flip_grid',
  'desc'     => 'Alias of <code>[ssseo_card_grid]</code>. Accepts the same attributes/behavior.',
  'attrs'    => [
      ['button_text', 'string',        'Learn More',   'CTA label in the flip/back area.'],
      ['image_size',  'string',        'medium_large', 'Any registered WordPress image size.'],
      ['use_icons',   '0|1',           '0',            'If 1 and no image available, render <code>icon_class</code>.'],
      ['icon_class',  'string (class)','bi bi-grid-3x3-gap', 'Bootstrap Icons class name.'],
  ],
  'examples' => ['[ssseo_flip_grid]'],
  'returns'  => 'Same output classes as <code>[ssseo_card_grid]</code>.'
],

/** =========================
 * FAQ / CONTENT
 * ========================= */
[
  'title'    => 'FAQ Accordion (ACF)',
  'tag'      => 'faq_schema_accordion',
  'desc'     => 'Renders a Bootstrap 5 accordion from ACF repeater <code>faq_items</code> (sub-fields: <code>question</code>, <code>answer</code>).',
  'attrs'    => [],
  'examples' => ['[faq_schema_accordion]'],
  'returns'  => 'Accordion markup: <code>&lt;div class="accordion ssseo-accordion"&gt;</code> with <code>.accordion-button</code> and <code>.accordion-body</code>.'
],

[
  'title'    => 'About The Area (service_area meta)',
  'tag'      => 'about_the_area',
  'desc'     => 'Prints HTML from custom field <code>_about_the_area</code> on the current <code>service_area</code>. Shortcodes inside are processed.',
  'attrs'    => [],
  'examples' => ['[about_the_area]'],
  'returns'  => 'Raw HTML block.'
],

/** =========================
 * BLOG / LISTS
 * ========================= */
[
  'title'    => 'Category List (Bootstrap list-group)',
  'tag'      => 'ssseo_category_list',
  'desc'     => 'Outputs a Bootstrap list-group of post categories with optional filtering.',
  'attrs'    => [
      ['include_empty', '0|1', '0', 'Include categories with 0 posts when "1".'],
      ['min_count',     'int', '0', 'Only show categories with count ≥ this value.'],
  ],
  'examples' => [
      '[ssseo_category_list]',
      '[ssseo_category_list include_empty="1" min_count="3"]',
  ],
  'returns'  => '<code>&lt;div class="list-group ssseo"&gt;</code> of category links with a count badge.'
],

[
  'title'    => 'Blog Cards (Latest Posts Grid)',
  'tag'      => 'custom_blog_cards',
  'desc'     => 'Renders a responsive Bootstrap card grid of recent blog posts with pagination.',
  'attrs'    => [
      ['posts_per_page', 'int', '12', 'Number of posts per page.'],
  ],
  'examples' => [
      '[custom_blog_cards]',
      '[custom_blog_cards posts_per_page="6"]',
  ],
  'returns'  => 'Cards in a <code>.row</code> with <code>.col-md-4.mb-4</code>.'
],

/** =========================
 * LOCATION META SHORTCODES
 * ========================= */
[
  'title'    => 'City Only (from "City, ST")',
  'tag'      => 'city_only',
  'desc'     => 'Extracts the City portion from a combined field (default <code>city_state</code>). Can read from self, parent, or ancestor.',
  'attrs'    => [
      ['post_id',   'int',                 '0',          'Specific post ID; defaults to current post.'],
      ['from',      'self|parent|ancestor','self',       'Where to read from.'],
      ['field',     'string',              'city_state', 'Field/meta name.'],
      ['delimiter', 'string',              ',',          'Delimiter between City and State.'],
      ['fallback',  'string',              '',           'Text to show when empty.'],
  ],
  'examples' => ['[city_only]', '[city_only from="ancestor" fallback="Your Area"]'],
  'returns'  => 'Escaped City (e.g., "Tampa").'
],

[
  'title'    => 'City & State (normalize/case)',
  'tag'      => 'city_state',
  'desc'     => 'Reads a combined City/State from the given field with optional normalization.',
  'attrs'    => [
      ['post_id',     'int',                 '0',          'Specific post ID; defaults to current.'],
      ['from',        'self|parent|ancestor','self',       'Where to read from.'],
      ['field',       'string',              'city_state', 'Field/meta name.'],
      ['delimiter',   'string',              ',',          'Delimiter.'],
      ['normalize',   '0|1',                 '0',          'Force "City, ST" spacing.'],
      ['state_upper', '0|1',                 '0',          'Uppercase trailing 2-letter state.'],
      ['fallback',    'string',              '',           'Text to show when empty.'],
  ],
  'examples' => ['[city_state]', '[city_state normalize="1" state_upper="1"]'],
  'returns'  => 'Escaped "City, ST" (e.g., "Tampa, FL").'
],

];

/** =========================
 * RENDER
 * ========================= */
?>
<style>
  .ssseo-shortcodes * { box-sizing: border-box; }
  .ssseo-shortcodes h2 { margin: 0 0 8px; }
  .ssseo-shortcodes p  { margin: 0 0 12px; }
  .ssseo-chip {
    display:inline-block; font-size:12px; line-height:1; padding:4px 8px; border-radius:999px;
    margin-left:8px; vertical-align:middle; border:1px solid transparent;
  }
  .ssseo-chip.ok   { background:#e8fff1; color:#116a3e; border-color:#bfe8d0; }
  .ssseo-chip.warn { background:#fff4e5; color:#6a3a11; border-color:#f6d4a8; }
  .ssseo-card { border:1px solid #ececec; border-radius:10px; padding:16px; margin:16px 0; background:#fff; }
  .ssseo-card h3 { margin:0 0 8px; font-size:16px; }
  .ssseo-meta { font-size:12px; color:#666; margin-bottom:8px; }
  .ssseo-attrs table { width:100%; border-collapse: collapse; }
  .ssseo-attrs th, .ssseo-attrs td { border-bottom:1px solid #eee; padding:8px; text-align:left; }
  .ssseo-attrs th { font-size:12px; text-transform:uppercase; letter-spacing:0.02em; color:#555; }
  .ssseo-code { background:#0b1020; color:#e6f3ff; padding:10px 12px; border-radius:6px; overflow:auto; }
  .ssseo-code code { color:inherit; white-space:pre; display:block; }
</style>

<div class="ssseo-shortcodes">
  <h2>Available SSSEO Shortcodes</h2>
  <p>Copy/paste any of the following shortcodes into the Block Editor (Shortcode block), Classic Editor, or page builders. For PHP templates, wrap with <code>echo do_shortcode('[tag]');</code>.</p>

  <?php foreach ( $shortcodes as $sc ): ?>
    <div class="ssseo-card">
      <h3>
        <code>[<?php echo esc_html( $sc['tag'] ); ?>]</code>
        <?php echo $exists_chip( $sc['tag'] ); ?>
      </h3>

      <?php if ( ! empty( $sc['title'] ) ): ?>
        <div class="ssseo-meta"><?php echo wp_kses_post( $sc['title'] ); ?></div>
      <?php endif; ?>

      <?php if ( ! empty( $sc['desc'] ) ): ?>
        <p><?php echo wp_kses_post( $sc['desc'] ); ?></p>
      <?php endif; ?>

      <?php if ( ! empty( $sc['attrs'] ) ): ?>
        <div class="ssseo-attrs" style="margin-top:10px;">
          <table>
            <thead>
              <tr>
                <th>Attribute</th>
                <th>Type</th>
                <th>Default</th>
                <th>Description</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $sc['attrs'] as $a ): ?>
                <?php if ( is_array($a) ): 
                  $name = $a[0] ?? '';
                  $type = $a[1] ?? '';
                  $def  = $a[2] ?? '';
                  $desc = $a[3] ?? '';
                ?>
                  <tr>
                    <td><code><?php echo esc_html($name); ?></code></td>
                    <td><?php echo esc_html($type); ?></td>
                    <td><code><?php echo esc_html($def); ?></code></td>
                    <td><?php echo wp_kses_post($desc); ?></td>
                  </tr>
                <?php else: // string fallback for notes / no-attr messages ?>
                  <tr>
                    <td colspan="4"><?php echo wp_kses_post( $a ); ?></td>
                  </tr>
                <?php endif; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ( ! empty( $sc['examples'] ) ): ?>
        <div style="margin-top:10px;">
          <strong>Examples</strong>
          <div class="ssseo-code" style="margin-top:6px;">
            <code>
<?php
foreach ( $sc['examples'] as $ex ) {
  echo $esc_code( $ex ) . "\n";
}
?>
            </code>
          </div>
          <div style="margin-top:6px;">
            <em>PHP template:</em>
            <div class="ssseo-code" style="margin-top:6px;">
              <code><?php echo $esc_code( "echo do_shortcode('".$sc['examples'][0]."');" ); ?></code>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if ( ! empty( $sc['returns'] ) ): ?>
        <p style="margin-top:10px;"><strong>Output:</strong> <?php echo wp_kses_post( $sc['returns'] ); ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
