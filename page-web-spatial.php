<?php
/**
 * Template Name: Web Spatial Page
 *
 * This is the template for the Web Spatial application.
 */

// Enqueue scripts for the Web Spatial app
function enqueue_web_spatial_scripts() {
    if (!is_page_template('page-web-spatial.php')) return;

    $dev_server_host = 'http://obi-wan-v:5173';

    if (defined('WP_DEBUG') && WP_DEBUG) {
        // Development: Load from Vite dev server
        wp_enqueue_script('vite-client', $dev_server_host . '/@vite/client', [], null, ['strategy' => 'module']);
        wp_enqueue_script('web-spatial-app', $dev_server_host . '/main.js', ['vite-client'], null, ['strategy' => 'module']);
    } else {
        // Production: Load from manifest
        $manifest_path = get_template_directory() . '/web-spatial/dist/.vite/manifest.json';
        if (file_exists($manifest_path)) {
            $manifest = json_decode(file_get_contents($manifest_path), true);
            if (isset($manifest['main.js'])) {
                $main_js = $manifest['main.js'];
                wp_enqueue_script('web-spatial-app', get_template_directory_uri() . '/web-spatial/dist/' . $main_js['file'], [], null, ['strategy' => 'module']);
                if (!empty($main_js['css'])) {
                    foreach ($main_js['css'] as $css_file) {
                        wp_enqueue_style('web-spatial-css', get_template_directory_uri() . '/web-spatial/dist/' . $css_file);
                    }
                }
            }
        }
    }
}
add_action('wp_enqueue_scripts', 'enqueue_web_spatial_scripts');

// Add module type to script tags
function add_module_type_for_web_spatial($tag, $handle, $src) {
    if (in_array($handle, ['vite-client', 'web-spatial-app'])) {
        return '<script type="module" src="' . esc_url($src) . '" id="' . $handle . '-js"></script>';
    }
    return $tag;
}
add_filter('script_loader_tag', 'add_module_type_for_web_spatial', 10, 3);

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">

        <div id="app"></div>

        <?php
        // Start the loop.
        while ( have_posts() ) : the_post();
            the_content();
        endwhile;
        ?>

    </main><!-- .site-main -->
</div><!-- .content-area -->

<?php get_footer(); ?>

