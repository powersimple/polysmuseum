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
require_once get_template_directory() . '/functions/functions-exhibits.php';

// Prevent caching so menu changes are reflected immediately
if (!headers_sent()) {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}
// Also tell WordPress caching plugins to skip this page
if (!defined('DONOTCACHEPAGE')) {
    define('DONOTCACHEPAGE', true);
}

// Clear WordPress nav menu cache to ensure fresh data
wp_cache_delete('nav_menu_items', 'nav_menu_items');
if (function_exists('wp_cache_flush_group')) {
    wp_cache_flush_group('nav_menu_items');
}
// Clear all nav menu related caches
$all_menus = wp_get_nav_menus();
foreach ($all_menus as $menu) {
    wp_cache_delete($menu->term_id, 'nav_menu_items');
    wp_cache_delete('nav_menu_items-' . $menu->term_id, 'nav_menu_items');
}
// Force WordPress to refresh post meta cache
wp_cache_flush();

get_header();

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

// Exhibits page: force summary mode and suppress debug output
$_GET['view'] = 'summary';
if (isset($_GET['debug'])) { unset($_GET['debug']); }

// Optional filter: show only a specific section via ?only=summary|narrative|categories|awards
$only = '';
if (isset($_GET['only'])) {
    $only = strtolower(sanitize_text_field((string)$_GET['only']));
}
$show_summaries = ($only === '' || $only === 'summary');
$show_narrative = ($only === '' || $only === 'narrative');
$show_categories = ($only === '' || $only === 'categories');
$show_awards = ($only === '' || in_array($only, array('awards','awardcards','exhibits'), true));
$show_announcements = ($only === 'announcements');

// Initialize Awards array
$awards = array();
// Aggregates for grouped Hosts / Ambassadors / Red Carpet Hosts
$hosts_group = array();
$amb_group = array();
$red_group = array();
// Year-mapped role collections (Ceremony Host, Ambassador, Red Carpet Host)
$ceremony_by_year = array();
$amb_by_year = array();
$red_by_year = array();

// Exhibits: do not print duplicate profiles table
// profile_appearances();

// Get all menus for the top list
$all_menus = get_all_nav_menus();

// Build a Hosts Summary table by scanning all menus matching polys*
// This is independent of award categories and honorees.
$hosts_summary = array();
if (is_array($all_menus) && !empty($all_menus)) {
    foreach ($all_menus as $menu_obj) {
        if (!isset($menu_obj->slug)) { continue; }
        $slug = (string)$menu_obj->slug;
        if (strpos($slug, 'polys') !== 0) { continue; }

        $results = get_menu_items_for_slug($slug);
        if (!is_array($results) || !isset($results['menu_items']) || !is_array($results['menu_items'])) { continue; }

        $mi_list = $results['menu_items'];
        $menu_items_map = array();
        foreach ($mi_list as $itm) { $menu_items_map[$itm->ID] = $itm; }

        $map_year = function($cls){
            $cls = is_array($cls) ? $cls : array();
            $cset = array(); foreach ($cls as $c) { $cset[strtolower($c)] = true; }
            if (isset($cset['1st']) || isset($cset['first'])) { return 2020; }
            if (isset($cset['2nd']) || isset($cset['second'])) { return 2021; }
            if (isset($cset['3rd']) || isset($cset['third'])) { return 2022; }
            if (isset($cset['4th']) || isset($cset['fourth'])) { return 2023; }
            if (isset($cset['5th']) || isset($cset['fifth'])) { return 2024; }
            foreach ($cset as $k => $_) { if (preg_match('/^year[-_]?20(2[0-4]|20)$/', $k)) { $yy = substr($k, -4); return intval($yy); } }
            return 0;
        };

        foreach ($mi_list as $item) {
            if (!isset($item->actual_post_type) || $item->actual_post_type !== 'event') { continue; }
            $level = get_nesting_level($menu_items_map, $item->ID);
            if ($level !== 2) { continue; }

            $classes = get_post_meta($item->ID, '_menu_item_classes', true);
            $mi_classes = is_array($classes) ? array_map('strtolower', $classes) : array();
            $classes_str = implode(' ', $mi_classes);
            $has_cls = function($c) use ($mi_classes, $classes_str){ return in_array(strtolower($c), $mi_classes, true) || strpos($classes_str, strtolower($c)) !== false; };

            $event_type = strtolower(trim((string)get_post_meta($item->ID, '_event_type', true)));
            $year = $map_year($mi_classes);
            if ($year < 2020 || $year > 2024) { continue; }

            $role = '';
            if (strpos($event_type, 'keynote') !== false && ($has_cls('keynote') || $has_cls('keynot'))) {
                $role = 'Ceremony Host';
            } elseif (strpos($event_type, 'keynote') !== false && $has_cls('ambassador')) {
                $role = 'Ambassador';
            } elseif (strpos($event_type, 'red carpet interview') !== false && $has_cls('red-carpet')) {
                $role = 'Red Carpet Host';
            } else {
                continue;
            }

            $title = '';
            if (!empty($item->object_id)) {
                $p = get_post($item->object_id);
                if ($p) { $title = $p->post_title; }
            }
            if ($title === '' && isset($item->post_title)) { $title = (string)$item->post_title; }

            $thumb = !empty($item->object_id) ? (get_the_post_thumbnail_url(intval($item->object_id), 'full') ?: '') : '';
            $hosts_summary[] = array(
                'role' => $role,
                'year' => $year,
                'menu' => isset($results['menu']->name) ? (string)$results['menu']->name : $slug,
                'title' => $title,
                'object_id' => !empty($item->object_id) ? intval($item->object_id) : 0,
                'thumb' => $thumb,
            );
        }
    }
}

// Render Hosts Summary table
if (!empty($hosts_summary)) {
    // Sort by role then year asc
    usort($hosts_summary, function($a,$b){
        if ($a['role'] === $b['role']) { return $a['year'] <=> $b['year']; }
        return strcmp($a['role'], $b['role']);
    });
    echo '<div class="exhibits-wrapper">';
    echo '<h2>Hosts Summary</h2>';
    echo '<table class="widefat">';
    echo '<thead><tr><th>Role</th><th>Year</th><th>Menu</th><th>Title</th><th>Post ID</th><th>Featured Image</th></tr></thead><tbody>';
    foreach ($hosts_summary as $row) {
        echo '<tr>'
           . '<td>' . esc_html($row['role']) . '</td>'
           . '<td>' . esc_html((string)$row['year']) . '</td>'
           . '<td>' . esc_html($row['menu']) . '</td>'
           . '<td>' . esc_html($row['title']) . '</td>'
           . '<td>' . esc_html((string)$row['object_id']) . '</td>'
           . '<td>' . ($row['thumb'] ? ('<a href="' . esc_url($row['thumb']) . '" target="_blank">view</a>') : '') . '</td>'
           . '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
}

// Only show full menu analysis if not in summary view
if (!isset($_GET['view']) || $_GET['view'] !== 'summary') {
    echo '<h1>Menu Structures</h1>';
}

// Only run menu analysis if event_menu parameter is present
if (isset($_GET['event_menu'])) {
    // Get all menu slugs
    $menu_slug = $_GET['event_menu'];
    
    // Resolve wildcard patterns (strict Polys filtering for polys*)
    $menu_slugs = exhibits_resolve_menu_slugs($menu_slug, function_exists('resolve_menu_slugs') ? resolve_menu_slugs($menu_slug) : array($menu_slug));
    if (empty($menu_slugs)) {
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
        $current_event_logo_url = '';
        $current_tile_bg_url = '';
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
                // Capture event-level logo once and reuse across awards in this menu
                if (!empty($item->object_id) && $current_event_logo_url === '') {
                    $elogom = get_post_meta($item->object_id, 'event_logo', true);
                    $elogo = '';
                    if (is_array($elogom) && !empty($elogom)) {
                        $firstl = $elogom[0];
                        $lid = is_array($firstl) && isset($firstl['ID']) ? intval($firstl['ID']) : intval($firstl);
                        if ($lid) { $elogo = wp_get_attachment_image_url($lid, 'full'); }
                    } elseif (is_numeric($elogom)) {
                        $elogo = wp_get_attachment_image_url(intval($elogom), 'full');
                    }
                    if ($elogo) { $current_event_logo_url = $elogo; }
                }
                // Capture event-level tile background once and reuse across awards
                if (!empty($item->object_id) && $current_tile_bg_url === '') {
                    $tbgm = get_post_meta($item->object_id, 'tile_bg', true);
                    $tbg = '';
                    if (is_array($tbgm) && !empty($tbgm)) {
                        $firstt = $tbgm[0];
                        $tid = is_array($firstt) && isset($firstt['ID']) ? intval($firstt['ID']) : intval($firstt);
                        if ($tid) { $tbg = wp_get_attachment_image_url($tid, 'full'); }
                    } elseif (is_numeric($tbgm)) {
                        $tbg = wp_get_attachment_image_url(intval($tbgm), 'full');
                    }
                    if ($tbg) { $current_tile_bg_url = $tbg; }
                }
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
                        'current_winner' => null,
                        'presenter_image_url' => '',
                        'acceptance_image_url' => '',
                        'event_logo_url' => $current_event_logo_url,
                        'tile_bg_url' => $current_tile_bg_url
                    );
                    // Preload presenter_image and acceptance_image from event meta
                    if (!empty($current_award['object_id'])) {
                        $pmeta = get_post_meta($current_award['object_id'], 'presenter_image', true);
                        $purl = '';
                        if (is_array($pmeta) && !empty($pmeta)) {
                            $first = $pmeta[0];
                            $att_id = is_array($first) && isset($first['ID']) ? intval($first['ID']) : intval($first);
                            if ($att_id) { $purl = wp_get_attachment_image_url($att_id, 'full'); }
                        } elseif (is_numeric($pmeta)) {
                            $purl = wp_get_attachment_image_url(intval($pmeta), 'full');
                        }
                        if ($purl) { $current_award['presenter_image_url'] = $purl; }

                        $ameta = get_post_meta($current_award['object_id'], 'acceptance_image', true);
                        $aurl = '';
                        if (is_array($ameta) && !empty($ameta)) {
                            $firsta = $ameta[0];
                            $aid = is_array($firsta) && isset($firsta['ID']) ? intval($firsta['ID']) : intval($firsta);
                            if ($aid) { $aurl = wp_get_attachment_image_url($aid, 'full'); }
                        } elseif (is_numeric($ameta)) {
                            $aurl = wp_get_attachment_image_url(intval($ameta), 'full');
                        }
                        if ($aurl) { $current_award['acceptance_image_url'] = $aurl; }

                        // Preload event_logo (image_advanced)
                        $elogom = get_post_meta($current_award['object_id'], 'event_logo', true);
                        $elogo = '';
                        if (is_array($elogom) && !empty($elogom)) {
                            $firstl = $elogom[0];
                            $lid = is_array($firstl) && isset($firstl['ID']) ? intval($firstl['ID']) : intval($firstl);
                            if ($lid) { $elogo = wp_get_attachment_image_url($lid, 'full'); }
                        } elseif (is_numeric($elogom)) {
                            $elogo = wp_get_attachment_image_url(intval($elogom), 'full');
                        }
                        if ($elogo) { $current_award['event_logo_url'] = $elogo; }
                        elseif ($current_event_logo_url !== '') { $current_award['event_logo_url'] = $current_event_logo_url; }
                        // Preload tile background for this event object if any
                        $tbgm = get_post_meta($current_award['object_id'], 'tile_bg', true);
                        $tbg = '';
                        if (is_array($tbgm) && !empty($tbgm)) {
                            $firstt = $tbgm[0];
                            $tid = is_array($firstt) && isset($firstt['ID']) ? intval($firstt['ID']) : intval($firstt);
                            if ($tid) { $tbg = wp_get_attachment_image_url($tid, 'full'); }
                        } elseif (is_numeric($tbgm)) {
                            $tbg = wp_get_attachment_image_url(intval($tbgm), 'full');
                        }
                        if ($tbg) { $current_award['tile_bg_url'] = $tbg; }
                        elseif ($current_tile_bg_url !== '') { $current_award['tile_bg_url'] = $current_tile_bg_url; }
                    }
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
            // Collect level-2 event cards for Hosts/Keynotes/Ambassadors/Red Carpet using menu classes and event-type meta
            if ($item->actual_post_type === 'event' && $level === 2) {
                $mi_classes = is_array($classes) ? array_map('strtolower', $classes) : array();
                $classes_str = implode(' ', $mi_classes);
                $et_mi = strtolower(trim((string)get_post_meta($item->ID, '_event_type', true)));
                // Prefer menu-item meta per request; event post meta may not exist
                $contains = function($hay, $needle){ return $hay !== '' && strpos($hay, $needle) !== false; };
                $has_cls = function($c) use ($mi_classes, $classes_str){ return in_array(strtolower($c), $mi_classes, true) || strpos($classes_str, strtolower($c)) !== false; };
                $is_keynote = $has_cls('keynote') || $contains($et_mi, 'keynote');
                $is_host = $has_cls('host') || $is_keynote; // treat keynote as host-like per spec
                $is_amb = $has_cls('ambassador') || $contains($et_mi, 'ambassador');
                $is_redcarpet = $has_cls('red-carpet') || ($has_cls('red') && $has_cls('carpet')) || $contains($et_mi, 'red carpet');
                if ($is_host || $is_amb || $is_redcarpet) {
                    $img = '';
                    if (!empty($item->object_id)) {
                        // Preferred: event featured image
                        $img = get_the_post_thumbnail_url(intval($item->object_id), 'full') ?: '';
                        // Fallbacks: event_logo, presenter_image
                        if ($img === '') {
                            $elogom = get_post_meta($item->object_id, 'event_logo', true);
                            if (is_array($elogom) && !empty($elogom)) {
                                $firstl = $elogom[0];
                                $lid = is_array($firstl) && isset($firstl['ID']) ? intval($firstl['ID']) : intval($firstl);
                                if ($lid) { $img = wp_get_attachment_image_url($lid, 'full') ?: $img; }
                            } elseif (is_numeric($elogom)) {
                                $img = wp_get_attachment_image_url(intval($elogom), 'full') ?: $img;
                            }
                        }
                        if ($img === '') {
                            $pmeta = get_post_meta($item->object_id, 'presenter_image', true);
                            if (is_array($pmeta) && !empty($pmeta)) {
                                $first = $pmeta[0];
                                $att_id = is_array($first) && isset($first['ID']) ? intval($first['ID']) : intval($first);
                                if ($att_id) { $img = wp_get_attachment_image_url($att_id, 'full') ?: $img; }
                            } elseif (is_numeric($pmeta)) {
                                $img = wp_get_attachment_image_url(intval($pmeta), 'full') ?: $img;
                            }
                        }
                    }
                    $title_txt = $post_title !== '' ? $post_title : $item->post_title;
                    $entry = array('title' => $title_txt, 'img' => $img);
                    if ($is_host) { $hosts_group[] = $entry; }
                    if ($is_amb) { $amb_group[] = $entry; }
                    if ($is_redcarpet) { $red_group[] = $entry; }
                }

                // Strict role/year mapping based on event type and specific classes
                $map_year = function($cls) {
                    $cls = is_array($cls) ? $cls : array();
                    $cset = array(); foreach ($cls as $c) { $cset[strtolower($c)] = true; }
                    // Accept both words and ordinals
                    if (isset($cset['1st']) || isset($cset['first'])) { return 2020; }
                    if (isset($cset['2nd']) || isset($cset['second'])) { return 2021; }
                    if (isset($cset['3rd']) || isset($cset['third'])) { return 2022; }
                    if (isset($cset['4th']) || isset($cset['fourth'])) { return 2023; }
                    if (isset($cset['5th']) || isset($cset['fifth'])) { return 2024; }
                    // Also accept year-* directly if present
                    foreach ($cset as $k => $_) {
                        if (preg_match('/^year[-_]?20(2[0-4]|20)$/', $k, $m)) {
                            $yy = substr($k, -4);
                            if (is_numeric($yy)) { return intval($yy); }
                        }
                    }
                    return 0;
                };
                $year_for_item = $map_year($mi_classes);

                // Ceremony Host: event type is keynote AND class has keynote (accept typo 'keynot')
                $class_has_keynot = $has_cls('keynote') || $has_cls('keynot');
                if ($contains($et_mi, 'keynote') && $class_has_keynot) {
                    if ($year_for_item > 0) {
                        $ceremony_by_year[$year_for_item] = array(
                            'title' => $post_title !== '' ? $post_title : $item->post_title,
                            'img' => (!empty($item->object_id) ? (get_the_post_thumbnail_url(intval($item->object_id), 'full') ?: '') : ''),
                        );
                    }
                }
                // Ambassador: event type is keynote AND class has ambassador
                if ($contains($et_mi, 'keynote') && $has_cls('ambassador')) {
                    if ($year_for_item > 0) {
                        $amb_by_year[$year_for_item] = array(
                            'title' => $post_title !== '' ? $post_title : $item->post_title,
                            'img' => (!empty($item->object_id) ? (get_the_post_thumbnail_url(intval($item->object_id), 'full') ?: '') : ''),
                        );
                    }
                }
                // Red Carpet Host: event type is red carpet interview AND class has red-carpet
                if ($contains($et_mi, 'red carpet interview') && $has_cls('red-carpet')) {
                    if ($year_for_item > 0) {
                        $red_by_year[$year_for_item] = array(
                            'title' => $post_title !== '' ? $post_title : $item->post_title,
                            'img' => (!empty($item->object_id) ? (get_the_post_thumbnail_url(intval($item->object_id), 'full') ?: '') : ''),
                        );
                    }
                }
            }
            
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
                    'current_winner' => null,
                    'presenter_image_url' => '',
                    'acceptance_image_url' => '',
                    'event_logo_url' => $current_event_logo_url,
                    'tile_bg_url' => $current_tile_bg_url
                );
                // Preload images for this award as well
                if (!empty($current_award['object_id'])) {
                    $pmeta = get_post_meta($current_award['object_id'], 'presenter_image', true);
                    $purl = '';
                    if (is_array($pmeta) && !empty($pmeta)) {
                        $first = $pmeta[0];
                        $att_id = is_array($first) && isset($first['ID']) ? intval($first['ID']) : intval($first);
                        if ($att_id) { $purl = wp_get_attachment_image_url($att_id, 'full'); }
                    } elseif (is_numeric($pmeta)) {
                        $purl = wp_get_attachment_image_url(intval($pmeta), 'full');
                    }
                    if ($purl) { $current_award['presenter_image_url'] = $purl; }

                    $ameta = get_post_meta($current_award['object_id'], 'acceptance_image', true);
                    $aurl = '';
                    if (is_array($ameta) && !empty($ameta)) {
                        $firsta = $ameta[0];
                        $aid = is_array($firsta) && isset($firsta['ID']) ? intval($firsta['ID']) : intval($firsta);
                        if ($aid) { $aurl = wp_get_attachment_image_url($aid, 'full'); }
                    } elseif (is_numeric($ameta)) {
                        $aurl = wp_get_attachment_image_url(intval($ameta), 'full');
                    }
                    if ($aurl) { $current_award['acceptance_image_url'] = $aurl; }

                    $elogom = get_post_meta($current_award['object_id'], 'event_logo', true);
                    $elogo = '';
                    if (is_array($elogom) && !empty($elogom)) {
                        $firstl = $elogom[0];
                        $lid = is_array($firstl) && isset($firstl['ID']) ? intval($firstl['ID']) : intval($firstl);
                        if ($lid) { $elogo = wp_get_attachment_image_url($lid, 'full'); }
                    } elseif (is_numeric($elogom)) {
                        $elogo = wp_get_attachment_image_url(intval($elogom), 'full');
                    }
                    if ($elogo) { $current_award['event_logo_url'] = $elogo; }
                }
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
                        'id' => intval($item->object_id),
                        'title' => $item->post_title ?: $post_title,
                        'company' => '',
                        'people' => array(),
                        'thumb_url' => ''
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

                    // Precompute nominee thumbnail (150x150) when possible
                    if (!empty($nominee_info['id'])) {
                        $turl = get_the_post_thumbnail_url(intval($nominee_info['id']), 'thumbnail');
                        if ($turl) { $nominee_info['thumb_url'] = $turl; }
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
                    if ($lvl < 2 || $lvl > 5) { continue; }

                    // Build a readable piece for this item
                    $post = !empty($itm->object_id) ? get_post($itm->object_id) : null;
                    $title = !empty($itm->title) ? $itm->title : ($post ? $post->post_title : (isset($itm->post_title) ? $itm->post_title : ''));
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

                    // Wrap presenter items in a span
                    if ($piece !== '') {
                        $mc = get_post_meta($itm->ID, '_menu_item_classes', true);
                        if ((is_array($mc) && in_array('presenter', $mc)) || (is_string($mc) && strpos($mc, 'presenter') !== false)) {
                            $piece = '<span class="presenter">' . $piece . '</span>';
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
    }

    // Add this before the awards summary section
    if (isset($_GET['debug']) && current_user_can('manage_options')) {
        // Removed debug data dump
    }

    // If only awards are requested, render Award Exhibits immediately and skip other sections
    if (in_array($only, array('awards','awardcards','exhibits'), true)) {
        if (current_user_can('manage_options') && empty($awards)) {
            echo '<div class="notice notice-warning"><p>Only=awards active but no awards were assembled. Check event_menu filter.</p></div>';
        }
        // Inline minimal CSS for the corner brand (avoids requiring a build)
        echo '<style>\n'
           . '.exhibit-content{position:relative}.ex-corner-brand{position:absolute;left:15px;top:10px;width:200px;z-index:4}.ex-corner-brand img{display:block;width:100%;height:auto;object-fit:contain}\n'
           . '</style>';
        echo '<div class="exhibits-wrapper">';
        echo '<h2>Award Exhibits</h2>';
        echo '<div class="exhibits-grid">';
        foreach ($awards as $idx => $award) { echo exhibits_render_award_tile($award, $idx); }
        echo '</div>';
        echo '</div>';
    }

    // ── Announcements view: one 1920×1080 card per award category ──────
    // Renders ALL nominees (Level 3) horizontally — no winner filtering.
    if ($show_announcements && !empty($awards)) {
        echo '<div class="ex-announce-cards">';
        foreach ($awards as $award) {
            if (!is_array($award)) { continue; }

            // Collect all nominees (Level 3 items already parsed by menu walker)
            $nominees = array();
            if (!empty($award['nominees']) && is_array($award['nominees'])) {
                foreach ($award['nominees'] as $nom) {
                    if (!empty($nom['id']) || !empty($nom['title'])) {
                        $nominees[] = $nom;
                    }
                }
            }
            // Skip categories with zero nominees
            if (empty($nominees)) { continue; }

            // Background image
            $bg_url = !empty($award['tile_bg_url']) ? (string)$award['tile_bg_url'] : '';
            $bg_style = $bg_url !== '' ? 'background-image:url(' . esc_url($bg_url) . ')' : '';

            // Event logo
            $logo_url = !empty($award['event_logo_url']) ? (string)$award['event_logo_url'] : '';

            // Category title
            $cat_title = isset($award['title']) ? (string)$award['title'] : '';

            // Row split: N<=5 → 1 row; N>5 → 2 rows, row1=ceil(N/2) capped at 5
            $n = count($nominees);
            if ($n <= 5) {
                $rows = array(array_slice($nominees, 0));
            } else {
                $row1_count = min(5, intval(ceil($n / 2)));
                $rows = array(
                    array_slice($nominees, 0, $row1_count),
                    array_slice($nominees, $row1_count),
                );
            }

            echo '<div class="ex-announce-card"' . ($bg_style !== '' ? ' style="' . $bg_style . '"' : '') . '>';
            echo '<div class="ex-announce-safe">';

            // Logo top-left (absolute positioned via CSS)
            if ($logo_url !== '') {
                echo '<img class="ex-announce-logo" src="' . esc_url($logo_url) . '" alt="">';
            }
            // Category title centered
            echo '<div class="ex-announce-category">' . esc_html($cat_title) . '</div>';

            // Nominee rows (each row centers independently)
            echo '<div class="ex-announce-grid">';
            foreach ($rows as $row_nominees) {
                echo '<div class="ex-announce-row">';
                foreach ($row_nominees as $nom) {
                    // Featured image: Level 3 nominee post thumbnail ONLY
                    $nom_img = '';
                    if (!empty($nom['id'])) {
                        $nom_img = get_the_post_thumbnail_url(intval($nom['id']), 'full') ?: '';
                    }

                    // Nominee title (Level 3)
                    $nom_title = isset($nom['title']) ? trim((string)$nom['title']) : '';

                    // Level 4 credits: company + people (no Level 5)
                    $nom_credit = '';
                    $company = isset($nom['company']) ? trim((string)$nom['company']) : '';
                    $people = array();
                    if (!empty($nom['people']) && is_array($nom['people'])) {
                        foreach ($nom['people'] as $pn) {
                            $pn = trim((string)$pn);
                            if ($pn !== '') { $people[] = esc_html($pn); }
                        }
                    }
                    if ($company !== '') {
                        $nom_credit .= esc_html($company);
                        if (!empty($people)) { $nom_credit .= ': ' . implode(', ', $people); }
                    } elseif (!empty($people)) {
                        $nom_credit .= implode(', ', $people);
                    }

                    echo '<div class="ex-announce-tile">';
                    // Title ABOVE laurel (Level 3, Agency FB, yellow)
                    if ($nom_title !== '') {
                        echo '<div class="ex-announce-title">' . esc_html($nom_title) . '</div>';
                    }
                    // Single laurel wrap with glow (reuses Exhibits technique)
                    echo '<div class="ex-announce-laurel-wrap">';
                    if ($nom_img !== '') {
                        echo '<img class="ex-announce-hero-img" src="' . esc_url($nom_img) . '" alt="">';
                    }
                    echo '</div>';
                    // Credits BELOW laurel (Level 4, Raleway, white)
                    if ($nom_credit !== '') {
                        echo '<div class="ex-announce-credits">' . $nom_credit . '</div>';
                    }
                    echo '</div>'; // .ex-announce-tile
                }
                echo '</div>'; // .ex-announce-row
            }
            echo '</div>'; // .ex-announce-grid

            echo '</div>'; // .ex-announce-safe
            echo '</div>'; // .ex-announce-card
        }
        echo '</div>'; // .ex-announce-cards
    }

    // Display awards summary
    if (!empty($awards)) {
        if ($show_summaries) {
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
                    $company = isset($winner['company']) ? trim((string)$winner['company']) : '';
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
            // Debug columns: presenter and acceptance image URLs
            echo '<td class="presenter-image-url">';
            if (!empty($award['presenter_image_url'])) {
                echo '<a href="' . esc_url($award['presenter_image_url']) . '" target="_blank" class="award-link">presenter_image</a>';
            } else { echo '&nbsp;'; }
            echo '</td>';
            echo '<td class="acceptance-image-url">';
            if (!empty($award['acceptance_image_url'])) {
                echo '<a href="' . esc_url($award['acceptance_image_url']) . '" target="_blank" class="award-link">acceptance_image</a>';
            } else { echo '&nbsp;'; }
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
        }


        // Add narrative section
        if ($show_narrative) {
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

        

            // Grouped Award Categories AFTER initial award cards
            if ($show_categories) {
            if (!empty($awards)) {
                // Build code mappings and group awards by code/year
                $category_labels = array(
                    'XOTY' => 'Experience of the Year',
                    'EDOTY' => 'Education Experience of the Year',
                    'EEOTY' => 'Entertainment Experience of the Year',
                    'DOTY' => 'Developer of the Year',
                    'IOTY' => 'Innovator of the Year',
                    'GOTY' => 'Game of the Year',
                    'Lifetime' => 'Lifetime Achievement Award',
                    'Ombudsperson' => 'Ombudsperson of the Year',
                    'Community' => 'Community Honoree'
                );
                // Exclusive detector: EDOTY/EEOTY first; XOTY only when plain 'Experience of the Year' without 'Education' or 'Entertainment'
                $norm_code2 = function($title) {
                    $t = (string)$title;
                    if (stripos($t, 'Education Experience of the Year') !== false) { return 'EDOTY'; }
                    if (stripos($t, 'Entertainment Experience of the Year') !== false) { return 'EEOTY'; }
                    if (stripos($t, 'Game of the Year') !== false) { return 'GOTY'; }
                    if (stripos($t, 'Developer of the Year') !== false) { return 'DOTY'; }
                    if (stripos($t, 'Innovator of the Year') !== false || stripos($t, 'Innovation of the Year') !== false) { return 'IOTY'; }
                    if (stripos($t, 'Lifetime Achievement Award') !== false) { return 'Lifetime'; }
                    if (stripos($t, 'Ombudsperson of the Year') !== false) { return 'Ombudsperson'; }
                    if (stripos($t, 'Community Honore') !== false || stripos($t, 'Community Honoree') !== false || stripos($t, 'Community Honorée') !== false) { return 'Community'; }
                    // Experience of the Year: include only when explicitly marked with dash+"Experience" to exclude AR/Reality variants
                    if (stripos($t, 'Experience of the Year') !== false && stripos($t, 'Education') === false && stripos($t, 'Entertainment') === false) {
                        // match hyphen, en dash, em dash before the word Experience
                        if (preg_match('/[\-\x{2013}\x{2014}]\s*Experience\b/iu', $t)) { return 'XOTY'; }
                        return '';
                    }
                    return '';
                };
                $extract_year2 = function($title) { if (preg_match('/\\b(20[0-5][0-9])\\b/', (string)$title, $m)) { return $m[1]; } return ''; };
                $strip_year2 = function($title) use ($extract_year2) { $y=$extract_year2($title); return $y!=='' ? trim(preg_replace('/\\b'.$y.'\\b/','',(string)$title)) : (string)$title; };
                $cards2 = array();
                // Helper: fetch images from meta key allowing arrays or single values
                $gc_fetch_images = function($post_id, $meta_key) {
                    $imgs = array();
                    if (empty($post_id)) { return $imgs; }
                    $raw_values = get_post_meta($post_id, $meta_key, false);
                    $ids = array();
                    foreach ((array)$raw_values as $val) {
                        if (is_array($val)) {
                            foreach ($val as $m) {
                                if (is_array($m) && isset($m['ID'])) { $ids[] = intval($m['ID']); }
                                elseif (is_numeric($m)) { $ids[] = intval($m); }
                            }
                        } elseif (is_numeric($val)) {
                            $ids[] = intval($val);
                        }
                    }
                    if (empty($ids)) {
                        $single = get_post_meta($post_id, $meta_key, true);
                        if (is_array($single)) {
                            foreach ($single as $m) {
                                if (is_array($m) && isset($m['ID'])) { $ids[] = intval($m['ID']); }
                                elseif (is_numeric($m)) { $ids[] = intval($m); }
                            }
                        } elseif (is_numeric($single)) { $ids[] = intval($single); }
                    }
                    foreach ($ids as $aid) {
                        if (!$aid) { continue; }
                        $url = wp_get_attachment_image_url($aid, 'full');
                        if (!$url) { continue; }
                        $title = get_the_title($aid);
                        $file = get_attached_file($aid);
                        $filename = $file ? wp_basename($file) : '';
                        $imgs[] = array('id' => $aid, 'url' => $url, 'title' => (string)$title, 'filename' => (string)$filename);
                    }
                    return $imgs;
                };

                $gc_normalize = function($s) { return preg_replace('/[^a-z0-9]+/','', strtolower((string)$s)); };
                $gc_score_match = function($name, $image) use ($gc_normalize) {
                    $title = $gc_normalize($image['title'] . ' ' . $image['filename']);
                    $tokens = preg_split('/\s+/u', strtolower((string)$name));
                    $score = 0;
                    foreach ($tokens as $tok) { $tok = preg_replace('/[^a-z0-9]+/','', $tok); if (strlen($tok) >= 3 && $tok !== '' && strpos($title, $tok) !== false) { $score++; } }
                    return $score;
                };

                foreach ($awards as $aw) {
                    if (!is_array($aw)) { continue; }
                    $code = $norm_code2(isset($aw['title']) ? $aw['title'] : ''); if ($code==='') { continue; }
                    $year = $extract_year2(isset($aw['title']) ? $aw['title'] : ''); if ($year==='') { continue; }
                    if (!isset($cards2[$code])) { $cards2[$code]=array(); }
                    if (!isset($cards2[$code][$year])) { $cards2[$code][$year]=array(); }
                    $hero=''; if (!empty($aw['acceptance_image_url'])) { $hero=$aw['acceptance_image_url']; }
                    if ($hero==='' && !empty($aw['winner_ids']) && is_array($aw['winner_ids'])) { $wid0=intval($aw['winner_ids'][0]); if ($wid0) { $fi=get_the_post_thumbnail_url($wid0,'full'); if ($fi) { $hero=$fi; } } }
                    $featured=''; if (!empty($aw['winner_ids']) && is_array($aw['winner_ids'])) { $wid0=intval($aw['winner_ids'][0]); if ($wid0) { $feat=get_the_post_thumbnail_url($wid0,'full'); if ($feat) { $featured=$feat; } } }
                    // Winner/Honoree base name
                    $winner_title = '';
                    if (!empty($aw['winners']) && is_array($aw['winners']) && !empty($aw['winners'][0]['title'])) { $winner_title = (string)$aw['winners'][0]['title']; }
                    // Company and people for h5 line similar to exhibits when available
                    $winner_company = '';
                    $winner_people = array();
                    if (!empty($aw['winners']) && is_array($aw['winners'])) {
                        $w0 = $aw['winners'][0];
                        if (!empty($w0['company'])) { $winner_company = (string)$w0['company']; }
                        if (!empty($w0['people']) && is_array($w0['people'])) { $winner_people = $w0['people']; }
                    }
                    // Build recipient names from winners (prefer people -> company -> title)
                    $recipient_names = array();
                    if (!empty($aw['winners']) && is_array($aw['winners'])) {
                        foreach ($aw['winners'] as $w) {
                            $added = false;
                            if (!empty($w['people']) && is_array($w['people'])) {
                                foreach ($w['people'] as $pn) { $pn = trim((string)$pn); if ($pn !== '') { $recipient_names[] = $pn; $added = true; } }
                            }
                            if (!$added) {
                                $comp = isset($w['company']) ? trim((string)$w['company']) : '';
                                if ($comp !== '') { $recipient_names[] = $comp; $added = true; }
                            }
                            if (!$added) {
                                $ttl = isset($w['title']) ? trim((string)$w['title']) : '';
                                if ($ttl !== '') { $recipient_names[] = $ttl; }
                            }
                        }
                    }

                    // Collect acceptance images from event and winner objects
                    $acceptance_images = array();
                    $seen = array();
                    if (!empty($aw['object_id'])) {
                        foreach ($gc_fetch_images(intval($aw['object_id']), 'acceptance_image') as $im) {
                            if (!empty($im['url']) && !isset($seen[$im['url']])) { $seen[$im['url']] = true; $acceptance_images[] = $im; }
                        }
                    }
                    if (!empty($aw['winner_ids']) && is_array($aw['winner_ids'])) {
                        foreach ($aw['winner_ids'] as $wid) {
                            $wid = intval($wid); if (!$wid) { continue; }
                            foreach ($gc_fetch_images($wid, 'acceptance_image') as $im) {
                                if (!empty($im['url']) && !isset($seen[$im['url']])) { $seen[$im['url']] = true; $acceptance_images[] = $im; }
                            }
                        }
                    }

                    // Order acceptance images by recipient names
                    if (!empty($recipient_names) && !empty($acceptance_images)) {
                        $remaining = $acceptance_images; $ordered = array();
                        foreach ($recipient_names as $nm) {
                            $best_i = -1; $best_s = 0; foreach ($remaining as $i => $img) { $s = $gc_score_match($nm, $img); if ($s > $best_s) { $best_s = $s; $best_i = $i; } }
                            if ($best_i >= 0) { $img = $remaining[$best_i]; array_splice($remaining, $best_i, 1); $ordered[] = array('name'=>$nm,'img'=>$img['url'],'title'=>$img['title']); }
                        }
                        foreach ($remaining as $img) { $ordered[] = array('name'=>'','img'=>$img['url'],'title'=>$img['title']); }
                        $acceptance_items = $ordered;
                    } else {
                        $acceptance_items = array(); foreach ($acceptance_images as $img) { $acceptance_items[] = array('name'=>'','img'=>$img['url'],'title'=>$img['title']); }
                    }

                    // Presenter names (no images)
                    $presenter_names = array();
                    if (!empty($aw['presenters']) && is_array($aw['presenters'])) {
                        foreach ($aw['presenters'] as $pn) { $pn = trim((string)$pn); if ($pn !== '') { $presenter_names[] = $pn; } }
                    }

                    $cards2[$code][$year][] = array(
                        'object_id' => isset($aw['object_id']) ? $aw['object_id'] : '',
                        'title' => $strip_year2(isset($aw['title']) ? $aw['title'] : ''),
                        'year' => $year,
                        'hero' => $hero,
                        'acceptance' => isset($aw['acceptance_image_url']) ? $aw['acceptance_image_url'] : '',
                        'featured' => $featured,
                        'winner_title' => $winner_title,
                        'winner_company' => $winner_company,
                        'winner_people' => $winner_people,
                        'acceptance_items' => $acceptance_items,
                        'presenter_names' => $presenter_names,
                    );
                }
            // Render three sections with exactly 5 cards each, mapped to years 2020–2024 and using featured images only
            $render_yeared_people = function($heading, $by_year_map) {
                $years = array(2020,2021,2022,2023,2024);
                echo '<div class="exhibits-wrapper">';
                echo '<h2>' . esc_html($heading) . '</h2>';
                echo '<div class="exhibits-grid">';
                foreach ($years as $yy) {
                    $entry = isset($by_year_map[$yy]) ? $by_year_map[$yy] : array('title' => '', 'img' => '');
                    $title = isset($entry['title']) ? (string)$entry['title'] : '';
                    $img = isset($entry['img']) ? (string)$entry['img'] : '';
                    echo '<div class="exhibit-tile"><div class="exhibit-content">';
                    echo '<div class="ex-line ex-award-name">' . esc_html($title) . '</div>';
                    if ($img !== '') {
                        echo '<div style="width:100%;height:80%;display:flex;align-items:center;justify-content:center;">'
                           . '<img src="' . esc_url($img) . '" alt="" style="max-width:70%;max-height:70%;object-fit:contain;box-shadow:0 12px 24px rgba(0,0,0,0.55)" />'
                           . '</div>';
                    }
                    echo '</div></div>';
                }
                echo '</div>';
                echo '</div>';
            };
            $render_yeared_people('Ceremony Host', $ceremony_by_year);
            $render_yeared_people('Ambassador', $amb_by_year);
            $render_yeared_people('Red Carpet Host', $red_by_year);

            // Bespoke combined card: 2021 AR Experience, 2022 AR Passthrough Experience, 2023 Mixed Reality Experience
            $norm = function($s){
                $s = strtolower((string)$s);
                $s = preg_replace('/[\x{2013}\x{2014}\-]+/u',' ', $s);
                $s = preg_replace('/\s+/', ' ', $s);
                return trim($s);
            };
            $targets = array(
                '2021 ar experience of the year',
                '2022 ar passthrough experience of the year',
                '2023 mixed reality experience of the year',
            );
            $wanted = array();
            foreach ($awards as $aw) {
                if (!is_array($aw)) { continue; }
                $t = isset($aw['title']) ? $aw['title'] : '';
                $nt = $norm($t);
                foreach ($targets as $idx => $needle) {
                    if (strpos($nt, $needle) !== false) {
                        // Winner/project info
                        $winner_title = '';
                        $winner_company = '';
                        $winner_people = array();
                        if (!empty($aw['winners']) && is_array($aw['winners'])) {
                            $w0 = $aw['winners'][0];
                            $winner_title = isset($w0['title']) ? (string)$w0['title'] : '';
                            $winner_company = isset($w0['company']) ? (string)$w0['company'] : '';
                            if (!empty($w0['people']) && is_array($w0['people'])) { $winner_people = $w0['people']; }
                        }
                        // Hero image: prefer winner featured image
                        $img = '';
                        if (!empty($aw['winner_ids']) && is_array($aw['winner_ids'])) {
                            foreach ($aw['winner_ids'] as $wid) {
                                $wimg = get_the_post_thumbnail_url(intval($wid), 'full');
                                if ($wimg) { $img = $wimg; break; }
                            }
                        }
                        if ($img === '' && !empty($aw['object_id'])) {
                            $img = get_the_post_thumbnail_url(intval($aw['object_id']), 'full') ?: '';
                        }
                        if ($img === '' && !empty($aw['acceptance_image_url'])) { $img = (string)$aw['acceptance_image_url']; }
                        if ($img === '' && !empty($aw['event_logo_url'])) { $img = (string)$aw['event_logo_url']; }

                        // Build acceptance items similar to grouped categories (event + winners acceptance images)
                        $acc_items = array();
                        // Event-level acceptance images
                        if (!empty($aw['object_id'])) {
                            $ameta = get_post_meta(intval($aw['object_id']), 'acceptance_image', true);
                            if (is_array($ameta) && !empty($ameta)) {
                                foreach ($ameta as $ai) {
                                    $aid = is_array($ai) && isset($ai['ID']) ? intval($ai['ID']) : intval($ai);
                                    if ($aid) {
                                        $url = wp_get_attachment_image_url($aid, 'full');
                                        $title = get_the_title($aid);
                                        if ($url) { $acc_items[] = array('name'=>'','img'=>$url,'title'=>$title); }
                                    }
                                }
                            } elseif (is_numeric($ameta)) {
                                $url = wp_get_attachment_image_url(intval($ameta), 'full');
                                $title = get_the_title(intval($ameta));
                                if ($url) { $acc_items[] = array('name'=>'','img'=>$url,'title'=>$title); }
                            }
                        }
                        // Winner-level acceptance images
                        if (!empty($aw['winner_ids']) && is_array($aw['winner_ids'])) {
                            foreach ($aw['winner_ids'] as $wid) {
                                $wameta = get_post_meta(intval($wid), 'acceptance_image', true);
                                if (is_array($wameta) && !empty($wameta)) {
                                    foreach ($wameta as $wai) {
                                        $waid = is_array($wai) && isset($wai['ID']) ? intval($wai['ID']) : intval($wai);
                                        if ($waid) {
                                            $wurl = wp_get_attachment_image_url($waid, 'full');
                                            $wtitle = get_the_title($waid);
                                            if ($wurl) { $acc_items[] = array('name'=>'','img'=>$wurl,'title'=>$wtitle); }
                                        }
                                    }
                                } elseif (is_numeric($wameta)) {
                                    $wurl = wp_get_attachment_image_url(intval($wameta), 'full');
                                    $wtitle = get_the_title(intval($wameta));
                                    if ($wurl) { $acc_items[] = array('name'=>'','img'=>$wurl,'title'=>$wtitle); }
                                }
                            }
                        }

                        $wanted[$needle] = array(
                            'title' => $t,
                            'img' => $img,
                            'winner_title' => $winner_title,
                            'winner_company' => $winner_company,
                            'winner_people' => $winner_people,
                            'acceptance_items' => $acc_items,
                        );
                    }
                }
            }
            // Ensure order as in $targets and render if at least one present
            $combined = array();
            foreach ($targets as $needle) { if (isset($wanted[$needle])) { $combined[] = $wanted[$needle]; } }
            if (!empty($combined)) {
                echo '<div class="exhibits-wrapper">';
                echo '<h2>AR/MR Experience Highlights</h2>';
                echo '<style>'
                   . '.cat-tile{width:2048px;height:720px;margin:12px 0;color:#e6f0ff;position:relative;overflow:hidden;border:1px solid #000;box-sizing:border-box}'
                   . '.cat-tile-head.ex-award-name{position:absolute;top:0;left:0;width:2048px;height:72px;display:flex;align-items:center;justify-content:center;font-size:3.5rem;font-weight:900}'
                   . '.cat-tile-grid{position:absolute;inset:72px 0 0 0;display:grid;gap:6px;padding:6px}'
                   . '.cat-cell{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;overflow:hidden}'
                   . '.cell-award-name{color:#fff;font-size:2rem;line-height:1.1;text-align:center;margin:2px 0 4px;max-width:95%}'
                   . '.cat-presented{font-size:1.25rem;line-height:1.1;color:#e6f0ff;margin:0 0 4px;text-align:center}'
                   . '.cat-hero-wrap{width:100%;display:flex;align-items:center;justify-content:center}'
                   . '.cat-hero-wrap .ex-winner-hero-wrap{transform-origin:center top;}'
                   . '.cat-hero-wrap .ex-winner-hero-img{width:61.8%;}'
                   . '.cat-cap{margin:0;text-align:center;min-height:84px;width:100%}'
                   . '.cat-cap h4.ex-winner-base{margin:0;font-size:2.2rem;font-weight:700;line-height:1.06;color:#fee813;-webkit-text-stroke:1px #7a5f00;width:auto;max-width:95%;margin-left:auto;margin-right:auto}'
                   . '.cat-cap h5.ex-winner-company{margin:0;font-size:1.4rem;line-height:1.1;width:100%}'
                   . '</style>';
                echo '<div class="cat-tile">';
                echo '<div class="ex-corner-brand"><img src="https://obi-wan-v:3000/wp-content/uploads/2025/11/PolysImmersiveAwardsLogoWithTrophy-3-1Aspect-NoYear.png" alt="Polys Immersive Awards" /></div>';
                echo '<div class="cat-tile-head ex-award-name">AR/MR Experience of the Year</div>';
                $cols = count($combined);
                if ($cols < 3) { $cols = 3; }
                echo '<div class="cat-tile-grid" style="grid-template-columns:repeat(' . intval($cols) . ',1fr);">';
                foreach ($combined as $p) {
                    $awt = isset($p['title']) ? trim((string)$p['title']) : '';
                    $pi = isset($p['img']) ? (string)$p['img'] : '';
                    $wtitle = isset($p['winner_title']) ? trim((string)$p['winner_title']) : '';
                    $wcompany = isset($p['winner_company']) ? trim((string)$p['winner_company']) : '';
                    $wtitle_b = isset($p['winner_title_b']) ? trim((string)$p['winner_title_b']) : '';
                    $wpeople = isset($p['winner_people']) && is_array($p['winner_people']) ? $p['winner_people'] : array();
                    $acc = isset($p['acceptance_items']) && is_array($p['acceptance_items']) ? $p['acceptance_items'] : array();
                    $winner_ids = (isset($p['winner_ids']) && is_array($p['winner_ids'])) ? $p['winner_ids'] : array();
                    echo '<div class="cat-cell">';
                    // Award name above image in white
                    if ($awt !== '') { echo '<div class="cell-award-name">' . esc_html($awt) . '</div>'; }
                    echo '<div class="cat-hero-wrap">';
                    // Dual honoree support: collect candidates from acceptance, then winners' acceptance meta, then winners' featured
                    $candidates = array();
                    if ($pi !== '') { $candidates[] = $pi; }
                    if (!empty($acc)) {
                        foreach ($acc as $it) { $u = isset($it['img']) ? (string)$it['img'] : ''; if ($u !== '') { $candidates[] = $u; } }
                    }
                    // Winners' acceptance images
                    if (is_array($winner_ids) && !empty($winner_ids)) {
                        foreach ($winner_ids as $wid) {
                            $wameta = get_post_meta(intval($wid), 'acceptance_image', true);
                            if (is_array($wameta)) {
                                foreach ($wameta as $wai) { $id = is_array($wai)&&isset($wai['ID'])?intval($wai['ID']):intval($wai); if ($id) { $u = wp_get_attachment_image_url($id,'full'); if ($u) { $candidates[] = $u; } } }
                            } elseif (is_numeric($wameta)) { $u = wp_get_attachment_image_url(intval($wameta),'full'); if ($u) { $candidates[] = $u; } }
                        }
                    }
                    // Winners' featured images
                    if (is_array($winner_ids) && !empty($winner_ids)) {
                        foreach ($winner_ids as $wid) { $u = get_the_post_thumbnail_url(intval($wid),'full'); if ($u) { $candidates[] = $u; } }
                    }
                    // Dedup in order and pick first two distinct
                    $seenU = array(); $uniqU = array();
                    foreach ($candidates as $u) { if ($u !== '' && !isset($seenU[$u])) { $uniqU[] = $u; $seenU[$u] = true; } }
                    $heroA = isset($uniqU[0]) ? $uniqU[0] : '';
                    $heroB = isset($uniqU[1]) ? $uniqU[1] : '';
                    
                    if ($heroA !== '' && $heroB !== '') {
                        echo '<div class="ex-winner-hero-wrap" style="width:520px;height:380px;"><div class="ex-winner-hero duo">'
                           . '<img class="ex-winner-hero-img hero-a" src="' . esc_url($heroA) . '" alt="" />'
                           . '<img class="ex-winner-hero-img hero-b" src="' . esc_url($heroB) . '" alt="" />'
                           . '</div></div>';
                    } elseif ($heroA !== '') {
                        echo '<div class="ex-winner-hero-wrap" style="width:520px;height:380px;"><div class="ex-winner-hero"><img class="ex-winner-hero-img" src="' . esc_url($heroA) . '" alt="" /></div></div>';
                    }
                    echo '</div>';
                    echo '<div class="cat-cap">';
                    // Show winning project name
                    if ($wtitle !== '') {
                        $h4_inner = esc_html($wtitle);
                        if ($wtitle_b !== '') { $h4_inner = esc_html($wtitle) . ' and<br>' . esc_html($wtitle_b); }
                        echo '<h4 class="ex-winner-base"><span>' . $h4_inner . '</span></h4>';
                    }
                    // Winners line (company/people)
                    $line = '';
                    if ($wcompany !== '') { $line .= 'by ' . esc_html($wcompany); }
                    if (!empty($wpeople)) { $line .= ($wcompany !== '' ? ': ' : ' ') . esc_html(implode(', ', $wpeople)); }
                    if ($line !== '') { echo '<h5 class="ex-winner-company">' . $line . '</h5>'; }
                    echo '</div>';
                    // Accepted by grid (up to 4), same layout as grouped categories
                    if (!empty($acc)) {
                        $acc4 = array_slice($acc, 0, 4);
                        $cols = count($acc4) > 2 ? 2 : count($acc4);
                        echo '<div class="cat-acceptance">'
                           . '<div class="cat-acceptance-header">Accepted by</div>'
                           . '<div style="display:grid;grid-template-columns:repeat(' . intval(max(1,$cols)) . ',1fr);gap:4px;align-items:start;justify-items:center;width:100%">';
                        foreach ($acc4 as $it) {
                            $iurl = isset($it['img']) ? (string)$it['img'] : '';
                            if ($iurl === '') { continue; }
                            $label = '';
                            if (!empty($it['name'])) { $label = trim((string)$it['name']); }
                            if ($label === '' && !empty($it['title'])) { $label = trim((string)$it['title']); }
                            echo '<div style="display:flex;flex-direction:column;align-items:center;justify-content:flex-start;margin:2px 0;">'
                               . '<img src="' . esc_url($iurl) . '" alt="" style="height:132px;object-fit:cover;border-radius:50%;box-shadow:0 8px 16px rgba(0,0,0,0.45)" />'
                               . ($label !== '' ? '<div class="names">' . esc_html($label) . '</div>' : '')
                               . '</div>';
                        }
                        echo '</div></div>';
                    }
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }

            // Bespoke combined card: Community Honors across years (label each as 'Community Honor')
            $community_found = array();
            foreach ($awards as $aw) {
                if (!is_array($aw)) { continue; }
                $t = isset($aw['title']) ? (string)$aw['title'] : '';
                $nt = $norm($t);
                if (strpos($nt, 'community') === false) { continue; }
                // Derive year from title if possible
                $year_label = '';
                if (preg_match('/\b(20\d{2})\b/', $t, $m)) { $year_label = $m[1]; }
                // Winner/project info
                $winner_title = '';
                $winner_company = '';
                $winner_people = array();
                // Presenter names if available
                $presenter_names = array();
                if (!empty($aw['presenter_names']) && is_array($aw['presenter_names'])) {
                    foreach ($aw['presenter_names'] as $pn) { $pn = trim((string)$pn); if ($pn !== '') { $presenter_names[] = $pn; } }
                } elseif (!empty($aw['presenters']) && is_array($aw['presenters'])) {
                    foreach ($aw['presenters'] as $pn) { $pn = trim((string)$pn); if ($pn !== '') { $presenter_names[] = $pn; } }
                }
                if (!empty($aw['winners']) && is_array($aw['winners'])) {
                    $w0 = $aw['winners'][0];
                    $winner_title = isset($w0['title']) ? (string)$w0['title'] : '';
                    $winner_company = isset($w0['company']) ? (string)$w0['company'] : '';
                    if (!empty($w0['people']) && is_array($w0['people'])) { $winner_people = $w0['people']; }
                    // capture second honoree name if present (for 2025 duo rendering)
                    $winner_title_b = '';
                    if (count($aw['winners']) > 1 && !empty($aw['winners'][1]['title'])) {
                        $winner_title_b = (string)$aw['winners'][1]['title'];
                    }
                }
                // Build acceptance items (event + winners acceptance images)
                $acc_items = array();
                if (!empty($aw['object_id'])) {
                    $ameta = get_post_meta(intval($aw['object_id']), 'acceptance_image', true);
                    if (is_array($ameta) && !empty($ameta)) {
                        foreach ($ameta as $ai) {
                            $aid = is_array($ai) && isset($ai['ID']) ? intval($ai['ID']) : intval($ai);
                            if ($aid) {
                                $url = wp_get_attachment_image_url($aid, 'full');
                                $title = get_the_title($aid);
                                if ($url) { $acc_items[] = array('name'=>'','img'=>$url,'title'=>$title); }
                            }
                        }
                    } elseif (is_numeric($ameta)) {
                        $url = wp_get_attachment_image_url(intval($ameta), 'full');
                        $title = get_the_title(intval($ameta));
                        if ($url) { $acc_items[] = array('name'=>'','img'=>$url,'title'=>$title); }
                    }
                }
                if (!empty($aw['winner_ids']) && is_array($aw['winner_ids'])) {
                    foreach ($aw['winner_ids'] as $wid) {
                        $wameta = get_post_meta(intval($wid), 'acceptance_image', true);
                        if (is_array($wameta) && !empty($wameta)) {
                            foreach ($wameta as $wai) {
                                $waid = is_array($wai) && isset($wai['ID']) ? intval($wai['ID']) : intval($wai);
                                if ($waid) {
                                    $wurl = wp_get_attachment_image_url($waid, 'full');
                                    $wtitle = get_the_title($waid);
                                    if ($wurl) { $acc_items[] = array('name'=>'','img'=>$wurl,'title'=>$wtitle); }
                                }
                            }
                        } elseif (is_numeric($wameta)) {
                            $wurl = wp_get_attachment_image_url(intval($wameta), 'full');
                            $wtitle = get_the_title(intval($wameta));
                            if ($wurl) { $acc_items[] = array('name'=>'','img'=>$wurl,'title'=>$wtitle); }
                        }
                    }
                }
                // Hero image: use acceptance image (laurel) if available, otherwise fallback
                $img = '';
                if (!empty($acc_items)) {
                    $img = (string)$acc_items[0]['img'];
                }
                if ($img === '' && !empty($aw['winner_ids']) && is_array($aw['winner_ids'])) {
                    foreach ($aw['winner_ids'] as $wid) {
                        $wimg = get_the_post_thumbnail_url(intval($wid), 'full');
                        if ($wimg) { $img = $wimg; break; }
                    }
                }
                if ($img === '' && !empty($aw['object_id'])) {
                    $img = get_the_post_thumbnail_url(intval($aw['object_id']), 'full') ?: '';
                }
                if ($img === '' && !empty($aw['event_logo_url'])) { $img = (string)$aw['event_logo_url']; }

                $community_found[] = array(
                    'display_title' => ($year_label !== '' ? $year_label : 'Community Honor'),
                    'year' => ($year_label !== '' ? intval($year_label) : 0),
                    'img' => $img,
                    'winner_title' => $winner_title,
                    'winner_title_b' => isset($winner_title_b) ? $winner_title_b : '',
                    'winner_company' => $winner_company,
                    'winner_people' => $winner_people,
                    'presenter_names' => $presenter_names,
                    'winner_ids' => (!empty($aw['winner_ids']) && is_array($aw['winner_ids'])) ? $aw['winner_ids'] : array(),
                    'winners_struct' => isset($aw['winners']) ? $aw['winners'] : array(),
                    'object_id' => isset($aw['object_id']) ? $aw['object_id'] : 0,
                    'acceptance_items' => $acc_items,
                );
            }
            if (!empty($community_found) || (isset($cards2['Community']) && !empty($cards2['Community']))) {
                // If we have fewer than 5 from award scan, supplement from grouped Community entries
                if (count($community_found) < 5 && isset($cards2) && is_array($cards2)) {
                    $years_map = array();
                    foreach ($community_found as $cf) { if (!empty($cf['year'])) { $years_map[intval($cf['year'])] = true; } }
                    // Gather all grouped keys that contain 'community'
                    $grp_all = array();
                    foreach ($cards2 as $code_key => $by_year) {
                        if (stripos((string)$code_key, 'community') !== false && is_array($by_year)) {
                            foreach ($by_year as $yr => $entries) {
                                if (!isset($grp_all[$yr])) { $grp_all[$yr] = array(); }
                                foreach ((array)$entries as $entry) { $grp_all[$yr][] = $entry; }
                            }
                        }
                    }
                    // Flatten by ascending year and fill up to 5
                    if (!empty($grp_all)) {
                        ksort($grp_all);
                        foreach ($grp_all as $yr => $entries) {
                            if (count($community_found) >= 5) { break; }
                            if (isset($years_map[intval($yr)])) { continue; }
                            foreach ((array)$entries as $entry) {
                                if (count($community_found) >= 5) { break; }
                                $pi = '';
                                if (!empty($entry['acceptance'])) { $pi = (string)$entry['acceptance']; }
                                elseif (!empty($entry['featured'])) { $pi = (string)$entry['featured']; }
                                elseif (!empty($entry['hero'])) { $pi = (string)$entry['hero']; }
                                $community_found[] = array(
                                    'display_title' => (string)$yr,
                                    'year' => intval($yr),
                                    'img' => $pi,
                                    'winner_title' => isset($entry['winner_title']) ? (string)$entry['winner_title'] : '',
                                    'winner_company' => isset($entry['winner_company']) ? (string)$entry['winner_company'] : '',
                                    'winner_people' => isset($entry['winner_people']) && is_array($entry['winner_people']) ? $entry['winner_people'] : array(),
                                    'presenter_names' => isset($entry['presenter_names']) && is_array($entry['presenter_names']) ? $entry['presenter_names'] : array(),
                                    'winner_ids' => isset($entry['winner_ids']) && is_array($entry['winner_ids']) ? $entry['winner_ids'] : array(),
                                    'acceptance_items' => isset($entry['acceptance_items']) && is_array($entry['acceptance_items']) ? $entry['acceptance_items'] : array(),
                                );
                                $years_map[intval($yr)] = true;
                                break; // one per year
                            }
                        }
                    }
                }
                // Build a map by year, prefer entries with acceptance items and hero image
                $by_year = array();
                foreach ($community_found as $e) {
                    $y = isset($e['year']) ? intval($e['year']) : 0;
                    if ($y <= 0) { continue; }
                    if (!isset($by_year[$y])) { $by_year[$y] = $e; continue; }
                    $cur = $by_year[$y];
                    $cur_acc = !empty($cur['acceptance_items']);
                    $e_acc = !empty($e['acceptance_items']);
                    $cur_img = !empty($cur['img']);
                    $e_img = !empty($e['img']);
                    // Prefer candidate that has acceptance images; tie-breaker: has hero image
                    if ((!$cur_acc && $e_acc) || (!$cur_img && $e_img)) { $by_year[$y] = $e; }
                }
                // Select fixed range 2021–2025 (5 years) and render ascending so 2025 appears last
                $years = array(2021, 2022, 2023, 2024, 2025);
                $community_display = array();
                foreach ($years as $y) { if (isset($by_year[$y])) { $community_display[] = $by_year[$y]; } }
                echo '<div class="exhibits-wrapper">';
                echo '<h2>Community Honorees</h2>';
                echo '<style>'
                   . '.cat-tile{width:2048px;height:720px;margin:12px 0;color:#e6f0ff;position:relative;overflow:hidden;border:1px solid #000;box-sizing:border-box}'
                   . '.cat-tile-head.ex-award-name{position:absolute;top:0;left:0;width:2048px;height:72px;display:flex;align-items:center;justify-content:center;font-size:3.5rem;font-weight:900}'
                   . '.cat-tile-grid{position:absolute;inset:72px 0 0 0;display:grid;gap:6px;padding:6px}'
                   . '.cat-cell{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;overflow:hidden}'
                   . '.cell-award-name{color:#fff;font-size:3.4rem;line-height:1.05;text-align:center;margin:2px 0 2px;max-width:95%}'
                   . '.cat-hero-wrap{width:100%;display:flex;align-items:center;justify-content:center}'
                   . '.cat-hero-wrap .ex-winner-hero-wrap{transform-origin:center top;}'
                   . '.cat-hero-wrap .ex-winner-hero-img{width:61.8%;}'
                   . '.cat-cap{margin:0;text-align:center;min-height:84px;width:100%}'
                   . '.cat-cap h4.ex-winner-base{margin:0;font-size:2.2rem;font-weight:700;line-height:1.06;color:#fee813;-webkit-text-stroke:1px #7a5f00;width:auto;max-width:95%;margin-left:auto;margin-right:auto}'
                   . '.cat-cap h5.ex-winner-company{margin:0;font-size:1.4rem;line-height:1.1;width:100%}'
                   . '</style>';
                echo '<div class="cat-tile">';
                echo '<div class="ex-corner-brand"><img src="https://obi-wan-v:3000/wp-content/uploads/2025/11/PolysImmersiveAwardsLogoWithTrophy-3-1Aspect-NoYear.png" alt="Polys Immersive Awards" /></div>';
                echo '<div class="cat-tile-head ex-award-name">Community Honorees</div>';
                // Force 5 columns across
                echo '<div class="cat-tile-grid" style="grid-template-columns:repeat(5,1fr);">';
                foreach ($community_display as $p) {
                    $awt = isset($p['display_title']) ? trim((string)$p['display_title']) : 'Community Honor';
                    $pi = isset($p['img']) ? (string)$p['img'] : '';
                    $wtitle = isset($p['winner_title']) ? trim((string)$p['winner_title']) : '';
                    $wcompany = isset($p['winner_company']) ? trim((string)$p['winner_company']) : '';
                    $wpeople = isset($p['winner_people']) && is_array($p['winner_people']) ? $p['winner_people'] : array();
                    $acc = isset($p['acceptance_items']) && is_array($p['acceptance_items']) ? $p['acceptance_items'] : array();
                    $winner_ids = isset($p['winner_ids']) && is_array($p['winner_ids']) ? $p['winner_ids'] : array();
                    $winners_struct = isset($p['winners_struct']) && is_array($p['winners_struct']) ? $p['winners_struct'] : array();
                    $event_obj = isset($p['object_id']) ? intval($p['object_id']) : 0;
                    $year_val = 0; if (isset($p['year'])) { $year_val = intval($p['year']); }
                    if (!$year_val && preg_match('/\b(20\d{2})\b/', $awt, $m)) { $year_val = intval($m[1]); }
                    echo '<div class="cat-cell">';
                    // Year above image in white
                    echo '<div class="cell-award-name">' . esc_html($awt) . '</div>';
                    // Presented by under the year (names only)
                    if (!empty($p['presenter_names']) && is_array($p['presenter_names'])) {
                        $pnames = array();
                        foreach ($p['presenter_names'] as $pn) { $pn = trim((string)$pn); if ($pn !== '') { $pnames[] = $pn; } }
                        if (!empty($pnames)) {
                            echo '<div class="cat-presented">Presented by: ' . esc_html(implode(', ', $pnames)) . '</div>';
                        }
                    }
                    echo '<div class="cat-hero-wrap">';
                    // Determine duo honoree rendering using same strategy as individual tiles
                    $duo_names = array();
                    if (!empty($winners_struct) && count($winners_struct) >= 2) {
                        $n1 = isset($winners_struct[0]['title']) ? trim((string)$winners_struct[0]['title']) : '';
                        $n2 = isset($winners_struct[1]['title']) ? trim((string)$winners_struct[1]['title']) : '';
                        if ($n1 !== '' && $n2 !== '') { $duo_names = array($n1, $n2); }
                    }
                    // Build recipient names preference: people -> company -> title
                    $recipient_names = array();
                    if (!empty($winners_struct)) {
                        foreach ($winners_struct as $w) {
                            $added = false;
                            if (!empty($w['people']) && is_array($w['people'])) {
                                foreach ($w['people'] as $ppnm) { $ppnm = trim((string)$ppnm); if ($ppnm !== '') { $recipient_names[] = $ppnm; $added = true; } }
                            }
                            if (!$added) {
                                $comp = isset($w['company']) ? trim((string)$w['company']) : '';
                                if ($comp !== '') { $recipient_names[] = $comp; $added = true; }
                            }
                            if (!$added) {
                                $ttl = isset($w['title']) ? trim((string)$w['title']) : '';
                                if ($ttl !== '') { $recipient_names[] = $ttl; }
                            }
                        }
                    }
                    // Collect acceptance images from event and winner posts
                    $acc_imgs = array();
                    $push_url = function($u) use (&$acc_imgs){ if ($u && !in_array($u, $acc_imgs, true)) { $acc_imgs[] = $u; } };
                    // Event-level acceptance
                    if ($event_obj) {
                        $am = get_post_meta($event_obj, 'acceptance_image', true);
                        if (is_array($am)) { foreach ($am as $m) { $aid = (is_array($m)&&isset($m['ID']))?intval($m['ID']):(is_numeric($m)?intval($m):0); if ($aid) { $push_url(wp_get_attachment_image_url($aid,'full')); } } }
                        elseif (is_numeric($am)) { $push_url(wp_get_attachment_image_url(intval($am), 'full')); }
                    }
                    // Winner-level acceptance and featured
                    if (!empty($winner_ids)) {
                        foreach ($winner_ids as $wid) {
                            $wid = intval($wid); if (!$wid) { continue; }
                            $am = get_post_meta($wid, 'acceptance_image', true);
                            if (is_array($am)) { foreach ($am as $m) { $aid = (is_array($m)&&isset($m['ID']))?intval($m['ID']):(is_numeric($m)?intval($m):0); if ($aid) { $push_url(wp_get_attachment_image_url($aid,'full')); } } }
                            elseif (is_numeric($am)) { $push_url(wp_get_attachment_image_url(intval($am), 'full')); }
                            $fi = get_the_post_thumbnail_url($wid, 'full'); if ($fi) { $push_url($fi); }
                        }
                    }
                    // Order images heuristically by recipient names: simple contains token matching
                    $normalize = function($s){ return preg_replace('/[^a-z0-9]+/','', strtolower((string)$s)); };
                    $score = function($name,$url) use ($normalize){ $t = $normalize(wp_basename(parse_url((string)$url, PHP_URL_PATH))); $n = $normalize($name); return ($n!=='' && strpos($t,$n)!==false)?1:0; };
                    if (!empty($recipient_names) && count($acc_imgs) > 1) {
                        $ordered = array();
                        $remaining = $acc_imgs;
                        foreach ($recipient_names as $nm) {
                            $best_i=-1;$best_s=0; foreach ($remaining as $i=>$u){ $s=$score($nm,$u); if($s>$best_s){$best_s=$s;$best_i=$i;} }
                            if ($best_i>=0 && $best_s>0) { $ordered[] = $remaining[$best_i]; array_splice($remaining,$best_i,1); }
                        }
                        $acc_imgs = array_merge($ordered, $remaining);
                    }
                    // Render duo or single inside laurel
                    if (!empty($duo_names) && count($acc_imgs) >= 2) {
                        echo '<div class="ex-winner-hero-wrap"><div class="ex-winner-hero duo">'
                           . '<img class="ex-winner-hero-img hero-a" src="' . esc_url($acc_imgs[0]) . '" alt="" />'
                           . '<img class="ex-winner-hero-img hero-b" src="' . esc_url($acc_imgs[1]) . '" alt="" />'
                           . '</div></div>';
                    } elseif ($pi !== '') {
                        // Slightly smaller wrapper so images fit better
                        echo '<div class="ex-winner-hero-wrap" style="width:480px;height:360px;"><div class="ex-winner-hero"><img class="ex-winner-hero-img" src="' . esc_url($pi) . '" alt="" /></div></div>';
                    }
                    echo '</div>';
                    echo '<div class="cat-cap">';
                    // Names under laurel: if duo, print as "Name1 and Name2" (no line break)
                    if (!empty($duo_names) && count($duo_names) >= 2) {
                        echo '<h4 class="ex-winner-base"><span>' . esc_html($duo_names[0]) . ' and ' . esc_html($duo_names[1]) . '</span></h4>';
                    } elseif ($wtitle !== '') {
                        echo '<h4 class="ex-winner-base"><span>' . esc_html($wtitle) . '</span></h4>';
                    }
                    $line = '';
                    if ($wcompany !== '') { $line .= 'by ' . esc_html($wcompany); }
                    if (!empty($wpeople)) { $line .= ($wcompany !== '' ? ': ' : ' ') . esc_html(implode(', ', $wpeople)); }
                    if ($line !== '') { echo '<h5 class="ex-winner-company">' . $line . '</h5>'; }
                    echo '</div>';
                    // Omit acceptance grid for Community Honors bespoke card
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
            if (!empty($cards2)) {
                echo '<div class="exhibits-wrapper">';
                echo '<h2>Grouped Award Categories</h2>';
                echo "<style>
                    .cat-tile{width:2048px;height:1024px;margin:16px 0;color:#e6f0ff;position:relative;overflow:hidden;border:1px solid #000;box-sizing:border-box}
                    .cat-tile-head.ex-award-name{position:absolute;top:0;left:0;width:2048px;height:84px;display:flex;align-items:center;justify-content:center;font-size:4.25rem;font-weight:900}
                    .cat-tile-grid{position:absolute;inset:84px 0 0 0;display:grid;gap:4px;padding:4px}
                    .cat-cell{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;overflow:hidden}
                    /* Corner brand in upper-left for grouped exhibits only */
                    .cat-tile .ex-corner-brand{position:absolute;left:15px;top:10px;width:200px;z-index:4}
                    .cat-tile .ex-corner-brand img{display:block;width:100%;height:auto;object-fit:contain}
                    /* Scale down exhibits laurel hero for category cells */
                    .cat-hero-wrap{width:100%;display:flex;align-items:center;justify-content:center}
                    .cat-hero-wrap .ex-winner-hero-wrap{transform-origin:center top;}
                    /* Dampen the laurels glow inside grouped category cells */
                    .cat-hero-wrap .ex-winner-hero-wrap::after{filter:drop-shadow(0 6px 12px rgba(0,0,0,0.4)) drop-shadow(0 0 14px rgba(56,140,255,0.35)) drop-shadow(0 0 28px rgba(56,140,255,0.2))}
                    .cat-hero-wrap .ex-winner-hero-img{width:61.8%;}
                    .cat-cap{margin:0;text-align:center;min-height:100px;width:100%}
                    .cat-cap h4.ex-winner-base{margin:0;font-size:2.5rem;font-weight:700;line-height:1.1;color:#fee813;-webkit-text-stroke:1px #7a5f00;width:auto;max-width:95%;margin-left:auto;margin-right:auto}
                    .cat-cap h5.ex-winner-company{margin:0;font-size:1.4rem;line-height:1.1;width:100%}
                    .cat-year{font-size:3.5rem;color:#fff;margin:0 0 2px;text-align:center}
                    .cat-presented{font-size:1.25rem;line-height:1.1;color:#e6f0ff;margin:0 0 4px;text-align:center}
                    .cat-acceptance{width:100%;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;margin-top:2px}
                    .cat-acceptance-header{font-weight:700;margin-bottom:1px;font-size:1rem}
                    .cat-acceptance img{display:block;width:auto;height:132px;max-width:90%;border-radius:50%;object-fit:cover;box-shadow:0 10px 20px rgba(0,0,0,0.5),0 0 20px rgba(56,140,255,0.35)}
                    .cat-acceptance .names{margin-top:2px;font-size:1.1rem;line-height:1.1;text-align:center;color:#e6f0ff;width:100%}
                    /* Honors-only tighter spacing above hero */
                    .cat-tile.honor .cat-year{margin:0 0 0px;font-size:3.0rem;line-height:1.0}
                    .cat-tile.honor .cat-presented{margin:0 0 1px}
                    /* Prevent clipping on honors titles */
                    .cat-tile.honor .cat-cap h4.ex-winner-base{font-size:2.4rem;line-height:1.06}
                    .cat-tile.honor .cat-cap h4.ex-winner-base span{display:inline-block;padding-bottom:2px}
                    .cat-tile.honor .cat-cap{min-height:98px}
                    /* Honors 3x2 row separator: centered hr, not full width */
                    .cat-row-sep{grid-column:1 / -1;display:flex;align-items:center;justify-content:center;margin:6px 0}
                    .cat-row-sep .cat-sep-hr{width:80%;height:3px;background:rgba(230,240,255,0.60);border:0;margin:0;border-radius:2px}
                </style>";
                // Helper to sanitize title: remove dashes and extra spaces
                $sanitize_title = function($t){
                    $t = preg_replace('/[\x{2013}\x{2014}\-]+/u',' ', (string)$t); // remove en/em dashes and hyphens
                    $t = preg_replace('/\s+/', ' ', $t);
                    return trim($t);
                };
                foreach ($cards2 as $code => $years) {
                    // Flatten entries by year ascending
                    ksort($years);
                    $flat = array();
                    foreach ($years as $yr => $entries) {
                        foreach ($entries as $entry) { $flat[] = $entry; if (count($flat) >= 6) break; }
                        if (count($flat) >= 6) break;
                    }
                    if (empty($flat)) { continue; }
                    // Determine preferred hero selection per category code
                    $is_honor_cat = in_array($code, array('Lifetime','Ombudsperson','Community'), true);
                    // Compute columns based on number of winners
                    // Non-honor categories: allow up to 5 columns (tile is 2048x1024)
                    // Honors: handle special 6 -> 3x2 below
                    $cols = count($flat);
                    if (!$is_honor_cat && $cols > 5) { $cols = 5; }
                    if ($is_honor_cat && $cols > 4) { $cols = 4; }
                    if ($cols < 1) { $cols = 1; }
                    echo '<div class="cat-tile' . ($is_honor_cat ? ' honor' : '') . '"><div class="ex-corner-brand"><img src="https://obi-wan-v:3000/wp-content/uploads/2025/11/PolysImmersiveAwardsLogoWithTrophy-3-1Aspect-NoYear.png" alt="Polys Immersive Awards" /></div>';
                    // Header should render once on top with the award name (not code)
                    $label = isset($category_labels[$code]) ? $category_labels[$code] : $code;
                    $label = $sanitize_title($label);
                    echo '<div class="cat-tile-head ex-award-name">' . esc_html($label) . '</div>';
                    // Grid: horizontal tiling across columns equal to count (capped)
                    // Honors layout special-case: when exactly six, force 3 columns (2 rows)
                    $grid_cols = $cols;
                    if ($is_honor_cat && count($flat) === 6) { $grid_cols = 3; }
                    if (!$is_honor_cat) { $grid_cols = (count($flat) >= 5) ? 5 : count($flat); }
                    echo '<div class="cat-tile-grid" style="grid-template-columns:repeat(' . intval($grid_cols) . ',1fr);">';
                    $__idx = 0; $total_cells = count($flat);
                    foreach ($flat as $entry) {
                        // Hero image selection
                        $hero = '';
                        if ($is_honor_cat) {
                            // Honors: prefer acceptance image
                            $hero = $entry['acceptance'] ?: $entry['featured'] ?: $entry['hero'];
                        } else {
                            // Experiences/Games/Innovators/Developers: prefer featured image
                            if ($code === 'DOTY') {
                                // Developer of the Year: use acceptance image from level 2
                                $hero = $entry['acceptance'] ?: $entry['featured'] ?: $entry['hero'];
                            } else {
                                $hero = $entry['featured'] ?: $entry['hero'] ?: $entry['acceptance'];
                            }
                        }
                        $title_clean = $sanitize_title($entry['title']);
                        echo '<div class="cat-cell">';
                        // Year above hero
                        echo '<div class="cat-year">' . esc_html($entry['year']) . '</div>';
                        // Presented by (names only) under the year
                        if (!empty($entry['presenter_names'])) {
                            $pnames = array();
                            foreach ((array)$entry['presenter_names'] as $pn) { $pn = trim((string)$pn); if ($pn !== '') { $pnames[] = $pn; } }
                            if (!empty($pnames)) {
                                echo '<div class="cat-presented">Presented by: ' . esc_html(implode(', ', $pnames)) . '</div>';
                            }
                        }
                        echo '<div class="cat-hero-wrap">';
                        // Compute explicit width/height for exhibits hero wrap to fit within cell
                        $hero_w = 420; $hero_h = 400;
                        if ($grid_cols >= 5) { $hero_w = 340; $hero_h = 320; }
                        elseif ($grid_cols === 4) { $hero_w = 400; $hero_h = 370; }
                        elseif ($grid_cols === 3) { $hero_w = ($is_honor_cat && count($flat) === 6) ? 360 : 460; $hero_h = ($is_honor_cat && count($flat) === 6) ? 340 : 420; }
                        elseif ($grid_cols === 2) { $hero_w = 520; $hero_h = 480; }
                        elseif ($grid_cols === 1) { $hero_w = 700; $hero_h = 650; }
                        // Scale width and height separately: keep width boost, reduce height for more vertical room
                        $inc_w = ($is_honor_cat && count($flat) === 6) ? 1.00 : 1.15;
                        $inc_h = $is_honor_cat ? ((count($flat) === 6) ? 0.80 : 0.80) : 0.82; // shorten cells further without changing width
                        $hero_w = intval(round($hero_w * $inc_w));
                        $hero_h = intval(round($hero_h * $inc_h));
                        if (!empty($hero)) {
                            echo '<div class="ex-winner-hero-wrap" style="width:' . intval($hero_w) . 'px;height:' . intval($hero_h) . 'px;"><div class="ex-winner-hero"><img class="ex-winner-hero-img" src="' . esc_url($hero) . '" alt="" /></div></div>';
                        }
                        echo '</div>';
                        echo '<div class="cat-cap">';
                        // Winner/Honoree name under the image, reusing h4.ex-winner-base formatting
                        $wn = isset($entry['winner_title']) ? $entry['winner_title'] : $title_clean;
                        $wclean = esc_html($sanitize_title($wn));
                        echo '<h4 class="ex-winner-base"><span>' . $wclean . '</span></h4>';
                        // Company/people line similar to exhibits when available
                        $line = '';
                        $company = isset($entry['winner_company']) ? trim((string)$entry['winner_company']) : '';
                        $ppl = array();
                        if (!empty($entry['winner_people']) && is_array($entry['winner_people'])) {
                            foreach ($entry['winner_people'] as $pn) { $pn = trim((string)$pn); if ($pn !== '') { $ppl[] = $pn; } }
                        }
                        if ($company !== '' || !empty($ppl)) {
                            if ($company !== '') { $line .= 'by ' . esc_html($company); }
                            if (!empty($ppl)) { $line .= ($company !== '' ? ': ' : ' ') . esc_html(implode(', ', $ppl)); }
                            echo '<h5 class="ex-winner-company">' . $line . '</h5>';
                        }
                        echo '</div>';
                        
                        // Experience and other specified categories: render acceptance images with captions under winner (up to 4)
                        if (in_array($code, array('XOTY','EDOTY','EEOTY','GOTY','IOTY'), true)) {
                            $acc = isset($entry['acceptance_items']) && is_array($entry['acceptance_items']) ? $entry['acceptance_items'] : array();
                            if (!empty($acc)) {
                                $acc4 = array_slice($acc, 0, 4);
                                $cols = count($acc4) > 2 ? 2 : count($acc4);
                                echo '<div class="cat-acceptance">'
                                   . '<div class="cat-acceptance-header">Accepted by</div>'
                                   . '<div style="display:grid;grid-template-columns:repeat(' . intval(max(1,$cols)) . ',1fr);gap:4px;align-items:start;justify-items:center;width:100%">';
                                foreach ($acc4 as $it) {
                                    $img = isset($it['img']) ? (string)$it['img'] : '';
                                    if ($img === '') { continue; }
                                    $name = isset($it['name']) ? trim((string)$it['name']) : '';
                                    $fallback = isset($it['title']) ? trim((string)$it['title']) : '';
                                    $label = $name !== '' ? $name : $fallback;
                                    echo '<div style="display:flex;flex-direction:column;align-items:center;justify-content:flex-start;margin:2px 0;">'
                                       . '<img src="' . esc_url($img) . '" alt="" style="height:132px;object-fit:cover;border-radius:50%;box-shadow:0 8px 16px rgba(0,0,0,0.45)" />'
                                       . ($label !== '' ? '<div class="names">' . esc_html($label) . '</div>' : '')
                                       . '</div>';
                                }
                                echo '</div></div>';
                            }
                        }
                        
                        echo '</div>';
                        $__idx++;
                        // Insert a single full-width separator after the first row in honors 3x2 layout
                        if ($is_honor_cat && $total_cells === 6 && $grid_cols === 3 && $__idx === 3) {
                            echo '<div class="cat-row-sep"><hr class="cat-sep-hr" /></div>';
                        }
                    }
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';
            }
        }

        // (Removed duplicate Hosts & Ambassadors block rendered earlier above grouped categories)

        // Award Exhibits grid (1024x1024 tiles with small text) - default view only
        if ($only === '') {
        echo '<div class="exhibits-wrapper">';
        echo '<h2>Award Exhibits</h2>';
        echo '<div class="exhibits-grid">';
        foreach ($awards as $idx => $award) {
            echo exhibits_render_award_tile($award, $idx);
        }
        echo '</div>';
        echo '</div>';
        }

        // Removed old generic Hosts & Ambassadors blocks; replaced by year-mapped sections above
    }
}

if (!isset($_GET['view']) || $_GET['view'] !== 'summary') {
    echo '</div>'; // End wrap
}

// Centralized action dispatcher for audit actions
audit_dispatch_actions($_GET);

get_footer();