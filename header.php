<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js no-svg">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="profile" href="https://gmpg.org/xfn/11">
    <link rel="shortcut icon" href="<?php echo get_stylesheet_directory_uri();?>/images/icons/favicon.ico" />
<?php
$post_title = modify_post_title();
add_filter('wp_title', 'modify_post_title', 10, 2);
$url = wp_upload_dir();
// All CSS is enqueued via functions-enqueue.php - do not add hardcoded links here
wp_head();

// Null-safe post references for custom tool pages where $post may not exist
global $post;
$_has_post = isset($post) && is_object($post);
$current_post_id = $_has_post && isset($post->ID) ? (int) $post->ID : 0;
$current_post_name = $_has_post && isset($post->post_name) ? $post->post_name : '';
$current_post_type = $_has_post && isset($post->post_type) ? $post->post_type : '';
$current_post_title = $_has_post && isset($post->post_title) ? $post->post_title : '';

if(is_front_page()){
  $page_title= '';
} else {
  $page_title = $current_post_title . " | ";
}

  if(strpos($_SERVER['HTTP_HOST'],'obi-wan-v:3000')){
    $page_title = '🅳🅴🆅 '.$page_title;
  } else if (strpos($_SERVER['HTTP_HOST'],'staging')){
    $page_title = '🆂🆃🅰🅶🅸🅽🅶 '.$page_title;// doesn't work
  }
  // INCLUDES AFRAME JS TAGES ONLY IF IT IS ENABLED.

  //
 $aframe = $current_post_id ? get_post_meta($current_post_id, "use_aframe", true) : '';







  if(@$aframe == 1){
    require_once("webxr/libraries/aframe.php");
  }
  // 
  
    // END AFRAME

  //

$rel_path= str_replace(url_root(),"",get_stylesheet_directory_uri());
// section vars used below in JS Default Var declarations
$section_class = $current_post_id ? get_post_meta($current_post_id, "section_class", true) : '';
$section_menu = $current_post_id ? get_post_meta($current_post_id, "section_menu", true) : '';
$section_menu_slug = $section_menu ? @get_term($section_menu, "nav_menu")->slug : '';

global $default_embed_video_url;
$default_embed_video_url = "https://www.youtube.com/embed/AWFgm65j4n8?autoplay=1&rel=0";
//phpinfo();

?>


    <title><?=$page_title?><?=get_bloginfo('name')?> - <?=bloginfo("description");?></title>
 <script>

if (location.protocol !== 'https:') {
    location.replace(`https:${location.href.substring(location.protocol.length)}`);
}
      // Wordpress PHP variables to render into JS at outset.
      var active_id = <?=$current_post_id?>,
      active_object = "<?=$current_post_type?>",
      home_page = <?=get_option( 'page_on_front' )?>,
      site_title = "<?=get_bloginfo('name')?>",
      xr_path = "<?=get_stylesheet_directory_uri()?>/xr/",
      data_path = "<?=$rel_path?>/data/",
      useWheelNav = false,
      uploads_path =  "<?=$url['baseurl']?>/",
      section_class = "<?=$section_class?>",
      
      section_menu = "<?=$section_menu?>",
      section_menu_slug = "<?=$section_menu_slug?>",
      slug = "<?=$current_post_name;?>",
      


      profile_template = ''//hack
      </script>
      <?php
     
    
     
    
          if(function_exists('icl_object_id')){
              global $sitepress;

        //     print "var languages = ".json_encode(getLanguageList());
            


        
   
   
  

    $thumbnail = $current_post_id ? getThumbnail(get_post_thumbnail_id($current_post_id), "Full") : '';
          }

         
      ?>
</head>

<?php
$page_style = '';
if($current_post_id && $bg=get_post_meta($current_post_id,'page-background',true)){
   $bg_src = getThumbnail($bg);
  if($bg_src != ''){
    $style_background="background:url($bg_src);background-size:cover";
  }
  $page_style = "style='$style_background'";
}
$section_class = $current_post_id ? get_post_meta($current_post_id, 'section_class', true) : '';
$class_bg = $section_class;

// Add scoped body class for red-carpet event pages (used by SCSS to avoid style leakage)
if ($section_class === 'red-carpet' && $current_post_type === 'event') {
    $class_bg .= ' event--red-carpet';
}

// Centralized URL-based brand detection (defined in functions.php)
// Default is "academy" if no URL pattern matches
$body_brand = polys_get_current_brand();

?>

<body data-spy="scroll" data-target=".onpage-navigation" data-offset="60" class="<?=@$class_bg?>" data-body-brand="<?=$body_brand?>" <?=@$page_style?>>

<!-- Skip to Content Link - WCAG 2.1 AA: visible on focus, targets main content -->
<a href="#main-content" class="skip-to-content">Skip to main content</a>

        <div class="page-loader">
        <div class="loader">Loading...</div>
      </div>
  

  <div class="flex-wrapper"><!--to maintain sticky footer-->
    <header id="header" class="navbar navbar-custom navbar-fixed-top navbar-transparent" role="navigation">
        <div class="container">
         
          <!-- ============================================================
                 NEW MEGAMENU SYSTEM
                 Responsive, accessible navigation with L1-L4 support
                 Menu slug: 'megamenu' (set in WordPress Admin > Menus)
            ============================================================ -->
            <?php echo render_megamenu_with_logo('megamenu'); ?>
          
          
      </div>  
      
      <!-- ============================================================
           SECTION BAR
           Shows L2 items from megamenu (children of active L1 item)
           Brand styling determined by URL path
           Renders only when an active L1 section with children is detected
      ============================================================ -->
      <?php // echo render_sectionbar('megamenu-polys'); ?>
      
  </header>
  <!-- Parallax effect now handled by hero-parallax.js module (bundled in main.js) -->

<?php
  
   

function extract_number($class) {
    preg_match('/\d+$/', $class, $matches);
    return isset($matches[0]) ? intval($matches[0]) : null;
}


      $section_class = $current_post_id ? get_post_meta($current_post_id, "section_class", true) : '';
    $section_hero_class = $current_post_id ? get_post_meta($current_post_id, "section_hero_class", true) : '';

      if($section_hero_class == ''){
        $section_hero_class = 'hero-cover-25';
      }
    
      $padding_number = extract_number($section_hero_class);
      if(!empty($padding_number)){
        $padding_bottom = "padding-bottom:$padding_number%";
      } else {
        $padding_bottom = '';
      }

      $hero_ids   = $current_post_id ? get_hero_image_ids($current_post_id) : array();
      $hero_count = count($hero_ids);
      $hero       = $hero_count ? $hero_ids[0] : '';
      $hero_image = $hero ? getThumbnail($hero) : '';

      $slides = $current_post_id ? get_slides($current_post_id) : [];
      if($current_post_name != 'nominees'){

      
      ?>

<?php
}
      // Page title band — rendered directly below the top hero item. When there
      // is no hero / carousel / slideshow above it, the band is the first
      // element under the fixed nav and must clear it (page-title-band--top).
      $has_hero_video = has_hero_video($current_post_id);
      $has_top_media  = $has_hero_video
          || $hero_count > 0
          || (is_array($slides) && count($slides) > 0);
      $band_top_class = $has_top_media ? '' : ' page-title-band--top';
      $page_title = $current_post_id ? get_the_title($current_post_id) : '';
      $page_title_html = ($page_title !== '')
          ? '<div class="page-title-band' . $band_top_class . '"><h1 class="page-title"><span class="page-title__text">' . esc_html($page_title) . '</span></h1></div>'
          : '';

      if($has_hero_video){
        // Hero = video (top item). The title sits directly below the video; the
        // screen_image slideshow (if any) renders below the title.
        echo render_hero_video($current_post_id);
        echo $page_title_html;
        echo render_screen_carousel($current_post_id);
      } else if($hero_count > 1){
        // Multiple hero images → slideshow carousel (distinct hero transition).
        $hero_label = $page_title !== '' ? $page_title . ' — hero' : 'Hero';
        echo render_image_carousel($hero_ids, array('variant' => 'hero', 'label' => $hero_label));
      } else if($hero){
      ?>


        <?php $hero_fit_class = !empty($padding_number) ? 'hero-fit-auto' : ''; ?>
        <?php $hero_home_class = is_front_page() ? 'hero-home-mobile' : ''; ?>
        <section class="parallax home-fade home-full-height hero-content <?=$section_hero_class?> <?=@$section_class?> <?=$hero_fit_class?> <?=$hero_home_class?>" id="dynamic-hero" style="--hero-img:url(<?=$hero_image?>);background-image:var(--hero-img);<?php if(!empty($padding_number)) echo '--hero-vh:' . intval($padding_number) . 'vh;'; ?>"></section>
       


    <?php
      } else if(is_array($slides) && count($slides)>0){ 
     
        $slide_version_list = [];
        
        if(is_array($slides) && count($slides)>0){
          ?>
          <!--
            <section style="padding: 20px 5%;">
<p style="text-align:center">WATCH LIVE THIS SUNDAY LIVE FROM MICROSOFT GARAGE IN SOHO. REGISTER FOR UPDATED STREAMING AND WATCH PARTY INFO AT <a href="https://bit.ly/POLYS5LIVE" target="blank">BIT.LY/POLYS5LIVE</a></p>
</section>-->
   
    <section class="home-section home-parallax home-fade <?=@$section_hero_class?>" id="home" style="top:25px;">

    <?php // Screen Image carousel (modern, zero-dependency module). ?>
    <?php echo render_screen_carousel($current_post_id); ?>

        
        </section>
      
      
      <?php
        }
 
?>



    <?php
  
          // Retired: legacy hero_slides JS builder disabled (carousel renders via
          // render_screen_carousel()). Loop over an empty set to skip the work.
          foreach (array() as $key => $media_id) {
              $versions = getThumbnailVersions($media_id);
              $version_list = array();
            // var_dump($versions);
              foreach($versions as $v => $version) {
                  $version_list[] = "'$v': '$version'";
              }
              
              $media_data = get_media_data($media_id);
              
              $slide_data = array(
                  "sm" => $versions["thumbnail"],
                  "md" => $versions["medium"],
                  "md_lg" => $versions["medium_large"],
                  "lg" => $versions["large"],
                  "xl" => $versions["1536x1536"],
                  "full"=>$versions["2048x2048"],
                  "title" => $media_data["title"],
                  "alt" => $media_data["alt"],
                  "description" => $media_data["desc"],
                  "caption" => $media_data["caption"]
              );
              
              $slide_json = json_encode($slide_data);
              array_push($slide_version_list, $slide_json);
          }
          
          
          ?>
              <script>
          var hero_slides = [
            <?=implode(",", $slide_version_list)?>
          ];
          </script>
          <?php
        

        // Old jQuery/Slick slideshow retired — the carousel is now rendered by
        // render_screen_carousel() above. The use_slick gate is gone.
      }

      // Title for the non-video branches (image hero / slideshow-as-hero / no
      // hero): render it directly below the single top item. The video branch
      // above already placed the title between the video and the slideshow.
      if (!$has_hero_video) {
          echo $page_title_html;
      }

   
     
    
      
      
      
      ?>