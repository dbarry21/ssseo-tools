<?php
/**
 * SSSEO Tools – Card Grid Shortcode (Image/Icon Boxes; columns via CSS)
 *
 * Priority media source:
 *   1) ACF image field 'featured_icon' (array/id/url)
 *   2) Featured Image (post thumbnail)
 *   3) Optional icon (when use_icons="1")
 *
 * Shortcode:
 *   [ssseo_card_grid button_text="Learn More" image_size="medium_large" use_icons="0" icon_class="bi bi-grid-3x3-gap"]
 *
 * Class hooks you can style in CSS:
 *   ssseo-grid, ssseo-flip-box, ssseo-card, card-media, flip-title, flip-excerpt, flip-button
 *
 * Columns are controlled in your CSS on .ssseo-grid (e.g., CSS Grid repeat()).
 * Precedence rule kept:
 *   For each top-level "service", if the current "service_area" has a child whose title
 *   starts with the service title (case-insensitive), show that child; otherwise show the service.
 */

namespace SSSEO\CardGrid;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Register shortcode (and alias to replace older tag if used) */
function bootstrap() {
	add_shortcode( 'ssseo_card_grid', __NAMESPACE__ . '\\shortcode' );
	add_shortcode( 'ssseo_flip_grid', __NAMESPACE__ . '\\shortcode' ); // alias
}
add_action( 'init', __NAMESPACE__ . '\\bootstrap' );

/**
 * Render shortcode output.
 *
 * @param array $atts
 * @return string
 */
function shortcode( $atts ) {
	$atts = shortcode_atts( [
		'button_text' => 'Learn More',
		'image_size'  => 'medium_large',      // any registered WP size
		'use_icons'   => '0',                 // "1" to render an icon if no image available
		'icon_class'  => 'bi bi-grid-3x3-gap' // Bootstrap Icons class
	], $atts, 'ssseo_card_grid' );

	$current_id   = get_current_post_id();
	$is_sa_parent = $current_id && ( get_post_type( $current_id ) === 'service_area' );

	// 1) Top-level Services
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

	// 2) Child Service Areas of current Service Area (if applicable)
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

	// 3) Build final items per precedence
	$items = build_items_with_precedence( $services, $sa_children );
	if ( empty( $items ) ) return '';

	$use_icons = ( $atts['use_icons'] === '1' || $atts['use_icons'] === 1 );

	ob_start(); ?>
	<!-- Columns/colors handled by your CSS on .ssseo-grid -->
	<div class="ssseo-grid">
		<?php foreach ( $items as $post_obj ) :
			$post_id   = (int) $post_obj->ID;
			$title     = get_the_title( $post_id );
			$permalink = get_permalink( $post_id );
			$excerpt   = get_manual_excerpt_only( $post_id ); // blank if no manual excerpt

			// Build media HTML with priority: ACF 'featured_icon' → featured image → icon (optional).
			$media_html = get_priority_media_html( $post_id, $atts['image_size'], $title, $permalink, $use_icons, $atts['icon_class'] );
		?>
		<div class="ssseo-flip-box">
			<article class="ssseo-card">
				<?php
					// Media (clickable if image present)
					echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
				<div class="card-body">
					<h3 class="flip-title">
						<a href="<?php echo esc_url( $permalink ); ?>">
							<?php echo esc_html( $title ); ?>
						</a>
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

/** Get current post ID if available */
function get_current_post_id() {
	if ( is_singular() ) {
		return (int) get_queried_object_id();
	}
	global $post;
	return isset( $post->ID ) ? (int) $post->ID : null;
}

/**
 * Precedence:
 * For each top-level Service S, if a child Service Area (of current SA parent)
 * has a title that starts with S's title (case-insensitive), use that child; else use S.
 *
 * @param \WP_Post[] $services
 * @param \WP_Post[] $sa_children
 * @return \WP_Post[]
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

/**
 * Manual excerpt only (no auto content fallback). '' when none.
 *
 * @param int $post_id
 * @return string
 */
function get_manual_excerpt_only( $post_id ) {
	$raw = get_post_field( 'post_excerpt', $post_id ); // no auto-gen
	$raw = is_string( $raw ) ? trim( $raw ) : '';
	if ( $raw === '' ) return '';
	$max = (int) apply_filters( 'ssseo/card_grid/excerpt_length', 24 );
	return wp_trim_words( $raw, max( 1, $max ) );
}

/**
 * Return HTML for the media area with priority:
 *  - ACF field 'featured_icon' (array/id/url)
 *  - Featured image
 *  - Optional icon (if $allow_icon is true)
 *
 * @param int    $post_id
 * @param string $size        Registered image size
 * @param string $title       For alt text / aria labels
 * @param string $permalink   Link target
 * @param bool   $allow_icon  If true, show icon when no image found
 * @param string $icon_class  Bootstrap Icons class
 * @return string HTML
 */
function get_priority_media_html( $post_id, $size, $title, $permalink, $allow_icon, $icon_class ) {
	// 1) ACF 'featured_icon' (works for return formats: array, id, or url)
	$acf_img_html = get_acf_featured_icon_image_html( $post_id, $size, $title );
	if ( $acf_img_html ) {
		return sprintf(
			'<a class="card-media" href="%s" aria-label="%s">%s</a>',
			esc_url( $permalink ),
			esc_attr( $title ),
			$acf_img_html
		);
	}

	// 2) Featured image (post thumbnail)
	if ( has_post_thumbnail( $post_id ) ) {
		$thumb = get_the_post_thumbnail(
			$post_id,
			$size,
			[
				'class'   => 'img-fluid',
				'alt'     => esc_attr( $title ),
				'loading' => 'lazy',
				'decoding'=> 'async',
			]
		);
		if ( $thumb ) {
			return sprintf(
				'<a class="card-media" href="%s" aria-label="%s">%s</a>',
				esc_url( $permalink ),
				esc_attr( $title ),
				$thumb
			);
		}
	}

	// 3) Optional icon fallback
	if ( $allow_icon ) {
		return sprintf(
			'<div class="card-media d-flex align-items-center justify-content-center"><i class="%s" aria-hidden="true"></i><span class="visually-hidden">%s</span></div>',
			esc_attr( $icon_class ),
			esc_html( $title )
		);
	}

	return ''; // nothing to render
}

/**
 * Build an <img> tag for ACF 'featured_icon' if present.
 * Supports ACF return formats: 'array', 'id', 'url'.
 *
 * @param int    $post_id
 * @param string $size
 * @param string $title
 * @return string HTML or ''
 */
function get_acf_featured_icon_image_html( $post_id, $size, $title ) {
	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}

	$img = get_field( 'featured_icon', $post_id );
	if ( empty( $img ) ) {
		return '';
	}

	// ACF return formats handling
	if ( is_array( $img ) ) {
		// Expected: array with 'ID' and/or 'url'
		if ( ! empty( $img['ID'] ) ) {
			return wp_get_attachment_image(
				(int) $img['ID'],
				$size,
				false,
				[
					'class'   => 'img-fluid',
					'alt'     => $title,    // wp_get_attachment_image will escape
					'loading' => 'lazy',
					'decoding'=> 'async',
				]
			);
		}
		if ( ! empty( $img['url'] ) ) {
			$src = esc_url( $img['url'] );
			return sprintf(
				'<img class="img-fluid" src="%s" alt="%s" loading="lazy" decoding="async" />',
				$src,
				esc_attr( $title )
			);
		}
	} elseif ( is_numeric( $img ) ) {
		// Return format: id
		return wp_get_attachment_image(
			(int) $img,
			$size,
			false,
			[
				'class'   => 'img-fluid',
				'alt'     => $title,
				'loading' => 'lazy',
				'decoding'=> 'async',
			]
		);
	} elseif ( is_string( $img ) && filter_var( $img, FILTER_VALIDATE_URL ) ) {
		// Return format: url
		return sprintf(
			'<img class="img-fluid" src="%s" alt="%s" loading="lazy" decoding="async" />',
			esc_url( $img ),
			esc_attr( $title )
		);
	}

	return '';
}



