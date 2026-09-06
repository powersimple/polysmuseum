<?php
/**
 * Events Sidebar Template
 * 
 * Displays video embed at top, followed by upcoming and recent events.
 * If the page has a `sidbebar_menu` metabox value, renders curated menu
 * items in a single vertical stack instead of Upcoming/Recent sections.
 * 
 * Include via: get_template_part('templates/sidebar-events');
 * 
 * Expected variables (optional, will use defaults):
 * - $default_video_url: Video URL for embed
 * - $video_playlist: Video playlist menu slug
 * - $show_player: Whether to show video player
 */

if (!defined('ABSPATH')) {
    exit;
}

global $post;

if (!isset($default_video_url)) {
    $default_video_url = get_post_meta($post->ID, 'featured_video_url', true);
}
if (!isset($video_playlist)) {
    $video_playlist = get_post_meta($post->ID, 'video_playlist', true);
}
if (!isset($show_player)) {
    $show_player = true;
}

// ── Sidebar menu override ──
// If the page has a curated sidebar menu assigned, use it instead of Upcoming/Recent.
$sidebar_menu_id = get_post_meta($post->ID, 'sidbebar_menu', true);
$has_sidebar_menu = !empty($sidebar_menu_id);

// ── Determine if video block should render ──
$has_video = !empty($default_video_url) && filter_var($default_video_url, FILTER_VALIDATE_URL);
if (!$has_video && !empty($default_embed_video_url) && filter_var($default_embed_video_url, FILTER_VALIDATE_URL)) {
    $has_video = true;
}

// If no sidebar menu and no events content, bail early
if (!$has_sidebar_menu) {
    $upcoming_events = events_sidebar_get_upcoming(8);
    $recent_events = events_sidebar_get_recent(8);
}
?>

<aside class="events-sidebar">
    
    <?php if ($has_video): ?>
    <div class="events-sidebar__video">
        <div id="events-sidebar-video-player" class="events-sidebar-video-player"></div>
        <?php 
        if (!isset($default_embed_video_url)) {
            $default_embed_video_url = '';
        }
        require get_template_directory() . '/templates/embed-video.php';
        ?>
    </div>
    <?php endif; ?>
    
    <div class="events-sidebar__sections">
        
        <?php if ($has_sidebar_menu): ?>
            <?php echo render_curated_sidebar_menu($sidebar_menu_id); ?>

        <?php else: ?>
            
            <section class="events-sidebar__section events-sidebar__section--upcoming">
                <h3 class="events-sidebar__heading">Upcoming Events</h3>
                <?php events_sidebar_render_upcoming($upcoming_events); ?>
            </section>
            
            <section class="events-sidebar__section events-sidebar__section--recent">
                <h3 class="events-sidebar__heading">Recent Events</h3>
                <?php events_sidebar_render_recent($recent_events); ?>
            </section>
            
        <?php endif; ?>
        
    </div>
    
</aside>
