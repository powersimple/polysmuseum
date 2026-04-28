---
title: Technical Concerns
focus: concerns
last_mapped: 2026-04-28
---

# Technical Concerns

## HIGH — Security

### SQL Injection Risk
- **Files**: `functions/functions-profiles.php`, `functions/functions-events.php`, `functions/functions-mailing-list.php`, `functions/functions-awards.php`
- Direct string interpolation in `$wpdb->get_results()` and `$wpdb->query()` without `$wpdb->prepare()`:
  ```php
  $sql = "select post_id from wp_postmeta where meta_value = $id and meta_key like 'event_%'";
  $sql = "select * from wp_posts where post_type = 'profile' and post_status = 'publish'";
  ```
- Newer files (megamenu, profile-auth) correctly use `$wpdb->prepare()`
- **Risk**: If any of the unsanitized values come from user input, SQL injection is possible

### SSL Certificates Committed to Repo
- `localhost.crt` and `localhost.key` are committed to the git repository
- These are self-signed dev certs, but the pattern is dangerous if prod certs are ever added
- Should be added to `.gitignore` and managed separately

### Error Suppression Hiding Failures
- `@` operator used throughout legacy code hides real errors
- Examples: `@$meta['email'][0]`, `@$nominee_meta_list`, `@$speaker['meta']['email'][0]`
- Masked errors may represent security-relevant failures

## HIGH — Code Quality

### Very Large Page Files
- `page-exhibits.php` — 128KB (largest template file)
- `page-audit.php` — 61KB
- `page-the-1st-polys-webxr-awards.php` — 112KB
- These files are unmaintainable at this size; logic should be extracted to functions

### No Test Suite
- Zero automated testing across PHP and JavaScript
- All validation is manual; regressions are undetected until production
- See TESTING.md for full coverage gap analysis

### Direct SQL Without ORM or Abstraction
- ~749 PHP functions across 57 files with no service layer
- Business logic mixed directly into template files
- Functions access `$wpdb` globally with no data access abstraction

## MEDIUM — Technical Debt

### Dead Code / Backup Files
Multiple backup and abandoned files in the root directory:
- `BACKUPpage-ballot.php` — old ballot backup
- `OLDSINGLEEVENT.php` — old single event template
- `__front-page.php`, `xfront-page.php`, `xfront-page-bkup.php` — front page variants
- `xfunctions.php` — old functions file
- `xindex.php` — old index
- `xpage-the-polys.php` — old Polys page
- `__temp-production-summit-static.html` — 76KB static HTML dump
- `functions/ENDPOINTS-ITERATION-UNFINISHED.php` — explicitly named as unfinished
- `nul` file (14KB) at root — unclear purpose

### Large Binary in Repo
- `Polysmuseum.zip` (43MB) committed to git repo
- Severely bloats repo size and clone time
- Should be removed from history with git-filter-repo

### Compiled Assets Committed to Repo
- `main.js`, `main.min.js`, `vendor.js`, `vendor.min.js` — built files in version control
- `style.css`, `style.min.css`, `build/` — compiled CSS and Vite output
- Causes unnecessary diffs and merge conflicts

### Legacy JavaScript Dominance
- Most interactive behavior still relies on legacy jQuery pipeline (`app/js/custom/`)
- Modern pipeline (`app/js/modern/`) exists but only has megamenu so far
- Migration path exists (enqueue.php documents it) but is incomplete

### Deprecated Functions in Active Use
- Multiple `@deprecated` annotations in `functions/functions-custom-menu-admin.php`
- Deprecated functions still called by templates (not removed)
- No deprecation schedule or replacement timeline documented

### Inconsistent Function Prefixing
- Some functions: `polys_*` prefix (newer)
- Most functions: no prefix (older) — global namespace pollution risk
- Mix makes it hard to identify theme-owned functions

## MEDIUM — Architecture

### page-*.php File Proliferation
- ~60 page template files in root — no subdirectory organization
- Many are very similar (summit series variations, awards show variants)
- Could benefit from a shared template + data pattern

### Dual Megamenu in Transition
- Old `#main-menu` / `#megamenu-linear-nav` commented out but not removed from `header.php`
- Two parallel megamenu systems exist (legacy commented out, modern active)
- Commented-out code is noise; old system should be fully removed when confirmed working

### Data in `data/` vs `app/json/` — Unclear Separation
- `data/` directory has JSON files + a PHP ingestion script
- `app/json/` has different JSON files
- No documented policy for which data lives where

## LOW — Performance

### HMR Disabled in Vite Config
- `hmr: false` in `vite.config.mjs` — custom WSS on port 3001 handles reload instead
- Full page reload on every change (no hot module replacement)
- Acceptable for PHP-based workflow but slower DX than true HMR

### Large Single-Bundle JS
- All `app/js/custom/*.js` files concatenated into a single `main.js` — no code splitting
- All vendor files concatenated into a single `vendor.js`
- No lazy loading or route-based splitting

### SCSS Compilation Speed
- All SCSS compiled as one bundle; no incremental compilation
- On large changes, full recompile required

## LOW — Maintainability

### Session Notes as Documentation
- `SESSION-NOTES.md` in repo root documents megamenu rebuild
- Good intent but informal; should be migrated to proper docs
- `docs/PAGE-FRAME-ARCHITECTURE.md` is a better example of structured docs

### `view-source_*.html` Files
- Two large HTML dump files (742KB, 719KB) committed to root
- Are reference snapshots but add noise and bloat

### `functions/new/` Directory
- Empty or placeholder `new/` directory inside `functions/`
- Unclear purpose
