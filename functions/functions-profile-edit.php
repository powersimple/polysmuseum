<?php
/**
 * Profile Edit Functions
 */

/**
 * Handle profile update form submission
 */
function handle_profile_update() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'update_profile') {
        return;
    }

    if (!wp_verify_nonce($_POST['_wpnonce'], 'update_profile')) {
        wp_die('Invalid nonce');
    }

    $profile_id = intval($_POST['profile_id']);
    
    // Verify user has permission to edit this profile
    if (!can_edit_profile($profile_id)) {
        wp_die('You do not have permission to edit this profile');
    }

    // Update basic profile data
    $title = sanitize_text_field($_POST['title']);
    $content = wp_kses_post($_POST['content']);
    
    wp_update_post([
        'ID' => $profile_id,
        'post_title' => $title,
        'post_content' => $content
    ]);

    // Update profile meta fields
    $meta_fields = [
        // Contact Information
        'profile_email',
        'contact_email',
        'phone',
        'profile_title',
        'company',
        
        // Address
        'address',
        'address2',
        'city',
        'state',
        'postal_code',
        'country',
        
        // Social Media & Links
        'website',
        'profile_linkedin',
        'profile_twitter',
        'profile_facebook',
        'profile_instagram',
        'profile_wikipedia'
    ];

    foreach ($meta_fields as $field) {
        if (isset($_POST[$field])) {
            $value = sanitize_text_field($_POST[$field]);
            if (strpos($field, 'url') !== false || in_array($field, ['website', 'profile_linkedin', 'profile_twitter', 'profile_facebook', 'profile_instagram', 'profile_wikipedia'])) {
                $value = esc_url_raw($value);
            }
            update_post_meta($profile_id, $field, $value);
        }
    }

    // Handle profile image
    if (isset($_POST['profile_image_id'])) {
        $image_id = intval($_POST['profile_image_id']);
        if ($image_id > 0) {
            set_post_thumbnail($profile_id, $image_id);
        } else {
            delete_post_thumbnail($profile_id);
        }
    }

    // Handle hero image
    if (isset($_POST['hero_image_id'])) {
        $hero_id = intval($_POST['hero_image_id']);
        if ($hero_id > 0) {
            // Update hero image using Meta Box
            update_post_meta($profile_id, 'hero', [$hero_id]);
        } else {
            delete_post_meta($profile_id, 'hero');
        }
    }

    return [
        'message' => 'Profile updated successfully',
        'type' => 'success'
    ];
}

/**
 * Check if current user can edit a profile
 */
function can_edit_profile($profile_id) {
    // Admin users can edit any profile
    if (current_user_can('administrator')) {
        return true;
    }

    // Check if profile is authenticated
    if (is_profile_authenticated()) {
        $authenticated_id = get_authenticated_profile_id();
        return $authenticated_id === $profile_id;
    }

    return false;
}

/**
 * Get profile edit URL
 */
function get_profile_edit_url($profile_id) {
    return add_query_arg([
        'profile_id' => $profile_id
    ], home_url('/admin-profile/'));
}

/**
 * Get profile login URL
 */
function get_profile_login_url() {
    return home_url('/admin-profile/');
}

/**
 * Get profile logout URL
 */
function get_profile_logout_url() {
    return add_query_arg('action', 'logout', home_url('/admin-profile/'));
} 