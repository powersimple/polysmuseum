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

    // Helpers for multi-image handling and fuzzy ordering by names
    $fetch_images = function($post_id, $meta_key) {
        $imgs = array();
        if (empty($post_id)) { return $imgs; }
        // Get all values for this meta key to ensure multiple attachments are retrieved
        $raw_values = get_post_meta($post_id, $meta_key, false);
        $ids = array();
        foreach ((array)$raw_values as $val) {
            if (is_array($val)) {
                // Meta Box image_advanced typically stores a single serialized array in one row
                foreach ($val as $m) {
                    if (is_array($m) && isset($m['ID'])) { $ids[] = intval($m['ID']); }
                    elseif (is_numeric($m)) { $ids[] = intval($m); }
                }
            } elseif (is_numeric($val)) {
                $ids[] = intval($val);
            }
        }
        // Fallback: if no IDs and single fetch returns a structured array
        if (empty($ids)) {
            $single = get_post_meta($post_id, $meta_key, true);
            if (is_array($single)) {
                foreach ($single as $m) {
                    if (is_array($m) && isset($m['ID'])) { $ids[] = intval($m['ID']); }
                    elseif (is_numeric($m)) { $ids[] = intval($m); }
                }
            } elseif (is_numeric($single)) {
                $ids[] = intval($single);
            }
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

    $normalize = function($s) { return preg_replace('/[^a-z0-9]+/','', strtolower((string)$s)); };
    $score_match = function($name, $image) use ($normalize) {
        $title = $normalize($image['title'] . ' ' . $image['filename']);
        $tokens = preg_split('/\s+/u', strtolower((string)$name));
        $score = 0;
        foreach ($tokens as $tok) {
            $tok = preg_replace('/[^a-z0-9]+/','', $tok);
            if (strlen($tok) >= 3 && $tok !== '' && strpos($title, $tok) !== false) { $score++; }
        }
        return $score;
    };
    $order_images_by_names = function($images, $names) use ($score_match) {
        if (empty($images) || empty($names)) { return $images; }
        $remaining = $images;
        $ordered = array();
        foreach ($names as $nm) {
            $best_i = -1; $best_s = 0;
            foreach ($remaining as $i => $img) {
                $s = $score_match($nm, $img);
                if ($s > $best_s) { $best_s = $s; $best_i = $i; }
            }
            if ($best_i >= 0 && $best_s > 0) {
                $ordered[] = $remaining[$best_i];
                array_splice($remaining, $best_i, 1);
            }
        }
        // Append any leftover images
        return array_merge($ordered, $remaining);
    };

    // Presenter images from event meta (allow multiple) with ordering by presenter names
    $presenter_images = array();
    if (!empty($award['object_id'])) {
        $presenter_images = $fetch_images($award['object_id'], 'presenter_image');
    }
    // Fallback to single URL in award struct if meta is not populated
    if (empty($presenter_images) && !empty($award['presenter_image_url'])) {
        $presenter_images = array(array('id'=>0,'url'=>$award['presenter_image_url'],'title'=>'','filename'=>''));
    }
    if (!empty($award['presenters']) && is_array($award['presenters']) && !empty($presenter_images)) {
        $presenter_images = $order_images_by_names($presenter_images, $award['presenters']);
    }

    // Acceptance images: collect from event and winner objects, deduplicate by URL
    $acceptance_images = array();
    $acceptance_by_url = array();
    $push_imgs = function($imgs) use (&$acceptance_images, &$acceptance_by_url) {
        foreach ((array)$imgs as $im) {
            if (empty($im['url'])) { continue; }
            $u = (string)$im['url'];
            if (isset($acceptance_by_url[$u])) { continue; }
            $acceptance_by_url[$u] = true;
            $acceptance_images[] = $im;
        }
    };
    if (!empty($award['object_id'])) {
        $push_imgs($fetch_images($award['object_id'], 'acceptance_image'));
    }
    if (!empty($award['winner_ids']) && is_array($award['winner_ids'])) {
        foreach ($award['winner_ids'] as $wid) {
            $wid = intval($wid);
            if ($wid) { $push_imgs($fetch_images($wid, 'acceptance_image')); }
        }
    }
    if (empty($acceptance_images) && !empty($award['acceptance_image_url'])) {
        $acceptance_images = array(array('id'=>0,'url'=>$award['acceptance_image_url'],'title'=>'','filename'=>''));
    }
    // Collect recipient names from winners (prefer people; fallback to company, then title)
    $recipient_names = array();
    if (!empty($award['winners']) && is_array($award['winners'])) {
        foreach ($award['winners'] as $w) {
            $added = false;
            if (!empty($w['people']) && is_array($w['people'])) {
                foreach ($w['people'] as $pn) {
                    $pn = trim((string)$pn);
                    if ($pn !== '') { $recipient_names[] = $pn; $added = true; }
                }
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
    if (!empty($recipient_names) && !empty($acceptance_images)) {
        $acceptance_images = $order_images_by_names($acceptance_images, $recipient_names);
    }

    // Determine if this award is an Honoree BEFORE any use
    // Also treat 'XR Elevation' awards as honorees (no nominees, show event content)
    $is_xr_elevation = (stripos($award_title, 'xr elevation') !== false);
    $is_honoree = (isset($award['award_type']) && $award['award_type'] === 'HONOREE') || $is_xr_elevation;

    $presenters_count = (!empty($award['presenters']) && is_array($award['presenters'])) ? count($award['presenters']) : 0;
    $multi_presenters = ($presenters_count > 1);

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
            $presenters_line = $presenter_pieces[0] . '<br>and ' . $presenter_pieces[1];
        } else {
            $last = array_pop($presenter_pieces);
            $presenters_line = implode(', ', $presenter_pieces) . '<br>and ' . $last;
        }
    }

    // Winners: base title + acceptance images, and lines per winner
    $winner_lines = array();
    $base_title = (!empty($award['winners'][0]['title'])) ? trim((string)$award['winners'][0]['title']) : '';
    $hero_img_url = '';
    $hero_img_url_b = '';
    // Special case flag: two honorees but only one acceptance image that matches an honoree name
    $special_accept_hero_url = '';
    // Determine if this award represents two honoree profiles (two level-2 honorees collected)
    $duo_honoree = ($is_honoree && isset($award['winners']) && is_array($award['winners']) && count($award['winners']) >= 2);
    $forced_hero_from_accept = false;
    // Build acceptance items matched to recipient names
    $acceptance_items = array();
    if (!empty($recipient_names) && !empty($acceptance_images)) {
        // Map names to best image (in order), avoiding reuse
        $remaining = $acceptance_images;
        foreach ($recipient_names as $nm) {
            $best_i = -1; $best_s = 0;
            foreach ($remaining as $i => $img) {
                $s = $score_match($nm, $img);
                if ($s > $best_s) { $best_s = $s; $best_i = $i; }
            }
            if ($best_i >= 0) {
                $img = $remaining[$best_i];
                array_splice($remaining, $best_i, 1);
                $acceptance_items[] = array('name' => $nm, 'img' => $img['url']);
            }
        }
        // Append any leftover images without names
        foreach ($remaining as $img) { $acceptance_items[] = array('name' => '', 'img' => $img['url']); }
    } elseif (!empty($acceptance_images)) {
        foreach ($acceptance_images as $img) { $acceptance_items[] = array('name' => '', 'img' => $img['url']); }
    }
    // Decide hero image per rules and render inside laurel wrapper, then title below
    if ($base_title !== '') {
        // Global single-acceptance override: one acceptance image matches the H4 base title
        if (!$duo_honoree && is_array($acceptance_images) && count($acceptance_images) === 1 && $base_title !== '') {
            $ai0 = $acceptance_images[0];
            $cap0 = '';
            if (!empty($ai0['title'])) { $cap0 = (string)$ai0['title']; }
            elseif (!empty($ai0['filename'])) { $cap0 = preg_replace('/\.[^.]+$/', '', wp_basename((string)$ai0['filename'])); }
            $cap0_norm = strtolower(trim($cap0));
            $base_norm = strtolower(trim((string)$base_title));
            if ($cap0_norm !== '' && $cap0_norm === $base_norm && !empty($ai0['url'])) {
                $hero_img_url = (string)$ai0['url'];
                $forced_hero_from_accept = true;
            }
        }
        // New conditions:
        // - If XR Elevation: FORCE single hero from featured image (no acceptance fallback, no duo)
        // - Else if HONOREE: use acceptance image logic (duo special cases)
        // - Else WINNER: try featured image of primary winner object; if none and profiles, fallback to acceptance image
        if ($is_xr_elevation) {
            // Force single hero from winner featured image (or event thumbnail) and disable duo
            $duo_honoree = false;
            if (!empty($award['winner_ids'][0])) {
                $cand = get_the_post_thumbnail_url(intval($award['winner_ids'][0]), 'full');
                if ($cand) { $hero_img_url = $cand; }
            }
            if (!$hero_img_url && !empty($award['object_id'])) {
                $cand = get_the_post_thumbnail_url(intval($award['object_id']), 'full');
                if ($cand) { $hero_img_url = $cand; }
            }
        } elseif ($is_honoree) {
            // Special two-honoree exception: if exactly one acceptance image and it matches an honoree name
            if ($duo_honoree && is_array($recipient_names) && count($recipient_names) >= 2 && is_array($acceptance_images) && count($acceptance_images) === 1) {
                $ai0 = $acceptance_images[0];
                $cap0 = '';
                if (!empty($ai0['title'])) { $cap0 = (string)$ai0['title']; }
                elseif (!empty($ai0['filename'])) { $cap0 = preg_replace('/\.[^.]+$/', '', wp_basename((string)$ai0['filename'])); }
                $cap0_norm = strtolower(trim($cap0));
                foreach ($recipient_names as $nm) {
                    if ($cap0_norm !== '' && strtolower(trim((string)$nm)) === $cap0_norm) {
                        $special_accept_hero_url = !empty($ai0['url']) ? (string)$ai0['url'] : '';
                        break;
                    }
                }
            }
            // Two honorees case: try to get two distinct hero images
            if ($duo_honoree && $special_accept_hero_url !== '') {
                $hero_img_url = $special_accept_hero_url;
                // Find a second hero from featured images of winner_ids
                if (!empty($award['winner_ids']) && is_array($award['winner_ids'])) {
                    foreach ($award['winner_ids'] as $wid) {
                        $cand = get_the_post_thumbnail_url(intval($wid), 'full');
                        if ($cand && $cand !== $hero_img_url) { $hero_img_url_b = $cand; break; }
                    }
                }
            } elseif ($duo_honoree && !empty($acceptance_items)) {
                $hero_img_url = $acceptance_items[0]['img'];
                if (count($acceptance_items) > 1) { $hero_img_url_b = $acceptance_items[1]['img']; }
            }
            // Fallbacks from featured images if needed (don't override a forced acceptance hero)
            if ((!$hero_img_url || (!$hero_img_url_b && $duo_honoree)) && !empty($award['winner_ids']) && is_array($award['winner_ids'])) {
                foreach ($award['winner_ids'] as $wid) {
                    if ($duo_honoree) { if ($hero_img_url && $hero_img_url_b) { break; } }
                    else { if ($hero_img_url) { break; } }
                    $cand = get_the_post_thumbnail_url(intval($wid), 'full');
                    if ($cand && !$forced_hero_from_accept) {
                        if (!$hero_img_url) { $hero_img_url = $cand; }
                        elseif (!$hero_img_url_b && $cand !== $hero_img_url) { $hero_img_url_b = $cand; }
                    }
                }
            }
        } else {
            // Attempt featured image of main winner object
            if (!$forced_hero_from_accept && !empty($award['winner_ids'][0])) {
                $cand = get_the_post_thumbnail_url($award['winner_ids'][0], 'full');
                if ($cand) { $hero_img_url = $cand; }
            }
            // Fallbacks
            if (!$hero_img_url && !empty($acceptance_items)) { $hero_img_url = $acceptance_items[0]['img']; }
        }

        $hero_html = '';
        if ($duo_honoree && $hero_img_url && $hero_img_url_b) {
            // Dual hero images for two honorees
            $hero_html = '<div class="ex-winner-hero-wrap"><div class="ex-winner-hero duo">'
                       . '<img class="ex-winner-hero-img hero-a" src="' . esc_url($hero_img_url) . '" alt="" />'
                       . '<img class="ex-winner-hero-img hero-b" src="' . esc_url($hero_img_url_b) . '" alt="" />'
                       . '</div></div>';
            $used_thumbs[$hero_img_url] = true;
            $used_thumbs[$hero_img_url_b] = true;
        } else if ($hero_img_url && !isset($used_thumbs[$hero_img_url])) {
            $hero_html = '<div class="ex-winner-hero-wrap"><div class="ex-winner-hero"><img class="ex-winner-hero-img" src="' . esc_url($hero_img_url) . '" alt="" /></div></div>';
            $used_thumbs[$hero_img_url] = true;
        }
        // Build H4: if dual honoree, print both names as "Name1 and<br>Name2"
        $h4_inner = esc_html($base_title);
        if ($duo_honoree) {
            $name1 = isset($award['winners'][0]['title']) ? trim((string)$award['winners'][0]['title']) : '';
            $name2 = isset($award['winners'][1]['title']) ? trim((string)$award['winners'][1]['title']) : '';
            if ($name1 !== '' && $name2 !== '') {
                $h4_inner = esc_html($name1) . ' and<br>' . esc_html($name2);
            }
        }
        $winner_lines[] = $hero_html . '<h4 class="ex-winner-base"><span>' . $h4_inner . '</span></h4>';
    }
    // Prepare acceptance images for a right-side wrapper (include all acceptance images)
    $acceptance_remaining = $acceptance_images;
    // If we forced the hero from acceptance (single-person match), suppress the right rail entirely
    if (!$duo_honoree && $forced_hero_from_accept) {
        $acceptance_remaining = array();
    }
    // If we used the single acceptance image as a hero in the two-honoree special case, don't render it again
    if ($duo_honoree && !empty($special_accept_hero_url) && is_array($acceptance_remaining) && count($acceptance_remaining) === 1) {
        $only = $acceptance_remaining[0];
        if (!empty($only['url']) && (string)$only['url'] === (string)$special_accept_hero_url) {
            $acceptance_remaining = array();
        }
    }

    if (!empty($award['winners']) && is_array($award['winners'])) {
        $company_parts = array();
        foreach ($award['winners'] as $wi => $winner) {
            $company = isset($winner['company']) ? trim((string)$winner['company']) : '';
            $people = isset($winner['people']) && is_array($winner['people']) ? $winner['people'] : array();
            $people_names = array();
            if (!empty($people)) {
                foreach ($people as $pn_raw) {
                    $pn = trim((string)$pn_raw);
                    if ($pn === '') { continue; }
                    $people_names[] = $pn;
                }
            }
            if ($company !== '' || !empty($people_names)) {
                $people_html = '';
                if (!empty($people_names)) {
                    $prefix = ($company !== '' ? ': ' : '');
                    $buf = '';
                    $last_i = count($people_names) - 1;
                    foreach ($people_names as $i => $nm) {
                        $label = esc_html($nm) . ($i !== $last_i ? ', ' : '');
                        $buf .= '<span class="ex-winner-person">' . $label . '</span>';
                    }
                    $people_html = $prefix . $buf;
                }
                $by_prefix = (empty($company_parts) && $company !== '') ? 'by ' : '';
                $part = ($company !== '' ? $by_prefix . esc_html($company) : '') . $people_html;
                if ($part !== '') { $company_parts[] = $part; }
            }
        }
        if (!empty($company_parts)) {
            $winner_lines[] = '<h5 class="ex-winner-company">' . implode('', $company_parts) . '</h5>';
        }
    }

    // $is_honoree already determined above

    // Nominees: prefer awards summary computed groups/text when available, fallback to local construction
    $nominees_html = '';
    $nominees_count = 0;
    if (!empty($award['nominees_groups']) && is_array($award['nominees_groups'])) {
        $group_lines = array();
        foreach ($award['nominees_groups'] as $g) {
            $label = isset($g['label']) ? trim((string)$g['label']) : '';
            $items = isset($g['items']) && is_array($g['items']) ? $g['items'] : array();
            if (!empty($items)) { $nominees_count += count($items); }
            $line = '';
            if ($label !== '') { $line .= $label; }
            if (!empty($items)) {
                $line .= ($label !== '' ? ': ' : '') . implode(', ', $items);
            }
            if ($line !== '') { $group_lines[] = $line; }
        }
        if (!empty($group_lines)) { $nominees_html = implode('<br>', $group_lines); }
    } elseif (!empty($award['nominees_text'])) {
        $nominees_html = esc_html((string)$award['nominees_text']);
    } else {
        // Fallback: build from nominees structure if present (no thumbnails)
        $nominee_items = array();
        if (!empty($award['nominees']) && is_array($award['nominees'])) {
            $winner_id_set = array();
            if (!empty($award['winner_ids']) && is_array($award['winner_ids']) && !$is_honoree) {
                foreach ($award['winner_ids'] as $wid) { $winner_id_set[intval($wid)] = true; }
            }
            foreach ($award['nominees'] as $nom) {
                $n_id = 0;
                if (isset($nom['id'])) { $n_id = intval($nom['id']); }
                elseif (isset($nom['ID'])) { $n_id = intval($nom['ID']); }
                elseif (isset($nom['object_id'])) { $n_id = intval($nom['object_id']); }
                elseif (isset($nom['post_id'])) { $n_id = intval($nom['post_id']); }
                $n_title = isset($nom['title']) ? trim((string)$nom['title']) : '';
                $n_company = isset($nom['company']) ? trim((string)$nom['company']) : '';
                $n_people = isset($nom['people']) && is_array($nom['people']) ? $nom['people'] : array();
                $text = '';
                if ($n_title !== '') { $text .= esc_html($n_title); }
                if ($n_company !== '') { $text .= ($text !== '' ? ' ' : '') . 'by ' . esc_html($n_company); }
                if (!empty($n_people)) { $text .= (!empty($n_company) ? ': ' : ' ') . esc_html(implode(', ', $n_people)); }
                if ($text === '') { continue; }
                $nominee_items[] = '<div class="ex-nominee-item"><span class="ex-nominee-text">' . $text . '</span></div>';
            }
        }
        if (!empty($nominee_items)) { $nominees_html = implode('', $nominee_items); $nominees_count = count($nominee_items); }
    }

    // Build tile HTML
    $html = '';
    $html .= '<div class="ex-line ex-award-number">' . $num . '</div>';
    // Event logo (global for the event) as second element
    if (!empty($award['event_logo_url'])) {
        $html .= '<div class="ex-event-logo"><img src="' . esc_url($award['event_logo_url']) . '" alt="Event Logo" /></div>';
    }
    $html .= '<div class="ex-line ex-award-name">' . esc_html($award_title) . '</div>';
    // Presenter block: pair images to presenter names with wrappers
    $presenter_items = array();
    if (!empty($award['presenters']) && !empty($presenter_images)) {
        $remaining = $presenter_images;
        foreach ($award['presenters'] as $pname) {
            $best_i = -1; $best_s = 0;
            foreach ($remaining as $i => $img) {
                $s = $score_match($pname, $img);
                if ($s > $best_s) { $best_s = $s; $best_i = $i; }
            }
            if ($best_i >= 0) {
                $img = $remaining[$best_i];
                array_splice($remaining, $best_i, 1);
                $presenter_items[] = array('name' => $pname, 'img' => $img['url']);
            } else {
                $presenter_items[] = array('name' => $pname, 'img' => '');
            }
        }
        // any leftover images
        foreach ($remaining as $img) { $presenter_items[] = array('name' => '', 'img' => $img['url']); }
    }
    $presenter_label = ($presenters_line !== '') ? ('Presented by: ' . $presenters_line) : '';
    if (!empty($presenter_items) || $presenter_label !== '') {
        $html .= '<div class="ex-presenter-block">';
        if (!empty($presenter_items)) {
            $single_class = (count($presenter_items) === 1) ? ' single' : '';
            $html .= '<div class="ex-presenter-list' . $single_class . '">';
            foreach ($presenter_items as $it) {
                $img_html = $it['img'] ? '<img class="ex-presenter-img" src="' . esc_url($it['img']) . '" alt="" />' : '';
                $html .= '<div class="ex-presenter-item">' . $img_html . '</div>';
            }
            $html .= '</div>';
        }
        if ($presenter_label !== '') {
            // Allow only <br> tags in the presenter label
            $html .= '<div class="ex-presenter-label">' . wp_kses($presenter_label, array('br' => array())) . '</div>';
        }
        $html .= '</div>';
    }
    // Remove label line and render winners list directly
    if (!empty($winner_lines)) { $html .= '<div class="ex-line ex-winners-list">' . implode('', $winner_lines) . '</div>'; }
    // Left-side trophy wrapper from poly_trophy_image
    $trophy_imgs = array();
    if (!empty($award['object_id'])) {
        $trophy_imgs = $fetch_images($award['object_id'], 'poly_trophy_image');
    }
    if (!empty($trophy_imgs)) {
        $html .= '<div class="ex-trophy-left-wrap">'
              . '<img class="ex-trophy-left-img" src="' . esc_url($trophy_imgs[0]['url']) . '" alt="" />'
              . '</div>';
    }

    // Right-side acceptance wrapper with remaining images, vertical list with captions
    if (!empty($acceptance_remaining)) {
        $wrap_class = (is_array($acceptance_remaining) && count($acceptance_remaining) >= 4) ? ' compact' : '';
        $html .= '<div class="ex-acceptance-right-wrap' . $wrap_class . '">';
        $html .= '<div class="ex-acceptance-header">Accepted by</div>';
        foreach ($acceptance_remaining as $ai) {
            if (empty($ai['url'])) { continue; }
            $cap = '';
            if (!empty($ai['title'])) { $cap = $ai['title']; }
            elseif (!empty($ai['filename'])) {
                $fn = (string)$ai['filename'];
                $cap = preg_replace('/\.[^.]+$/', '', wp_basename($fn));
            }
            $html .= '<div class="ex-acceptance-item">'
                  . '<img class="ex-acceptance-right-img" src="' . esc_url($ai['url']) . '" alt="" />'
                  . ($cap !== '' ? '<div class="ex-acceptance-caption">' . esc_html($cap) . '</div>' : '')
                  . '</div>';
        }
        $html .= '</div>';
    }
    if (!$is_honoree) {
        $html .= '<div class="ex-line ex-nominees"><h6>Nominees</h6></div>';
        if (!empty($nominees_html) && !empty($award['presenters']) && is_array($award['presenters'])) {
            $pnames = array_map('trim', $award['presenters']);
            $lines = explode('<br>', $nominees_html);
            foreach ($lines as &$ln) {
                $stripped = strip_tags(trim($ln));
                foreach ($pnames as $pn) {
                    if ($pn !== '' && strpos($stripped, $pn) !== false) {
                        $ln = '<span class="presenter">' . $ln . '</span>';
                        break;
                    }
                }
            }
            unset($ln);
            $nominees_html = implode('<br>', $lines);
        }
        // Wrap everything after the first colon in each line in span.creators
        if (!empty($nominees_html)) {
            $lines = explode('<br>', $nominees_html);
            foreach ($lines as &$ln) {
                $colonPos = strpos($ln, ':');
                if ($colonPos !== false) {
                    $before = substr($ln, 0, $colonPos);
                    $after = substr($ln, $colonPos);
                    $ln = $before . '<span class="creators">' . $after . '</span>';
                }
            }
            unset($ln);
            $nominees_html = implode('<br>', $lines);
        }
        if (!empty($nominees_html)) { $html .= '<div class="ex-line ex-nominees-list">' . $nominees_html . '</div>'; }
    } else {
        // Honorees: show Level 2 event post content where nominees would be
        if (!empty($award['object_id'])) {
            $lvl2_content = get_post_field('post_content', intval($award['object_id']));
            if (!empty($lvl2_content)) {
                $html .= '<div class="ex-honors-content">' . apply_filters('the_content', $lvl2_content) . '</div>';
            }
        }
    }

    $bg_style = '';
    if (!empty($award['tile_bg_url'])) {
        $bg_style = ' style="background-image:url(' . esc_url($award['tile_bg_url']) . ');background-size:cover;background-position:center;background-repeat:no-repeat;"';
    }
    $type_class = $is_honoree ? ' ex-honoree' : ' ex-winner';
    $presenter_class = $multi_presenters ? ' has-multiple-presenters' : '';
    $many_nominees_class = ($nominees_count >= 5) ? ' many-nominees' : '';
    return '<div class="exhibit-tile"><div class="exhibit-content' . $type_class . $presenter_class . $many_nominees_class . '"' . $bg_style . '>' . $html . '</div></div>';
}

/**
 * Check if a slug is a strict Polys slug like `polys3` (number only),
 * excluding variants like `polys3-credits`.
 *
 * @param string $slug
 * @return bool
 */
function exhibits_is_strict_polys_slug($slug) {
    $s = strtolower((string)$slug);
    return (bool) preg_match('/^polys-?\d+$/i', $s);
}

/**
 * Given a list of slugs (e.g., from resolve_menu_slugs),
 * filter them to strict Polys slugs only when appropriate.
 *
 * @param array $slugs
 * @return array
 */
function exhibits_filter_polys_slugs(array $slugs) {
    $allowed = array();
    foreach ($slugs as $s) {
        $norm = strtolower((string)$s);
        // support optional hyphen, exact match polys1..polys6 only
        if (preg_match('/^polys-?(\d+)$/i', $norm, $m)) {
            $n = intval($m[1]);
            if ($n >= 1 && $n <= 6) { $allowed[] = $s; }
        }
    }
    return $allowed;
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

    // Only apply strict filtering when using a Polys wildcard like "polys*" (case-insensitive).
    if (is_string($menu_slug)) {
        $ms = strtolower($menu_slug);
        if (strpos($ms, 'polys') === 0 && strpos($ms, '*') !== false) {
            return exhibits_filter_polys_slugs(is_array($pre_resolved) ? $pre_resolved : array());
        }
    }

    return is_array($pre_resolved) ? $pre_resolved : array();
}

