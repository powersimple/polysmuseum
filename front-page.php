<?php

get_header(); 
$section_class = get_post_meta($post->ID,'section_class',true);
$default_video_url = get_post_meta($post->ID,"featured_video_url",true);
$video_playlist = get_post_meta($post->ID,"video_playlist",true);

$section_menu = get_post_meta($post->ID,"section_menu",true);


$hero_image_id = get_post_thumbnail_id($post->ID);
$hero_image = $hero_image_id ? getThumbnail($hero_image_id, 'full') : '';
$section_class = get_post_meta($post->ID, 'section_class', true);
$section_hero_class = get_post_meta($post->ID, 'section_hero_class', true);

// ── Partner logo ticker (desktop only) ──────────────────────────────
$partner_menu = get_menu_array('polys6-partner');
$ticker_items = array();
if (!empty($partner_menu) && is_array($partner_menu)):
  foreach ($partner_menu as $pi) {
    $pi_post = isset($pi['post']) ? $pi['post'] : null;
    if (!$pi_post) continue;

    // Logo: use 'partner-logo' size (400px cap), fall back to thumbnail from menu array
    $logo_url = get_the_post_thumbnail_url($pi_post->ID, 'partner-logo') ?: '';
    if (empty($logo_url) && !empty($pi['thumbnail'])) {
      $logo_url = $pi['thumbnail'];
    }

    // Link: website meta → menu item URL → none
    $link_url = get_post_meta($pi_post->ID, 'website', true);
    if (empty($link_url)) {
      $link_url = get_post_meta($pi['ID'], '_menu_item_url', true);
    }
    if (!empty($link_url)) {
      $link_url = preg_replace('#^(?:https?:)?(?://)+(?:https?:(?://)+)*#i', '', $link_url);
      $link_url = 'https://' . $link_url;
    }

    $title = !empty($pi['title']) ? $pi['title'] : '';
    if (empty($logo_url) && empty($title)) continue; // skip empty items
    $ticker_items[] = array('logo' => $logo_url, 'link' => $link_url, 'title' => $title);
  }
endif;
?>

<?php if (!empty($ticker_items)): ?>
<section class="partner-ticker" aria-label="Partners">
  <div class="partner-ticker__track">
    <ul class="partner-ticker__list">
      <?php foreach ($ticker_items as $ti):
        $inner = !empty($ti['logo'])
          ? '<img src="' . esc_url($ti['logo']) . '" alt="' . esc_attr($ti['title']) . '" loading="lazy">'
          : '<span class="partner-ticker__text">' . esc_html($ti['title']) . '</span>';
      ?>
        <li class="partner-ticker__item">
          <?php if (!empty($ti['link'])): ?>
            <a href="<?= esc_url($ti['link']) ?>" target="_blank" rel="noopener" title="<?= esc_attr($ti['title']) ?>"><?= $inner ?></a>
          <?php else: ?>
            <?= $inner ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <ul class="partner-ticker__list" aria-hidden="true">
      <?php foreach ($ticker_items as $ti):
        $inner = !empty($ti['logo'])
          ? '<img src="' . esc_url($ti['logo']) . '" alt="' . esc_attr($ti['title']) . '" loading="lazy">'
          : '<span class="partner-ticker__text">' . esc_html($ti['title']) . '</span>';
      ?>
        <li class="partner-ticker__item">
          <?php if (!empty($ti['link'])): ?>
            <a href="<?= esc_url($ti['link']) ?>" target="_blank" rel="noopener" title="<?= esc_attr($ti['title']) ?>"><?= $inner ?></a>
          <?php else: ?>
            <?= $inner ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<main id="main-content" role="main" class="main has-events-sidebar <?=$section_class?>">
  <div class="main-content-area">
    
    <div class="widget-container">
<div id="page-content">      
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
      </div>
    </div><!-- /.widget-container -->
  </div><!-- /.main-content-area -->

  <?php get_template_part('templates/sidebar-events'); ?>
</main>

<?php get_footer(); ?>