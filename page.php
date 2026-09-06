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

// Curated sidebar menu (Bug 5): only render the events sidebar layout when a
// sidebar menu is assigned via the metabox. Pages without it are unchanged.
$sidebar_menu_id  = get_post_meta($post->ID, 'sidbebar_menu', true);
$has_sidebar_menu = !empty($sidebar_menu_id);
?>



<main id="main-content" role="main" class="main <?php echo esc_attr($section_class); ?><?php echo $has_sidebar_menu ? ' has-events-sidebar' : ''; ?>">

    <?php if ($has_sidebar_menu): ?><div class="main-content-area"><?php endif; ?>

    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        
        <?php // Page title now renders once in the page-title-band (header.php)
              // directly under the hero; the in-article <h1> was removed to
              // avoid a duplicate page heading. ?>
        
     
      <div class="widget-container">
      
      <?php
      print do_blocks(do_shortcode($post->post_content));
      if(@$section_class == 'ceremony'){
        if(@$section_menu){
          require_once "functions/functions-awards.php";
          $awards = get_menu_array($section_menu);
          require_once('templates/awards.php');
        }
      }
      ?>
    </div><!-- /.widget-container -->
    </article>

    <?php endwhile; endif; ?>

    <?php if ($has_sidebar_menu): ?></div><!-- /.main-content-area -->
    <?php get_template_part('templates/sidebar-events'); ?>
    <?php endif; ?>

</main>

<?php get_footer(); ?>