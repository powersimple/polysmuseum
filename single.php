<?php

get_header();
$section_class = get_post_meta($post->ID,'section_class',true);
$is_press_release = is_singular('post') && has_category('press-release', $post);

// Curated sidebar menu (Bug 5): only render the events sidebar layout when a
// sidebar menu is assigned via the metabox. Posts without it are unchanged.
$sidebar_menu_id  = get_post_meta($post->ID, 'sidbebar_menu', true);
$has_sidebar_menu = !empty($sidebar_menu_id);
print $default_video_url = get_post_meta($post->ID,"embed_video_url",true);

if($hero=get_post_meta($post->ID,'hero',true)){
   $hero_image = getThumbnail($hero);
   /*
if($post->post_parent==0){

    print "<div id='section-heading'>";
    $parent_post = get_post($post->post_parent);
    $parent_post_title = $parent_post->post_title;
    echo $parent_post_title;
    print "</div>";
}
*/
?>



<?php
}

?>

<div class="title-bar">
 
    <h1 class="title"><?=$post->post_title?></h1>
    <?php
    if(@$post->post_excerpt){
    ?>
    <h2 class="featuring">
        <?=$post->post_excerpt?></h1>
    
    <?php
    }
 
    ?>
</div>


<main role="main" class="main <?=$section_class?><?= $is_press_release ? ' press-release-page' : '' ?><?= $has_sidebar_menu ? ' has-events-sidebar' : '' ?>">

  <?php if ($has_sidebar_menu): ?><div class="main-content-area"><?php endif; ?>

  <section class="module" id="<?php echo @$slug?>" role="region">
<div class="row">
<div class="container">
 
  <div class="col-xs-12 col-sm-offset-1 col-sm-10<?= $is_press_release ? ' press-release-content' : '' ?>">
    <div class="post">

<?php

//match_profilesFromTable('_profile_import');
  print do_blocks(do_shortcode($post->post_content));
?>
    </div>
</div>
</section>
</div>

</div>

  <?php if ($has_sidebar_menu): ?></div><!-- /.main-content-area -->
  <?php get_template_part('templates/sidebar-events'); ?>
  <?php endif; ?>

  </main>
  <?php get_footer(); ?>