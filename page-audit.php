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

// Add the metadata
add_event_type_metadata();

// Initialize awards array
$awards = array();

// Display duplicate profiles
profile_appearances();

// Get all menus for the top list
global $wpdb;
$all_menus = $wpdb->get_results("
    SELECT t.*, tt.term_taxonomy_id
    FROM {$wpdb->terms} t
    JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
    WHERE tt.taxonomy = 'nav_menu'
    ORDER BY t.name ASC
");

// Only show full menu analysis if not in summary view
if (!isset($_GET['view']) || $_GET['view'] !== 'summary') {
    echo '<div class="wrap audit-page">';
    echo '<h1>Available Menus</h1>';
    echo '<p>';
    foreach ($all_menus as $menu) {
        echo '<a href="?event_menu=' . esc_attr($menu->slug) . '" class="menu-link">' . 
             esc_html($menu->name) . '</a>';
    }
    echo '</p>';
}

// Only run menu analysis if event_menu parameter is present
if (isset($_GET['event_menu'])) {
    // Get all menu slugs
    $menu_slug = $_GET['event_menu'];
    
    // Handle wildcard patterns
    if (strpos($menu_slug, '*') !== false) {
        $pattern = str_replace('*', '', $menu_slug) . '%';  // Remove * and add % at the end
        $menu_slugs = $wpdb->get_col($wpdb->prepare("
            SELECT t.slug
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'nav_menu'
            AND t.slug LIKE %s
            ORDER BY t.slug ASC
        ", $pattern));
        
        if (empty($menu_slugs)) {
            echo '<div class="notice notice-error"><p>No menus found matching pattern: ' . esc_html($menu_slug) . '</p></div>';
            get_footer();
            return;
        }
    } else {
        $menu_slugs = array($menu_slug);
    }

    if (!isset($_GET['view']) || $_GET['view'] !== 'summary') {
        echo '<h1>Menu Structures</h1>';
    }

    foreach ($menu_slugs as $slug) {
        // Get menu term by slug
        $menu_term = $wpdb->get_row($wpdb->prepare("
            SELECT t.*, tt.term_taxonomy_id
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'nav_menu'
            AND t.slug = %s
        ", $slug));

        if (!$menu_term) {
            echo '<div class="notice notice-error"><p>Menu not found: ' . esc_html($slug) . '</p></div>';
            continue;
        }

        $results = check_menu_relationships($menu_term->term_taxonomy_id);

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
        if (isset($_GET['event_menu']) && test_menu_pattern($_GET['event_menu'], 'polys')) {
            echo '<th>Blocks</th>';
        }
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
            if (!empty($award['winners'])) {
                $winner_parts = array();
                foreach ($award['winners'] as $winner) {
                    $winner_str = $winner['title'];
                    if (!empty($winner['company'])) {
                        $winner_str .= ' by ' . $winner['company'];
                    }
                    if (!empty($winner['people'])) {
                        $winner_str .= '; ' . implode(', ', $winner['people']);
                    }
                    $winner_parts[] = $winner_str;
                }
                $winners_text = implode('; ', $winner_parts);
            }
            echo '</td>';
            if (isset($_GET['event_menu']) && test_menu_pattern($_GET['event_menu'], 'polys')) {
                echo '<td class="trophy-data">';
                if (!empty($award['object_id'])) {
                    $trophy = get_post_meta($award['object_id'], 'looking_glass_embed_trophy', true);
                    $trophy_base = get_post_meta($award['object_id'], 'looking_glass_embed_trophy_base', true);
                    if ($trophy || $trophy_base) {
                        $output = array();
                        if ($trophy) {
                            $output[] = '<a href="https://blocks.glass/thepolys/' . esc_attr($trophy) . '" target="_blank" class="award-link">trophy</a>';
                        }
                        if ($trophy_base) {
                            $output[] = '<a href="https://blocks.glass/thepolys/' . esc_attr($trophy_base) . '" target="_blank" class="award-link">base</a>';
                        }
                        echo implode(' | ', $output);
                    } else {
                        echo 'No trophy data';
                    }
                }
                echo '</td>';
            }
            echo '<td class="video-data">';
            if (!empty($award['object_id'])) {
                $video_url = get_post_meta($award['object_id'], 'video_url', true);
                $embed_video_url = get_post_meta($award['object_id'], 'embed_video_url', true);
                
                $output = array();
                
                // Handle video URL
                if ($video_url) {
                    $output[] = '<a href="' . esc_url($video_url) . '" target="_blank" class="award-link">link</a>';
                } else {
                    $output[] = '<span class="missing">no link</span>';
                }
                
                // Handle embed video URL
                if ($embed_video_url) {
                    $output[] = '<a href="' . esc_url($embed_video_url) . '" target="_blank" class="award-link">embed</a>';
                } else {
                    $output[] = '<span class="missing">no embed</span>';
                }
                
                echo implode(' | ', $output);
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
        foreach ($awards as $award) {
            $narrative = generate_award_narrative($award);
            if ($narrative) {
                echo '<div class="award-narrative" style="margin-bottom: 15px;">';
                echo '<p>' . esc_html($narrative) . '</p>';
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

// Add this line before get_footer() to run the update
if (isset($_GET['update_videos']) && current_user_can('manage_options')) {
    update_award_videos();
}

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

// Add this line before get_footer() to run the search
if (isset($_GET['find_titles']) && current_user_can('manage_options')) {
    find_award_titles();
}

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

    // Format winners
    $winners_text = '';
    if (!empty($award['winners'])) {
        $winner_parts = array();
        foreach ($award['winners'] as $winner) {
            $winner_str = $winner['title'];
            if (!empty($winner['company'])) {
                $winner_str .= ' by ' . $winner['company'];
            }
            if (!empty($winner['people'])) {
                $winner_str .= '; ' . implode(', ', $winner['people']);
            }
            $winner_parts[] = $winner_str;
        }
        $winners_text = implode('; ', $winner_parts);
    }

    // Get award date from post meta if available
    $award_date = '';
    if (!empty($award['object_id'])) {
        $award_date = get_post_meta($award['object_id'], 'award_date', true);
        if ($award_date) {
            $award_date = date('F j, Y', strtotime($award_date));
        }
    }

    // Format nominees
    $nominees_text = '';
    if (!empty($award['nominees'])) {
        $nominee_parts = array();
        foreach ($award['nominees'] as $nominee) {
            $nominee_str = $nominee['title'];
            if (!empty($nominee['company'])) {
                $nominee_str .= ' by ' . $nominee['company'];
            }
            if (!empty($nominee['people'])) {
                $nominee_str .= '; ' . implode(', ', $nominee['people']);
            }
            $nominee_parts[] = $nominee_str;
        }
        $nominees_text = implode('; ', $nominee_parts);
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

        // Format winners
        $winners_text = '';
        if (!empty($award['winners'])) {
            $winner_parts = array();
            foreach ($award['winners'] as $winner) {
                $winner_str = $winner['title'];
                if (!empty($winner['company'])) {
                    $winner_str .= ' by ' . $winner['company'];
                }
                if (!empty($winner['people'])) {
                    $winner_str .= '; ' . implode(', ', $winner['people']);
                }
                $winner_parts[] = $winner_str;
            }
            $winners_text = implode('; ', $winner_parts);
        }

        // Format nominees
        $nominees_text = '';
        if (!empty($award['nominees'])) {
            $nominee_parts = array();
            foreach ($award['nominees'] as $nominee) {
                $nominee_str = $nominee['title'];
                if (!empty($nominee['company'])) {
                    $nominee_str .= ' by ' . $nominee['company'];
                }
                if (!empty($nominee['people'])) {
                    $nominee_str .= '; ' . implode(', ', $nominee['people']);
                }
                $nominee_parts[] = $nominee_str;
            }
            $nominees_text = implode('; ', $nominee_parts);
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

// Add this line before get_footer() to run the update
if (isset($_GET['update_narratives']) && current_user_can('manage_options')) {
    update_award_narratives();
}

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
                'items' => array(), // Store all items in order with their levels
                'object_id' => $item->object_id
            );
        } elseif ($current_award !== null) {
            $item_info = array(
                'level' => $level,
                'title' => $item->post_title ?: $post_title,
                'type' => $item->actual_post_type,
                'is_winner' => $is_winner,
                'is_honoree' => $is_honoree,
                'is_presenter' => get_post_meta($item->ID, '_guest_type', true) === 'award-presenter',
                'company' => '',
                'people' => array()
            );

            // Check for company in the title
            if (preg_match('/^(.*?)\s+by\s+(.*?)$/', $item_info['title'], $matches)) {
                $item_info['title'] = trim($matches[1]);
                $item_info['company'] = trim($matches[2]);
            }

            // Get level 4 items (company or person)
            foreach ($menu_items as $child_item) {
                if ($child_item->menu_item_parent == $item->ID) {
                    $child_post = get_post($child_item->object_id);
                    if ($child_post) {
                        // If this is a company (level 4), get its people (level 5)
                        if ($child_item->actual_post_type === 'resource') {
                            $item_info['company'] = $child_post->post_title;
                            // Get level 5 people
                            foreach ($menu_items as $grandchild_item) {
                                if ($grandchild_item->menu_item_parent == $child_item->ID) {
                                    $grandchild_post = get_post($grandchild_item->object_id);
                                    if ($grandchild_post) {
                                        $item_info['people'][] = $grandchild_post->post_title;
                                    }
                                }
                            }
                        } else {
                            // If level 4 is a person, add them directly
                            $item_info['people'][] = $child_post->post_title;
                        }
                    }
                }
            }

            $current_award['items'][] = $item_info;
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

        // Build the narrative by processing items in order
        $narrative = '';
        if ($year) {
            $narrative .= "The {$year} ";
        }
        $narrative .= $award_name;

        $presenters = array();
        $winners = array();
        $nominees = array();

        // Process all items in order
        foreach ($award['items'] as $item) {
            if ($item['is_presenter']) {
                $presenters[] = $item['title'];
            } elseif ($item['is_winner'] || $item['is_honoree']) {
                $winner_str = $item['title'];
                if (!empty($item['company'])) {
                    $winner_str .= ' by ' . $item['company'];
                }
                if (!empty($item['people'])) {
                    $winner_str .= '; ' . implode(', ', $item['people']);
                }
                $winners[] = $winner_str;
            } else {
                $nominee_str = $item['title'];
                if (!empty($item['company'])) {
                    $nominee_str .= ' by ' . $item['company'];
                }
                if (!empty($item['people'])) {
                    $nominee_str .= '; ' . implode(', ', $item['people']);
                }
                $nominees[] = $nominee_str;
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
        if (!empty($winners)) {
            $narrative .= " to " . implode('; ', $winners);
        }

        // Add nominees
        if (!empty($nominees)) {
            $narrative .= ". Nominees were: " . implode('; ', $nominees);
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

// Update the preview link to include the event_menu parameter
if (isset($_GET['preview_narratives']) && current_user_can('manage_options')) {
    preview_award_narratives();
}

get_footer(); 