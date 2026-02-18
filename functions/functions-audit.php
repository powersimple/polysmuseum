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
        $base = strtolower(str_replace('*', '', (string)$menu_slug));
        $pattern = $base . '%';
        $slugs = $wpdb->get_col($wpdb->prepare("
            SELECT t.slug
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'nav_menu'
            AND LOWER(t.slug) LIKE %s
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
            // Add a colon after the level-2 winner name
            $winners_text = $base_title . ':';
            if (!empty($group_parts)) {
                $winners_text .= ' ' . implode(' ; ', $group_parts);
            }
        } else {
            $winners_text = implode(' ; ', $group_parts);
        }
    }

    // Build the narrative
    $narrative = '';
    if ($year) {
        $narrative .= "The {$year} ";
    }
    $narrative .= $award_name;
    // If the award name ends with "of the Year", append the word "award" before "was presented"
    $needs_award_word = preg_match('/\bof the Year\b$/i', $award_name) === 1;
    if ($needs_award_word) {
        $narrative .= ' award';
    }

    if ($presenters_text) {
        $narrative .= " was presented by {$presenters_text}";
    }

    if ($winners_text) {
        $narrative .= " to {$winners_text}";
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

// =============================================================================
// MEGAMENU CONTENT AUDIT MODE
// =============================================================================

// =============================================================================
// CACHING INFRASTRUCTURE
// =============================================================================

/**
 * Build a consistent cache key for audit transients
 *
 * @param string $namespace Cache namespace (e.g., 'menu_tree', 'render')
 * @param array $parts Key parts to include in the hash
 * @return string Transient key (max 172 chars for WP transients)
 */
function audit_cache_key($namespace, $parts = []) {
    $site_id = get_current_blog_id();
    $parts_string = implode('_', array_map('sanitize_key', $parts));
    $hash = substr(md5($parts_string), 0, 12);
    return 'audit_' . $namespace . '_' . $site_id . '_' . $hash;
}

/**
 * Get the lightweight megamenu subtree structure (cached)
 * 
 * This returns ONLY the menu structure without post_content to keep cache small.
 * The structure includes: menu_item_id, object_id, object_type, url, title, 
 * parent relationships, depth, and linked post metadata (excluding content).
 *
 * @param string $menu_slug The menu slug
 * @param string $root_slug Optional root item slug to filter subtree
 * @param bool $force_refresh Force cache refresh
 * @return array ['data' => array|false, 'cache_hit' => bool, 'build_time' => float]
 */
function audit_get_megamenu_subtree_cached($menu_slug = 'megamenu', $root_slug = '', $force_refresh = false) {
    $cache_key = audit_cache_key('menu_tree', [$menu_slug, $root_slug]);
    $ttl = 12 * HOUR_IN_SECONDS; // 12 hours default
    
    // Check cache first (unless force refresh)
    if (!$force_refresh) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return [
                'data' => $cached,
                'cache_hit' => true,
                'build_time' => 0
            ];
        }
    }
    
    // Cache miss - build the subtree
    $start_time = microtime(true);
    $subtree = audit_get_megamenu_subtree($menu_slug, $root_slug);
    $build_time = microtime(true) - $start_time;
    
    // Store in cache (only if we got valid data)
    if ($subtree !== false) {
        set_transient($cache_key, $subtree, $ttl);
    }
    
    return [
        'data' => $subtree,
        'cache_hit' => false,
        'build_time' => $build_time
    ];
}

/**
 * Get lightweight megamenu subtree (no post_content, for caching)
 *
 * @param string $menu_slug The menu slug
 * @param string $root_slug Optional root item slug
 * @return array|false Subtree data or false if not found
 */
function audit_get_megamenu_subtree($menu_slug = 'megamenu', $root_slug = '') {
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
    
    // Get menu items WITHOUT post_content (lightweight for caching)
    $menu_items = $wpdb->get_results($wpdb->prepare("
        SELECT 
            p.ID,
            p.post_title,
            p.menu_order,
            pm_object_id.meta_value AS object_id,
            pm_object.meta_value AS object_type,
            pm_parent.meta_value AS menu_item_parent,
            linked_post.ID AS linked_id,
            linked_post.post_title AS linked_title,
            linked_post.post_name AS linked_slug,
            linked_post.post_type AS linked_post_type
        FROM {$wpdb->posts} p
        JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        LEFT JOIN {$wpdb->postmeta} pm_object_id ON p.ID = pm_object_id.post_id AND pm_object_id.meta_key = '_menu_item_object_id'
        LEFT JOIN {$wpdb->postmeta} pm_object ON p.ID = pm_object.post_id AND pm_object.meta_key = '_menu_item_object'
        LEFT JOIN {$wpdb->postmeta} pm_parent ON p.ID = pm_parent.post_id AND pm_parent.meta_key = '_menu_item_menu_item_parent'
        LEFT JOIN {$wpdb->posts} linked_post ON pm_object_id.meta_value = linked_post.ID
        WHERE tr.term_taxonomy_id = %d
        AND p.post_type = 'nav_menu_item'
        AND p.post_status = 'publish'
        ORDER BY p.menu_order ASC
    ", $menu_term->term_taxonomy_id));
    
    if (empty($menu_items)) {
        return false;
    }
    
    // Build hierarchical structure (lightweight - no content)
    $items_by_id = [];
    foreach ($menu_items as $item) {
        $items_by_id[$item->ID] = [
            'menu_item_id' => $item->ID,
            'menu_title' => $item->post_title,
            'menu_order' => $item->menu_order,
            'object_id' => $item->object_id,
            'object_type' => $item->object_type,
            'parent_id' => (int)$item->menu_item_parent,
            'linked_id' => $item->linked_id,
            'linked_title' => $item->linked_title,
            'linked_slug' => $item->linked_slug,
            'linked_post_type' => $item->linked_post_type,
            'children' => [],
            'level' => 0
        ];
    }
    
    // Build tree
    $root_items = [];
    foreach ($items_by_id as $id => &$item) {
        if ($item['parent_id'] && isset($items_by_id[$item['parent_id']])) {
            $items_by_id[$item['parent_id']]['children'][] = &$item;
        } else {
            $root_items[] = &$item;
        }
    }
    
    // Set levels
    _audit_set_levels($root_items, 0);
    
    // If root_slug specified, find that subtree
    if (!empty($root_slug)) {
        $root_item = audit_find_item_by_slug($root_items, $root_slug);
        if ($root_item) {
            $root_items = [$root_item];
        }
        // If not found, return all items (caller can show warning)
    }
    
    return [
        'menu' => [
            'term_id' => $menu_term->term_id,
            'name' => $menu_term->name,
            'slug' => $menu_term->slug
        ],
        'items' => $root_items,
        'item_count' => count($menu_items),
        'root_slug' => $root_slug,
        'generated_at' => time()
    ];
}

/**
 * Clear audit cache(s) for a specific menu/root combination
 *
 * @param string $menu_slug Menu slug (empty = clear all audit caches)
 * @param string $root_slug Root slug
 * @return int Number of transients deleted
 */
function audit_clear_cache($menu_slug = '', $root_slug = '') {
    global $wpdb;
    $deleted = 0;
    
    if (!empty($menu_slug)) {
        // Clear specific cache
        $cache_key = audit_cache_key('menu_tree', [$menu_slug, $root_slug]);
        if (delete_transient($cache_key)) {
            $deleted++;
        }
    } else {
        // Clear all audit caches (pattern match)
        $site_id = get_current_blog_id();
        $pattern = '_transient_audit_%_' . $site_id . '_%';
        
        $transients = $wpdb->get_col($wpdb->prepare("
            SELECT option_name FROM {$wpdb->options}
            WHERE option_name LIKE %s
        ", $pattern));
        
        foreach ($transients as $transient_name) {
            // Extract transient key from option_name
            $key = str_replace('_transient_', '', $transient_name);
            if (delete_transient($key)) {
                $deleted++;
            }
        }
    }
    
    return $deleted;
}

/**
 * Check if megamenu audit should be blocked (production guardrails)
 *
 * @param bool $force_requested Whether &force=1 was passed
 * @return array ['blocked' => bool, 'reason' => string]
 */
function audit_check_production_guardrails($force_requested = false) {
    // Capability gate: require at least edit_posts
    if (!current_user_can('edit_posts')) {
        return [
            'blocked' => true,
            'reason' => 'You do not have permission to run the megamenu audit. Required capability: edit_posts'
        ];
    }
    
    // Environment gate: block on production unless admin OR force=1 with manage_options
    $env_type = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
    
    if ($env_type === 'production') {
        // Admins can always access (no force required)
        if (current_user_can('manage_options')) {
            return ['blocked' => false, 'reason' => ''];
        }
        
        // Non-admins on production are blocked
        return [
            'blocked' => true,
            'reason' => 'Megamenu audit is disabled on production to prevent heavy database operations. Administrator privileges required.'
        ];
    }
    
    return ['blocked' => false, 'reason' => ''];
}

/**
 * Render cache/timing instrumentation header
 *
 * @param bool $cache_hit Whether cache was hit
 * @param float $build_time Time spent building (seconds)
 * @param float $render_time Time spent rendering (seconds)
 */
function audit_render_instrumentation($cache_hit, $build_time = 0, $render_time = 0) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $status = $cache_hit ? '<span style="color:green;font-weight:bold;">HIT</span>' : '<span style="color:orange;font-weight:bold;">MISS</span>';
    
    echo '<div style="background:#e7f3ff; border:1px solid #0073aa; padding:10px 15px; margin-bottom:15px; font-size:12px; color:#333;">';
    echo '<strong>Cache Status:</strong> ' . $status;
    if (!$cache_hit && $build_time > 0) {
        echo ' | <strong>Build Time:</strong> ' . number_format($build_time * 1000, 2) . 'ms';
    }
    if ($render_time > 0) {
        echo ' | <strong>Render Time:</strong> ' . number_format($render_time * 1000, 2) . 'ms';
    }
    echo ' | <a href="' . esc_url(add_query_arg('clear_cache', '1')) . '">Clear Cache</a>';
    echo '</div>';
}

// =============================================================================
// MEGAMENU DATA FUNCTIONS (with content - for rendering)
// =============================================================================

/**
 * Get megamenu data for audit purposes.
 * Reuses the megamenu data structure but returns flat items with hierarchy info.
 *
 * @param string $menu_slug The menu slug (default: 'megamenu')
 * @return array|false Menu data or false if not found
 */
function audit_get_megamenu_data($menu_slug = 'megamenu') {
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
    
    // Get all menu items with their metadata
    $menu_items = $wpdb->get_results($wpdb->prepare("
        SELECT 
            p.ID,
            p.post_title,
            p.menu_order,
            pm_object_id.meta_value AS object_id,
            pm_object.meta_value AS object_type,
            pm_parent.meta_value AS menu_item_parent,
            linked_post.ID AS linked_id,
            linked_post.post_title AS linked_title,
            linked_post.post_name AS linked_slug,
            linked_post.post_type AS linked_post_type,
            linked_post.post_content AS linked_content
        FROM {$wpdb->posts} p
        JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        LEFT JOIN {$wpdb->postmeta} pm_object_id ON p.ID = pm_object_id.post_id AND pm_object_id.meta_key = '_menu_item_object_id'
        LEFT JOIN {$wpdb->postmeta} pm_object ON p.ID = pm_object.post_id AND pm_object.meta_key = '_menu_item_object'
        LEFT JOIN {$wpdb->postmeta} pm_parent ON p.ID = pm_parent.post_id AND pm_parent.meta_key = '_menu_item_menu_item_parent'
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
    
    foreach ($menu_items as $item) {
        $items_by_id[$item->ID] = [
            'menu_item_id' => $item->ID,
            'menu_title' => $item->post_title,
            'menu_order' => $item->menu_order,
            'object_id' => $item->object_id,
            'object_type' => $item->object_type,
            'parent_id' => (int)$item->menu_item_parent,
            'linked_id' => $item->linked_id,
            'linked_title' => $item->linked_title,
            'linked_slug' => $item->linked_slug,
            'linked_post_type' => $item->linked_post_type,
            'linked_content' => $item->linked_content,
            'children' => [],
            'level' => 0
        ];
    }
    
    // Build tree
    $root_items = [];
    foreach ($items_by_id as $id => &$item) {
        if ($item['parent_id'] && isset($items_by_id[$item['parent_id']])) {
            $items_by_id[$item['parent_id']]['children'][] = &$item;
        } else {
            $root_items[] = &$item;
        }
    }
    
    // Set levels
    _audit_set_levels($root_items, 0);
    
    return [
        'menu' => $menu_term,
        'items' => $root_items,
        'items_by_id' => $items_by_id
    ];
}

/**
 * Recursively set nesting levels for audit
 */
function _audit_set_levels(&$items, $level) {
    foreach ($items as &$item) {
        $item['level'] = $level;
        if (!empty($item['children'])) {
            _audit_set_levels($item['children'], $level + 1);
        }
    }
}

/**
 * Find a menu item by its linked post slug within the menu tree
 *
 * @param array $items Menu items array
 * @param string $slug The slug to find
 * @return array|null The found item or null
 */
function audit_find_item_by_slug($items, $slug) {
    foreach ($items as $item) {
        if ($item['linked_slug'] === $slug) {
            return $item;
        }
        if (!empty($item['children'])) {
            $found = audit_find_item_by_slug($item['children'], $slug);
            if ($found) {
                return $found;
            }
        }
    }
    return null;
}

/**
 * Flatten menu tree into ordered array for rendering
 *
 * @param array $items Menu items (hierarchical)
 * @param array $breadcrumb Current breadcrumb path
 * @return array Flat array of items with breadcrumb info
 */
function audit_flatten_menu_tree($items, $breadcrumb = []) {
    $flat = [];
    foreach ($items as $item) {
        $current_breadcrumb = $breadcrumb;
        $current_breadcrumb[] = $item['linked_title'] ?: $item['menu_title'];
        
        $item['breadcrumb'] = $current_breadcrumb;
        $flat[] = $item;
        
        if (!empty($item['children'])) {
            $flat = array_merge($flat, audit_flatten_menu_tree($item['children'], $current_breadcrumb));
        }
    }
    return $flat;
}

// =============================================================================
// MEGAMENU SORTING
// =============================================================================

/**
 * Supported wp_posts fields for sorting
 */
function audit_get_sortable_post_fields() {
    return ['ID', 'post_title', 'post_name', 'post_type', 'post_date', 'post_modified'];
}

/**
 * Sort flattened megamenu items by a field
 * 
 * @param array $items Flattened menu items (each has linked_id, breadcrumb, level)
 * @param string $sortby Field to sort by (wp_posts field or meta_key)
 * @param string $order ASC or DESC
 * @return array Sorted items
 */
function audit_sort_megamenu_items($items, $sortby, $order = 'ASC') {
    if (empty($sortby) || empty($items)) {
        return $items;
    }
    
    $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
    $post_fields = audit_get_sortable_post_fields();
    $is_post_field = in_array($sortby, $post_fields, true);
    
    // Collect all linked post IDs for bulk operations
    $post_ids = array_filter(array_column($items, 'linked_id'));
    
    // Prime meta cache if sorting by meta key
    if (!$is_post_field && !empty($post_ids)) {
        update_meta_cache('post', $post_ids);
    }
    
    // Enrich items with sort values
    $enriched = [];
    foreach ($items as $item) {
        $post_id = $item['linked_id'];
        $sort_value_raw = null;
        
        if ($post_id) {
            if ($is_post_field) {
                $post = get_post($post_id);
                if ($post) {
                    $sort_value_raw = $post->$sortby;
                }
            } else {
                $meta_value = get_post_meta($post_id, $sortby, true);
                if (is_array($meta_value)) {
                    $meta_value = reset($meta_value);
                }
                $sort_value_raw = $meta_value;
            }
        }
        
        $item['_sort_raw'] = $sort_value_raw;
        $item['_sort_normalized'] = audit_normalize_sort_value($sort_value_raw, $sortby);
        $enriched[] = $item;
    }
    
    // Detect if all non-null values are numeric
    $all_numeric = true;
    foreach ($enriched as $item) {
        if ($item['_sort_normalized'] !== null && !is_numeric($item['_sort_normalized'])) {
            $all_numeric = false;
            break;
        }
    }
    
    // Sort
    usort($enriched, function($a, $b) use ($order, $all_numeric) {
        $val_a = $a['_sort_normalized'];
        $val_b = $b['_sort_normalized'];
        
        // NULLs always last regardless of order
        if ($val_a === null && $val_b === null) return 0;
        if ($val_a === null) return 1;
        if ($val_b === null) return -1;
        
        // Compare
        if ($all_numeric) {
            $cmp = (float)$val_a <=> (float)$val_b;
        } else {
            $cmp = strnatcasecmp((string)$val_a, (string)$val_b);
        }
        
        return $order === 'DESC' ? -$cmp : $cmp;
    });
    
    // Clean up internal keys
    foreach ($enriched as &$item) {
        unset($item['_sort_raw'], $item['_sort_normalized']);
    }
    
    return $enriched;
}

/**
 * Normalize a sort value for comparison
 * 
 * Handles date/time parsing for utc_start and similar fields.
 * 
 * @param mixed $value Raw value
 * @param string $sortby Field name (used to detect date fields)
 * @return mixed Normalized value (numeric for dates, original otherwise)
 */
function audit_normalize_sort_value($value, $sortby) {
    if ($value === null || $value === '' || $value === false) {
        return null;
    }
    
    // Date/time fields - convert to epoch
    $date_fields = ['utc_start', 'utc_end', 'event_date', 'start_date', 'end_date'];
    if (in_array($sortby, $date_fields, true) || 
        $sortby === 'post_date' || $sortby === 'post_modified') {
        
        // Already numeric timestamp
        if (is_numeric($value)) {
            return (int)$value;
        }
        
        // Try to parse as date string
        $parsed = strtotime($value);
        if ($parsed !== false) {
            return $parsed;
        }
        
        return null;
    }
    
    // Return as-is for other fields
    return $value;
}

/**
 * Render sort status line
 * 
 * @param string $sortby Sort field
 * @param string $order Sort order
 */
function audit_render_sort_status($sortby, $order) {
    if (empty($sortby)) {
        return;
    }
    echo '<div class="audit-sort-status" style="background:#e7f3ff;border:1px solid #0073aa;padding:10px 15px;margin-bottom:15px;border-radius:3px;">';
    echo '<strong>Sorted by:</strong> <code>' . esc_html($sortby) . '</code> ';
    echo '<span style="color:#666;">(' . esc_html($order) . ')</span>';
    echo '</div>';
}

/**
 * Filter postmeta to exclude internal/plugin keys
 *
 * @param array $meta_rows Raw postmeta rows
 * @return array Filtered meta key-value pairs
 */
function audit_filter_postmeta($meta_rows) {
    $filtered = [];
    
    // Patterns to exclude
    $exclude_prefixes = ['_', 'wp_', '_wp_', '_edit_', '_oembed_'];
    $exclude_exact = [
        'edit_lock', 'edit_last', 'enclosure', 'pingback', 
        'post_views_count', '_pingme', '_encloseme'
    ];
    
    foreach ($meta_rows as $row) {
        $key = $row->meta_key;
        
        // Skip if starts with underscore (most internal keys)
        if (strpos($key, '_') === 0) {
            continue;
        }
        
        // Skip exact matches
        if (in_array($key, $exclude_exact, true)) {
            continue;
        }
        
        // Skip common plugin patterns
        if (preg_match('/^(rank_math|yoast|aioseo|jetpack|_yoast|_aioseop)/i', $key)) {
            continue;
        }
        
        $filtered[$key] = $row->meta_value;
    }
    
    return $filtered;
}

/**
 * Extract image URLs from content and meta values
 *
 * @param string $content Post content
 * @param array $meta_values Meta values array
 * @return array Array of image URLs
 */
function audit_extract_image_urls($content, $meta_values) {
    $images = [];
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
    
    // Combine content and meta values for searching
    $search_text = $content;
    foreach ($meta_values as $value) {
        if (is_string($value)) {
            $search_text .= ' ' . $value;
        }
    }
    
    // Find URLs that look like images
    // Match src="..." or href="..." or plain URLs
    preg_match_all('/(?:src|href)=["\']([^"\']+)["\']|https?:\/\/[^\s<>"\']+/i', $search_text, $matches);
    
    $all_urls = array_merge(
        isset($matches[1]) ? array_filter($matches[1]) : [],
        isset($matches[0]) ? array_filter($matches[0], function($u) { return strpos($u, 'http') === 0; }) : []
    );
    
    foreach ($all_urls as $url) {
        // Clean URL
        $url = trim($url);
        if (empty($url)) continue;
        
        // Check if it's an image
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (in_array($ext, $image_extensions, true)) {
            $images[] = $url;
        }
        // Also check for wp-content/uploads pattern
        elseif (strpos($url, '/wp-content/uploads/') !== false) {
            $images[] = $url;
        }
    }
    
    // Also check for attachment IDs in meta (common pattern)
    foreach ($meta_values as $key => $value) {
        if (is_numeric($value) && $value > 0) {
            $attachment_url = wp_get_attachment_url((int)$value);
            if ($attachment_url) {
                $ext = strtolower(pathinfo($attachment_url, PATHINFO_EXTENSION));
                if (in_array($ext, $image_extensions, true)) {
                    $images[] = $attachment_url;
                }
            }
        }
    }
    
    return array_unique($images);
}

/**
 * Extract video URLs from meta values where key contains 'video'
 *
 * @param array $meta_values Meta key-value pairs
 * @return array Array of video URLs
 */
function audit_extract_video_urls($meta_values) {
    $videos = [];
    
    foreach ($meta_values as $key => $value) {
        // Check if key contains 'video' (case-insensitive)
        if (stripos($key, 'video') !== false && !empty($value)) {
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                $videos[] = [
                    'key' => $key,
                    'url' => $value
                ];
            }
        }
    }
    
    return $videos;
}

/**
 * Render embedded video HTML
 *
 * @param string $url Video URL
 * @return string HTML for embedded video
 */
function audit_render_video_embed($url) {
    // Try WordPress oEmbed first
    $embed = wp_oembed_get($url, ['width' => 400]);
    if ($embed) {
        return $embed;
    }
    
    // YouTube patterns
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $video_id = $matches[1];
        return '<iframe width="400" height="225" src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" frameborder="0" allowfullscreen></iframe>';
    }
    
    // Vimeo patterns
    if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $matches)) {
        $video_id = $matches[1];
        return '<iframe width="400" height="225" src="https://player.vimeo.com/video/' . esc_attr($video_id) . '" frameborder="0" allowfullscreen></iframe>';
    }
    
    // Direct video file
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (in_array($ext, ['mp4', 'webm', 'ogg'], true)) {
        return '<video width="400" controls><source src="' . esc_url($url) . '" type="video/' . esc_attr($ext) . '">Your browser does not support video.</video>';
    }
    
    // Fallback: just show the link
    return '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a>';
}

/**
 * Get post content for a specific post ID (lazy loading for cached subtree)
 *
 * @param int $post_id The post ID
 * @return string Post content or empty string
 */
function audit_get_post_content($post_id) {
    if (empty($post_id)) {
        return '';
    }
    $post = get_post($post_id);
    return $post ? $post->post_content : '';
}

/**
 * Render the megamenu content audit page
 *
 * Uses cached subtree for menu structure, fetches content on-demand.
 *
 * @param string $menu_slug Menu slug to audit
 * @param string $root_slug Optional root item slug to start from
 * @param array $type_filter Optional post type filter
 * @param string $sortby Optional field to sort by
 * @param string $sort_order Optional sort order (ASC/DESC)
 */
function render_megamenu_content_audit($menu_slug = 'megamenu', $root_slug = '', $type_filter = [], $sortby = '', $sort_order = 'ASC') {
    // Use cached subtree (already fetched in page-audit.php, but safe to call again - will be cache hit)
    $subtree_result = audit_get_megamenu_subtree_cached($menu_slug, $root_slug);
    $subtree = $subtree_result['data'];
    
    if (!$subtree) {
        echo '<div class="notice notice-error"><p>Menu not found: ' . esc_html($menu_slug) . '</p></div>';
        return;
    }
    
    $items = $subtree['items'];
    $menu_info = $subtree['menu'];
    
    // Flatten for rendering
    $flat_items = audit_flatten_menu_tree($items);
    
    // Apply type filter
    if (!empty($type_filter)) {
        $flat_items = audit_filter_by_type($flat_items, $type_filter);
    }
    
    // Apply sorting if requested
    if (!empty($sortby)) {
        $flat_items = audit_sort_megamenu_items($flat_items, $sortby, $sort_order);
    }
    
    if (empty($flat_items)) {
        echo '<div class="notice notice-warning"><p>No menu items found matching criteria.</p></div>';
        return;
    }
    
    // Output CSS
    echo '<style>
        .megamenu-audit-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .megamenu-audit-table th,
        .megamenu-audit-table td {
            border: 1px solid #ddd;
            padding: 10px;
            vertical-align: top;
            text-align: left;
            color: #333;
        }
        .megamenu-audit-header {
            background: #f5f5f5;
            font-weight: bold;
            color: #222;
        }
        .megamenu-audit-header a {
            color: #0073aa;
            text-decoration: none;
        }
        .megamenu-audit-header a:hover {
            text-decoration: underline;
        }
        .megamenu-audit-meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .megamenu-audit-meta-table td {
            border: 1px solid #eee;
            padding: 5px 8px;
            vertical-align: top;
        }
        .megamenu-audit-meta-table td:first-child {
            width: 30%;
            font-weight: 600;
            background: #fafafa;
            word-break: break-word;
        }
        .megamenu-audit-meta-table td:last-child {
            word-break: break-word;
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
        }
        .megamenu-audit-content {
            max-height: 300px;
            overflow-y: auto;
            font-size: 14px;
            line-height: 1.5;
        }
        .megamenu-audit-images {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .megamenu-audit-image-item {
            text-align: center;
        }
        .megamenu-audit-image-item img {
            max-width: 120px;
            max-height: 120px;
            border: 1px solid #ddd;
            display: block;
            margin-bottom: 5px;
        }
        .megamenu-audit-image-item a {
            font-size: 11px;
            word-break: break-all;
            display: block;
            max-width: 120px;
        }
        .megamenu-audit-videos {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .megamenu-audit-video-item {
            margin-bottom: 10px;
        }
        .megamenu-audit-video-item .video-key {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 12px;
            color: #666;
        }
        .megamenu-audit-breadcrumb {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
            padding: 5px 10px;
            background: #f9f9f9;
            border-left: 3px solid #0073aa;
        }
        .megamenu-audit-depth {
            display: inline-block;
            background: #0073aa;
            color: #fff;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            margin-right: 10px;
        }
        .megamenu-audit-missing {
            background: #fff3cd;
            border-color: #ffc107;
        }
        .megamenu-audit-missing td {
            color: #856404;
        }
    </style>';
    
    echo '<div class="wrap megamenu-audit-wrap">';
    echo '<h1>Megamenu Content Audit</h1>';
    echo '<p>Menu: <strong>' . esc_html($menu_info['name']) . '</strong> (' . esc_html($menu_info['slug']) . ')</p>';
    if (!empty($root_slug)) {
        echo '<p>Root filter: <strong>' . esc_html($root_slug) . '</strong></p>';
    }
    echo '<p>Total items: <strong>' . count($flat_items) . '</strong></p>';
    
    // Show sort status if sorting is active
    audit_render_sort_status($sortby, $sort_order);
    
    echo '<hr>';
    
    foreach ($flat_items as $item) {
        $post_id = $item['linked_id'];
        $has_post = !empty($post_id);
        $can_edit = $has_post && current_user_can('edit_post', $post_id);
        
        // Breadcrumb
        echo '<div class="megamenu-audit-breadcrumb">';
        echo '<span class="megamenu-audit-depth">Depth: ' . esc_html($item['level']) . '</span>';
        echo esc_html(implode(' → ', $item['breadcrumb']));
        echo '</div>';
        
        // Start table
        $table_class = 'megamenu-audit-table' . ($has_post ? '' : ' megamenu-audit-missing');
        echo '<table class="' . esc_attr($table_class) . '">';
        
        // Row 1: Header
        echo '<tr class="megamenu-audit-header">';
        echo '<th style="width:80px;">ID</th>';
        echo '<th style="width:100px;">Post Type</th>';
        echo '<th style="width:150px;">Post Name</th>';
        echo '<th>Title</th>';
        echo '</tr>';
        
        echo '<tr class="megamenu-audit-header">';
        if ($has_post) {
            // ID column: link to admin edit if user can edit
            echo '<td>';
            if ($can_edit) {
                $edit_url = admin_url('post.php?post=' . $post_id . '&action=edit');
                echo '<a href="' . esc_url($edit_url) . '" target="_blank" rel="noopener">' . esc_html($post_id) . '</a>';
            } else {
                echo esc_html($post_id);
            }
            echo '</td>';
            echo '<td>' . esc_html($item['linked_post_type']) . '</td>';
            echo '<td>' . esc_html($item['linked_slug']) . '</td>';
            // Title column: link to public permalink
            echo '<td>';
            $permalink = get_permalink($post_id);
            if ($permalink) {
                echo '<a href="' . esc_url($permalink) . '" target="_blank" rel="noopener">' . esc_html($item['linked_title']) . '</a>';
            } else {
                echo esc_html($item['linked_title']);
            }
            echo '</td>';
        } else {
            echo '<td colspan="3"><em>No linked post (menu item only)</em></td>';
            echo '<td>' . esc_html($item['menu_title']) . '</td>';
        }
        echo '</tr>';
        
        if ($has_post) {
            // Fetch content on-demand (not in cached subtree)
            $post_content = audit_get_post_content($post_id);
            
            // Get post meta
            global $wpdb;
            $meta_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
                $post_id
            ));
            $filtered_meta = audit_filter_postmeta($meta_rows);
            
            // Row 2: Content + Meta
            echo '<tr>';
            echo '<td colspan="3" style="vertical-align:top;">';
            if (!empty($filtered_meta)) {
                echo '<table class="megamenu-audit-meta-table">';
                foreach ($filtered_meta as $key => $value) {
                    // Apply special formatting (e.g., utc_start datetime)
                    $display_value = audit_format_meta_value($key, $value);
                    // Truncate extremely long values
                    if (strlen($display_value) > 1000) {
                        $display_value = substr($display_value, 0, 1000) . '... [truncated]';
                    }
                    echo '<tr>';
                    echo '<td>' . esc_html($key) . '</td>';
                    echo '<td>' . esc_html($display_value) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<em>No bespoke meta found</em>';
            }
            echo '</td>';
            echo '<td style="vertical-align:top;">';
            echo '<div class="megamenu-audit-content">';
            if (!empty($post_content)) {
                // Apply content filters for readability
                echo wp_kses_post(apply_filters('the_content', $post_content));
            } else {
                echo '<em>No content</em>';
            }
            echo '</div>';
            echo '</td>';
            echo '</tr>';
            
            // Row 3: Featured Image (explicit post_id to avoid global context issues)
            $featured = audit_get_featured_image($post_id);
            echo '<tr>';
            echo '<td><strong>Featured Image</strong></td>';
            echo '<td colspan="3">';
            if ($featured['url']) {
                echo '<div class="megamenu-audit-images">';
                echo '<div class="megamenu-audit-image-item">';
                echo '<a href="' . esc_url($featured['full_url']) . '" target="_blank">';
                echo '<img src="' . esc_url($featured['url']) . '" alt="" loading="lazy">';
                echo '</a>';
                echo '<a href="' . esc_url($featured['full_url']) . '" target="_blank">' . esc_html(basename(parse_url($featured['full_url'], PHP_URL_PATH))) . '</a>';
                echo '</div>';
                echo '</div>';
            } else {
                echo '<em>No featured image</em>';
            }
            echo '</td>';
            echo '</tr>';
            
            // Row 4: Extracted Images (from content and meta)
            $images = audit_extract_image_urls($post_content, $filtered_meta);
            echo '<tr>';
            echo '<td><strong>Content Images</strong></td>';
            echo '<td colspan="3">';
            if (!empty($images)) {
                echo '<div class="megamenu-audit-images">';
                foreach ($images as $img_url) {
                    echo '<div class="megamenu-audit-image-item">';
                    echo '<a href="' . esc_url($img_url) . '" target="_blank">';
                    echo '<img src="' . esc_url($img_url) . '" alt="" loading="lazy">';
                    echo '</a>';
                    echo '<a href="' . esc_url($img_url) . '" target="_blank">' . esc_html(basename(parse_url($img_url, PHP_URL_PATH))) . '</a>';
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<em>No images found in content/meta</em>';
            }
            echo '</td>';
            echo '</tr>';
            
            // Row 5: Videos
            $videos = audit_extract_video_urls($filtered_meta);
            echo '<tr>';
            echo '<td><strong>Videos</strong></td>';
            echo '<td colspan="3">';
            if (!empty($videos)) {
                echo '<div class="megamenu-audit-videos">';
                foreach ($videos as $video) {
                    echo '<div class="megamenu-audit-video-item">';
                    echo '<div class="video-key">' . esc_html($video['key']) . ':</div>';
                    echo audit_render_video_embed($video['url']);
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<em>No videos found</em>';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }
    
    echo '</div>';
}

// =============================================================================
// MEGAMENU AUDIT FILTERS AND VIEWS
// =============================================================================

/**
 * Map user-friendly type argument to actual WordPress post_type slugs.
 * 
 * This handles the mapping between URL parameter values and actual CPT slugs.
 * The site uses these custom post types:
 * - 'profile' (CPT slug: profile)
 * - 'event' (CPT slug: event)
 * - 'resource' (CPT slug: resource)
 * - Standard WP types: 'page', 'post'
 *
 * @param array $type_args Array of type strings from URL parameter
 * @return array Array of actual post_type slugs for WP queries
 */
function audit_map_type_arg_to_post_types($type_args) {
    if (empty($type_args)) {
        return [];
    }
    
    // Mapping from user-friendly names to actual post_type slugs
    // Most are 1:1 but this allows for flexibility if CPT slugs differ
    $type_map = [
        // Custom post types (singular slugs as registered)
        'profile'   => 'profile',
        'profiles'  => 'profile',    // alias
        'event'     => 'event',
        'events'    => 'event',      // alias
        'resource'  => 'resource',
        'resources' => 'resource',   // alias
        // Standard WordPress types
        'page'      => 'page',
        'pages'     => 'page',       // alias
        'post'      => 'post',
        'posts'     => 'post',       // alias
    ];
    
    $mapped = [];
    foreach ($type_args as $arg) {
        $normalized = strtolower(trim($arg));
        if (isset($type_map[$normalized])) {
            $mapped[] = $type_map[$normalized];
        }
        // Unknown types are silently ignored
    }
    
    // Remove duplicates (e.g., if user passed both 'profile' and 'profiles')
    $mapped = array_unique($mapped);
    
    // Debug output when WP_DEBUG is enabled
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Audit] Type mapping: requested=' . implode(',', $type_args) . ' resolved=' . implode(',', $mapped));
    }
    
    return $mapped;
}

/**
 * Get featured image URL for a specific post
 * 
 * Uses explicit post_id to avoid global $post context issues.
 *
 * @param int $post_id The post ID
 * @param string $size Image size (default: 'medium')
 * @return array ['url' => string|null, 'full_url' => string|null]
 */
function audit_get_featured_image($post_id) {
    if (empty($post_id)) {
        return ['url' => null, 'full_url' => null];
    }
    
    $thumb_id = get_post_thumbnail_id($post_id);
    if (!$thumb_id) {
        return ['url' => null, 'full_url' => null];
    }
    
    $medium_url = wp_get_attachment_image_url($thumb_id, 'medium');
    $full_url = wp_get_attachment_image_url($thumb_id, 'full');
    
    return [
        'url' => $medium_url ?: null,
        'full_url' => $full_url ?: null
    ];
}

/**
 * Format utc_start meta value as readable datetime
 *
 * @param string $value Raw meta value (Unix timestamp or ISO string)
 * @return string Formatted datetime "yyyy-mm-dd hh:mm" or original value on failure
 */
function audit_format_utc_start($value) {
    if (empty($value)) {
        return $value;
    }
    
    try {
        // Try Unix timestamp first
        if (is_numeric($value)) {
            $dt = new DateTime('@' . intval($value));
            $dt->setTimezone(new DateTimeZone('UTC'));
            return $dt->format('Y-m-d H:i');
        }
        
        // Try ISO string parsing
        $dt = new DateTime($value, new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i');
    } catch (Exception $e) {
        // Fallback to raw value
        return $value;
    }
}

/**
 * Format meta value for display, with special handling for known keys
 *
 * @param string $key Meta key
 * @param string $value Meta value
 * @return string Formatted value
 */
function audit_format_meta_value($key, $value) {
    // Special formatting for utc_start
    if ($key === 'utc_start') {
        return audit_format_utc_start($value);
    }
    
    return $value;
}

/**
 * Filter menu items by post type(s)
 *
 * @param array $items Flat array of menu items
 * @param array $types Array of post_type strings to include
 * @return array Filtered items
 */
function audit_filter_by_type($items, $types) {
    if (empty($types)) {
        return $items;
    }
    
    return array_filter($items, function($item) use ($types) {
        return in_array($item['linked_post_type'], $types, true);
    });
}

/**
 * Get all posts of specified types that have a specific meta_key
 *
 * @param array $types Post types to search (empty = all)
 * @param string $meta_key Meta key to check for
 * @return array Array of post data with meta value
 */
function audit_get_posts_with_meta($types, $meta_key) {
    global $wpdb;
    
    $type_clause = '';
    if (!empty($types)) {
        $placeholders = implode(',', array_fill(0, count($types), '%s'));
        $type_clause = $wpdb->prepare("AND p.post_type IN ($placeholders)", $types);
    }
    
    $query = $wpdb->prepare("
        SELECT p.ID, p.post_title, p.post_type, p.post_name, pm.meta_value
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE pm.meta_key = %s
        AND p.post_status = 'publish'
        $type_clause
        ORDER BY p.post_type, p.post_title
    ", $meta_key);
    
    return $wpdb->get_results($query);
}

/**
 * Get all posts of specified types that do NOT have a specific meta_key
 *
 * @param array $types Post types to search (required for performance)
 * @param string $meta_key Meta key to check for absence
 * @return array Array of post data
 */
function audit_get_posts_without_meta($types, $meta_key) {
    global $wpdb;
    
    if (empty($types)) {
        // Require type filter for safety (avoid scanning entire posts table)
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($types), '%s'));
    $args = array_merge($types, [$meta_key]);
    
    $query = $wpdb->prepare("
        SELECT p.ID, p.post_title, p.post_type, p.post_name
        FROM {$wpdb->posts} p
        WHERE p.post_type IN ($placeholders)
        AND p.post_status = 'publish'
        AND NOT EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm 
            WHERE pm.post_id = p.ID AND pm.meta_key = %s
        )
        ORDER BY p.post_type, p.post_title
    ", ...$args);
    
    return $wpdb->get_results($query);
}

/**
 * Render active filters header
 *
 * @param array $filters Associative array of active filters
 */
function audit_render_filters_header($filters) {
    if (empty($filters)) {
        return;
    }
    
    echo '<div style="background:#f0f0f0; padding:10px 15px; margin-bottom:20px; border-left:4px solid #0073aa; color:#333;">';
    echo '<strong>Active filters:</strong> ';
    $parts = [];
    foreach ($filters as $key => $value) {
        if (!empty($value)) {
            $parts[] = esc_html($key) . '=' . esc_html(is_array($value) ? implode(',', $value) : $value);
        }
    }
    echo implode(', ', $parts);
    echo '</div>';
}

/**
 * Render compact "has meta" report
 *
 * @param array $types Post types to filter
 * @param string $meta_key Meta key to search for
 */
function audit_render_has_meta_report($types, $meta_key) {
    $posts = audit_get_posts_with_meta($types, $meta_key);
    
    echo '<style>
        .audit-report { background:#fff; color:#333; padding:20px; }
        .audit-report table { width:100%; border-collapse:collapse; }
        .audit-report th, .audit-report td { border:1px solid #ddd; padding:8px; text-align:left; color:#333; }
        .audit-report th { background:#f5f5f5; }
        .audit-report a { color:#0073aa; }
    </style>';
    
    echo '<div class="audit-report">';
    echo '<h2>Posts with meta key: <code>' . esc_html($meta_key) . '</code></h2>';
    
    if (!empty($types)) {
        echo '<p>Filtered to types: ' . esc_html(implode(', ', $types)) . '</p>';
    }
    
    echo '<p>Found: <strong>' . count($posts) . '</strong> posts</p>';
    
    if (empty($posts)) {
        echo '<p><em>No matching posts found.</em></p>';
    } else {
        echo '<table>';
        echo '<thead><tr><th>ID</th><th>Title</th><th>Type</th><th>Meta Value</th></tr></thead>';
        echo '<tbody>';
        foreach ($posts as $post) {
            $can_edit = current_user_can('edit_post', $post->ID);
            $edit_url = admin_url('post.php?post=' . $post->ID . '&action=edit');
            $display_value = audit_format_meta_value($meta_key, $post->meta_value);
            if (strlen($display_value) > 200) {
                $display_value = substr($display_value, 0, 200) . '...';
            }
            
            echo '<tr>';
            echo '<td>' . esc_html($post->ID) . '</td>';
            echo '<td>';
            if ($can_edit) {
                echo '<a href="' . esc_url($edit_url) . '" target="_blank">' . esc_html($post->post_title) . '</a>';
            } else {
                echo esc_html($post->post_title);
            }
            echo '</td>';
            echo '<td>' . esc_html($post->post_type) . '</td>';
            echo '<td>' . esc_html($display_value) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}

/**
 * Render compact "missing meta" report
 *
 * @param array $types Post types to filter (required)
 * @param string $meta_key Meta key to check for absence
 */
function audit_render_missing_meta_report($types, $meta_key) {
    if (empty($types)) {
        echo '<div class="notice notice-error"><p>The &type= parameter is required when using has_not filter for performance reasons.</p></div>';
        return;
    }
    
    $posts = audit_get_posts_without_meta($types, $meta_key);
    
    echo '<style>
        .audit-report { background:#fff; color:#333; padding:20px; }
        .audit-report table { width:100%; border-collapse:collapse; }
        .audit-report th, .audit-report td { border:1px solid #ddd; padding:8px; text-align:left; color:#333; }
        .audit-report th { background:#f5f5f5; }
        .audit-report a { color:#0073aa; }
    </style>';
    
    echo '<div class="audit-report">';
    echo '<h2>Posts missing meta key: <code>' . esc_html($meta_key) . '</code></h2>';
    echo '<p>Filtered to types: ' . esc_html(implode(', ', $types)) . '</p>';
    echo '<p>Found: <strong>' . count($posts) . '</strong> posts</p>';
    
    if (empty($posts)) {
        echo '<p><em>No matching posts found (all posts of these types have this meta key).</em></p>';
    } else {
        echo '<table>';
        echo '<thead><tr><th>ID</th><th>Title</th><th>Type</th><th>Slug</th></tr></thead>';
        echo '<tbody>';
        foreach ($posts as $post) {
            $can_edit = current_user_can('edit_post', $post->ID);
            $edit_url = admin_url('post.php?post=' . $post->ID . '&action=edit');
            
            echo '<tr>';
            echo '<td>' . esc_html($post->ID) . '</td>';
            echo '<td>';
            if ($can_edit) {
                echo '<a href="' . esc_url($edit_url) . '" target="_blank">' . esc_html($post->post_title) . '</a>';
            } else {
                echo esc_html($post->post_title);
            }
            echo '</td>';
            echo '<td>' . esc_html($post->post_type) . '</td>';
            echo '<td>' . esc_html($post->post_name) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}

/**
 * Render megamenu content audit in summary (document) view
 *
 * @param string $menu_slug Menu slug to audit
 * @param string $root_slug Optional root item slug to start from
 * @param array $type_filter Optional post type filter
 * @param string $sortby Optional field to sort by
 * @param string $sort_order Optional sort order (ASC/DESC)
 */
function render_megamenu_summary_view($menu_slug = 'megamenu', $root_slug = '', $type_filter = [], $sortby = '', $sort_order = 'ASC') {
    // Use cached subtree (already fetched in page-audit.php, but safe to call again - will be cache hit)
    $subtree_result = audit_get_megamenu_subtree_cached($menu_slug, $root_slug);
    $subtree = $subtree_result['data'];
    
    if (!$subtree) {
        echo '<div class="notice notice-error"><p>Menu not found: ' . esc_html($menu_slug) . '</p></div>';
        return;
    }
    
    $items = $subtree['items'];
    $menu_info = $subtree['menu'];
    
    // Flatten for rendering
    $flat_items = audit_flatten_menu_tree($items);
    
    // Apply type filter
    if (!empty($type_filter)) {
        $flat_items = audit_filter_by_type($flat_items, $type_filter);
    }
    
    // Apply sorting if requested
    if (!empty($sortby)) {
        $flat_items = audit_sort_megamenu_items($flat_items, $sortby, $sort_order);
    }
    
    if (empty($flat_items)) {
        echo '<div class="notice notice-warning"><p>No menu items found matching criteria.</p></div>';
        return;
    }
    
    // Output CSS for summary view
    echo '<style>
        .megamenu-summary {
            background: #fff;
            color: #333;
            padding: 30px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            max-width: 900px;
        }
        .megamenu-summary h1 {
            color: #222;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        .megamenu-summary-item {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid #ddd;
        }
        .megamenu-summary-heading {
            font-size: 18px;
            font-weight: bold;
            color: #222;
            margin-bottom: 5px;
        }
        .megamenu-summary-heading a {
            color: #0073aa;
            text-decoration: none;
        }
        .megamenu-summary-heading a:hover {
            text-decoration: underline;
        }
        .megamenu-summary-depth {
            display: inline-block;
            background: #666;
            color: #fff;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            margin-right: 10px;
            font-weight: normal;
        }
        .megamenu-summary-keyfields {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
            padding: 8px 12px;
            background: #f9f9f9;
            border-left: 3px solid #ccc;
        }
        .megamenu-summary-keyfields code {
            background: #eee;
            padding: 1px 4px;
            border-radius: 2px;
        }
        .megamenu-summary-meta {
            margin-bottom: 15px;
        }
        .megamenu-summary-meta h4 {
            font-size: 14px;
            color: #444;
            margin: 0 0 8px 0;
        }
        .megamenu-summary-meta ul {
            margin: 0;
            padding-left: 20px;
            font-size: 13px;
        }
        .megamenu-summary-meta li {
            margin-bottom: 4px;
        }
        .megamenu-summary-meta .meta-key {
            font-weight: 600;
            color: #555;
        }
        .megamenu-summary-content {
            margin-bottom: 15px;
        }
        .megamenu-summary-content h4 {
            font-size: 14px;
            color: #444;
            margin: 0 0 8px 0;
        }
        .megamenu-summary-content-body {
            padding: 10px 15px;
            background: #fafafa;
            border: 1px solid #eee;
            font-size: 14px;
            max-height: 300px;
            overflow-y: auto;
        }
        .megamenu-summary-assets {
            font-size: 13px;
        }
        .megamenu-summary-assets h4 {
            font-size: 14px;
            color: #444;
            margin: 10px 0 5px 0;
        }
        .megamenu-summary-assets ul {
            margin: 0;
            padding-left: 20px;
        }
        .megamenu-summary-assets li {
            margin-bottom: 3px;
            word-break: break-all;
        }
        .megamenu-summary-assets a {
            color: #0073aa;
        }
        .megamenu-summary-missing {
            background: #fff3cd;
            padding: 15px;
            border-left: 3px solid #ffc107;
        }
    </style>';
    
    echo '<div class="megamenu-summary">';
    echo '<h1>Megamenu Content Audit - Summary View</h1>';
    echo '<p><strong>Menu:</strong> ' . esc_html($menu_info['name']) . ' (' . esc_html($menu_info['slug']) . ')</p>';
    if (!empty($root_slug)) {
        echo '<p><strong>Root filter:</strong> ' . esc_html($root_slug) . '</p>';
    }
    if (!empty($type_filter)) {
        echo '<p><strong>Type filter:</strong> ' . esc_html(implode(', ', $type_filter)) . '</p>';
    }
    echo '<p><strong>Total items:</strong> ' . count($flat_items) . '</p>';
    
    // Show sort status if sorting is active
    audit_render_sort_status($sortby, $sort_order);
    
    echo '<hr style="margin: 20px 0;">';
    
    foreach ($flat_items as $item) {
        $post_id = $item['linked_id'];
        $has_post = !empty($post_id);
        $can_edit = $has_post && current_user_can('edit_post', $post_id);
        
        echo '<div class="megamenu-summary-item' . ($has_post ? '' : ' megamenu-summary-missing') . '">';
        
        // Heading with depth and title (title links to public permalink)
        echo '<div class="megamenu-summary-heading">';
        echo '<span class="megamenu-summary-depth">Level ' . esc_html($item['level']) . '</span>';
        if ($has_post) {
            $permalink = get_permalink($post_id);
            if ($permalink) {
                echo '<a href="' . esc_url($permalink) . '" target="_blank" rel="noopener">' . esc_html($item['linked_title']) . '</a>';
            } else {
                echo esc_html($item['linked_title']);
            }
        } else {
            echo esc_html($item['linked_title'] ?: $item['menu_title']);
        }
        echo '</div>';
        
        if ($has_post) {
            // Fetch content on-demand (not in cached subtree)
            $post_content = audit_get_post_content($post_id);
            
            // Key fields line (ID links to admin edit if can_edit)
            $permalink = get_permalink($post_id);
            echo '<div class="megamenu-summary-keyfields">';
            echo '<strong>ID:</strong> ';
            if ($can_edit) {
                $edit_url = admin_url('post.php?post=' . $post_id . '&action=edit');
                echo '<a href="' . esc_url($edit_url) . '" target="_blank" rel="noopener">' . esc_html($post_id) . '</a>';
            } else {
                echo esc_html($post_id);
            }
            echo ' | ';
            echo '<strong>Type:</strong> ' . esc_html($item['linked_post_type']) . ' | ';
            echo '<strong>Slug:</strong> <code>' . esc_html($item['linked_slug']) . '</code> | ';
            echo '<strong>URL:</strong> <a href="' . esc_url($permalink) . '" target="_blank" rel="noopener">' . esc_html($permalink) . '</a>';
            echo '</div>';
            
            // Get post meta
            global $wpdb;
            $meta_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
                $post_id
            ));
            $filtered_meta = audit_filter_postmeta($meta_rows);
            
            // Bespoke meta
            if (!empty($filtered_meta)) {
                echo '<div class="megamenu-summary-meta">';
                echo '<h4>Custom Fields</h4>';
                echo '<ul>';
                foreach ($filtered_meta as $key => $value) {
                    $display_value = audit_format_meta_value($key, $value);
                    if (strlen($display_value) > 500) {
                        $display_value = substr($display_value, 0, 500) . '... [truncated]';
                    }
                    echo '<li><span class="meta-key">' . esc_html($key) . ':</span> ' . esc_html($display_value) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
            
            // Post content (fetched on-demand)
            if (!empty($post_content)) {
                echo '<div class="megamenu-summary-content">';
                echo '<h4>Content</h4>';
                echo '<div class="megamenu-summary-content-body">';
                echo wp_kses_post(apply_filters('the_content', $post_content));
                echo '</div>';
                echo '</div>';
            }
            
            // Featured Image (explicit post_id)
            $featured = audit_get_featured_image($post_id);
            echo '<div class="megamenu-summary-assets">';
            echo '<h4>Featured Image</h4>';
            if ($featured['full_url']) {
                echo '<p><a href="' . esc_url($featured['full_url']) . '" target="_blank">' . esc_html($featured['full_url']) . '</a></p>';
            } else {
                echo '<p><em>No featured image</em></p>';
            }
            
            // Content Images (extracted from content and meta)
            $images = audit_extract_image_urls($post_content, $filtered_meta);
            echo '<h4>Content Images</h4>';
            if (!empty($images)) {
                echo '<ul>';
                foreach ($images as $img_url) {
                    echo '<li><a href="' . esc_url($img_url) . '" target="_blank">' . esc_html($img_url) . '</a></li>';
                }
                echo '</ul>';
            } else {
                echo '<p><em>No images found in content/meta</em></p>';
            }
            
            // Videos (URLs only in summary view)
            $videos = audit_extract_video_urls($filtered_meta);
            echo '<h4>Videos</h4>';
            if (!empty($videos)) {
                echo '<ul>';
                foreach ($videos as $video) {
                    echo '<li><strong>' . esc_html($video['key']) . ':</strong> <a href="' . esc_url($video['url']) . '" target="_blank">' . esc_html($video['url']) . '</a></li>';
                }
                echo '</ul>';
            } else {
                echo '<p><em>No videos found</em></p>';
            }
            echo '</div>';
            
        } else {
            echo '<p><em>Menu item only - no linked post</em></p>';
        }

        echo '</div>';
    }

    echo '</div>';
}

/**
 * Render a "Keys" table showing Level 2 menu items (object_id + post_title).
 *
 * Level 2 = items whose parent is a top-level (level 1) item.
 * In the get_nesting_level() convention used here, top-level items return 0
 * and their direct children return 1. We want the direct children of top-level
 * items, so we filter for nesting level === 1.
 *
 * @param array $menu_items_map Associative array of menu items keyed by ID.
 * @param array $raw_items      The ordered menu_items array from $results['menu_items'].
 */
function audit_render_keys_table($menu_items_map, $raw_items) {
    $rows = array();
    foreach ($raw_items as $item) {
        $level = get_nesting_level($menu_items_map, $item->ID);
        if ($level !== 1) {
            continue;
        }
        if (empty($item->object_id)) {
            continue;
        }
        $post = get_post($item->object_id);

        // Collect only direct children (level 3 = nesting level 2)
        $children = array();
        foreach ($raw_items as $child) {
            if ((int) $child->menu_item_parent !== (int) $item->ID) {
                continue;
            }
            if (get_nesting_level($menu_items_map, $child->ID) !== 2) {
                continue;
            }
            if (empty($child->object_id)) {
                continue;
            }
            $child_post = get_post($child->object_id);
            if ($child_post) {
                $children[] = $child_post->post_title;
            }
        }

        $title = $post ? $post->post_title : '';

        // Skip host intro items
        $title_lower = strtolower($title);
        if (strpos($title_lower, 'host intro') !== false) {
            continue;
        }

        if (!empty($children)) {
            $title .= ' — ' . implode(', ', $children);
        }

        $embed_video_url = get_post_meta($item->object_id, 'embed_video_url', true);
        $video_url       = get_post_meta($item->object_id, 'video_url', true);

        $rows[] = array(
            'object_id'       => $item->object_id,
            'post_title'      => $title,
            'embed_video_url' => $embed_video_url ? $embed_video_url : '',
            'video_url'       => $video_url ? $video_url : '',
        );
    }

    if (empty($rows)) {
        return;
    }

    echo '<h3>Keys</h3>';
    echo '<table class="widefat">';
    echo '<thead><tr><th>object_id</th><th>post_title</th><th>embed_video_url</th><th>video_url</th></tr></thead>';
    echo '<tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . esc_html($row['object_id']) . '</td>';
        echo '<td>' . esc_html($row['post_title']) . '</td>';
        echo '<td>' . esc_html($row['embed_video_url']) . '</td>';
        echo '<td>' . esc_html($row['video_url']) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}