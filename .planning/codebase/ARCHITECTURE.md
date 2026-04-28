---
title: Architecture
focus: arch
last_mapped: 2026-04-28
---

# Architecture

## Pattern

**WordPress Theme + Custom Application Layer**

This is not a standard WordPress theme — it's a full application built on top of WordPress's theme system. The theme manages conference/awards program content, WebXR experiences, admin tooling, and a custom auth system alongside standard WordPress page rendering.

## High-Level Layers

```
┌─────────────────────────────────────────────────────┐
│  Browser                                            │
│  ├── Legacy JS (main.js + vendor.js via jQuery)    │
│  ├── Modern JS (Vite bundle, ES modules)            │
│  └── CSS (style.min.css)                            │
├─────────────────────────────────────────────────────┤
│  WordPress Theme (PHP)                              │
│  ├── Page Templates (page-*.php)                   │
│  ├── Template Parts (header.php, footer.php)       │
│  ├── Reusable Templates (templates/)               │
│  └── Function Modules (functions/)                 │
├─────────────────────────────────────────────────────┤
│  WordPress Core + MySQL                             │
│  ├── Standard WP APIs                              │
│  ├── Custom REST Routes (showrunner/v1)             │
│  ├── Custom Post Types                              │
│  └── Custom Auth Table (wp_profile_auth)           │
├─────────────────────────────────────────────────────┤
│  Build Layer (Dev only)                            │
│  ├── Vite dev server (port 3000, HTTPS proxy)      │
│  └── SCSS watcher (sass CLI via chokidar)          │
└─────────────────────────────────────────────────────┘
```

## Page Frame System

All pages follow a canonical DOM structure documented in `docs/PAGE-FRAME-ARCHITECTURE.md`:

```
<body>
  <div class="flex-wrapper">
    <header class="megamenu">...</header>     ← global
    <section class="pf-hero pf-hero--[variant]">  ← optional
    <main id="main-content" class="pf-main">
      <div class="pf-media">...</div>          ← optional
      <div class="pf-content-wrap container-flex d-flex">
        <div class="pf-content col-md-7">...</div>
        <div class="pf-sidebar col-md-5">...</div>
      </div>
    </main>
    <footer>...</footer>
  </div>
</body>
```

Hero variants: `--static`, `--parallax`, `--slideshow`, `--video`
Hero heights: `--25`, `--30`, `--40`, `--50`, `--full`

## Function Module System

`functions.php` loads all function files at WordPress `init` hook:

```
functions/
├── functions-enqueue.php       ← asset loading (SINGLE SOURCE OF TRUTH)
├── functions-post-types.php    ← CPT registration
├── functions-megamenu.php      ← megamenu data + rendering
├── functions-profiles.php      ← profile CPT helpers
├── functions-events.php        ← event helpers, calendar
├── functions-awards.php        ← awards/nominations logic
├── functions-ballot.php        ← voting/ballot system
├── functions-run-of-show.php   ← show scheduling
├── functions-rest-endpoints.php← custom WP REST routes
├── functions-sheets.php        ← Google Sheets export
├── functions-profile-auth.php  ← custom auth table
├── functions-magic-link-media.php ← session media restriction
├── functions-profile-edit.php  ← magic link handler
├── functions-cesium.php        ← CesiumJS integration
├── functions-aframe.php        ← A-Frame WebXR
├── functions-audit.php         ← content audit tools
├── functions-metabox.php       ← MetaBox custom field defs
├── metaboxes-aframe.php        ← A-Frame-specific metaboxes
├── functions-navigation.php    ← WP nav menu helpers
├── functions-custom-menu-admin.php ← admin menu customizations
├── functions-entities.php      ← entity helpers
├── functions-publish.php       ← publish workflow
├── functions-post-access.php   ← access control
├── functions-print.php         ← print stylesheet logic
├── parsers.php                 ← data parsers
├── import.php                  ← content import
├── media.php                   ← media helpers
├── invitations.php             ← invitation system
├── profiler/profiler.php       ← performance profiler
└── scraper/simple_html_dom.php ← HTML scraper lib
```

## Megamenu Architecture (Recent Rebuild)

Two-layer megamenu system in transition:

| Layer | Files | Status |
|-------|-------|--------|
| **Legacy** | `app/js/custom/megamenu.js`, `#main-menu` div | Commented out in header |
| **Modern** | `app/js/modern/megamenu.js` + `app/js/custom/megamenu-controller.js` | Active |

Data flow:
1. `get_megamenu_data('megamenu')` — single optimized SQL query fetches full menu tree
2. `render_megamenu('megamenu')` — outputs BEM HTML structure (`.megamenu__bar`, `.megamenu__panel`, etc.)
3. `MegaMenu` class (vanilla JS) — handles all interaction, ARIA, keyboard nav, mobile drawer

## JavaScript Architecture

### Legacy (jQuery-based)
Entry: `app/js/custom/` files concatenated into `main.js`
- `00-jquery-compat.js` — jQuery compatibility shim
- `megamenu.js` — old menu builder (inactive)
- `app.js` — app init
- `taxonomies.js` — taxonomy filtering
- `directory.js` — directory page logic
- `drawer.js` — drawer component
- `media.js` — media handling

### Modern (ES modules)
Entry: `app/js/modern/main.js` → Vite → `build/assets/`
- `megamenu-controller.js` — WCAG 2.1 AA accessible megamenu (no jQuery)
- `megamenu.js` — megamenu rendering helpers

## WebXR Architecture

```
webxr/
├── polys/          ← Polys awards experiences (polys 1-6)
├── polys2-6/       ← Versioned show assets
├── summits/        ← Summit series XR
├── academy/        ← Academy of Immersive Arts content
├── makers/         ← Maker profiles XR
├── model/          ← 3D model viewer
├── archive/        ← Archived experiences
└── libraries/
    ├── aframe.php         ← Conditional A-Frame loader
    └── simple-navmesh-constraint.js
```

## Data Flow for Content

1. WordPress admin creates content (events, profiles, sponsors) via custom post types + MetaBox fields
2. REST endpoints (`showrunner/v1`) expose data as JSON
3. Some data cached as static JSON in `data/` and `app/json/`
4. Page templates query data via `$wpdb` or WP_Query and render HTML

## Environment Detection

Single function `polys_is_dev()` in `functions-enqueue.php`:
- DEV: `$_SERVER['HTTP_HOST'] === 'obi-wan-v:3000'`
- STAGING: HTTP_HOST contains `staging`
- PROD: everything else
