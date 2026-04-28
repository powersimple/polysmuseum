---
title: Code Conventions
focus: quality
last_mapped: 2026-04-28
---

# Code Conventions

## PHP Style

### General
- WordPress coding conventions followed loosely (not strictly enforced)
- Files loaded at WordPress `init` hook via `functions.php` — no autoloader
- Global `$wpdb` used directly for DB queries
- `extract((array) $object)` used frequently to unpack wpdb row objects
- No namespace declarations; functions are global

### Function Naming
- Snake_case: `get_megamenu_data()`, `polys_is_dev()`, `render_megamenu()`
- No class-based OOP in most files; exceptions: `ICS`, `MenuVars`, `RestAPIFilterFields`, `Profiler`
- Prefix convention inconsistent — newer files use `polys_` prefix (`polys_is_dev()`), older files have no prefix

### Database Queries
- `$wpdb->get_results()` used throughout for SELECT queries
- `$wpdb->prepare()` used in newer/security-conscious code (e.g., megamenu functions)
- Direct string interpolation still present in older function files (`functions-profiles.php`, `functions-events.php`)
- REST API results filtered with `$GLOBALS['REST_post_filter']` for pagination/sort consistency

### Error Handling
- `@` operator (suppress errors) used in legacy code: `@$meta['email'][0]`, `@$nominee_meta_list`
- `isset()` / `empty()` checks inconsistent across files
- Newer files use `!defined('ABSPATH') exit;` guard at top
- Older files have no ABSPATH guard

### Comments
- DocBlocks present in newer files (`functions-megamenu.php`, `functions-enqueue.php`, `functions-profile-auth.php`)
- Older files have minimal or no documentation
- `@deprecated` annotations used in `functions-custom-menu-admin.php` for legacy functions
- Inline debug comments (`// DEBUG: temporary`, `//BUG THIS RETURNS...`) scattered throughout

## JavaScript Style

### Legacy (app/js/custom/)
- ES5 / jQuery patterns
- IIFE pattern not consistently used
- Global variables via `window.*` or bare globals
- `var` declarations (not let/const)
- Callbacks rather than Promises

### Modern (app/js/modern/, megamenu-controller.js)
- ES6+ with IIFE wrapper: `(function() { 'use strict'; ... })()`
- Class-based: `class MegaMenu { constructor() {} init() {} }`
- `const`/`let` throughout
- Arrow functions
- `document.querySelector` / `addEventListener` (no jQuery)
- Initialization guard: `if (window.MegaMenuInitialized) return;`
- JSDoc comments for public methods

## SCSS / CSS Style

### Methodology
- BEM (Block__Element--Modifier): `.megamenu__bar`, `.megamenu__panel--open`
- Utility classes mixed in: `.container-flex`, `.d-flex`, `.col-md-7`
- Bootstrap-style grid column classes

### File Organization
- `app/scss/style.scss` — single entry point with `@import` statements
- `app/scss/partials/` — one file per UI component/section
- Variables defined at top of individual files (not a central variables file)
- Reduced-motion support: `@media (prefers-reduced-motion: reduce)` in megamenu

### Naming
- Lowercase kebab-case for class names
- BEM blocks match PHP render function names where possible (`.megamenu` ↔ `render_megamenu()`)

## Asset Loading Rules

From `functions/functions-enqueue.php` (enforced by comments):
- **DO NOT** add hardcoded `<script>` or `<link>` tags in templates
- All theme assets must flow through the enqueue function
- Environment-specific loading via `polys_is_dev()`
- Cache-busting via `filemtime()` for both dev and prod

## WordPress Template Conventions

- Page templates use `page-{slug}.php` filename convention
- Header included via `header.php` (outputs full `<html>` to `<body>`)
- Footer included via `footer.php`
- `global $post` referenced in templates; null-safe access pattern in `header.php`:
  ```php
  $current_post_id = $_has_post && isset($post->ID) ? (int) $post->ID : 0;
  ```
- MetaBox fields accessed via `get_post_meta($id, 'field_key', true)` or `rwmb_meta()`

## Environment Detection Pattern

```php
// CANONICAL — do not duplicate this logic
function polys_is_dev() {
    return isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'obi-wan-v:3000';
}
```

## Debug/Development Patterns

- `POLYSMUSEUM_SHOW_TEMPLATE_DEBUG` constant for template path overlay
- `POLYSMUSEUM_DEPLOY_CHECK` date constant in `functions.php` for prod verification
- WP_DEBUG gates used for conditional debug output
- Admin-only checks for debug HTML output
- Dev title prefix: emoji-decorated page title in dev/staging environments
