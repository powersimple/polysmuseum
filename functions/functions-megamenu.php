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
            linked_post.post_type AS linked_post_type,
            (CHAR_LENGTH(TRIM(COALESCE(linked_post.post_content, ''))) > 20) AS has_content
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
        LEFT JOIN {$wpdb->posts} linked_post ON linked_post.ID = CAST(pm_object_id.meta_value AS UNSIGNED)
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
        // Brand section links use the full vanity domain (thepolys.com …) on
        // production; a no-op locally unless ?brand_urls=1. See functions-brands.php.
        if (function_exists('rewrite_url_to_brand_domain')) {
            $url = rewrite_url_to_brand_domain($url);
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
            'media_link' => $media_link,
            'has_content' => !empty($item->has_content),
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
 * Decide whether to render the megamenu__subnav bar for the given candidates.
 *
 * Returns false when:
 *   - branded context is active (nav bar is already the scoped section nav)
 *   - candidate list is empty
 *   - any candidate item carries a media_link (brand logo links — these belong
 *     in the megamenu panel, not in a secondary bar below the header)
 *
 * @param  array|null $snav_items        Output of megamenu_get_active_subnav_items().
 * @param  bool       $is_branded_context True when the header is brand-scoped.
 * @return bool
 */
function megamenu_should_render_active_subnav( $snav_items, $is_branded_context ) {
    if ( $is_branded_context ) return false;
    if ( empty( $snav_items ) ) return false;
    foreach ( $snav_items as $item ) {
        if ( ! empty( $item['media_link'] ) ) return false;
    }
    return true;
}

/**
 * Detect the active subnav for the current page.
 *
 * Walks the rendered nav items (top bar items) and finds the first one whose
 * subtree contains the current page.  Returns that item's direct children to
 * be shown as an auto-expanded subnav row below the main header.
 *
 * Skips the nav-bar item when the current page IS that item (already shown in
 * bar), so the subnav only appears when the visitor is inside the section, not
 * on its root page.
 *
 * @param  array  $nav_items  Processed top-bar menu items (direct children of root).
 * @return array|null  Direct children of the matching nav item, or null.
 */
function megamenu_get_active_subnav_items( array $nav_items ) {
    global $post;
    if ( ! $post || empty( $nav_items ) ) {
        return null;
    }

    $current_post_id = (int) $post->ID;
    $current_url     = $current_post_id ? trailingslashit( get_permalink( $current_post_id ) ) : '';

    foreach ( $nav_items as $nav_item ) {
        if ( empty( $nav_item['children'] ) ) {
            continue;
        }

        // Skip when current page IS this nav-bar item itself (already in the bar)
        if ( (int) $nav_item['object_id'] === $current_post_id ) {
            continue;
        }
        if ( $current_url && trailingslashit( $nav_item['url'] ) === $current_url ) {
            continue;
        }

        // Check if current page lives anywhere in this item's subtree
        $flat = [];
        _polys_megamenu_flatten_tree( $nav_item['children'], $flat );

        foreach ( $flat as $desc ) {
            if ( ( $current_post_id && (int) $desc['object_id'] === $current_post_id )
                 || ( $current_url && trailingslashit( $desc['url'] ) === $current_url ) ) {
                return $nav_item['children'];
            }
        }
    }

    return null;
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

    // ── Single-root detection ────────────────────────────────────────────────
    // If the menu has exactly one root item and that root has children, treat
    // the root as the persistent logo and promote its children to the visible
    // nav bar.  This handles the new single-root hierarchy where Academy (or
    // any other brand) is the sole level-0 item.
    // When multiple root items exist the original behaviour is preserved.
    $root_items  = $menu_data['items'];
    $single_root = count($root_items) === 1 && !empty($root_items[0]['children']);
    $root_item   = $root_items[0] ?? null;

    // Items that will be passed to render_megamenu_items() for both desktop
    // bar and mobile drawer.  In single-root mode this is the root's children;
    // in multi-root mode it is the full root items array.
    $nav_items = $single_root ? $root_item['children'] : $root_items;

    // ── Active subnav detection ──────────────────────────────────────────────
    // Computed BEFORE brand override because nav_items may be replaced below.
    // After the brand override $nav_items will reflect the scoped brand children,
    // so we detect the subnav against the pre-override (full section) nav items
    // first, then re-run against the brand-scoped items if the brand was swapped.
    // We store this after the brand override block below (see $active_subnav_items).

    // ── Active brand override (single-root mode only) ────────────────────────
    // When the resolved brand ≠ menu root brand, swap nav_items to the active
    // brand's children.  Logo policy (updated 2026-08-24): in a branded context
    // the header shows ONLY the active brand's logo — the Academy root logo is no
    // longer pinned alongside it.  Per-brand Academy awareness is handled case by
    // case, not globally forced here.
    $effective_logo_item = $root_item; // default: menu root (usually Academy)
    $is_branded_context  = false;
    if ($single_root && $root_item) {
        $active_brand_node = polys_megamenu_get_active_brand_root_node($menu_slug);
        if ($active_brand_node) {
            $root_brand_key = polys_megamenu_extract_brand_key($root_item['classes_array']);
            if ($active_brand_node['brand_key'] !== $root_brand_key
                && !empty($active_brand_node['menu_item']['children'])) {
                $effective_logo_item = $active_brand_node['menu_item'];
                $nav_items           = $effective_logo_item['children'];
                $is_branded_context  = true;
            }
        }
    }

    // ── Root logo vars (always the menu root — Academy or whatever is L0) ───
    $root_logo_url    = '';
    $root_logo_href   = home_url('/');
    $root_logo_title  = 'Home';
    $root_logo_target = '';
    if ($root_item) {
        if (!empty($root_item['media_link'])) {
            $root_logo_url = megamenu_get_relative_image_url($root_item['media_link']);
        }
        $root_logo_title  = $root_item['title'];
        $root_logo_href   = $root_item['url'] ?: home_url('/');
        $root_logo_target = $root_item['target'] ?? '';
    }

    // ── Brand logo vars (only populated in branded context) ──────────────────
    $brand_logo_url    = '';
    $brand_logo_href   = '';
    $brand_logo_title  = '';
    $brand_logo_target = '';
    if ($is_branded_context && $effective_logo_item) {
        if (!empty($effective_logo_item['media_link'])) {
            $brand_logo_url = megamenu_get_relative_image_url($effective_logo_item['media_link']);
        }
        $brand_logo_title  = $effective_logo_item['title'];
        $brand_logo_href   = $effective_logo_item['url'] ?: home_url('/');
        $brand_logo_target = $effective_logo_item['target'] ?? '';
    }

    // ── Active subnav items (run against final $nav_items after brand swap) ──
    // megamenu_should_render_active_subnav() suppresses:
    //   - branded context (nav bar is already the scoped section nav)
    //   - sections whose children are brand logo items (e.g. Programs)
    $_snav_candidates    = megamenu_get_active_subnav_items( $nav_items );
    $active_subnav_items = megamenu_should_render_active_subnav( $_snav_candidates, $is_branded_context )
        ? $_snav_candidates
        : null;

    // Legacy aliases used by the mobile logo template below.
    $first_item_logo   = $root_logo_url;
    $first_item_url    = $root_logo_href;
    $first_item_title  = $root_logo_title;
    $first_item_target = $root_logo_target;

    // ── Desktop logo <li> for single-root mode ───────────────────────────────
    // Academy context:  one <li> with the root logo.
    // Branded context:  one <li> containing both logos side by side.
    //   Both share has-logo so the CSS :first-child margin-right:auto fires on
    //   the combined item, pushing all nav items to the right.
    // Branded context => show ONLY the brand logo. Unbranded => root logo.
    $desktop_logo_li = '';
    $show_brand_logo = $is_branded_context && ($brand_logo_url || $brand_logo_title);
    if ($single_root && ($root_logo_url || $show_brand_logo)) {
        $logo_source_item = $show_brand_logo ? $effective_logo_item : $root_item;

        $li_classes = 'megamenu__item has-logo';
        if (!empty($logo_source_item['classes'])) {
            $li_classes .= ' ' . $logo_source_item['classes'];
        }

        $desktop_logo_li  = '<li class="' . esc_attr($li_classes) . '"';
        $desktop_logo_li .= ' id="megamenu-item-' . esc_attr($logo_source_item['id']) . '" role="none">';

        if ($show_brand_logo) {
            $brand_href_attr   = esc_url($brand_logo_href);
            $brand_target_attr = $brand_logo_target ? ' target="' . esc_attr($brand_logo_target) . '"' : '';
            $desktop_logo_li  .= '<a href="' . $brand_href_attr . '"' . $brand_target_attr;
            $desktop_logo_li  .= ' role="menuitem" class="megamenu__logo-link megamenu__logo-link--brand">';
            if ($brand_logo_url) {
                $desktop_logo_li .= '<img class="megamenu__logo-img" src="' . esc_attr($brand_logo_url) . '"';
                $desktop_logo_li .= ' alt="' . esc_attr($brand_logo_title) . '" />';
            } else {
                $desktop_logo_li .= '<span class="megamenu__brand-label">' . esc_html($brand_logo_title) . '</span>';
            }
            $desktop_logo_li .= '</a>';
        } else {
            $root_href_attr   = esc_url($root_logo_href);
            $root_target_attr = $root_logo_target ? ' target="' . esc_attr($root_logo_target) . '"' : '';
            $desktop_logo_li .= '<a href="' . $root_href_attr . '"' . $root_target_attr;
            $desktop_logo_li .= ' role="menuitem" class="megamenu__logo-link megamenu__logo-link--root">';
            $desktop_logo_li .= '<img class="megamenu__logo-img" src="' . esc_attr($root_logo_url) . '"';
            $desktop_logo_li .= ' alt="' . esc_attr($root_logo_title) . '" />';
            $desktop_logo_li .= '</a>';
        }

        $desktop_logo_li .= '</li>';
    }

    ob_start();
    ?>
    <!-- Fixed Header Bar -->
    <nav class="megamenu" role="navigation" aria-label="<?php echo esc_attr($menu_data['menu']['name']); ?>">
        <?php if ($is_branded_context && ($brand_logo_url || $brand_logo_title)): ?>
        <!-- Mobile: active brand logo only (Academy root logo retired in branded context) -->
        <a href="<?php echo esc_url($brand_logo_href); ?>" class="megamenu__mobile-logo-link megamenu__mobile-logo-link--brand" aria-label="<?php echo esc_attr($brand_logo_title); ?>"<?php echo $brand_logo_target ? ' target="' . esc_attr($brand_logo_target) . '"' : ''; ?>>
            <?php if ($brand_logo_url): ?>
            <img src="<?php echo esc_attr($brand_logo_url); ?>" alt="<?php echo esc_attr($brand_logo_title); ?>" class="megamenu__mobile-header-logo" />
            <?php else: ?>
            <span class="megamenu__brand-label"><?php echo esc_html($brand_logo_title); ?></span>
            <?php endif; ?>
        </a>
        <?php elseif ($first_item_logo): ?>
        <!-- Mobile: root logo (persistent home) -->
        <a href="<?php echo esc_url($first_item_url); ?>" class="megamenu__mobile-logo-link megamenu__mobile-logo-link--root" aria-label="<?php echo esc_attr($first_item_title); ?>"<?php echo $first_item_target ? ' target="' . esc_attr($first_item_target) . '"' : ''; ?>>
            <img src="<?php echo esc_attr($first_item_logo); ?>" alt="<?php echo esc_attr($first_item_title); ?>" class="megamenu__mobile-header-logo" />
        </a>
        <?php endif; ?>

        <!-- Mobile Toggle -->
        <button class="megamenu__toggle" aria-expanded="false" aria-controls="megamenu-mobile" aria-label="Open menu">
            <span class="megamenu__toggle-icon"></span>
            <span class="megamenu__sr-only">Menu</span>
        </button>

        <!-- Desktop Navigation Bar -->
        <div class="megamenu__bar">
            <ul class="megamenu__list" role="menubar">
                <?php
                // In single-root mode: logo <li> first, then root's children.
                // In multi-root mode: original items (logo is part of the items list).
                echo $desktop_logo_li;
                echo render_megamenu_items($nav_items, 'desktop');
                ?>
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
            <?php
            // Mobile drawer also uses $nav_items so the root is not repeated
            // (the root logo already appears as the persistent mobile header logo).
            echo render_megamenu_items($nav_items, 'mobile');
            ?>
        </nav>
    </div>

    <?php if (!empty($active_subnav_items)): ?>
    <!-- Active section subnav: shown when current page is inside a nav-bar section -->
    <div class="megamenu__subnav" role="navigation" aria-label="Section navigation">
        <div class="megamenu__subnav-inner">
            <?php foreach ($active_subnav_items as $snav_item):
                $snav_current  = is_megamenu_current_item($snav_item);
                $snav_has_logo = !empty($snav_item['media_link']);
                $snav_href     = esc_url($snav_item['url'] ?: '#');
                $snav_cls      = 'megamenu__subnav-link'
                                 . ($snav_current  ? ' is-current' : '')
                                 . ($snav_has_logo ? ' has-logo'   : '');
                $snav_target   = $snav_item['target'] ? ' target="' . esc_attr($snav_item['target']) . '"' : '';
            ?>
            <a href="<?= $snav_href ?>"<?= $snav_target ?> class="<?= esc_attr($snav_cls) ?>"<?= $snav_current ? ' aria-current="page"' : '' ?>>
                <?php if ($snav_has_logo): ?>
                    <img class="megamenu__subnav-logo" src="<?= esc_attr(megamenu_get_relative_image_url($snav_item['media_link'])) ?>" alt="<?= esc_attr($snav_item['title']) ?>" />
                <?php else: ?>
                    <?= esc_html($snav_item['title']) ?>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

/**
 * Compute a short slug from a menu item for use as CSS class / data attribute.
 * Uses the linked post slug if available, otherwise sanitizes the title.
 *
 * @param array $item Processed menu item
 * @return string Sanitized slug (e.g. "the-polys")
 */
function _megamenu_item_slug($item) {
    // Prefer the linked post slug (already URL-safe)
    if (!empty($item['slug'])) {
        return sanitize_html_class($item['slug']);
    }
    // Fallback: sanitize title
    return sanitize_title($item['title']);
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
 * Returns true when the menu item links to a real page with meaningful post_content.
 * Uses the has_content flag populated from the query (CHAR_LENGTH > 20 check).
 */
function megamenu_item_has_real_page( $item ) {
    return !empty( $item['has_content'] )
        && !empty( $item['url'] )
        && $item['url'] !== '#';
}

/**
 * Returns true when the current queried page lives anywhere in $item's subtree.
 * Used to add the is-ancestor CSS class for breadcrumb highlighting.
 */
function megamenu_item_is_ancestor( $item ) {
    global $post;
    if ( ! $post || empty( $item['children'] ) ) {
        return false;
    }
    $flat        = [];
    _polys_megamenu_flatten_tree( $item['children'], $flat );
    $current_url = trailingslashit( get_permalink( $post->ID ) );
    foreach ( $flat as $desc ) {
        if ( $post->ID && (int) $desc['object_id'] === $post->ID ) {
            return true;
        }
        if ( $current_url && trailingslashit( $desc['url'] ) === $current_url ) {
            return true;
        }
    }
    return false;
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
    if ( !$is_current && $has_children && megamenu_item_is_ancestor( $item ) ) {
        $classes[] = 'is-ancestor';
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
            // Text item with children: split into navigable link + dropdown trigger when a real page exists
            if ( megamenu_item_has_real_page( $item ) ) {
                $nav_target = $item['target'] ? ' target="' . esc_attr( $item['target'] ) . '"' : '';
                $output .= '<a href="' . esc_url( $item['url'] ) . '"' . $nav_target . ' role="menuitem">';
                $output .= esc_html( $item['title'] );
                $output .= '</a>';
                $output .= '<button type="button" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '" role="none" aria-haspopup="true" class="megamenu__dropdown-trigger">';
                $output .= '<span class="megamenu__sr-only">' . esc_html( $item['title'] ) . ' submenu</span>';
                $output .= '</button>';
            } else {
                // No real page content — trigger-only behaviour
                $output .= '<button type="button" aria-expanded="false" aria-controls="' . esc_attr($panel_id) . '" role="menuitem" aria-haspopup="true">';
                $output .= esc_html($item['title']);
                $output .= '</button>';
            }
        }
        
        // Panel with children — add slug class + data-mm for CSS/JS targeting.
        // Layout rule: if any level-2 child has its own children (an L3 exists)
        // the panel is a horizontal mega panel; if the children are all leaf
        // links (level-2 only) it renders as a traditional vertical dropdown.
        $item_slug = _megamenu_item_slug($item);
        $has_l3 = false;
        foreach ($item['children'] as $l2_child) {
            if (!empty($l2_child['children'])) {
                $has_l3 = true;
                break;
            }
        }
        $panel_classes  = 'megamenu__panel';
        $panel_classes .= $has_l3 ? ' megamenu__panel--mega' : ' megamenu__panel--dropdown';
        if ($item_slug) {
            $panel_classes .= ' ' . $item_slug;
        }
        $inner_cols = $has_l3 ? 'cols-auto' : 'cols-1';
        $data_mm = $item_slug ? ' data-mm="mm_' . esc_attr($item_slug) . '"' : '';
        $output .= '<div class="' . esc_attr($panel_classes) . '" id="' . esc_attr($panel_id) . '" data-state="closed" role="menu"' . $data_mm . '>';
        $output .= '<div class="megamenu__panel-inner ' . $inner_cols . '">';
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
 * Render panel content — direct children of a hovered top-bar item.
 *
 * Progressive depth behaviour:
 *   Items that have their own children get class megamenu__group--has-sub.
 *   Their child list is hidden by default and revealed on CSS :hover/:focus-within
 *   (see megamenu.scss .megamenu__group--has-sub rules).
 *   This means hovering "Programs" shows brand titles only; hovering a brand
 *   title expands that brand's children without leaving the panel.
 *
 * Media/logo:
 *   If a panel item has a media_link (set in WP Admin → Menus → custom field),
 *   it is rendered as an image inside the group title instead of text.
 *
 * Classes:
 *   $item['classes'] is propagated to the group <div> so SCSS rules such as
 *   .megamenu__group.polys and .megamenu__group._event fire correctly.
 */
function render_megamenu_panel_content($items) {
    $output = '';

    foreach ($items as $item) {
        $has_children = !empty($item['children']);
        $has_logo     = !empty($item['media_link']);

        // Build group class list.
        // - Always: megamenu__group
        // - When item has WP menu classes (polys, _event, etc.): add them so SCSS fires
        // - When item slug exists: add slug for CSS/JS targeting
        // - When item has children: add megamenu__group--has-sub for progressive reveal
        $group_classes = ['megamenu__group'];
        if (!empty($item['classes'])) {
            $group_classes[] = $item['classes'];
        }
        $item_slug = _megamenu_item_slug($item);
        if ($item_slug) {
            $group_classes[] = $item_slug;
        }
        if ($has_children) {
            $group_classes[] = 'megamenu__group--has-sub';
        }

        $output .= '<div class="' . esc_attr(implode(' ', $group_classes)) . '">';

        // ── Group title ───────────────────────────────────────────────────────
        // Wraps the item link/image.  For items with children this is the only
        // visible element until hover; for leaf items the link is shown directly.
        if ($has_children) {
            $output .= '<h3 class="megamenu__group-title">';
            if ($item['url'] && $item['url'] !== '#') {
                $output .= '<a href="' . esc_url($item['url']) . '">';
                if ($has_logo) {
                    $logo_url = megamenu_get_relative_image_url($item['media_link']);
                    $output  .= '<img class="megamenu__panel-logo-img" src="' . esc_attr($logo_url) . '" alt="' . esc_attr($item['title']) . '" />';
                } else {
                    $output .= esc_html($item['title']);
                }
                $output .= '</a>';
            } else {
                if ($has_logo) {
                    $logo_url = megamenu_get_relative_image_url($item['media_link']);
                    $output  .= '<img class="megamenu__panel-logo-img" src="' . esc_attr($logo_url) . '" alt="' . esc_attr($item['title']) . '" />';
                } else {
                    $output .= esc_html($item['title']);
                }
            }
            $output .= '</h3>';

            // ── Children: leaf list OR recursive column grid ──────────────────
            // If any child itself has children, render the whole level as a
            // horizontal column grid (same structure as the top-level panel)
            // so sub-groups spread across rather than stacking into one column.
            // If all children are leaves, use a simple vertical link list.
            $children_have_sub = false;
            foreach ($item['children'] as $child) {
                if (!empty($child['children'])) {
                    $children_have_sub = true;
                    break;
                }
            }

            if ($children_have_sub) {
                $output .= '<div class="megamenu__group-list megamenu__group-list--cols">';
                $output .= render_megamenu_panel_content($item['children']);
                $output .= '</div>';
            } else {
                $output .= '<ul class="megamenu__group-list">';
                foreach ($item['children'] as $child) {
                    $output .= '<li>';
                    $output .= '<a href="' . esc_url($child['url']) . '" class="megamenu__link">';
                    $output .= esc_html($child['title']);
                    $output .= '</a>';
                    $output .= '</li>';
                }
                $output .= '</ul>';
            }

        } else {
            // ── Leaf item: simple link, with logo if available ─────────────────
            $link_url = $item['url'] ?: '#';
            $output  .= '<a href="' . esc_url($link_url) . '" class="megamenu__link">';
            if ($has_logo) {
                $logo_url = megamenu_get_relative_image_url($item['media_link']);
                $output  .= '<img class="megamenu__panel-logo-img" src="' . esc_attr($logo_url) . '" alt="' . esc_attr($item['title']) . '" />';
            } else {
                $output .= esc_html($item['title']);
            }
            $output .= '</a>';
        }

        $output .= '</div>';
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
 *
 * LEGACY NOTE: This section assumes brand roots are top-level (L1) menu items
 * and uses hardcoded URL prefix matching via _sectionbar_detect_brand().
 * It also hardcodes 'brand-academy', 'polys2', and section_class values.
 * The next rendering pass should replace this logic with:
 *   polys_megamenu_get_active_brand_root_node() → use its children as the
 *   section bar items, its brand_key for the data-brand attribute.
 * Do not change behaviour here until the new renderer is ready and tested.
 * =============================================================================
 */

/**
 * Get the active L1 menu item and its L2 children based on current URL.
 *
 * @deprecated Pending replacement by polys_megamenu_get_active_brand_root_node().
 *             Hardcoded URL patterns and brand strings will be removed once the
 *             brand-based renderer is wired in.  Behaviour is preserved intact.
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
    
    // Fallback: if no URL match, try matching via the current post's parent hierarchy or section_class.
    // This handles event child pages (red-carpet, ceremony, etc.) whose URLs are nested
    // under a parent event that IS in the megamenu but the child page itself isn't an L2 item.
    if (!$best_match && $post) {
        // Walk up the post parent chain to find an ancestor whose URL matches an L2 item
        $ancestor_id = $post->post_parent;
        $max_depth = 5; // safety limit
        while ($ancestor_id && $max_depth-- > 0) {
            $ancestor_path = rtrim(parse_url(get_permalink($ancestor_id), PHP_URL_PATH), '/');
            foreach ($menu_data['items'] as $l1_item) {
                if (empty($l1_item['children'])) continue;
                $l1_path = rtrim(parse_url($l1_item['url'], PHP_URL_PATH), '/');
                // Check if ancestor matches L1
                if (!empty($l1_path) && $l1_path !== '/' && $l1_path !== '#' && $ancestor_path === $l1_path) {
                    return [
                        'parent' => $l1_item,
                        'children' => $l1_item['children'],
                        'brand' => _sectionbar_detect_brand($l1_path)
                    ];
                }
                // Check if ancestor matches any L2
                foreach ($l1_item['children'] as $l2_item) {
                    $l2_path = rtrim(parse_url($l2_item['url'], PHP_URL_PATH), '/');
                    if ($ancestor_path === $l2_path) {
                        return [
                            'parent' => $l1_item,
                            'children' => $l1_item['children'],
                            'brand' => _sectionbar_detect_brand($l1_path)
                        ];
                    }
                }
            }
            $ancestor = get_post($ancestor_id);
            $ancestor_id = $ancestor ? $ancestor->post_parent : 0;
        }

        // Last resort: brand hint. Prefer the authoritative brand_key meta; the
        // section_class formatting value (ceremony/red-carpet) is only a fallback.
        $section_class = get_post_meta($post->ID, 'section_class', true);
        $brand_key     = sanitize_key( (string) get_post_meta($post->ID, 'brand_key', true) );
        $polys_hint = in_array($brand_key, ['polys', 'the-polys'], true)
                   || ( $section_class && in_array($section_class, ['ceremony', 'red-carpet'], true) );
        if ($polys_hint) {
            // These are Polys events — find the Polys L1 item by slug, class, or URL
            foreach ($menu_data['items'] as $l1_item) {
                if (empty($l1_item['children'])) continue;
                $l1_slug = !empty($l1_item['slug']) ? $l1_item['slug'] : '';
                $l1_path = rtrim(parse_url($l1_item['url'], PHP_URL_PATH), '/');
                $l1_classes = isset($l1_item['classes_array']) ? $l1_item['classes_array'] : [];
                $is_polys = ($l1_slug === 'the-polys')
                    || _sectionbar_detect_brand($l1_path) === 'polys'
                    || in_array('polys', $l1_classes, true)
                    || in_array('polys2', $l1_classes, true)
                    || (stripos($l1_item['title'], 'polys') !== false);
                if ($is_polys) {
                    return [
                        'parent' => $l1_item,
                        'children' => $l1_item['children'],
                        'brand' => 'polys'
                    ];
                }
            }
        }
    }

    // Before falling back to Academy, check if the current post has a section_menu meta.
    // If it does, skip the Academy fallback — render_sectionbar will handle it via
    // _sectionbar_from_section_menu() which builds the bar from that menu directly.
    if (!$best_match && $post) {
        $post_section_menu = get_post_meta($post->ID, 'section_menu', true);
        // Also check parent
        if (empty($post_section_menu) && $post->post_parent) {
            $post_section_menu = get_post_meta($post->post_parent, 'section_menu', true);
        }
        if (!empty($post_section_menu)) {
            // Return null so render_sectionbar's section_menu fallback kicks in
            return null;
        }
    }

    // If no URL match found but we're in Academy context, use Academy L1 as fallback
    if (!$best_match && $current_brand === 'academy' && $academy_fallback) {
        return $academy_fallback;
    }

    return $best_match;
}

/**
 * Detect brand from URL path for styling purposes.
 *
 * @deprecated LEGACY — hardcoded URL slug list.  Brand paths are now derived
 *             dynamically from the megamenu via polys_megamenu_get_brand_index().
 *             This function is still called by get_sectionbar_data(); remove
 *             both together when the section bar renderer is updated.
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
 * Build sectionbar data from a section_menu (separate WP nav menu, not the megamenu).
 * Used as fallback when URL-based megamenu matching fails (e.g. red-carpet events).
 *
 * @deprecated LEGACY — brand is detected from section_menu slug string using
 *             hardcoded substring matches (metatraversal, rpg, ready-player, academy).
 *             When the section bar renderer is updated to use the megamenu brand
 *             system, this fallback should be replaced with a menu-ancestry lookup
 *             on the queried object.  Behaviour is preserved intact for now.
 *
 * @param string $section_menu_slug  The nav menu slug from post meta (e.g. 'virtual-red-carpet-1')
 * @param string $section_class      The section_class meta value (e.g. 'red-carpet', 'ceremony')
 * @return array|null  Sectionbar data array or null
 */
function _sectionbar_from_section_menu($section_menu_slug, $section_class = '') {
    $menu_items = wp_get_nav_menu_items($section_menu_slug);
    if (empty($menu_items)) {
        return null;
    }

    // Find top-level items (parent == 0) — these are the L1 events
    $top_items = [];
    foreach ($menu_items as $item) {
        if (empty($item->menu_item_parent) || $item->menu_item_parent == 0) {
            $top_items[] = $item;
        }
    }

    if (empty($top_items)) {
        return null;
    }

    // Build children array in the same format get_sectionbar_data returns
    $children = [];
    foreach ($top_items as $item) {
        $children[] = [
            'id'       => $item->ID,
            'title'    => $item->title,
            'url'      => $item->url ?: '#',
            'slug'     => '',
            'classes'  => implode(' ', (array)$item->classes),
            'children' => [],
        ];
    }

    // Detect brand from section_class or menu slug
    $brand = 'polys'; // default for ceremony/red-carpet
    if (stripos($section_menu_slug, 'metatraversal') !== false) {
        $brand = 'metatraversal';
    } elseif (stripos($section_menu_slug, 'rpg') !== false || stripos($section_menu_slug, 'ready-player') !== false) {
        $brand = 'rpg';
    } elseif (stripos($section_menu_slug, 'academy') !== false) {
        $brand = 'academy';
    }

    // Use the first top-level item as the "parent" label, or synthesize one from menu name
    $menu_obj = wp_get_nav_menu_object($section_menu_slug);
    $parent_title = $menu_obj ? $menu_obj->name : ucwords(str_replace('-', ' ', $section_menu_slug));

    return [
        'parent'   => [
            'id'       => 0,
            'title'    => $parent_title,
            'url'      => '#',
            'slug'     => sanitize_title($parent_title),
            'classes'  => $section_class,
            'children' => $children,
        ],
        'children' => $children,
        'brand'    => $brand,
    ];
}

/**
 * Render the Section bar HTML
 *
 * @param string $menu_slug The menu slug (default: 'megamenu')
 * @return string HTML output
 */
function render_sectionbar($menu_slug = 'megamenu') {
    $data = get_sectionbar_data($menu_slug);

    // Fallback: if megamenu matching failed, try building sectionbar from section_menu meta.
    // Walks up the post_parent chain since child pages (red-carpet, etc.) may not have
    // section_menu set directly — it may live on the parent event.
    if (!$data) {
        global $post;
        if ($post) {
            $section_menu = get_post_meta($post->ID, 'section_menu', true);
            $section_class = get_post_meta($post->ID, 'section_class', true);

            // Walk up parents to find section_menu if not on current post
            if (empty($section_menu) && $post->post_parent) {
                $ancestor_id = $post->post_parent;
                $depth = 5;
                while ($ancestor_id && $depth-- > 0) {
                    $sm = get_post_meta($ancestor_id, 'section_menu', true);
                    if (!empty($sm)) {
                        $section_menu = $sm;
                        // Also grab section_class from ancestor if we don't have one
                        if (empty($section_class)) {
                            $section_class = get_post_meta($ancestor_id, 'section_class', true);
                        }
                        break;
                    }
                    $anc = get_post($ancestor_id);
                    $ancestor_id = $anc ? $anc->post_parent : 0;
                }
            }

            if (!empty($section_menu)) {
                $data = _sectionbar_from_section_menu($section_menu, $section_class);
            }
        }
    }

    // No active L1 with children - don't render
    if (!$data) {
        // DEBUG: temporary — remove after confirming red-carpet fix
        if (polys_is_dev()) {
            global $post;
            $dbg = 'sectionbar: no match';
            if ($post) {
                $sc = get_post_meta($post->ID, 'section_class', true) ?: '(empty)';
                $sm = get_post_meta($post->ID, 'section_menu', true) ?: '(empty)';
                $pt = $post->post_type;
                $pp = $post->post_parent;
                $uri = $_SERVER['REQUEST_URI'] ?? '';
                // Check parent too
                $psm = $pp ? (get_post_meta($pp, 'section_menu', true) ?: '(empty)') : 'N/A';
                $psc = $pp ? (get_post_meta($pp, 'section_class', true) ?: '(empty)') : 'N/A';
                $dbg .= " | sc=$sc | sm=$sm | pt=$pt | parent=$pp | parent_sm=$psm | parent_sc=$psc | uri=$uri";
                // Test wp_get_nav_menu_items on the found menu
                if (!empty($sm) || (!empty($psm) && $psm !== '(empty)')) {
                    $test_slug = !empty($sm) ? $sm : $psm;
                    $test_items = wp_get_nav_menu_items($test_slug);
                    $dbg .= " | menu_items_count=" . ($test_items ? count($test_items) : 'FALSE');
                    $test_obj = wp_get_nav_menu_object($test_slug);
                    $dbg .= " | menu_obj=" . ($test_obj ? $test_obj->slug : 'FALSE');
                }
            } else {
                $dbg .= ' | no $post';
            }
            return "<!-- $dbg -->";
        }
        return '';
    }
    
    $brand = $data['brand'];
    $parent = $data['parent'];
    $children = $data['children'];
    $section_slug = _megamenu_item_slug($parent);

    ob_start();
    ?>
    <nav class="sectionbar" data-brand="<?php echo esc_attr($brand); ?>" aria-label="<?php echo esc_attr($parent['title']); ?> section navigation">
        <div class="sectionbar__inner <?php echo esc_attr($section_slug); ?>"<?php echo $section_slug ? ' data-mm="mm_' . esc_attr($section_slug) . '"' : ''; ?>>
            <ul class="sectionbar__list">
                <?php foreach ($children as $item): 
                    $is_current = is_sectionbar_current($item['url']);
                ?>
                <li class="sectionbar__item<?php echo !empty($item['classes']) ? ' ' . esc_attr($item['classes']) : ''; ?>">
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

// =============================================================================
// Brand Resolution Helpers
// =============================================================================
// These helpers support the new dynamic brand detection architecture.
// They do NOT affect rendering yet — wire polys_get_active_brand_key() into
// templates only after testing resolution on all brands.
// =============================================================================

/**
 * Extract brand key from a menu item's classes array.
 *
 * Strict: only accepts classes starting with "brand-". The exact string
 * after the prefix is returned as-is — no normalization, no aliases.
 * If the menu item class is wrong or missing, that is a data issue.
 *
 * Expected classes: brand-thepolys, brand-metatraversal, brand-ready-player-golf
 *
 * @param  array  $classes_array  Classes array from a processed menu item.
 * @return string Brand key (e.g. "thepolys", "metatraversal", "ready-player-golf")
 *                or empty string if no brand- class found.
 */
function polys_megamenu_extract_brand_key( $classes_array ) {
    if ( ! is_array( $classes_array ) ) {
        return '';
    }
    foreach ( $classes_array as $class ) {
        $raw = trim( (string) $class );
        if ( strpos( $raw, 'brand-' ) === 0 ) {
            return substr( $raw, 6 );
        }
    }
    return '';
}

/**
 * Recursively flatten a menu item tree into a lookup map keyed by menu item ID.
 *
 * Used internally so other helpers can do O(1) parent-chain walks without
 * re-traversing the tree.  Each entry in $flat is the full processed menu item
 * array (including children, level, parent_id, classes_array, etc.).
 *
 * @param  array $items  Array of processed menu item nodes (from get_megamenu_data).
 * @param  array &$flat  Accumulator — pass an empty array [], receives all nodes.
 */
function _polys_megamenu_flatten_tree( array $items, array &$flat ) {
    foreach ( $items as $item ) {
        $flat[ $item['id'] ] = $item;
        if ( ! empty( $item['children'] ) ) {
            _polys_megamenu_flatten_tree( $item['children'], $flat );
        }
    }
}

/**
 * Recursively count all descendant nodes in a subtree.
 *
 * @param  array $items  Children array from a processed menu item.
 * @return int   Total descendant count (not including the root node itself).
 */
function _polys_megamenu_count_descendants( array $items ) {
    $count = 0;
    foreach ( $items as $item ) {
        $count++;
        if ( ! empty( $item['children'] ) ) {
            $count += _polys_megamenu_count_descendants( $item['children'] );
        }
    }
    return $count;
}

/**
 * Build a brand index from the megamenu tree.
 *
 * Traverses the ENTIRE menu tree at all depths, not just root items.
 * Brand roots (items with a brand-* CSS class) can live at any level —
 * e.g. Academy at depth 1, The Polys at depth 3 inside Programs.
 *
 * Returns an associative array keyed by the normalised brand key so
 * callers can do $index['the-polys'] or array_keys() to discover all
 * brands without hardcoding them.
 *
 * Each record includes:
 *   brand_key, menu_item_id, object_id, title, url, classes (string),
 *   classes_array, depth (level), parent_id, children (direct subtree),
 *   menu_item (full processed item).
 *
 * Results are statically cached per menu_slug within a single request.
 *
 * @param  string $menu_slug  Menu slug (default: 'megamenu').
 * @return array  Associative array keyed by brand_key; empty array if menu
 *                not found or no brand roots present.
 */
function polys_megamenu_get_brand_index( $menu_slug = 'megamenu' ) {
    static $cache = [];
    if ( isset( $cache[ $menu_slug ] ) ) {
        return $cache[ $menu_slug ];
    }

    $menu_data = get_megamenu_data( $menu_slug );
    if ( ! $menu_data || empty( $menu_data['items'] ) ) {
        return $cache[ $menu_slug ] = [];
    }

    // Flatten the entire tree so brand-* items at any depth are found.
    $flat = [];
    _polys_megamenu_flatten_tree( $menu_data['items'], $flat );

    $brands = [];
    foreach ( $flat as $item ) {
        $brand_key = polys_megamenu_extract_brand_key( $item['classes_array'] );
        if ( ! $brand_key ) {
            continue;
        }
        $brands[ $brand_key ] = [
            'brand_key'     => $brand_key,
            'menu_item_id'  => $item['id'],
            'object_id'     => $item['object_id'],
            'title'         => $item['title'],
            'url'           => $item['url'],
            'classes'       => $item['classes'],
            'classes_array' => $item['classes_array'],
            'depth'         => $item['level'],
            'parent_id'     => $item['parent_id'],
            'children'      => $item['children'],
            'menu_item'     => $item,
        ];
    }

    return $cache[ $menu_slug ] = $brands;
}

/**
 * Find the active brand by walking the megamenu ancestry.
 *
 * Strategy: flatten the entire menu tree into a map keyed by menu item ID,
 * then for each menu item whose object_id matches the queried object, walk UP
 * the parent_id chain checking each node for a brand-* class.  The first
 * brand-* class found is the nearest (most specific) brand ancestor.
 *
 * This works at arbitrary menu depth — brand roots do not need to be at the
 * top level.  It also handles cross-post-type cases: an event placed as a
 * menu item under The Polys will resolve to "the-polys" even though there is
 * no post_parent link between them.
 *
 * @param  int    $object_id  WordPress object ID (post ID, term ID, etc.).
 * @param  string $menu_slug  Menu slug (default: 'megamenu').
 * @return string Brand key, or empty string if object not found under any brand root.
 */
function polys_megamenu_find_brand_by_ancestry( $object_id, $menu_slug = 'megamenu' ) {
    if ( ! $object_id ) {
        return '';
    }
    $object_id = (int) $object_id;

    $menu_data = get_megamenu_data( $menu_slug );
    if ( ! $menu_data || empty( $menu_data['items'] ) ) {
        return '';
    }

    // Build a flat lookup map keyed by menu item ID so parent walks are O(1).
    $flat = [];
    _polys_megamenu_flatten_tree( $menu_data['items'], $flat );

    // Find all menu item IDs whose linked object_id matches.
    $matched_ids = [];
    foreach ( $flat as $menu_item_id => $node ) {
        if ( (int) $node['object_id'] === $object_id ) {
            $matched_ids[] = $menu_item_id;
        }
    }
    if ( empty( $matched_ids ) ) {
        return '';
    }

    // For each match, walk the parent chain (starting from the item itself).
    // Return the nearest brand-* class found, i.e. the most specific brand.
    foreach ( $matched_ids as $start_id ) {
        $current_id  = (int) $start_id;
        $depth_limit = 12; // safety; realistic menus are < 6 levels
        while ( $current_id && $depth_limit-- > 0 ) {
            if ( ! isset( $flat[ $current_id ] ) ) {
                break;
            }
            $node      = $flat[ $current_id ];
            $brand_key = polys_megamenu_extract_brand_key( $node['classes_array'] );
            if ( $brand_key ) {
                return $brand_key;
            }
            // parent_id is 0 at root; cast to int so the while condition exits cleanly.
            $current_id = (int) $node['parent_id'];
        }
    }
    return '';
}

/**
 * Get brand_key from post meta, walking up the native post_parent chain.
 *
 * Checks the post's own "brand_key" meta first.  If not set, walks up
 * post_parent ancestors (up to 6 levels) and returns the nearest one found.
 * Falls back to empty string — callers decide whether to continue to the
 * menu ancestry step.
 *
 * NOTE: post_parent only links pages to pages and child posts of the same
 * type.  Events attached to brand pages via the menu will NOT be covered
 * here — use polys_megamenu_find_brand_by_ancestry() for that.
 *
 * @param  int|null $post_id  Post ID; omit or pass null to use the queried object.
 * @return string Brand key from meta, or empty string.
 */
function polys_get_brand_key_from_meta( $post_id = null ) {
    if ( ! $post_id ) {
        $queried = get_queried_object();
        $post_id = ( $queried && isset( $queried->ID ) ) ? (int) $queried->ID : 0;
    }
    if ( ! $post_id ) {
        return '';
    }
    $post_id = (int) $post_id;

    // Direct meta on current post.
    $brand_key = get_post_meta( $post_id, 'brand_key', true );
    if ( $brand_key ) {
        return sanitize_key( $brand_key );
    }

    // Walk up post_parent chain.
    $current_id = $post_id;
    for ( $i = 0; $i < 6; $i++ ) {
        $post_obj = get_post( $current_id );
        if ( ! $post_obj || ! $post_obj->post_parent ) {
            break;
        }
        $parent_id    = (int) $post_obj->post_parent;
        $parent_brand = get_post_meta( $parent_id, 'brand_key', true );
        if ( $parent_brand ) {
            return sanitize_key( $parent_brand );
        }
        $current_id = $parent_id;
    }
    return '';
}

/**
 * Main brand resolver.
 *
 * Resolution order:
 *   1. Domain mapping via 'polys_brand_from_domain' filter.
 *   2. Explicit brand_key post meta (direct or inherited via post_parent).
 *   3. Menu ancestry — walks the megamenu tree from the queried object's
 *      menu placement up through parent nodes, returning the first brand-*
 *      class found (cross-post-type safe).
 *   Returns empty string if none of the above resolves.
 *
 * @param  string $menu_slug  Menu slug (default: 'megamenu').
 * @return string Resolved brand key, or empty string if undetermined.
 */
function polys_get_active_brand_key( $menu_slug = 'megamenu' ) {
    // 1. Domain mapping (extend by hooking 'polys_brand_from_domain').
    $host         = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
    $domain_brand = apply_filters( 'polys_brand_from_domain', '', $host );
    if ( $domain_brand ) {
        return sanitize_key( $domain_brand );
    }

    // 2. Post meta (direct or inherited via post_parent).
    $queried    = get_queried_object();
    $queried_id = ( $queried && isset( $queried->ID ) ) ? (int) $queried->ID : 0;

    $meta_brand = polys_get_brand_key_from_meta( $queried_id );
    if ( $meta_brand ) {
        return $meta_brand;
    }

    // 3. Menu ancestry (handles events/CPTs nested under brand roots).
    if ( $queried_id ) {
        $ancestry_brand = polys_megamenu_find_brand_by_ancestry( $queried_id, $menu_slug );
        if ( $ancestry_brand ) {
            return $ancestry_brand;
        }
    }

    return '';
}

/**
 * Return the full brand index record for the currently active brand.
 *
 * Direct lookup only — no aliases, no fallback recovery.
 * Returns null if brand key is empty or not present in the index.
 *
 * @param  string $menu_slug  Menu slug (default: 'megamenu').
 * @return array|null  Full brand index record, or null.
 */
function polys_megamenu_get_active_brand_root_node( $menu_slug = 'megamenu' ) {
    $brand_key   = polys_get_active_brand_key( $menu_slug );
    $brand_index = polys_megamenu_get_brand_index( $menu_slug );
    return $brand_index[ $brand_key ] ?? null;
}

/**
 * Debug helper: return structured resolution data for all brand detection steps.
 *
 * Intentionally never echoed automatically.  Use the [polys_brand_debug]
 * shortcode or call this from a template while logged in as admin.
 *
 * Returned keys (resolution):
 *   queried_object_id, queried_object_type, host, path,
 *   resolved_brand_key, resolution_source,
 *   meta_brand_key (direct meta only, not inherited),
 *   inherited_brand_key (first ancestor meta, no direct),
 *   menu_ancestry_brand_key, path_brand_key,
 *   available_brand_keys — all brand-* roots found in the menu at any depth,
 *   brand_index_summary — depth/title/url for each brand root (diagnostics).
 *
 * Returned keys (active brand root):
 *   active_brand_root_title, active_brand_root_menu_item_id,
 *   active_brand_root_depth, active_brand_root_url,
 *   active_brand_direct_children (array of title + url),
 *   active_brand_descendant_count.
 *
 * @param  string $menu_slug  Menu slug (default: 'megamenu').
 * @return array  Structured debug data.
 */
function polys_debug_active_brand_resolution( $menu_slug = 'megamenu' ) {
    $queried      = get_queried_object();
    $queried_id   = ( $queried && isset( $queried->ID ) ) ? (int) $queried->ID : 0;
    $queried_type = '';
    if ( $queried ) {
        if ( isset( $queried->post_type ) ) {
            $queried_type = $queried->post_type;
        } elseif ( isset( $queried->taxonomy ) ) {
            $queried_type = 'term:' . $queried->taxonomy;
        }
    }

    $host        = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    $path        = rtrim( parse_url( $request_uri, PHP_URL_PATH ), '/' );

    // ── Step 1: domain mapping ──────────────────────────────────────────────
    $domain_brand = sanitize_key( apply_filters( 'polys_brand_from_domain', '', $host ) );

    // ── Step 2: meta — split direct vs inherited ────────────────────────────
    $direct_meta_brand = '';
    $inherited_brand   = '';
    if ( $queried_id ) {
        $raw_meta = get_post_meta( $queried_id, 'brand_key', true );
        if ( $raw_meta ) {
            $direct_meta_brand = sanitize_key( $raw_meta );
        } else {
            $current_id = $queried_id;
            for ( $i = 0; $i < 6; $i++ ) {
                $post_obj = get_post( $current_id );
                if ( ! $post_obj || ! $post_obj->post_parent ) {
                    break;
                }
                $parent_id    = (int) $post_obj->post_parent;
                $parent_brand = get_post_meta( $parent_id, 'brand_key', true );
                if ( $parent_brand ) {
                    $inherited_brand = sanitize_key( $parent_brand );
                    break;
                }
                $current_id = $parent_id;
            }
        }
    }
    $combined_meta_brand = $direct_meta_brand ?: $inherited_brand;

    // ── Step 3: menu ancestry ───────────────────────────────────────────────
    $ancestry_brand = $queried_id
        ? polys_megamenu_find_brand_by_ancestry( $queried_id, $menu_slug )
        : '';

    // ── Step 4: URL path prefix ─────────────────────────────────────────────
    $brand_index = polys_megamenu_get_brand_index( $menu_slug );
    $path_brand  = '';
    $best_length = 0;
    foreach ( $brand_index as $bk => $brand ) {
        $brand_path = rtrim( parse_url( $brand['url'], PHP_URL_PATH ), '/' );
        if ( empty( $brand_path ) || $brand_path === '/' ) {
            continue;
        }
        if ( $path === $brand_path || strpos( $path, $brand_path . '/' ) === 0 ) {
            $length = strlen( $brand_path );
            if ( $length > $best_length ) {
                $path_brand  = $bk;
                $best_length = $length;
            }
        }
    }

    // ── Determine winner ────────────────────────────────────────────────────
    $resolved = 'academy';
    $source   = 'fallback';
    if ( $domain_brand ) {
        $resolved = $domain_brand;
        $source   = 'domain_mapping';
    } elseif ( $combined_meta_brand ) {
        $resolved = $combined_meta_brand;
        $source   = $direct_meta_brand ? 'post_meta' : 'post_meta_inherited';
    } elseif ( $ancestry_brand ) {
        $resolved = $ancestry_brand;
        $source   = 'menu_ancestry';
    } elseif ( $path_brand ) {
        $resolved = $path_brand;
        $source   = 'url_path';
    }

    // ── Brand index summary (all brands found in menu, with depth/url) ──────
    $brand_index_summary = [];
    foreach ( $brand_index as $bk => $brand ) {
        $brand_index_summary[ $bk ] = [
            'title'        => $brand['title'],
            'depth'        => $brand['depth'],
            'url'          => $brand['url'],
            'menu_item_id' => $brand['menu_item_id'],
            'object_id'    => $brand['object_id'],
        ];
    }

    // ── Active brand root node details ──────────────────────────────────────
    $root_node        = $brand_index[ $resolved ] ?? null;
    $root_title       = $root_node ? $root_node['title'] : '';
    $root_item_id     = $root_node ? $root_node['menu_item_id'] : 0;
    $root_depth       = $root_node ? $root_node['depth'] : 0;
    $root_url         = $root_node ? $root_node['url'] : '';
    $root_children    = [];
    $descendant_count = 0;
    if ( $root_node && ! empty( $root_node['children'] ) ) {
        foreach ( $root_node['children'] as $child ) {
            $root_children[] = [ 'title' => $child['title'], 'url' => $child['url'] ];
        }
        $descendant_count = _polys_megamenu_count_descendants( $root_node['children'] );
    }

    return [
        // ── Request context ──────────────────────────────────────────────────
        'queried_object_id'            => $queried_id,
        'queried_object_type'          => $queried_type,
        'host'                         => $host,
        'path'                         => $path,
        // ── Resolution ───────────────────────────────────────────────────────
        'resolved_brand_key'           => $resolved,
        'resolution_source'            => $source,
        'meta_brand_key'               => $direct_meta_brand,
        'inherited_brand_key'          => $inherited_brand,
        'menu_ancestry_brand_key'      => $ancestry_brand,
        'path_brand_key'               => $path_brand,
        // ── Brand index ───────────────────────────────────────────────────────
        'available_brand_keys'         => array_keys( $brand_index ),
        'brand_index_summary'          => $brand_index_summary,
        // ── Active brand root ─────────────────────────────────────────────────
        'active_brand_root_title'      => $root_title,
        'active_brand_root_menu_item_id' => $root_item_id,
        'active_brand_root_depth'      => $root_depth,
        'active_brand_root_url'        => $root_url,
        'active_brand_direct_children' => $root_children,
        'active_brand_descendant_count' => $descendant_count,
    ];
}

/**
 * [polys_brand_debug] shortcode — admin-only brand resolution inspector.
 *
 * Usage: add [polys_brand_debug] to any page/post while logged in as admin.
 * Renders pre-formatted JSON showing every step of brand resolution for the
 * current request.  Never outputs anything to non-admins.
 */
add_shortcode( 'polys_brand_debug', function( $atts ) {
    if ( ! current_user_can( 'administrator' ) ) {
        return '';
    }
    $data   = polys_debug_active_brand_resolution( 'megamenu' );
    $json   = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    $output  = '<pre style="background:#111;color:#0f0;padding:1em;border-radius:4px;';
    $output .= 'font-size:12px;line-height:1.5;overflow:auto;text-align:left;">';
    $output .= esc_html( $json );
    $output .= '</pre>';
    return $output;
} );

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
                'url' => rewrite_url_to_brand_domain($l2_item->url),
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
            'url' => rewrite_url_to_brand_domain($l1_item->url),
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
            
            <?php // The Polys stands alone — no "fiscally sponsored project of the Academy" relationship line. ?>
            <?php if (!empty($brand_panel_data['description']) && $current_brand !== 'polys'): ?>
            <p class="footer-brand__relationship"><?php echo esc_html($brand_panel_data['description']); ?></p>
            <?php endif; ?>

            <?php // Dedicated horizontal Polys footer menu ('polys-footer' nav location) ?>
            <?php if ($current_brand === 'polys' && has_nav_menu('polys-footer')): ?>
            <nav class="footer-brand__menu footer-polys-menu" aria-label="The Polys footer menu">
                <?php wp_nav_menu(array(
                    'theme_location' => 'polys-footer',
                    'container'      => false,
                    'menu_class'     => 'footer-polys-menu__list',
                    'depth'          => 1,
                    'fallback_cb'    => false,
                )); ?>
            </nav>
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
    
    <?php
    // Academy footer: shown for Academy and academy-aware brands. Retired for
    // stand-alone brands — The Polys uses its own brand footer (above) instead.
    $show_academy_footer = ! ( $current_brand === 'polys' && $brand_panel_data );
    if ( $show_academy_footer ): ?>
    <!-- Academy Footer -->
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
    <?php endif; ?>

    <?php
    return ob_get_clean();
}
