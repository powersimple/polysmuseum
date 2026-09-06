<?php
  function getProfileEvents($id){
    global $wpdb;
    $sql = $wpdb->prepare("select post_id from wp_postmeta where meta_value = %d and meta_key like 'event_%'", $id);
    return $wpdb->get_results($sql);
    

  }
  function getProfileSession($id){
    global $wpdb;
    $sql = $wpdb->prepare("select ID, post_title, post_excerpt, post_content, post_parent from wp_posts where ID = %d", $id);
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
    function displayProfileMeta($profile_id, $social_only = false){
        
         $profile_meta = get_post_meta($profile_id);
         ?>
        <div class="speaker-meta">
        <?php if (!$social_only) : ?>
        <?= wrapMeta($profile_meta,'profile_title','h5');?>
        <?= wrapMeta($profile_meta,'company','h5');?>
        <?php endif; ?>
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
        $q = $wpdb->get_results($wpdb->prepare("select ID from wp_posts where post_status = 'publish' and post_type='profile' and post_parent = %d order by menu_order", $id));
        $profile_children = array();
        foreach($q as $key=>$value){
            array_push($profile_children,$value->ID);
        }
        return $profile_children;
    }

    function displayTeam($team,$className,$show_titles=false,$show_links=false){
        $is_partners = strpos($className, 'partners-page') !== false;

        if (!$is_partners) {
            // Modern responsive grid — auto-fit by card width, and the last
            // row's remainder centres (see .profile-grid in profile.scss).
            // Replaces the old Bootstrap .row / col-* columns. The passed
            // $className (e.g. "team-member") is kept for card-content styling.
            print "<div class='profile-grid'>";
            foreach($team as $key => $member){
              print "<div class='profile-card " . esc_attr($className) . "'>";
                displayTeamMember($member, $className, $show_titles, $show_links);
              print "</div>";
            }
            print "</div>";
            return $team;
        }

        // Partners-page path — supports tier headings and new-row breaks
        $tier_colors = ['blue-tier','red-tier','gold-tier','platinum-tier','silver-tier','green-tier','orange-tier','purple-tier'];
        $row_open = false;

        foreach($team as $key => $member){
            $classes = is_array($member['classes'] ?? null) ? $member['classes'] : [];
            $is_tier = in_array('tier', $classes);
            $is_new_row = in_array('new-row', $classes);

            if ($is_tier) {
                if ($row_open) { print "</div></div>"; $row_open = false; }

                $color = 'blue-tier';
                foreach ($tier_colors as $tc) {
                    if (in_array($tc, $classes)) { $color = $tc; break; }
                }
                $raw_url = get_post_meta($member['ID'], '_menu_item_url', true);
                $tier_id = sanitize_title(ltrim($raw_url, '#'));
                $tier_title = esc_html($member['title']);
                print "<h3 id='$tier_id' class='tier $color'>$tier_title</h3>";
            } else {
                // new-row: close current grid, will reopen below
                if ($is_new_row && $row_open) {
                    print "</div></div>";
                    $row_open = false;
                }
                if (!$row_open) {
                    print "<div class='partners-grid-wrap'><div class='partners-grid'>";
                    $row_open = true;
                }
                print "<div class='$className'>";
                  displayTeamMember($member, $className, $show_titles, $show_links);
                print "</div>";
            }
        }

        if ($row_open) { print "</div></div>"; }
        return $team;
    }
    function displayTeamMember($member, $className = '', $show_titles = false, $show_links = false){
        extract((array)$member);
        $is_partners = strpos($className, 'partners-page') !== false;

        $link = get_permalink($post->ID);
        $link_target = '';
        $link_rel = '';
        if ($is_partners) {
            // Match home page partner_list: website meta → menu item URL → permalink
            $partner_url = get_post_meta($post->ID, 'website', true);
            if (empty($partner_url)) {
                $partner_url = get_post_meta($ID, '_menu_item_url', true);
            }
            if (!empty($partner_url)) {
                $partner_url = preg_replace('#^(?:https?:)?(?://)+(?:https?:(?://)+)*#i', '', $partner_url);
                $link = 'https://' . $partner_url;
                $link_target = " target='_blank'";
                $link_rel = " rel='noopener'";
            }
        }
       $thumbnail_id = @$meta['_thumbnail_id'][0];
        $thumbnail = getThumbnail($thumbnail_id);
        $custom_class= @$classes[0];
        $link_title = ($is_partners) ? $attr_title : $title;
        // Partners always link (to their site); team members link to their
        // profile only when links="true".
        $use_link = $is_partners || $show_links;
       ?>

       <?php if ($is_partners) : ?>

        <?php // Partners — link wraps meta + title + logo, then description. ?>
        <?php if ($use_link) : ?><a href='<?=esc_url($link)?>'<?=$link_target?><?=$link_rel?>><?php endif; ?>
        <?php if (!empty($attr_title)) : ?><h6 class="partner-meta"><?=esc_html($attr_title)?></h6><?php endif; ?>
        <h3 class="partner-title"><?=esc_html($title)?></h3>
        <img class="<?=$custom_class?>" src="<?=$thumbnail?>" alt="<?=$title?>" title="<?=$title?>">
        <?php if ($use_link) : ?></a><?php endif; ?>
        <?php if (!empty($description)) : ?><p><?=wp_kses_post($description)?></p><?php endif; ?>
        <?php if (is_user_logged_in() && current_user_can('edit_post', $post->ID)) : ?>
            <a href="<?=esc_url(admin_url('post.php?post=' . $post->ID . '&action=edit'))?>" class="partner-edit-link" target="_blank" title="Edit this partner">&#9998;&#65039;</a>
        <?php endif; ?>

       <?php else : ?>

        <?php // Team member — photo, name, menu description, social icons (flex stack). ?>
        <?php if ($use_link) : ?><a href='<?=esc_url($link)?>' title='<?=esc_attr($link_title)?>'><?php endif; ?>
        <img class="<?=$custom_class?>" src="<?=$thumbnail?>" alt="<?=$title?>" title="<?=$title?>">
        <?php if ($use_link) : ?></a><?php endif; ?>
        <h4 class="team-member__name"><?=esc_html($title)?></h4>
        <?php if ($show_titles && !empty($attr_title)) : ?><h5 class="team-member__meta"><?=esc_html($attr_title)?></h5><?php endif; ?>
        <?php if (!empty($description)) : ?><p class="team-member__desc"><?=wp_kses_post($description)?></p><?php endif; ?>
        <h6 class="team-member__social"><div class="social-icons profile-meta"><?=displayProfileMeta($post->ID, true)?></div></h6>

       <?php endif; ?>

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

		// titles="true" shows each member's name + company (h4/h5); off by default.
		$show_titles = isset($atts['titles']) && filter_var($atts['titles'], FILTER_VALIDATE_BOOLEAN);
		// links="true" wraps each card in a link to the person's profile; off by default.
		$show_links = isset($atts['links']) && filter_var($atts['links'], FILTER_VALIDATE_BOOLEAN);

		ob_start();
       displayTeam($menu,$atts['class'],$show_titles,$show_links);

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
			// Sanitize URL: strip duplicate schemes, ensure single https://
			if (!empty($link_url)) {
				// Remove any leading scheme(s) — handles "https://https://", "://", etc.
				$link_url = preg_replace('#^(?:https?:)?(?://)+(?:https?:(?://)+)*#i', '', $link_url);
				$link_url = 'https://' . $link_url;
			}

			// Kicker from menu item attr_title (not linked)
			$kicker = !empty($item['attr_title']) ? $item['attr_title'] : '';

			// Menu item title (not post title)
			$title = !empty($item['title']) ? $item['title'] : '';

			// Featured image — use small 'partner-logo' size, strip srcset bloat
			$thumbnail_html = get_the_post_thumbnail($post->ID, 'partner-logo', array(
				'alt'    => $title,
				'title'  => $title,
				'srcset' => '',
				'sizes'  => '',
			));

			// Content: menu item description, or post_content via do_blocks
			$content_html = '';
			if (!empty($item['description'])) {
				$content_html = wp_kses_post($item['description']);
			} elseif (!empty($post->post_content)) {
				$content_html = do_blocks(do_shortcode($post->post_content));
			}

			// Open link wrapper if URL exists
			if (!empty($link_url)) {
				echo '<a class="partner-item-link" href="' . esc_url($link_url) . '" target="_blank" rel="noopener">';
			}

			echo '<div class="partner-item">';

			if (!empty($kicker)) {
				echo '<h5 class="partner-item-kicker">' . esc_html($kicker) . '</h5>';
			}

			if (!empty($thumbnail_html)) {
				echo '<div class="partner-item-image">' . $thumbnail_html . '</div>';
			}

			if (!empty($content_html)) {
				echo '<div class="partner-item-content">' . $content_html . '</div>';
			}

			echo '</div>';

			if (!empty($link_url)) {
				echo '</a>';
			}
		}

		echo '</div>';
		return ob_get_clean();
	}
	add_shortcode('partner_list', 'polys_partner_list_shortcode');

?>

