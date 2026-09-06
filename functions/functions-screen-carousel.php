<?php
/**
 * Screen Image Carousel
 * =============================================================================
 * Modern, zero-dependency replacement for the old jQuery/Slick "hero-slideshow"
 * driven by the `screen_image` Meta Box field (an image_advanced field storing
 * one attachment ID per row).
 *
 * Render it from any template:
 *
 *     echo render_screen_carousel();          // uses the current post
 *     echo render_screen_carousel( $post_id ); // explicit post
 *
 * Behaviour:
 *   - Returns '' when the post has no screen_image (safe to call anywhere).
 *   - Emits real <figure><img srcset><figcaption> markup so it works with JS
 *     off; app/js/custom/screen-carousel.js enhances it into a carousel.
 *   - Pulls title / caption / description / alt straight from each image's
 *     media-library record for on-screen text + accessibility.
 *
 * Styling:  app/scss/partials/_screen-carousel.scss
 * Behaviour: app/js/custom/screen-carousel.js  (compiled into main[.min].js)
 * =============================================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the ordered, de-duped array of valid image attachment IDs stored in an
 * image_advanced meta field (each attachment is its own meta row).
 *
 * @param int    $post_id
 * @param string $meta_key e.g. 'screen_image', 'hero'
 * @return int[] Attachment IDs (may be empty).
 */
function get_image_field_ids( $post_id, $meta_key ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || '' === (string) $meta_key ) {
		return array();
	}

	$raw = get_post_meta( $post_id, $meta_key, false );

	// Flatten one level in case any value is itself an array (defensive).
	$ids = array();
	foreach ( (array) $raw as $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $v ) {
				$ids[] = (int) $v;
			}
		} else {
			$ids[] = (int) $value;
		}
	}

	// Drop zeros / non-images, preserve order, de-dupe.
	$ids = array_values( array_unique( array_filter( $ids ) ) );
	$ids = array_filter( $ids, static function ( $id ) {
		return wp_attachment_is_image( $id );
	} );

	return array_values( $ids );
}

/**
 * Ordered screen_image attachment IDs for a post.
 *
 * @param int $post_id
 * @return int[]
 */
function get_screen_image_ids( $post_id ) {
	return get_image_field_ids( $post_id, 'screen_image' );
}

/**
 * Ordered hero image attachment IDs for a post.
 *
 * @param int $post_id
 * @return int[]
 */
function get_hero_image_ids( $post_id ) {
	return get_image_field_ids( $post_id, 'hero' );
}

/**
 * Does this post have a screen_image carousel to show?
 *
 * @param int|null $post_id
 * @return bool
 */
function has_screen_carousel( $post_id = null ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	return ! empty( get_screen_image_ids( (int) $post_id ) );
}

/**
 * Render a carousel from an explicit set of image attachment IDs.
 *
 * Shared by the screen_image carousel and the multi-image hero. A single id
 * still renders (no controls, no autoplay) — so callers can use it for the
 * "1 image = static-ish, 2+ = slideshow" pattern.
 *
 * @param int[] $ids  Attachment IDs.
 * @param array $args 'autoplay' (ms, 0=off), 'size', 'variant' (modifier class,
 *                    e.g. 'hero' → .screen-carousel--hero), 'label' (aria-label).
 * @return string HTML, or '' when there are no ids.
 */
function render_image_carousel( $ids, $args = array() ) {
	$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
	if ( empty( $ids ) ) {
		return '';
	}

	$defaults = array(
		'autoplay' => 6000, // ms between slides; 0 disables.
		'size'     => 'large',
		'variant'  => '',
		'label'    => 'Image gallery',
	);
	$args  = wp_parse_args( $args, $defaults );
	$count = count( $ids );

	$section_classes = 'screen-carousel';
	if ( '' !== $args['variant'] ) {
		$section_classes .= ' screen-carousel--' . sanitize_html_class( $args['variant'] );
	}

	ob_start();
	?>
	<section class="<?php echo esc_attr( $section_classes ); ?>"
	         aria-roledescription="carousel"
	         aria-label="<?php echo esc_attr( $args['label'] ); ?>"
	         data-autoplay="<?php echo (int) $args['autoplay']; ?>">
		<noscript>
			<style>
				.screen-carousel__viewport,.screen-carousel__track{height:auto}
				.screen-carousel__slide{position:relative;inset:auto;opacity:1;visibility:visible;transform:none;height:clamp(320px,62vh,780px)}
				.screen-carousel__slide + .screen-carousel__slide{margin-top:4px}
				.screen-carousel__caption{opacity:1;transform:none}
				.screen-carousel__nav,.screen-carousel__dots{display:none}
			</style>
		</noscript>
		<div class="screen-carousel__viewport">
			<ul class="screen-carousel__track">
				<?php foreach ( $ids as $i => $id ) :
					$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
					$title       = trim( (string) get_the_title( $id ) );
					$caption     = trim( (string) wp_get_attachment_caption( $id ) );
					$description = trim( (string) get_post_field( 'post_content', $id ) );

					// Backdrop fill (blurred) — a mid size is plenty since it is blurred.
					$bg = wp_get_attachment_image_url( $id, 'medium_large' );
					if ( ! $bg ) {
						$bg = wp_get_attachment_image_url( $id, 'large' );
					}

					// Only show a text block when the editor actually authored one.
					// (Attachment titles default to the filename, so title alone is
					//  not treated as "has text".)
					$has_text = ( '' !== $caption || '' !== $description );

					$img = wp_get_attachment_image(
						$id,
						$args['size'],
						false,
						array(
							'class'    => 'screen-carousel__img',
							'alt'      => $alt, // may be '' — that is valid for decorative context
							'sizes'    => '100vw',
							'loading'  => ( 0 === $i ) ? 'eager' : 'lazy',
							'decoding' => 'async',
						)
					);
					?>
					<li class="screen-carousel__slide<?php echo ( 0 === $i ) ? ' is-active' : ''; ?>"
					    role="group"
					    aria-roledescription="slide"
					    aria-label="<?php echo esc_attr( ( $i + 1 ) . ' of ' . $count ); ?>"
					    style="--slide-bg:url('<?php echo esc_url( $bg ); ?>');">
						<div class="screen-carousel__backdrop" aria-hidden="true"></div>
						<figure class="screen-carousel__figure">
							<?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<?php if ( $has_text ) : ?>
								<figcaption class="screen-carousel__caption">
									<?php if ( '' !== $title ) : ?>
										<h3 class="screen-carousel__title"><?php echo esc_html( $title ); ?></h3>
									<?php endif; ?>
									<?php if ( '' !== $caption ) : ?>
										<p class="screen-carousel__excerpt"><?php echo esc_html( $caption ); ?></p>
									<?php endif; ?>
									<?php if ( '' !== $description ) : ?>
										<div class="screen-carousel__desc"><?php echo wp_kses_post( wpautop( $description ) ); ?></div>
									<?php endif; ?>
								</figcaption>
							<?php endif; ?>
						</figure>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<?php if ( $count > 1 ) : ?>
			<button class="screen-carousel__nav screen-carousel__nav--prev" type="button" aria-label="Previous image">
				<span aria-hidden="true">&#8249;</span>
			</button>
			<button class="screen-carousel__nav screen-carousel__nav--next" type="button" aria-label="Next image">
				<span aria-hidden="true">&#8250;</span>
			</button>
			<div class="screen-carousel__dots" role="group" aria-label="Choose image"></div>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Render the screen_image carousel for a post (thin wrapper over the shared core).
 *
 * @param int|null $post_id Defaults to the current post.
 * @param array    $args    See render_image_carousel().
 * @return string HTML, or '' when there are no screen images.
 */
function render_screen_carousel( $post_id = null, $args = array() ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	$post_id = (int) $post_id;

	$ids = get_screen_image_ids( $post_id );
	if ( empty( $ids ) ) {
		return '';
	}

	$label = get_the_title( $post_id );
	$label = $label ? $label . ' — image gallery' : 'Image gallery';

	return render_image_carousel( $ids, wp_parse_args( $args, array( 'label' => $label ) ) );
}
