<?php
/**
 * Profile Edit Functions
 * 
 * Includes:
 * - Email gate form handler with magic link generation
 * - Profile update form handler
 * - Dev environment magic link display
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// ENVIRONMENT DETECTION
// =============================================================================

/**
 * Check if we're in a local/development environment
 * 
 * @return bool True if local/dev environment
 */
function is_profile_edit_dev_environment() {
    // Check WP_ENVIRONMENT_TYPE first (preferred)
    if (function_exists('wp_get_environment_type')) {
        $env = wp_get_environment_type();
        if (in_array($env, ['local', 'development'], true)) {
            return true;
        }
    }
    
    // Fallback: WP_DEBUG + known local hostnames
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $local_hosts = ['localhost', '127.0.0.1', 'obi-wan-v', 'polys.local', 'polys.test'];
        
        foreach ($local_hosts as $local) {
            if (stripos($host, $local) !== false) {
                return true;
            }
        }
    }
    
    return false;
}

// =============================================================================
// MAGIC LINK EMAIL GATE
// =============================================================================

/**
 * Handle email gate form submission
 * 
 * Generates magic link and either emails it (production) or stores for display (dev)
 */
function handle_profile_email_gate() {
    // Only process POST requests with our action
    if (!isset($_POST['action']) || $_POST['action'] !== 'profile_email_gate') {
        return;
    }
    
    // Verify nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'profile_email_gate')) {
        return ['error' => 'Invalid request. Please try again.'];
    }
    
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    
    if (empty($email) || !is_email($email)) {
        return ['error' => 'Please enter a valid email address.'];
    }
    
    // Look up profile by email (check multiple email fields)
    $profile = get_profile_by_email($email);
    
    // Generate a request key for transient storage (used in dev mode)
    $request_key = 'ml_' . substr(md5($_SERVER['REMOTE_ADDR'] . time()), 0, 16);
    
    if ($profile) {
        // Generate magic link token
        $token = wp_generate_password(64, false);
        $expiry = time() + HOUR_IN_SECONDS; // 1 hour
        
        // Store token in user meta or custom table
        update_post_meta($profile->ID, '_magic_link_token', $token);
        update_post_meta($profile->ID, '_magic_link_expiry', $expiry);
        
        // Build the magic link URL
        $magic_url = add_query_arg([
            'action' => 'magic_login',
            'token' => $token,
            'profile' => $profile->ID
        ], home_url('/profile-editor/'));
        
        // In dev environment, store the link for display
        if (is_profile_edit_dev_environment()) {
            set_transient('dev_magic_link_' . $request_key, $magic_url, 2 * MINUTE_IN_SECONDS);
        }
        
        // Send email (in production, this actually sends; in dev, it may or may not)
        $subject = 'Your Profile Edit Link - The Polys';
        $message = sprintf(
            "Hello,\n\nClick the link below to edit your profile. This link expires in 1 hour.\n\n%s\n\nIf you didn't request this, you can ignore this email.",
            $magic_url
        );
        
        wp_mail($email, $subject, $message);
    } else {
        // No profile found - but we still show success message (anti-enumeration)
        // In dev mode, store a "not found" indicator
        if (is_profile_edit_dev_environment()) {
            set_transient('dev_magic_link_' . $request_key, 'NOT_FOUND:' . $email, 2 * MINUTE_IN_SECONDS);
        }
    }
    
    // Redirect back with success flag (anti-enumeration: same message regardless)
    $redirect_url = add_query_arg([
        'requested' => '1',
        'rk' => $request_key
    ], home_url('/profile-editor/'));
    
    wp_redirect($redirect_url);
    exit;
}
add_action('init', 'handle_profile_email_gate');

/**
 * Get profile post by email address
 * 
 * Checks multiple email meta fields
 * 
 * @param string $email Email to search for
 * @return WP_Post|null Profile post or null
 */
function get_profile_by_email($email) {
    global $wpdb;
    
    // Search in common email meta fields
    $email_fields = ['email', 'profile_email', 'contact_email'];
    
    foreach ($email_fields as $field) {
        $profile_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = %s AND meta_value = %s
            LIMIT 1
        ", $field, $email));
        
        if ($profile_id) {
            $post = get_post($profile_id);
            if ($post && $post->post_type === 'profile') {
                return $post;
            }
        }
    }
    
    return null;
}

/**
 * Handle magic link consumption
 */
function handle_magic_link_login() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'magic_login') {
        return;
    }
    
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    $profile_id = isset($_GET['profile']) ? intval($_GET['profile']) : 0;
    
    if (empty($token) || empty($profile_id)) {
        return;
    }
    
    // Verify token
    $stored_token = get_post_meta($profile_id, '_magic_link_token', true);
    $expiry = get_post_meta($profile_id, '_magic_link_expiry', true);
    
    if ($token !== $stored_token || time() > $expiry) {
        // Invalid or expired token
        wp_redirect(add_query_arg('error', 'expired', home_url('/profile-editor/')));
        exit;
    }
    
    // Token is valid - set up session
    // Clear the token (one-time use)
    delete_post_meta($profile_id, '_magic_link_token');
    delete_post_meta($profile_id, '_magic_link_expiry');
    
    // Set authentication cookie/session
    $auth_token = wp_generate_password(64, false);
    $auth_expiry = time() + (24 * HOUR_IN_SECONDS); // 24 hours
    
    update_post_meta($profile_id, '_auth_token', $auth_token);
    update_post_meta($profile_id, '_auth_expiry', $auth_expiry);
    
    // Set cookie
    setcookie('profile_edit_token', $auth_token, $auth_expiry, '/', '', is_ssl(), true);
    setcookie('profile_edit_id', $profile_id, $auth_expiry, '/', '', is_ssl(), true);
    
    // Redirect to edit page
    wp_redirect(add_query_arg('profile_id', $profile_id, home_url('/profile-editor/')));
    exit;
}
add_action('init', 'handle_magic_link_login');

/**
 * Render the email gate form
 */
function render_profile_email_gate_form() {
    $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
    $requested = isset($_GET['requested']) && $_GET['requested'] === '1';
    $request_key = isset($_GET['rk']) ? sanitize_text_field($_GET['rk']) : '';
    
    ob_start();
    ?>
    <div class="profile-email-gate">
        <?php if ($error === 'expired'): ?>
            <div class="alert alert-error" style="background:#fee; border:1px solid #c00; padding:15px; margin-bottom:20px; color:#900;">
                <strong>Link Expired:</strong> Your magic link has expired. Please request a new one.
            </div>
        <?php endif; ?>
        
        <?php if ($requested): ?>
            <div class="alert alert-success" style="background:#efe; border:1px solid #0a0; padding:15px; margin-bottom:20px; color:#060;">
                <strong>Check your email!</strong> If an account exists with that email, we've sent a login link. It expires in 1 hour.
            </div>
            
            <?php 
            // DEV MODE: Show the magic link on screen
            if (is_profile_edit_dev_environment() && !empty($request_key)):
                $dev_link = get_transient('dev_magic_link_' . $request_key);
                if ($dev_link):
                    // Clean up transient after reading
                    delete_transient('dev_magic_link_' . $request_key);
            ?>
                <div class="dev-magic-link" style="background:#fff3cd; border:2px solid #ffc107; padding:20px; margin-bottom:20px; border-radius:5px;">
                    <strong style="color:#856404;">🔧 DEV MODE - Magic Link:</strong><br>
                    <?php if (strpos($dev_link, 'NOT_FOUND:') === 0): ?>
                        <span style="color:#721c24;">No profile found for: <?php echo esc_html(str_replace('NOT_FOUND:', '', $dev_link)); ?></span>
                    <?php else: ?>
                        <input type="text" 
                               value="<?php echo esc_attr($dev_link); ?>" 
                               readonly 
                               onclick="this.select();" 
                               style="width:100%; padding:10px; margin:10px 0; font-family:monospace; font-size:12px;">
                        <br>
                        <a href="<?php echo esc_url($dev_link); ?>" 
                           style="display:inline-block; background:#28a745; color:#fff; padding:10px 20px; text-decoration:none; border-radius:3px; margin-top:5px;">
                            Click to Login →
                        </a>
                    <?php endif; ?>
                </div>
            <?php 
                endif;
            endif; 
            ?>
        <?php else: ?>
            <h2>Edit Your Profile</h2>
            <p>Enter your email address to receive a secure login link.</p>
            
            <form method="post" action="<?php echo esc_url(home_url('/profile-editor/')); ?>">
                <?php wp_nonce_field('profile_email_gate'); ?>
                <input type="hidden" name="action" value="profile_email_gate">
                
                <div class="form-group" style="margin-bottom:15px;">
                    <label for="email" style="display:block; margin-bottom:5px; font-weight:bold;">Email Address:</label>
                    <input type="email" 
                           name="email" 
                           id="email" 
                           required 
                           placeholder="your@email.com"
                           style="width:100%; max-width:400px; padding:10px; font-size:16px; border:1px solid #ccc; border-radius:3px;">
                </div>
                
                <button type="submit" 
                        style="background:#0073aa; color:#fff; padding:12px 30px; font-size:16px; border:none; border-radius:3px; cursor:pointer;">
                    Send Login Link
                </button>
            </form>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode for email gate form
 */
function profile_email_gate_shortcode() {
    return render_profile_email_gate_form();
}
add_shortcode('profile_email_gate', 'profile_email_gate_shortcode');

// =============================================================================
// PROFILE UPDATE HANDLING
// =============================================================================

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