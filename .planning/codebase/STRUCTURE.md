---
title: Directory Structure
focus: arch
last_mapped: 2026-04-28
---

# Directory Structure

## Root Layout

```
polysmuseum/                    ← WordPress theme root
├── functions.php               ← Main theme init; loads all function modules
├── header.php                  ← Global header + megamenu render
├── footer.php                  ← Global footer
├── index.php                   ← Default template fallback
├── front-page.php              ← Homepage template
├── page.php                    ← Generic page template
├── single.php                  ← Single post template
├── single-event.php            ← Event CPT single template
├── single-event-new.php        ← Newer event single (in progress)
├── single-profile.php          ← Profile CPT single template
├── style.css                   ← Compiled CSS (DO NOT EDIT directly)
├── style.min.css               ← Minified CSS (DO NOT EDIT directly)
├── main.js                     ← Compiled legacy JS (DO NOT EDIT directly)
├── main.min.js                 ← Minified legacy JS (DO NOT EDIT directly)
├── vendor.js                   ← Compiled vendor JS (DO NOT EDIT directly)
├── vendor.min.js               ← Minified vendor JS (DO NOT EDIT directly)
├── vite.config.mjs             ← Vite build + dev server config
├── package.json                ← npm dependencies and scripts
├── watch-scss.js               ← SCSS file watcher (chokidar)
├── print.css / print.min.css   ← Print styles
└── style.scss                  ← Root SCSS (imports from app/scss/)
```

## Key Source Directories

```
app/
├── js/
│   ├── custom/                 ← LEGACY jQuery source (→ main.js)
│   │   ├── 00-jquery-compat.js
│   │   ├── megamenu-controller.js  ← NEW accessible megamenu
│   │   ├── megamenu.js         ← OLD megamenu (inactive)
│   │   ├── app.js, drawer.js, directory.js, etc.
│   │   └── hmr-client.js       ← Vite HMR client (excluded from bundle)
│   ├── modern/                 ← Modern ES module source (→ build/)
│   │   ├── main.js             ← Modern entry point
│   │   └── megamenu.js         ← Modern megamenu helpers
│   └── vendor/                 ← Third-party JS (→ vendor.js)
│       ├── jquery-ui.min.js
│       ├── owl.carousel.min.js
│       ├── particles.js
│       └── ... (slick, flexslider, etc.)
├── scss/
│   ├── style.scss              ← Main SCSS entry
│   └── partials/               ← SCSS modules (BEM)
│       ├── header.scss, footer.scss, hero.scss
│       ├── megamenu.scss       ← Megamenu styles (recently rebuilt)
│       ├── main.scss, sections.scss, typography.scss
│       ├── drawer.scss, filters.scss, profile.scss
│       ├── events-sidebar.scss, exhibit.scss, nominees.scss
│       ├── _globals.scss, reset.scss, loader.scss
│       └── admin.scss          ← Admin-specific styles
├── includes/
│   └── reverly.php             ← (purpose unclear)
├── json/
│   ├── content.json            ← Static content data
│   └── topo-world-110m.json    ← World topology for map
└── xr/
    └── app.js                  ← XR app entry
```

```
functions/                      ← PHP function modules (57 files)
├── functions-enqueue.php       ← Asset registration (source of truth)
├── functions-megamenu.php      ← Megamenu data/render
├── functions-post-types.php    ← CPT registration
├── functions-profiles.php      ← Profile helpers
├── functions-events.php        ← Event helpers
├── functions-awards.php        ← Awards logic
├── functions-ballot.php        ← Voting system
├── functions-run-of-show.php   ← Show scheduling
├── functions-rest-*.php        ← REST API extensions
├── functions-metabox.php       ← MetaBox field definitions
├── functions-auth-*.php        ← Auth systems
├── profiler/profiler.php       ← Performance profiler
└── scraper/simple_html_dom.php ← HTML scraping library
```

```
webxr/                          ← WebXR experiences
├── polys/                      ← Polys 1st annual XR
├── polys2/ → polys6/           ← Annual show experiences
├── academy/                    ← Academy content
├── summits/ summitsold/        ← Summit series
├── makers/                     ← Maker profiles XR
├── model/                      ← 3D model viewer
└── libraries/                  ← Shared XR libraries
```

```
page-*.php                      ← WordPress page templates (~60 files)
├── page-the-polys-*.php        ← Annual awards show pages
├── page-webxr-summit-*.php     ← Summit series pages
├── page-admin-*.php            ← Internal admin tools
├── page-profiles.php           ← Profile directory
├── page-events.php             ← Events listing
├── page-exhibits.php           ← Exhibits (128KB - very large)
├── page-audit.php              ← Content audit tool (61KB)
└── page-ballot.php             ← Voting/ballot page
```

```
templates/                      ← Reusable PHP template parts
├── academy/
├── makers/
└── ... (sub-templates by section)
```

```
assets/                         ← Static assets
├── js/                         ← Third-party JS (A-Frame, etc.)
├── css/colors/                 ← Color CSS files
├── scss/                       ← Additional SCSS
├── images/                     ← Static images
└── lib/                        ← Library assets
```

```
admin/                          ← Admin-specific assets
├── arrivalspace-admin.php
├── css/
└── js/
```

```
build/                          ← Vite build output (committed)
├── assets/                     ← Modern JS bundles
└── cesium/                     ← CesiumJS bundle
```

```
cesium/                         ← CesiumJS sub-project
├── cesium.js                   ← Entry point
├── package.json                ← Own npm deps
├── vite.config.js
└── src/
```

```
data/                           ← Static data exports
├── content.json, index.json, menus.json
├── profiles/                   ← Profile data exports
└── page-ingest.php             ← Data ingestion script
```

## Naming Conventions

- **Page templates**: `page-{slug}.php` maps to WordPress page with that slug
- **Function files**: `functions-{domain}.php` groups related functionality
- **SCSS partials**: `_{name}.scss` for imported partials, no underscore for compiled entries
- **Backup files**: Prefixed with `x`, `X`, `__`, or `BACKUP` (many in root — should be cleaned up)
- **CSS classes**: BEM — `.block__element--modifier`

## Files to Ignore / Not Edit Directly

- `main.js`, `main.min.js`, `main.min.js.map` — compiled from `app/js/custom/`
- `vendor.js`, `vendor.min.js` — compiled from `app/js/vendor/`
- `style.css`, `style.min.css` (and maps) — compiled from `app/scss/`
- `build/` — Vite output
- `node_modules/` — npm deps
