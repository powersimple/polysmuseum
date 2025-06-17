<?php
/**
 * Cesium Integration Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

// Debug output when file is loaded
error_log('Cesium functions file loaded');

function polysmuseum_enqueue_cesium() {
    // Only load on our Cesium page
    if (get_the_ID() !== 9172) {
        return;
    }

    // Development mode
    if (defined('WP_DEBUG') && WP_DEBUG) {
        // Add type="module" to the script
        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'cesium-globe') {
                return str_replace(' src', ' type="module" src', $tag);
            }
            return $tag;
        }, 10, 2);

        // Enqueue Cesium CSS
        wp_enqueue_style(
            'cesium-widgets',
            'http://localhost:3000/@vite/client',
            array(),
            null
        );

        // Enqueue our Cesium initialization
        wp_enqueue_script(
            'cesium-globe',
            'http://localhost:3000/cesium/cesium.js',
            array(),
            null,
            true
        );

        // Add inline script to check if Cesium loaded
        wp_add_inline_script('cesium-globe', '
            console.log("Cesium script tag added");
            window.addEventListener("load", () => {
                console.log("Window loaded, checking for Cesium viewer...");
                if (window.viewer) {
                    console.log("Cesium viewer found:", window.viewer);
                } else {
                    console.error("Cesium viewer not found!");
                }
            });
        ');
    } 
    // Production mode
    else {
        $manifest_path = get_template_directory() . '/build/manifest.json';
        if (file_exists($manifest_path)) {
            $manifest = json_decode(file_get_contents($manifest_path), true);
            if (isset($manifest['cesium'])) {
                wp_enqueue_script(
                    'cesium-globe',
                    get_template_directory_uri() . '/build/' . $manifest['cesium']['file'],
                    array(),
                    null,
                    true
                );
            }
        }
    }
}

// Debug output before adding action
error_log('Adding Cesium enqueue action');
add_action('wp_enqueue_scripts', 'polysmuseum_enqueue_cesium');
error_log('Cesium enqueue action added'); 