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


if(is_front_page()){
  $page_title= '';
} else {
  $page_title = $post->post_title . " | ";
}

  if(strpos($_SERVER['HTTP_HOST'],'obi-wan-v:3000')){
    $page_title = '🅳🅴🆅 '.$page_title;
  } else if (strpos($_SERVER['HTTP_HOST'],'staging')){
    $page_title = '🆂🆃🅰🅶🅸🅽🅶 '.$page_title;// doesn't work
  }
  // INCLUDES AFRAME JS TAGES ONLY IF IT IS ENABLED.

  //
 $aframe =    get_post_meta($post->ID,"use_aframe",true);







  if(@$aframe == 1){
    require_once("webxr/libraries/aframe.php");
  }
  // 
  
    // END AFRAME

  //

$rel_path= str_replace(url_root(),"",get_stylesheet_directory_uri());
// section vars used below in JS Default Var declarations
$section_class = @get_post_meta($post->ID,"section_class",true);
$section_menu = @get_post_meta($post->ID,"section_menu",true);
$section_menu_slug = @get_term($section_menu,"nav_menu")->slug;

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
      var active_id = <?=$post->ID?>,
      active_object = "<?=$post->post_type?>",
      home_page = <?=get_option( 'page_on_front' )?>,
      site_title = "<?=get_bloginfo('name')?>",
      xr_path = "<?=get_stylesheet_directory_uri()?>/xr/",
      data_path = "<?=$rel_path?>/data/",
      useWheelNav = false,
      uploads_path =  "<?=$url['baseurl']?>/",
      section_class = "<?=$section_class?>",
      
      section_menu = "<?=$section_menu?>",
      section_menu_slug = "<?=$section_menu_slug?>",
      slug = "<?=$post->post_name;?>",
      


      profile_template = ''//hack
      </script>
      <?php
     
    
     
    
          if(function_exists('icl_object_id')){
              global $sitepress;

        //     print "var languages = ".json_encode(getLanguageList());
            


        
   
   
  

    $thumbnail =getThumbnail(get_post_thumbnail_id($post->ID),"Full");
          }

         
      ?>
</head>

<?php
$page_style = '';
if($bg=get_post_meta($post->ID,'page-background',true)){
   $bg_src = getThumbnail($bg);
  if($bg_src != ''){
    $style_background="background:url($bg_src);background-size:cover";
  }
  $page_style = "style='$style_background'";
}
$section_class = @get_post_meta($post->ID,'section_class',true);
$class_bg = $section_class;

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
            <?php echo render_megamenu_with_logo('megamenu-polys'); ?>
          
          
      </div>  
      
      <!-- ============================================================
           SECTION BAR
           Shows L2 items from megamenu (children of active L1 item)
           Brand styling determined by URL path
           Renders only when an active L1 section with children is detected
      ============================================================ -->
      <?php echo render_sectionbar('megamenu-polys'); ?>
      
  </header>
  <!-- Parallax effect now handled by hero-parallax.js module (bundled in main.js) -->

<?php
  
   

function extract_number($class) {
    preg_match('/\d+$/', $class, $matches);
    return isset($matches[0]) ? intval($matches[0]) : null;
}


      $section_class = @get_post_meta($post->ID,"section_class",true);
    $section_hero_class = @get_post_meta($post->ID,"section_hero_class",true);

      if($section_hero_class == ''){
        $section_hero_class = 'hero-cover-25';
      }
    
      $padding_number = extract_number($section_hero_class);
      if(!empty($padding_number)){
        $padding_bottom = "padding-bottom:$padding_number%";
      } else {
        $padding_bottom = '';
      }

      $hero=get_post_meta($post->ID,'hero',true);
   $hero_image = getThumbnail($hero);
        
      $slides = get_slides($post->ID);
      if($post->post_name != 'nominees'){

      
      ?>

<?php
}
      if($hero){
      ?>


        <?php $hero_fit_class = !empty($padding_number) ? 'hero-fit-auto' : ''; ?>
        <section class="parallax home-fade home-full-height hero-content <?=$section_hero_class?> <?=@$section_class?> <?=$hero_fit_class?>" id="dynamic-hero" style="--hero-img:url(<?=$hero_image?>);background-image:var(--hero-img);<?php if(!empty($padding_number)) echo '--hero-vh:' . intval($padding_number) . 'vh;'; ?>"></section>
       


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

    <div class="hero-slideshow">
  <!-- Slides will be dynamically added here -->
</div>

        
        </section>
      
      
      <?php
        }
 
?>



    <?php
  
          foreach ($slides as $key => $media_id) {
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
        

        $slick =    get_post_meta($post->ID,"use_slick",true);  
        if(@$slick == 1){
          require_once("functions/slick.php");
        }
      }

   
     
    
      
      
      
      ?>