<?php
/**
 * Profile Authentication Functions
 * 
 * Handles authentication for the external profile admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize the profile authentication system
 */
function init_profile_auth() {
    // Create the auth table if it doesn't exist
    global $wpdb;
    $table_name = $wpdb->prefix . 'profile_auth';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            profile_id bigint(20) NOT NULL,
            email varchar(255) NOT NULL,
            auth_token varchar(64),
            token_expiry datetime,
            last_login datetime,
            created_at datetime,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY profile_id (profile_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
add_action('init', 'init_profile_auth');

/**
 * Check if a profile is currently authenticated
 */
function is_profile_authenticated() {
    if (isset($_COOKIE['profile_auth_token'])) {
        $token = sanitize_text_field($_COOKIE['profile_auth_token']);
        return verify_auth_token($token);
    }
    return false;
}

/**
 * Get the authenticated profile ID
 */
function get_authenticated_profile_id() {
    if (isset($_COOKIE['profile_auth_token'])) {
        $token = sanitize_text_field($_COOKIE['profile_auth_token']);
        return get_profile_id_from_token($token);
    }
    return false;
}

/**
 * Send a magic link to the profile's email
 */
function send_profile_magic_link($profile_id, $email) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'profile_auth';
    
    // Generate a secure token
    $token = wp_generate_password(64, false);
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Store the token
    $wpdb->replace(
        $table_name,
        array(
            'profile_id' => $profile_id,
            'email' => $email,
            'auth_token' => $token,
            'token_expiry' => $expiry,
            'created_at' => current_time('mysql')
        ),
        array('%d', '%s', '%s', '%s', '%s')
    );
    
    // Generate the magic link
    $magic_link = add_query_arg(array(
        'action' => 'profile_auth',
        'token' => $token
    ), home_url('/admin-profile/'));
    
    // Send the email
    $subject = 'Your Profile Admin Access Link';
    $message = sprintf(
        'Click the following link to access your profile admin page. This link will expire in 1 hour:\n\n%s',
        $magic_link
    );
    
    wp_mail($email, $subject, $message);
    
    return true;
}

/**
 * Verify an authentication token
 */
function verify_auth_token($token) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'profile_auth';
    
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name 
        WHERE auth_token = %s 
        AND token_expiry > %s",
        $token,
        current_time('mysql')
    ));
    
    if ($result) {
        // Update last login
        $wpdb->update(
            $table_name,
            array('last_login' => current_time('mysql')),
            array('id' => $result->id),
            array('%s'),
            array('%d')
        );
        return true;
    }
    
    return false;
}

/**
 * Get profile ID from token
 */
function get_profile_id_from_token($token) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'profile_auth';
    
    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT profile_id FROM $table_name 
        WHERE auth_token = %s 
        AND token_expiry > %s",
        $token,
        current_time('mysql')
    ));
    
    return $result ? intval($result) : false;
}

/**
 * Handle magic link authentication
 */
function handle_profile_auth() {
    if (isset($_GET['action']) && $_GET['action'] === 'profile_auth' && isset($_GET['token'])) {
        $token = sanitize_text_field($_GET['token']);
        
        if (verify_auth_token($token)) {
            // Set the auth cookie
            setcookie('profile_auth_token', $token, time() + (86400 * 30), '/', '', true, true);
            
            // Redirect to remove the token from URL
            wp_redirect(home_url('/admin-profile/'));
            exit;
        }
    }
}
add_action('template_redirect', 'handle_profile_auth');

/**
 * Log out a profile
 */
function logout_profile() {
    if (isset($_COOKIE['profile_auth_token'])) {
        setcookie('profile_auth_token', '', time() - 3600, '/', '', true, true);
    }
    wp_redirect(home_url('/admin-profile/'));
    exit;
} 