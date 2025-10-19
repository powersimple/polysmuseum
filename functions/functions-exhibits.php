<?php
/**
 * Exhibits Functions
 *
 * Helpers specific to the Exhibits page/template.
 */

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Render a single Award Exhibits tile given an award data array.
 * This mirrors the tile-building logic in page-exhibits.php so it can be reused
 * from other templates (e.g., single event pages).
 *
 * @param array $award Structured award data built by Exhibits page
 * @param int   $index 0-based index of the tile in the grid
 * @return string HTML for one tile (wrapper + content)
 */
function exhibits_render_award_tile(array $award, int $index) {
    $num = $index + 1;
    $award_title = isset($award['title']) ? trim((string)$award['title']) : '';
    $award_thumb = '';
    $used_thumbs = array(); // ensure unique thumbnails per tile
    if (!empty($award['object_id'])) {
        $t = get_the_post_thumbnail_url($award['object_id'], 'full');
        if ($t && !isset($used_thumbs[$t])) { $award_thumb = '<img class="ex-thumb" src="' . esc_url($t) . '" alt="" />'; $used_thumbs[$t] = true; }
    }

    // Presenter image from event and names (no per-presenter thumbs)
    $presenter_event_img = '';
    if (!empty($award['presenter_image_url']) && !isset($used_thumbs[$award['presenter_image_url']])) {
        $presenter_event_img = '<img class="ex-thumb" src="' . esc_url($award['presenter_image_url']) . '" alt="" />';
        $used_thumbs[$award['presenter_image_url']] = true;
    }

    $presenter_pieces = array();
    if (!empty($award['presenters']) && is_array($award['presenters'])) {
        foreach ($award['presenters'] as $pi => $pname) {
            $pname = trim((string)$pname);
            if ($pname === '') { continue; }
            $presenter_pieces[] = esc_html($pname);
        }
    }
    $presenters_line = '';
    if (!empty($presenter_pieces)) {
        if (count($presenter_pieces) === 1) {
            $presenters_line = $presenter_pieces[0];
        } else if (count($presenter_pieces) === 2) {
            $presenters_line = $presenter_pieces[0] . ' and ' . $presenter_pieces[1];
        } else {
            $last = array_pop($presenter_pieces);
            $presenters_line = implode(', ', $presenter_pieces) . ' and ' . $last;
        }
    }

    // Winners: base title + acceptance image, and lines per winner
    $winner_lines = array();
    $base_title = (!empty($award['winners'][0]['title'])) ? trim((string)$award['winners'][0]['title']) : '';
    if ($base_title !== '') {
        $acceptance_img_html = '';
        if (!empty($award['acceptance_image_url']) && !isset($used_thumbs[$award['acceptance_image_url']])) {
            $acceptance_img_html = '<img class="ex-thumb" src="' . esc_url($award['acceptance_image_url']) . '" alt="" />';
            $used_thumbs[$award['acceptance_image_url']] = true;
        }
        $winner_lines[] = '<span class="ex-winner-base">' . esc_html($base_title) . '</span>' . ($acceptance_img_html ?: '');
    }
    if (!empty($award['winners']) && is_array($award['winners'])) {
        foreach ($award['winners'] as $wi => $winner) {
            $company = isset($winner['company']) ? trim((string)$winner['company']) : '';
            $people = isset($winner['people']) && is_array($winner['people']) ? $winner['people'] : array();
            $thumb = '';
            if (!empty($award['winner_ids'][$wi])) {
                $wt = get_the_post_thumbnail_url($award['winner_ids'][$wi], 'full');
                if ($wt && !isset($used_thumbs[$wt])) { $thumb = '<img class="ex-thumb" src="' . esc_url($wt) . '" alt="" />'; $used_thumbs[$wt] = true; }
            }
            $line = '';
            if ($company !== '') { $line .= 'by ' . esc_html($company); }
            if (!empty($people)) {
                $people_clean = array();
                foreach ($people as $pn) { $pn = trim((string)$pn); if ($pn !== '') { $people_clean[] = $pn; } }
                if (!empty($people_clean)) {
                    $line .= ($company !== '' ? ': ' : '') . esc_html(implode(', ', $people_clean));
                }
            }
            if ($line !== '') { $winner_lines[] = $thumb . $line; }
        }
    }

    // Nominees
    $nominee_lines = array();
    if (!empty($award['nominees']) && is_array($award['nominees'])) {
        foreach ($award['nominees'] as $nom) {
            $n_title = isset($nom['title']) ? trim((string)$nom['title']) : '';
            $n_company = isset($nom['company']) ? trim((string)$nom['company']) : '';
            $n_people = isset($nom['people']) && is_array($nom['people']) ? $nom['people'] : array();
            $thumb = '';
            if (!empty($nom['id']) && !empty($nom['post_type'])) {
                if ($nom['post_type'] === 'resource' && empty($nom['has_people'])) {
                    $nt = get_the_post_thumbnail_url(intval($nom['id']), 'full');
                    if ($nt && !isset($used_thumbs[$nt])) { $thumb = '<img class="ex-thumb" src="' . esc_url($nt) . '" alt="" />'; $used_thumbs[$nt] = true; }
                } elseif ($nom['post_type'] === 'profile') {
                    $nt = get_the_post_thumbnail_url(intval($nom['id']), 'full');
                    if ($nt && !isset($used_thumbs[$nt])) { $thumb = '<img class="ex-thumb" src="' . esc_url($nt) . '" alt="" />'; $used_thumbs[$nt] = true; }
                }
            }
            $piece = '';
            if ($n_title !== '') { $piece .= esc_html($n_title); }
            if ($n_company !== '') { $piece .= ($piece !== '' ? ' ' : '') . 'by ' . esc_html($n_company); }
            if (!empty($n_people)) { $piece .= (!empty($n_company) ? ': ' : ' ') . esc_html(implode(', ', $n_people)); }
            if ($piece !== '') { $nominee_lines[] = $thumb . $piece; }
        }
    }

    $is_honoree = (isset($award['award_type']) && $award['award_type'] === 'HONOREE');

    // Build tile HTML
    $html = '';
    $html .= '<div class="ex-line ex-award-number"><strong>Award ' . $num . ':</strong></div>';
    $html .= '<div class="ex-line ex-award-name">' . ($award_thumb ? $award_thumb : '') . esc_html($award_title) . '</div>';
    $html .= '<div class="ex-line ex-presenters"><strong>Presented by:</strong> ' . ($presenters_line !== '' ? $presenters_line : '&nbsp;') . ($presenter_event_img ? ' ' . $presenter_event_img : '') . '</div>';
    $html .= '<div class="ex-line ex-winners"><strong>' . ($is_honoree ? 'Honoree' : 'Winner') . ':</strong></div>';
    if (!empty($winner_lines)) { $html .= '<div class="ex-line ex-winners-list">' . implode('<br>', $winner_lines) . '</div>'; }
    if (!$is_honoree) {
        $html .= '<div class="ex-line ex-nominees"><strong>Nominees:</strong></div>';
        if (!empty($nominee_lines)) { $html .= '<div class="ex-line ex-nominees-list">' . implode('<br>', $nominee_lines) . '</div>'; }
    }

    return '<div class="exhibit-tile"><div class="exhibit-content">' . $html . '</div></div>';
}

/**
 * Check if a slug is a strict Polys slug like `polys3` (number only),
 * excluding variants like `polys3-credits`.
 *
 * @param string $slug
 * @return bool
 */
function exhibits_is_strict_polys_slug($slug) {
    return (bool) preg_match('/^polys\d+$/', (string)$slug);
}

/**
 * Given a list of slugs (e.g., from resolve_menu_slugs),
 * filter them to strict Polys slugs only when appropriate.
 *
 * @param array $slugs
 * @return array
 */
function exhibits_filter_polys_slugs(array $slugs) {
    return array_values(array_filter($slugs, 'exhibits_is_strict_polys_slug'));
}

/**
 * Exhibits-aware resolver. If the requested $menu_slug uses a Polys wildcard
 * (e.g., "polys*"), return only strict Polys slugs (polys + number, nothing after).
 * Otherwise, pass through results unchanged.
 *
 * @param string $menu_slug Incoming menu slug or wildcard
 * @param array|null $pre_resolved Optional pre-resolved list from resolve_menu_slugs()
 * @return array
 */
function exhibits_resolve_menu_slugs($menu_slug, $pre_resolved = null) {
    // If not provided, defer to the audit helper to resolve wildcards.
    if ($pre_resolved === null) {
        if (function_exists('resolve_menu_slugs')) {
            $pre_resolved = resolve_menu_slugs($menu_slug);
        } else {
            $pre_resolved = array($menu_slug);
        }
    }

    // Only apply strict filtering when using a Polys wildcard like "polys*".
    if (is_string($menu_slug) && strpos($menu_slug, 'polys') === 0 && strpos($menu_slug, '*') !== false) {
        return exhibits_filter_polys_slugs(is_array($pre_resolved) ? $pre_resolved : array());
    }

    return is_array($pre_resolved) ? $pre_resolved : array();
}

