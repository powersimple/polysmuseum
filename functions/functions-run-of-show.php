<?php

/**
 * Run of Show TSV Renderer
 *
 * Renders a full run-of-show as tab-separated values for copy/paste into Google Sheets.
 * Event-agnostic: pass any red carpet + ceremony menu slugs.
 */

// ─── Meta cache ───────────────────────────────────────────────
$_ros_meta_cache = [];

function get_nav_meta($menu_item_id) {
	global $_ros_meta_cache;
	if (isset($_ros_meta_cache[$menu_item_id])) {
		return $_ros_meta_cache[$menu_item_id];
	}
	$_ros_meta_cache[$menu_item_id] = [
		'event_length_seconds' => (int) get_post_meta($menu_item_id, '_event_length_seconds', true),
		'event_type'           => get_post_meta($menu_item_id, '_event_type', true),
		'guest_type'           => get_post_meta($menu_item_id, '_guest_type', true),
	];
	return $_ros_meta_cache[$menu_item_id];
}

// ─── Helpers ──────────────────────────────────────────────────

function title_case_type($slug) {
	if (empty($slug)) return '';
	if ($slug === 'red-carpet-interview') return 'Interview';
	$str = str_replace(['-', '_'], ' ', $slug);
	return ucwords($str);
}

function seconds_to_minutes($seconds) {
	if ($seconds <= 0) return 0;
	$min = $seconds / 60;
	// Keep fractional values like 0.75; ceil whole-minute boundaries
	if ($min == floor($min)) return (int) $min;
	// Round to 2 decimal places; use ceil only for near-whole values
	$rounded = round($min, 2);
	return ($rounded == ceil($rounded)) ? (int) ceil($rounded) : $rounded;
}

// ─── Menu tree ────────────────────────────────────────────────

function get_menu_tree($slug) {
	$items = wp_get_nav_menu_items($slug);
	if (!$items) return [];
	return build_hierarchy($items);
}

function build_hierarchy($items) {
	$children = [];
	$top = [];

	// Index children by parent
	foreach ($items as $item) {
		$pid = (int) $item->menu_item_parent;
		if (!isset($children[$pid])) {
			$children[$pid] = [];
		}
		$children[$pid][] = $item;
	}

	// Recursive tree builder
	$build = function ($parent_id) use (&$build, &$children) {
		if (!isset($children[$parent_id])) return [];
		$nodes = [];
		foreach ($children[$parent_id] as $item) {
			$node = [
				'item'     => $item,
				'id'       => (int) $item->ID,
				'title'    => $item->title,
				'object'   => $item->object,
				'children' => $build((int) $item->ID),
			];
			$nodes[] = $node;
		}
		return $nodes;
	};

	return $build(0);
}

// ─── Talent collection ────────────────────────────────────────

/**
 * Collect profile names from a flat array of nav menu item IDs,
 * filtered by allowed guest_type values.
 */
function collect_profiles_by_guest_type($node_ids, $types_array) {
	$names = [];
	foreach ($node_ids as $id) {
		$post_type = get_post_meta($id, '_menu_item_object', true);
		if ($post_type !== 'profile') continue;
		$meta = get_nav_meta($id);
		if (in_array($meta['guest_type'], $types_array, true)) {
			$item_title = get_the_title(get_post_meta($id, '_menu_item_object_id', true));
			if (!empty($item_title)) {
				$names[] = $item_title;
			}
		}
	}
	return $names;
}

/**
 * Get all descendant nav_menu_item IDs from a tree node (recursive).
 */
function get_descendant_ids($node) {
	$ids = [];
	if (!empty($node['children'])) {
		foreach ($node['children'] as $child) {
			$ids[] = $child['id'];
			$ids = array_merge($ids, get_descendant_ids($child));
		}
	}
	return $ids;
}

/**
 * Get profile names from direct children of a node, filtered by guest_type.
 */
function get_child_profile_names($node, $types_array) {
	$names = [];
	if (empty($node['children'])) return $names;
	foreach ($node['children'] as $child) {
		if ($child['object'] !== 'profile') continue;
		$meta = get_nav_meta($child['id']);
		if (in_array($meta['guest_type'], $types_array, true)) {
			$names[] = $child['title'];
		}
	}
	return $names;
}

/**
 * Get all profile names from descendants of a node, filtered by guest_type.
 */
function get_descendant_profile_names($node, $types_array) {
	$names = [];
	if (empty($node['children'])) return $names;
	foreach ($node['children'] as $child) {
		if ($child['object'] === 'profile') {
			$meta = get_nav_meta($child['id']);
			if (in_array($meta['guest_type'], $types_array, true)) {
				$names[] = $child['title'];
			}
		}
		$names = array_merge($names, get_descendant_profile_names($child, $types_array));
	}
	return $names;
}

// ─── Code formatter ───────────────────────────────────────────

/**
 * Format a CODE value with zero-padded numeric portion.
 * e.g. format_code('C', 3) => 'C03', format_code('C', 3, 'A') => 'C03A'
 */
function format_code($prefix, $num, $suffix = '') {
	return $prefix . str_pad($num, 2, '0', STR_PAD_LEFT) . $suffix;
}

// ─── Row emitter ──────────────────────────────────────────────

function emit_row(&$rows, $seq, $code, $minutes, $menu, $session, $talent, $type) {
	$rows[] = implode("\t", [
		$seq,
		$code,
		'',  // TIME column — always blank
		$minutes,
		$menu,     // E: Level 2 parent title
		$session,  // F: segment/row label
		$talent,
		$type,
	]);
}

// ─── Main renderer ────────────────────────────────────────────

function render_run_of_show_tsv($red_carpet_slug, $ceremony_slug, $debug = false) {
	global $_ros_meta_cache;
	$_ros_meta_cache = []; // reset cache

	$rc_tree   = get_menu_tree($red_carpet_slug);
	$cere_tree = get_menu_tree($ceremony_slug);

	$rows = [];
	$seq  = 0; // global sequence counter

	// Header row
	$rows[] = implode("\t", ['#', 'CODE', 'TIME', 'MIN', 'MENU', 'SESSION', 'TALENT', 'TYPE']);

	// Presenter types for nomination categories
	$presenter_types = ['award-presenter', 'host', 'ambassador', 'special-guest', 'sponsor'];

	// Exploded row templates for nomination-category and honors-presentation
	$explode_templates = [
		['suffix' => 'A', 'label' => 'Host Intro',                   'seconds' => 45],
		['suffix' => 'B', 'label' => 'Presenter Intro',              'seconds' => 45],
		['suffix' => 'C', 'label' => 'Nomination Reel and Reveal',   'seconds' => 180],
		['suffix' => 'D', 'label' => 'Acceptance Speech',            'seconds' => 60],
	];

	// ─── SECTION A: Red Carpet ────────────────────────────────
	$section_num = 0;

	foreach ($rc_tree as $l1) {
		// Level 1 is the top menu group (e.g. "Virtual Red Carpet")
		if (empty($l1['children'])) continue;

		foreach ($l1['children'] as $l2) {
			// Level 2 = session
			$section_num++;
			$l2_title = $l2['title'];
			$meta = get_nav_meta($l2['id']);
			$seconds = $meta['event_length_seconds'];
			$event_type = $meta['event_type'];
			$type_label = title_case_type($event_type);

			// Check if this session has any L3 timed event children
			$has_timed_children = false;
			if (!empty($l2['children'])) {
				foreach ($l2['children'] as $l3) {
					if ($l3['object'] === 'event') {
						$l3meta = get_nav_meta($l3['id']);
						if ($l3meta['event_length_seconds'] > 0) {
							$has_timed_children = true;
							break;
						}
					}
				}
			}

			// Talent for red carpet interviews
			$talent = '';
			if ($event_type === 'red-carpet-interview') {
				$names = get_descendant_profile_names($l2, ['interviewee']);
				$talent = implode(', ', $names);
			}

			$seq++;
			$code = format_code('A', $section_num);
			emit_row($rows, $seq, $code, seconds_to_minutes($seconds), $l2_title, '', $talent, $type_label);

			// Level 3 timed events — letter suffixes on child rows
			if ($has_timed_children) {
				$sub_letter = 'A';
				foreach ($l2['children'] as $l3) {
					if ($l3['object'] !== 'event') continue;
					$l3meta = get_nav_meta($l3['id']);
					if ($l3meta['event_length_seconds'] <= 0) continue;

					$seq++;
					emit_row(
						$rows, $seq,
						format_code('A', $section_num, $sub_letter),
						seconds_to_minutes($l3meta['event_length_seconds']),
						$l2_title,
						$l3['title'],
						'',
						title_case_type($l3meta['event_type'])
					);
					$sub_letter++;
				}
			}
		}
	}

	// ─── SECTION B: Intermission ──────────────────────────────
	$seq++;
	emit_row($rows, $seq, 'B1', 5, 'Intermission', 'Audience-out', '', 'Break');
	$seq++;
	emit_row($rows, $seq, 'B2', 10, 'Intermission', 'Audience break', '', 'Break');
	$seq++;
	emit_row($rows, $seq, 'B3', 5, 'Intermission', 'Audience-in', '', 'Break');

	// ─── SECTION C: Ceremony ──────────────────────────────────
	$section_num = 0;

	foreach ($cere_tree as $l1) {
		if (empty($l1['children'])) continue;

		foreach ($l1['children'] as $l2) {
			$section_num++;
			$l2_title = $l2['title'];
			$meta = get_nav_meta($l2['id']);
			$seconds = $meta['event_length_seconds'];
			$event_type = $meta['event_type'];
			$type_label = title_case_type($event_type);

			// Explodable types
			if ($event_type === 'nomination-category' || $event_type === 'honors-presentation') {

				// Emit 4 exploded rows: A, B, C, D
				foreach ($explode_templates as $tmpl) {
					$seq++;
					$talent = '';

					// Presenter Intro row (B) gets talent for nomination categories
					if ($tmpl['suffix'] === 'B' && $event_type === 'nomination-category') {
						$names = get_descendant_profile_names($l2, $presenter_types);
						$talent = implode(', ', $names);
					}

					// Honors: honoree name on Host Intro row (A)
					if ($tmpl['suffix'] === 'A' && $event_type === 'honors-presentation') {
						$honoree_names = get_child_profile_names($l2, ['honoree']);
						$talent = implode(', ', $honoree_names);
					}

					emit_row(
						$rows, $seq,
						format_code('C', $section_num, $tmpl['suffix']),
						seconds_to_minutes($tmpl['seconds']),
						$l2_title,
						$tmpl['label'],
						$talent,
						$type_label
					);
				}

				// Continue with Level 3 timed events after exploded rows (E onwards)
				$next_letter = 'E';
				if (!empty($l2['children'])) {
					foreach ($l2['children'] as $l3) {
						if ($l3['object'] !== 'event') continue;
						$l3meta = get_nav_meta($l3['id']);
						if ($l3meta['event_length_seconds'] <= 0) continue;

						$seq++;
						emit_row(
							$rows, $seq,
							format_code('C', $section_num, $next_letter),
							seconds_to_minutes($l3meta['event_length_seconds']),
							$l2_title,
							$l3['title'],
							'',
							title_case_type($l3meta['event_type'])
						);
						$next_letter++;
					}
				}

			} else {
				// Non-explodable session — check for L3 timed children
				$has_timed_children = false;
				if (!empty($l2['children'])) {
					foreach ($l2['children'] as $l3) {
						if ($l3['object'] === 'event') {
							$l3meta = get_nav_meta($l3['id']);
							if ($l3meta['event_length_seconds'] > 0) {
								$has_timed_children = true;
								break;
							}
						}
					}
				}

				$seq++;
				$code = format_code('C', $section_num);
				emit_row($rows, $seq, $code, seconds_to_minutes($seconds), $l2_title, '', '', $type_label);

				// Level 3 timed events — letter suffixes
				if ($has_timed_children) {
					$sub_letter = 'A';
					foreach ($l2['children'] as $l3) {
						if ($l3['object'] !== 'event') continue;
						$l3meta = get_nav_meta($l3['id']);
						if ($l3meta['event_length_seconds'] <= 0) continue;

						$seq++;
						emit_row(
							$rows, $seq,
							format_code('C', $section_num, $sub_letter),
							seconds_to_minutes($l3meta['event_length_seconds']),
							$l2_title,
							$l3['title'],
							'',
							title_case_type($l3meta['event_type'])
						);
						$sub_letter++;
					}
				}
			}
		}
	}

	// ─── Output ───────────────────────────────────────────────
	$tsv = implode("\n", $rows);

	$output = '';
	if ($debug) {
		$rc_count = count($rc_tree);
		$cere_count = count($cere_tree);
		$output .= "<p>Red Carpet top-level items: {$rc_count}</p>\n";
		$output .= "<p>Ceremony top-level items: {$cere_count}</p>\n";
		$output .= "<p>Total rows (excl header): {$seq}</p>\n";
	}
	$output .= '<pre><code>' . esc_html($tsv) . '</code></pre>';

	return $output;
}
