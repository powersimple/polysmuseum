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
        
        $processed_item = [
            'id' => $item->ID,
            'title' => $title,
            'url' => $url ?: '#',
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
            'level' => 0
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
 * 
 * @param string $menu_slug The menu slug to render
 * @return string HTML output
 */
function render_megamenu_with_logo($menu_slug = 'megamenu') {
    $menu_data = get_megamenu_data($menu_slug);
    
    if (!$menu_data) {
        return '<!-- Megamenu: Menu not found -->';
    }
    
    ob_start();
    ?>
    <nav class="megamenu" role="navigation" aria-label="<?php echo esc_attr($menu_data['menu']['name']); ?>">
        <!-- Logo -->
        <div id="logo" class="megamenu__logo">
            <a href="/" aria-label="Home"></a>
        </div>
        
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
                <div id="logo-mobile" class="megamenu__logo">
                    <a href="/" aria-label="Home"></a>
                </div>
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
    
    $item_id = 'megamenu-item-' . $item['id'];
    $panel_id = 'megamenu-panel-' . $item['id'];
    
    $output = '<li class="' . esc_attr(implode(' ', $classes)) . '" id="' . esc_attr($item_id) . '" role="none">';
    
    if ($has_children) {
        // Button trigger for items with children
        $output .= '<button type="button" aria-expanded="false" aria-controls="' . esc_attr($panel_id) . '" role="menuitem" aria-haspopup="true">';
        $output .= esc_html($item['title']);
        $output .= '</button>';
        
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
        $output .= '<a href="' . esc_url($item['url']) . '"' . $target . $current . ' role="menuitem">';
        $output .= esc_html($item['title']);
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
    $output = '<div class="megamenu__mobile-item">';
    
    if ($has_children) {
        $submenu_id = 'megamenu-mobile-submenu-' . $item['id'];
        
        // Accordion trigger
        $output .= '<button class="megamenu__mobile-trigger" aria-expanded="false" aria-controls="' . esc_attr($submenu_id) . '">';
        $output .= '<span>' . esc_html($item['title']) . '</span>';
        $output .= '</button>';
        
        // Submenu
        $output .= '<div class="megamenu__mobile-submenu" id="' . esc_attr($submenu_id) . '">';
        
        // If the parent item itself has a URL, include it
        if ($item['url'] && $item['url'] !== '#') {
            $output .= '<a href="' . esc_url($item['url']) . '" class="megamenu__mobile-sublink">';
            $output .= 'View ' . esc_html($item['title']);
            $output .= '</a>';
        }
        
        $output .= render_megamenu_mobile_children($item['children']);
        $output .= '</div>';
    } else {
        // Simple link
        $current = $is_current ? ' aria-current="page"' : '';
        $output .= '<a href="' . esc_url($item['url']) . '" class="megamenu__mobile-link"' . $current . '>';
        $output .= esc_html($item['title']);
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
            // Group title for items with children
            $output .= '<div class="megamenu__mobile-group-title">' . esc_html($item['title']) . '</div>';
            
            // If the item has a URL, show it
            if ($item['url'] && $item['url'] !== '#') {
                $output .= '<a href="' . esc_url($item['url']) . '" class="megamenu__mobile-sublink ' . $level_class . '">';
                $output .= 'View All';
                $output .= '</a>';
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

// Megamenu scripts are enqueued in functions-enqueue.php
