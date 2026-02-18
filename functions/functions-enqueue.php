<?php
/**
 * Theme Asset Enqueue - SINGLE SOURCE OF TRUTH
 * 
 * ============================================================================
 * ENVIRONMENT DETECTION
 * ============================================================================
 * DEV:  Host is exactly "obi-wan-v:3000" (Vite dev server)
 * PROD: All other hosts
 * 
 * ============================================================================
 * ASSET LOADING RULES
 * ============================================================================
 * DEV (obi-wan-v:3000):
 *   - vendor.js     (unminified)
 *   - main.js       (unminified)
 *   - style.css     (unminified)
 *   - Versioned with filemtime() for cache-busting
 * 
 * PROD (all other hosts):
 *   - vendor.min.js (minified)
 *   - main.min.js   (minified)
 *   - style.min.css (minified, fallback to style.css if missing)
 *   - Versioned with filemtime() for cache-busting
 * 
 * NEVER load both minified and unminified versions simultaneously.
 * 
 * ============================================================================
 * LEGACY JS NOTE
 * ============================================================================
 * main.js bundles legacy jQuery-based code from app/js/custom/:
 *   - megamenu.js      (legacy jQuery menu builder - targets #main-menu)
 *   - app.js           (legacy app initialization)
 *   - taxonomies.js    (legacy taxonomy handling)
 * 
 * Modern code (no jQuery dependency):
 *   - megamenu-controller.js (new accessible megamenu - targets .megamenu)
 * 
 * Legacy code can be removed module-by-module as pages migrate to new systems.
 * 
 * ============================================================================
 * DO NOT ADD HARDCODED <script> OR <link> TAGS IN TEMPLATES
 * All theme assets must flow through this enqueue function.
 * ============================================================================
 */

/**
 * Check if we're in development environment
 * SINGLE SOURCE OF TRUTH for environment detection
 * 
 * @return bool True if host is exactly "obi-wan-v:3000"
 */
function polys_is_dev() {
    return isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'obi-wan-v:3000';
}

/**
 * Get asset version based on file modification time
 * Used for cache-busting in both DEV and PROD
 * 
 * @param string $file_path Absolute path to the file
 * @return string Version string (filemtime or theme version fallback)
 */
function polys_asset_version($file_path) {
    if (file_exists($file_path)) {
        return (string) filemtime($file_path);
    }
    return wp_get_theme()->get('Version') ?: '1.0';
}

/**
 * Enqueue theme stylesheets
 * 
 * DEV:  style.css (unminified)
 * PROD: style.min.css (minified, fallback to style.css if missing)
 */
function enqueue_style() {
    $theme_uri = get_stylesheet_directory_uri();
    $theme_dir = get_stylesheet_directory();
    $is_dev = polys_is_dev();
    
    // Bootstrap 5 CSS (CDN - same for dev/prod)
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css', array(), '5.3.1');
    
    // Font Awesome 6.x (CDN - same for dev/prod)
    wp_enqueue_style('font-awesome-css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css', array(), '6.5.2');
    
    // Animate.css (local)
    $animate_file = $theme_dir . '/assets/lib/animate.css/animate.css';
    wp_enqueue_style('animate-css', $theme_uri . '/assets/lib/animate.css/animate.css', array(), polys_asset_version($animate_file));
    
    // Main theme stylesheet (compiled from SCSS by Vite)
    // DEV: style.css | PROD: style.min.css (fallback to style.css if minified doesn't exist)
    if ($is_dev) {
        $style_filename = 'style.css';
    } else {
        // PROD: prefer minified, fallback to unminified
        $style_filename = file_exists($theme_dir . '/style.min.css') ? 'style.min.css' : 'style.css';
    }
    $style_file = $theme_dir . '/' . $style_filename;
    wp_enqueue_style('theme-style', $theme_uri . '/' . $style_filename, array('bootstrap-css', 'font-awesome-css', 'animate-css'), polys_asset_version($style_file));
    
    // Print stylesheet
    $print_file = $theme_dir . '/print.css';
    wp_enqueue_style('theme-print', $theme_uri . '/print.css', array(), polys_asset_version($print_file), 'print');
}
add_action('wp_enqueue_scripts', 'enqueue_style');


/**
 * Enqueue theme scripts
 * 
 * DEV:  vendor.js + main.js (unminified)
 * PROD: vendor.min.js + main.min.js (minified)
 * 
 * NEVER loads both minified and unminified versions.
 */
function theme_scripts() {
    $theme_uri = get_stylesheet_directory_uri();
    $theme_dir = get_stylesheet_directory();
    $theme_path = parse_url($theme_uri, PHP_URL_PATH);
    $is_dev = polys_is_dev();
    
    // Determine which files to load based on environment
    // DEV: unminified | PROD: minified
    $vendor_filename = $is_dev ? 'vendor.js' : 'vendor.min.js';
    $main_filename = $is_dev ? 'main.js' : 'main.min.js';
    
    $vendor_file = $theme_dir . '/' . $vendor_filename;
    $main_file = $theme_dir . '/' . $main_filename;
    
    // Vendor scripts (bundled by Vite from app/js/vendor/)
    // Contains: Isotope, Masonry, Slick, and other third-party libraries
    // Depends on WordPress jQuery (handle: 'jquery')
    wp_enqueue_script('vendor-js', $theme_path . '/' . $vendor_filename, array('jquery'), polys_asset_version($vendor_file), true);
    
    // Main theme scripts (bundled by Vite from app/js/custom/)
    // Contains:
    //   MODERN (no jQuery): megamenu-controller.js
    //   LEGACY (jQuery):    megamenu.js, app.js, taxonomies.js, etc.
    // Legacy code can be removed module-by-module as pages migrate.
    wp_enqueue_script('main-js', $theme_path . '/' . $main_filename, array('jquery', 'vendor-js'), polys_asset_version($main_file), true);
}
add_action('wp_enqueue_scripts', 'theme_scripts');


/**
 * Make LOCAL style URLs relative for portability
 * Only applies to theme assets, not CDN URLs
 */
function style_loader_src_make_relative($src, $handle) {
    // Skip CDN/external URLs - only process local theme assets
    if (strpos($src, 'cdn.') !== false || strpos($src, 'cdnjs.') !== false || strpos($src, '//') === 0) {
        return $src;
    }
    if (function_exists('url_root')) {
        $src = str_replace(url_root(), "", $src);
    }
    return $src;
}
add_filter('style_loader_src', 'style_loader_src_make_relative', 10, 2);


/**
 * Dev-only: Inject live-reload WebSocket client
 *
 * Connects to Vite's WS server and listens for full-reload messages.
 * Only runs in dev environment (obi-wan-v:3000) and NOT in wp-admin.
 * Outputs inline script — no external file to manage or accidentally ship.
 */
function polys_dev_livereload() {
    if ( ! polys_is_dev() || is_admin() ) {
        return;
    }
    ?>
    <script>
    (function() {
        // Livereload via dedicated WS server on port 3001
        // Separate from Vite's port 3000 proxy which swallows WebSockets
        var wsUrl = 'wss://' + window.location.hostname + ':3001/';
        console.log('[livereload] Connecting to', wsUrl);
        var ws = new WebSocket(wsUrl);
        ws.onopen = function() {
            console.log('[livereload] Connected.');
        };
        ws.onmessage = function(e) {
            try {
                var msg = JSON.parse(e.data);
                if (msg.type === 'full-reload') {
                    console.log('[livereload] Reloading...');
                    window.location.reload();
                }
            } catch(err) {}
        };
        ws.onerror = function() {
            console.log('[livereload] Connection failed — is Vite running?');
        };
        ws.onclose = function() {
            console.log('[livereload] Disconnected.');
        };
    })();
    </script>
    <?php
}
add_action('wp_footer', 'polys_dev_livereload', 999);