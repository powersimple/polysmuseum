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

/**
 * =============================================================================
 * Run of Show menu rendering (the generic default)
 * -----------------------------------------------------------------------------
 * get_menu_array() (functions-navigation.php) is the generic tree builder:
 *   Level 1 = event   (post_type event)
 *   Level 2 = session (post_type event)
 *   Level 3 = speaker (post_type profile)
 *
 * get_run_of_show_menu( $menu, $section_class, $run_of_show ) renders that tree.
 *
 * Run of Show is OPT-IN via the "Event Menu" checkbox (event_menu meta), passed as
 * $run_of_show. When ON, section_class is a SINGLE formatting value, emitted as a
 * CSS class on the container so each variation can be styled:
 *
 *   (blank)        → generic run of show (all sessions + speakers)
 *   metatraversal  → generic + .metatraversal style hook (CSS only)
 *   red-carpet     → generic + .red-carpet style hook (CSS only)
 *   ceremony       → only level-2 sessions classed 'nomination'/'honor' render,
 *                    plus the .ceremony style hook (laurel styling in CSS).
 *
 * When the checkbox is OFF ($run_of_show false), rendering falls back to the
 * original awards page (templates/awards.php + functions-awards.php), unchanged —
 * so existing ceremonies are undisturbed.
 *
 * NOTE: keep section_class single-valued. It is compared as an exact string in
 * several places (front-page.php, header.php, page.php, page-watch.php,
 * functions-megamenu.php); putting multiple classes there breaks those checks.
 * Brand is a SEPARATE concern — use the brand_key field, not section_class.
 *
 * Any level-2 session classed 'hide' is skipped in every mode.
 * =============================================================================
 */
if ( ! function_exists( 'get_run_of_show_menu' ) ) {
	function get_run_of_show_menu( $menu, $section_class = '', $run_of_show = false ) {
		if ( empty( $menu ) || ! function_exists( 'get_menu_array' ) ) {
			return '';
		}
		$tree = get_menu_array( $menu );
		if ( empty( $tree ) ) {
			return '';
		}

		// section_class is a single formatting value (ceremony/red-carpet/metatraversal).
		$is_ceremony = ( strpos( strtolower( (string) $section_class ), 'ceremony' ) !== false );

		// Run of Show is opt-in via the Event Menu checkbox. When on, section_class
		// only selects the formatting variation.
		if ( $run_of_show ) {
			return render_run_of_show_menu( $tree, $section_class, $is_ceremony );
		}

		// Checkbox off → legacy awards page (unchanged for existing ceremonies).
		require_once get_template_directory() . '/functions/functions-awards.php';
		$awards = $tree; // templates/awards.php iterates $awards.
		ob_start();
		require get_template_directory() . '/templates/awards.php';
		return ob_get_clean();
	}
}

/**
 * Generic run-of-show renderer. $section_class is emitted as container classes so
 * variations (metatraversal, red-carpet, ceremony) are styleable. When $ceremony
 * is true only 'nomination'/'honor' sessions render. 'hide' sessions are skipped.
 */
if ( ! function_exists( 'render_run_of_show_menu' ) ) {
	function render_run_of_show_menu( $tree, $section_class = '', $ceremony = false ) {
		if ( empty( $tree ) ) {
			return '';
		}
		$is_past_event = true;

		ob_start();
		?>
		<div id="schedule" class="run-of-show <?php echo esc_attr( $section_class ); ?>">
			<?php foreach ( $tree as $event ) : ?>
				<?php
				if ( empty( $event['children'] ) ) {
					continue;
				}
				foreach ( $event['children'] as $session ) :
					$sclasses = ros_node_classes( $session );
					// Always skip hidden sessions.
					if ( in_array( 'hide', $sclasses, true ) ) {
						continue;
					}
					// Ceremony treatment: only award categories (nomination/honor).
					if ( $ceremony && ! array_intersect( array( 'nomination', 'honor' ), $sclasses ) ) {
						continue;
					}
					echo render_event_session_row( $session, $is_past_event );
				endforeach;
				?>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}

/**
 * Flatten a menu node's classes to a simple array of strings.
 */
if ( ! function_exists( 'ros_node_classes' ) ) {
	function ros_node_classes( $node ) {
		$c = isset( $node['classes'] ) ? $node['classes'] : array();
		if ( is_array( $c ) ) {
			return array_values( array_filter( array_map( 'strval', $c ) ) );
		}
		return array_values( array_filter( explode( ' ', (string) $c ) ) );
	}
}

/**
 * Normalise a video URL with autoplay params (used by session Watch buttons).
 */
if ( ! function_exists( 'event_format_video_url' ) ) {
	function event_format_video_url( $url ) {
		if ( empty( $url ) ) {
			return '';
		}
		// Force www.youtube.com — the www-less host redirects inside the iframe
		// and autoplay is dropped on that redirect (video cues but won't play).
		$url = preg_replace( '#^(https?://)(?:www\.)?youtube\.com/#i', '$1www.youtube.com/', $url );
		// youtu.be/<id> short links → proper embed URL.
		$url = preg_replace( '#^(https?://)(?:www\.)?youtu\.be/([A-Za-z0-9_-]+)#i', '$1www.youtube.com/embed/$2', $url );
		if ( strpos( $url, '?' ) !== false ) {
			if ( strpos( $url, 'autoplay' ) === false ) {
				$url .= '&autoplay=1&rel=0';
			}
		} else {
			$url .= '?autoplay=1&rel=0';
		}
		return $url;
	}
}

/**
 * Render one session row (level 2) plus its speakers (level 3).
 */
if ( ! function_exists( 'render_event_session_row' ) ) {
	function render_event_session_row( $session, $is_past_event = false ) {
		$session_title   = esc_html( $session['title'] );
		$session_slug    = $session['slug'];
		$session_content = @$session['post']->post_content;
		$session_video   = @$session['meta']['embed_video_url'][0];
		$classes         = is_array( $session['classes'] ) ? implode( ' ', $session['classes'] ) : @$session['classes'];

		ob_start();
		?>
		<div id="<?php echo esc_attr( $session_slug ); ?>" class="row session <?php echo esc_attr( $classes ); ?>">
			<div class="col-sm-3 col-md-2">
				<?php if ( $is_past_event && ! empty( $session_video ) ) : ?>
				<a href="#<?php echo esc_attr( $session_slug ); ?>"
				   class="watch video-button"
				   onclick="playSessionVideo('<?php echo esc_js( event_format_video_url( $session_video ) ); ?>','<?php echo esc_js( $session_title ); ?>','')">
					<i title="WATCH" class="fa-brands fa-youtube"></i><br> Watch
				</a>
				<?php endif; ?>
			</div>
			<div class="col-sm-9 col-md-10">
				<h3 class="session-title"><?php echo $session_title; ?></h3>
				<?php if ( ! empty( $session_content ) ) : ?>
				<div class="session-content"><?php echo do_blocks( $session_content ); ?></div>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( ! empty( $session['children'] ) ) : ?>
		<div class="row">
			<div class="col-12">
				<div class="row speaker-list">
					<?php foreach ( $session['children'] as $profile ) : ?>
						<?php echo render_event_profile_card( $profile ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}
}

/**
 * Render one speaker/profile card (level 3).
 */
if ( ! function_exists( 'render_event_profile_card' ) ) {
	function render_event_profile_card( $profile ) {
		$meta          = $profile['meta'];
		$thumbnail     = getThumbnail( @$meta['_thumbnail_id'][0], 'thumbnail' );
		$title         = esc_html( $profile['title'] );
		$profile_title = @$meta['profile_title'][0];
		$company       = @$meta['company'][0];
		$twitter       = @$meta['twitter'][0];
		$linkedin      = @$meta['linkedin'][0];
		$github        = @$meta['github'][0];
		$classes       = is_array( $profile['classes'] ) ? implode( ' ', $profile['classes'] ) : $profile['classes'];

		ob_start();
		?>
		<div class="profile-card col <?php echo esc_attr( $classes ); ?>">
			<?php if ( $thumbnail ) : ?>
			<div class="profile-thumbnail">
				<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo $title; ?>" title="<?php echo $title; ?>">
			</div>
			<?php endif; ?>
			<span class="profile-info">
				<span class="profile-name"><?php echo $title; ?></span>
				<?php if ( $profile_title || $company ) : ?>
				<span class="credential">
					<?php if ( $profile_title ) : ?>
					<span><?php echo esc_html( trim( $profile_title ) ); ?></span>
					<?php endif; ?>
					<?php if ( $company ) : ?>
					<span><?php echo esc_html( trim( $company ) ); ?></span>
					<?php endif; ?>
				</span>
				<?php endif; ?>
				<?php if ( $twitter || $linkedin || $github ) : ?>
				<span class="social">
					<?php if ( $twitter ) : ?>
					<a target="_blank" class="twitter" href="<?php echo esc_url( $twitter ); ?>">
						<i class="fa-brands fa-x-twitter social-icon" title="<?php echo $title; ?> on Twitter"></i>
					</a>
					<?php endif; ?>
					<?php if ( $linkedin ) : ?>
					<a target="_blank" class="linkedin" href="<?php echo esc_url( $linkedin ); ?>">
						<i class="fa-brands fa-linkedin social-icon" title="<?php echo $title; ?> on LinkedIn"></i>
					</a>
					<?php endif; ?>
					<?php if ( $github ) : ?>
					<a target="_blank" class="github" href="<?php echo esc_url( $github ); ?>">
						<i class="fa-brands fa-github social-icon" title="<?php echo $title; ?> on GitHub"></i>
					</a>
					<?php endif; ?>
				</span>
				<?php endif; ?>
			</span>
		</div>
		<?php
		return ob_get_clean();
	}
}
