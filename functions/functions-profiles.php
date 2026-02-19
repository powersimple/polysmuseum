<?php
  function getProfileEvents($id){
    global $wpdb;
    $sql = "select post_id from wp_postmeta where meta_value = $id and meta_key like 'event_%'";
    return $wpdb->get_results($sql);
    

  }
  function getProfileSession($id){
    global $wpdb;
    $sql = "select ID, post_title, post_excerpt, post_content, post_parent from wp_posts where ID = $id";
    return $wpdb->get_results($sql);
    

  }
  function getProfiles(){
    global $wpdb;
    $sql = "select ID, post_title, post_excerpt, post_content, post_parent,post_name from wp_posts where post_type='profile' and post_status='publish' order by post_title";
    return $wpdb->get_results($sql);
    

  }
  function indexProfiles($profiles){

    foreach($profiles as $key=>$profile){
        extract((array)$profile);
        print  $ID ." ". get_last_name_first($post_title);
    //    print "<br>";
       // add_post_meta($ID,'sort_name',get_last_name_first($post_title));
      // print "insert into wp_postmeta (post_id,meta_key,meta_value) values ($ID,'sort_name','".get_last_name_first($post_title)."');<BR>";
    }




  }


  function get_last_name_first($name){
    $name = explode(" ",$name);
    $index_name = array_pop($name);
    if(count($name)>=1){
        $index_name .= ", ". implode(" ",$name);
    }
    return str_replace("'","\'",$index_name);
  }


 

    function displayProfiles($profile_array,$event,$profile_context,$session_type){

        $profiles_count = count($profile_array);
        if($profile_context == 'about'){
            $cols = ['col-sm-4'];
        } else {
            if($profiles_count == 2){
                $cols = ['col-sm-12','col-md-6'];
            } else if($profiles_count == 3){
                $cols = ['col-sm-12','col-md-4'];

            } else if($profiles_count == 4){
                $cols = ['col-sm-6','col-md-3','col-xl-6'];
            } else{
                $cols = ['col-sm-6','col-md-3','col-lg-3','col-xl-2'];
            }
        }
        $grid= implode(" ",@$cols);

        print "<div class='row'>";


        foreach($profile_array as $key => $profile_id){
            
            ?>
            
            <div class="profile <?=$grid?>"><?=displayProfile($profile_id,$profile_context);?></div>
            
            <?php
        
        }
        print "</div>";
        return ob_get_clean();
    }
    function displayProfileMeta($profile_id){
        
         $profile_meta = get_post_meta($profile_id);
         ?>
        <div class="speaker-meta">
        <?= wrapMeta($profile_meta,'profile_title','h5');?>
        <?= wrapMeta($profile_meta,'company','h5');?>
        <?= wrapMeta($profile_meta,'linkedin','a');?>
        <?= wrapMeta($profile_meta,'github','a');?>
        <?= wrapMeta($profile_meta,'website','a');?>
        <?= wrapMeta($profile_meta,'twitter','a');?>

<?php

    }
    function displayProfile($profile_id,$profile_context){
        $profile_post = get_post($profile_id);
        
     
        $profile_name = $profile_post->post_title;
        $profile_about = $profile_post->post_content;
        $profile_excerpt = $profile_post->post_excerpt;

        ob_start();
        $thumbnail = getThumbnail(get_post_meta($profile_id,"_thumbnail_id",true),"medium");   
        ?>
        <div class="speaker-thumbnail">
        <?php
        if($thumbnail != ''){
            print "<img src='$thumbnail' alt='$profile_name' title='$profile_name'>";
        }
        ?>

        </div>
        <h4><?=$profile_post->post_title?></h4>
        <?=displayProfileMeta($profile_id)?>
        <?php 
        if($profile_context == 'about'){?>
        <p class="profile-excerpt"><?=nl2br($profile_excerpt)?></p>
        <?php } ?>
        </div>

<?php

return ob_get_clean();


    }

    function wrapMeta($array,$var,$tag,$target="_blank"){
     
       
        if(@$array[$var]){
           
           $value = $array[$var][0];
            if($tag == 'a'){
                return wrapLink($var,$value,$target);
            } else {
                return "<$tag>".$value."</$tag>";
            }
        }
    }
    function wrapLink($var,$link,$target){
        if($link == ''){
            return;
        }
       
        $use_font_awesome = false;
        if($var == 'twitter'
        || $var == 'linkedin'
        || $var == 'github'
        || $var == 'instagram'
        || $var == 'website'){
            $use_font_awesome = true;

        }    
        if($var == 'twitter'){
            $var = 'x-twitter';
        }
      //
        
        if($var == 'website'){
            $var = 'link';
            
        }
        if($use_font_awesome == true){
            // fa-link is a solid icon, all others are brands
            $icon_prefix = ($var == 'link') ? 'fa-solid' : 'fa-brands';
            $label = "<i class='$icon_prefix fa-$var social-icon'></i>";
        } else {
            
            $label = $link;
        }
        
        return "<a href='$link' target='$target'>$label</a>";
    }
    function getProfileChildrenIDs($id){
        global $wpdb;
        $q = $wpdb->get_results("select ID from wp_posts where post_status = 'publish' and post_type='profile' and post_parent = $id order by menu_order");
        $profile_children = array();
        foreach($q as $key=>$value){
            array_push($profile_children,$value->ID);
        }
        return $profile_children;
    }

    function displayTeam($team,$className){
       
        print "<div class='row'>";

        foreach($team as $key => $member){
            
          print "<div class='$className'>";
            displayTeamMember($member);
          print "</div>";  
          
        }

        print "</div>";
        return $team;
    }
    function displayTeamMember($member){
        extract((array)$member);
      
        $link = get_permalink($post->ID);
       $thumbnail_id = @$meta['_thumbnail_id'][0];
        $thumbnail = getThumbnail($thumbnail_id);
        $custom_class= @$classes[0];
       ?>
    
       <a href='<?=$link?>' title='<?=$title?>'>
        <img class="<?=$custom_class?>" src="<?=$thumbnail?>" alt="<?=$title?>" title="<?=$title?>"> 
        <h5><?=$attr_title?></h5>
        <h4><?=$title?><h4></a>
        
        <h6><div class="social-icons profile-meta"><?=displayProfileMeta($post->ID)?></div></h6>
        <p><?=nl2br($member['post']->post_excerpt)?><p>
    

        <?php
     
    }


	function profile_shortcode( $atts, $content = null ) {
		//set default attributes and values

		// If class attribute is empty, do not render anything
		if (empty($atts['class'])) {
			return '';
		}

        $menu = get_menu_array($atts['menu']);

		// Filter out items with event_type "video reel"
		$menu = array_filter($menu, function($item) {
			return strcasecmp(trim(@$item['event_type']), 'video reel') !== 0;
		});

		$values = shortcode_atts( array(
			'menu'   	=>  $menu,
			'className'	=> $atts['class'],
		), $atts );

		ob_start();
       displayTeam($menu,$atts['class']);

		?>
	
		<?php
		return ob_get_clean();
	
	}
	add_shortcode( 'profile_list', 'profile_shortcode' );

	function polys_partner_list_shortcode($atts) {
		$atts = shortcode_atts(array(
			'menu'  => '',
			'class' => '',
		), $atts);

		if (empty($atts['menu'])) {
			return '';
		}

		// If class attribute is empty, do not render anything
		if (empty($atts['class'])) {
			return '';
		}

		$menu = get_menu_array($atts['menu']);
		if (empty($menu)) {
			return '';
		}

		$wrapper_class = 'partner-list';
		if (!empty($atts['class'])) {
			$wrapper_class .= ' ' . esc_attr($atts['class']);
		}

		ob_start();
		echo '<div class="' . $wrapper_class . '">';

		foreach ($menu as $item) {
			// Skip items with event_type "video reel"
			if (strcasecmp(trim(@$item['event_type']), 'video reel') === 0) {
				continue;
			}

			$post = $item['post'];
			if (!$post) continue;

			// Resolve external URL: website meta → raw menu item URL → none
			$link_url = get_post_meta($post->ID, 'website', true);
			if (empty($link_url)) {
				$link_url = get_post_meta($item['ID'], '_menu_item_url', true);
			}
			// Ensure protocol — fixes values like "://site.com" or "site.com"
			if (!empty($link_url)) {
				$link_url = preg_replace('#^:?//#', 'https://', $link_url);
				if (!preg_match('#^https?://#i', $link_url)) {
					$link_url = 'https://' . $link_url;
				}
			}

			// Kicker from menu item attr_title (not linked)
			$kicker = !empty($item['attr_title']) ? $item['attr_title'] : '';

			// Menu item title (not post title)
			$title = !empty($item['title']) ? $item['title'] : '';

			// Featured image (medium) — title in alt/title attrs
			$thumbnail_html = get_the_post_thumbnail($post->ID, 'medium', array('alt' => $title, 'title' => $title));

			// Menu item description field
			$description = !empty($item['description']) ? $item['description'] : '';

			// Open link wrapper if URL exists
			if (!empty($link_url)) {
				echo '<a class="partner-item-link" href="' . esc_url($link_url) . '" target="_blank" rel="noopener">';
			}

			echo '<div class="partner-item">';

			if (!empty($kicker)) {
				echo '<h5 class="partner-item-kicker" style="text-align:center">' . esc_html($kicker) . '</h5>';
			}

			if (!empty($thumbnail_html)) {
				echo '<div class="partner-item-image" style="margin-bottom:5px">' . str_replace('<img ', '<img style="width:100%;height:auto" ', $thumbnail_html) . '</div>';
			}

			echo '</div>';

			if (!empty($link_url)) {
				echo '</a>';
			}

			if (!empty($description)) {
				echo '<p class="partner-item-content">' . esc_html($description) . '</p>';
			}
		}

		echo '</div>';
		return ob_get_clean();
	}
	add_shortcode('partner_list', 'polys_partner_list_shortcode');

?>

