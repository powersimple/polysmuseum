<?php
/**
 * Template Name: Profile Editor
 * 
 * Profile editing page with email gate authentication.
 * Shows email form for unauthenticated users, edit form for authenticated.
 * 
 * In DEV mode, displays the magic link on-screen after form submission.
 *
 * @package Polys Museum
 */

get_header();

// Get page meta for hero/featured image
$hero_image_id = get_post_thumbnail_id($post->ID);
$hero_image = $hero_image_id ? getThumbnail($hero_image_id, 'full') : '';
$section_class = get_post_meta($post->ID, 'section_class', true);
$section_hero_class = get_post_meta($post->ID, 'section_hero_class', true);
?>

<?php if ($hero_image): ?>
<section class="parallax home-fade hero-content <?php echo esc_attr($section_hero_class); ?> <?php echo esc_attr($section_class); ?>" 
         id="dynamic-hero" 
         style="background-image:url(<?php echo esc_url($hero_image); ?>);">
</section>
<?php endif; ?>

<main id="main-content" role="main" class="main <?php echo esc_attr($section_class); ?>">
    
    <article class="profile-editor-page">
        
        <?php if (!$hero_image): ?>
        <header class="page-header">
            <h1 class="page-title"><?php the_title(); ?></h1>
        </header>
        <?php endif; ?>
        
        <div class="page-content">
            <?php 
            // Check if user is authenticated via cookie
            $is_authenticated = false;
            $profile_id = 0;
            
            if (isset($_COOKIE['profile_edit_token']) && isset($_COOKIE['profile_edit_id'])) {
                $token = sanitize_text_field($_COOKIE['profile_edit_token']);
                $profile_id = intval($_COOKIE['profile_edit_id']);
                
                // Verify token
                $stored_token = get_post_meta($profile_id, '_auth_token', true);
                $auth_expiry = get_post_meta($profile_id, '_auth_expiry', true);
                
                if ($token === $stored_token && time() < $auth_expiry) {
                    $is_authenticated = true;
                }
            }
            
            if ($is_authenticated && $profile_id): 
                // Show profile edit form
                $profile = get_post($profile_id);
                if ($profile && $profile->post_type === 'profile'):
            ?>
                <div class="profile-edit-authenticated">
                    <p><strong>Editing:</strong> <?php echo esc_html($profile->post_title); ?></p>
                    <p><a href="<?php echo esc_url(admin_url('post.php?post=' . $profile_id . '&action=edit')); ?>" class="button">Edit in Admin →</a></p>
                    
                    <hr style="margin:30px 0;">
                    
                    <p><a href="<?php echo esc_url(add_query_arg('action', 'logout', home_url('/profile-editor/'))); ?>">Logout</a></p>
                </div>
            <?php 
                else:
                    echo '<p>Profile not found.</p>';
                endif;
            else: 
                // Show email gate form
                echo render_profile_email_gate_form();
            endif; 
            ?>
        </div>
        
    </article>
    
</main>

<?php get_footer(); ?>
