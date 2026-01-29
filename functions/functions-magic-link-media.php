<?php
/**
 * Magic Link Media Restrictions
 * 
 * Restricts media library access for users in magic-link sessions.
 * When a user consumes a magic link, we store:
 *   - allowed_profile_post_id (int) - the post they can edit
 *   - allowed_until (timestamp) - when access expires
 * 
 * This file restricts media browsing to only show attachments uploaded
 * by the current user during these sessions.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if current user is in an active magic-link session
 * 
 * @return bool True if user is in magic-link window and not admin/editor
 */
function is_magic_link_session() {
    // Must be logged in
    if (!is_user_logged_in()) {
        return false;
    }
    
    $user_id = get_current_user_id();
    
    // Admins and editors are never restricted
    if (current_user_can('manage_options') || current_user_can('edit_others_posts')) {
        return false;
    }
    
    // Check for magic-link user meta
    $allowed_post_id = get_user_meta($user_id, 'allowed_profile_post_id', true);
    $allowed_until = get_user_meta($user_id, 'allowed_until', true);
    
    // Must have both meta values set
    if (empty($allowed_post_id) || empty($allowed_until)) {
        return false;
    }
    
    // Check if still within allowed window
    $now = time();
    $expiry = is_numeric($allowed_until) ? (int) $allowed_until : strtotime($allowed_until);
    
    if ($now > $expiry) {
        return false;
    }
    
    return true;
}

/**
 * Filter media modal (block editor / classic "Add Media") attachments
 * 
 * Uses ajax_query_attachments_args filter to restrict media library
 * in the media modal to only show user's own uploads.
 * 
 * @param array $query Query arguments for WP_Query
 * @return array Modified query arguments
 */
function magic_link_filter_media_modal($query) {
    if (is_magic_link_session()) {
        $query['author'] = get_current_user_id();
    }
    return $query;
}
add_filter('ajax_query_attachments_args', 'magic_link_filter_media_modal');

/**
 * Filter Media Library list table (upload.php)
 * 
 * Uses pre_get_posts to restrict the main query on upload.php
 * to only show user's own uploads during magic-link sessions.
 * 
 * @param WP_Query $query The query object
 */
function magic_link_filter_media_library($query) {
    // Only in admin
    if (!is_admin()) {
        return;
    }
    
    // Only on the main query
    if (!$query->is_main_query()) {
        return;
    }
    
    // Only for attachment queries (Media Library)
    $post_type = $query->get('post_type');
    if ($post_type !== 'attachment') {
        return;
    }
    
    // Apply restriction for magic-link sessions
    if (is_magic_link_session()) {
        $query->set('author', get_current_user_id());
    }
}
add_action('pre_get_posts', 'magic_link_filter_media_library');

/**
 * Also filter the media grid view AJAX request
 * 
 * The media grid uses a separate AJAX endpoint that may bypass
 * the standard pre_get_posts filter.
 * 
 * @param array $query Query arguments
 * @return array Modified query arguments
 */
function magic_link_filter_media_grid_query($query) {
    if (is_magic_link_session()) {
        $query['author'] = get_current_user_id();
    }
    return $query;
}
add_filter('ajax_query_attachments_args', 'magic_link_filter_media_grid_query', 20);

/**
 * Ensure uploaded media is assigned to the current user
 * 
 * This ensures that when a magic-link user uploads media,
 * it's properly attributed to them so they can see it.
 * 
 * @param array $post_data Post data for the attachment
 * @param array $postarr Original post array
 * @return array Modified post data
 */
function magic_link_set_upload_author($post_data, $postarr) {
    // Only for new attachments
    if ($post_data['post_type'] !== 'attachment') {
        return $post_data;
    }
    
    // Only during magic-link sessions
    if (is_magic_link_session()) {
        // Ensure the author is set to current user
        $post_data['post_author'] = get_current_user_id();
    }
    
    return $post_data;
}
add_filter('wp_insert_post_data', 'magic_link_set_upload_author', 10, 2);

/**
 * Filter attachment counts in Media Library
 * 
 * Adjusts the media counts shown in the filter dropdown
 * to reflect only the user's own uploads during magic-link sessions.
 * 
 * @param object $counts Attachment counts by mime type
 * @return object Modified counts
 */
function magic_link_filter_media_counts($counts) {
    if (!is_magic_link_session()) {
        return $counts;
    }
    
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Get counts for current user only
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT post_mime_type, COUNT(*) as count
        FROM {$wpdb->posts}
        WHERE post_type = 'attachment'
        AND post_status != 'trash'
        AND post_author = %d
        GROUP BY post_mime_type
    ", $user_id), OBJECT_K);
    
    // Build new counts object
    $new_counts = new stdClass();
    $new_counts->trash = 0;
    
    // Get trash count for user
    $trash_count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->posts}
        WHERE post_type = 'attachment'
        AND post_status = 'trash'
        AND post_author = %d
    ", $user_id));
    $new_counts->trash = (int) $trash_count;
    
    // Copy over mime type counts
    foreach ($results as $mime => $row) {
        $new_counts->$mime = (int) $row->count;
    }
    
    return $new_counts;
}
add_filter('wp_count_attachments', 'magic_link_filter_media_counts');

/**
 * Add admin notice for magic-link users about media restrictions
 */
function magic_link_media_admin_notice() {
    $screen = get_current_screen();
    
    // Only on media screens
    if (!$screen || $screen->base !== 'upload') {
        return;
    }
    
    if (is_magic_link_session()) {
        echo '<div class="notice notice-info"><p>';
        echo '<strong>Media Library Restricted:</strong> You are viewing only media files you have uploaded. ';
        echo 'This restriction is in place during your profile editing session.';
        echo '</p></div>';
    }
}
add_action('admin_notices', 'magic_link_media_admin_notice');
