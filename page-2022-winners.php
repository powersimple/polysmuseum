<?php
require_once "functions/functions-awards.php";
require_once get_template_directory() . '/functions/functions-audit.php';
require_once get_template_directory() . '/functions/functions-exhibits.php';

function url(){
  return sprintf(
    "%s://%s%s",
    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
    $_SERVER['SERVER_NAME'],
    $_SERVER['REQUEST_URI']
  );
}
get_header(); 
$section_class = get_post_meta($post->ID,'section_class',true);
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




<main  role="main" class="main <?=$section_class?>">

  <section class="module" id="<?php echo @$slug?>" role="region">
      <?php 
        echo do_blocks(do_shortcode($post->post_content));

        // Build awards array from menu 'polys3' using Exhibits logic
        $awards = array();
        $results = get_menu_items_for_slug('polys3');
        if (!is_string($results) && !empty($results['menu_items'])) {
            $menu_items = array();
            $parent_map = array();
            foreach ($results['menu_items'] as $item) {
                $menu_items[$item->ID] = $item;
                if ($item->menu_item_parent) { $parent_map[$item->ID] = $item->menu_item_parent; }
            }

            $current_award = null;
            $current_event_logo_url = '';
            $current_tile_bg_url = '';
            $index_by_id = array(); foreach ($results['menu_items'] as $pos => $mi) { $index_by_id[$mi->ID] = $pos; }

            foreach ($results['menu_items'] as $item) {
                $post_title = '';
                if ($item->object_id) { $p = get_post($item->object_id); if ($p) { $post_title = $p->post_title; } }
                $classes = get_post_meta($item->ID, '_menu_item_classes', true);
                $is_winner = (is_array($classes) && in_array('winner', $classes));
                $is_honoree = (is_array($classes) && in_array('honoree', $classes));
                $level = get_nesting_level($menu_items, $item->ID);

                if ($item->actual_post_type === 'event') {
                    $type_info = get_post_meta($item->ID, '_event_type', true);
                    if ($level === 2) {
                        if ($current_award !== null) { $awards[] = $current_award; }
                        $current_award = array(
                            'title' => $item->post_title ?: $post_title,
                            'type' => $type_info,
                            'presenters' => array(),
                            'winners' => array(),
                            'nominees' => array(),
                            'award_type' => '',
                            'object_id' => $item->object_id,
                            'presenter_ids' => array(),
                            'winner_ids' => array(),
                            'current_winner' => null,
                            'presenter_image_url' => '',
                            'acceptance_image_url' => '',
                            'event_logo_url' => $current_event_logo_url,
                            'tile_bg_url' => $current_tile_bg_url
                        );
                        // preload event images
                        if (!empty($current_award['object_id'])) {
                            $pmeta = get_post_meta($current_award['object_id'], 'presenter_image', true);
                            $purl = '';
                            if (is_array($pmeta) && !empty($pmeta)) { $first = $pmeta[0]; $att_id = is_array($first) && isset($first['ID']) ? intval($first['ID']) : intval($first); if ($att_id) { $purl = wp_get_attachment_image_url($att_id, 'full'); } }
                            elseif (is_numeric($pmeta)) { $purl = wp_get_attachment_image_url(intval($pmeta), 'full'); }
                            if ($purl) { $current_award['presenter_image_url'] = $purl; }

                            $ameta = get_post_meta($current_award['object_id'], 'acceptance_image', true);
                            $aurl = '';
                            if (is_array($ameta) && !empty($ameta)) { $firsta = $ameta[0]; $aid = is_array($firsta) && isset($firsta['ID']) ? intval($firsta['ID']) : intval($firsta); if ($aid) { $aurl = wp_get_attachment_image_url($aid, 'full'); } }
                            elseif (is_numeric($ameta)) { $aurl = wp_get_attachment_image_url(intval($ameta), 'full'); }
                            if ($aurl) { $current_award['acceptance_image_url'] = $aurl; }
                        }
                    }
                } elseif ($level === 2 && ($is_winner || $is_honoree) && $current_award !== null) {
                    $base_title = $item->post_title ?: $post_title;
                    $base_company = '';
                    if (preg_match('/^(.*?)\s+by\s+(.*?)$/', $base_title, $matches)) {
                        $base_title = trim($matches[1]);
                        $base_company = trim($matches[2]);
                    }
                    $winners_for_node = array();
                    foreach ($results['menu_items'] as $child_item) {
                        if ($child_item->menu_item_parent == $item->ID) {
                            $child_post = get_post($child_item->object_id);
                            if (!$child_post) { continue; }
                            $child_has_children = false;
                            foreach ($results['menu_items'] as $probe_item) {
                                if ($probe_item->menu_item_parent == $child_item->ID) { $child_has_children = true; break; }
                            }
                            if ($child_item->actual_post_type === 'resource' || $child_has_children) {
                                $wi = array('title' => $base_title, 'company' => $child_post->post_title ?: $base_company, 'people' => array());
                                foreach ($results['menu_items'] as $grandchild_item) {
                                    if ($grandchild_item->menu_item_parent == $child_item->ID) {
                                        $grandchild_post = get_post($grandchild_item->object_id);
                                        if ($grandchild_post && $grandchild_item->actual_post_type === 'profile') { $wi['people'][] = $grandchild_post->post_title; }
                                    }
                                }
                                $winners_for_node[] = $wi;
                            } elseif ($child_item->actual_post_type === 'profile') {
                                $winners_for_node[] = array('title' => $base_title, 'company' => $base_company, 'people' => array($child_post->post_title));
                            }
                        }
                    }
                    if (empty($winners_for_node)) { $winners_for_node[] = array('title' => $base_title, 'company' => $base_company, 'people' => array()); }
                    foreach ($winners_for_node as $wi) { $current_award['winners'][] = $wi; $current_award['winner_ids'][] = $item->object_id; }
                    $current_award['current_winner'] = $item->ID;
                    $current_award['award_type'] = $is_winner ? 'WINNER' : 'HONOREE';
                } elseif ($level === 3 && $current_award !== null) {
                    $type_info = get_post_meta($item->ID, '_guest_type', true);
                    if ($type_info === 'award-presenter') { $current_award['presenters'][] = $item->post_title ?: $post_title; $current_award['presenter_ids'][] = $item->object_id; }
                }
            }
            if ($current_award !== null) { $awards[] = $current_award; }
        }

        echo '<div class="exhibits-wrapper">';
        echo '<h2>Award Exhibits</h2>';
        echo '<div class="exhibits-grid">';
        foreach ($awards as $idx => $award) {
            echo '<div class="exhibit-tile"><div class="exhibit-content" style="position:relative;">'
               . '<img src="' . esc_url('https://obi-wan-v:3000/wp-content/uploads/2025/11/PolysImmersiveAwardsLogoWithTrophy-3-1Aspect-NoYear.png') . '" alt="Polys Immersive Awards" style="position:absolute;top:0;left:0;width:256px;height:auto;" />'
               . '</div></div>';
        }
        echo '</div>';
        echo '</div>';
      ?>
  </section>

</main>
<?php get_footer(); ?>