<?php
/**
 * Hero Video
 * =============================================================================
 * Reusable hero video player driven by the `hero_video` Meta Box field
 * (type: video — stores an attachment ID). Call from any template:
 *
 *     echo render_hero_video();          // current post
 *     echo render_hero_video( $post_id ); // explicit post
 *
 * Behaviour:
 *   - Returns '' when the post has no hero_video (safe to call anywhere).
 *   - Autoplays, muted, looped, inline (muted is REQUIRED for browsers to allow
 *     autoplay). Uses the Hero Image as a poster when one is set.
 *   - Intended to supersede the hero image; a screen_image carousel can render
 *     below it (see header.php).
 *
 * Styling: app/scss/partials/_hero-video.scss
 * =============================================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * First valid hero_video attachment ID for a post (0 if none).
 *
 * @param int $post_id
 * @return int
 */
function get_hero_video_id( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return 0;
	}

	$raw = get_post_meta( $post_id, 'hero_video', false );
	foreach ( (array) $raw as $value ) {
		$id = is_array( $value ) ? (int) reset( $value ) : (int) $value;
		if ( $id && wp_attachment_is( 'video', $id ) ) {
			return $id;
		}
	}
	return 0;
}

/**
 * Does this post have a hero video?
 *
 * @param int|null $post_id
 * @return bool
 */
function has_hero_video( $post_id = null ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	return (bool) get_hero_video_id( (int) $post_id );
}

/**
 * Render the hero video markup.
 *
 * @param int|null $post_id Defaults to the current post.
 * @param array    $args    Optional: 'autoplay','loop','muted','controls' (bool),
 *                          'poster' (url; '' to omit, null = auto from Hero Image).
 * @return string HTML, or '' when there is no hero video.
 */
function render_hero_video( $post_id = null, $args = array() ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	$post_id = (int) $post_id;

	$id = get_hero_video_id( $post_id );
	if ( ! $id ) {
		return '';
	}
	$src = wp_get_attachment_url( $id );
	if ( ! $src ) {
		return '';
	}

	$defaults = array(
		'autoplay' => true,
		'loop'     => true,
		'muted'    => true,
		'controls' => false,
		'poster'   => null, // null => auto from Hero Image
	);
	$args = wp_parse_args( $args, $defaults );

	// Auto poster from the Hero Image field, if present.
	$poster = $args['poster'];
	if ( null === $poster ) {
		$hero = get_post_meta( $post_id, 'hero', true );
		$hero = is_array( $hero ) ? reset( $hero ) : $hero;
		$poster = $hero ? (string) wp_get_attachment_image_url( (int) $hero, 'large' ) : '';
	}

	$mime  = get_post_mime_type( $id );
	$label = get_the_title( $post_id );
	$label = $label ? $label . ' — hero video' : 'Hero video';

	$attrs = 'playsinline preload="auto"';
	if ( $args['autoplay'] ) { $attrs .= ' autoplay'; }
	if ( $args['loop'] ) { $attrs .= ' loop'; }
	if ( $args['muted'] ) { $attrs .= ' muted'; }        // required for autoplay
	if ( $args['controls'] ) { $attrs .= ' controls'; }

	ob_start();
	?>
	<section class="hero-video" aria-label="<?php echo esc_attr( $label ); ?>">
		<video class="hero-video__media"
		       <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		       <?php if ( $poster ) : ?>poster="<?php echo esc_url( $poster ); ?>"<?php endif; ?>>
			<source src="<?php echo esc_url( $src ); ?>"<?php echo $mime ? ' type="' . esc_attr( $mime ) . '"' : ''; ?> />
		</video>
	</section>
	<?php
	return ob_get_clean();
}
