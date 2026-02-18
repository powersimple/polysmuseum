<div id='top'></div>
<?php
    $GLOBALS['participants'] = is_array($GLOBALS['participants'] ?? null) ? $GLOBALS['participants'] : [];
   foreach($awards as $key => $award){// outer menu loop
    print "<ul class='awards-list'>";
    foreach($award['children'] as $c =>$child){// EVENTS
        if(@$child['classes'][0] == 'nomination' || @$child['classes'][0] == 'honor'){
          
            if(strpos($child['title'],"–") !== false){
                $label = explode("–",$child['title']);
                $label = $label[1];
            } else {
                $label = $child['title'];
            }
           
          
   
        ?>
<li><a href="#<?=$child['slug']?>" title="<?=$child['title']?>"><?=$label?></a></li>
        <?php
        }
       
    } print "</ul>";
   foreach($award['children'] as $c =>$child){// EVENTS loop
     ?>
              
  
   <?php
     if(@$child['classes'][0] == 'nomination' || @$child['classes'][0] == 'honor'){
       $link = get_permalink($child['ID']);
       ?>
       <div class="row justify-content-center">
       <h3 class="nomination-category"  id="<?=$child['slug']?>">
      <?php
      $embed_video_url = @$child['meta']['embed_video_url'][0];
     if(@$embed_video_url != ''){
       $embed_video_url;
   ?>
  <a href="#<?=$child['slug']?>" onclick="playSessionVideo('<?=$embed_video_url?>');" class='watch video-button' title="WATCH"><i class="fa-brands fa-youtube"></i></a>
   
   <?php
      }
   
   ?>
      <a href="<?=$link?>" title="<?=$child['title']?>"></a><?=$child['title']?></h3>
<?php
  $category_content = $child['post']->post_content ?? '';
  $stripped_content = preg_replace('/<!--\s*\/?wp:.*?-->/', '', $category_content);
  if (!empty(trim(strip_tags($stripped_content)))): ?>
    <div class="nomination-description"><?= do_blocks($category_content) ?></div>
<?php endif; ?>

       <div>
       <?php
       
      
       // Resolve presenter_image(s) from level 2 event meta (transparent webp)
       $presenter_image_urls = array();
       $pi_meta = @$child['meta']['presenter_image'];
       if (!empty($pi_meta) && is_array($pi_meta)) {
           foreach ($pi_meta as $pi_item) {
               $pi_id = is_array($pi_item) ? intval(@$pi_item['ID']) : intval($pi_item);
               if ($pi_id) {
                   $url = wp_get_attachment_image_url($pi_id, 'medium');
                   if ($url) $presenter_image_urls[] = $url;
               }
           }
       } elseif (!empty($pi_meta) && is_numeric($pi_meta)) {
           $url = wp_get_attachment_image_url(intval($pi_meta), 'medium');
           if ($url) $presenter_image_urls[] = $url;
       }

       // Resolve acceptance_image for individual honor categories
       $acceptance_image_url = '';
       $honor_categories = array('lifetime achievement', 'ombudsperson', 'community', 'creator', 'developer');
       $child_title_lower = strtolower($child['title']);
       $is_honor_category = false;
       foreach ($honor_categories as $hc) {
           if (strpos($child_title_lower, $hc) !== false) {
               $is_honor_category = true;
               break;
           }
       }
       if ($is_honor_category) {
           $ai_meta = @$child['meta']['acceptance_image'];
           if (!empty($ai_meta)) {
               $ai_id = is_array($ai_meta) ? (is_array($ai_meta[0]) ? intval($ai_meta[0]['ID']) : intval($ai_meta[0])) : intval($ai_meta);
               if ($ai_id) {
                   $acceptance_image_url = wp_get_attachment_image_url($ai_id, 'medium');
               }
           }
       }

       print "<ul class='nominee-list'>";
      get_nominees_and_winners($child['children'], 0, $presenter_image_urls, $acceptance_image_url);//recursive nominees loop
       ?>
      </ul>

       <a href="#top" class="back-to-top">Back to top ↥</a>
       </div>      
       </div><hr>
       <?php
     }
    
   }
  
 
 }
if(($contacts=@$GLOBALS['participants']) && @$_GET['mode']=='contact'){
  //var_dump($GLOBALS['participants']);
  foreach($contacts as $key => $contact){
    print "<div class='contact-card'>";
    print "$contact[name]|";
    print "$contact[email]<br>";

    print "</div>";
  }
}
?>