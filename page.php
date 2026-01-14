<?php
/**
 * Template Name: Default Page
 * 
 * The template for displaying standard pages.
 * This is the canonical wrapper for pages that don't have a custom template.
 * 
 * Brand detection is handled automatically via polys_get_current_brand() in header.php.
 * URL-based brand resolution:
 *   /the-polys/*        → polys
 *   /metatraversal/*    → metatraversal
 *   /ready-player-golf/* → rpg
 *   (default)           → academy
 *
 * @package Polys Museum
 */

get_header();

// Get page meta for hero/featured image
$hero_image_id = get_post_thumbnail_id($post->ID);
$hero_image = $hero_image_id ? getThumbnail($hero_image_id, 'full') : '';
$section_class = get_post_meta($post->ID, 'section_class', true);
$section_hero_class = get_post_meta($post->ID, 'section_hero_class', true);
?>

<?php if ($hero_image): ?>
<section class="parallax home-fade hero-content <?php echo esc_attr($section_hero_class); ?> <?php echo esc_attr($section_class); ?>" 
         id="dynamic-hero" 
         style="background-image:url(<?php echo esc_url($hero_image); ?>);">
</section>
<?php endif; ?>

<main id="main-content" role="main" class="main <?php echo esc_attr($section_class); ?>">
    
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        
        <?php if (!$hero_image): ?>
        <header class="page-header">
            <h1 class="page-title"><?php the_title(); ?></h1>
        </header>
        <?php endif; ?>
        
        <div class="page-content">
            <?php the_content(); ?>
        </div>
        
    </article>
    
    <?php endwhile; endif; ?>
    
</main>

<?php get_footer(); ?>