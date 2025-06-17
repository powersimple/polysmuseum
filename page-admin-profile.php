<?php
/**
 * Template Name: Admin Profile
 * 
 * External admin interface for profile editing
 */

// Include required functions
require_once get_template_directory() . '/functions/functions-profile-auth.php';
require_once get_template_directory() . '/functions/functions-profile-edit.php';

// Initialize profile auth
init_profile_auth();

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout_profile();
    wp_redirect(get_profile_login_url());
    exit;
}

// Handle profile update
$update_result = handle_profile_update();
if ($update_result) {
    $message = $update_result['message'];
    $message_type = $update_result['type'];
}

// Get profile ID from URL
$profile_id = isset($_GET['profile_id']) ? intval($_GET['profile_id']) : 0;

// Check if user is authenticated or is admin
$is_authenticated = is_profile_authenticated() || current_user_can('administrator');
$can_edit = $is_authenticated && $profile_id > 0;

get_header();
?>

<div class="admin-profile-page">
    <?php if ($can_edit): ?>
        <?php get_template_part('templates/admin-profile/edit'); ?>
    <?php else: ?>
        <?php get_template_part('templates/admin-profile/login'); ?>
    <?php endif; ?>
</div>

<?php get_footer(); ?> 