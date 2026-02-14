# Polys Museum WordPress Theme - Claude Code Guide

## Theme Overview
- **Name:** Polys Museum | **Text Domain:** `polysmuseum` | **Author:** Ben Erwin, Powersimple, LLC
- **Purpose:** Immersive arts events platform (The Polys Awards, Academy, Summit Series, MetaTr@versal)
- **Stack:** WordPress + Vite + SCSS + jQuery (legacy) / ES6 modules (modern) + A-Frame + Cesium.js
- **Branch:** `academy2026` (main: `master`)

## IMPORTANT: No Git Operations
Claude should NEVER run git commands in this project. The user manages version control manually.

## Directory Structure
```
polysmuseum/
├── app/                    # SOURCE CODE - edit here
│   ├── js/custom/          # Custom JS (jQuery-based legacy)
│   ├── js/modern/          # Modern ES6 modules (no jQuery)
│   ├── js/vendor/          # Third-party JS libs (don't edit)
│   ├── js/vault/           # Archived legacy JS (don't edit)
│   ├── scss/               # SCSS source (style.scss is entry)
│   │   └── partials/       # All SCSS partials (48 files)
│   ├── includes/           # PHP includes
│   ├── json/               # Static JSON data exports
│   ├── aframe/             # A-Frame XR scene files
│   └── xr/                 # XR assets
├── functions/              # PHP feature modules (one per feature)
├── templates/              # Template parts
├── build/                  # Vite build output (DO NOT EDIT)
├── cesium/                 # Cesium.js library (DO NOT EDIT)
├── node_modules/           # NPM deps (DO NOT EDIT)
├── webxr/                  # WebXR experiences (polys, academy, summits)
├── fonts/                  # Raleway TTF files
├── images/                 # Theme images
├── admin/                  # WP admin customizations
├── config/                 # Config files
├── docs/                   # Documentation
└── web-spatial/            # Web spatial computing module
```

## Coding Standards

### PHP
- **Function naming:** Snake case with `polys_` prefix: `polys_get_current_brand()`
- **Variables:** Camel case for locals: `$heroImage`, `$sectionClass`
- **Post meta keys:** Snake case: `use_aframe`, `embed_video_url`, `hero`
- **File naming:** `functions-{feature}.php` in `functions/` directory
- **One feature per file** - loaded via `functions.php` init action
- **Hook prefixes:** `polys_`, `render_`, `get_`, `add_`
- **WordPress standards:** Use `esc_html()`, `esc_attr()`, `wp_kses()` for output escaping

### SCSS
- **Entry point:** `app/scss/style.scss` (uses `@use` statements, NOT `@import`)
- **Partials:** `app/scss/partials/` with `_prefix.scss` naming
- **Naming:** BEM convention (`.block__element--modifier`)
- **Variables:** Defined in `_globals.scss` and `colors.scss`
- **Responsive:** Mobile-first breakpoints
- **Layout:** CSS Grid and Flexbox preferred

### JavaScript
- **Legacy code:** `app/js/custom/` - jQuery-based, IIFE pattern
- **Modern code:** `app/js/modern/` - ES6 modules, no jQuery dependency
- **New JS should go in `app/js/modern/`** unless modifying existing legacy code
- **Entry points:** `app/js/custom/main.js` (legacy), `app/js/modern/main.js` (modern)

## Build System

### Vite (Primary)
- **Dev server:** `npm run dev` (Vite + SCSS watcher via concurrently)
- **Build:** `npm run build`
- **SCSS only:** `npm run build-scss` or `npm run watch-scss`
- **Dev host detection:** `polys_is_dev()` checks for `obi-wan-v:3000`

### Asset Loading (functions-enqueue.php)
| Environment | CSS | JS |
|---|---|---|
| DEV (`obi-wan-v:3000`) | `style.css` | `vendor.js` + `main.js` |
| PROD (all others) | `style.min.css` | `vendor.min.js` + `main.min.js` |

- Cache busting via `filemtime()` versioning
- External CDN: Bootstrap 5.3.1 (CSS), Font Awesome 6.5.2, Animate.css (local)

## Key Patterns

### Brand Detection System
```php
polys_get_current_brand() // Returns: 'polys', 'metatraversal', 'rpg', or 'academy'
// URL-based: /the-polys/ -> polys, /metatraversal/ -> metatraversal, etc.
// Sets body[data-body-brand] attribute for CSS targeting
```

### Custom Post Types
`profile`, `event`, `sponsor`, `resource`, `hardware`, `project`, `social`
Registered in `functions/functions-post-types.php`

### REST API
Custom endpoints at `/wp-json/wp/v2/{type}` with pagination (100 items)
Defined in `functions/functions-rest-endpoints.php` and `functions-rest-register.php`
Static JSON exports to `app/json/`

### Page Templates
80+ custom `page-*.php` templates in theme root. Standard WordPress template hierarchy.

### Meta Boxes
Extensive custom fields via `functions/functions-metabox.php` (62KB).
Key meta: `use_aframe`, `embed_video_url`, `hero`, `hero_class`, `section_class`, `page-background`

### 3D/XR Support
- A-Frame scenes in `app/aframe/` and `webxr/`
- Cesium.js maps in `cesium/`
- 3D model uploads enabled: `.glb`, `.gltf`, `.usdz`, `.obj`, `.fbx`, `.stl`

## Common Tasks

### Adding a New Page Template
1. Create `page-{slug}.php` in theme root
2. Add template header comment: `/* Template Name: My Template */`
3. Use `get_header()` / `get_footer()`
4. Follow existing patterns from `page.php` for hero/section structure

### Adding a WordPress Hook
```php
// Action: add_action('hook_name', 'polys_callback_name', priority, args);
// Filter: add_filter('hook_name', 'polys_callback_name', priority, args);
// Register in the appropriate functions/{feature}.php file
```

### Adding New SCSS
1. Create partial: `app/scss/partials/_my-feature.scss`
2. Add `@use 'partials/my-feature';` to `app/scss/style.scss`
3. Run `npm run build-scss` or use watch mode

### Adding New JavaScript
- **Modern (preferred):** Add module in `app/js/modern/`, import from `main.js`
- **Legacy:** Add to `app/js/custom/`, follow IIFE pattern

### Debugging
- Dev mode: `polys_is_dev()` returns true on `obi-wan-v:3000`
- PHP: Use `error_log()` and check WordPress debug log
- JS: Browser DevTools, HMR active in dev mode
- REST API: Test endpoints directly at `/wp-json/wp/v2/`

## Files to NEVER Edit Directly
- `node_modules/` - managed by npm
- `build/` - Vite output, regenerated on build
- `cesium/` - third-party library
- `app/js/vendor/` - third-party JS (update via npm or manual replacement)
- `app/js/vault/` - archived legacy code
- `vendor-legacy.js`, `polyfills-legacy.js` - build artifacts
- `*.min.js`, `*.min.css` - compiled output
- `*.map` - source maps (auto-generated)
