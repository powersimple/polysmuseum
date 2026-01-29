<?php
/**
 * Events Sidebar Template
 * 
 * Displays video embed at top, followed by upcoming and recent events.
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

$upcoming_events = events_sidebar_get_upcoming(8);
$recent_events = events_sidebar_get_recent(8);
?>

<aside class="events-sidebar">
    
    <div class="events-sidebar__video">
        <div id="events-sidebar-video-player" class="events-sidebar-video-player"></div>
        <?php 
        $default_embed_video_url = '';
        require get_template_directory() . '/templates/embed-video.php';
        ?>
    </div>
    
    <div class="events-sidebar__sections">
        
        <section class="events-sidebar__section events-sidebar__section--upcoming">
            <h3 class="events-sidebar__heading">Upcoming Events</h3>
            <?php events_sidebar_render_upcoming($upcoming_events); ?>
        </section>
        
        <section class="events-sidebar__section events-sidebar__section--recent">
            <h3 class="events-sidebar__heading">Recent Events</h3>
            <?php events_sidebar_render_recent($recent_events); ?>
        </section>
        
    </div>
    
</aside>
