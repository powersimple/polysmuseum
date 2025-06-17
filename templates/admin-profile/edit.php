<?php
/**
 * Profile Admin Edit Template
 */

// Get profile data
$profile = get_post($profile_id);
if (!$profile) {
    wp_die('Profile not found');
}

// Get profile meta
$profile_meta = get_post_meta($profile_id);

// Enqueue media scripts
wp_enqueue_media();
?>

<div class="profile-admin-edit">
    <div class="edit-container">
        <h1>Edit Profile: <?php echo esc_html($profile->post_title); ?></h1>
        
        <?php if (isset($message)): ?>
            <div class="message <?php echo esc_attr($message_type); ?>">
                <?php echo esc_html($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field('update_profile'); ?>
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="profile_id" value="<?php echo esc_attr($profile_id); ?>">
            
            <div class="form-section">
                <h2>Basic Information</h2>
                
                <div class="form-group">
                    <label for="title">Name:</label>
                    <input type="text" id="title" name="title" value="<?php echo esc_attr($profile->post_title); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="content">Biography:</label>
                    <textarea id="content" name="content" rows="6"><?php echo esc_textarea($profile->post_content); ?></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h2>Contact Information</h2>
                
                <div class="form-group">
                    <label for="profile_email">Public Email:</label>
                    <input type="email" id="profile_email" name="profile_email" value="<?php echo esc_attr($profile_meta['profile_email'][0] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="contact_email">Private Email:</label>
                    <input type="email" id="contact_email" name="contact_email" value="<?php echo esc_attr($profile_meta['contact_email'][0] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone:</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo esc_attr($profile_meta['phone'][0] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="profile_title">Title:</label>
                    <input type="text" id="profile_title" name="profile_title" value="<?php echo esc_attr($profile_meta['profile_title'][0] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="company">Company/Organization:</label>
                    <input type="text" id="company" name="company" value="<?php echo esc_attr($profile_meta['company'][0] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-section">
                <h2>Address</h2>
                
                <div class="form-group">
                    <label for="address">Address:</label>
                    <input type="text" id="address" name="address" value="<?php echo esc_attr($profile_meta['address'][0] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="address2">Address 2:</label>
                    <input type="text" id="address2" name="address2" value="<?php echo esc_attr($profile_meta['address2'][0] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="city">City:</label>
                    <input type="text" id="city" name="city" value="<?php echo esc_attr($profile_meta['city'][0] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="state">State/Province:</label>
                    <input type="text" id="state" name="state" value="<?php echo esc_attr($profile_meta['state'][0] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="postal_code">Postal Code:</label>
                    <input type="text" id="postal_code" name="postal_code" value="<?php echo esc_attr($profile_meta['postal_code'][0] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="country">Country:</label>
                    <input type="text" id="country" name="country" value="<?php echo esc_attr($profile_meta['country'][0] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-section">
                <h2>Social Media & Links</h2>
                
                <div class="form-group">
                    <label for="website">Website:</label>
                    <input type="url" id="website" name="website" value="<?php echo esc_url($profile_meta['website'][0] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="profile_linkedin">LinkedIn:</label>
                    <input type="url" id="profile_linkedin" name="profile_linkedin" value="<?php echo esc_url($profile_meta['profile_linkedin'][0] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="profile_twitter">Twitter:</label>
                    <input type="url" id="profile_twitter" name="profile_twitter" value="<?php echo esc_url($profile_meta['profile_twitter'][0] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="profile_facebook">Facebook:</label>
                    <input type="url" id="profile_facebook" name="profile_facebook" value="<?php echo esc_url($profile_meta['profile_facebook'][0] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="profile_instagram">Instagram:</label>
                    <input type="url" id="profile_instagram" name="profile_instagram" value="<?php echo esc_url($profile_meta['profile_instagram'][0] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="profile_wikipedia">Wikipedia:</label>
                    <input type="url" id="profile_wikipedia" name="profile_wikipedia" value="<?php echo esc_url($profile_meta['profile_wikipedia'][0] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-section">
                <h2>Media</h2>
                
                <div class="form-group">
                    <label for="profile_image">Profile Image:</label>
                    <div class="media-upload-container">
                        <?php
                        $thumbnail_id = get_post_thumbnail_id($profile_id);
                        $thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'thumbnail') : '';
                        ?>
                        <div class="current-image">
                            <?php if ($thumbnail_url): ?>
                                <img src="<?php echo esc_url($thumbnail_url); ?>" alt="Current profile image">
                            <?php else: ?>
                                <p>No profile image set</p>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="profile_image_id" id="profile_image_id" value="<?php echo esc_attr($thumbnail_id); ?>">
                        <button type="button" class="button" id="upload_profile_image">Select Image</button>
                        <button type="button" class="button" id="remove_profile_image" <?php echo $thumbnail_id ? '' : 'style="display:none;"'; ?>>Remove Image</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="hero_image">Hero Image:</label>
                    <div class="media-upload-container">
                        <?php
                        $hero_images = rwmb_meta('hero', ['type' => 'image_advanced'], $profile_id);
                        $hero_image_id = !empty($hero_images) ? reset($hero_images)['ID'] : '';
                        $hero_image_url = $hero_image_id ? wp_get_attachment_image_url($hero_image_id, 'thumbnail') : '';
                        ?>
                        <div class="current-image">
                            <?php if ($hero_image_url): ?>
                                <img src="<?php echo esc_url($hero_image_url); ?>" alt="Current hero image">
                            <?php else: ?>
                                <p>No hero image set</p>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="hero_image_id" id="hero_image_id" value="<?php echo esc_attr($hero_image_id); ?>">
                        <button type="button" class="button" id="upload_hero_image">Select Image</button>
                        <button type="button" class="button" id="remove_hero_image" <?php echo $hero_image_id ? '' : 'style="display:none;"'; ?>>Remove Image</button>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="button button-primary">Update Profile</button>
                <a href="<?php echo esc_url(add_query_arg('action', 'logout')); ?>" class="button">Logout</a>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Profile Image Upload
    $('#upload_profile_image').click(function(e) {
        e.preventDefault();
        var image = wp.media({
            title: 'Upload Profile Image',
            multiple: false
        }).open()
        .on('select', function(e){
            var uploaded_image = image.state().get('selection').first();
            var image_url = uploaded_image.toJSON().sizes.thumbnail ? uploaded_image.toJSON().sizes.thumbnail.url : uploaded_image.toJSON().url;
            $('.current-image').html('<img src="' + image_url + '" alt="Current profile image">');
            $('#profile_image_id').val(uploaded_image.id);
            $('#remove_profile_image').show();
        });
    });
    
    $('#remove_profile_image').click(function(e) {
        e.preventDefault();
        $('.current-image').html('<p>No profile image set</p>');
        $('#profile_image_id').val('');
        $(this).hide();
    });
    
    // Hero Image Upload
    $('#upload_hero_image').click(function(e) {
        e.preventDefault();
        var image = wp.media({
            title: 'Upload Hero Image',
            multiple: false
        }).open()
        .on('select', function(e){
            var uploaded_image = image.state().get('selection').first();
            var image_url = uploaded_image.toJSON().sizes.thumbnail ? uploaded_image.toJSON().sizes.thumbnail.url : uploaded_image.toJSON().url;
            $('.current-image').html('<img src="' + image_url + '" alt="Current hero image">');
            $('#hero_image_id').val(uploaded_image.id);
            $('#remove_hero_image').show();
        });
    });
    
    $('#remove_hero_image').click(function(e) {
        e.preventDefault();
        $('.current-image').html('<p>No hero image set</p>');
        $('#hero_image_id').val('');
        $(this).hide();
    });
});
</script>

<style>
.profile-admin-edit {
    max-width: 800px;
    margin: 40px auto;
    padding: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.edit-container h1 {
    margin-bottom: 30px;
    text-align: center;
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.form-section h2 {
    margin-bottom: 20px;
    font-size: 1.2em;
    color: #333;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="tel"],
.form-group input[type="url"],
.form-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-group textarea {
    resize: vertical;
}

.media-upload-container {
    margin-top: 10px;
}

.current-image {
    margin-bottom: 15px;
}

.current-image img {
    max-width: 150px;
    height: auto;
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

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 30px;
}

.button {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.button-primary {
    background: #0073aa;
    color: #fff;
}

.button-primary:hover {
    background: #005177;
}

.button:not(.button-primary) {
    background: #f7f7f7;
    color: #333;
    border: 1px solid #ddd;
}

.button:not(.button-primary):hover {
    background: #f0f0f0;
}
</style> 