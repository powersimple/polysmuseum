<?php
/**
 * Web Spatial Integration Functions
 */

function polysmuseum_enqueue_web_spatial() {
    // Only load on our Web Spatial page template
    if (!is_page_template('page-web-spatial.php')) {
        return;
    }

    // Use your local dev server name here
    $dev_server_host = 'http://obi-wan-v:5173';

    // Development mode
    if (defined('WP_DEBUG') && WP_DEBUG) {
        // Enqueue Vite client
        wp_enqueue_script('vite-client', $dev_server_host . '/@vite/client', [], null, ['strategy' => 'module']);

        // Enqueue our Web Spatial app
        wp_enqueue_script('web-spatial-app', $dev_server_host . '/main.js', [], null, ['strategy' => 'module']);

    } 
    // Production mode
    else {
        $manifest_path = get_template_directory() . '/web-spatial/dist/manifest.json';
        if (file_exists($manifest_path)) {
            $manifest = json_decode(file_get_contents($manifest_path), true);
            if (isset($manifest['main.js'])) {
                // Enqueue main script
                wp_enqueue_script(
                    'web-spatial-app',
                    get_template_directory_uri() . '/web-spatial/dist/' . $manifest['main.js']['file'],
                    [],
                    null,
                    ['strategy' => 'module']
                );
                // Enqueue CSS if it exists
                if (!empty($manifest['main.js']['css'])) {
                    foreach ($manifest['main.js']['css'] as $css_file) {
                        wp_enqueue_style(
                            'web-spatial-app-css',
                            get_template_directory_uri() . '/web-spatial/dist/' . $css_file
                        );
                    }
                }
            }
        }
    }
}
add_action('wp_enqueue_scripts', 'polysmuseum_enqueue_web_spatial');

// Add module type to script tags
function add_module_type_to_web_spatial_scripts($tag, $handle, $src) {
    if (in_array($handle, ['vite-client', 'web-spatial-app'])) {
        return '<script type="module" src="' . esc_url($src) . '" id="' . $handle . '-js"></script>';
    }
    return $tag;
}
add_filter('script_loader_tag', 'add_module_type_to_web_spatial_scripts', 10, 3);
