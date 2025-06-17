<?php
/**
 * Profile Admin Login Template
 */

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_magic_link') {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'request_magic_link')) {
        wp_die('Invalid nonce');
    }
    
    $email = sanitize_email($_POST['email']);
    $profile_id = intval($_POST['profile_id']);
    
    // Verify the profile exists and belongs to this email
    $profile = get_post($profile_id);
    if ($profile && $profile->post_type === 'profile') {
        $profile_email = get_post_meta($profile_id, 'profile_email', true);
        if ($profile_email === $email) {
            send_profile_magic_link($profile_id, $email);
            $message = 'Magic link sent! Check your email.';
            $message_type = 'success';
        } else {
            $message = 'Email does not match this profile.';
            $message_type = 'error';
        }
    } else {
        $message = 'Profile not found.';
        $message_type = 'error';
    }
}
?>

<div class="profile-admin-login">
    <div class="login-container">
        <h1>Profile Admin Access</h1>
        
        <?php if (isset($message)): ?>
            <div class="message <?php echo esc_attr($message_type); ?>">
                <?php echo esc_html($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <?php wp_nonce_field('request_magic_link'); ?>
            <input type="hidden" name="action" value="request_magic_link">
            
            <div class="form-group">
                <label for="profile_id">Profile ID:</label>
                <input type="number" id="profile_id" name="profile_id" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="button button-primary">Request Magic Link</button>
            </div>
        </form>
        
        <div class="help-text">
            <p>Enter your Profile ID and the email associated with your profile. We'll send you a magic link to access your profile admin page.</p>
        </div>
    </div>
</div>

<style>
.profile-admin-login {
    max-width: 400px;
    margin: 40px auto;
    padding: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.login-container h1 {
    margin-bottom: 20px;
    text-align: center;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-group input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.message {
    padding: 10px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.help-text {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
    font-size: 0.9em;
    color: #666;
}
</style> 