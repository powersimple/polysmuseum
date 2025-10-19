<?php
/**
 * Database Audit and Repair Tool
 * 
 * This tool helps identify and fix database issues, particularly focusing on:
 * - Missing or incorrect relationships
 * - Orphaned post meta
 * - Inconsistent data
 * - Missing required fields
 * - Menu relationships
 * - Event-Profile connections
 */

require_once get_template_directory() . '/functions/functions-audit.php';

get_header();

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

// Add the metadata (gated for safety)
if (isset($_GET['update_event_types']) && current_user_can('manage_options')) {
    add_event_type_metadata();
}

// Initialize awards array
$awards = array();

// Display duplicate profiles
profile_appearances();

// Get all menus for the top list
$all_menus = get_all_nav_menus();

// Only show full menu analysis if not in summary view
if (!isset($_GET['view']) || $_GET['view'] !== 'summary') {
    echo '<div class="wrap audit-page">';
    echo render_available_menus($all_menus);
}

// Only run menu analysis if event_menu parameter is present
if (isset($_GET['event_menu'])) {
    // Get all menu slugs
    $menu_slug = $_GET['event_menu'];
    
    // Resolve wildcard patterns
    $menu_slugs = resolve_menu_slugs($menu_slug);
    if (empty($menu_slugs)) {
        echo '<div class="notice notice-error"><p>No menus found matching pattern: ' . esc_html($menu_slug) . '</p></div>';
        get_footer();
        return;
    }

    if (!isset($_GET['view']) || $_GET['view'] !== 'summary') {
        echo '<h1>Menu Structures</h1>';
    }

    foreach ($menu_slugs as $slug) {
        // Get menu and items by slug via helper
        $results = get_menu_items_for_slug($slug);

        if (is_string($results)) {
            echo '<div class="notice notice-error"><p>' . esc_html($results) . '</p></div>';
            continue;
        }

        // Build parent-child relationships
        $menu_items = array();
        $parent_map = array();
        foreach ($results['menu_items'] as $item) {
            $menu_items[$item->ID] = $item;
            if ($item->menu_item_parent) {
                $parent_map[$item->ID] = $item->menu_item_parent;
            }
        }

        if (!isset($_GET['view']) || $_GET['view'] !== 'summary') {
            echo '<div class="card">';
            echo '<h2>Menu: ' . esc_html($results['menu']->name) . '</h2>';
            echo '<ul>';
            echo '<li>Name: ' . esc_html($results['menu']->name) . '</li>';
            echo '<li>Slug: ' . esc_html($results['menu']->slug) . '</li>';
            echo '</ul>';
            
            echo '<h2>Menu Items</h2>';
            echo '<table class="widefat">';
            echo '<thead><tr>';
            echo '<th>Order</th>';
            echo '<th>Post Type</th>';
            echo '<th>Object ID</th>';
            echo '<th>Parent ID</th>';
            echo '<th>Type</th>';
            echo '<th>Post Title</th>';
            echo '<th>Menu Title</th>';
            echo '<th>Winner</th>';
            echo '</tr></thead><tbody>';
        }
        
        $current_award = null;
        // Track starting index of awards contributed by this menu
        $awards_menu_start = isset($awards) ? count($awards) : 0;
        // Build a quick index map for menu item ID -> position
        $index_by_id = array();
        foreach ($results['menu_items'] as $pos => $mi) { $index_by_id[$mi->ID] = $pos; }
        foreach ($results['menu_items'] as $item) {
            // Get the actual post title
            $post_title = '';
            $edit_link = '';
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
                    // Add edit link for profiles, events, and resources
                    if ($item->actual_post_type === 'profile' || 
                        $item->actual_post_type === 'event' || 
                        $item->actual_post_type === 'resource') {
                        $edit_link = admin_url("post.php?action=edit&post=" . $item->object_id);
                    }
                }
            }
            
            // Get type info based on post type
            if ($item->actual_post_type === 'event') {
                $type_info = get_post_meta($item->ID, '_event_type', true);
                $row_style = 'background-color: #fff;'; // Add white background for events
                // Track award categories
                if ($type_info === 'nomination-category' || $type_info === 'honors-presentation') {
                    // If we have a current award, make sure it's complete before starting a new one
                    if ($current_award !== null) {
                        $awards[] = $current_award;
                    }
                    $current_award = array(
                        'title' => $item->post_title ?: $post_title,
                        'type' => $type_info,
                        'presenters' => array(),
                        'winners' => array(),
                        'award_type' => '',
                        'object_id' => $item->object_id,
                        'presenter_ids' => array(),
                        'winner_ids' => array(),
                        'current_winner' => null
                    );
                }
            } elseif ($item->actual_post_type === 'profile') {
                $type_info = get_post_meta($item->ID, '_guest_type', true);
                // If this is an award presenter, add to the current award's presenters array
                if ($type_info === 'award-presenter' && $current_award !== null) {
                    $current_award['presenters'][] = $item->post_title ?: $post_title;
                    $current_award['presenter_ids'][] = $item->object_id;
                }
            }
            
            // Get nesting level using the existing function
            $level = get_nesting_level($menu_items, $item->ID);

            // If this is a winner/honoree at level 2, collect its level 3/4 descendants as winner details
            if ($level === 2 && ($is_winner || $is_honoree) && $current_award !== null) {
                // Parse the level-2 title/company once
                $base_title = $item->post_title ?: $post_title;
                $base_company = '';
                if (preg_match('/^(.*?)\s+by\s+(.*?)$/', $base_title, $matches)) {
                    $base_title = trim($matches[1]);
                    $base_company = trim($matches[2]);
                }

                $winners_for_node = array();

                // Walk direct children (level 3) in menu order
                foreach ($results['menu_items'] as $child_item) {
                    if ($child_item->menu_item_parent == $item->ID) {
                        $child_post = get_post($child_item->object_id);
                        if (!$child_post) { continue; }

                        // Detect if this level-3 child has its own children (treat as company group)
                        $child_has_children = false;
                        foreach ($results['menu_items'] as $probe_item) {
                            if ($probe_item->menu_item_parent == $child_item->ID) { $child_has_children = true; break; }
                        }

                        // Case 1: Company node at level 3 (resource or any item that has children)
                        if ($child_item->actual_post_type === 'resource' || $child_has_children) {
                            $wi = array(
                                'title' => $base_title,
                                'company' => $child_post->post_title ?: $base_company,
                                'people' => array()
                            );
                            // Gather level 4 people under this company
                            foreach ($results['menu_items'] as $grandchild_item) {
                                if ($grandchild_item->menu_item_parent == $child_item->ID) {
                                    $grandchild_post = get_post($grandchild_item->object_id);
                                    if ($grandchild_post && $grandchild_item->actual_post_type === 'profile') {
                                        $wi['people'][] = $grandchild_post->post_title;
                                    }
                                }
                            }
                            $winners_for_node[] = $wi;
                        }
                        // Case 2: Person directly under the winner at level 3
                        elseif ($child_item->actual_post_type === 'profile') {
                            $wi = array(
                                'title' => $base_title,
                                'company' => $base_company,
                                'people' => array($child_post->post_title)
                            );
                            $winners_for_node[] = $wi;
                        }
                    }
                }

                // If no level-3 children, fallback to a single entry using parsed base title/company
                if (empty($winners_for_node)) {
                    $winners_for_node[] = array(
                        'title' => $base_title,
                        'company' => $base_company,
                        'people' => array()
                    );
                }

                foreach ($winners_for_node as $wi) {
                    $current_award['winners'][] = $wi;
                    $current_award['winner_ids'][] = $item->object_id;
                }
                $current_award['current_winner'] = $item->ID;
                $current_award['award_type'] = $is_winner ? 'WINNER' : 'HONOREE';
            }

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
                    'current_winner' => null
                );
            } elseif ($level === 3 && $current_award !== null) {
                // Handle level 3 items (presenters, winners, and nominees)
                $type_info = get_post_meta($item->ID, '_guest_type', true);
                
                // Check if this is a presenter
                if ($type_info === 'award-presenter') {
                    $current_award['presenters'][] = $item->post_title ?: $post_title;
                    $current_award['presenter_ids'][] = $item->object_id;
                }
                // If not a presenter or winner, it's a nominee
                else {
                    $nominee_info = array(
                        'title' => $item->post_title ?: $post_title,
                        'company' => '',
                        'people' => array()
                    );

                    // Check for company in the title
                    if (preg_match('/^(.*?)\s+by\s+(.*?)$/', $nominee_info['title'], $matches)) {
                        $nominee_info['title'] = trim($matches[1]);
                        $nominee_info['company'] = trim($matches[2]);
                    }

                    // Get level 4 items (company or person)
                    foreach ($menu_items as $child_item) {
                        if ($child_item->menu_item_parent == $item->ID) {
                            $child_post = get_post($child_item->object_id);
                            if ($child_post) {
                                // If this is a company (level 4), get its people (level 5)
                                if ($child_item->actual_post_type === 'resource') {
                                    $nominee_info['company'] = $child_post->post_title;
                                    // Get level 5 people
                                    foreach ($menu_items as $grandchild_item) {
                                        if ($grandchild_item->menu_item_parent == $child_item->ID) {
                                            $grandchild_post = get_post($grandchild_item->object_id);
                                            if ($grandchild_post) {
                                                $nominee_info['people'][] = $grandchild_post->post_title;
                                            }
                                        }
                                    }
                                } else {
                                    // If level 4 is a person, add them directly
                                    $nominee_info['people'][] = $child_post->post_title;
                                }
                            }
                        }
                    }

                    $current_award['nominees'][] = $nominee_info;
                }
            }
            
            // Get nesting level and create arrows
            $level = get_nesting_level($menu_items, $item->ID);
            $arrows = str_repeat('→ ', $level);
            
            // Set row class based on type
            $row_class = '';
            if ($item->actual_post_type === 'profile') {
                $row_class = 'profile-row';
            } elseif ($item->actual_post_type === 'resource') {
                $row_class = 'resource-row';
            } elseif (strpos($post_title, '@') !== false || strpos($item->post_title, '@') !== false) {
                $row_class = 'email-row';
            } elseif ($item->actual_post_type === 'event') {
                $row_class = 'event-row';
            }
            
            // Set text class based on nesting level
            $text_class = '';
            if ($level === 2) {
                $text_class = 'level-2';
            } elseif ($level === 3) {
                $text_class = 'level-3';
            } elseif ($level === 4) {
                $text_class = 'level-4';
            }

            if (!isset($_GET['view']) || $_GET['view'] !== 'summary') {
                echo '<tr class="' . esc_attr($row_class) . '">';
                echo '<td>' . esc_html($item->menu_order) . '</td>';
                echo '<td>' . esc_html($item->actual_post_type) . '</td>';
                echo '<td>' . esc_html($item->object_id) . '</td>';
                echo '<td>' . esc_html($item->menu_item_parent) . '</td>';
                echo '<td>' . ($type_info ? esc_html($type_info) : '') . '</td>';
                echo '<td>';
                if ($edit_link) {
                    echo '<a href="' . esc_url($edit_link) . '" class="award-link" target="_blank">' . 
                         '<span class="' . esc_attr($text_class) . ' ' . ($item->actual_post_type === 'event' ? 'event-title' : '') . '">' . 
                         $arrows . esc_html($post_title) . '</span>' . 
                         '</a>';
                } else {
                    echo '<span class="' . esc_attr($text_class) . ' ' . ($item->actual_post_type === 'event' ? 'event-title' : '') . '">' . 
                         $arrows . esc_html($post_title) . '</span>';
                }
                echo '</td>';
                echo '<td>';
                if ($item->post_title !== $post_title) {
                    echo esc_html($item->post_title);
                }
                echo '</td>';
                // Winner column: print classes for this menu item
                echo '<td>' . (is_array($classes) ? esc_html(implode(', ', $classes)) : '') . '</td>';
                echo '</tr>';
            }
        }
        
        // After the loop, make sure to add the last award if it exists
        if ($current_award !== null) {
            $awards[] = $current_award;
        }

        // Post-process: compute nominees text and resource URLs for awards added by this menu.
        // Concatenate all level 2/3/4 items following the winner until we return to level 1.
        if (!empty($results['menu_items'])) {
            $mi_list = $results['menu_items'];
            // Helper to get nesting level quickly
            $get_level = function($id) use ($menu_items) { return get_nesting_level($menu_items, $id); };
            for ($ai = $awards_menu_start; $ai < count($awards); $ai++) {
                if (empty($awards[$ai]['current_winner'])) { continue; }
                $winner_id = (int)$awards[$ai]['current_winner'];
                if (!isset($index_by_id[$winner_id])) { continue; }
                $startIdx = $index_by_id[$winner_id];

                // Helper: is j-th item a descendant of winner?
                $is_descendant = function($nodeId, $ancestorId) use ($parent_map) {
                    $guard = 0;
                    while (!empty($nodeId) && $guard < 20) {
                        if ((int)$nodeId === (int)$ancestorId) { return true; }
                        $nodeId = isset($parent_map[$nodeId]) ? (int)$parent_map[$nodeId] : 0;
                        $guard++;
                    }
                    return false;
                };

                // Advance to the first item AFTER the winner's entire subtree
                $firstNomIdx = $startIdx + 1;
                for (; $firstNomIdx < count($mi_list); $firstNomIdx++) {
                    $cand = $mi_list[$firstNomIdx];
                    if (!$is_descendant($cand->ID, $winner_id)) { break; }
                }

                // Build grouped nominees: level-2 groups separated by ';', with level-3/4 comma-separated
                $groups = array();            // rendered strings per group
                $groups_struct = array();     // structured groups for reuse
                $current_group = '';
                $current_group_items = array();
                $visited = array(); // prevent duplicate additions when traversing
                for ($j = $firstNomIdx; $j < count($mi_list); $j++) {
                    $itm = $mi_list[$j];
                    $lvl = $get_level($itm->ID);
                    // Stop when we return to level 1
                    if ($lvl === 1) { break; }
                    if ($lvl < 2 || $lvl > 4) { continue; }

                    // Build a readable piece for this item
                    $post = !empty($itm->object_id) ? get_post($itm->object_id) : null;
                    $title = $post ? $post->post_title : (isset($itm->post_title) ? $itm->post_title : '');
                    $piece = '';

                    if ($lvl === 2) {
                        // Close previous group
                        if ($current_group !== '') {
                            $groups[] = $current_group;
                            $groups_struct[] = array('label' => $current_group_label, 'items' => $current_group_items);
                        }

                        // Base label for level-2
                        if (preg_match('/^(.*?)\s+by\s+(.*?)$/i', (string)$title, $m)) {
                            $current_group_label = esc_html(trim($m[1])) . ' by ' . esc_html(trim($m[2]));
                        } else {
                            $current_group_label = esc_html(trim($title));
                        }
                        $current_group_items = array();
                        // Start group string with label and colon (even if no items may follow)
                        $current_group = $current_group_label; // keep label for now; add colon only when first item arrives
                        $added_colon = false;
                        continue;
                    }

                    // Level 3/4 aggregation into current_group with commas
                    if (isset($itm->actual_post_type) && $itm->actual_post_type === 'resource') {
                        // Company node: include any direct child profiles
                        $people = array();
                        $visited[$itm->ID] = true; // mark company as visited
                        foreach ($mi_list as $probe) {
                            if ((int)$probe->menu_item_parent === (int)$itm->ID && isset($probe->actual_post_type) && $probe->actual_post_type === 'profile') {
                                $pp = !empty($probe->object_id) ? get_post($probe->object_id) : null;
                                if ($pp) { $people[] = trim($pp->post_title); }
                                $visited[$probe->ID] = true; // mark direct profile children as visited
                            }
                        }
                        $piece = esc_html(trim($title));
                        if (!empty($people)) { $piece .= ': ' . esc_html(implode(', ', $people)); }
                    } else if (isset($itm->actual_post_type) && $itm->actual_post_type === 'profile') {
                        if (isset($visited[$itm->ID])) { continue; } // already captured under a resource
                        $piece = esc_html(trim($title));
                    } else {
                        // Generic: normalize "Title by Company" when present
                        if (preg_match('/^(.*?)\s+by\s+(.*?)$/i', (string)$title, $m)) {
                            $piece = esc_html(trim($m[1])) . ' by ' . esc_html(trim($m[2]));
                        } else {
                            $piece = esc_html(trim($title));
                        }
                    }

                    if ($piece !== '') {
                        if ($current_group === '') {
                            // If no current level-2 group yet, start one implicitly
                            $current_group_label = '';
                            $current_group = $piece;
                            $current_group_items = array($piece);
                        } else {
                            // Append with colon before the first item, then commas
                            if (!isset($added_colon) || $added_colon === false) {
                                $current_group .= ': ' . $piece;
                                $added_colon = true;
                            } else {
                                $current_group .= ', ' . $piece;
                            }
                            $current_group_items[] = $piece;
                        }
                    }
                }
                if ($current_group !== '') {
                    $groups[] = $current_group;
                    $groups_struct[] = array('label' => isset($current_group_label)?$current_group_label:'', 'items' => $current_group_items);
                }
                $awards[$ai]['nominees_text'] = implode(' ; ', $groups);
                $awards[$ai]['nominees_groups'] = $groups_struct;

                // Compute Resource URLs for the winner: collect all level-3 resource items directly under the winner
                $resource_urls = array();
                foreach ($mi_list as $probe) {
                    if ((int)$probe->menu_item_parent === (int)$winner_id && isset($probe->actual_post_type) && $probe->actual_post_type === 'resource') {
                        if (!empty($probe->object_id)) {
                            $ru = get_post_meta($probe->object_id, 'resource_url', true);
                            if (!empty($ru)) { $resource_urls[] = $ru; }
                        }
                    }
                }
                $awards[$ai]['resource_urls'] = $resource_urls;
            }
        }

        if (!isset($_GET['view']) || $_GET['view'] !== 'summary') {
            echo '</tbody></table>';
            echo '</div>';
        }
    }

    // Add this before the awards summary section
    if (isset($_GET['debug']) && current_user_can('manage_options')) {
        echo '<div class="card" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">';
        echo '<h2>Debug Data Structure</h2>';
        echo '<pre style="background: #f5f5f5; padding: 15px; overflow: auto; max-height: 500px;">';
        echo htmlspecialchars(json_encode($awards, JSON_PRETTY_PRINT));
        echo '</pre>';
        echo '</div>';
    }

    // Display awards summary
    if (!empty($awards)) {
        echo '<style>
            .award-link {
                color: var(--dark-blue, #183369);
                text-decoration: none;
            }
            .award-link:hover {
                text-decoration: underline;
            }
        </style>';
        echo '<div class="card" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">';
        echo '<h2>Awards Summary</h2>';
        echo '<table class="widefat" style="margin: 10px 0;">';
        echo '<thead><tr>';
        echo '<th style="width: 50px;">#</th>';
        echo '<th>Award</th>';
        if (isset($_GET['thumbnails']) && $_GET['thumbnails'] === 'presenters') {
            echo '<th>Thumbnail</th>';
        }
        echo '<th>Presenter(s)</th>';
        echo '<th>Type</th>';
        echo '<th>Winner(s)</th>';
        echo '<th>Nominees</th>';
        if (isset($_GET['event_menu']) && test_menu_pattern($_GET['event_menu'], 'polys')) {
            echo '<th>Blocks</th>';
        }
        echo '<th>Resource URL</th>';
        echo '<th>Video</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($awards as $index => $award) {
            if (!is_array($award)) {
                continue;
            }
            echo '<tr>';
            echo '<td>' . ($index + 1) . '</td>';
            echo '<td>';
            if (!empty($award['object_id'])) {
                echo '<a href="' . admin_url("post.php?action=edit&post=" . $award['object_id']) . '" target="_blank" class="award-link">' . 
                     esc_html($award['title'] ?? '') . '</a>';
            } else {
                echo esc_html($award['title'] ?? '');
            }
            echo '</td>';
            if (isset($_GET['thumbnails']) && $_GET['thumbnails'] === 'presenters') {
                echo '<td>';
                if (!empty($award['presenter_ids']) && is_array($award['presenter_ids'])) {
                    foreach ($award['presenter_ids'] as $presenter_id) {
                        $thumbnail = get_the_post_thumbnail_url($presenter_id, 'thumbnail');
                        if ($thumbnail) {
                            echo '<img src="' . esc_url($thumbnail) . '" alt="Presenter thumbnail" style="width: 50px; height: 50px; object-fit: cover; margin: 2px;">';
                        }
                    }
                }
                echo '</td>';
            }
            echo '<td>';
            if (!empty($award['presenters']) && is_array($award['presenters'])) {
                $presenter_links = array();
                foreach ($award['presenters'] as $i => $presenter) {
                    if (!empty($award['presenter_ids'][$i])) {
                        $presenter_links[] = '<a href="' . admin_url("post.php?action=edit&post=" . $award['presenter_ids'][$i]) . 
                                           '" target="_blank" class="award-link">' . esc_html($presenter) . '</a>';
                    } else {
                        $presenter_links[] = esc_html($presenter);
                    }
                }
                // Format presenters based on count
                if (count($presenter_links) === 1) {
                    echo $presenter_links[0];
                } else {
                    // For multiple presenters, format as "Presenter 1 and Presenter 2"
                    $last_presenter = array_pop($presenter_links);
                    echo implode(', ', $presenter_links) . ' and ' . $last_presenter;
                }
            }
            echo '</td>';
            echo '<td>' . esc_html($award['award_type'] ?? '') . '</td>';
            echo '<td>';
            // Winners column rendering with ' | ' separator and non-empty fallback
            $winners_html = '';
            if (!empty($award['winners'])) {
                $base_title = isset($award['winners'][0]['title']) ? $award['winners'][0]['title'] : '';

                $group_parts = array();
                foreach ($award['winners'] as $idx => $winner) {
                    $company = isset($winner['company']) ? trim($winner['company']) : '';
                    $people = isset($winner['people']) && is_array($winner['people']) ? $winner['people'] : array();

                    $people_escaped = array();
                    foreach ($people as $person_name) {
                        if ($person_name !== '') { $people_escaped[] = esc_html($person_name); }
                    }

                    $part = '';
                    if ($company !== '') {
                        $part .= ($idx === 0 ? 'by ' : '') . esc_html($company);
                        if (!empty($people_escaped)) { $part .= ': ' . implode(', ', $people_escaped); }
                    } else if (!empty($people_escaped)) {
                        $part .= implode(', ', $people_escaped);
                    }

                    if ($part !== '') { $group_parts[] = $part; }
                }

                if ($base_title !== '') {
                    $winners_html .= '<strong>' . esc_html($base_title) . '</strong>';
                    if (!empty($group_parts)) { $winners_html .= ' | '; }
                }
                if (!empty($group_parts)) {
                    $winners_html .= implode(' ; ', $group_parts);
                }
            }
            echo ($winners_html !== '') ? $winners_html : '&nbsp;';
            echo '</td>';

            // Nominees column (concatenate level 2/3/4 nominee structures)
            echo '<td class="nominees-data">';
            $nominees_html = '';
            // Prefer computed nominees_text if available
            if (!empty($award['nominees_text'])) {
                $nominees_html = esc_html($award['nominees_text']);
            } elseif (!empty($award['nominees']) && is_array($award['nominees'])) {
                $nom_parts = array();
                foreach ($award['nominees'] as $nom) {
                    $n_title = isset($nom['title']) ? trim((string)$nom['title']) : '';
                    $n_company = isset($nom['company']) ? trim((string)$nom['company']) : '';
                    $n_people = array();
                    if (!empty($nom['people']) && is_array($nom['people'])) {
                        foreach ($nom['people'] as $pn) {
                            $pn = trim((string)$pn);
                            if ($pn !== '') { $n_people[] = esc_html($pn); }
                        }
                    }

                    $piece = '';
                    if ($n_title !== '') { $piece .= esc_html($n_title); }
                    if ($n_company !== '') {
                        $piece .= ($piece !== '' ? ' ' : '');
                        $piece .= 'by ' . esc_html($n_company);
                    }
                    if (!empty($n_people)) {
                        $piece .= (!empty($n_company) ? ': ' : ' ');
                        $piece .= implode(', ', $n_people);
                    }
                    if ($piece !== '') { $nom_parts[] = $piece; }
                }
                if (!empty($nom_parts)) { $nominees_html = implode(' ; ', $nom_parts); }
            }
            echo ($nominees_html !== '') ? $nominees_html : '&nbsp;';
            echo '</td>';
            if (isset($_GET['event_menu']) && test_menu_pattern($_GET['event_menu'], 'polys')) {
                echo '<td class="trophy-data">';
                if (!empty($award['object_id'])) {
                    $trophy = get_post_meta($award['object_id'], 'looking_glass_embed_trophy', true);
                    $trophy_base = get_post_meta($award['object_id'], 'looking_glass_embed_trophy_base', true);
                    if ($trophy || $trophy_base) {
                        $output = array();
                        if ($trophy) {
                            $trophy_url = 'https://blocks.glass/thepolys/' . $trophy;
                            $output[] = '<a href="' . esc_url($trophy_url) . '" target="_blank" class="award-link">' . esc_html($trophy_url) . '</a>';
                        }
                        if ($trophy_base) {
                            $base_url = 'https://blocks.glass/thepolys/' . $trophy_base;
                            $output[] = '<a href="' . esc_url($base_url) . '" target="_blank" class="award-link">' . esc_html($base_url) . '</a>';
                        }
                        echo implode(' | ', $output);
                    } else {
                        echo '&nbsp;';
                    }
                }
                echo '</td>';
            }
            // Resource URL column (full clickable URL(s) if available)
            echo '<td class="resource-url">';
            if (!empty($award['resource_urls']) && is_array($award['resource_urls'])) {
                $links = array();
                foreach ($award['resource_urls'] as $ru) {
                    $links[] = '<a href="' . esc_url($ru) . '" target="_blank" class="award-link">' . esc_html($ru) . '</a>';
                }
                echo !empty($links) ? implode(' | ', $links) : '&nbsp;';
            } else {
                echo '&nbsp;';
            }
            echo '</td>';
            echo '<td class="video-data">';
            if (!empty($award['object_id'])) {
                $embed_video_url = get_post_meta($award['object_id'], 'embed_video_url', true);
                if ($embed_video_url) {
                    echo '<a href="' . esc_url($embed_video_url) . '" target="_blank" class="award-link">' . esc_html($embed_video_url) . '</a>';
                } else {
                    echo '&nbsp;';
                }
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';

        // Add narrative section
        echo '<div class="card" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">';
        echo '<h2>Award Narratives</h2>';
        echo '<div class="award-narratives">';
        foreach ($awards as $idx => $award) {
            $narrative = generate_award_narrative($award);
            if ($narrative) {
                echo '<div class="award-narrative" style="margin-bottom: 15px;">';
                // Build single-paragraph output with index prefix and inline nominees after a line break
                $content = esc_html(($idx + 1) . ' | ' . $narrative);
                // Inject nominees line using computed nominees_text (fallback to structured nominees)
                $nominees_line = '';
                if (!empty($award['nominees_text'])) {
                    $nominees_line = $award['nominees_text'];
                } elseif (!empty($award['nominees']) && is_array($award['nominees'])) {
                    $nom_parts = array();
                    foreach ($award['nominees'] as $nom) {
                        $n_title = isset($nom['title']) ? trim((string)$nom['title']) : '';
                        $n_company = isset($nom['company']) ? trim((string)$nom['company']) : '';
                        $n_people = array();
                        if (!empty($nom['people']) && is_array($nom['people'])) {
                            foreach ($nom['people'] as $pn) {
                                $pn = trim((string)$pn);
                                if ($pn !== '') { $n_people[] = $pn; }
                            }
                        }
                        $piece = '';
                        if ($n_title !== '') { $piece .= $n_title; }
                        if ($n_company !== '') { $piece .= ($piece !== '' ? ' ' : '') . 'by ' . $n_company; }
                        if (!empty($n_people)) { $piece .= (!empty($n_company) ? ': ' : ' ') . implode(', ', $n_people); }
                        if ($piece !== '') { $nom_parts[] = $piece; }
                    }
                    if (!empty($nom_parts)) { $nominees_line = implode(' ; ', $nom_parts); }
                }
                if ($nominees_line !== '') {
                    $content .= '<br><em>Nominees were:</em> ' . esc_html($nominees_line);
                }
                echo '<p>' . $content . '</p>';
                echo '</div>';
            }
        }
        echo '</div>';
        echo '</div>';
    }
} else {
    if (!isset($_GET['view']) || $_GET['view'] !== 'summary') {
        echo '<div class="wrap">';
        echo '<h1>Menu Analysis</h1>';
        echo '<p>To analyze a menu, add the event_menu parameter to the URL. For example:</p>';
        echo '<ul>';
        echo '<li><a href="?event_menu=polys3">Analyze Polys3 Menu</a></li>';
        echo '<li><a href="?event_menu=polys4">Analyze Polys4 Menu</a></li>';
        echo '<li><a href="?event_menu=polys5">Analyze Polys5 Menu</a></li>';
        echo '<li><a href="?event_menu=polys*">Analyze All Polys Menus</a></li>';
        echo '<li><a href="?event_menu=virtual-red-carpet-*">Analyze All Virtual Red Carpet Menus</a></li>';
        echo '</ul>';
        echo '</div>';
    }
}

if (!isset($_GET['view']) || $_GET['view'] !== 'summary') {
    echo '</div>'; // End wrap
}

// Centralized action dispatcher for audit actions
audit_dispatch_actions($_GET);

get_footer();