<?php
/**
 * SSSEO Tools – Card Grid Shortcode (Image/Icon Boxes, no flip; columns via CSS)
 *
 * Shortcode:
 *   [ssseo_card_grid button_text="Learn More" image_size="medium_large" use_icons="0" icon_class="bi bi-grid-3x3-gap"]
 *
 * Markup classes you can style:
 *   ssseo-grid, ssseo-flip-box, ssseo-card, card-media, flip-title, flip-excerpt, flip-button
 *
 * Precedence rule:
 *   For each top-level "service", if the current "service_area" has a child whose title
 *   starts with the service title (case-insensitive), show that child; otherwise show the service.
 */

namespace SSSEO\CardGrid;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Register shortcode (and alias for drop-in replacement) */
function bootstrap() {
	add_shortcode( 'ssseo_card_grid', __NAMESPACE__ . '\\shortcode' );
	add_shortcode( 'ssseo_flip_grid', __NAMESPACE__ . '\\shortcode' ); // alias
}
add_action( 'init', __NAMESPACE__ . '\\bootstrap' );

/** Render shortcode */
function shortcode( $atts ) {
	$atts = shortcode_atts( [
		'button_text' => 'Learn More',
		'image_size'  => 'medium_large',      // any registered WP size (thumbnail|medium|large|full|custom)
		'use_icons'   => '0',                 // "1" to render an icon if no image
		'icon_class'  => 'bi bi-grid-3x3-gap' // Bootstrap Icons class for fallback
	], $atts, 'ssseo_card_grid' );

	$current_id   = get_current_post_id();
	$is_sa_parent = $current_id && ( get_post_type( $current_id ) === 'service_area' );

	// Top-level Services
	$services = get_posts( [
		'post_type'        => 'service',
		'post_status'      => 'publish',
		'posts_per_page'   => -1,
		'post_parent'      => 0,
		'orderby'          => 'menu_order title',
		'order'            => 'ASC',
		'no_found_rows'    => true,
		'suppress_filters' => true,
	] );

	// Child service_area (of current service_area) if applicable
	$sa_children = [];
	if ( $is_sa_parent ) {
		$sa_children = get_children( [
			'post_parent' => $current_id,
			'post_type'   => 'service_area',
			'post_status' => 'publish',
			'orderby'     => 'menu_order title',
			'order'       => 'ASC',
		] );
	}

	$items = build_items_with_precedence( $services, $sa_children );
	if ( empty( $items ) ) return '';

	$use_icons = ( $atts['use_icons'] === '1' || $atts['use_icons'] === 1 );

	ob_start(); ?>
	<!-- Columns/Colors are controlled in CSS on .ssseo-grid and child classes -->
	<div class="ssseo-grid">
		<?php foreach ( $items as $post_obj ) :
			$post_id   = (int) $post_obj->ID;
			$title     = get_the_title( $post_id );
			$permalink = get_permalink( $post_id );
			$excerpt   = get_manual_excerpt_only( $post_id ); // blank if no manual excerpt
			$thumb     = get_card_image( $post_id, $atts['image_size'] );
		?>
		<div class="ssseo-flip-box"><!-- keeping class name per your spec -->
			<article class="ssseo-card">
				<?php if ( $thumb ) : ?>
					<a class="card-media" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
						<?php echo $thumb; /* core-escaped */ ?>
					</a>
				<?php elseif ( $use_icons ) : ?>
					<div class="card-media d-flex align-items-center justify-content-center">
						<i class="<?php echo esc_attr( $atts['icon_class'] ); ?>" aria-hidden="true"></i>
						<span class="visually-hidden"><?php echo esc_html( $title ); ?></span>
					</div>
				<?php endif; ?>

				<div class="card-body">
					<h3 class="flip-title">
						<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
					</h3>

					<?php if ( $excerpt !== '' ) : ?>
						<div class="flip-excerpt"><?php echo wp_kses_post( $excerpt ); ?></div>
					<?php endif; ?>

					<a class="flip-button btn" href="<?php echo esc_url( $permalink ); ?>">
						<?php echo esc_html( $atts['button_text'] ); ?>
					</a>
				</div>
			</article>
		</div>
		<?php endforeach; ?>
	</div>
	<?php
	return trim( ob_get_clean() );
}

/* ===================== Helpers ===================== */

function get_current_post_id() {
	if ( is_singular() ) {
		return (int) get_queried_object_id();
	}
	global $post;
	return isset( $post->ID ) ? (int) $post->ID : null;
}

/**
 * For each top-level Service S, if a child Service Area (of current SA parent)
 * has a title that starts with S's title (case-insensitive), use that child; else use S.
 */
function build_items_with_precedence( $services, $sa_children ) {
	if ( empty( $services ) ) return [];

	$used_child_ids = [];
	$result = [];

	foreach ( $services as $service ) {
		$svc_title = (string) ( $service->post_title ?? '' );
		$chosen    = $service;

		if ( $svc_title !== '' && ! empty( $sa_children ) ) {
			foreach ( $sa_children as $child ) {
				if ( in_array( $child->ID, $used_child_ids, true ) ) continue;
				if ( title_starts_with( (string) $child->post_title, $svc_title ) ) {
					$chosen = $child;
					$used_child_ids[] = (int) $child->ID;
					break;
				}
			}
		}
		$result[] = $chosen;
	}
	return $result;
}

/** Case-insensitive "starts with" */
function title_starts_with( $haystack, $needle ) {
	$h = preg_replace( '/\s+/', ' ', trim( (string) $haystack ) );
	$n = preg_replace( '/\s+/', ' ', trim( (string) $needle ) );
	if ( $n === '' ) return false;
	return stripos( $h, $n ) === 0;
}

/** Manual excerpt only (no auto content fallback). '' when none. */
function get_manual_excerpt_only( $post_id ) {
	$raw = get_post_field( 'post_excerpt', $post_id ); // no auto-gen
	$raw = is_string( $raw ) ? trim( $raw ) : '';
	if ( $raw === '' ) return '';
	$max = (int) apply_filters( 'ssseo/card_grid/excerpt_length', 24 );
	return wp_trim_words( $raw, max( 1, $max ) );
}

/** Featured image HTML or '' */
function get_card_image( $post_id, $size = 'medium_large' ) {
	return has_post_thumbnail( $post_id )
		? get_the_post_thumbnail( $post_id, $size, [ 'class' => 'img-fluid', 'alt' => get_the_title( $post_id ) ] )
		: '';
}

add_action( 'acf/include_fields', function() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
	'key' => 'group_68b69ccacb01f',
	'title' => 'Featured Icon',
	'fields' => array(
		array(
			'key' => 'field_68b69ccba9d34',
			'label' => 'Featured Icon',
			'name' => 'featured_icon',
			'aria-label' => '',
			'type' => 'image',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'return_format' => 'array',
			'library' => 'all',
			'min_width' => '',
			'min_height' => '',
			'min_size' => '',
			'max_width' => '',
			'max_height' => '',
			'max_size' => '',
			'mime_types' => '',
			'allow_in_bindings' => 0,
			'preview_size' => 'medium',
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'service',
			),
		),
		array(
			array(
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'service_area',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'normal',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => '',
	'active' => true,
	'description' => '',
	'show_in_rest' => 0,
) );
} );

