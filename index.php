<?php
/**
 * The main template file — required by WordPress to validate a classic theme.
 * In practice, more-specific templates (front-page.php, page.php, single.php,
 * archive.php, etc.) handle rendering. This file is the ultimate fallback.
 */

get_header(); ?>

<main id="main-content" role="main" class="main">
  <div class="main-content-area">
    <div class="widget-container">
      <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>
          <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
            <div class="entry-content">
              <?php the_content(); ?>
            </div>
          </article>
        <?php endwhile; ?>
        <?php the_posts_navigation(); ?>
      <?php else : ?>
        <p><?php esc_html_e( 'No content found.', 'polysmuseum' ); ?></p>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php get_footer(); ?>
