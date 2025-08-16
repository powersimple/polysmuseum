<?php
/**
 * Database Audit Functions
 * 
 * Functions for auditing database relationships and menu structures
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Join array items with commas and a final conjunction (e.g., "A, B and C").
 *
 * @param array $items
 * @param string $conjunction Typically 'and'
 * @param string $separator Typically ', '
 * @return string
 */
function human_join(array $items, $conjunction = 'and', $separator = ', ') {
    $items = array_values(array_filter(array_map('trim', $items), function($v){ return $v !== ''; }));
    $count = count($items);
    if ($count === 0) return '';
    if ($count === 1) return $items[0];
    if ($count === 2) return $items[0] . ' ' . $conjunction . ' ' . $items[1];
    $last = array_pop($items);
    return implode($separator, $items) . $separator . $conjunction . ' ' . $last;
}

// Function to get menu item's parent menu with full relationship chain
function get_menu_item_parent_menu($menu_item_id) {
    global $wpdb;
    
    // First get the menu item's details
    $menu_item = $wpdb->get_row($wpdb->prepare("
        SELECT p.*, 
               pm1.meta_value as object_id,
               pm2.meta_value as object_type,
               pm3.meta_value as menu_item_parent
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_menu_item_object_id'
        LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_menu_item_type'
        LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_menu_item_menu_item_parent'
        WHERE p.ID = %d
    ", $menu_item_id));
    
    if (!$menu_item) {
        return array('error' => 'Menu item not found');
    }
    
    // Then get the menu it belongs to
    $menu = $wpdb->get_row($wpdb->prepare("
        SELECT t.name as menu_name, t.slug as menu_slug, tt.term_taxonomy_id
        FROM {$wpdb->term_relationships} tr
        JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
        WHERE tr.object_id = %d
        AND tt.taxonomy = 'nav_menu'
    ", $menu_item_id));
    
    // Get the object this menu item points to
    $object = null;
    if ($menu_item->object_id) {
        $object = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->posts} WHERE ID = %d
        ", $menu_item->object_id));
        
        // Get all postmeta for the object
        if ($object) {
            $object->meta = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM {$wpdb->postmeta} WHERE post_id = %d
            ", $object->ID));
        }
    }
    
    // Get parent menu item if exists
    $parent_item = null;
    if ($menu_item->menu_item_parent) {
        $parent_item = $wpdb->get_row($wpdb->prepare("
            SELECT p.*, 
                   pm1.meta_value as object_id,
                   pm2.meta_value as object_type
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_menu_item_object_id'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_menu_item_type'
            WHERE p.ID = %d
        ", $menu_item->menu_item_parent));
        
        if ($parent_item && $parent_item->object_id) {
            $parent_object = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->posts} WHERE ID = %d
            ", $parent_item->object_id));
            $parent_item->object = $parent_object;
        }
    }
    
    return array(
        'menu_item' => $menu_item,
        'menu' => $menu,
        'object' => $object,
        'parent_item' => $parent_item,
        'relationship_chain' => array(
            'menu_item_id' => $menu_item_id,
            'menu_item_title' => $menu_item->post_title,
            'object_id' => $menu_item->object_id,
            'object_type' => $object ? $object->post_type : $menu_item->object_type,
            'menu_name' => $menu ? $menu->menu_name : 'Unknown',
            'menu_id' => $menu ? $menu->term_taxonomy_id : 'Unknown',
            'object_title' => $object ? $object->post_title : 'Unknown',
            'parent_menu_item_id' => $menu_item->menu_item_parent,
            'parent_menu_item_title' => $parent_item ? $parent_item->post_title : null,
            'parent_object_id' => $parent_item ? $parent_item->object_id : null,
            'parent_object_title' => $parent_item && $parent_item->object ? $parent_item->object->post_title : null,
            'parent_object_type' => $parent_item && $parent_item->object ? $parent_item->object->post_type : null
        )
    );
}

// Function to check menu relationships
function check_menu_relationships($menu_term_id) {
    global $wpdb;
    
    // Get the menu details
    $menu = $wpdb->get_row($wpdb->prepare("
        SELECT t.*, tt.*
        FROM {$wpdb->terms} t
        JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        WHERE tt.term_taxonomy_id = %d
        AND tt.taxonomy = 'nav_menu'
    ", $menu_term_id));
    
    if (!$menu) {
        return "Menu not found";
    }
    
    // Get all menu items in this menu
    $menu_items = $wpdb->get_results($wpdb->prepare("
        SELECT p.*, 
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
        ORDER BY p.menu_order
    ", $menu_term_id));
    
    return array(
        'menu' => $menu,
        'menu_items' => $menu_items
    );
}

// Function to do reverse lookup of post IDs
function reverse_lookup_post($post_id) {
    global $wpdb;
    
    $results = array();
    
    // 1. Get the post itself
    $post = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->posts} WHERE ID = %d
    ", $post_id));
    
    if (!$post) {
        return "Post not found";
    }
    
    $results['post'] = $post;
    
    // 2. Get all post meta
    $results['meta'] = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->postmeta} WHERE post_id = %d
    ", $post_id));
    
    // 3. Find where this post is referenced in other post meta - ONLY for relevant relationships
    $results['referenced_in_meta'] = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID as post_id, p.post_title, p.post_type, pm.meta_key, pm.meta_value
        FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE (
            -- Event guests relationship
            (p.post_type = 'event' AND pm.meta_key = 'event_guests' AND pm.meta_value = %d)
            OR
            -- Menu item relationship
            (p.post_type = 'nav_menu_item' AND pm.meta_key = '_menu_item_object_id' AND pm.meta_value = %d)
            OR
            -- Profile relationships
            (p.post_type = 'profile' AND pm.meta_key = 'profile_events' AND pm.meta_value = %d)
        )
        AND p.ID != %d
    ", $post_id, $post_id, $post_id, $post_id));
    
    // 4. Check term relationships - ONLY for relevant taxonomies
    $results['term_relationships'] = $wpdb->get_results($wpdb->prepare("
        SELECT t.name, t.slug, tt.taxonomy
        FROM {$wpdb->term_relationships} tr
        JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
        WHERE tr.object_id = %d
        AND tt.taxonomy IN ('nav_menu', 'industry', 'platform', 'feature')
    ", $post_id));
    
    return $results;
}

// Function to get admin edit link
function get_admin_edit_link($post_id, $post_type) {
    if ($post_type === 'nav_menu_item') {
        return admin_url('nav-menus.php?action=edit&menu=0');
    }
    return admin_url("post.php?action=edit&post={$post_id}");
}

// Function to display audit results
function display_audit_results($post_ids = array()) {
    if (empty($post_ids)) {
        return;
    }

    foreach ($post_ids as $id) {
        $results = reverse_lookup_post($id);
        
        echo '<div class="card" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">';
        echo '<h2>Reverse Lookup for ID: ' . $id . '</h2>';
        
        if (is_string($results)) {
            echo '<p>' . esc_html($results) . '</p>';
            continue;
        }
        
        // Post Details
        echo '<h3>Post Details</h3>';
        echo '<ul>';
        echo '<li>Title: ' . esc_html($results['post']->post_title) . '</li>';
        echo '<li>Type: ' . esc_html($results['post']->post_type) . '</li>';
        echo '<li>Status: ' . esc_html($results['post']->post_status) . '</li>';
        echo '<li>Date: ' . esc_html($results['post']->post_date) . '</li>';
        echo '<li><a href="' . get_admin_edit_link($id, $results['post']->post_type) . '" target="_blank">Edit in WordPress Admin</a></li>';
        echo '</ul>';
        
        // Meta Data
        echo '<h3>Meta Data</h3>';
        echo '<table class="widefat" style="margin: 10px 0;">';
        echo '<thead><tr><th>Meta Key</th><th>Meta Value</th></tr></thead><tbody>';
        foreach ($results['meta'] as $meta) {
            echo '<tr>';
            echo '<td>' . esc_html($meta->meta_key) . '</td>';
            echo '<td>' . esc_html(substr($meta->meta_value, 0, 100)) . 
                 (strlen($meta->meta_value) > 100 ? '...' : '') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        
        // References in other posts
        echo '<h3>Referenced In Other Posts</h3>';
        if (!empty($results['referenced_in_meta'])) {
            echo '<ul>';
            foreach ($results['referenced_in_meta'] as $ref) {
                echo '<li>';
                echo '<strong>' . esc_html($ref->post_type) . ':</strong> ' . 
                     esc_html($ref->post_title) . ' (ID: ' . $ref->post_id . ')<br>';
                echo '<a href="' . get_admin_edit_link($ref->post_id, $ref->post_type) . '" target="_blank">Edit in WordPress Admin</a><br>';
                echo 'Meta Key: ' . esc_html($ref->meta_key) . '<br>';
                echo 'Meta Value: ' . esc_html(substr($ref->meta_value, 0, 100)) . 
                     (strlen($ref->meta_value) > 100 ? '...' : '') . '<br>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>Not referenced in any other posts</p>';
        }
        
        // Term Relationships
        echo '<h3>Term Relationships</h3>';
        if (!empty($results['term_relationships'])) {
            echo '<ul>';
            foreach ($results['term_relationships'] as $term) {
                echo '<li>' . esc_html($term->name) . ' (' . esc_html($term->taxonomy) . ')</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>No term relationships found</p>';
        }
        
        echo '</div>';
    }
}

// Function to find duplicate menu items
function find_duplicate_menu_items() {
    global $wpdb;
    
    $duplicates = $wpdb->get_results("
        SELECT 
            p.ID as menu_item_id,
            p.post_title as menu_item_title,
            pm1.meta_value as object_id,
            p2.post_title as object_title,
            p2.post_type as object_type,
            COUNT(*) as count
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_menu_item_object_id'
        JOIN {$wpdb->posts} p2 ON pm1.meta_value = p2.ID
        WHERE p.post_type = 'nav_menu_item'
        GROUP BY pm1.meta_value
        HAVING COUNT(*) > 1
        ORDER BY count DESC, object_title
    ");
    
    return $duplicates;
}

// Function to display duplicate menu items
function display_duplicate_menu_items() {
    $duplicates = find_duplicate_menu_items();
    
    if (empty($duplicates)) {
        echo '<div class="notice notice-success"><p>No duplicate menu items found.</p></div>';
        return;
    }
    
    echo '<div class="card" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">';
    echo '<h2>Appearances</h2>';
    echo '<table class="widefat" style="margin: 10px 0;">';
    echo '<thead><tr>';
    echo '<th>Object Title</th>';
    echo '<th>Object Type</th>';
    echo '<th>Object ID</th>';
    echo '<th>Times Used</th>';
    echo '<th>Menu Items</th>';
    echo '</tr></thead><tbody>';
    
    foreach ($duplicates as $dupe) {
        echo '<tr>';
        echo '<td>' . esc_html($dupe->object_title) . '</td>';
        echo '<td>' . esc_html($dupe->object_type) . '</td>';
        echo '<td>' . esc_html($dupe->object_id) . '</td>';
        echo '<td>' . esc_html($dupe->count) . '</td>';
        echo '<td>';
        
        // Get all menu items that reference this object
        $menu_items = get_menu_items_by_object_id($dupe->object_id);
        foreach ($menu_items as $item) {
            echo '<div>';
            echo 'Menu Item: ' . esc_html($item->post_title) . ' (ID: ' . $item->ID . ')<br>';
            echo 'Menu: ' . esc_html($item->menu_name) . '<br>';
            echo '<a href="' . get_admin_edit_link($item->ID, 'nav_menu_item') . '" target="_blank">Edit in WordPress Admin</a>';
            echo '</div><br>';
        }
        
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '</div>';
}

// Helper function to get menu items by object ID
function get_menu_items_by_object_id($object_id) {
    global $wpdb;
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT p.*, t.name as menu_name
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_menu_item_object_id'
        JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
        WHERE pm.meta_value = %d
        AND tt.taxonomy = 'nav_menu'
        ORDER BY t.name, p.menu_order
    ", $object_id));
}

// Function to find duplicate profiles and generate delete SQL
function profile_appearances() {
    global $wpdb;
    
    // Handle form submission first
    if (isset($_POST['delete_selected_profiles']) && !empty($_POST['delete_profiles'])) {
        $selected_ids = array_map('intval', $_POST['delete_profiles']);
        
        // Execute the delete statements
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE ID IN (" . implode(',', $selected_ids) . ")");
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN (" . implode(',', $selected_ids) . ")");
        
        echo '<div class="notice notice-success"><p>Selected profiles have been deleted.</p></div>';
    }
    
    // Find duplicate profiles by name, including drafts and other statuses
    $duplicate_profiles = $wpdb->get_results("
        SELECT 
            post_title,
            COUNT(*) as count,
            GROUP_CONCAT(ID) as profile_ids
        FROM {$wpdb->posts}
        WHERE post_type = 'profile'
        GROUP BY post_title
        HAVING COUNT(*) > 1
        ORDER BY count DESC, post_title
    ");
    
    if (empty($duplicate_profiles)) {
        echo '<div class="notice notice-success"><p>No duplicate profiles found.</p></div>';
        return;
    }
    
    echo '<form method="post" action="">';
    echo '<div class="card" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">';
    echo '<h2>Duplicate Profiles</h2>';
    
    foreach ($duplicate_profiles as $profile) {
        echo '<div class="profile-group" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd;">';
        echo '<h3>Profile: ' . esc_html($profile->post_title) . ' (Found ' . $profile->count . ' times)</h3>';
        
        // Get detailed info for each duplicate
        $profile_ids = explode(',', $profile->profile_ids);
        echo '<table class="widefat" style="margin: 10px 0; border-collapse: collapse; width: 100%;">';
        echo '<thead><tr>';
        echo '<th style="padding: 8px; border: 1px solid #ddd;">ID</th>';
        echo '<th style="padding: 8px; border: 1px solid #ddd;">Title</th>';
        echo '<th style="padding: 8px; border: 1px solid #ddd;">Type</th>';
        echo '<th style="padding: 8px; border: 1px solid #ddd;">Date</th>';
        echo '<th style="padding: 8px; border: 1px solid #ddd;">Status</th>';
        echo '<th style="padding: 8px; border: 1px solid #ddd;">Menu References</th>';
        echo '<th style="padding: 8px; border: 1px solid #ddd;">Meta Data</th>';
        echo '<th style="padding: 8px; border: 1px solid #ddd;">Action</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($profile_ids as $profile_id) {
            // Get profile details
            $profile_details = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->posts} WHERE ID = %d
            ", $profile_id));
            
            // Get menu references with menu names
            $menu_refs = $wpdb->get_results($wpdb->prepare("
                SELECT t.name as menu_name, COUNT(*) as count
                FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE p.post_type = 'nav_menu_item'
                AND pm.meta_key = '_menu_item_object_id'
                AND pm.meta_value = %d
                AND tt.taxonomy = 'nav_menu'
                GROUP BY t.name
            ", $profile_id));
            
            // Get postmeta data
            $meta_data = $wpdb->get_results($wpdb->prepare("
                SELECT meta_key, meta_value 
                FROM {$wpdb->postmeta} 
                WHERE post_id = %d
                ORDER BY meta_key
            ", $profile_id));
            
            echo '<tr>';
            echo '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($profile_id) . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($profile_details->post_title) . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($profile_details->post_type) . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($profile_details->post_date) . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($profile_details->post_status) . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ddd;">';
            if (!empty($menu_refs)) {
                foreach ($menu_refs as $ref) {
                    echo esc_html($ref->menu_name) . ' (' . $ref->count . ')<br>';
                }
            } else {
                echo 'No menu references';
            }
            echo '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ddd; max-width: 300px; overflow-x: auto;">';
            if (!empty($meta_data)) {
                echo '<div style="max-height: 200px; overflow-y: auto;">';
                foreach ($meta_data as $meta) {
                    echo '<strong>' . esc_html($meta->meta_key) . ':</strong> ';
                    echo esc_html(substr($meta->meta_value, 0, 100)) . 
                         (strlen($meta->meta_value) > 100 ? '...' : '') . '<br>';
                }
                echo '</div>';
            } else {
                echo 'No meta data';
            }
            echo '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ddd;">';
            echo '<input type="checkbox" name="delete_profiles[]" value="' . esc_attr($profile_id) . '">';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }
    
    echo '<div style="margin-top: 20px;">';
    echo '<input type="submit" name="delete_selected_profiles" class="button button-primary" value="Delete Selected Profiles">';
    echo '</div>';
    echo '</div>';
    echo '</form>';
}

function display_menu_structure($menu_slug) {
    global $wpdb;
    
    // Debug output
    echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
    echo '<strong>Debug Info:</strong><br>';
    echo 'Menu Slug: ' . esc_html($menu_slug) . '<br>';
    
    // Get menu term by slug
    $menu_term = $wpdb->get_row($wpdb->prepare("
        SELECT t.*, tt.term_taxonomy_id
        FROM {$wpdb->terms} t
        JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        WHERE tt.taxonomy = 'nav_menu'
        AND t.slug = %s
    ", $menu_slug));

    if (!$menu_term) {
        echo 'Menu not found in database<br>';
        echo '</div>';
        return '<div class="notice notice-error"><p>Menu not found: ' . esc_html($menu_slug) . '</p></div>';
    }

    echo 'Menu found: ' . esc_html($menu_term->name) . ' (ID: ' . $menu_term->term_id . ')<br>';

    $results = check_menu_relationships($menu_term->term_taxonomy_id);

    if (is_string($results)) {
        echo 'Error getting menu relationships: ' . esc_html($results) . '<br>';
        echo '</div>';
        return '<div class="notice notice-error"><p>' . esc_html($results) . '</p></div>';
    }

    echo 'Menu items found: ' . count($results['menu_items']) . '<br>';
    echo '</div>';

    $output = '<div class="card" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">';
    $output .= '<h2>Menu: ' . esc_html($results['menu']->name) . '</h2>';
    $output .= '<ul>';
    $output .= '<li>ID: ' . esc_html($menu_term->term_id) . '</li>';
    $output .= '<li>Taxonomy ID: ' . esc_html($menu_term->term_taxonomy_id) . '</li>';
    $output .= '<li>Slug: ' . esc_html($menu_term->slug) . '</li>';
    $output .= '</ul>';
    
    // Create summary table for winners
    $winners = array();
    foreach ($results['menu_items'] as $item) {
        $meta_data = $wpdb->get_results($wpdb->prepare("
            SELECT meta_key, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE post_id = %d
            ORDER BY meta_key
        ", $item->ID));
        
        $css_classes = '';
        $winner_class = '';
        foreach ($meta_data as $meta) {
            if ($meta->meta_key === '_menu_item_classes') {
                $classes = maybe_unserialize($meta->meta_value);
                if (is_array($classes)) {
                    $css_classes = implode(' ', $classes);
                    foreach ($classes as $class) {
                        if (strpos($class, 'winner') !== false) {
                            $winner_class = $class;
                            break;
                        }
                    }
                }
                break;
            }
        }
        
        if ($winner_class) {
            if (!isset($winners[$winner_class])) {
                $winners[$winner_class] = array();
            }
            $winners[$winner_class][] = array(
                'title' => $item->post_title,
                'object_id' => $item->object_id,
                'css_classes' => $css_classes
            );
        }
    }
    
    // Display summary table
    if (!empty($winners)) {
        $output .= '<h3>Winner Summary</h3>';
        $output .= '<table class="widefat" style="margin: 10px 0; border-collapse: collapse; width: 100%;">';
        $output .= '<thead><tr>';
        $output .= '<th style="padding: 8px; border: 1px solid #ddd;">Winner Type</th>';
        $output .= '<th style="padding: 8px; border: 1px solid #ddd;">Profile Title</th>';
        $output .= '<th style="padding: 8px; border: 1px solid #ddd;">Object ID</th>';
        $output .= '<th style="padding: 8px; border: 1px solid #ddd;">CSS Classes</th>';
        $output .= '</tr></thead><tbody>';
        
        foreach ($winners as $winner_type => $profiles) {
            foreach ($profiles as $index => $profile) {
                $output .= '<tr>';
                if ($index === 0) {
                    $output .= '<td style="padding: 8px; border: 1px solid #ddd;" rowspan="' . count($profiles) . '">' . esc_html($winner_type) . '</td>';
                }
                $output .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($profile['title']) . '</td>';
                $output .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($profile['object_id']) . '</td>';
                $output .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($profile['css_classes']) . '</td>';
                $output .= '</tr>';
            }
        }
        
        $output .= '</tbody></table>';
    } else {
        $output .= '<p>No winners found in this menu.</p>';
    }
    
    $output .= '<h3>Menu Items</h3>';
    $output .= '<table class="widefat" style="margin: 10px 0; border-collapse: collapse; width: 100%;">';
    $output .= '<thead><tr>';
    $output .= '<th style="padding: 8px; border: 1px solid #ddd;">Order</th>';
    $output .= '<th style="padding: 8px; border: 1px solid #ddd;">Title</th>';
    $output .= '<th style="padding: 8px; border: 1px solid #ddd;">Type</th>';
    $output .= '<th style="padding: 8px; border: 1px solid #ddd;">Object ID</th>';
    $output .= '<th style="padding: 8px; border: 1px solid #ddd;">Parent ID</th>';
    $output .= '<th style="padding: 8px; border: 1px solid #ddd;">CSS Classes</th>';
    $output .= '<th style="padding: 8px; border: 1px solid #ddd;">Meta Data</th>';
    $output .= '</tr></thead><tbody>';
    
    foreach ($results['menu_items'] as $item) {
        // Get menu item meta including CSS classes
        $meta_data = $wpdb->get_results($wpdb->prepare("
            SELECT meta_key, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE post_id = %d
            ORDER BY meta_key
        ", $item->ID));
        
        // Extract CSS classes
        $css_classes = '';
        $winner_class = '';
        foreach ($meta_data as $meta) {
            if ($meta->meta_key === '_menu_item_classes') {
                $classes = maybe_unserialize($meta->meta_value);
                if (is_array($classes)) {
                    $css_classes = implode(' ', $classes);
                    // Look for winner-related classes
                    foreach ($classes as $class) {
                        if (strpos($class, 'winner') !== false) {
                            $winner_class = $class;
                            break;
                        }
                    }
                }
                break;
            }
        }
        
        // Determine display type
        $display_type = $item->actual_post_type;
        if ($winner_class) {
            $display_type = $winner_class;
        }
        
        $output .= '<tr>';
        $output .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($item->menu_order) . '</td>';
        $output .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($item->post_title) . '</td>';
        $output .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($display_type) . '</td>';
        $output .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($item->object_id) . '</td>';
        $output .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($item->menu_item_parent) . '</td>';
        $output .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($css_classes) . '</td>';
        $output .= '<td style="padding: 8px; border: 1px solid #ddd; max-width: 300px; overflow-x: auto;">';
        if (!empty($meta_data)) {
            $output .= '<div style="max-height: 200px; overflow-y: auto;">';
            foreach ($meta_data as $meta) {
                $output .= '<strong>' . esc_html($meta->meta_key) . ':</strong> ';
                $output .= esc_html(substr($meta->meta_value, 0, 100)) . 
                     (strlen($meta->meta_value) > 100 ? '...' : '') . '<br>';
            }
            $output .= '</div>';
        } else {
            $output .= 'No meta data';
        }
        $output .= '</td>';
        $output .= '</tr>';
    }
    
    $output .= '</tbody></table>';
    $output .= '</div>';
    
    return $output;
}

// Function to add event type metadata
function add_event_type_metadata() {
    global $wpdb;
    // First, find the menu item ID for post ID 5010
    $menu_item_id = $wpdb->get_var($wpdb->prepare("
        SELECT ID 
        FROM {$wpdb->posts} 
        WHERE post_type = 'nav_menu_item' 
        AND ID IN (
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_menu_item_object_id' 
            AND meta_value = %d
        )
    ", 5010));

    echo '<div class="notice notice-info"><p>Debug: Menu item ID found: ' . ($menu_item_id ? $menu_item_id : 'none') . '</p></div>';

    if (!$menu_item_id) {
        echo '<div class="notice notice-error"><p>Could not find menu item for post ID 5010</p></div>';
        return;
    }

    // Check current metadata
    $current_meta = $wpdb->get_var($wpdb->prepare("
        SELECT meta_value 
        FROM {$wpdb->postmeta} 
        WHERE post_id = %d 
        AND meta_key = '_event_type'
    ", $menu_item_id));

    echo '<div class="notice notice-info"><p>Debug: Current _event_type value: ' . ($current_meta ? $current_meta : 'none') . '</p></div>';

    // Try update instead of insert
    $result = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} 
             SET meta_value = %s 
             WHERE post_id = %d 
             AND meta_key = '_event_type'",
            'red-carpet-interview', $menu_item_id
        )
    );

    if ($result === false) {
        echo '<div class="notice notice-error"><p>Error updating metadata: ' . esc_html($wpdb->last_error) . '</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>Successfully updated event type metadata for menu item ID ' . esc_html($menu_item_id) . '</p></div>';
    }
}

// Function to count nesting level
function get_nesting_level($menu_items, $item_id, $level = 0) {
    $item = array_filter($menu_items, function($i) use ($item_id) {
        return $i->ID == $item_id;
    });
    $item = reset($item);
    
    if (!$item || !$item->menu_item_parent) {
        return $level;
    }
    
    return get_nesting_level($menu_items, $item->menu_item_parent, $level + 1);
}

/**
 * Test if a menu slug matches a specific pattern
 * 
 * @param string $menu_slug The menu slug to test
 * @param string $base_pattern The base pattern to match (e.g., 'polys')
 * @return bool True if the pattern matches
 */
function test_menu_pattern($menu_slug, $base_pattern) {
    // Create a regex pattern that matches the base followed by either a number or *
    $pattern = '/^' . preg_quote($base_pattern, '/') . '(?:\d+|\*)$/';
    return preg_match($pattern, $menu_slug) > 0;
} 
 
// ---------------------------------------------
// Menu helpers for audit template (safe, reusable)
// ---------------------------------------------
 
/**
 * Get all nav menus ordered by name.
 *
 * @return array List of term rows (objects) with taxonomy nav_menu
 */
function get_all_nav_menus() {
    global $wpdb;
    return $wpdb->get_results("
        SELECT t.*, tt.term_taxonomy_id
        FROM {$wpdb->terms} t
        JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        WHERE tt.taxonomy = 'nav_menu'
        ORDER BY t.name ASC
    ");
}
 
/**
 * Resolve one or more menu slugs from an input that may include a wildcard (*).
 *
 * Examples:
 * - 'polys3' => ['polys3']
 * - 'polys*' => all matching DB slugs under 'nav_menu'
 *
 * @param string $menu_slug
 * @return array List of slugs (strings). Empty array if none.
 */
function resolve_menu_slugs($menu_slug) {
    global $wpdb;

    if (strpos($menu_slug, '*') !== false) {
        $pattern = str_replace('*', '', $menu_slug) . '%';
        $slugs = $wpdb->get_col($wpdb->prepare("
            SELECT t.slug
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'nav_menu'
            AND t.slug LIKE %s
            ORDER BY t.slug ASC
        ", $pattern));
        return is_array($slugs) ? $slugs : array();
    }

    return array($menu_slug);
}
 
/**
 * Wrapper: given a menu slug, fetch its term and menu items using check_menu_relationships().
 *
 * @param string $slug
 * @return array|string Either ['menu'=>..., 'menu_items'=>...] or error string
 */
function get_menu_items_for_slug($slug) {
    global $wpdb;

    $menu_term = $wpdb->get_row($wpdb->prepare("
        SELECT t.*, tt.term_taxonomy_id
        FROM {$wpdb->terms} t
        JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        WHERE tt.taxonomy = 'nav_menu'
        AND t.slug = %s
    ", $slug));

    if (!$menu_term) {
        return "Menu not found: " . $slug;
    }

    $results = check_menu_relationships($menu_term->term_taxonomy_id);
    if (is_string($results)) {
        return $results; // propagate error string
    }

    return $results;
}
 
/**
 * Render the Available Menus link list as HTML.
 * Caller is expected to echo the returned string.
 *
 * @param array $menus Rows returned by get_all_nav_menus()
 * @return string HTML markup
 */
function render_available_menus($menus) {
    $out = '';
    $out .= '<h1>Available Menus</h1>';
    $out .= '<p>';
    foreach ($menus as $menu) {
        $out .= '<a href="?event_menu=' . esc_attr($menu->slug) . '" class="menu-link">' .
                esc_html($menu->name) . '</a>';
    }
    $out .= '</p>';
    return $out;
}

/**
 * Central dispatcher for GET-triggered audit actions.
 * Keeps page-audit.php clean and ensures capability checks are centralized.
 *
 * @param array $params Typically $_GET
 * @return void
 */
function audit_dispatch_actions($params) {
    if (!is_array($params)) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($params['update_videos']) && function_exists('update_award_videos')) {
        update_award_videos();
    }
    if (isset($params['find_titles']) && function_exists('find_award_titles')) {
        find_award_titles();
    }
    if (isset($params['update_narratives']) && function_exists('update_award_narratives')) {
        update_award_narratives();
    }
    if (isset($params['preview_narratives']) && function_exists('preview_award_narratives')) {
        preview_award_narratives();
    }
}
 
// Moved from page-audit.php: Update award videos based on embed_video_url
function update_award_videos() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    // First get all posts that have embed_video_url but no video_url
    $posts = $wpdb->get_results(
        "SELECT p.ID, p.post_title, pm.meta_value as embed_url 
        FROM {$wpdb->posts} p 
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
        LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'video_url'
        WHERE p.post_type = 'event' 
        AND p.post_status = 'publish' 
        AND pm.meta_key = 'embed_video_url'
        AND pm2.meta_id IS NULL"
    );

    echo '<div class="notice notice-info">';
    echo '<h3>Debug Information:</h3>';
    echo '<p>Found ' . count($posts) . ' posts with embed URLs but no video URLs</p>';

    $created = 0;
    $skipped = 0;
    $errors = array();

    foreach ($posts as $post) {
        echo '<div style="margin: 10px 0; padding: 10px; border: 1px solid #ccc;">';
        echo '<p>Processing post: ' . esc_html($post->post_title) . ' (ID: ' . esc_html($post->ID) . ')</p>';
        echo '<p>Embed URL: ' . esc_html($post->embed_url) . '</p>';

        // Extract video ID from embed URL
        if (preg_match('/embed\/([^"\']+)/', $post->embed_url, $matches)) {
            $video_id = $matches[1];
            $video_url = 'https://www.youtube.com/watch?v=' . $video_id;
            
            echo '<p>Extracted video ID: ' . esc_html($video_id) . '</p>';
            echo '<p>Generated video URL: ' . esc_html($video_url) . '</p>';
            
            // Insert the video_url
            $result = $wpdb->insert(
                $wpdb->postmeta,
                array(
                    'post_id' => $post->ID,
                    'meta_key' => 'video_url',
                    'meta_value' => $video_url
                ),
                array('%d', '%s', '%s')
            );
            
            if ($result) {
                echo '<p style="color: green;">Successfully inserted video URL</p>';
                $created++;
            } else {
                echo '<p style="color: red;">Failed to insert video URL. Error: ' . esc_html($wpdb->last_error) . '</p>';
                $errors[] = $post->post_title;
            }
        } else {
            echo '<p style="color: red;">Could not extract video ID from embed URL</p>';
            $errors[] = $post->post_title;
        }
        echo '</div>';
    }

    // Display results
    echo '<h3>Summary:</h3>';
    echo '<p>Created video URLs for ' . $created . ' awards.</p>';
    echo '<p>Skipped ' . $skipped . ' awards that already had video URLs.</p>';
    
    if (!empty($errors)) {
        echo '<h3>Errors occurred with these posts:</h3>';
        echo '<ul>';
        foreach ($errors as $title) {
            echo '<li>' . esc_html($title) . '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}

// Moved from page-audit.php: Find award titles containing known terms
function find_award_titles() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    $search_terms = array(
        'Single-User',
        'Multi-User',
        'Entertainment',
        'WebXR Developer',
        'Ombudsman',
        'Education',
        'Site of the Year'
    );

    echo '<div class="notice notice-info">';
    echo '<h3>Searching for award titles containing:</h3>';
    echo '<ul>';
    foreach ($search_terms as $term) {
        echo '<li>' . esc_html($term) . '</li>';
    }
    echo '</ul>';

    echo '<h3>Found posts:</h3>';
    echo '<ul>';
    
    foreach ($search_terms as $term) {
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title 
            FROM {$wpdb->posts} 
            WHERE post_type = 'event' 
            AND post_status = 'publish' 
            AND post_title LIKE %s",
            '%' . $wpdb->esc_like($term) . '%'
        ));

        if (!empty($posts)) {
            foreach ($posts as $post) {
                echo '<li>' . esc_html($post->post_title) . ' (ID: ' . esc_html($post->ID) . ')</li>';
            }
        } else {
            echo '<li>No posts found containing: ' . esc_html($term) . '</li>';
        }
    }
    echo '</ul>';
    echo '</div>';
}

// Moved from page-audit.php: Build narrative string for an award
function generate_award_narrative($award) {
    if (!is_array($award)) {
        return '';
    }

    // Extract year from award title (assuming format includes year)
    $year = '';
    if (preg_match('/\b(20\d{2})\b/', $award['title'], $matches)) {
        $year = $matches[1];
    }

    // Get award name without year
    $award_name = preg_replace('/\b20\d{2}\b/', '', $award['title']);
    $award_name = trim($award_name);

    // Format presenters
    $presenters_text = '';
    if (!empty($award['presenters'])) {
        if (count($award['presenters']) === 1) {
            $presenters_text = $award['presenters'][0];
        } else {
            $last_presenter = array_pop($award['presenters']);
            $presenters_text = implode(', ', $award['presenters']) . ' and ' . $last_presenter;
        }
    }

    // Format winners (mirror summary winners syntax)
    $winners_text = '';
    if (!empty($award['winners'])) {
        $base_title = isset($award['winners'][0]['title']) ? $award['winners'][0]['title'] : '';
        $group_parts = array();
        foreach ($award['winners'] as $idx => $winner) {
            $company = isset($winner['company']) ? trim($winner['company']) : '';
            $people = isset($winner['people']) && is_array($winner['people']) ? $winner['people'] : array();
            $part = '';
            if ($company !== '') {
                $part .= ($idx === 0 ? 'by ' : '') . $company;
                if (!empty($people)) {
                    $part .= ': ' . implode(', ', $people);
                }
            } else {
                if (!empty($people)) {
                    $part .= implode(', ', $people);
                }
            }
            if ($part !== '') { $group_parts[] = $part; }
        }
        if ($base_title !== '') {
            $winners_text = $base_title;
            if (!empty($group_parts)) {
                $winners_text .= ' ' . implode(' ; ', $group_parts);
            }
        } else {
            $winners_text = implode(' ; ', $group_parts);
        }
    }

    // Get award date from post meta if available
    $award_date = '';
    if (!empty($award['object_id'])) {
        $award_date = get_post_meta($award['object_id'], 'award_date', true);
        if ($award_date) {
            $award_date = date('F j, Y', strtotime($award_date));
        }
    }

    // Format nominees (mirror winners syntax): base title with grouped company/people; exclude titles matching winners
    $nominees_text = '';
    if (!empty($award['nominees'])) {
        $winner_title_set = array();
        if (!empty($award['winners']) && is_array($award['winners'])) {
            foreach ($award['winners'] as $w) {
                $t = isset($w['title']) ? strtolower(trim($w['title'])) : '';
                if ($t !== '') { $winner_title_set[$t] = true; }
            }
        }

        // Group nominee entries by title
        $groups_by_title = array();
        $order_titles = array();
        foreach ($award['nominees'] as $n) {
            $title = isset($n['title']) ? trim($n['title']) : '';
            if ($title === '') { continue; }
            $norm = strtolower($title);
            if (isset($winner_title_set[$norm])) { continue; }
            if (!isset($groups_by_title[$title])) { $groups_by_title[$title] = array(); $order_titles[] = $title; }
            $groups_by_title[$title][] = array(
                'company' => isset($n['company']) ? trim($n['company']) : '',
                'people' => (isset($n['people']) && is_array($n['people'])) ? $n['people'] : array()
            );
        }

        $rendered = array();
        foreach ($order_titles as $title) {
            $parts = array();
            foreach ($groups_by_title[$title] as $idx => $g) {
                $company = $g['company'];
                $people = $g['people'];
                $part = '';
                if ($company !== '') {
                    $part .= ($idx === 0 ? 'by ' : '') . $company;
                    if (!empty($people)) { $part .= ': ' . implode(', ', $people); }
                } elseif (!empty($people)) {
                    $part .= implode(', ', $people);
                }
                if ($part !== '') { $parts[] = $part; }
            }
            $s = $title;
            if (!empty($parts)) { $s .= ' ' . implode(' ; ', $parts); }
            $rendered[] = $s;
        }

        if (!empty($rendered)) { $nominees_text = implode(' ; ', $rendered); }
    }

    // Build the narrative
    $narrative = '';
    if ($year) {
        $narrative .= "The {$year} ";
    }
    $narrative .= $award_name;

    if ($presenters_text) {
        $narrative .= " was presented by {$presenters_text}";
    }

    if ($winners_text) {
        $narrative .= " to {$winners_text}";
    }

    if ($nominees_text) {
        $narrative .= ". Nominees were: {$nominees_text}";
    }

    return $narrative;
}

// Function to recursively extract nominee info from level 2 down
function extract_nominee_info($nominee_item, $menu_items) {
    $nominee_info = array(
        'title' => $nominee_item->post_title ?: '',
        'company' => '',
        'people' => array()
    );

    // Check for company in the title
    if (preg_match('/^(.*?)\s+by\s+(.*?)$/', $nominee_info['title'], $matches)) {
        $nominee_info['title'] = trim($matches[1]);
        $nominee_info['company'] = trim($matches[2]);
    }

    // Get level 3/4 items (company or person) recursively
    foreach ($menu_items as $child_item) {
        if ($child_item->menu_item_parent == $nominee_item->ID) {
            $child_post = get_post($child_item->object_id);
            if ($child_post) {
                // If this is a company (level 3), get its people (level 4)
                if ($child_item->actual_post_type === 'resource') {
                    $nominee_info['company'] = $child_post->post_title;
                    // Get level 4 people
                    foreach ($menu_items as $grandchild_item) {
                        if ($grandchild_item->menu_item_parent == $child_item->ID) {
                            $grandchild_post = get_post($grandchild_item->object_id);
                            if ($grandchild_post) {
                                $nominee_info['people'][] = $grandchild_post->post_title;
                            }
                        }
                    }
                } else {
                    // If level 3 is a person, add them directly
                    $nominee_info['people'][] = $child_post->post_title;
                }
            }
        }
    }

    return $nominee_info;
}

// Moved from page-audit.php: Update award narratives for polys1 from menu items
function update_award_narratives() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    
    // Get all awards from polys1 menu
    $menu_term = $wpdb->get_row($wpdb->prepare("
        SELECT t.*, tt.term_taxonomy_id
        FROM {$wpdb->terms} t
        JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        WHERE tt.taxonomy = 'nav_menu'
        AND t.slug = %s
    ", 'polys1'));

    if (!$menu_term) {
        echo '<div class="notice notice-error"><p>Polys1 menu not found</p></div>';
        return;
    }

    $results = check_menu_relationships($menu_term->term_taxonomy_id);
    if (is_string($results)) {
        echo '<div class="notice notice-error"><p>' . esc_html($results) . '</p></div>';
        return;
    }

    $awards = array();
    $current_award = null;
    $menu_items = array();
    $parent_map = array();

    // First, build the menu structure
    foreach ($results['menu_items'] as $item) {
        $menu_items[$item->ID] = $item;
        if ($item->menu_item_parent) {
            $parent_map[$item->ID] = $item->menu_item_parent;
        }
    }

    foreach ($results['menu_items'] as $item) {
        $post_title = '';
        $type_info = '';
        $classes = get_post_meta($item->ID, '_menu_item_classes', true);
        $is_winner = false;
        $is_honoree = false;
        
        if (is_array($classes)) {
            if (in_array('winner', $classes)) {
                $is_winner = true;
            }
            if (in_array('honoree', $classes)) {
                $is_honoree = true;
            }
        }

        if ($item->object_id) {
            $post = get_post($item->object_id);
            if ($post) {
                $post_title = $post->post_title;
            }
        }

        // Get nesting level using the existing function
        $level = get_nesting_level($menu_items, $item->ID);
        
        if ($item->actual_post_type === 'event' && $level === 2) {
            if ($current_award !== null) {
                $awards[] = $current_award;
            }
            $current_award = array(
                'title' => $item->post_title ?: $post_title,
                'type' => $type_info,
                'presenters' => array(),
                'winners' => array(),
                'nominees' => array(),
                'award_type' => '',
                'object_id' => $item->object_id,
                'presenter_ids' => array(),
                'winner_ids' => array(),
                'current_winner' => null,
                'items' => array()
            );
        } elseif ($level === 3 && $current_award !== null) {
            // Handle level 3 items (presenters, winners, and nominees)
            $type_info = get_post_meta($item->ID, '_guest_type', true);
            
            // Check if this is a presenter
            if ($type_info === 'award-presenter') {
                $current_award['presenters'][] = $item->post_title ?: $post_title;
                $current_award['presenter_ids'][] = $item->object_id;
                // Track item
                $current_award['items'][] = array(
                    'level' => $level,
                    'title' => $item->post_title ?: $post_title,
                    'type' => $item->actual_post_type,
                    'is_winner' => false,
                    'is_honoree' => false,
                    'is_presenter' => true,
                    'company' => '',
                    'people' => array()
                );
            }
            // Check if this is a winner/honoree
            elseif ($is_winner || $is_honoree) {
                $winner_info = array(
                    'title' => $item->post_title ?: $post_title,
                    'company' => '',
                    'people' => array()
                );

                // Check for company in the title
                if (preg_match('/^(.*?)\s+by\s+(.*?)$/', $winner_info['title'], $matches)) {
                    $winner_info['title'] = trim($matches[1]);
                    $winner_info['company'] = trim($matches[2]);
                }

                // Get level 4 items (company or person)
                foreach ($menu_items as $child_item) {
                    if ($child_item->menu_item_parent == $item->ID) {
                        $child_post = get_post($child_item->object_id);
                        if ($child_post) {
                            // If this is a company (level 4), get its people (level 5)
                            if ($child_item->actual_post_type === 'resource') {
                                $winner_info['company'] = $child_post->post_title;
                                // Get level 5 people
                                foreach ($menu_items as $grandchild_item) {
                                    if ($grandchild_item->menu_item_parent == $child_item->ID) {
                                        $grandchild_post = get_post($grandchild_item->object_id);
                                        if ($grandchild_post) {
                                            $winner_info['people'][] = $grandchild_post->post_title;
                                        }
                                    }
                                }
                            } else {
                                // If level 4 is a person, add them directly
                                $winner_info['people'][] = $child_post->post_title;
                            }
                        }
                    }
                }

                $current_award['winners'][] = $winner_info;
                $current_award['winner_ids'][] = $item->object_id;
                $current_award['current_winner'] = $item->ID;
                if ($is_winner) {
                    $current_award['award_type'] = 'WINNER';
                } else {
                    $current_award['award_type'] = 'HONOREE';
                }
                // Track item
                $current_award['items'][] = array(
                    'level' => $level,
                    'title' => $winner_info['title'],
                    'type' => $item->actual_post_type,
                    'is_winner' => true,
                    'is_honoree' => $is_honoree,
                    'is_presenter' => false,
                    'company' => $winner_info['company'],
                    'people' => $winner_info['people']
                );
            }
            // If not a presenter or winner, it's a nominee
            else {
                $nominee_info = extract_nominee_info($item, $menu_items);
                $current_award['nominees'][] = $nominee_info;
            }
        }
    }

    // After the loop, make sure to add the last award if it exists
    if ($current_award !== null) {
        $awards[] = $current_award;
    }

    $updated = 0;
    $errors = array();

    foreach ($awards as $award) {
        if (empty($award['object_id'])) {
            continue;
        }

        // Extract year from award title
        $year = '';
        if (preg_match('/\b(20\d{2})\b/', $award['title'], $matches)) {
            $year = $matches[1];
        }

        // Get award name without year
        $award_name = preg_replace('/\b20\d{2}\b/', '', $award['title']);
        $award_name = trim($award_name);

        // Format presenters
        $presenters_text = '';
        if (!empty($award['presenters'])) {
            if (count($award['presenters']) === 1) {
                $presenters_text = $award['presenters'][0];
            } else {
                $last_presenter = array_pop($award['presenters']);
                $presenters_text = implode(', ', $award['presenters']) . ' and ' . $last_presenter;
            }
        }

        // Format winners (mirror summary winners syntax)
        $winners_text = '';
        if (!empty($award['winners'])) {
            $base_title = isset($award['winners'][0]['title']) ? $award['winners'][0]['title'] : '';
            $group_parts = array();
            foreach ($award['winners'] as $idx => $winner) {
                $company = isset($winner['company']) ? trim($winner['company']) : '';
                $people = isset($winner['people']) && is_array($winner['people']) ? $winner['people'] : array();
                $part = '';
                if ($company !== '') {
                    $part .= ($idx === 0 ? 'by ' : '') . $company;
                    if (!empty($people)) {
                        $part .= ': ' . implode(', ', $people);
                    }
                } else {
                    if (!empty($people)) {
                        $part .= implode(', ', $people);
                    }
                }
                if ($part !== '') { $group_parts[] = $part; }
            }
            if ($base_title !== '') {
                $winners_text = $base_title;
                if (!empty($group_parts)) {
                    $winners_text .= ' ' . implode(' ; ', $group_parts);
                }
            } else {
                $winners_text = implode(' ; ', $group_parts);
            }
        }

        // Format nominees (mirror winners syntax): exclude titles matching winners
        $nominees_text = '';
        if (!empty($award['nominees'])) {
            $winner_title_set = array();
            if (!empty($award['winners'])) {
                foreach ($award['winners'] as $w) {
                    $t = isset($w['title']) ? strtolower(trim($w['title'])) : '';
                    if ($t !== '') { $winner_title_set[$t] = true; }
                }
            }

            $rendered = array();
            foreach ($award['nominees'] as $nominee) {
                $title = isset($nominee['title']) ? trim($nominee['title']) : '';
                if ($title === '') { continue; }
                $norm = strtolower($title);
                if (isset($winner_title_set[$norm])) { continue; } // Exclude winners from nominees

                $company = isset($nominee['company']) ? trim($nominee['company']) : '';
                $people = isset($nominee['people']) && is_array($nominee['people']) ? $nominee['people'] : array();
                
                $s = $title;
                $part = '';
                if ($company !== '') {
                    $part .= 'by ' . $company;
                    if (!empty($people)) { $part .= ': ' . implode(', ', $people); }
                } elseif (!empty($people)) {
                    $part .= implode(', ', $people);
                }
                if ($part !== '') { $s .= ' ' . $part; }
                $rendered[] = $s;
            }
            if (!empty($rendered)) { $nominees_text = implode(' ; ', $rendered); }
        }

        // Build the narrative
        $narrative = '';
        if ($year) {
            $narrative .= "The {$year} ";
        }
        $narrative .= $award_name;

        if ($presenters_text) {
            $narrative .= " was presented by {$presenters_text}";
        }

        if ($winners_text) {
            $narrative .= " to {$winners_text}";
        }

        if ($nominees_text) {
            $narrative .= ". Nominees were: {$nominees_text}";
        }

        // Update the post content
        $result = $wpdb->update(
            $wpdb->posts,
            array('post_content' => $narrative),
            array('ID' => $award['object_id']),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            $updated++;
        } else {
            $errors[] = $award['title'];
        }
    }

    // Display results
    echo '<div class="notice notice-info">';
    echo '<p>Updated narratives for ' . $updated . ' awards.</p>';
    if (!empty($errors)) {
        echo '<p>Failed to update these awards:</p>';
        echo '<ul>';
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}

// Moved from page-audit.php: Preview narratives for a given event_menu without saving
function preview_award_narratives() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_GET['event_menu'])) {
        echo '<div class="notice notice-error"><p>Please specify an event_menu parameter in the URL</p></div>';
        return;
    }

    global $wpdb;
    
    // Get all awards from the specified menu
    $menu_term = $wpdb->get_row($wpdb->prepare("
        SELECT t.*, tt.term_taxonomy_id
        FROM {$wpdb->terms} t
        JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        WHERE tt.taxonomy = 'nav_menu'
        AND t.slug = %s
    ", $_GET['event_menu']));

    if (!$menu_term) {
        echo '<div class="notice notice-error"><p>Menu not found: ' . esc_html($_GET['event_menu']) . '</p></div>';
        return;
    }

    $results = check_menu_relationships($menu_term->term_taxonomy_id);
    if (is_string($results)) {
        echo '<div class="notice notice-error"><p>' . esc_html($results) . '</p></div>';
        return;
    }

    $awards = array();
    $current_award = null;
    $menu_items = array();
    $parent_map = array();

    // First, build the menu structure
    foreach ($results['menu_items'] as $item) {
        $menu_items[$item->ID] = $item;
        if ($item->menu_item_parent) {
            $parent_map[$item->ID] = $item->menu_item_parent;
        }
    }

    // Sort menu items by menu_order to ensure correct processing order
    usort($results['menu_items'], function($a, $b) {
        return $a->menu_order - $b->menu_order;
    });

    foreach ($results['menu_items'] as $item) {
        $post_title = '';
        $type_info = '';
        $classes = get_post_meta($item->ID, '_menu_item_classes', true);
        $is_winner = false;
        $is_honoree = false;
        
        if (is_array($classes)) {
            if (in_array('winner', $classes)) {
                $is_winner = true;
            }
            if (in_array('honoree', $classes)) {
                $is_honoree = true;
            }
        }

        if ($item->object_id) {
            $post = get_post($item->object_id);
            if ($post) {
                $post_title = $post->post_title;
            }
        }

        // Get nesting level using the existing function
        $level = get_nesting_level($menu_items, $item->ID);
        
        if ($item->actual_post_type === 'event' && $level === 2) {
            if ($current_award !== null) {
                $awards[] = $current_award;
            }
            $current_award = array(
                'title' => $item->post_title ?: $post_title,
                'type' => $type_info,
                'presenters' => array(),
                'winners' => array(),
                'nominees' => array(),
                'object_id' => $item->object_id
            );
        } elseif ($current_award !== null && $level === 3) {
            // Handle level 3 items (presenters, winners, and nominees) - these are the actual people/entries
            $type_info = get_post_meta($item->ID, '_guest_type', true);
            
            // Check if this is a presenter
            if ($type_info === 'award-presenter') {
                $current_award['presenters'][] = $item->post_title ?: $post_title;
            }
            // Check if this is a winner/honoree
            elseif ($is_winner || $is_honoree) {
                $winner_info = extract_nominee_info($item, $menu_items); // Same extraction logic
                $current_award['winners'][] = $winner_info;
            }
            // If not a presenter or winner, it's a nominee
            else {
                $nominee_info = extract_nominee_info($item, $menu_items);
                $current_award['nominees'][] = $nominee_info;
            }
        }
    }

    // Add the last award if it exists
    if ($current_award !== null) {
        $awards[] = $current_award;
    }

    echo '<div class="card" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">';
    echo '<h2>Preview Award Narratives</h2>';
    echo '<div style="margin-bottom: 20px;">';
    echo '<p>This is a preview of the narratives that will be generated. To apply these changes, use the update_narratives parameter.</p>';
    echo '</div>';

    foreach ($awards as $award) {
        if (empty($award['object_id'])) {
            continue;
        }

        // Extract year from award title
        $year = '';
        if (preg_match('/\b(20\d{2})\b/', $award['title'], $matches)) {
            $year = $matches[1];
        }

        // Get award name without year
        $award_name = preg_replace('/\b20\d{2}\b/', '', $award['title']);
        $award_name = trim($award_name);

        // Extract presenters, winners, and nominees from the award data
        $presenters = isset($award['presenters']) ? $award['presenters'] : array();
        $winners = isset($award['winners']) ? $award['winners'] : array();
        $nominees = isset($award['nominees']) ? $award['nominees'] : array();
        
        // DEBUG: Show what we collected
        echo '<div style="background: #ffffcc; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
        echo '<strong>DEBUG - Award: ' . esc_html($award['title']) . '</strong><br>';
        echo 'Presenters (' . count($presenters) . '): ' . implode(', ', $presenters) . '<br>';
        echo 'Winners (' . count($winners) . '): ';
        foreach ($winners as $w) {
            echo '[' . (isset($w['title']) ? $w['title'] : 'NO_TITLE') . '] ';
        }
        echo '<br>';
        echo 'Nominees (' . count($nominees) . '): ';
        foreach ($nominees as $n) {
            echo '[' . (isset($n['title']) ? $n['title'] : 'NO_TITLE') . '] ';
        }
        echo '<br></div>';

        // Build the narrative
        $narrative = '';
        if ($year) {
            $narrative .= "The {$year} ";
        }
        $narrative .= $award_name;

        // Format winners (mirror summary winners syntax)
        $winners_text = '';
        if (!empty($winners)) {
            $base_title = isset($winners[0]['title']) ? $winners[0]['title'] : '';
            $group_parts = array();
            foreach ($winners as $idx => $winner) {
                $company = isset($winner['company']) ? trim($winner['company']) : '';
                $people = isset($winner['people']) && is_array($winner['people']) ? $winner['people'] : array();
                $part = '';
                if ($company !== '') {
                    $part .= ($idx === 0 ? 'by ' : '') . $company;
                    if (!empty($people)) {
                        $part .= ': ' . implode(', ', $people);
                    }
                } else {
                    if (!empty($people)) {
                        $part .= implode(', ', $people);
                    }
                }
                if ($part !== '') { $group_parts[] = $part; }
            }
            if ($base_title !== '') {
                $winners_text = $base_title;
                if (!empty($group_parts)) {
                    $winners_text .= ' ' . implode(' ; ', $group_parts);
                }
            } else {
                $winners_text = implode(' ; ', $group_parts);
            }
        }

        // Add presenters
        if (!empty($presenters)) {
            if (count($presenters) === 1) {
                $narrative .= " was presented by " . $presenters[0];
            } else {
                $last_presenter = array_pop($presenters);
                $narrative .= " was presented by " . implode(', ', $presenters) . ' and ' . $last_presenter;
            }
        }

        // Add winners
        if (!empty($winners_text)) {
            $narrative .= " to " . $winners_text;
        }

        // Format nominees (mirror winners syntax): exclude titles matching winners
        $nominees_text = '';
        if (!empty($nominees)) {
            $winner_title_set = array();
            if (!empty($winners)) {
                foreach ($winners as $w) {
                    $t = isset($w['title']) ? strtolower(trim($w['title'])) : '';
                    if ($t !== '') { $winner_title_set[$t] = true; }
                }
            }

            $rendered = array();
            foreach ($nominees as $nominee) {
                $title = isset($nominee['title']) ? trim($nominee['title']) : '';
                if ($title === '') { continue; }
                $norm = strtolower($title);
                if (isset($winner_title_set[$norm])) { continue; } // Exclude winners from nominees

                $company = isset($nominee['company']) ? trim($nominee['company']) : '';
                $people = isset($nominee['people']) && is_array($nominee['people']) ? $nominee['people'] : array();
                
                $s = $title;
                $part = '';
                if ($company !== '') {
                    $part .= 'by ' . $company;
                    if (!empty($people)) { $part .= ': ' . implode(', ', $people); }
                } elseif (!empty($people)) {
                    $part .= implode(', ', $people);
                }
                if ($part !== '') { $s .= ' ' . $part; }
                $rendered[] = $s;
            }
            if (!empty($rendered)) { $nominees_text = implode(' ; ', $rendered); }
        }
        // Add nominees
        if (!empty($nominees_text)) {
            $narrative .= ". Nominees were: " . $nominees_text;
        }

        echo '<div style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">';
        echo '<h3 style="margin-top: 0;">' . esc_html($award['title']) . '</h3>';
        echo '<p style="margin: 0;">' . esc_html($narrative) . '</p>';
        echo '</div>';
    }

    echo '<div style="margin-top: 20px;">';
    echo '<a href="?event_menu=' . esc_attr($_GET['event_menu']) . '&update_narratives" class="button button-primary">Apply These Changes</a>';
    echo '</div>';
    echo '</div>';
}