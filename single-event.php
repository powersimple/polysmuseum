<?php
/**
 * Single Event Template - PHP-based rendering
 * 
 * This template replaces the JavaScript/JSON-based approach with direct PHP rendering
 * using WordPress menu data (section_menu) for the Event->Session->Profiles hierarchy.
 * 
 * Data Structure:
 * - Event (top level menu item)
 *   - Session (child of event) 
 *     - Profile (child of session) with metadata
 * 
 * Key Features:
 * - Video player with clickable session links to switch videos
 * - Profile cards with thumbnails and social links
 * - No dependency on content.json
 */

/**
 * Format video URL with autoplay parameters
 */
if (!function_exists('event_format_video_url')) {
    function event_format_video_url($url) {
        if (empty($url)) return '';
        
        // Add autoplay and rel params if not present
        if (strpos($url, '?') !== false) {
            if (strpos($url, 'autoplay') === false) {
                $url .= '&autoplay=1&rel=0';
            }
        } else {
            $url .= '?autoplay=1&rel=0';
        }
        return $url;
    }
}

get_header();

// Get event metadata
$default_video_url = get_post_meta($post->ID, "embed_video_url", true);
$session_type = get_post_meta($post->ID, "session_type", true);
$section_menu = get_post_meta($post->ID, "section_menu", true);
$section_class = get_post_meta($post->ID, "section_class", true);
$sponsor_board = get_post_meta($post->ID, "sponsor_board", true);

// Get the run of show data from the menu if section_menu is set
$run_of_show = null;
$first_video_url = $default_video_url;
if (!empty($section_menu)) {
    $run_of_show = get_menu_array($section_menu);
    // Find first session with video for initial player
    if (!empty($run_of_show)) {
        foreach ($run_of_show as $event) {
            if (!empty($event['children'])) {
                foreach ($event['children'] as $session) {
                    $session_video = @$session['meta']['embed_video_url'][0];
                    if (!empty($session_video) && empty($first_video_url)) {
                        $first_video_url = $session_video;
                        break 2;
                    }
                }
            }
        }
    }
}

// Fallback: use the home page's featured video if no video URL found
if (empty($first_video_url)) {
    $front_page_id = get_option('page_on_front');
    if ($front_page_id) {
        $first_video_url = get_post_meta($front_page_id, 'featured_video_url', true);
    }
}

// Ensure video URL has autoplay params
if (!empty($first_video_url)) {
    $first_video_url = event_format_video_url($first_video_url);
}

/**
 * Render profile card HTML
 */
if (!function_exists('render_event_profile_card')) {
    function render_event_profile_card($profile) {
        $meta = $profile['meta'];
        $thumbnail = getThumbnail(@$meta['_thumbnail_id'][0], 'thumbnail');
        $title = esc_html($profile['title']);
        $profile_title = @$meta['profile_title'][0];
        $company = @$meta['company'][0];
        $twitter = @$meta['twitter'][0];
        $linkedin = @$meta['linkedin'][0];
        $github = @$meta['github'][0];
        $classes = is_array($profile['classes']) ? implode(' ', $profile['classes']) : $profile['classes'];
        
        ob_start();
        ?>
        <div class="profile-card col <?php echo esc_attr($classes); ?>">
            <?php if ($thumbnail): ?>
            <div class="profile-thumbnail">
                <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo $title; ?>" title="<?php echo $title; ?>">
            </div>
            <?php endif; ?>
            <span class="profile-info">
                <span class="profile-name"><?php echo $title; ?></span>
                <?php if ($profile_title || $company): ?>
                <span class="credential">
                    <?php if ($profile_title): ?>
                    <span><?php echo esc_html(trim($profile_title)); ?></span>
                    <?php endif; ?>
                    <?php if ($company): ?>
                    <span><?php echo esc_html(trim($company)); ?></span>
                    <?php endif; ?>
                </span>
                <?php endif; ?>
                <?php if ($twitter || $linkedin || $github): ?>
                <span class="social">
                    <?php if ($twitter): ?>
                    <a target="_blank" class="twitter" href="<?php echo esc_url($twitter); ?>">
                        <i class="fa-brands fa-x-twitter social-icon" title="<?php echo $title; ?> on Twitter"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($linkedin): ?>
                    <a target="_blank" class="linkedin" href="<?php echo esc_url($linkedin); ?>">
                        <i class="fa-brands fa-linkedin social-icon" title="<?php echo $title; ?> on LinkedIn"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($github): ?>
                    <a target="_blank" class="github" href="<?php echo esc_url($github); ?>">
                        <i class="fa-brands fa-github social-icon" title="<?php echo $title; ?> on GitHub"></i>
                    </a>
                    <?php endif; ?>
                </span>
                <?php endif; ?>
            </span>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * Render a session row with profiles
 */
if (!function_exists('render_event_session_row')) {
    function render_event_session_row($session, $is_past_event = false) {
        $session_title = esc_html($session['title']);
        $session_slug = $session['slug'];
        $session_content = @$session['post']->post_content;
        $session_video = @$session['meta']['embed_video_url'][0];
        $classes = is_array($session['classes']) ? implode(' ', $session['classes']) : @$session['classes'];
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($session_slug); ?>" class="row session <?php echo esc_attr($classes); ?>">
            <div class="col-sm-3 col-md-2">
                <?php if ($is_past_event && !empty($session_video)): ?>
                <a href="#<?php echo esc_attr($session_slug); ?>" 
                   class="watch video-button" 
                   onclick="playSessionVideo('<?php echo esc_js(event_format_video_url($session_video)); ?>','<?php echo esc_js($session_title); ?>','')">
                    <i title="WATCH" class="fa-brands fa-youtube"></i><br> Watch
                </a>
                <?php endif; ?>
            </div>
            <div class="col-sm-9 col-md-10">
                <h3 class="session-title"><?php echo $session_title; ?></h3>
                <?php if (!empty($session_content)): ?>
                <div class="session-content"><?php echo do_blocks($session_content); ?></div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($session['children'])): ?>
        <div class="row">
            <div class="col-12">
                <div class="row speaker-list">
                    <?php foreach ($session['children'] as $profile): ?>
                        <?php echo render_event_profile_card($profile); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }
}

/**
 * Render the full schedule from menu data
 */
if (!function_exists('render_event_schedule')) {
    function render_event_schedule($run_of_show, $section_class = '') {
        if (empty($run_of_show)) return '';
        
        // Default to past event (shows Watch buttons)
        $is_past_event = true;
        
        ob_start();
        ?>
        <div id="schedule" class="<?php echo esc_attr($section_class); ?>">
            <?php foreach ($run_of_show as $event): ?>
                <?php if (!empty($event['children'])): ?>
                    <?php foreach ($event['children'] as $session): ?>
                        <?php echo render_event_session_row($session, $is_past_event); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * Display Looking Glass embeds if configured
 */
if (!function_exists('display_LookingGlass')) {
    function display_LookingGlass($post_id) {
        $trophy_embed_id = get_post_meta($post_id, "looking_glass_embed_trophy", true);
        $trophy_base_embed_id = get_post_meta($post_id, "looking_glass_embed_trophy_base", true);
        
        if (!empty(trim($trophy_embed_id)) && !empty(trim($trophy_base_embed_id))) {
            ?>
            <div class="row">
                <?php if (!empty($trophy_embed_id)): ?>
                <div class="col col-sm-6">
                    <?php embed_LKBlock_by_id($trophy_embed_id); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($trophy_base_embed_id)): ?>
                <div class="col col-sm-6">
                    <?php embed_LKBlock_by_id($trophy_base_embed_id); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php
        }
    }
}
?>

<main id="main" class="main <?php echo esc_attr($section_class); ?>" role="main">
    <div class="d-flex container-flex">
        <div class="col-md-7 left event-post">
            <?php
            // Output post content
            print do_blocks($post->post_content);
            ?>
            
            <!-- Schedule/Run of Show -->
            <div id="ros-table">
                <?php
                if (!empty($section_menu)) {
                    require_once "functions/functions-awards.php";
                    $awards = get_menu_array($section_menu);
                    require_once('templates/awards.php');
                }
                ?>
            </div>
            
            <?php
            // Looking Glass embeds (if configured)
            display_LookingGlass($post->ID);
            ?>
        </div>

        <div class="col-md-5 right">
            <div class="sticky">
                <?php if (!empty($first_video_url)): ?>
                <div class="video-position">
                    <div id="video-wrap-header"></div>
                    <div class="video-wrap">
                        <iframe id="video-player"
                                src="<?php echo esc_url($first_video_url); ?>"
                                frameborder="0"
                                allow="autoplay; encrypted-media"
                                allowfullscreen></iframe>
                    </div>
                    <div id="video-wrap-footer"></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($sponsor_board)): ?>
                <div class="sponsor-board">
                    <img src="<?php echo esc_url(getThumbnail($sponsor_board, 'medium_large')); ?>" alt="Sponsors" />
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php
get_footer();
?>

<script>
// Override playSessionVideo for PHP-rendered events (must be after footer scripts)
window.playSessionVideo = function(src, title, attrs) {
    var player = document.getElementById('video-player');
    if (player && src) {
        // Ensure autoplay params
        if (src.indexOf('?') === -1) {
            src += '?autoplay=1&rel=0';
        } else if (src.indexOf('autoplay') === -1) {
            src += '&autoplay=1&rel=0';
        }
        player.src = src;
    }
    var header = document.getElementById('video-wrap-header');
    if (header && title) {
        header.innerHTML = '';
        var h4 = document.createElement('h4');
        h4.className = 'video-title';
        h4.textContent = title;
        header.appendChild(h4);
    }
};
</script>
