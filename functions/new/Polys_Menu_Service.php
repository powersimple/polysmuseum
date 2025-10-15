<?php
/**
 * Polys_Menu_Service (new)
 * Centralized, read-only helpers around WordPress menus and nav_menu_item metadata.
 *
 * Safe: no side-effects. Add-only. Not auto-loaded yet.
 */

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/constants-meta.php';

class Polys_Menu_Service {
    /**
     * Get all nav menus (terms) ordered by name.
     * @return array
     */
    public static function getAllNavMenus(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT t.*, tt.term_taxonomy_id
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'nav_menu'
            ORDER BY t.name ASC");
    }

    /**
     * Resolve slugs possibly containing a wildcard (e.g., polys*).
     * @param string $menuSlug
     * @return array of slugs
     */
    public static function resolveMenuSlugs(string $menuSlug): array {
        global $wpdb;
        if (strpos($menuSlug, '*') !== false) {
            $pattern = str_replace('*', '', $menuSlug) . '%';
            $slugs = $wpdb->get_col($wpdb->prepare("SELECT t.slug
                FROM {$wpdb->terms} t
                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = 'nav_menu' AND t.slug LIKE %s
                ORDER BY t.slug ASC", $pattern));
            return is_array($slugs) ? $slugs : array();
        }
        return array($menuSlug);
    }

    /**
     * Given a menu slug, fetch its term and menu items (augmented with actual post type).
     * Returns array{menu: object, menu_items: object[]} or string error.
     */
    public static function getMenuItemsForSlug(string $slug) {
        global $wpdb;
        $menu_term = $wpdb->get_row($wpdb->prepare("SELECT t.*, tt.term_taxonomy_id
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'nav_menu' AND t.slug = %s", $slug));
        if (!$menu_term) { return "Menu not found: " . $slug; }

        $results = self::checkMenuRelationships((int)$menu_term->term_taxonomy_id);
        if (is_string($results)) { return $results; }
        return $results;
    }

    /**
     * Internal: check menu relationships similar to legacy helper.
     */
    public static function checkMenuRelationships(int $menuTermId) {
        global $wpdb;
        $menu = $wpdb->get_row($wpdb->prepare("SELECT t.*, tt.*
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.term_taxonomy_id = %d AND tt.taxonomy = 'nav_menu'", $menuTermId));
        if (!$menu) { return 'Menu not found'; }
        $menu_items = $wpdb->get_results($wpdb->prepare("SELECT p.*,
                pm1.meta_value as object_id,
                pm2.meta_value as object_type,
                pm3.meta_value as menu_item_parent,
                p2.post_type as actual_post_type
            FROM {$wpdb->posts} p
            JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_menu_item_object_id'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_menu_item_type'
            LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_menu_item_menu_item_parent'
            LEFT JOIN {$wpdb->posts} p2 ON pm1.meta_value = p2.ID
            WHERE tr.term_taxonomy_id = %d
            ORDER BY p.menu_order", $menuTermId));
        return array('menu' => $menu, 'menu_items' => $menu_items);
    }

    /**
     * Compute nesting level for a given menu item id.
     */
    public static function getNestingLevel(array $menuItems, $itemId, int $level = 0): int {
        $item = array_filter($menuItems, function($i) use ($itemId) { return $i->ID == $itemId; });
        $item = reset($item);
        if (!$item || !$item->menu_item_parent) { return $level; }
        return self::getNestingLevel($menuItems, $item->menu_item_parent, $level + 1);
    }
}
