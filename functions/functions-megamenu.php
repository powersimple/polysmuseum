<?php
/**
 * Megamenu Functions
 * 
 * Optimized menu fetching and rendering for the new megamenu system.
 * Uses a single optimized query to fetch all menu data with parent-child relationships.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Convert absolute image URL to relative path from site root
 * This ensures images work across different hostnames (IP, DNS name, etc.)
 * 
 * @param string $url The absolute URL
 * @return string Relative path starting with /wp-content/
 */
function megamenu_get_relative_image_url($url) {
    if (empty($url)) {
        return '';
    }
    
    // If already relative, return as-is
    if (strpos($url, '/wp-content/') === 0) {
        return $url;
    }
    
    // Extract path from /wp-content/ onwards
    if (preg_match('#(/wp-content/.+)$#', $url, $matches)) {
        return $matches[1];
    }
    
    // Fallback: return original URL
    return $url;
}

/**
 * Get megamenu data with optimized single query
 * 
 * @param string $menu_slug The menu slug to fetch (default: 'megamenu')
 * @return array|false Menu data structured for rendering, or false if not found
 */
function get_megamenu_data($menu_slug = 'megamenu') {
    global $wpdb;
    
    // Get menu term
    $menu_term = $wpdb->get_row($wpdb->prepare("
        SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id
        FROM {$wpdb->terms} t
        JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        WHERE tt.taxonomy = 'nav_menu'
        AND t.slug = %s
    ", $menu_slug));
    
    if (!$menu_term) {
        return false;
    }
    
    // Single optimized query to get all menu items with their metadata
    $menu_items = $wpdb->get_results($wpdb->prepare("
        SELECT 
            p.ID,
            p.post_title,
            p.menu_order,
            pm_object_id.meta_value AS object_id,
            pm_object.meta_value AS object_type,
            pm_parent.meta_value AS menu_item_parent,
            pm_url.meta_value AS url,
            pm_target.meta_value AS target,
            pm_classes.meta_value AS classes,
            pm_xfn.meta_value AS xfn,
            pm_description.meta_value AS description,
            linked_post.post_title AS linked_title,
            linked_post.post_name AS linked_slug,
            linked_post.post_type AS linked_post_type
        FROM {$wpdb->posts} p
        JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        LEFT JOIN {$wpdb->postmeta} pm_object_id ON p.ID = pm_object_id.post_id AND pm_object_id.meta_key = '_menu_item_object_id'
        LEFT JOIN {$wpdb->postmeta} pm_object ON p.ID = pm_object.post_id AND pm_object.meta_key = '_menu_item_object'
        LEFT JOIN {$wpdb->postmeta} pm_parent ON p.ID = pm_parent.post_id AND pm_parent.meta_key = '_menu_item_menu_item_parent'
        LEFT JOIN {$wpdb->postmeta} pm_url ON p.ID = pm_url.post_id AND pm_url.meta_key = '_menu_item_url'
        LEFT JOIN {$wpdb->postmeta} pm_target ON p.ID = pm_target.post_id AND pm_target.meta_key = '_menu_item_target'
        LEFT JOIN {$wpdb->postmeta} pm_classes ON p.ID = pm_classes.post_id AND pm_classes.meta_key = '_menu_item_classes'
        LEFT JOIN {$wpdb->postmeta} pm_xfn ON p.ID = pm_xfn.post_id AND pm_xfn.meta_key = '_menu_item_xfn'
        LEFT JOIN {$wpdb->postmeta} pm_description ON p.ID = pm_description.post_id AND pm_description.meta_key = '_menu_item_description'
        LEFT JOIN {$wpdb->posts} linked_post ON pm_object_id.meta_value = linked_post.ID
        WHERE tr.term_taxonomy_id = %d
        AND p.post_type = 'nav_menu_item'
        AND p.post_status = 'publish'
        ORDER BY p.menu_order ASC
    ", $menu_term->term_taxonomy_id));
    
    if (empty($menu_items)) {
        return false;
    }
    
    // Build hierarchical structure
    $items_by_id = [];
    $root_items = [];
    
    foreach ($menu_items as $item) {
        // Parse classes
        $classes = maybe_unserialize($item->classes);
        $classes_str = is_array($classes) ? implode(' ', array_filter($classes)) : '';
        
        // Determine URL
        $url = $item->url;
        if (empty($url) && $item->object_id) {
            $url = get_permalink($item->object_id);
        }
        
        // Use linked post title if menu item title is empty
        $title = !empty($item->post_title) ? $item->post_title : $item->linked_title;
        
        // Get menu background image for logo display
        // Priority: 1) _menu_bg_image (theme field), 2) rt-wp-menu-custom-fields (plugin)
        $media_link = '';
        
        // First check our theme's _menu_bg_image field
        $menu_bg_image = get_post_meta($item->ID, '_menu_bg_image', true);
        if (!empty($menu_bg_image)) {
            $media_link = $menu_bg_image;
        }
        
        // Fallback to rt-wp-menu-custom-fields plugin data
        if (empty($media_link)) {
            $custom_fields = get_post_meta($item->ID, 'rt-wp-menu-custom-fields', true);
            if (!empty($custom_fields)) {
                if (is_string($custom_fields)) {
                    $custom_fields = json_decode($custom_fields, true);
                }
                
                if (is_array($custom_fields)) {
                    $feature = isset($custom_fields['selected-feature']) ? $custom_fields['selected-feature'] : '';
                    
                    if ($feature === 'image' && isset($custom_fields['image']['media-link'])) {
                        $media_link = $custom_fields['image']['media-link'];
                    }
                }
            }
        }
        
        $processed_item = [
            'id' => $item->ID,
            'title' => $title,
            'url' => $url ?: '#',
            'slug' => $item->linked_slug ?: '',
            'target' => $item->target ?: '',
            'classes' => $classes_str,
            'classes_array' => is_array($classes) ? array_filter($classes) : [],
            'description' => $item->description ?: '',
            'xfn' => $item->xfn ?: '',
            'object_id' => $item->object_id,
            'object_type' => $item->linked_post_type ?: $item->object_type,
            'parent_id' => (int)$item->menu_item_parent,
            'menu_order' => $item->menu_order,
            'children' => [],
            'level' => 0,
            'media_link' => $media_link
        ];
        
        $items_by_id[$item->ID] = $processed_item;
    }
    
    // Build tree structure
    foreach ($items_by_id as $id => &$item) {
        if ($item['parent_id'] && isset($items_by_id[$item['parent_id']])) {
            $items_by_id[$item['parent_id']]['children'][] = &$item;
        } else {
            $root_items[] = &$item;
        }
    }
    
    // Calculate levels
    _megamenu_set_levels($root_items, 1);
    
    return [
        'menu' => [
            'id' => $menu_term->term_id,
            'name' => $menu_term->name,
            'slug' => $menu_term->slug
        ],
        'items' => $root_items
    ];
}

/**
 * Recursively set nesting levels
 */
function _megamenu_set_levels(&$items, $level) {
    foreach ($items as &$item) {
        $item['level'] = $level;
        if (!empty($item['children'])) {
            _megamenu_set_levels($item['children'], $level + 1);
        }
    }
}

/**
 * Render the megamenu HTML
 * 
 * @param string $menu_slug The menu slug to render
 * @return string HTML output
 */
function render_megamenu($menu_slug = 'megamenu') {
    $menu_data = get_megamenu_data($menu_slug);
    
    if (!$menu_data) {
        return '<!-- Megamenu: Menu not found -->';
    }
    
    ob_start();
    ?>
    <nav class="megamenu" role="navigation" aria-label="<?php echo esc_attr($menu_data['menu']['name']); ?>">
        <!-- Mobile Toggle -->
        <button class="megamenu__toggle" aria-expanded="false" aria-controls="megamenu-mobile" aria-label="Open menu">
            <span class="megamenu__toggle-icon"></span>
            <span class="megamenu__sr-only">Menu</span>
        </button>
        
        <!-- Desktop Navigation Bar -->
        <div class="megamenu__bar">
            <ul class="megamenu__list" role="menubar">
                <?php echo render_megamenu_items($menu_data['items'], 'desktop'); ?>
            </ul>
        </div>
        
        <!-- Mobile Overlay -->
        <div class="megamenu__overlay" aria-hidden="true"></div>
        
        <!-- Mobile Navigation Drawer -->
        <div class="megamenu__mobile" id="megamenu-mobile" aria-hidden="true">
            <div class="megamenu__mobile-header">
                <span class="megamenu__mobile-title"><?php echo esc_html($menu_data['menu']['name']); ?></span>
                <button class="megamenu__mobile-close" aria-label="Close menu">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <nav class="megamenu__mobile-nav">
                <?php echo render_megamenu_items($menu_data['items'], 'mobile'); ?>
            </nav>
        </div>
    </nav>
    <?php
    return ob_get_clean();
}

/**
 * Render the megamenu with logo included
 * Logo now comes from menu items with media-link metadata
 * 
 * @param string $menu_slug The menu slug to render
 * @return string HTML output
 */
function render_megamenu_with_logo($menu_slug = 'megamenu') {
    $menu_data = get_megamenu_data($menu_slug);
    
    if (!$menu_data) {
        return '<!-- Megamenu: Menu not found -->';
    }
    
    // Get first menu item's logo for persistent mobile header
    // Respects the menu item's own URL (custom link, page permalink, etc.)
    $first_item_logo = '';
    $first_item_url = home_url('/');
    $first_item_title = 'Home';
    $first_item_target = '';
    if (!empty($menu_data['items'][0])) {
        $first_item = $menu_data['items'][0];
        if (!empty($first_item['media_link'])) {
            $first_item_logo = megamenu_get_relative_image_url($first_item['media_link']);
        }
        $first_item_title = $first_item['title'];
        if (!empty($first_item['url'])) {
            $first_item_url = $first_item['url'];
        }
        if (!empty($first_item['target'])) {
            $first_item_target = $first_item['target'];
        }
    }
    
    ob_start();
    ?>
    <!-- Fixed Header Bar -->
    <nav class="megamenu" role="navigation" aria-label="<?php echo esc_attr($menu_data['menu']['name']); ?>">
        <?php if ($first_item_logo): ?>
        <!-- Mobile Logo (persistent, left of hamburger) -->
        <a href="<?php echo esc_url($first_item_url); ?>" class="megamenu__mobile-logo-link" aria-label="<?php echo esc_attr($first_item_title); ?>"<?php echo $first_item_target ? ' target="' . esc_attr($first_item_target) . '"' : ''; ?>>
            <img src="<?php echo esc_attr($first_item_logo); ?>" alt="<?php echo esc_attr($first_item_title); ?>" class="megamenu__mobile-header-logo" />
        </a>
        <?php endif; ?>
        
        <!-- Mobile Toggle -->
        <button class="megamenu__toggle" aria-expanded="false" aria-controls="megamenu-mobile" aria-label="Open menu">
            <span class="megamenu__toggle-icon"></span>
            <span class="megamenu__sr-only">Menu</span>
        </button>
        
        <!-- Desktop Navigation Bar - Logo comes from menu items with media-link -->
        <div class="megamenu__bar">
            <ul class="megamenu__list" role="menubar">
                <?php echo render_megamenu_items($menu_data['items'], 'desktop'); ?>
            </ul>
        </div>
    </nav>
    
    <!-- Mobile Overlay (outside fixed nav) -->
    <div class="megamenu__overlay" aria-hidden="true"></div>
    
    <!-- Mobile Navigation Drawer (outside fixed nav) -->
    <div class="megamenu__mobile" id="megamenu-mobile" aria-hidden="true">
        <div class="megamenu__mobile-header">
            <button class="megamenu__mobile-close" aria-label="Close menu">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <nav class="megamenu__mobile-nav">
            <?php echo render_megamenu_items($menu_data['items'], 'mobile'); ?>
        </nav>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render menu items recursively
 * 
 * @param array $items Menu items
 * @param string $mode 'desktop' or 'mobile'
 * @return string HTML output
 */
function render_megamenu_items($items, $mode = 'desktop') {
    if (empty($items)) return '';
    
    $output = '';
    
    foreach ($items as $item) {
        $has_children = !empty($item['children']);
        $is_current = is_megamenu_current_item($item);
        
        if ($mode === 'desktop') {
            $output .= render_megamenu_desktop_item($item, $has_children, $is_current);
        } else {
            $output .= render_megamenu_mobile_item($item, $has_children, $is_current);
        }
    }
    
    return $output;
}

/**
 * Render a desktop menu item
 */
function render_megamenu_desktop_item($item, $has_children, $is_current) {
    $classes = ['megamenu__item'];
    if ($item['classes']) {
        $classes[] = $item['classes'];
    }
    if ($is_current) {
        $classes[] = 'is-current';
    }
    
    // Check for media-link (logo image)
    $has_logo = !empty($item['media_link']);
    if ($has_logo) {
        $classes[] = 'has-logo';
    }
    
    $item_id = 'megamenu-item-' . $item['id'];
    $panel_id = 'megamenu-panel-' . $item['id'];
    
    $output = '<li class="' . esc_attr(implode(' ', $classes)) . '" id="' . esc_attr($item_id) . '" role="none">';
    
    // Convert media_link to relative URL for cross-device compatibility
    $logo_url = $has_logo ? megamenu_get_relative_image_url($item['media_link']) : '';
    
    // Use the menu item's own URL — respects custom links, external URLs, and page permalinks
    // Only construct a slug-based path if the item has a slug but no explicit URL
    $logo_link_url = $item['url'] ?: home_url('/');
    
    if ($has_children) {
        if ($has_logo) {
            // Logo with children: wrap logo in link, then add button for dropdown
            $logo_target = $item['target'] ? ' target="' . esc_attr($item['target']) . '"' : '';
            $output .= '<a href="' . esc_url($logo_link_url) . '"' . $logo_target . ' role="menuitem" class="megamenu__logo-link">';
            $output .= '<img class="megamenu__logo-img" src="' . esc_attr($logo_url) . '" alt="' . esc_attr($item['title']) . '" />';
            $output .= '</a>';
            $output .= '<button type="button" aria-expanded="false" aria-controls="' . esc_attr($panel_id) . '" role="menuitem" aria-haspopup="true" class="megamenu__dropdown-trigger">';
            $output .= '<span class="megamenu__sr-only">' . esc_html($item['title']) . ' submenu</span>';
            $output .= '</button>';
        } else {
            // Text trigger for items with children
            $output .= '<button type="button" aria-expanded="false" aria-controls="' . esc_attr($panel_id) . '" role="menuitem" aria-haspopup="true">';
            $output .= esc_html($item['title']);
            $output .= '</button>';
        }
        
        // Panel with children
        $output .= '<div class="megamenu__panel" id="' . esc_attr($panel_id) . '" data-state="closed" role="menu">';
        $output .= '<div class="megamenu__panel-inner cols-auto">';
        $output .= render_megamenu_panel_content($item['children']);
        $output .= '</div>';
        $output .= '</div>';
    } else {
        // Simple link for items without children
        $target = $item['target'] ? ' target="' . esc_attr($item['target']) . '"' : '';
        $current = $is_current ? ' aria-current="page"' : '';
        $link_url = $has_logo ? $logo_link_url : $item['url'];
        $output .= '<a href="' . esc_url($link_url) . '"' . $target . $current . ' role="menuitem">';
        if ($has_logo) {
            $output .= '<img class="megamenu__logo-img" src="' . esc_attr($logo_url) . '" alt="' . esc_attr($item['title']) . '" />';
        } else {
            $output .= esc_html($item['title']);
        }
        $output .= '</a>';
    }
    
    $output .= '</li>';
    
    return $output;
}

/**
 * Render panel content (L2/L3/L4 items)
 */
function render_megamenu_panel_content($items) {
    $output = '';
    
    foreach ($items as $item) {
        $has_children = !empty($item['children']);
        
        if ($has_children) {
            // L2 item with children becomes a group
            $output .= '<div class="megamenu__group">';
            $output .= '<h3 class="megamenu__group-title">';
            if ($item['url'] && $item['url'] !== '#') {
                $output .= '<a href="' . esc_url($item['url']) . '">' . esc_html($item['title']) . '</a>';
            } else {
                $output .= esc_html($item['title']);
            }
            $output .= '</h3>';
            $output .= '<ul class="megamenu__group-list">';
            
            foreach ($item['children'] as $child) {
                $child_has_children = !empty($child['children']);
                $nested_class = $child['level'] >= 4 ? ' is-nested' : '';
                
                $output .= '<li>';
                $output .= '<a href="' . esc_url($child['url']) . '" class="megamenu__link' . $nested_class . '">';
                $output .= esc_html($child['title']);
                $output .= '</a>';
                
                // L4 items
                if ($child_has_children) {
                    foreach ($child['children'] as $grandchild) {
                        $output .= '<a href="' . esc_url($grandchild['url']) . '" class="megamenu__link is-nested">';
                        $output .= esc_html($grandchild['title']);
                        $output .= '</a>';
                    }
                }
                
                $output .= '</li>';
            }
            
            $output .= '</ul>';
            $output .= '</div>';
        } else {
            // L2 item without children - simple link in its own group
            $output .= '<div class="megamenu__group">';
            $output .= '<a href="' . esc_url($item['url']) . '" class="megamenu__link">';
            $output .= esc_html($item['title']);
            $output .= '</a>';
            $output .= '</div>';
        }
    }
    
    return $output;
}

/**
 * Render a mobile menu item
 */
function render_megamenu_mobile_item($item, $has_children, $is_current) {
    // Check for logo image
    $has_logo = !empty($item['media_link']);
    $item_classes = 'megamenu__mobile-item';
    if ($has_logo) {
        $item_classes .= ' has-logo';
    }
    
    // Convert to relative URL for cross-device compatibility
    $logo_url = $has_logo ? megamenu_get_relative_image_url($item['media_link']) : '';
    
    $output = '<div class="' . esc_attr($item_classes) . '">';
    
    if ($has_children) {
        $submenu_id = 'megamenu-mobile-submenu-' . $item['id'];
        
        // Container for link + accordion trigger
        $output .= '<div class="megamenu__mobile-item-header">';
        
        // Link area (most of the width) - navigates to the page
        $link_url = !empty($item['url']) && $item['url'] !== '#' ? $item['url'] : '#';
        $output .= '<a href="' . esc_url($link_url) . '" class="megamenu__mobile-link">';
        if ($has_logo) {
            $output .= '<img class="megamenu__mobile-logo" src="' . esc_attr($logo_url) . '" alt="' . esc_attr($item['title']) . '" />';
        } else {
            $output .= '<span>' . esc_html($item['title']) . '</span>';
        }
        $output .= '</a>';
        
        // Accordion trigger button (+/- button on right)
        $output .= '<button class="megamenu__mobile-trigger" aria-expanded="false" aria-controls="' . esc_attr($submenu_id) . '" aria-label="Toggle submenu for ' . esc_attr($item['title']) . '">';
        $output .= '<span class="megamenu__mobile-trigger-icon"></span>';
        $output .= '</button>';
        
        $output .= '</div>';
        
        // Submenu - no "View" links
        $output .= '<div class="megamenu__mobile-submenu" id="' . esc_attr($submenu_id) . '">';
        $output .= render_megamenu_mobile_children($item['children']);
        $output .= '</div>';
    } else {
        // Simple link - with logo if available
        $current = $is_current ? ' aria-current="page"' : '';
        $output .= '<a href="' . esc_url($item['url']) . '" class="megamenu__mobile-link"' . $current . '>';
        if ($has_logo) {
            $output .= '<img class="megamenu__mobile-logo" src="' . esc_attr($logo_url) . '" alt="' . esc_attr($item['title']) . '" />';
        } else {
            $output .= esc_html($item['title']);
        }
        $output .= '</a>';
    }
    
    $output .= '</div>';
    
    return $output;
}

/**
 * Render mobile submenu children recursively
 */
function render_megamenu_mobile_children($items, $level = 2) {
    $output = '';
    
    foreach ($items as $item) {
        $has_children = !empty($item['children']);
        $level_class = 'level-' . $level;
        
        if ($has_children && $level < 4) {
            // Group title for items with children - make it a link if URL exists
            if ($item['url'] && $item['url'] !== '#') {
                $output .= '<a href="' . esc_url($item['url']) . '" class="megamenu__mobile-group-title">' . esc_html($item['title']) . '</a>';
            } else {
                $output .= '<div class="megamenu__mobile-group-title">' . esc_html($item['title']) . '</div>';
            }
            
            $output .= render_megamenu_mobile_children($item['children'], $level + 1);
        } else {
            // Simple link
            $output .= '<a href="' . esc_url($item['url']) . '" class="megamenu__mobile-sublink ' . $level_class . '">';
            $output .= esc_html($item['title']);
            $output .= '</a>';
        }
    }
    
    return $output;
}

/**
 * Check if menu item is current page
 */
function is_megamenu_current_item($item) {
    global $post;
    
    if (!$post) return false;
    
    // Check if object_id matches current post
    if ($item['object_id'] && (int)$item['object_id'] === $post->ID) {
        return true;
    }
    
    // Check URL match
    $current_url = trailingslashit(get_permalink());
    $item_url = trailingslashit($item['url']);
    
    if ($current_url === $item_url) {
        return true;
    }
    
    return false;
}

/**
 * =============================================================================
 * Section Bar - Get active L1 item and its L2 children from megamenu
 * =============================================================================
 * Used by the Section bar to display L2 navigation items from the megamenu.
 * Determines active L1 based on current URL matching.
 */

/**
 * Get the active L1 menu item and its L2 children based on current URL
 * 
 * @param string $menu_slug The menu slug (default: 'megamenu')
 * @return array|null Array with 'parent' (L1 item) and 'children' (L2 items), or null if no match
 */
function get_sectionbar_data($menu_slug = 'megamenu') {
    $menu_data = get_megamenu_data($menu_slug);
    
    if (!$menu_data || empty($menu_data['items'])) {
        return null;
    }
    
    // Get current URL path for matching
    $current_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $current_path = rtrim(parse_url($current_url, PHP_URL_PATH), '/');
    
    // Also get current post permalink for exact matching
    global $post;
    $current_permalink = '';
    if ($post) {
        $current_permalink = rtrim(parse_url(get_permalink($post->ID), PHP_URL_PATH), '/');
    }
    
    // Get current brand from body attribute (set by polys_get_current_brand())
    $current_brand = polys_get_current_brand();
    
    $best_match = null;
    $best_match_length = 0;
    $academy_fallback = null; // Store Academy L1 for fallback
    
    // Iterate through L1 items to find the best match
    foreach ($menu_data['items'] as $l1_item) {
        // Skip items without children (no L2 to show)
        if (empty($l1_item['children'])) {
            continue;
        }
        
        // Skip the first item (usually logo/home)
        if ($l1_item['menu_order'] == 1 && !empty($l1_item['media_link'])) {
            continue;
        }
        
        // Get L1 item's URL path
        $l1_path = rtrim(parse_url($l1_item['url'], PHP_URL_PATH), '/');
        
        // Check for brand-academy class to identify Academy L1 item
        $l1_classes = isset($l1_item['classes_array']) ? $l1_item['classes_array'] : [];
        if (in_array('brand-academy', $l1_classes)) {
            $academy_fallback = [
                'parent' => $l1_item,
                'children' => $l1_item['children'],
                'brand' => 'academy'
            ];
        }
        
        // Check if current URL starts with this L1's path (section match)
        if (!empty($l1_path) && $l1_path !== '/' && $l1_path !== '#') {
            // Exact match on L1
            if ($current_path === $l1_path || $current_permalink === $l1_path) {
                return [
                    'parent' => $l1_item,
                    'children' => $l1_item['children'],
                    'brand' => _sectionbar_detect_brand($l1_path)
                ];
            }
            
            // Current URL is under this L1's path (best prefix match wins)
            if (strpos($current_path, $l1_path . '/') === 0) {
                $match_length = strlen($l1_path);
                if ($match_length > $best_match_length) {
                    $best_match = [
                        'parent' => $l1_item,
                        'children' => $l1_item['children'],
                        'brand' => _sectionbar_detect_brand($l1_path)
                    ];
                    $best_match_length = $match_length;
                }
            }
        }
        
        // Also check L2 items for exact match
        foreach ($l1_item['children'] as $l2_item) {
            $l2_path = rtrim(parse_url($l2_item['url'], PHP_URL_PATH), '/');
            
            if ($current_path === $l2_path || $current_permalink === $l2_path) {
                return [
                    'parent' => $l1_item,
                    'children' => $l1_item['children'],
                    'brand' => _sectionbar_detect_brand($l1_path)
                ];
            }
            
            // Check if current URL is under this L2's path
            if (!empty($l2_path) && $l2_path !== '/' && $l2_path !== '#') {
                if (strpos($current_path, $l2_path . '/') === 0) {
                    $match_length = strlen($l1_path); // Use L1 path length for priority
                    if ($match_length > $best_match_length) {
                        $best_match = [
                            'parent' => $l1_item,
                            'children' => $l1_item['children'],
                            'brand' => _sectionbar_detect_brand($l1_path)
                        ];
                        $best_match_length = $match_length;
                    }
                }
            }
        }
    }
    
    // If no URL match found but we're in Academy context, use Academy L1 as fallback
    if (!$best_match && $current_brand === 'academy' && $academy_fallback) {
        return $academy_fallback;
    }
    
    return $best_match;
}

/**
 * Detect brand from URL path for styling purposes
 * 
 * @param string $path URL path
 * @return string Brand identifier: academy|polys|metatraversal|rpg
 */
function _sectionbar_detect_brand($path) {
    $path = rtrim($path, '/');
    
    if (strpos($path, '/the-polys') === 0) {
        return 'polys';
    }
    if (strpos($path, '/metatraversal') === 0) {
        return 'metatraversal';
    }
    if (strpos($path, '/ready-player-golf') === 0) {
        return 'rpg';
    }
    
    // Default to academy
    return 'academy';
}

/**
 * Check if a URL matches the current page
 * 
 * @param string $url URL to check
 * @return bool True if current page
 */
function is_sectionbar_current($url) {
    global $post;
    
    $url_path = rtrim(parse_url($url, PHP_URL_PATH), '/');
    $current_path = isset($_SERVER['REQUEST_URI']) ? rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') : '';
    
    if ($url_path === $current_path) {
        return true;
    }
    
    if ($post) {
        $permalink_path = rtrim(parse_url(get_permalink($post->ID), PHP_URL_PATH), '/');
        if ($url_path === $permalink_path) {
            return true;
        }
    }
    
    return false;
}

/**
 * Render the Section bar HTML
 * 
 * @param string $menu_slug The menu slug (default: 'megamenu')
 * @return string HTML output
 */
function render_sectionbar($menu_slug = 'megamenu') {
    $data = get_sectionbar_data($menu_slug);
    
    // No active L1 with children - don't render
    if (!$data) {
        return '';
    }
    
    $brand = $data['brand'];
    $parent = $data['parent'];
    $children = $data['children'];
    
    ob_start();
    ?>
    <nav class="sectionbar" data-brand="<?php echo esc_attr($brand); ?>" aria-label="<?php echo esc_attr($parent['title']); ?> section navigation">
        <div class="sectionbar__inner">
            <ul class="sectionbar__list">
                <?php foreach ($children as $item): 
                    $is_current = is_sectionbar_current($item['url']);
                ?>
                <li class="sectionbar__item">
                    <a href="<?php echo esc_url($item['url']); ?>" 
                       class="sectionbar__link<?php echo $is_current ? ' is-current' : ''; ?>"
                       <?php echo $is_current ? 'aria-current="page"' : ''; ?>>
                        <?php echo esc_html($item['title']); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>
    <?php
    return ob_get_clean();
}

// Megamenu scripts are enqueued in functions-enqueue.php

/**
 * =============================================================================
 * Footer Menu - Parse "footermenu" menu into brand roots and children
 * =============================================================================
 * Uses WordPress native wp_get_nav_menu_items() for proper data retrieval.
 * L1 items with class "brand-*" are brand roots.
 * L2 children are categorized by class:
 *   - "is-social" → social links (icon + tooltip from Title Attribute)
 *   - "is-legal" → legal links (privacy, terms, etc.)
 *   - others → regular links
 */

/**
 * Get footer menu data parsed by brand
 * Uses wp_get_nav_menu_items() for proper WP menu item attributes
 * 
 * @param string $menu_slug The menu slug (default: 'footermenu')
 * @return array Associative array of brand data, plus '_debug' key for diagnostics
 */
function get_footer_menu_data($menu_slug = 'footermenu') {
    $debug = [];
    $debug['slug_requested'] = $menu_slug;
    
    // Get menu items using WordPress native function
    $menu_items = wp_get_nav_menu_items($menu_slug);
    
    if (!$menu_items || !is_array($menu_items)) {
        $debug['error'] = 'menu not found or empty';
        return ['_debug' => $debug];
    }
    
    $debug['total_items'] = count($menu_items);
    $debug['l1_items'] = [];
    
    // Build children index by parent ID
    $children_by_parent = [];
    $items_by_id = [];
    
    foreach ($menu_items as $item) {
        $items_by_id[$item->ID] = $item;
        $parent_id = (int) $item->menu_item_parent;
        
        if (!isset($children_by_parent[$parent_id])) {
            $children_by_parent[$parent_id] = [];
        }
        $children_by_parent[$parent_id][] = $item;
    }
    
    $brands = [];
    
    // Process L1 items (parent_id = 0)
    $l1_items = $children_by_parent[0] ?? [];
    
    foreach ($l1_items as $l1_item) {
        // Get classes array from WP menu item
        $classes_array = is_array($l1_item->classes) ? array_filter($l1_item->classes) : [];
        $classes_str = implode(' ', $classes_array);
        
        // Debug: log L1 item info
        $debug['l1_items'][] = [
            'id' => $l1_item->ID,
            'title' => $l1_item->title,
            'classes' => $classes_str,
        ];
        
        // Extract brand from classes (look for brand-*)
        $brand_key = null;
        foreach ($classes_array as $class) {
            if (strpos($class, 'brand-') === 0) {
                $brand_key = str_replace('brand-', '', $class);
                break;
            }
        }
        
        if (!$brand_key) {
            continue;
        }
        
        // Get L2 children for this brand
        $l2_items = $children_by_parent[$l1_item->ID] ?? [];
        
        // Parse L2 children into two categories only:
        // - social_links: items with 'is-social' class (icon links)
        // - nav_links: everything else (all non-social links in menu order)
        $social_links = [];
        $nav_links = [];
        
        foreach ($l2_items as $l2_item) {
            $l2_classes = is_array($l2_item->classes) ? array_filter($l2_item->classes) : [];
            $is_social = in_array('is-social', $l2_classes);
            
            // Title Attribute is $item->attr_title in WP menu items
            $title_attr = $l2_item->attr_title ?: '';
            
            // Extract FA icon classes for social links
            $fa_classes = [];
            foreach ($l2_classes as $class) {
                if (strpos($class, 'fa-') === 0 || $class === 'fa-brands' || $class === 'fa-solid') {
                    $fa_classes[] = $class;
                }
            }
            
            $link_data = [
                'id' => $l2_item->ID,
                'title' => $l2_item->title,
                'url' => $l2_item->url,
                'target' => $l2_item->target ?: '',
                'title_attr' => $title_attr,
                'fa_classes' => $fa_classes,
                'classes' => implode(' ', $l2_classes),
            ];
            
            if ($is_social) {
                $social_links[] = $link_data;
            } else {
                $nav_links[] = $link_data;
            }
        }
        
        $brands[$brand_key] = [
            'id' => $l1_item->ID,
            'title' => $l1_item->title,
            'url' => $l1_item->url,
            'description' => $l1_item->description ?: '',
            'has_children' => !empty($l2_items),
            'children_count' => count($l2_items),
            'social_links' => $social_links,
            'nav_links' => $nav_links,
        ];
    }
    
    // Add debug info
    $brands['_debug'] = $debug;
    
    return $brands;
}

/**
 * Check if URL is a legal page (privacy, terms, etc.)
 */
function _is_legal_url($url) {
    $legal_patterns = [
        '/privacy',
        '/terms',
        '/gdpr',
        '/cookie',
        '/legal',
        '/disclaimer',
    ];
    
    $path = parse_url($url, PHP_URL_PATH);
    if (!$path) return false;
    
    foreach ($legal_patterns as $pattern) {
        if (strpos($path, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get menu item's Title Attribute field
 * WordPress stores this in post meta as _menu_item_attr_title
 */
function _get_menu_item_title_attr($item_id) {
    return get_post_meta($item_id, '_menu_item_attr_title', true) ?: '';
}

/**
 * Get brand logo URL mapping
 */
function get_footer_brand_logo($brand) {
    $logos = [
        'academy' => '/wp-content/themes/polysmuseum/app/scss/partials/images/logo/academy-logo.svg',
        'polys' => '/wp-content/themes/polysmuseum/app/scss/partials/images/logo/polys-logo.svg',
        'metatraversal' => '/wp-content/themes/polysmuseum/app/scss/partials/images/logo/metatraversal-logo.svg',
        'rpg' => '/wp-content/themes/polysmuseum/app/scss/partials/images/logo/rpg-logo.svg',
    ];
    
    return $logos[$brand] ?? '';
}

/**
 * Render the footer HTML
 * 
 * @return string HTML output
 */
function render_footer_navigation() {
    // Use 'footermenu' slug - the actual menu name in WordPress
    $footer_data = get_footer_menu_data('footermenu');
    $current_brand = polys_get_current_brand();
    
    // Debug output (admin only or WP_DEBUG)
    $show_debug = (defined('WP_DEBUG') && WP_DEBUG) || current_user_can('administrator');
    $debug_info = $footer_data['_debug'] ?? [];
    
    ob_start();
    
    // Debug comments for diagnostics
    if ($show_debug): ?>
<!-- FOOTER DEBUG START -->
<!-- slug: <?php echo esc_html($debug_info['slug_requested'] ?? 'unknown'); ?> -->
<!-- total_items: <?php echo esc_html($debug_info['total_items'] ?? 0); ?> -->
<?php if (!empty($debug_info['error'])): ?>
<!-- ERROR: <?php echo esc_html($debug_info['error']); ?> -->
<?php endif; ?>
<?php if (!empty($debug_info['l1_items'])): ?>
<?php foreach ($debug_info['l1_items'] as $l1_debug): ?>
<!-- L1: <?php echo esc_html($l1_debug['title']); ?> | classes: <?php echo esc_html($l1_debug['classes']); ?> | id: <?php echo esc_html($l1_debug['id']); ?> -->
<?php endforeach; ?>
<?php endif; ?>
<?php 
    // Academy-specific debug
    $academy_data = $footer_data['academy'] ?? null;
    if ($academy_data): ?>
<!-- academy found: yes | children_count: <?php echo esc_html($academy_data['children_count'] ?? 0); ?> | social: <?php echo count($academy_data['social_links'] ?? []); ?> | nav: <?php echo count($academy_data['nav_links'] ?? []); ?> -->
<?php else: ?>
<!-- academy found: NO - check that L1 item has class "brand-academy" -->
<?php endif; ?>
<!-- FOOTER DEBUG END -->
    <?php endif; ?>
    
    <?php
    // Get academy data (always rendered)
    $academy_data = $footer_data['academy'] ?? null;
    
    // Get current brand data (if not academy and has children)
    $brand_panel_data = null;
    if ($current_brand !== 'academy' && isset($footer_data[$current_brand])) {
        $brand_data = $footer_data[$current_brand];
        // Only render brand panel if it has L2 children
        if (($brand_data['has_children'] ?? false) && 
            (!empty($brand_data['social_links']) || !empty($brand_data['nav_links']))) {
            $brand_panel_data = $brand_data;
        }
    }
    
    // Brand-specific footer panel (above Academy footer)
    if ($brand_panel_data): ?>
    <div class="footer-brand" data-brand="<?php echo esc_attr($current_brand); ?>">
        <div class="footer-brand__inner">
            <?php if (!empty($brand_panel_data['nav_links'])): ?>
            <nav class="footer-brand__nav" aria-label="<?php echo esc_attr($brand_panel_data['title']); ?> links">
                <ul>
                    <?php foreach ($brand_panel_data['nav_links'] as $link): ?>
                    <li>
                        <a href="<?php echo esc_url($link['url']); ?>"
                           <?php echo $link['target'] ? 'target="' . esc_attr($link['target']) . '"' : ''; ?>
                           <?php echo $link['title_attr'] ? 'title="' . esc_attr($link['title_attr']) . '"' : ''; ?>>
                            <?php echo esc_html($link['title']); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
            <?php if (!empty($brand_panel_data['description'])): ?>
            <p class="footer-brand__relationship"><?php echo esc_html($brand_panel_data['description']); ?></p>
            <?php endif; ?>
            
            <?php if (!empty($brand_panel_data['social_links'])): ?>
            <nav class="footer-brand__social" aria-label="<?php echo esc_attr($brand_panel_data['title']); ?> social links">
                <ul>
                    <?php foreach ($brand_panel_data['social_links'] as $link): 
                        $fa_class = implode(' ', $link['fa_classes']);
                    ?>
                    <li>
                        <a href="<?php echo esc_url($link['url']); ?>"
                           target="<?php echo esc_attr($link['target'] ?: '_blank'); ?>"
                           title="<?php echo esc_attr($link['title_attr'] ?: $link['title']); ?>"
                           aria-label="<?php echo esc_attr($link['title_attr'] ?: $link['title']); ?>">
                            <i class="<?php echo esc_attr($fa_class); ?>" aria-hidden="true"></i>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Persistent Academy Footer (always visible) -->
    <div class="footer-academy" data-brand="academy">
        <div class="footer-academy__inner">
            <?php if ($academy_data && !empty($academy_data['nav_links'])): ?>
            <nav class="footer-academy__nav" aria-label="Academy links">
                <ul>
                    <?php foreach ($academy_data['nav_links'] as $link): ?>
                    <li>
                        <a href="<?php echo esc_url($link['url']); ?>"
                           <?php echo $link['target'] ? 'target="' . esc_attr($link['target']) . '"' : ''; ?>
                           <?php echo $link['title_attr'] ? 'title="' . esc_attr($link['title_attr']) . '"' : ''; ?>>
                            <?php echo esc_html($link['title']); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
            <div class="footer-academy__brand">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="footer-academy__name">Academy of Immersive Arts &amp; Sciences</a>
                <span class="footer-academy__tagline">Tax-exempt 501(c)(3) nonprofit organization • EIN 99-3401892</span>
            </div>
            
            <?php if ($academy_data && !empty($academy_data['social_links'])): ?>
            <nav class="footer-academy__social" aria-label="Academy social links">
                <ul>
                    <?php foreach ($academy_data['social_links'] as $link): 
                        $fa_class = implode(' ', $link['fa_classes']);
                    ?>
                    <li>
                        <a href="<?php echo esc_url($link['url']); ?>"
                           target="<?php echo esc_attr($link['target'] ?: '_blank'); ?>"
                           title="<?php echo esc_attr($link['title_attr'] ?: $link['title']); ?>"
                           aria-label="<?php echo esc_attr($link['title_attr'] ?: $link['title']); ?>">
                            <i class="<?php echo esc_attr($fa_class); ?>" aria-hidden="true"></i>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <?php endif; ?>
            
            <div class="footer-academy__copyright">
                &copy; <?php echo date('Y'); ?> All Rights Reserved
            </div>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}
