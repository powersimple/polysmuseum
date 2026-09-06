<?php
/**
 * Events Sidebar Functions
 * 
 * Provides queries and rendering for the events sidebar component.
 * 
 * NOTE: Sidebar caching has been intentionally disabled/removed.
 * To re-enable caching later, add transient get/set calls in the query functions.
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// SCRIPTS AND STYLES
// =============================================================================

/**
 * Enqueue sidebar video script and dashicons
 */
function events_sidebar_enqueue_scripts() {
    // Enqueue dashicons for action icons (already available in admin, need for frontend)
    wp_enqueue_style('dashicons');
    
    // Enqueue sidebar video script
    wp_enqueue_script(
        'events-sidebar-video',
        get_template_directory_uri() . '/assets/js/sidebar-video.js',
        [],
        filemtime(get_template_directory() . '/assets/js/sidebar-video.js'),
        true
    );
}
add_action('wp_enqueue_scripts', 'events_sidebar_enqueue_scripts');

// =============================================================================
// QUERIES
// =============================================================================

/**
 * Get upcoming events
 * 
 * @param int $limit Number of events to fetch
 * @return array Array of event post objects
 */
function events_sidebar_get_upcoming($limit = 8) {
    // No caching - queries run fresh each page load
    $now = current_time('timestamp');
    
    $args = [
        'post_type' => 'event',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'meta_key' => 'utc_start',
        'orderby' => 'meta_value_num',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => 'utc_start',
                'value' => $now,
                'compare' => '>',
                'type' => 'NUMERIC'
            ]
        ]
    ];
    
    $query = new WP_Query($args);
    return $query->posts;
}

/**
 * Get recent events (within last 18 months)
 * 
 * @param int $limit Number of events to fetch
 * @return array Array of event post objects
 */
function events_sidebar_get_recent($limit = 8) {
    // No caching - queries run fresh each page load
    $now = current_time('timestamp');
    $window_start = strtotime('-18 months', $now);
    
    $args = [
        'post_type' => 'event',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'meta_key' => 'utc_start',
        'orderby' => 'meta_value_num',
        'order' => 'DESC',
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => 'utc_start',
                'value' => $window_start,
                'compare' => '>=',
                'type' => 'NUMERIC'
            ],
            [
                'key' => 'utc_start',
                'value' => $now,
                'compare' => '<=',
                'type' => 'NUMERIC'
            ]
        ]
    ];
    
    $query = new WP_Query($args);
    return $query->posts;
}

// =============================================================================
// RENDERING
// =============================================================================

/**
 * Get the first video URL from post meta
 * 
 * Searches for any meta key containing "video" (case-insensitive) with a non-empty URL value.
 * 
 * @param int $post_id Post ID
 * @return string|null Video URL or null if none found
 */
function events_sidebar_get_video_url($post_id) {
    $all_meta = get_post_meta($post_id);
    
    foreach ($all_meta as $key => $values) {
        if (stripos($key, 'video') !== false) {
            $value = is_array($values) ? reset($values) : $values;
            if (!empty($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                return $value;
            }
        }
    }
    
    return null;
}

/**
 * Check if an event is in the future based on utc_start
 * 
 * @param int $post_id Post ID
 * @return bool True if event is in the future
 */
function events_sidebar_is_future_event($post_id) {
    $utc_start = get_post_meta($post_id, 'utc_start', true);
    
    if (empty($utc_start)) {
        return false;
    }
    
    // Parse as numeric timestamp or datetime string
    if (is_numeric($utc_start)) {
        $timestamp = (int) $utc_start;
    } else {
        $timestamp = strtotime($utc_start);
        if ($timestamp === false) {
            return false;
        }
    }
    
    return $timestamp > time();
}

/**
 * Render a single event item
 * 
 * @param WP_Post $event Event post object
 * @param bool $featured Whether this is the featured/next upcoming event
 */
function events_sidebar_render_item($event, $featured = false) {
    $post_id = $event->ID;
    $permalink = get_permalink($post_id);
    $title = get_the_title($post_id);
    
    $utc_start = (int) get_post_meta($post_id, 'utc_start', true);
    $date_display = $utc_start ? wp_date('M j, Y', $utc_start) : '';
    
    $categories = get_the_category($post_id);
    $primary_cat = !empty($categories) ? $categories[0]->name : '';
    
    $tags = get_the_tags($post_id);
    $tag_list = [];
    if ($tags && count($tags) <= 3) {
        foreach ($tags as $tag) {
            $tag_list[] = $tag->name;
        }
    }
    
    // Check for video URL
    $video_url = events_sidebar_get_video_url($post_id);
    
    // Check for tickets URL (only for future events)
    $tickets_url = get_post_meta($post_id, 'tickets_url', true);
    $is_future = events_sidebar_is_future_event($post_id);
    $show_tickets = !empty($tickets_url) && $is_future;
    
    // Check if user can edit
    $can_edit = is_user_logged_in() && current_user_can('edit_post', $post_id);
    
    $item_class = 'events-sidebar-item';
    if ($featured) {
        $item_class .= ' events-sidebar-item--featured';
    }
    ?>
    <div class="<?php echo esc_attr($item_class); ?>">
        <!-- Thumbnail first -->
        <a href="<?php echo esc_url($permalink); ?>" class="events-sidebar-item__thumb-link">
            <div class="events-sidebar-item__thumb">
                <?php if (has_post_thumbnail($post_id)): ?>
                    <?php echo get_the_post_thumbnail($post_id, 'medium', ['class' => 'events-sidebar-item__img']); ?>
                <?php else: ?>
                    <div class="events-sidebar-item__placeholder"></div>
                <?php endif; ?>
            </div>
        </a>
        
        <!-- Title second -->
        <a href="<?php echo esc_url($permalink); ?>" class="events-sidebar-item__title-link">
            <div class="events-sidebar-item__title"><?php echo esc_html($title); ?></div>
        </a>
        
        <!-- Meta block third -->
        <div class="events-sidebar-item__meta">
            <?php if ($video_url || $show_tickets): ?>
                <div class="events-sidebar-item__links">
                    <?php if ($video_url): ?>
                        <a href="<?php echo esc_url($video_url); ?>" 
                           class="events-sidebar-item__watch-video"
                           data-video-url="<?php echo esc_attr($video_url); ?>"
                           data-post-id="<?php echo esc_attr($post_id); ?>"
                           title="Watch Video">Watch Video</a>
                    <?php endif; ?>
                    <?php if ($video_url && $show_tickets): ?> | <?php endif; ?>
                    <?php if ($show_tickets): ?>
                        <a href="<?php echo esc_url($tickets_url); ?>" 
                           class="events-sidebar-item__get-tickets"
                           target="_blank" 
                           rel="noopener"
                           title="Get Tickets">Get Tickets</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($date_display): ?>
                <div class="events-sidebar-item__date"><?php echo esc_html($date_display); ?></div>
            <?php endif; ?>
            <?php if ($primary_cat): ?>
                <div class="events-sidebar-item__cat"><?php echo esc_html($primary_cat); ?></div>
            <?php endif; ?>
            <?php if (!empty($tag_list)): ?>
                <div class="events-sidebar-item__tags"><?php echo esc_html(implode(', ', $tag_list)); ?></div>
            <?php endif; ?>
        </div>
        
        <?php if ($can_edit): ?>
            <a href="<?php echo esc_url(admin_url('post.php?post=' . $post_id . '&action=edit')); ?>" 
               class="events-sidebar-item__edit-icon"
               target="_blank" 
               rel="noopener"
               aria-label="Edit post"
               title="Edit post">✎</a>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render the upcoming events section
 * 
 * @param array $events Array of event posts
 */
function events_sidebar_render_upcoming($events) {
    if (empty($events)) {
        echo '<p class="events-sidebar-empty">No upcoming events.</p>';
        return;
    }
    
    $first = array_shift($events);
    
    echo '<div class="events-sidebar-featured">';
    events_sidebar_render_item($first, true);
    echo '</div>';
    
    if (!empty($events)) {
        echo '<div class="events-sidebar-list events-sidebar-list--upcoming">';
        foreach ($events as $event) {
            events_sidebar_render_item($event, false);
        }
        echo '</div>';
    }
}

/**
 * Render the recent events section
 * 
 * @param array $events Array of event posts
 */
function events_sidebar_render_recent($events) {
    if (empty($events)) {
        echo '<p class="events-sidebar-empty">No recent events.</p>';
        return;
    }
    
    echo '<div class="events-sidebar-list events-sidebar-list--recent">';
    foreach ($events as $event) {
        events_sidebar_render_item($event, false);
    }
    echo '</div>';
}

/**
 * Render a curated sidebar menu (the `sidbebar_menu` metabox value) as a vertical
 * stack of items. Shared by templates/sidebar-events.php and single-event.php so
 * the markup stays identical wherever the sidebar menu appears.
 *
 * @param int|string $menu_id Nav menu ID (term_id) or name/slug accepted by
 *                            wp_get_nav_menu_items().
 * @return string HTML, or '' when the menu is empty/unset.
 */
function render_curated_sidebar_menu($menu_id) {
    if (empty($menu_id)) {
        return '';
    }
    $menu_items = wp_get_nav_menu_items($menu_id);
    if (empty($menu_items)) {
        return '';
    }

    ob_start();
    ?>
    <div class="sidebar-curated">
        <?php foreach ($menu_items as $menu_item):
            $item_post_id = (int) $menu_item->object_id;
            $item_post = ($menu_item->type === 'post_type' && $item_post_id) ? get_post($item_post_id) : null;
            $item_title = !empty($menu_item->title) ? $menu_item->title : ($item_post ? get_the_title($item_post_id) : '');
            // Menu item "Description" field — shown as a caption/tagline under the
            // partner (NOT the post_content, which is long and stretches the column).
            $item_desc  = !empty($menu_item->description) ? trim($menu_item->description) : '';

            // Carry the menu item's own CSS classes (e.g. tier colours like
            // "purple-tier", "gold-tier" from _profile.scss) onto the item.
            $item_class_arr = (!empty($menu_item->classes) && is_array($menu_item->classes)) ? $menu_item->classes : array();
            $item_classes = 'sidebar-item';
            $extra = implode(' ', array_filter(array_map('sanitize_html_class', $item_class_arr)));
            if ($extra !== '') {
                $item_classes .= ' ' . $extra;
            }

        ?>
        <div class="<?php echo esc_attr($item_classes); ?>">
            <?php if ($item_post && has_post_thumbnail($item_post_id)): ?>
            <div class="sidebar-item-image">
                <?php // Partner name goes into the image's title + alt (no visible title row).
                echo get_the_post_thumbnail($item_post_id, 'medium', array(
                    'class' => 'sidebar-item-img',
                    'alt'   => $item_title,
                    'title' => $item_title,
                )); ?>
            </div>
            <?php endif; ?>
            <?php if ($item_desc !== ''): ?>
            <div class="sidebar-item-desc"><?php echo esc_html($item_desc); ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
