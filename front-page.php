<?php

get_header(); 
$section_class = get_post_meta($post->ID,'section_class',true);
$default_video_url = get_post_meta($post->ID,"featured_video_url",true);
$video_playlist = get_post_meta($post->ID,"video_playlist",true);

$section_menu = get_post_meta($post->ID,"section_menu",true);

?>

<main id="main-content" role="main" class="main has-events-sidebar <?=$section_class?>">
  <div class="main-content-area">
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
  </div><!-- /.main-content-area -->

  <?php get_template_part('templates/sidebar-events'); ?>
</main>

<?php get_footer(); ?>