<?php
/**
 * Image Audit Functions - 2-Step PLAN→APPLY Workflow
 * 
 * This tool shows a complete UPDATE PLAN of all legacy image references that
 * WILL be updated when you click Apply. The plan and apply use the SAME logic,
 * guaranteeing they always agree.
 * 
 * WORKFLOW:
 * 1. PLAN (default view): Shows ALL items that would be updated
 * 2. APPLY (with confirmation): Updates exactly those items
 * 3. After apply: Plan shows zero items (nothing left to update)
 * 
 * An item appears in the plan ONLY if:
 * 1. URL is local (in our uploads, not external CDN)
 * 2. Legacy file (.jpg/.jpeg/.png) EXISTS on disk
 * 3. Corresponding .webp file EXISTS on disk
 * 4. Legacy reference is still present in the database field
 * 
 * Exclusions (applied to both plan and apply):
 * - post_type = 'revision'
 * - post_status = 'trash'
 * - Serialized PHP data
 * - Transient and cache meta keys
 * - External URLs (CDNs, i0.wp.com, etc.)
 * 
 * Access:
 * - Tools menu: Tools -> Image Audit
 * - Querystring: &mode=imageaudit&list=dependencies on any wp-admin page
 * 
 * Safety:
 * - Apply requires checkbox confirmation + nonce
 * - Re-checks webp existence at time of update
 * - Logs all changes to wp-content/uploads/image-audit-logs/
 */

if (!defined('ABSPATH')) {
    exit;
}

function audit_images_render_attach_page($action, $is_apply, $apply_error, $baseurl, $basedir, $base_url) {
    global $wpdb;

    $log_dir = audit_images_get_log_dir();

    $apply_stats = null;
    if ($is_apply) {
        // Rebuild plan at apply-time (do not trust prior page state).
        $apply_plan = audit_images_attach_build_plan($basedir, $baseurl);
        $apply_stats = audit_images_attach_execute_apply($apply_plan, $basedir, $baseurl);
    }

    // Always build a plan for display (after APPLY, this reflects the new state).
    $plan = audit_images_attach_build_plan($basedir, $baseurl);

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Image Audit - Attachment Canonical Fix</title>
        <style>
            .audit-wrap { max-width: 1600px; margin: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .audit-wrap h1 { margin-bottom: 10px; }
            .audit-info { margin-bottom: 15px; padding: 12px; background: #e7f3ff; border-left: 4px solid #0073aa; font-size: 13px; }
            .audit-success { margin-bottom: 15px; padding: 12px; background: #d4edda; border-left: 4px solid #28a745; font-size: 13px; }
            .audit-warning { margin-bottom: 15px; padding: 12px; background: #fff3cd; border-left: 4px solid #ffc107; font-size: 13px; }
            .audit-summary { margin-bottom: 15px; padding: 12px; background: #f8f9fa; border: 1px solid #dee2e6; }
            .audit-summary p { margin: 5px 0; }
            .audit-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 20px; }
            .audit-table th, .audit-table td { padding: 6px 8px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
            .audit-table th { background: #f0f0f0; font-weight: 600; position: sticky; top: 0; }
            .audit-table tr:nth-child(even) { background: #fafafa; }
            .audit-table .url-cell { max-width: 420px; word-break: break-all; font-family: monospace; font-size: 11px; }
            .confirm-form { margin-top: 20px; padding: 15px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 4px; }
            .confirm-form label { display: block; margin-bottom: 10px; font-weight: bold; }
            .confirm-form button { padding: 12px 24px; background: #dc3545; color: #fff; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; }
            .confirm-form button:hover { background: #c82333; }
            .confirm-form button:disabled { background: #6c757d; cursor: not-allowed; }
            .empty-state { padding: 40px; text-align: center; background: #d4edda; border: 2px solid #28a745; border-radius: 4px; }
            .empty-state h2 { color: #155724; margin: 0 0 10px 0; }
            .empty-state p { color: #155724; margin: 0; }
        </style>
    </head>
    <body>
    <div class="audit-wrap">
        <h1>Image Audit — Attachment Canonical Fix (GUID + MIME + Meta)</h1>

        <div class="audit-summary">
            <p><strong>Navigation</strong></p>
            <p>
                <a href="<?php echo esc_url($base_url); ?>">Content Update Plan</a>
                |
                <a href="<?php echo esc_url(add_query_arg(['action' => 'attach_plan'], $base_url)); ?>">Attachment Canonical Fix</a>
            </p>
        </div>

        <div class="audit-info">
            <strong>DRY RUN:</strong> This scan shows attachments whose canonical file is still .jpg/.jpeg/.png but a matching .webp exists on disk.
            APPLY will update: <code>_wp_attached_file</code>, <code>_wp_attachment_metadata</code> (when possible), <code>wp_posts.guid</code>, and <code>post_mime_type</code>.
            All APPLY changes are logged to <code>wp-content/uploads/image-audit-logs/</code>.
        </div>

        <?php if (!empty($apply_error)): ?>
            <div class="audit-warning" style="border-left-color:#dc3545;color:#721c24;background:#f8d7da;">
                <strong>APPLY FAILED</strong><br>
                <?php echo esc_html($apply_error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($apply_stats)): ?>
            <div class="audit-success">
                <strong>✅ Attachment Updates Applied</strong><br>
                Attachments updated: <?php echo esc_html((string) ($apply_stats['attachments_updated'] ?? 0)); ?><br>
                Metadata updated: <?php echo esc_html((string) ($apply_stats['metadata_updated'] ?? 0)); ?><br>
                Total sizes changed: <?php echo esc_html((string) ($apply_stats['sizes_changed'] ?? 0)); ?><br>
                DB errors: <?php echo esc_html((string) ($apply_stats['db_errors'] ?? 0)); ?><br>
                Log file: <?php echo esc_html((string) ($apply_stats['log_file'] ?? '')); ?>
            </div>
        <?php endif; ?>

        <?php
            $stats = isset($plan['stats']) && is_array($plan['stats']) ? $plan['stats'] : [];
            $items = isset($plan['items']) && is_array($plan['items']) ? $plan['items'] : [];
        ?>

        <div class="audit-summary">
            <p><strong>Plan Summary</strong></p>
            <p>Attachments scanned: <?php echo esc_html((string) ($stats['attachments_scanned'] ?? 0)); ?></p>
            <p>Eligible (webp exists, jpg/jpeg/png): <strong><?php echo esc_html((string) ($stats['eligible'] ?? 0)); ?></strong></p>
            <p>Missing WebP (informational): <?php echo esc_html((string) ($stats['missing_webp'] ?? 0)); ?></p>
            <p>Skipped (no rel path): <?php echo esc_html((string) ($stats['skipped_no_rel'] ?? 0)); ?></p>
        </div>

        <table class="audit-table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Current guid</th>
                    <th>Current _wp_attached_file</th>
                    <th>Proposed _wp_attached_file (.webp)</th>
                    <th>Proposed guid (.webp)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?php echo esc_html((string) $it['att_id']); ?></td>
                        <td class="url-cell"><?php echo esc_html((string) $it['guid']); ?></td>
                        <td class="url-cell"><?php echo esc_html((string) $it['attached_file']); ?></td>
                        <td class="url-cell"><?php echo esc_html((string) $it['rel_webp']); ?></td>
                        <td class="url-cell"><?php echo esc_html((string) $it['guid_webp']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($items)): ?>
            <div class="empty-state">
                <h2>✅ Nothing to Update</h2>
                <p>No eligible attachment GUID/meta rows were found (matching .webp missing or already updated).</p>
            </div>
        <?php else: ?>
            <?php
                $form_action = add_query_arg(['action' => 'attach_apply'], $base_url);
            ?>
            <form method="post" action="<?php echo esc_url($form_action); ?>" class="confirm-form">
                <?php wp_nonce_field('audit_images_attach_apply', 'audit_attach_nonce'); ?>
                <label>
                    <input type="checkbox" name="audit_attach_confirm" value="1" id="confirm-attach-checkbox" required>
                    I have reviewed the <?php echo esc_html((string) count($items)); ?> eligible attachments above and want to update attachment canonical data to .webp
                </label>
                <button type="submit" name="audit_attach_apply_updates" value="1" id="apply-attach-button" disabled>
                    🚀 Apply Attachment Canonical Fix (<?php echo esc_html((string) count($items)); ?> attachments)
                </button>
                <script>
                    document.getElementById('confirm-attach-checkbox').addEventListener('change', function() {
                        document.getElementById('apply-attach-button').disabled = !this.checked;
                    });
                </script>
            </form>
        <?php endif; ?>

    </div>
    </body>
    </html>
    <?php
}

function audit_images_attach_build_plan($basedir, $baseurl) {
    global $wpdb;

    $plan = [
        'items' => [],
        'stats' => [
            'attachments_scanned' => 0,
            'eligible' => 0,
            'missing_webp' => 0,
            'skipped_no_rel' => 0,
        ],
    ];

    $batch_size = 500;
    $offset = 0;
    $has_more = true;

    while ($has_more) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.guid, p.post_mime_type, pm.meta_value AS attached_file
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm
               ON pm.post_id = p.ID AND pm.meta_key = %s
             WHERE p.post_type = %s
               AND (p.post_mime_type LIKE %s OR p.guid LIKE %s)
             LIMIT %d OFFSET %d",
            '_wp_attached_file',
            'attachment',
            'image/%',
            '%/wp-content/uploads/%',
            $batch_size,
            $offset
        ));

        if (empty($rows) || count($rows) < $batch_size) {
            $has_more = false;
        }

        foreach ($rows as $row) {
            $plan['stats']['attachments_scanned']++;

            $attached_file = is_string($row->attached_file) ? trim($row->attached_file) : '';
            $rel = '';
            if ($attached_file !== '') {
                $rel = $attached_file;
            } else {
                $rel = audit_images_attach_rel_from_guid((string) $row->guid);
            }

            if ($rel === '') {
                $plan['stats']['skipped_no_rel']++;
                continue;
            }

            $rel = ltrim($rel, '/');
            $ext = strtolower((string) pathinfo($rel, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                continue;
            }

            $rel_webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $rel);
            if (!is_string($rel_webp) || $rel_webp === '' || $rel_webp === $rel) {
                continue;
            }

            $webp_path = $basedir . '/' . $rel_webp;
            if (!file_exists($webp_path)) {
                $plan['stats']['missing_webp']++;
                continue;
            }

            $guid_webp = audit_images_attach_guid_to_webp((string) $row->guid);

            $plan['items'][] = [
                'att_id' => (int) $row->ID,
                'guid' => (string) $row->guid,
                'guid_webp' => (string) $guid_webp,
                'attached_file' => (string) $attached_file,
                'rel' => (string) $rel,
                'rel_webp' => (string) $rel_webp,
            ];
            $plan['stats']['eligible']++;
        }

        $offset += $batch_size;
    }

    return $plan;
}

function audit_images_attach_rel_from_guid($guid) {
    if (!is_string($guid) || $guid === '') {
        return '';
    }

    $parts = wp_parse_url($guid);
    $path = '';
    if (is_array($parts) && isset($parts['path']) && is_string($parts['path'])) {
        $path = $parts['path'];
    } else {
        $path = $guid;
    }

    $marker = '/wp-content/uploads/';
    $pos = strpos($path, $marker);
    if ($pos === false) {
        return '';
    }

    $rel = substr($path, $pos + strlen($marker));
    $rel = preg_replace('/[?#].*$/', '', $rel);
    $rel = ltrim((string) $rel, '/');
    $rel = rawurldecode((string) $rel);
    return (string) $rel;
}

function audit_images_attach_guid_to_webp($guid) {
    if (!is_string($guid) || $guid === '') {
        return '';
    }
    return (string) preg_replace('/\.(jpe?g|png)(?=($|[?#]))/i', '.webp', $guid);
}

function audit_images_attach_execute_apply($plan, $basedir, $baseurl) {
    global $wpdb;

    $log_dir = audit_images_get_log_dir();
    $log_file = $log_dir ? $log_dir . '/attach-apply-' . date('Y-m-d-His') . '.log' : null;

    $stats = [
        'attachments_updated' => 0,
        'metadata_updated' => 0,
        'sizes_changed' => 0,
        'db_errors' => 0,
        'log_file' => $log_file,
    ];

    $items = isset($plan['items']) && is_array($plan['items']) ? $plan['items'] : [];
    if (empty($items)) {
        return $stats;
    }

    foreach ($items as $it) {
        $att_id = (int) ($it['att_id'] ?? 0);
        if ($att_id <= 0) {
            continue;
        }

        $rel_webp = (string) ($it['rel_webp'] ?? '');
        if ($rel_webp === '') {
            continue;
        }

        $webp_path = $basedir . '/' . $rel_webp;
        if (!file_exists($webp_path)) {
            continue;
        }

        $old_guid = (string) $wpdb->get_var($wpdb->prepare("SELECT guid FROM {$wpdb->posts} WHERE ID=%d", $att_id));
        $old_mime = (string) $wpdb->get_var($wpdb->prepare("SELECT post_mime_type FROM {$wpdb->posts} WHERE ID=%d", $att_id));
        $old_attached = (string) get_post_meta($att_id, '_wp_attached_file', true);

        $new_guid = audit_images_attach_guid_to_webp($old_guid);
        $new_attached = $rel_webp;

        $entry = [
            'att_id' => $att_id,
            'old_guid' => $old_guid,
            'new_guid' => $new_guid,
            'old_attached_file' => $old_attached,
            'new_attached_file' => $new_attached,
            'old_mime' => $old_mime,
            'new_mime' => 'image/webp',
            'metadata_status' => 'skipped',
            'sizes_changed' => 0,
            'guid_update' => null,
            'mime_update' => null,
            'wpdb_last_error' => null,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $meta_res = update_post_meta($att_id, '_wp_attached_file', $new_attached);
        $entry['attached_file_update'] = $meta_res;

        $meta_update = audit_images_attach_rewrite_metadata($att_id, $new_attached, $basedir);
        $entry['metadata_status'] = (string) ($meta_update['status'] ?? 'unknown');
        $entry['sizes_changed'] = (int) ($meta_update['sizes_changed'] ?? 0);
        if (!empty($meta_update['updated'])) {
            $stats['metadata_updated']++;
            $stats['sizes_changed'] += (int) ($meta_update['sizes_changed'] ?? 0);
        }

        $res_guid = null;
        if ($new_guid !== '' && $new_guid !== $old_guid) {
            $res_guid = $wpdb->update($wpdb->posts, ['guid' => $new_guid], ['ID' => $att_id], ['%s'], ['%d']);
        }
        $entry['guid_update'] = $res_guid;

        $res_mime = null;
        if ($old_mime !== 'image/webp') {
            $res_mime = $wpdb->update($wpdb->posts, ['post_mime_type' => 'image/webp'], ['ID' => $att_id], ['%s'], ['%d']);
        }
        $entry['mime_update'] = $res_mime;

        $entry['wpdb_last_error'] = $wpdb->last_error;
        if ($wpdb->last_error) {
            $stats['db_errors']++;
        }

        $stats['attachments_updated']++;

        if ($log_file) {
            audit_images_write_log($log_file, $entry);
        }
    }

    return $stats;
}

function audit_images_attach_rewrite_metadata($att_id, $rel_webp, $basedir) {
    $meta = wp_get_attachment_metadata($att_id);
    if (!is_array($meta)) {
        return ['updated' => false, 'sizes_changed' => 0, 'status' => 'skipped_not_array'];
    }

    $sizes_changed = 0;
    $dir = trim((string) dirname($rel_webp), '.');

    $meta['file'] = $rel_webp;

    if (isset($meta['sizes']) && is_array($meta['sizes'])) {
        foreach ($meta['sizes'] as $k => $size) {
            if (!is_array($size) || !isset($size['file']) || !is_string($size['file']) || $size['file'] === '') {
                continue;
            }

            $new_file = preg_replace('/\.(jpe?g|png)$/i', '.webp', $size['file']);
            if (!is_string($new_file) || $new_file === '' || $new_file === $size['file']) {
                continue;
            }

            $candidate_rel = ($dir === '' || $dir === '/') ? $new_file : $dir . '/' . $new_file;
            if (file_exists($basedir . '/' . $candidate_rel)) {
                $meta['sizes'][$k]['file'] = $new_file;
                $sizes_changed++;
            }
        }
    }

    $res = wp_update_attachment_metadata($att_id, $meta);
    if ($res === false) {
        return ['updated' => false, 'sizes_changed' => $sizes_changed, 'status' => 'update_failed'];
    }

    return ['updated' => true, 'sizes_changed' => $sizes_changed, 'status' => 'updated'];
}

function audit_images_build_plan($phase, $offset, $limit, $basedir) {
    global $wpdb;

    $plan = [
        'items' => [],
        'by_record' => [],
        'stats' => [
            'total_records' => 0,
            'total_references' => 0,
            'posts_refs' => 0,
            'postmeta_refs' => 0,
            'blocked_references' => 0,
        ],
        'debug' => [
            'total_fields_scanned' => 0,
            'total_strings_examined' => 0,
            'total_legacy_matches_found' => 0,
            'total_local_matches' => 0,
            'total_legacy_files_found_on_disk' => 0,
            'total_webp_files_found_on_disk' => 0,
            'total_plan_items_emitted' => 0,
            'blocked_revision_or_trash' => 0,
            'blocked_excluded_meta_key' => 0,
            'blocked_serialized' => 0,
            'blocked_no_uploads_path' => 0,
            'blocked_unparseable_uploads_path' => 0,
            'blocked_legacy_missing' => 0,
            'blocked_webp_missing' => 0,
            'eligible_plan_items' => 0,
            'blocked_plan_items' => 0,
        ],
    ];

    $uploads_pattern = '%/wp-content/uploads/%';

    $add_alt = function ($s) {
        $alt = '';
        if (strpos($s, '\\/') !== false || strpos($s, '\/') !== false) {
            $alt = str_replace('\/', '/', $s);
        } else {
            $alt = str_replace('/', '\/', $s);
        }
        return $alt;
    };

    $add_item = function ($item) use (&$plan) {
        $plan['items'][] = $item;
        if (!isset($plan['by_record'][$item['record_key']])) {
            $plan['by_record'][$item['record_key']] = [];
        }
        $plan['by_record'][$item['record_key']][] = $item;
    };

    $seen_records = [];

    $scan_posts = ($phase === 'posts' || $phase === 'all');
    if ($scan_posts) {
        $sql = "SELECT ID, post_type, post_content FROM {$wpdb->posts} WHERE post_content LIKE %s";
        $args = [$uploads_pattern];
        if ($limit > 0) {
            $sql .= " LIMIT %d OFFSET %d";
            $args[] = $limit;
            $args[] = $offset;
        }
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args));

        foreach ($rows as $row) {
            $plan['debug']['total_fields_scanned']++;
            if (is_string($row->post_content) && $row->post_content !== '') {
                $plan['debug']['total_strings_examined']++;
            }

            $context = 'posts:ID=' . (int) $row->ID . ' field=post_content';
            $items = audit_images_find_updates_in_field($row->post_content, '', $basedir, $plan['debug'], $context);
            if (empty($items)) {
                continue;
            }

            $record_key = 'posts:' . (int) $row->ID;
            if (!isset($seen_records[$record_key])) {
                $seen_records[$record_key] = true;
                $plan['stats']['total_records']++;
            }

            foreach ($items as $it) {
                $exact = $it['from'];
                $replacement = $it['to'];
                $exact_alt = $add_alt($exact);
                $replacement_alt = preg_replace('/\.(jpe?g|png)(?=(?:\?[^"\'\s)]*)?(?:#[^"\'\s)]*)?$)/i', '.webp', $exact_alt);

                $add_item([
                    'record_key' => $record_key,
                    'location' => 'posts',
                    'record_label' => 'ID: ' . (int) $row->ID . ' (' . (string) $row->post_type . ')',
                    'field' => 'post_content',
                    'post_id' => (int) $row->ID,
                    'meta_id' => 0,
                    'exact_match' => $exact,
                    'replacement' => $replacement,
                    'exact_match_alt' => $exact_alt,
                    'replacement_alt' => $replacement_alt,
                    'legacy_exists' => !empty($it['legacy_exists']),
                    'webp_exists' => !empty($it['webp_exists']),
                    'rel_webp' => $it['rel_webp'] ?? '',
                ]);

                $plan['stats']['total_references']++;
                $plan['stats']['posts_refs']++;
            }
        }
    }

    $scan_postmeta = ($phase === 'postmeta' || $phase === 'all');
    if ($scan_postmeta) {
        $sql = "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s";
        $args = [$uploads_pattern];
        if ($limit > 0) {
            $sql .= " LIMIT %d OFFSET %d";
            $args[] = $limit;
            $args[] = $offset;
        }
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args));

        foreach ($rows as $row) {
            $plan['debug']['total_fields_scanned']++;
            if (is_string($row->meta_value) && $row->meta_value !== '') {
                $plan['debug']['total_strings_examined']++;
            }

            $context = 'postmeta:meta_id=' . (int) $row->meta_id . ' post_id=' . (int) $row->post_id . ' meta_key=' . (string) $row->meta_key . ' field=meta_value';
            $items = audit_images_find_updates_in_field($row->meta_value, '', $basedir, $plan['debug'], $context);
            if (empty($items)) {
                continue;
            }

            $record_key = 'postmeta:' . (int) $row->meta_id;
            if (!isset($seen_records[$record_key])) {
                $seen_records[$record_key] = true;
                $plan['stats']['total_records']++;
            }

            foreach ($items as $it) {
                $exact = $it['from'];
                $replacement = $it['to'];
                $exact_alt = $add_alt($exact);
                $replacement_alt = preg_replace('/\.(jpe?g|png)(?=(?:\?[^"\'\s)]*)?(?:#[^"\'\s)]*)?$)/i', '.webp', $exact_alt);

                $add_item([
                    'record_key' => $record_key,
                    'location' => 'postmeta',
                    'record_label' => 'meta_id: ' . (int) $row->meta_id . ' post_id: ' . (int) $row->post_id . ' key: ' . (string) $row->meta_key,
                    'field' => 'meta_value',
                    'post_id' => (int) $row->post_id,
                    'meta_id' => (int) $row->meta_id,
                    'exact_match' => $exact,
                    'replacement' => $replacement,
                    'exact_match_alt' => $exact_alt,
                    'replacement_alt' => $replacement_alt,
                    'legacy_exists' => !empty($it['legacy_exists']),
                    'webp_exists' => !empty($it['webp_exists']),
                    'rel_webp' => $it['rel_webp'] ?? '',
                ]);

                $plan['stats']['total_references']++;
                $plan['stats']['postmeta_refs']++;
            }
        }
    }

    return $plan;
}

// =============================================================================
// ADMIN MENU REGISTRATION
// =============================================================================

/**
 * Register the Image Audit admin menu page under Tools
 */
function audit_images_admin_menu() {
    add_management_page(
        'Image Audit',
        'Image Audit',
        'manage_options',
        'image-audit',
        'audit_images_render_page'
    );
}
add_action('admin_menu', 'audit_images_admin_menu');

/**
 * Handle querystring trigger on any admin page
 */
function audit_images_querystring_trigger() {
    if (!is_admin()) {
        return;
    }
    
    if (isset($_GET['mode']) && $_GET['mode'] === 'imageaudit' && isset($_GET['list']) && $_GET['list'] === 'dependencies') {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access', 'Forbidden', ['response' => 403]);
        }
        
        // Render the audit page and exit
        audit_images_render_page();
        exit;
    }
}
add_action('admin_init', 'audit_images_querystring_trigger');

// =============================================================================
// MAIN RENDER FUNCTION - 2-Step PLAN→APPLY Workflow
// =============================================================================

/**
 * Render the Image Audit admin page
 * 
 * Default view shows the UPDATE PLAN - all items that would be updated.
 * User can then confirm to APPLY those exact updates.
 */
function audit_images_render_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access', 'Forbidden', ['response' => 403]);
    }
    
    global $wpdb;
    
    // Get upload directory info
    $upload_dir = wp_get_upload_dir();
    $baseurl = $upload_dir['baseurl'];
    $basedir = $upload_dir['basedir'];
    
    $apply_test = isset($_GET['apply_test']) && $_GET['apply_test'] === '1';

    $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';

    if (in_array($action, ['attach_plan', 'attach_apply'], true)) {
        $attach_error = '';
        $is_attach_apply = false;
        if (isset($_POST['audit_attach_apply_updates'])) {
            if ($action !== 'attach_apply') {
                $attach_error = 'APPLY failed: missing action=attach_apply.';
            } elseif (!isset($_POST['audit_attach_confirm']) || $_POST['audit_attach_confirm'] !== '1') {
                $attach_error = 'APPLY failed: confirmation checkbox was not checked.';
            } elseif (!isset($_POST['audit_attach_nonce']) || !wp_verify_nonce($_POST['audit_attach_nonce'], 'audit_images_attach_apply')) {
                $attach_error = 'APPLY failed: nonce verification failed. Please reload and try again.';
            } else {
                $is_attach_apply = true;
            }
        }

        $base_url = admin_url('tools.php?page=image-audit');
        audit_images_render_attach_page($action, $is_attach_apply, $attach_error, $baseurl, $basedir, $base_url);
        return;
    }

    $phase = isset($_GET['phase']) ? sanitize_key($_GET['phase']) : 'all';
    if (!in_array($phase, ['posts', 'postmeta', 'all'], true)) {
        $phase = 'all';
    }
    $limit = isset($_GET['limit']) ? absint($_GET['limit']) : 0;
    $offset = isset($_GET['offset']) ? absint($_GET['offset']) : 0;

    $apply_error = '';
    $is_apply = false;
    if (isset($_POST['audit_apply_updates'])) {
        if ($action !== 'apply') {
            $apply_error = 'APPLY failed: missing action=apply.';
        } elseif (!isset($_POST['audit_confirm']) || $_POST['audit_confirm'] !== '1') {
            $apply_error = 'APPLY failed: confirmation checkbox was not checked.';
        } elseif (!isset($_POST['audit_nonce']) || !wp_verify_nonce($_POST['audit_nonce'], 'audit_images_apply')) {
            $apply_error = 'APPLY failed: nonce verification failed. Please reload and try again.';
        } else {
            $is_apply = true;
        }
    }
    
    // Batch size for internal processing
    $batch_size = 500;
    
    // Build base URL
    $base_url = admin_url('tools.php?page=image-audit');
    
    // Render the page
    audit_images_render_plan_page($is_apply, $apply_test, $apply_error, $phase, $offset, $limit, $batch_size, $baseurl, $basedir, $base_url);
}

/**
 * Render the PLAN page with optional APPLY execution
 * 
 * This is the main 2-step workflow:
 * - PLAN: Shows all items that would be updated (default)
 * - APPLY: When confirmed, updates those exact items
 */
function audit_images_render_plan_page($is_apply, $apply_test, $apply_error, $phase, $offset, $limit, $batch_size, $baseurl, $basedir, $base_url) {
    global $wpdb;
    
    // Start output
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Image Audit - Update Plan</title>
        <style>
            .audit-wrap { max-width: 1600px; margin: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .audit-wrap h1 { margin-bottom: 10px; }
            .audit-info { margin-bottom: 15px; padding: 12px; background: #e7f3ff; border-left: 4px solid #0073aa; font-size: 13px; }
            .audit-success { margin-bottom: 15px; padding: 12px; background: #d4edda; border-left: 4px solid #28a745; font-size: 13px; }
            .audit-warning { margin-bottom: 15px; padding: 12px; background: #fff3cd; border-left: 4px solid #ffc107; font-size: 13px; }
            .audit-summary { margin-bottom: 15px; padding: 12px; background: #f8f9fa; border: 1px solid #dee2e6; }
            .audit-summary p { margin: 5px 0; }
            .audit-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 20px; }
            .audit-table th, .audit-table td { padding: 6px 8px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
            .audit-table th { background: #f0f0f0; font-weight: 600; position: sticky; top: 0; }
            .audit-table tr:nth-child(even) { background: #fafafa; }
            .audit-table .url-cell { max-width: 350px; word-break: break-all; font-family: monospace; font-size: 11px; }
            .audit-table .status-ok { color: #28a745; }
            .audit-table .status-fail { color: #dc3545; }
            .confirm-form { margin-top: 20px; padding: 15px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 4px; }
            .confirm-form label { display: block; margin-bottom: 10px; font-weight: bold; }
            .confirm-form button { padding: 12px 24px; background: #dc3545; color: #fff; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; }
            .confirm-form button:hover { background: #c82333; }
            .confirm-form button:disabled { background: #6c757d; cursor: not-allowed; }
            .empty-state { padding: 40px; text-align: center; background: #d4edda; border: 2px solid #28a745; border-radius: 4px; }
            .empty-state h2 { color: #155724; margin: 0 0 10px 0; }
            .empty-state p { color: #155724; margin: 0; }
        </style>
    </head>
    <body>
    <div class="audit-wrap">
        <h1>Image Audit — Update Plan</h1>
        
        <div class="audit-info">
            <strong>How this works:</strong> This page shows ALL legacy image references (.jpg/.jpeg/.png) that will be 
            updated to .webp. Review the list below, then check the confirmation box and click Apply to perform the updates.
            After applying, reload this page to verify zero items remain.
        </div>
    <?php
    
    // Create a plan log file (used when matches exist but no webp exists)
    $log_dir = audit_images_get_log_dir();
    $GLOBALS['audit_images_plan_log_file'] = $log_dir ? $log_dir . '/plan-' . date('Y-m-d-His') . '.log' : null;

    $request_action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';
    $nonce_status = 'not_present';
    if (isset($_POST['audit_nonce'])) {
        $nonce_status = wp_verify_nonce($_POST['audit_nonce'], 'audit_images_apply') ? 'valid' : 'invalid';
    }
    $branch = $is_apply ? 'APPLY' : 'PLAN';

    ?>
    <div class="audit-summary">
        <p><strong>Request Debug</strong></p>
        <p>action: <?php echo esc_html($request_action === '' ? '(none)' : $request_action); ?> | nonce: <?php echo esc_html($nonce_status); ?> | branch: <?php echo esc_html($branch); ?></p>
        <p>phase: <?php echo esc_html($phase); ?> | offset: <?php echo esc_html((string) $offset); ?> | limit: <?php echo esc_html((string) $limit); ?> | apply_test: <?php echo esc_html($apply_test ? '1' : '0'); ?></p>
    </div>
    <?php

    if (!empty($apply_error)) {
        ?>
        <div class="audit-warning" style="border-left-color:#dc3545;color:#721c24;background:#f8d7da;">
            <strong>APPLY FAILED</strong><br>
            <?php echo esc_html($apply_error); ?>
        </div>
        <?php
    }

    // Build the plan ONCE for this request. Both PLAN display and APPLY execution use this.
    $plan = audit_images_build_plan($phase, $offset, $limit, $basedir);

    $apply_stats = null;
    if ($is_apply) {
        $apply_plan_count = isset($plan['items']) ? count($plan['items']) : 0;
        echo '<div class="audit-summary"><p><strong>APPLY plan items: ' . esc_html((string) $apply_plan_count) . '</strong></p></div>';

        $expected = isset($_POST['expected_plan_count']) ? absint($_POST['expected_plan_count']) : 0;
        if ($expected > 0 && $apply_plan_count !== $expected) {
            echo '<div class="audit-warning" style="border-left-color:#dc3545;color:#721c24;background:#f8d7da;">';
            echo '<strong>APPLY ABORTED: plan count mismatch.</strong><br>';
            echo 'Expected (from PLAN): ' . esc_html((string) $expected) . ' | APPLY computed: ' . esc_html((string) $apply_plan_count) . '<br>';
            echo 'First 3 items:<br><pre style="white-space:pre-wrap;">' . esc_html(print_r(array_slice($plan['items'], 0, 3), true)) . '</pre>';
            echo '</div>';
            $is_apply = false;
        }
    }

    if ($is_apply) {
        $apply_stats = audit_images_execute_apply($plan, $basedir, $apply_test);
        $planned = isset($apply_stats['planned_replacements']) ? (int) $apply_stats['planned_replacements'] : 0;
        $updated = isset($apply_stats['records_updated']) ? (int) $apply_stats['records_updated'] : 0;
        $replaced = isset($apply_stats['references_replaced']) ? (int) $apply_stats['references_replaced'] : 0;
        $is_hard_fail = ($planned > 0 && $updated === 0 && $replaced === 0);
        ?>
        <div class="<?php echo $is_hard_fail ? 'audit-warning' : 'audit-success'; ?>" style="<?php echo $is_hard_fail ? 'border-left-color:#dc3545;color:#721c24;background:#f8d7da;' : ''; ?>">
            <strong><?php echo $is_hard_fail ? 'APPLY FAILED: Plan had ' . esc_html($planned) . ' replacements but 0 were written. Check logs.' : '✅ Updates Applied!'; ?></strong><br>
            Planned replacements: <?php echo esc_html($planned); ?><br>
            Records updated: <?php echo esc_html($updated); ?><br>
            References replaced: <?php echo esc_html($replaced); ?><br>
            Records skipped: <?php echo esc_html((int) ($apply_stats['records_skipped'] ?? 0)); ?><br>
            No-change-after-replace: <?php echo esc_html((int) ($apply_stats['no_change_after_replace'] ?? 0)); ?><br>
            DB errors: <?php echo esc_html((int) ($apply_stats['db_error'] ?? 0)); ?><br>
            Update failed (0 rows): <?php echo esc_html((int) ($apply_stats['update_failed_zero_rows'] ?? 0)); ?><br>
            Update OK but 0 rows: <?php echo esc_html((int) ($apply_stats['updated_ok_but_zero_rows'] ?? 0)); ?>
        </div>
        <?php

        if (!empty($apply_stats['smoke_test'])) {
            echo '<div class="audit-summary"><p><strong>APPLY Smoke Test</strong></p>';
            echo '<pre style="white-space:pre-wrap;">' . esc_html(print_r($apply_stats['smoke_test'], true)) . '</pre>';
            echo '</div>';
        }
    }
    
    // Now show the PLAN (from the shared plan object)
    $plan_stats = isset($plan['stats']) && is_array($plan['stats']) ? $plan['stats'] : [
        'total_records' => 0,
        'total_references' => 0,
        'posts_refs' => 0,
        'postmeta_refs' => 0,
        'blocked_references' => 0,
    ];

    $debug = isset($plan['debug']) && is_array($plan['debug']) ? $plan['debug'] : [
        'total_fields_scanned' => 0,
        'total_strings_examined' => 0,
        'total_legacy_matches_found' => 0,
        'total_local_matches' => 0,
        'total_legacy_files_found_on_disk' => 0,
        'total_webp_files_found_on_disk' => 0,
        'total_plan_items_emitted' => 0,
        'blocked_revision_or_trash' => 0,
        'blocked_excluded_meta_key' => 0,
        'blocked_serialized' => 0,
        'blocked_no_uploads_path' => 0,
        'blocked_unparseable_uploads_path' => 0,
        'blocked_legacy_missing' => 0,
        'blocked_webp_missing' => 0,
        'eligible_plan_items' => 0,
        'blocked_plan_items' => 0,
    ];
    
    // Start the table
    ?>
    <div class="audit-summary" id="plan-summary">
        <p><strong>Scanning...</strong> Please wait while we build the update plan.</p>
    </div>
    
    <table class="audit-table">
        <thead>
            <tr>
                <th style="width: 80px;">Location</th>
                <th style="width: 180px;">Record</th>
                <th style="width: 80px;">Field</th>
                <th>Legacy URL (from)</th>
                <th>WebP URL (to)</th>
                <th style="width: 60px;">Legacy?</th>
                <th style="width: 60px;">WebP?</th>
                <th style="width: 160px;">Blocked Reason</th>
            </tr>
        </thead>
        <tbody>
    <?php
    
    // Flush output
    if (ob_get_level()) ob_flush();
    flush();
    
    $row_count = 0;
    
    $items_for_table = isset($plan['items']) && is_array($plan['items']) ? $plan['items'] : [];
    foreach ($items_for_table as $item) {
        $row_count++;
        ?>
        <tr>
            <td><?php echo esc_html($item['location']); ?></td>
            <td><?php echo esc_html($item['record_label']); ?></td>
            <td><?php echo esc_html($item['field']); ?></td>
            <td class="url-cell"><?php echo esc_html($item['exact_match']); ?></td>
            <td class="url-cell"><?php echo esc_html($item['replacement']); ?></td>
            <td class="<?php echo !empty($item['legacy_exists']) ? 'status-ok' : 'status-fail'; ?>"><?php echo !empty($item['legacy_exists']) ? '✓' : '✗'; ?></td>
            <td class="<?php echo !empty($item['webp_exists']) ? 'status-ok' : 'status-fail'; ?>"><?php echo !empty($item['webp_exists']) ? '✓' : '✗'; ?></td>
            <td class="status-ok">-</td>
        </tr>
        <?php
    }
    
    ?>
        </tbody>
    </table>
    
    <script>
        document.getElementById('plan-summary').innerHTML = 
            '<p><strong>Update Plan Summary</strong></p>' +
            '<p>Total references to update: <strong><?php echo (int) $plan_stats['total_references']; ?></strong></p>' +
            '<p>Blocked references (shown but will NOT be updated): <?php echo (int) $plan_stats['blocked_references']; ?></p>' +
            '<p>In posts: <?php echo (int) $plan_stats['posts_refs']; ?> | In postmeta: <?php echo (int) $plan_stats['postmeta_refs']; ?></p>' +
            '<p>Records affected: <?php echo (int) $plan_stats['total_records']; ?></p>' +
            '<hr style="border:0;border-top:1px solid #ddd;margin:10px 0;">' +
            '<p><strong>Detection Debug Counters</strong></p>' +
            '<p>Fields scanned: <?php echo (int) $debug['total_fields_scanned']; ?> | Strings examined: <?php echo (int) $debug['total_strings_examined']; ?></p>' +
            '<p>Raw legacy matches found: <?php echo (int) $debug['total_legacy_matches_found']; ?></p>' +
            '<p>Resolvable uploads matches: <?php echo (int) $debug['total_local_matches']; ?></p>' +
            '<p>Legacy files found on disk: <?php echo (int) $debug['total_legacy_files_found_on_disk']; ?></p>' +
            '<p>WebP files found on disk: <?php echo (int) $debug['total_webp_files_found_on_disk']; ?></p>' +
            '<p>Plan items emitted (passed file checks): <?php echo (int) $debug['total_plan_items_emitted']; ?></p>' +
            '<p>Eligible plan items: <?php echo (int) $debug['eligible_plan_items']; ?> | Blocked plan items: <?php echo (int) $debug['blocked_plan_items']; ?></p>' +
            '<p>Blocked (revision/trash): <?php echo (int) $debug['blocked_revision_or_trash']; ?> | Blocked (excluded meta key): <?php echo (int) $debug['blocked_excluded_meta_key']; ?> | Blocked (serialized): <?php echo (int) $debug['blocked_serialized']; ?></p>' +
            '<p>Blocked (no uploads path): <?php echo (int) $debug['blocked_no_uploads_path']; ?> | Blocked (unparseable uploads path): <?php echo (int) $debug['blocked_unparseable_uploads_path']; ?></p>' +
            '<p>Blocked (legacy missing): <?php echo (int) $debug['blocked_legacy_missing']; ?> | Blocked (webp missing): <?php echo (int) $debug['blocked_webp_missing']; ?></p>';
    </script>

    <?php if ($debug['total_legacy_matches_found'] > 0 && $plan_stats['total_references'] === 0): ?>
        <div class="audit-warning" style="border-left-color:#dc3545;color:#721c24;background:#f8d7da;">
            <strong>⚠ Legacy images detected but all were filtered out.</strong><br>
            Raw legacy matches were found in the database, but no plan items were emitted. The counters above show where the pipeline dropped to zero.
        </div>
    <?php endif; ?>
    
    <?php if ($plan_stats['total_references'] === 0): ?>
    <div class="empty-state">
        <h2>✅ Nothing to Update</h2>
        <p>All legacy image references have been converted to .webp, or no eligible references exist.</p>
    </div>
    <?php else: ?>
    <?php
        $form_args = ['action' => 'apply'];
        if ($apply_test) {
            $form_args['apply_test'] = '1';
        }
        $form_action = add_query_arg($form_args, $base_url);
    ?>
    <form method="post" action="<?php echo esc_url($form_action); ?>" class="confirm-form">
        <?php wp_nonce_field('audit_images_apply', 'audit_nonce'); ?>
        <input type="hidden" name="expected_plan_count" value="<?php echo esc_attr((string) $plan_stats['total_references']); ?>">
        <label>
            <input type="checkbox" name="audit_confirm" value="1" id="confirm-checkbox" required>
            I have reviewed the <?php echo esc_html($plan_stats['total_references']); ?> references above and want to update them to .webp
        </label>
        <button type="submit" name="audit_apply_updates" value="1" id="apply-button" disabled>
            🚀 Apply Updates (<?php echo esc_html($plan_stats['total_references']); ?> references)
        </button>
        <script>
            document.getElementById('confirm-checkbox').addEventListener('change', function() {
                document.getElementById('apply-button').disabled = !this.checked;
            });
        </script>
    </form>
    <?php endif; ?>
    
    </div>
    </body>
    </html>
    <?php
}

/**
 * Find all update items in a field value
 * 
 * This is the SINGLE SOURCE OF TRUTH used by both PLAN and APPLY.
 * Returns an array of items that would be updated.
 * 
 * @param string $field_value The content to scan
 * @param string $baseurl Uploads base URL
 * @param string $basedir Uploads base directory
 * @return array Array of update items: ['from' => string, 'to' => string, 'rel_legacy' => string, 'rel_webp' => string]
 */
function audit_images_find_updates_in_field($field_value, $baseurl, $basedir, &$debug = null, $context = '', $plan_mode = false) {
    $items = [];

    if (empty($field_value) || !is_string($field_value)) {
        return $items;
    }

    // Carpet-bomb extractor: find ANY /wp-content/uploads/...(.jpg|.jpeg|.png) in messy contexts.
    // This intentionally prioritizes robustness over precision.
    // Also supports JSON-escaped slashes: \/wp-content\/uploads\/
    $pattern_unescaped = '~(?:https?:)?//[^"\'\s)]*?/wp-content/uploads/[^"\'\s)]*?\.(?:jpe?g|png)(?:\?[^"\'\s)]*)?(?:#[^"\'\s)]*)?'
        . '|/wp-content/uploads/[^"\'\s)]*?\.(?:jpe?g|png)(?:\?[^"\'\s)]*)?(?:#[^"\'\s)]*)?~i';
    $pattern_escaped = '~\\/wp-content\\/uploads\\/[^"\'\s)]*?\.(?:jpe?g|png)(?:\?[^"\'\s)]*)?(?:#[^"\'\s)]*)?~i';

    $matches = [];
    $m1 = [];
    $m2 = [];
    preg_match_all($pattern_unescaped, $field_value, $m1);
    preg_match_all($pattern_escaped, $field_value, $m2);
    if (!empty($m1[0])) {
        $matches = array_merge($matches, $m1[0]);
    }
    if (!empty($m2[0])) {
        $matches = array_merge($matches, $m2[0]);
    }

    if (empty($matches)) {
        return $items;
    }

    if (is_array($debug)) {
        $debug['total_legacy_matches_found'] += count($matches);
        $debug['total_local_matches'] += count($matches);
    }

    $seen = [];
    $webp_missing_for_all = true;

    foreach ($matches as $legacy_raw) {
        if (empty($legacy_raw) || !is_string($legacy_raw)) {
            if (is_array($debug)) {
                $debug['blocked_unparseable_uploads_path']++;
            }
            continue;
        }

        // Dedupe by exact matched substring.
        if (isset($seen[$legacy_raw])) {
            continue;
        }
        $seen[$legacy_raw] = true;

        // Extract uploads-relative path from the matched substring.
        $marker_unescaped = '/wp-content/uploads/';
        $marker_escaped = '\\/wp-content\\/uploads\\/';
        $pos = strpos($legacy_raw, $marker_unescaped);
        $marker_len = strlen($marker_unescaped);
        if ($pos === false) {
            $pos = strpos($legacy_raw, $marker_escaped);
            $marker_len = strlen($marker_escaped);
        }
        if ($pos === false) {
            if (is_array($debug)) {
                $debug['blocked_no_uploads_path']++;
            }
            continue;
        }

        $relative_full = substr($legacy_raw, $pos + $marker_len);
        $relative_full = str_replace('\\/', '/', $relative_full);
        $relative_full = preg_replace('/[?#].*$/', '', $relative_full);
        $relative_full = ltrim($relative_full, '/');
        $relative_full = rawurldecode($relative_full);

        if ($relative_full === '') {
            if (is_array($debug)) {
                $debug['blocked_unparseable_uploads_path']++;
            }
            continue;
        }

        $rel_legacy = $relative_full;
        $rel_webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $rel_legacy);

        $legacy_path = $basedir . '/' . $rel_legacy;
        $legacy_exists = file_exists($legacy_path);

        $webp_path = $basedir . '/' . $rel_webp;
        $webp_exists = file_exists($webp_path);
        if ($webp_exists) {
            $webp_missing_for_all = false;
            if (is_array($debug)) {
                $debug['total_webp_files_found_on_disk']++;
            }
        } else {
            if (is_array($debug)) {
                $debug['blocked_webp_missing']++;
            }
        }

        if ($legacy_exists && is_array($debug)) {
            $debug['total_legacy_files_found_on_disk']++;
        }

        // Eligibility is simple: include only if corresponding .webp exists.
        if (!$webp_exists) {
            continue;
        }

        // Replacement: preserve exact prefix/path/suffix, only swap extension.
        $to = preg_replace('/\.(jpe?g|png)(?=(?:\?[^"\'\s)]*)?(?:#[^"\'\s)]*)?$)/i', '.webp', $legacy_raw);

        $items[] = [
            'from' => $legacy_raw,
            'to' => $to,
            'rel_legacy' => $rel_legacy,
            'rel_webp' => $rel_webp,
            'legacy_exists' => $legacy_exists,
            'webp_exists' => $webp_exists,
            'blocked_reason' => '',
        ];

        if (is_array($debug)) {
            $debug['total_plan_items_emitted']++;
            if (isset($debug['eligible_plan_items'])) {
                $debug['eligible_plan_items']++;
            }
        }
    }

    // Required one-line log if we detected matches but emitted 0 items due to no webp.
    if (!empty($matches) && empty($items) && $webp_missing_for_all) {
        $log_file = isset($GLOBALS['audit_images_plan_log_file']) ? $GLOBALS['audit_images_plan_log_file'] : null;
        if (!empty($log_file)) {
            audit_images_write_log($log_file, [
                'context' => $context,
                'matches_found' => count($matches),
                'reason' => 'no webp exists for any match',
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    return $items;
}

/**
 * Execute the APPLY step - update all eligible records
 * 
 * Uses the same audit_images_find_updates_in_field() function as PLAN
 * to ensure consistency.
 * 
 * @param int $batch_size Batch size for processing
 * @param string $baseurl Uploads base URL
 * @param string $basedir Uploads base directory
 * @return array Stats about the apply operation
 */
function audit_images_execute_apply($plan, $basedir, $apply_test = false) {
    global $wpdb;
    
    $stats = [
        'planned_replacements' => isset($plan['items']) && is_array($plan['items']) ? count($plan['items']) : 0,
        'records_updated' => 0,
        'references_replaced' => 0,
        'records_skipped' => 0,
        'no_change_after_replace' => 0,
        'db_error' => 0,
        'updated_ok_but_zero_rows' => 0,
        'update_failed_zero_rows' => 0,
        'from_not_found' => 0,
        'smoke_test' => [],
    ];
    
    // Create log file
    $log_dir = audit_images_get_log_dir();
    $log_file = $log_dir ? $log_dir . '/apply-' . date('Y-m-d-His') . '.log' : null;

    $items = isset($plan['items']) && is_array($plan['items']) ? $plan['items'] : [];
    $by_record = isset($plan['by_record']) && is_array($plan['by_record']) ? $plan['by_record'] : [];

    if (empty($items)) {
        return $stats;
    }

    // STEP 5: Hard smoke test on the first plan item.
    $first = $items[0];
    $smoke = [
        'record_key' => $first['record_key'] ?? '',
        'location' => $first['location'] ?? '',
        'exact_match' => $first['exact_match'] ?? '',
        'replacement' => $first['replacement'] ?? '',
        'strpos_exact' => null,
        'strpos_alt' => null,
        'used_variant' => null,
        'before_md5' => null,
        'after_md5' => null,
        'wpdb_update_result' => null,
        'wpdb_last_error' => null,
        're_read_md5' => null,
    ];

    $before = '';
    if ($first['location'] === 'posts') {
        $before = (string) $wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", (int) $first['post_id']));
    } elseif ($first['location'] === 'postmeta') {
        $before = (string) $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id=%d", (int) $first['meta_id']));
    }

    $smoke['before_md5'] = md5($before);
    $smoke['strpos_exact'] = (strpos($before, $first['exact_match']) !== false);
    $smoke['strpos_alt'] = (strpos($before, $first['exact_match_alt']) !== false);

    $after = $before;
    if ($smoke['strpos_exact']) {
        $smoke['used_variant'] = 'exact';
        $after = str_replace($first['exact_match'], $first['replacement'], $after);
    } elseif ($smoke['strpos_alt']) {
        $smoke['used_variant'] = 'alt';
        $after = str_replace($first['exact_match_alt'], $first['replacement_alt'], $after);
    } else {
        $stats['from_not_found']++;
        $smoke['used_variant'] = 'none';
        $stats['smoke_test'] = $smoke;
        if ($log_file) {
            audit_images_write_log($log_file, ['reason' => 'SMOKE_FROM_NOT_FOUND', 'smoke' => $smoke, 'timestamp' => date('Y-m-d H:i:s')]);
        }
        return $stats;
    }

    $smoke['after_md5'] = md5($after);

    $result = null;
    if ($first['location'] === 'posts') {
        $result = $wpdb->update($wpdb->posts, ['post_content' => $after], ['ID' => (int) $first['post_id']], ['%s'], ['%d']);
        $re_read = (string) $wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", (int) $first['post_id']));
        $smoke['re_read_md5'] = md5($re_read);
    } elseif ($first['location'] === 'postmeta') {
        $result = $wpdb->update($wpdb->postmeta, ['meta_value' => $after], ['meta_id' => (int) $first['meta_id']], ['%s'], ['%d']);
        $re_read = (string) $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id=%d", (int) $first['meta_id']));
        $smoke['re_read_md5'] = md5($re_read);
    }
    $smoke['wpdb_update_result'] = $result;
    $smoke['wpdb_last_error'] = $wpdb->last_error;
    $stats['smoke_test'] = $smoke;

    // We intentionally performed the first replacement/update above.
    // Remove that single item from the per-record queue to avoid logging FROM_NOT_FOUND
    // for an item that we already applied.
    if (!empty($first['record_key']) && isset($by_record[$first['record_key']]) && is_array($by_record[$first['record_key']])) {
        foreach ($by_record[$first['record_key']] as $idx => $pi) {
            if (($pi['exact_match'] ?? null) === ($first['exact_match'] ?? null) && ($pi['replacement'] ?? null) === ($first['replacement'] ?? null)) {
                unset($by_record[$first['record_key']][$idx]);
                $by_record[$first['record_key']] = array_values($by_record[$first['record_key']]);
                break;
            }
        }
    }

    if ($apply_test) {
        return $stats;
    }

    // STEP 4: Apply by record using the plan object only.
    foreach ($by_record as $record_key => $record_items) {
        if (empty($record_items)) {
            continue;
        }

        $location = $record_items[0]['location'];
        $before_value = '';

        if ($location === 'posts') {
            $post_id = (int) $record_items[0]['post_id'];
            $before_value = (string) $wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $post_id));
        } elseif ($location === 'postmeta') {
            $meta_id = (int) $record_items[0]['meta_id'];
            $before_value = (string) $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id=%d", $meta_id));
        } else {
            continue;
        }

        $after_value = $before_value;
        $replaced_count = 0;

        foreach ($record_items as $pi) {
            $from = $pi['exact_match'];
            $to = $pi['replacement'];
            $from_alt = $pi['exact_match_alt'];
            $to_alt = $pi['replacement_alt'];

            if (strpos($after_value, $from) !== false) {
                $c = 0;
                $after_value = str_replace($from, $to, $after_value, $c);
                $replaced_count += $c;
                continue;
            }

            if (strpos($after_value, $from_alt) !== false) {
                $c = 0;
                $after_value = str_replace($from_alt, $to_alt, $after_value, $c);
                $replaced_count += $c;
                continue;
            }

            $stats['from_not_found']++;
            if ($log_file) {
                audit_images_write_log($log_file, [
                    'reason' => 'FROM_NOT_FOUND',
                    'record_key' => $record_key,
                    'from' => $from,
                    'from_alt' => $from_alt,
                    'timestamp' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        if ($after_value === $before_value) {
            $stats['no_change_after_replace']++;
            continue;
        }

        if ($location === 'posts') {
            $post_id = (int) $record_items[0]['post_id'];
            $res = $wpdb->update($wpdb->posts, ['post_content' => $after_value], ['ID' => $post_id], ['%s'], ['%d']);
            if ($res === false) {
                $stats['db_error']++;
                if ($log_file) {
                    audit_images_write_log($log_file, ['reason' => 'wpdb_update_false', 'record_key' => $record_key, 'wpdb_last_error' => $wpdb->last_error, 'timestamp' => date('Y-m-d H:i:s')]);
                }
                continue;
            }
            if ($res === 0) {
                $re_read = (string) $wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $post_id));
                if ($re_read === $after_value) {
                    $stats['updated_ok_but_zero_rows']++;
                } else {
                    $stats['update_failed_zero_rows']++;
                }
            }
        } else {
            $meta_id = (int) $record_items[0]['meta_id'];
            $res = $wpdb->update($wpdb->postmeta, ['meta_value' => $after_value], ['meta_id' => $meta_id], ['%s'], ['%d']);
            if ($res === false) {
                $stats['db_error']++;
                if ($log_file) {
                    audit_images_write_log($log_file, ['reason' => 'wpdb_update_false', 'record_key' => $record_key, 'wpdb_last_error' => $wpdb->last_error, 'timestamp' => date('Y-m-d H:i:s')]);
                }
                continue;
            }
            if ($res === 0) {
                $re_read = (string) $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id=%d", $meta_id));
                if ($re_read === $after_value) {
                    $stats['updated_ok_but_zero_rows']++;
                } else {
                    $stats['update_failed_zero_rows']++;
                }
            }
        }

        $stats['records_updated']++;
        $stats['references_replaced'] += $replaced_count;
    }

    return $stats;
}

/**
 * Render the page header with controls
 * @deprecated Use audit_images_render_plan_page instead
 */
function audit_images_render_header($phase, $limit, $offset, $missing_only, $nolimit, $base_url, $current_args) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Image Audit</title>
        <style>
            .audit-images-wrap { max-width: 1400px; margin: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .audit-images-wrap h1 { margin-bottom: 20px; }
            .audit-controls { margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px; }
            .audit-controls a { margin-right: 10px; padding: 8px 16px; background: #0073aa; color: #fff; text-decoration: none; border-radius: 3px; display: inline-block; margin-bottom: 5px; }
            .audit-controls a.active { background: #005a87; }
            .audit-controls a:hover { background: #005a87; }
            .audit-controls .toggle-on { background: #28a745; }
            .audit-controls .toggle-on:hover { background: #1e7e34; }
            .audit-summary { margin-bottom: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #0073aa; }
            .audit-summary p { margin: 5px 0; }
            .audit-warning { margin-bottom: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
            .audit-table { width: 100%; border-collapse: collapse; font-size: 13px; }
            .audit-table th, .audit-table td { padding: 8px 10px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
            .audit-table th { background: #f0f0f0; font-weight: 600; position: sticky; top: 0; }
            .audit-table tr:nth-child(even) { background: #fafafa; }
            .audit-table .url-cell { max-width: 300px; word-break: break-all; font-family: monospace; font-size: 11px; }
            .audit-table .context-cell { max-width: 250px; font-size: 11px; color: #666; overflow: hidden; text-overflow: ellipsis; }
            .webp-yes { color: #28a745; font-weight: bold; }
            .webp-no { color: #dc3545; font-weight: bold; }
            .pagination { margin-top: 20px; }
            .pagination a { margin-right: 10px; padding: 8px 16px; background: #0073aa; color: #fff; text-decoration: none; border-radius: 3px; display: inline-block; }
            .pagination a:hover { background: #005a87; }
            .pagination span { color: #666; }
            .control-group { display: inline-block; margin-right: 20px; margin-bottom: 10px; }
            .control-group strong { display: block; margin-bottom: 5px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
    <div class="audit-images-wrap">
        <h1>Image Audit — Convertible References</h1>
        
        <div class="audit-info" style="margin-bottom: 15px; padding: 10px; background: #e7f3ff; border-left: 4px solid #0073aa; font-size: 13px;">
            <strong>Detection Mode:</strong> Shows legacy references (.jpg/.jpeg/.png) that CAN be converted to .webp.
            Requires: legacy file exists on disk, .webp file exists on disk.
            Excludes: revisions, trash, serialized data, transients, external URLs.
        </div>
        
        <div class="audit-controls">
            <div class="control-group">
                <strong>Phase:</strong>
                <?php
                $phases = ['posts' => 'Posts', 'postmeta' => 'Post Meta', 'options' => 'Options', 'all' => 'All'];
                foreach ($phases as $p => $label):
                    $args = array_merge($current_args, ['phase' => $p, 'offset' => 0]);
                ?>
                <a href="<?php echo esc_url(add_query_arg($args, $base_url)); ?>" class="<?php echo $phase === $p ? 'active' : ''; ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>
            
            <div class="control-group">
                <strong>Limit:</strong>
                <?php
                $limits = [50, 100, 200, 500];
                foreach ($limits as $l):
                    $args = array_merge($current_args, ['limit' => $l, 'offset' => 0]);
                    unset($args['nolimit']); // Remove nolimit when setting limit
                ?>
                <a href="<?php echo esc_url(add_query_arg($args, $base_url)); ?>" class="<?php echo ($limit === $l && !$nolimit) ? 'active' : ''; ?>"><?php echo $l; ?></a>
                <?php endforeach; ?>
            </div>
            
            <div class="control-group">
                <strong>Mode:</strong>
                <?php
                // No limit toggle
                $nolimit_args = $current_args;
                if ($nolimit) {
                    unset($nolimit_args['nolimit']);
                    $nolimit_label = 'No Limit: ON';
                    $nolimit_class = 'toggle-on';
                } else {
                    $nolimit_args['nolimit'] = '1';
                    unset($nolimit_args['offset']);
                    $nolimit_label = 'No Limit: OFF';
                    $nolimit_class = '';
                }
                ?>
                <a href="<?php echo esc_url(add_query_arg($nolimit_args, $base_url)); ?>" class="<?php echo $nolimit_class; ?>"><?php echo $nolimit_label; ?></a>
            </div>
        </div>
        
        <?php if ($nolimit): ?>
        <div class="audit-warning">
            <strong>⚠️ No Limit Mode Active:</strong> This will scan ALL content and may take a long time on large sites. Results are streamed as they are found.
        </div>
        <?php endif; ?>
    <?php
}

/**
 * Render results for standard paginated mode
 * 
 * STRICT MODE: Only shows actionable references (same logic as updater)
 */
function audit_images_render_results($results, $phase, $limit, $offset, $missing_only, $nolimit, $base_url, $current_args) {
    ?>
        <div class="audit-summary">
            <p><strong>Phase:</strong> <?php echo esc_html(ucfirst($phase)); ?></p>
            <p><strong>Offset:</strong> <?php echo esc_html($offset); ?> | <strong>Limit:</strong> <?php echo esc_html($limit); ?></p>
            <p><strong>Rows scanned this page:</strong> <?php echo esc_html($results['rows_scanned']); ?></p>
            <p><strong>Convertible references found:</strong> <?php echo esc_html($results['total_references']); ?></p>
            <p><strong>Unique legacy files:</strong> <?php echo esc_html($results['unique_files']); ?></p>
            <?php if ($results['total_references'] == 0): ?>
            <p style="color: #28a745; font-weight: bold;">✅ No convertible references found - all legacy URLs have been updated or no eligible files exist.</p>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($results['findings'])): ?>
        <table class="audit-table">
            <thead>
                <tr>
                    <th>Location</th>
                    <th>Record ID</th>
                    <th>Legacy URL/Path</th>
                    <th>Proposed .webp</th>
                    <th>Context</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['findings'] as $finding): ?>
                <tr>
                    <td><?php echo esc_html($finding['location']); ?></td>
                    <td><?php echo esc_html($finding['record_id']); ?></td>
                    <td class="url-cell"><?php echo esc_html($finding['legacy_url']); ?></td>
                    <td class="url-cell"><?php echo esc_html($finding['webp_url']); ?></td>
                    <td class="context-cell"><?php echo esc_html($finding['context']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No convertible legacy image references found in this page of results.</p>
        <?php endif; ?>
        
        <div class="pagination">
            <?php if ($offset > 0): ?>
                <?php $prev_args = array_merge($current_args, ['offset' => max(0, $offset - $limit)]); ?>
                <a href="<?php echo esc_url(add_query_arg($prev_args, $base_url)); ?>">&laquo; Previous</a>
            <?php endif; ?>
            
            <?php if ($results['rows_scanned'] >= $limit): ?>
                <?php $next_args = array_merge($current_args, ['offset' => $offset + $limit]); ?>
                <a href="<?php echo esc_url(add_query_arg($next_args, $base_url)); ?>">Next &raquo;</a>
            <?php else: ?>
                <span>End of results for this phase</span>
            <?php endif; ?>
        </div>
    <?php
}

/**
 * Render nolimit mode with streaming output
 * 
 * STRICT MODE: Only shows ACTIONABLE references (same logic as updater)
 */
function audit_images_render_nolimit($phase, $batch_size, $missing_only, $baseurl, $basedir) {
    global $wpdb;
    
    // Determine which phases to scan
    $phases_to_scan = ($phase === 'all') ? ['posts', 'postmeta', 'options'] : [$phase];
    
    // Initialize counters
    $total_rows = 0;
    $total_refs = 0;
    $row_count = 0;
    
    // Start summary placeholder (will update via JS at end)
    ?>
    <div class="audit-summary" id="streaming-summary">
        <p><strong>Phase:</strong> <?php echo esc_html(ucfirst($phase)); ?></p>
        <p><strong>Mode:</strong> No Limit - Actionable References Only</p>
        <p id="summary-status"><strong>Status:</strong> Scanning...</p>
    </div>
    
    <table class="audit-table">
        <thead>
            <tr>
                <th>Location</th>
                <th>Record ID</th>
                <th>Legacy URL/Path</th>
                <th>Proposed .webp</th>
                <th>Context</th>
            </tr>
        </thead>
        <tbody>
    <?php
    
    // Flush output
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
    
    $uploads_pattern = '%/wp-content/uploads/%';
    
    foreach ($phases_to_scan as $current_phase) {
        $phase_offset = 0;
        $has_more = true;
        
        while ($has_more) {
            // Query batch based on phase - with same exclusions as updater
            switch ($current_phase) {
                case 'posts':
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT ID, post_type, post_status, post_content 
                         FROM {$wpdb->posts} 
                         WHERE post_content LIKE %s 
                           AND post_type != 'revision'
                           AND post_status != 'trash'
                         LIMIT %d OFFSET %d",
                        $uploads_pattern,
                        $batch_size,
                        $phase_offset
                    ));
                    break;
                    
                case 'postmeta':
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value, p.post_type, p.post_status
                         FROM {$wpdb->postmeta} pm
                         LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                         WHERE pm.meta_value LIKE %s 
                           AND (p.post_type IS NULL OR p.post_type != 'revision')
                           AND (p.post_status IS NULL OR p.post_status != 'trash')
                         LIMIT %d OFFSET %d",
                        $uploads_pattern,
                        $batch_size,
                        $phase_offset
                    ));
                    break;
                    
                case 'options':
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT option_name, option_value 
                         FROM {$wpdb->options} 
                         WHERE option_value LIKE %s 
                         LIMIT %d OFFSET %d",
                        $uploads_pattern,
                        $batch_size,
                        $phase_offset
                    ));
                    break;
                    
                default:
                    $rows = [];
            }
            
            if (empty($rows) || count($rows) < $batch_size) {
                $has_more = false;
            }
            
            foreach ($rows as $row) {
                $total_rows++;
                
                // Get content and record ID based on phase - with same exclusions as updater
                switch ($current_phase) {
                    case 'posts':
                        $content = $row->post_content;
                        $record_id = "ID: {$row->ID} ({$row->post_type})";
                        // Skip serialized
                        if (audit_images_is_serialized($content)) {
                            continue 2;
                        }
                        break;
                    case 'postmeta':
                        $content = $row->meta_value;
                        $record_id = "Post ID: {$row->post_id} | Key: {$row->meta_key}";
                        // Skip excluded meta keys
                        if (audit_images_is_excluded_meta_key($row->meta_key)) {
                            continue 2;
                        }
                        // Skip serialized
                        if (audit_images_is_serialized($content)) {
                            continue 2;
                        }
                        break;
                    case 'options':
                        $content = $row->option_value;
                        $record_id = "Option: {$row->option_name}";
                        // Skip excluded options
                        if (audit_images_is_excluded_option($row->option_name)) {
                            continue 2;
                        }
                        // Skip serialized
                        if (audit_images_is_serialized($content)) {
                            continue 2;
                        }
                        break;
                    default:
                        continue 2;
                }
                
                $matches = audit_images_extract_legacy_urls($content, $baseurl);
                
                foreach ($matches as $match) {
                    // Use convertible check (for audit detection)
                    $convertible = audit_images_is_convertible_reference($match['url'], $baseurl, $basedir);
                    
                    // Only show CONVERTIBLE references
                    if (!$convertible['convertible']) {
                        continue;
                    }
                    
                    $total_refs++;
                    $row_count++;
                    ?>
                    <tr>
                        <td><?php echo esc_html($current_phase); ?></td>
                        <td><?php echo esc_html($record_id); ?></td>
                        <td class="url-cell"><?php echo esc_html($match['url']); ?></td>
                        <td class="url-cell"><?php echo esc_html($convertible['webp_info']['webp_url']); ?></td>
                        <td class="context-cell"><?php echo esc_html($match['context']); ?></td>
                    </tr>
                    <?php
                }
            }
            
            $phase_offset += $batch_size;
            
            // Flush periodically
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }
    }
    
    ?>
        </tbody>
    </table>
    
    <script>
        document.getElementById('summary-status').innerHTML = 
            '<strong>Status:</strong> Complete<br>' +
            '<strong>Total rows scanned:</strong> <?php echo $total_rows; ?><br>' +
            '<strong>Convertible references found:</strong> <?php echo $total_refs; ?><br>' +
            '<strong>Rows displayed:</strong> <?php echo $row_count; ?>';
    </script>
    
    <div class="pagination">
        <span>No Limit mode - showing convertible references (legacy + webp both exist on disk)</span>
    </div>
    <?php
}

/**
 * Render page footer
 */
function audit_images_render_footer() {
    ?>
    </div>
    </body>
    </html>
    <?php
}

// =============================================================================
// SCANNING FUNCTIONS
// =============================================================================

/**
 * Scan with support for phase=all aggregation and missing_only filtering
 * 
 * @param string $phase Phase to scan: 'posts', 'postmeta', 'options', or 'all'
 * @param int $limit Number of rows to scan
 * @param int $offset Starting offset
 * @param bool $missing_only Only include findings where webp does not exist
 * @param string $baseurl Uploads base URL
 * @param string $basedir Uploads base directory
 * @return array Results array with findings and stats
 */
function audit_images_scan_aggregated($phase, $limit, $offset, $missing_only, $baseurl, $basedir) {
    global $wpdb;
    
    $results = [
        'rows_scanned' => 0,
        'total_references' => 0,
        'unique_files' => 0,
        'webp_exists_count' => 0,
        'webp_missing_count' => 0,
        'findings' => [],
    ];
    
    $unique_files = [];
    $uploads_pattern = '%/wp-content/uploads/%';
    
    // Determine which phases to scan
    if ($phase === 'all') {
        // For 'all' mode, we need to distribute the limit across phases
        // We'll scan each phase with the full limit/offset for simplicity
        // This means offset applies to each phase independently
        $phases_to_scan = ['posts', 'postmeta', 'options'];
        $per_phase_limit = ceil($limit / 3);
        $per_phase_offset = floor($offset / 3);
    } else {
        $phases_to_scan = [$phase];
        $per_phase_limit = $limit;
        $per_phase_offset = $offset;
    }
    
    foreach ($phases_to_scan as $current_phase) {
        $phase_results = audit_images_scan_single_phase(
            $current_phase, 
            $per_phase_limit, 
            $per_phase_offset, 
            $missing_only, 
            $baseurl, 
            $basedir
        );
        
        $results['rows_scanned'] += $phase_results['rows_scanned'];
        $results['total_references'] += $phase_results['total_references'];
        $results['webp_exists_count'] += $phase_results['webp_exists_count'];
        $results['webp_missing_count'] += $phase_results['webp_missing_count'];
        $results['findings'] = array_merge($results['findings'], $phase_results['findings']);
        
        foreach ($phase_results['unique_files'] as $url => $exists) {
            $unique_files[$url] = $exists;
        }
    }
    
    $results['unique_files'] = count($unique_files);
    
    return $results;
}

/**
 * Scan a single phase for ACTIONABLE legacy image references
 * 
 * STRICT MODE: Only returns references that the updater would actually process.
 * This means:
 * - Local URLs only (no external CDNs)
 * - Legacy file exists on disk
 * - WebP file exists on disk
 * - Not in excluded records (revisions, trash, serialized, transients)
 * 
 * @param string $phase Phase to scan: 'posts', 'postmeta', or 'options'
 * @param int $limit Number of rows to scan
 * @param int $offset Starting offset
 * @param bool $missing_only IGNORED - kept for API compatibility but audit is now strict
 * @param string $baseurl Uploads base URL
 * @param string $basedir Uploads base directory
 * @return array Results array with findings and stats
 */
function audit_images_scan_single_phase($phase, $limit, $offset, $missing_only, $baseurl, $basedir) {
    global $wpdb;
    
    $results = [
        'rows_scanned' => 0,
        'total_references' => 0,
        'webp_exists_count' => 0,
        'webp_missing_count' => 0,
        'findings' => [],
        'unique_files' => [],
    ];
    
    $uploads_pattern = '%/wp-content/uploads/%';
    
    switch ($phase) {
        case 'posts':
            // EXCLUDE revisions and trash - same as updater
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_type, post_status, post_content 
                 FROM {$wpdb->posts} 
                 WHERE post_content LIKE %s 
                   AND post_type != 'revision'
                   AND post_status != 'trash'
                 LIMIT %d OFFSET %d",
                $uploads_pattern,
                $limit,
                $offset
            ));
            
            foreach ($rows as $row) {
                $results['rows_scanned']++;
                
                // Skip serialized content - same as updater
                if (audit_images_is_serialized($row->post_content)) {
                    continue;
                }
                
                $matches = audit_images_extract_legacy_urls($row->post_content, $baseurl);
                
                foreach ($matches as $match) {
                    // Use convertible check (for audit detection)
                    $convertible = audit_images_is_convertible_reference($match['url'], $baseurl, $basedir);
                    
                    // Only count and show CONVERTIBLE references
                    if (!$convertible['convertible']) {
                        continue;
                    }
                    
                    $results['total_references']++;
                    $results['unique_files'][$match['url']] = true;
                    $results['webp_exists_count']++;
                    
                    $results['findings'][] = [
                        'location' => 'posts',
                        'record_id' => "ID: {$row->ID} ({$row->post_type})",
                        'legacy_url' => $match['url'],
                        'webp_url' => $convertible['webp_info']['webp_url'],
                        'webp_exists' => true,
                        'context' => $match['context'],
                    ];
                }
            }
            break;
            
        case 'postmeta':
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value, p.post_type, p.post_status
                 FROM {$wpdb->postmeta} pm
                 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_value LIKE %s 
                   AND (p.post_type IS NULL OR p.post_type != 'revision')
                   AND (p.post_status IS NULL OR p.post_status != 'trash')
                 LIMIT %d OFFSET %d",
                $uploads_pattern,
                $limit,
                $offset
            ));
            
            foreach ($rows as $row) {
                $results['rows_scanned']++;
                
                // Skip excluded meta keys - same as updater
                if (audit_images_is_excluded_meta_key($row->meta_key)) {
                    continue;
                }
                
                // Skip serialized content - same as updater
                if (audit_images_is_serialized($row->meta_value)) {
                    continue;
                }
                
                $matches = audit_images_extract_legacy_urls($row->meta_value, $baseurl);
                
                foreach ($matches as $match) {
                    // Use convertible check (for audit detection)
                    $convertible = audit_images_is_convertible_reference($match['url'], $baseurl, $basedir);
                    
                    // Only count and show CONVERTIBLE references
                    if (!$convertible['convertible']) {
                        continue;
                    }
                    
                    $results['total_references']++;
                    $results['unique_files'][$match['url']] = true;
                    $results['webp_exists_count']++;
                    
                    $results['findings'][] = [
                        'location' => 'postmeta',
                        'record_id' => "Post ID: {$row->post_id} | Key: {$row->meta_key}",
                        'legacy_url' => $match['url'],
                        'webp_url' => $convertible['webp_info']['webp_url'],
                        'webp_exists' => true,
                        'context' => $match['context'],
                    ];
                }
            }
            break;
            
        case 'options':
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT option_name, option_value 
                 FROM {$wpdb->options} 
                 WHERE option_value LIKE %s 
                 LIMIT %d OFFSET %d",
                $uploads_pattern,
                $limit,
                $offset
            ));
            
            foreach ($rows as $row) {
                $results['rows_scanned']++;
                
                // Skip excluded options - same as updater
                if (audit_images_is_excluded_option($row->option_name)) {
                    continue;
                }
                
                // Skip serialized content - same as updater
                if (audit_images_is_serialized($row->option_value)) {
                    continue;
                }
                
                $matches = audit_images_extract_legacy_urls($row->option_value, $baseurl);
                
                foreach ($matches as $match) {
                    // Use convertible check (for audit detection)
                    $convertible = audit_images_is_convertible_reference($match['url'], $baseurl, $basedir);
                    
                    // Only count and show CONVERTIBLE references
                    if (!$convertible['convertible']) {
                        continue;
                    }
                    
                    $results['total_references']++;
                    $results['unique_files'][$match['url']] = true;
                    $results['webp_exists_count']++;
                    
                    $results['findings'][] = [
                        'location' => 'options',
                        'record_id' => "Option: {$row->option_name}",
                        'legacy_url' => $match['url'],
                        'webp_url' => $convertible['webp_info']['webp_url'],
                        'webp_exists' => true,
                        'context' => $match['context'],
                    ];
                }
            }
            break;
    }
    
    return $results;
}

/**
 * Extract legacy image URLs from content
 * 
 * Detects URLs/paths ending in .jpg, .jpeg, .png (case-insensitive)
 * including size variants like -300x300.jpg
 * 
 * Handles:
 * - <img src="...">
 * - srcset="..."
 * - url(...)
 * - Raw URLs in JSON
 * - Protocol-relative URLs //example.com/...
 * - Root-relative URLs /wp-content/uploads/...
 * 
 * @param string $content Content to scan
 * @param string $baseurl Uploads base URL for filtering
 * @return array Array of matches with 'url' and 'context'
 */
function audit_images_extract_legacy_urls($content, $baseurl) {
    $matches = [];
    
    if (empty($content) || !is_string($content)) {
        return $matches;
    }
    
    // Pattern to match image URLs ending in jpg, jpeg, or png (case-insensitive)
    // Captures URLs that contain /wp-content/uploads/
    // Handles: full URLs, protocol-relative, root-relative, and escaped URLs in JSON
    $pattern = '/
        (?:
            (?:https?:)?                           # Optional protocol (http: https: or protocol-relative)
            \/\/[^\s"\'<>]+                        # Domain and path
            |                                      # OR
            \/wp-content\/uploads\/[^\s"\'<>]+     # Root-relative path
        )
        \/wp-content\/uploads\/                    # Must contain uploads path
        [^\s"\'<>]*?                               # Path components
        (?:-\d+x\d+)?                              # Optional size suffix like -300x300
        \.(?:jpe?g|png)                            # Extension: jpg, jpeg, or png
    /ix';
    
    // Simpler pattern that's more reliable
    $pattern = '/
        (
            (?:https?:)?\/\/[^\s"\'<>\)]+?\/wp-content\/uploads\/[^\s"\'<>\)]*?\.(?:jpe?g|png)
            |
            \/wp-content\/uploads\/[^\s"\'<>\)]+?\.(?:jpe?g|png)
        )
    /ix';
    
    // Also handle escaped URLs in JSON (with \/)
    $content_unescaped = str_replace('\\/', '/', $content);
    
    if (preg_match_all($pattern, $content_unescaped, $url_matches)) {
        foreach ($url_matches[1] as $url) {
            // Clean up the URL
            $url = trim($url, '"\'');
            $url = html_entity_decode($url);
            
            // Skip if already processed (dedup within same content)
            $url_key = $url;
            
            // Get context snippet
            $context = audit_images_get_context($content, $url);
            
            $matches[] = [
                'url' => $url,
                'context' => $context,
            ];
        }
    }
    
    return $matches;
}

/**
 * Get a context snippet around a URL match
 * 
 * @param string $content Full content
 * @param string $url URL to find context for
 * @return string Context snippet
 */
function audit_images_get_context($content, $url) {
    $pos = strpos($content, $url);
    if ($pos === false) {
        // Try with escaped slashes
        $escaped_url = str_replace('/', '\\/', $url);
        $pos = strpos($content, $escaped_url);
    }
    
    if ($pos === false) {
        return '(context not found)';
    }
    
    $start = max(0, $pos - 30);
    $length = strlen($url) + 60;
    $snippet = substr($content, $start, $length);
    
    // Clean up for display
    $snippet = preg_replace('/\s+/', ' ', $snippet);
    $snippet = trim($snippet);
    
    if ($start > 0) {
        $snippet = '...' . $snippet;
    }
    if ($start + $length < strlen($content)) {
        $snippet .= '...';
    }
    
    return $snippet;
}

/**
 * Compute the proposed WebP URL/path and check if it exists
 * 
 * @param string $legacy_url Original legacy image URL
 * @param string $baseurl Uploads base URL
 * @param string $basedir Uploads base directory
 * @return array ['webp_url' => string, 'webp_path' => string, 'exists' => bool, 'relative_path' => string]
 */
function audit_images_compute_webp($legacy_url, $baseurl, $basedir) {
    // Extract the uploads-relative path
    $relative_path = '';
    
    // Try to extract path after /wp-content/uploads/
    if (preg_match('/\/wp-content\/uploads\/(.+)$/i', $legacy_url, $matches)) {
        $relative_path = $matches[1];
    }
    
    if (empty($relative_path)) {
        return [
            'webp_url' => '(could not parse)',
            'webp_path' => '',
            'exists' => false,
            'relative_path' => '',
        ];
    }
    
    // Replace extension with .webp (case-insensitive)
    $webp_relative = preg_replace('/\.(jpe?g|png)$/i', '.webp', $relative_path);
    
    // Build full paths
    $webp_url = $baseurl . '/' . $webp_relative;
    $webp_path = $basedir . '/' . $webp_relative;
    
    // Check if WebP exists on disk
    $exists = file_exists($webp_path);
    
    return [
        'webp_url' => $webp_url,
        'webp_path' => $webp_path,
        'exists' => $exists,
        'relative_path' => $relative_path,
    ];
}

// =============================================================================
// UPDATE FUNCTIONS - Safe database updates
// =============================================================================

/**
 * Check if a string appears to be serialized PHP data
 * Conservative check - if in doubt, treat as serialized
 * 
 * @param string $data String to check
 * @return bool True if likely serialized
 */
function audit_images_is_serialized($data) {
    if (!is_string($data) || strlen($data) < 4) {
        return false;
    }
    
    // Check for common serialized patterns
    // a: = array, s: = string, O: = object, i: = integer, b: = boolean
    if (preg_match('/^[aOsib]:\d+/', $data) && strpos($data, ':{') !== false) {
        return true;
    }
    
    // Also check for serialized arrays/objects that might be nested
    if (preg_match('/[aO]:\d+:\{/', $data)) {
        return true;
    }
    
    return false;
}

/**
 * Check if an option name should be excluded from updates
 * 
 * @param string $option_name Option name to check
 * @return bool True if should be excluded
 */
function audit_images_is_excluded_option($option_name) {
    // Exclude transients and cache-like options
    $excluded_prefixes = [
        '_site_transient_',
        '_transient_',
        'transient_',
    ];
    
    foreach ($excluded_prefixes as $prefix) {
        if (strpos($option_name, $prefix) === 0) {
            return true;
        }
    }
    
    // Exclude options containing 'transient' or 'cache'
    if (stripos($option_name, 'transient') !== false || stripos($option_name, 'cache') !== false) {
        return true;
    }
    
    return false;
}

/**
 * Check if a URL is external (not in our uploads) or hosted on external CDN
 * 
 * @param string $url URL to check
 * @return bool True if external
 */
function audit_images_is_external_url($url) {
    // Must contain /wp-content/uploads/ to be internal
    if (strpos($url, '/wp-content/uploads/') === false) {
        return true;
    }
    
    // Check for known external CDN patterns
    $external_patterns = [
        'i0.wp.com',
        'i1.wp.com',
        'i2.wp.com',
        'i3.wp.com',
        'cdn.',
        '.cloudfront.net',
        '.amazonaws.com',
        'feeds.',
    ];
    
    foreach ($external_patterns as $pattern) {
        if (stripos($url, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if a meta_key should be excluded from audit/updates
 * 
 * @param string $meta_key Meta key to check
 * @return bool True if should be excluded
 */
function audit_images_is_excluded_meta_key($meta_key) {
    // Exclude cache-like and transient-like meta keys
    $excluded_patterns = [
        '_transient',
        '_cache',
        '_oembed_',
        '_edit_lock',
        '_edit_last',
    ];
    
    foreach ($excluded_patterns as $pattern) {
        if (stripos($meta_key, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if a reference is CONVERTIBLE (eligible for conversion)
 * 
 * Used by AUDIT to detect references that COULD be updated.
 * A reference is convertible if:
 * 1. URL is local (in our uploads, not external CDN)
 * 2. Legacy file (.jpg/.jpeg/.png) EXISTS on disk
 * 3. Corresponding .webp file EXISTS on disk
 * 
 * This does NOT check if the reference still exists in the DB field.
 * That check is done by audit_images_needs_update() for the updater.
 * 
 * @param string $url The legacy image URL
 * @param string $baseurl Uploads base URL
 * @param string $basedir Uploads base directory
 * @return array ['convertible' => bool, 'reason' => string, 'webp_info' => array, 'legacy_path' => string]
 */
function audit_images_is_convertible_reference($url, $baseurl, $basedir) {
    // Check 1: Must be local URL (not external)
    if (audit_images_is_external_url($url)) {
        return [
            'convertible' => false,
            'reason' => 'external',
            'webp_info' => null,
            'legacy_path' => '',
        ];
    }
    
    // Compute webp info and check paths
    $webp_info = audit_images_compute_webp($url, $baseurl, $basedir);
    
    // Check if we could parse the path
    if (empty($webp_info['relative_path'])) {
        return [
            'convertible' => false,
            'reason' => 'unparseable',
            'webp_info' => $webp_info,
            'legacy_path' => '',
        ];
    }
    
    // Build legacy path
    $legacy_path = $basedir . '/' . $webp_info['relative_path'];
    
    // Check 2: Legacy file must exist on disk
    if (!file_exists($legacy_path)) {
        return [
            'convertible' => false,
            'reason' => 'legacy_missing',
            'webp_info' => $webp_info,
            'legacy_path' => $legacy_path,
        ];
    }
    
    // Check 3: WebP file must exist on disk
    if (!$webp_info['exists']) {
        return [
            'convertible' => false,
            'reason' => 'webp_missing',
            'webp_info' => $webp_info,
            'legacy_path' => $legacy_path,
        ];
    }
    
    // All checks passed - this is convertible
    return [
        'convertible' => true,
        'reason' => 'convertible',
        'webp_info' => $webp_info,
        'legacy_path' => $legacy_path,
    ];
}

/**
 * Check if a reference NEEDS UPDATE (for updater use)
 * 
 * Used by UPDATER to determine if a specific reference should be replaced.
 * Returns true only if:
 * 1. Reference is convertible (local, legacy exists, webp exists)
 * 2. The legacy URL still exists verbatim in the field content
 * 
 * @param string $url The legacy image URL
 * @param string $field_content The current content of the DB field
 * @param string $baseurl Uploads base URL
 * @param string $basedir Uploads base directory
 * @return array ['needs_update' => bool, 'reason' => string, 'webp_info' => array]
 */
function audit_images_needs_update($url, $field_content, $baseurl, $basedir) {
    // First check if convertible
    $convertible = audit_images_is_convertible_reference($url, $baseurl, $basedir);
    
    if (!$convertible['convertible']) {
        return [
            'needs_update' => false,
            'reason' => $convertible['reason'],
            'webp_info' => $convertible['webp_info'],
        ];
    }
    
    // Check if legacy URL still exists in the field content
    // (it might have already been replaced with .webp)
    if (strpos($field_content, $url) === false) {
        // Also check escaped version for JSON
        $escaped_url = str_replace('/', '\\/', $url);
        if (strpos($field_content, $escaped_url) === false) {
            return [
                'needs_update' => false,
                'reason' => 'already_updated',
                'webp_info' => $convertible['webp_info'],
            ];
        }
    }
    
    // Needs update
    return [
        'needs_update' => true,
        'reason' => 'needs_update',
        'webp_info' => $convertible['webp_info'],
    ];
}

/**
 * Replace legacy image extension with .webp in a URL
 * Preserves querystring and fragment
 * 
 * @param string $url Original URL with legacy extension
 * @return string URL with .webp extension
 */
function audit_images_replace_extension_with_webp($url) {
    // Handle querystring and fragment
    $parts = parse_url($url);
    $path = isset($parts['path']) ? $parts['path'] : $url;
    
    // Replace extension (case-insensitive)
    $new_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
    
    // Rebuild URL with querystring/fragment if present
    $result = $new_path;
    if (isset($parts['query'])) {
        $result .= '?' . $parts['query'];
    }
    if (isset($parts['fragment'])) {
        $result .= '#' . $parts['fragment'];
    }
    
    return $result;
}

/**
 * Process content and replace legacy image references with webp
 * Only replaces when webp file exists on disk
 * 
 * @param string $content Original content
 * @param string $baseurl Uploads base URL
 * @param string $basedir Uploads base directory
 * @return array ['new_content' => string, 'replacements' => array, 'replacement_count' => int]
 */
function audit_images_process_content_for_update($content, $baseurl, $basedir) {
    $result = [
        'new_content' => $content,
        'replacements' => [],
        'replacement_count' => 0,
    ];
    
    if (empty($content) || !is_string($content)) {
        return $result;
    }
    
    // Extract all legacy URLs
    $matches = audit_images_extract_legacy_urls($content, $baseurl);
    
    if (empty($matches)) {
        return $result;
    }
    
    $new_content = $content;
    
    foreach ($matches as $match) {
        $legacy_url = $match['url'];
        
        // Skip external URLs
        if (audit_images_is_external_url($legacy_url)) {
            continue;
        }
        
        // Check if webp exists
        $webp_info = audit_images_compute_webp($legacy_url, $baseurl, $basedir);
        
        if (!$webp_info['exists']) {
            continue;
        }
        
        // Compute the replacement URL
        $webp_url = audit_images_replace_extension_with_webp($legacy_url);
        
        // Count occurrences before replacement
        $count_before = substr_count($new_content, $legacy_url);
        
        // Also handle escaped URLs in JSON
        $escaped_legacy = str_replace('/', '\\/', $legacy_url);
        $escaped_webp = str_replace('/', '\\/', $webp_url);
        
        // Perform replacements
        $new_content = str_replace($legacy_url, $webp_url, $new_content);
        $new_content = str_replace($escaped_legacy, $escaped_webp, $new_content);
        
        $count_after = substr_count($new_content, $legacy_url);
        $replaced_count = $count_before - $count_after;
        
        if ($replaced_count > 0) {
            $result['replacements'][] = [
                'from' => $legacy_url,
                'to' => $webp_url,
                'count' => $replaced_count,
            ];
            $result['replacement_count'] += $replaced_count;
        }
    }
    
    $result['new_content'] = $new_content;
    
    return $result;
}

/**
 * Get or create the log directory for audit changes
 * 
 * @return string|false Log directory path or false on failure
 */
function audit_images_get_log_dir() {
    $upload_dir = wp_get_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/image-audit-logs';
    
    if (!file_exists($log_dir)) {
        if (!wp_mkdir_p($log_dir)) {
            return false;
        }
        
        // Add index.php for security
        file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
        
        // Add .htaccess to deny direct access
        file_put_contents($log_dir . '/.htaccess', 'Deny from all');
    }
    
    return $log_dir;
}

/**
 * Write a change log entry
 * 
 * @param string $log_file Path to log file
 * @param array $entry Log entry data
 */
function audit_images_write_log($log_file, $entry) {
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";
    file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Render the update section on the main audit page
 */
function audit_images_render_update_section($phase, $limit, $offset, $allow_options, $base_url, $current_args) {
    // Build URLs for plan and apply actions
    $plan_args = array_merge($current_args, ['action' => 'plan']);
    $apply_args = array_merge($current_args, ['action' => 'apply']);
    
    // Remove nolimit for update actions
    unset($plan_args['nolimit']);
    unset($apply_args['nolimit']);
    unset($plan_args['missing_only']);
    unset($apply_args['missing_only']);
    
    ?>
    <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
        <h2 style="margin-top: 0;">🔄 Update to WebP</h2>
        <p style="color: #666;">
            Convert legacy image references (.jpg/.jpeg/.png) to .webp in the database.<br>
            <strong>Only updates references where the .webp file exists on disk.</strong>
        </p>
        
        <div style="margin: 15px 0; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
            <strong>⚠️ Safety Notes:</strong>
            <ul style="margin: 5px 0 0 20px; padding: 0;">
                <li>Excludes post revisions</li>
                <li>Excludes serialized data (will not corrupt)</li>
                <li>Excludes transients and cache options</li>
                <li>Options table updates require explicit <code>allow_options=1</code></li>
                <li>All changes are logged to <code>wp-content/uploads/image-audit-logs/</code></li>
            </ul>
        </div>
        
        <div style="margin: 15px 0;">
            <strong>Current scope:</strong> 
            Phase: <code><?php echo esc_html($phase); ?></code> | 
            Limit: <code><?php echo esc_html($limit); ?></code> | 
            Offset: <code><?php echo esc_html($offset); ?></code>
            <?php if ($phase === 'options' || $phase === 'all'): ?>
                <?php if ($allow_options): ?>
                    | <span style="color: #dc3545;">Options updates: ENABLED</span>
                <?php else: ?>
                    | <span style="color: #666;">Options updates: disabled (add <code>allow_options=1</code> to enable)</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 15px;">
            <a href="<?php echo esc_url(add_query_arg($plan_args, $base_url)); ?>" 
               style="display: inline-block; padding: 10px 20px; background: #0073aa; color: #fff; text-decoration: none; border-radius: 3px; margin-right: 10px;">
                📋 Dry Run (Plan)
            </a>
            <a href="<?php echo esc_url(add_query_arg($apply_args, $base_url)); ?>" 
               style="display: inline-block; padding: 10px 20px; background: #28a745; color: #fff; text-decoration: none; border-radius: 3px; margin-right: 10px;">
                ✅ Apply Updates
            </a>
        </div>
        
        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
            <strong>Process All Records:</strong>
            <p style="color: #666; font-size: 13px; margin: 5px 0 10px;">
                Process all matching records in batches (no pagination). Results stream as they complete.
            </p>
            <?php
            $process_all_args = ['action' => 'apply', 'phase' => 'all', 'process_all' => '1'];
            if ($allow_options) {
                $process_all_args['allow_options'] = '1';
            }
            ?>
            <a href="<?php echo esc_url(add_query_arg($process_all_args, $base_url)); ?>" 
               style="display: inline-block; padding: 10px 20px; background: #dc3545; color: #fff; text-decoration: none; border-radius: 3px;">
                🚀 Process ALL Records (posts + postmeta)
            </a>
        </div>
    </div>
    <?php
}

/**
 * Render the update page (plan or apply mode)
 */
function audit_images_render_update_page($action, $phase, $limit, $offset, $allow_options, $baseurl, $basedir, $base_url, $current_args) {
    global $wpdb;
    
    $is_apply = ($action === 'apply');
    $nonce_valid = false;
    $confirmed = false;
    
    // For apply, check nonce and confirmation
    if ($is_apply) {
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'audit_images_apply')) {
            $nonce_valid = true;
            $confirmed = isset($_POST['confirm_apply']) && $_POST['confirm_apply'] === '1';
        }
    }
    
    // Determine phases to process
    if ($phase === 'all') {
        $phases_to_process = ['posts', 'postmeta'];
        if ($allow_options) {
            $phases_to_process[] = 'options';
        }
    } elseif ($phase === 'options') {
        $phases_to_process = $allow_options ? ['options'] : [];
    } else {
        $phases_to_process = [$phase];
    }
    
    // Initialize stats
    $stats = [
        'references_found' => 0,
        'references_eligible' => 0,
        'references_updated' => 0,
        'records_scanned' => 0,
        'records_updated' => 0,
        'records_skipped_revision' => 0,
        'records_skipped_serialized' => 0,
        'records_skipped_external' => 0,
        'records_skipped_no_change' => 0,
    ];
    
    $plan_items = [];
    $log_file = null;
    
    // Create log file for apply mode
    if ($is_apply && $nonce_valid && $confirmed) {
        $log_dir = audit_images_get_log_dir();
        if ($log_dir) {
            $log_file = $log_dir . '/update-' . date('Y-m-d-His') . '.jsonl';
            audit_images_write_log($log_file, [
                'type' => 'header',
                'timestamp' => date('c'),
                'phase' => $phase,
                'limit' => $limit,
                'offset' => $offset,
                'allow_options' => $allow_options,
            ]);
        }
    }
    
    $uploads_pattern = '%/wp-content/uploads/%';
    
    // Process each phase
    foreach ($phases_to_process as $current_phase) {
        switch ($current_phase) {
            case 'posts':
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID, post_type, post_content 
                     FROM {$wpdb->posts} 
                     WHERE post_content LIKE %s 
                       AND post_type != 'revision'
                     LIMIT %d OFFSET %d",
                    $uploads_pattern,
                    $limit,
                    $offset
                ));
                
                foreach ($rows as $row) {
                    $stats['records_scanned']++;
                    
                    // Skip serialized content
                    if (audit_images_is_serialized($row->post_content)) {
                        $stats['records_skipped_serialized']++;
                        continue;
                    }
                    
                    $result = audit_images_process_content_for_update($row->post_content, $baseurl, $basedir);
                    
                    if ($result['replacement_count'] === 0) {
                        $stats['records_skipped_no_change']++;
                        continue;
                    }
                    
                    $stats['references_eligible'] += $result['replacement_count'];
                    
                    $plan_items[] = [
                        'phase' => 'posts',
                        'record_id' => $row->ID,
                        'post_type' => $row->post_type,
                        'replacements' => $result['replacements'],
                        'replacement_count' => $result['replacement_count'],
                    ];
                    
                    // Apply if confirmed
                    if ($is_apply && $nonce_valid && $confirmed) {
                        $updated = $wpdb->update(
                            $wpdb->posts,
                            ['post_content' => $result['new_content']],
                            ['ID' => $row->ID],
                            ['%s'],
                            ['%d']
                        );
                        
                        if ($updated !== false) {
                            $stats['records_updated']++;
                            $stats['references_updated'] += $result['replacement_count'];
                            
                            if ($log_file) {
                                audit_images_write_log($log_file, [
                                    'type' => 'update',
                                    'phase' => 'posts',
                                    'record_id' => $row->ID,
                                    'post_type' => $row->post_type,
                                    'replacements' => $result['replacements'],
                                    'replacement_count' => $result['replacement_count'],
                                    'timestamp' => date('c'),
                                ]);
                            }
                        }
                    }
                }
                break;
                
            case 'postmeta':
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT meta_id, post_id, meta_key, meta_value 
                     FROM {$wpdb->postmeta} 
                     WHERE meta_value LIKE %s 
                     LIMIT %d OFFSET %d",
                    $uploads_pattern,
                    $limit,
                    $offset
                ));
                
                foreach ($rows as $row) {
                    $stats['records_scanned']++;
                    
                    // Skip serialized content
                    if (audit_images_is_serialized($row->meta_value)) {
                        $stats['records_skipped_serialized']++;
                        continue;
                    }
                    
                    $result = audit_images_process_content_for_update($row->meta_value, $baseurl, $basedir);
                    
                    if ($result['replacement_count'] === 0) {
                        $stats['records_skipped_no_change']++;
                        continue;
                    }
                    
                    $stats['references_eligible'] += $result['replacement_count'];
                    
                    $plan_items[] = [
                        'phase' => 'postmeta',
                        'meta_id' => $row->meta_id,
                        'post_id' => $row->post_id,
                        'meta_key' => $row->meta_key,
                        'replacements' => $result['replacements'],
                        'replacement_count' => $result['replacement_count'],
                    ];
                    
                    // Apply if confirmed
                    if ($is_apply && $nonce_valid && $confirmed) {
                        $updated = $wpdb->update(
                            $wpdb->postmeta,
                            ['meta_value' => $result['new_content']],
                            ['meta_id' => $row->meta_id],
                            ['%s'],
                            ['%d']
                        );
                        
                        if ($updated !== false) {
                            $stats['records_updated']++;
                            $stats['references_updated'] += $result['replacement_count'];
                            
                            if ($log_file) {
                                audit_images_write_log($log_file, [
                                    'type' => 'update',
                                    'phase' => 'postmeta',
                                    'meta_id' => $row->meta_id,
                                    'post_id' => $row->post_id,
                                    'meta_key' => $row->meta_key,
                                    'replacements' => $result['replacements'],
                                    'replacement_count' => $result['replacement_count'],
                                    'timestamp' => date('c'),
                                ]);
                            }
                        }
                    }
                }
                break;
                
            case 'options':
                if (!$allow_options) {
                    continue 2;
                }
                
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT option_id, option_name, option_value 
                     FROM {$wpdb->options} 
                     WHERE option_value LIKE %s 
                     LIMIT %d OFFSET %d",
                    $uploads_pattern,
                    $limit,
                    $offset
                ));
                
                foreach ($rows as $row) {
                    $stats['records_scanned']++;
                    
                    // Skip excluded options
                    if (audit_images_is_excluded_option($row->option_name)) {
                        $stats['records_skipped_external']++;
                        continue;
                    }
                    
                    // Skip serialized content
                    if (audit_images_is_serialized($row->option_value)) {
                        $stats['records_skipped_serialized']++;
                        continue;
                    }
                    
                    $result = audit_images_process_content_for_update($row->option_value, $baseurl, $basedir);
                    
                    if ($result['replacement_count'] === 0) {
                        $stats['records_skipped_no_change']++;
                        continue;
                    }
                    
                    $stats['references_eligible'] += $result['replacement_count'];
                    
                    $plan_items[] = [
                        'phase' => 'options',
                        'option_id' => $row->option_id,
                        'option_name' => $row->option_name,
                        'replacements' => $result['replacements'],
                        'replacement_count' => $result['replacement_count'],
                    ];
                    
                    // Apply if confirmed
                    if ($is_apply && $nonce_valid && $confirmed) {
                        $updated = $wpdb->update(
                            $wpdb->options,
                            ['option_value' => $result['new_content']],
                            ['option_id' => $row->option_id],
                            ['%s'],
                            ['%d']
                        );
                        
                        if ($updated !== false) {
                            $stats['records_updated']++;
                            $stats['references_updated'] += $result['replacement_count'];
                            
                            if ($log_file) {
                                audit_images_write_log($log_file, [
                                    'type' => 'update',
                                    'phase' => 'options',
                                    'option_id' => $row->option_id,
                                    'option_name' => $row->option_name,
                                    'replacements' => $result['replacements'],
                                    'replacement_count' => $result['replacement_count'],
                                    'timestamp' => date('c'),
                                ]);
                            }
                        }
                    }
                }
                break;
        }
    }
    
    // Write footer to log
    if ($log_file) {
        audit_images_write_log($log_file, [
            'type' => 'footer',
            'stats' => $stats,
            'timestamp' => date('c'),
        ]);
    }
    
    // Render the page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Image Audit - <?php echo $is_apply ? 'Apply Updates' : 'Dry Run Plan'; ?></title>
        <style>
            .audit-images-wrap { max-width: 1400px; margin: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .audit-images-wrap h1 { margin-bottom: 20px; }
            .audit-summary { margin-bottom: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #0073aa; }
            .audit-summary p { margin: 5px 0; }
            .audit-success { background: #d4edda; border-left-color: #28a745; }
            .audit-warning { margin-bottom: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
            .audit-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 20px; }
            .audit-table th, .audit-table td { padding: 8px 10px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
            .audit-table th { background: #f0f0f0; font-weight: 600; }
            .audit-table tr:nth-child(even) { background: #fafafa; }
            .audit-table .url-cell { max-width: 400px; word-break: break-all; font-family: monospace; font-size: 11px; }
            .btn { display: inline-block; padding: 10px 20px; text-decoration: none; border-radius: 3px; margin-right: 10px; border: none; cursor: pointer; font-size: 14px; }
            .btn-primary { background: #0073aa; color: #fff; }
            .btn-success { background: #28a745; color: #fff; }
            .btn-secondary { background: #6c757d; color: #fff; }
        </style>
    </head>
    <body>
    <div class="audit-images-wrap">
        <h1>Image Audit — <?php echo $is_apply ? '✅ Apply Updates' : '📋 Dry Run Plan'; ?></h1>
        
        <?php if ($is_apply && (!$nonce_valid || !$confirmed)): ?>
            <div class="audit-warning">
                <h3>⚠️ Confirmation Required</h3>
                <p>You are about to update the database. This action will modify:</p>
                <ul>
                    <li><strong><?php echo count($plan_items); ?></strong> records</li>
                    <li><strong><?php echo $stats['references_eligible']; ?></strong> image references</li>
                </ul>
                <p>Phase: <code><?php echo esc_html($phase); ?></code> | Limit: <code><?php echo $limit; ?></code> | Offset: <code><?php echo $offset; ?></code></p>
                
                <form method="post" style="margin-top: 15px;">
                    <?php wp_nonce_field('audit_images_apply'); ?>
                    <label style="display: block; margin-bottom: 15px;">
                        <input type="checkbox" name="confirm_apply" value="1" required>
                        I understand this will modify the database and have a backup ready
                    </label>
                    <button type="submit" class="btn btn-success">✅ Confirm and Apply Updates</button>
                    <a href="<?php echo esc_url(add_query_arg(['action' => 'plan'] + $current_args, $base_url)); ?>" class="btn btn-secondary">← Back to Plan</a>
                </form>
            </div>
        <?php else: ?>
            <div class="audit-summary <?php echo ($is_apply && $confirmed) ? 'audit-success' : ''; ?>">
                <h3><?php echo ($is_apply && $confirmed) ? '✅ Updates Applied' : '📋 Dry Run Results'; ?></h3>
                <p><strong>Records scanned:</strong> <?php echo $stats['records_scanned']; ?></p>
                <p><strong>Records with eligible updates:</strong> <?php echo count($plan_items); ?></p>
                <p><strong>References eligible for update:</strong> <?php echo $stats['references_eligible']; ?></p>
                <?php if ($is_apply && $confirmed): ?>
                <p><strong>Records updated:</strong> <span style="color: #28a745; font-weight: bold;"><?php echo $stats['records_updated']; ?></span></p>
                <p><strong>References updated:</strong> <span style="color: #28a745; font-weight: bold;"><?php echo $stats['references_updated']; ?></span></p>
                <?php if ($log_file): ?>
                <p><strong>Log file:</strong> <code><?php echo esc_html(basename($log_file)); ?></code></p>
                <?php endif; ?>
                <?php endif; ?>
                <hr style="margin: 10px 0;">
                <p><strong>Skipped (serialized):</strong> <?php echo $stats['records_skipped_serialized']; ?></p>
                <p><strong>Skipped (no change/missing webp):</strong> <?php echo $stats['records_skipped_no_change']; ?></p>
                <p><strong>Skipped (excluded options):</strong> <?php echo $stats['records_skipped_external']; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($plan_items) && !($is_apply && !$confirmed)): ?>
        <h3><?php echo ($is_apply && $confirmed) ? 'Applied Changes' : 'Planned Changes'; ?></h3>
        <table class="audit-table">
            <thead>
                <tr>
                    <th>Phase</th>
                    <th>Record</th>
                    <th>Replacements</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plan_items as $item): ?>
                <tr>
                    <td><?php echo esc_html($item['phase']); ?></td>
                    <td>
                        <?php 
                        if ($item['phase'] === 'posts') {
                            echo "Post ID: " . esc_html($item['record_id']) . " (" . esc_html($item['post_type']) . ")";
                        } elseif ($item['phase'] === 'postmeta') {
                            echo "Meta ID: " . esc_html($item['meta_id']) . " (Post: " . esc_html($item['post_id']) . ", Key: " . esc_html($item['meta_key']) . ")";
                        } else {
                            echo "Option: " . esc_html($item['option_name']);
                        }
                        ?>
                    </td>
                    <td>
                        <?php foreach ($item['replacements'] as $r): ?>
                        <div style="margin-bottom: 5px; font-size: 11px;">
                            <span style="color: #dc3545;"><?php echo esc_html($r['from']); ?></span><br>
                            → <span style="color: #28a745;"><?php echo esc_html($r['to']); ?></span>
                            <span style="color: #666;">(×<?php echo $r['count']; ?>)</span>
                        </div>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php elseif (empty($plan_items)): ?>
        <p>No eligible updates found in this batch. All references either have missing .webp files or are in serialized/excluded content.</p>
        <?php endif; ?>
        
        <div style="margin-top: 20px;">
            <a href="<?php echo esc_url(remove_query_arg('action', add_query_arg($current_args, $base_url))); ?>" class="btn btn-secondary">← Back to Audit</a>
            <?php if (!$is_apply && !empty($plan_items)): ?>
            <a href="<?php echo esc_url(add_query_arg(['action' => 'apply'] + $current_args, $base_url)); ?>" class="btn btn-success">✅ Apply These Updates</a>
            <?php endif; ?>
        </div>
    </div>
    </body>
    </html>
    <?php
}

/**
 * Render the process-all page with streaming batch updates
 */
function audit_images_render_process_all_page($action, $phase, $allow_options, $baseurl, $basedir, $base_url, $current_args) {
    global $wpdb;
    
    $is_apply = ($action === 'apply');
    $nonce_valid = false;
    $confirmed = false;
    
    // For apply, check nonce and confirmation
    if ($is_apply) {
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'audit_images_apply_all')) {
            $nonce_valid = true;
            $confirmed = isset($_POST['confirm_apply']) && $_POST['confirm_apply'] === '1';
        }
    }
    
    // Determine phases to process
    if ($phase === 'all') {
        $phases_to_process = ['posts', 'postmeta'];
        if ($allow_options) {
            $phases_to_process[] = 'options';
        }
    } elseif ($phase === 'options') {
        $phases_to_process = $allow_options ? ['options'] : [];
    } else {
        $phases_to_process = [$phase];
    }
    
    $batch_size = 500;
    $uploads_pattern = '%/wp-content/uploads/%';
    
    // Render page header
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Image Audit - Process All</title>
        <style>
            .audit-images-wrap { max-width: 1400px; margin: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .audit-images-wrap h1 { margin-bottom: 20px; }
            .audit-summary { margin-bottom: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #0073aa; }
            .audit-summary p { margin: 5px 0; }
            .audit-success { background: #d4edda; border-left-color: #28a745; }
            .audit-warning { margin-bottom: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
            .audit-danger { margin-bottom: 20px; padding: 15px; background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
            .btn { display: inline-block; padding: 10px 20px; text-decoration: none; border-radius: 3px; margin-right: 10px; border: none; cursor: pointer; font-size: 14px; }
            .btn-success { background: #28a745; color: #fff; }
            .btn-secondary { background: #6c757d; color: #fff; }
            .btn-danger { background: #dc3545; color: #fff; }
            .progress-log { background: #1e1e1e; color: #d4d4d4; padding: 15px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; margin: 20px 0; border-radius: 4px; }
            .progress-log .success { color: #4ec9b0; }
            .progress-log .warning { color: #dcdcaa; }
            .progress-log .info { color: #9cdcfe; }
            .progress-log .error { color: #f14c4c; }
        </style>
    </head>
    <body>
    <div class="audit-images-wrap">
        <h1>Image Audit — 🚀 Process All Records</h1>
        
        <?php if (!$is_apply || !$nonce_valid || !$confirmed): ?>
            <div class="audit-danger">
                <h3>⚠️ Process All Confirmation Required</h3>
                <p>You are about to update <strong>ALL</strong> matching records in the database.</p>
                <p>This will process:</p>
                <ul>
                    <?php foreach ($phases_to_process as $p): ?>
                    <li><strong><?php echo esc_html(ucfirst($p)); ?></strong></li>
                    <?php endforeach; ?>
                </ul>
                <p><strong>Make sure you have a database backup before proceeding!</strong></p>
                
                <form method="post" style="margin-top: 15px;">
                    <?php wp_nonce_field('audit_images_apply_all'); ?>
                    <label style="display: block; margin-bottom: 15px;">
                        <input type="checkbox" name="confirm_apply" value="1" required>
                        I have a database backup and understand this will modify ALL matching records
                    </label>
                    <button type="submit" class="btn btn-danger">🚀 Confirm and Process All</button>
                    <a href="<?php echo esc_url(remove_query_arg(['action', 'process_all'], add_query_arg($current_args, $base_url))); ?>" class="btn btn-secondary">← Cancel</a>
                </form>
            </div>
        <?php else: ?>
            <div class="audit-summary">
                <p><strong>Processing phases:</strong> <?php echo esc_html(implode(', ', $phases_to_process)); ?></p>
                <p><strong>Batch size:</strong> <?php echo $batch_size; ?></p>
                <p id="status-line"><strong>Status:</strong> Starting...</p>
            </div>
            
            <div class="progress-log" id="progress-log">
    <?php
    
    // Flush initial output
    if (ob_get_level()) ob_flush();
    flush();
    
    // Create log file
    $log_dir = audit_images_get_log_dir();
    $log_file = null;
    if ($log_dir) {
        $log_file = $log_dir . '/update-all-' . date('Y-m-d-His') . '.jsonl';
        audit_images_write_log($log_file, [
            'type' => 'header',
            'timestamp' => date('c'),
            'phase' => $phase,
            'phases_processed' => $phases_to_process,
            'allow_options' => $allow_options,
            'batch_size' => $batch_size,
        ]);
        echo '<div class="info">[' . date('H:i:s') . '] Log file: ' . esc_html(basename($log_file)) . '</div>';
        if (ob_get_level()) ob_flush();
        flush();
    }
    
    // Initialize totals
    $total_stats = [
        'records_scanned' => 0,
        'records_updated' => 0,
        'records_skipped_serialized' => 0,
        'records_skipped_excluded' => 0,
        'records_skipped_no_change' => 0,
        'references_updated' => 0,
    ];
    
    // Process each phase
    foreach ($phases_to_process as $current_phase) {
        echo '<div class="info">[' . date('H:i:s') . '] === Processing phase: ' . esc_html(strtoupper($current_phase)) . ' ===</div>';
        if (ob_get_level()) ob_flush();
        flush();
        
        $phase_offset = 0;
        $phase_updated = 0;
        $has_more = true;
        
        while ($has_more) {
            switch ($current_phase) {
                case 'posts':
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT ID, post_type, post_content 
                         FROM {$wpdb->posts} 
                         WHERE post_content LIKE %s 
                           AND post_type != 'revision'
                         LIMIT %d OFFSET %d",
                        $uploads_pattern,
                        $batch_size,
                        $phase_offset
                    ));
                    break;
                    
                case 'postmeta':
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT meta_id, post_id, meta_key, meta_value 
                         FROM {$wpdb->postmeta} 
                         WHERE meta_value LIKE %s 
                         LIMIT %d OFFSET %d",
                        $uploads_pattern,
                        $batch_size,
                        $phase_offset
                    ));
                    break;
                    
                case 'options':
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT option_id, option_name, option_value 
                         FROM {$wpdb->options} 
                         WHERE option_value LIKE %s 
                         LIMIT %d OFFSET %d",
                        $uploads_pattern,
                        $batch_size,
                        $phase_offset
                    ));
                    break;
                    
                default:
                    $rows = [];
            }
            
            if (empty($rows) || count($rows) < $batch_size) {
                $has_more = false;
            }
            
            $batch_updated = 0;
            
            foreach ($rows as $row) {
                $total_stats['records_scanned']++;
                
                // Get content based on phase
                switch ($current_phase) {
                    case 'posts':
                        $content = $row->post_content;
                        $record_id = $row->ID;
                        $record_desc = "Post {$row->ID} ({$row->post_type})";
                        break;
                    case 'postmeta':
                        $content = $row->meta_value;
                        $record_id = $row->meta_id;
                        $record_desc = "Meta {$row->meta_id} (post {$row->post_id}, key: {$row->meta_key})";
                        break;
                    case 'options':
                        $content = $row->option_value;
                        $record_id = $row->option_id;
                        $record_desc = "Option: {$row->option_name}";
                        
                        // Skip excluded options
                        if (audit_images_is_excluded_option($row->option_name)) {
                            $total_stats['records_skipped_excluded']++;
                            continue 2;
                        }
                        break;
                    default:
                        continue 2;
                }
                
                // Skip serialized
                if (audit_images_is_serialized($content)) {
                    $total_stats['records_skipped_serialized']++;
                    continue;
                }
                
                // Process content
                $result = audit_images_process_content_for_update($content, $baseurl, $basedir);
                
                if ($result['replacement_count'] === 0) {
                    $total_stats['records_skipped_no_change']++;
                    continue;
                }
                
                // Apply update
                switch ($current_phase) {
                    case 'posts':
                        $updated = $wpdb->update(
                            $wpdb->posts,
                            ['post_content' => $result['new_content']],
                            ['ID' => $record_id],
                            ['%s'],
                            ['%d']
                        );
                        break;
                    case 'postmeta':
                        $updated = $wpdb->update(
                            $wpdb->postmeta,
                            ['meta_value' => $result['new_content']],
                            ['meta_id' => $record_id],
                            ['%s'],
                            ['%d']
                        );
                        break;
                    case 'options':
                        $updated = $wpdb->update(
                            $wpdb->options,
                            ['option_value' => $result['new_content']],
                            ['option_id' => $record_id],
                            ['%s'],
                            ['%d']
                        );
                        break;
                    default:
                        $updated = false;
                }
                
                if ($updated !== false) {
                    $total_stats['records_updated']++;
                    $total_stats['references_updated'] += $result['replacement_count'];
                    $batch_updated++;
                    $phase_updated++;
                    
                    echo '<div class="success">[' . date('H:i:s') . '] ✓ Updated ' . esc_html($record_desc) . ' (' . $result['replacement_count'] . ' refs)</div>';
                    
                    if ($log_file) {
                        audit_images_write_log($log_file, [
                            'type' => 'update',
                            'phase' => $current_phase,
                            'record_id' => $record_id,
                            'record_desc' => $record_desc,
                            'replacements' => $result['replacements'],
                            'replacement_count' => $result['replacement_count'],
                            'timestamp' => date('c'),
                        ]);
                    }
                }
            }
            
            echo '<div class="info">[' . date('H:i:s') . '] Batch complete: offset=' . $phase_offset . ', scanned=' . count($rows) . ', updated=' . $batch_updated . '</div>';
            if (ob_get_level()) ob_flush();
            flush();
            
            $phase_offset += $batch_size;
        }
        
        echo '<div class="warning">[' . date('H:i:s') . '] Phase ' . esc_html($current_phase) . ' complete: ' . $phase_updated . ' records updated</div>';
        if (ob_get_level()) ob_flush();
        flush();
    }
    
    // Write footer to log
    if ($log_file) {
        audit_images_write_log($log_file, [
            'type' => 'footer',
            'stats' => $total_stats,
            'timestamp' => date('c'),
        ]);
    }
    
    echo '<div class="success">[' . date('H:i:s') . '] === ALL PHASES COMPLETE ===</div>';
    ?>
            </div>
            
            <div class="audit-summary audit-success">
                <h3>✅ Processing Complete</h3>
                <p><strong>Records scanned:</strong> <?php echo $total_stats['records_scanned']; ?></p>
                <p><strong>Records updated:</strong> <span style="color: #28a745; font-weight: bold;"><?php echo $total_stats['records_updated']; ?></span></p>
                <p><strong>References updated:</strong> <span style="color: #28a745; font-weight: bold;"><?php echo $total_stats['references_updated']; ?></span></p>
                <hr style="margin: 10px 0;">
                <p><strong>Skipped (serialized):</strong> <?php echo $total_stats['records_skipped_serialized']; ?></p>
                <p><strong>Skipped (excluded options):</strong> <?php echo $total_stats['records_skipped_excluded']; ?></p>
                <p><strong>Skipped (no eligible changes):</strong> <?php echo $total_stats['records_skipped_no_change']; ?></p>
                <?php if ($log_file): ?>
                <p><strong>Log file:</strong> <code><?php echo esc_html(basename($log_file)); ?></code></p>
                <?php endif; ?>
            </div>
            
            <script>
                document.getElementById('status-line').innerHTML = '<strong>Status:</strong> <span style="color: #28a745;">Complete!</span>';
                document.getElementById('progress-log').scrollTop = document.getElementById('progress-log').scrollHeight;
            </script>
        <?php endif; ?>
        
        <div style="margin-top: 20px;">
            <a href="<?php echo esc_url(remove_query_arg(['action', 'process_all'], add_query_arg($current_args, $base_url))); ?>" class="btn btn-secondary">← Back to Audit</a>
        </div>
    </div>
    </body>
    </html>
    <?php
}
