<?php
    get_header();

    // Source event menus for the appearance index. Add new event menus here.
    $events = "virtual-red-carpet-4,virtual-red-carpet-3,virtual-red-carpet-2,virtual-red-carpet-1,polys1,polys2,polys3,polys4,meet-the-makers,devsummit21,bizsummit21,designsummit21,edsummit22,brandsummit22,prodsummit22,special-editions,wolviclaunch,metatraversal1,metatraversal2,metatraversal3,metatraversal4,metatraversal-a-day-in-the-life,metatraversal9";

    $default_video_url = get_post_meta($post->ID, "featured_video_url", true);
    $sidebar_list      = get_post_meta($post->ID, "sidebar_list", true);

    // The heavy index walk runs ONLY on an admin rebuild; every visitor just reads
    // the small static data/profile-index.json (server-rendered below).
    if ( current_user_can('manage_options') ) {
        if ( isset($_GET['rebuild-index']) && function_exists('rebuild_profile_index') ) {
            $rebuilt = rebuild_profile_index($events);
            if ( $rebuilt ) {
                echo '<div style="padding:8px;background:#d7f5d7;border:1px solid #7bc47b;margin:8px 0;font:13px/1.4 sans-serif;">Rebuilt <code>data/profile-index.json</code> — ' . intval($rebuilt['count']) . ' profiles.</div>';
            }
        }
        // Legacy heavy publishers, kept for back-compat.
        if ( isset($_GET['publish-index']) ) {
            $lists = eventIndex($events);
            publishThis('index', $lists);
        }
        if ( isset($_GET['publish-profiles']) ) {
            if ( ! isset($lists) ) { $lists = eventIndex($events); }
            publishProfiles('index', $lists);
        }
        // Prompt to rebuild when content changed since the last build.
        if ( ! isset($_GET['rebuild-index']) && function_exists('profile_index_is_stale') && profile_index_is_stale() ) {
            echo '<div style="padding:8px;background:#fff3cd;border:1px solid #ffc107;margin:8px 0;font:13px/1.4 sans-serif;">The appearance index may be out of date. <a href="?rebuild-index">Rebuild now</a>.</div>';
        }
    }
?>
<main role="main" class="main <?=@$section_class?>" id="events-index-page">
    <div class="d-flex container-flex">
        <div class="col-md-7 left" id="profile-videos">
            <?php // Alphabetical appearance directory — server-rendered from the lean JSON. ?>
            <div id="profile-index"><?php echo render_profile_index_html(); ?></div>
        </div>
        <div class="col-md-5 right">
            <?php
                // Appearances sidebar (under the video) — server-rendered when a
                // Sidebar List name is set; embed-video.php prints it into its own
                // #appearances, and clicks refresh it. No duplicate container.
                $appearances_html = render_appearances_html($sidebar_list);
                $show_player = ($default_video_url == '');
                require_once('templates/embed-video.php');
            ?>
        </div>
    </div>
</main>
<?php
    get_footer();
?>
