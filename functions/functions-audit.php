<?php
/**
 * Database Audit Functions
 * 
 * Functions for auditing database relationships and menu structures
 */

if (!defined('ABSPATH')) {
    exit;
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
    return preg_match($pattern, $menu_slug) === 1;
} 