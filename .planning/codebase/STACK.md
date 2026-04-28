---
title: Technology Stack
focus: tech
last_mapped: 2026-04-28
---

# Technology Stack

## Runtime & Platform

| Layer | Technology | Version |
|-------|-----------|---------|
| CMS | WordPress | (server-managed) |
| Server Language | PHP | (WordPress-compat) |
| Build Tool | Vite | ^6.3.5 |
| Package Manager | npm | (package-lock.json present) |
| Dev Server | Vite + custom WSS | port 3000 (HTTPS) |

## Frontend Languages

- **PHP** — WordPress template rendering, all page templates and function files
- **SCSS** — compiled via `sass` CLI; BEM methodology; source in `app/scss/`
- **JavaScript** — dual pipeline: legacy jQuery (concatenated) + modern ES modules (Vite)

## JavaScript Architecture (Dual Pipeline)

### Legacy Pipeline
- Source: `app/js/custom/*.js` → concatenated to `main.js` → minified to `main.min.js`
- Source: `app/js/vendor/*.js` → concatenated to `vendor.js` → minified to `vendor.min.js`
- Minification via `terser`
- jQuery 3.7.1 as primary dependency
- Key custom modules: `megamenu-controller.js`, `megamenu.js`, `app.js`, `taxonomies.js`, `directory.js`, `drawer.js`

### Modern Pipeline
- Source: `app/js/modern/main.js` → Vite bundle → `build/assets/`
- Source: `cesium/cesium.js` → Vite bundle → `build/cesium/`
- ES modules, no jQuery dependency
- `megamenu-controller.js` is the new accessible megamenu (vanilla JS, WCAG 2.1 AA)

## CSS Build

- Source SCSS: `app/scss/style.scss` (imports all partials from `app/scss/partials/`)
- Compiled outputs: `style.css`, `style.min.css` (+ source maps)
- Print styles: `print.scss` → `print.css`, `print.min.css`
- Build command: `npm run build-scss` (sass CLI, no PostCSS)

## Key npm Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `vite` | ^6.3.5 | Build tool & dev server |
| `sass` | ^1.83.1 | SCSS compiler |
| `terser` | ^5.37.0 | JS minification |
| `cesium` | ^1.130.0 | 3D globe / geospatial |
| `vite-plugin-cesium` | ^1.2.23 | Vite+CesiumJS integration |
| `jquery` | ^3.7.1 | Legacy jQuery (npm, also local in vendor) |
| `isotope-layout` | ^3.0.6 | Grid filtering/sorting |
| `masonry-layout` | ^4.2.2 | Masonry grid layouts |
| `concurrently` | ^9.0.1 | Run vite + scss watcher together |
| `chokidar` | ^3.5.3 | File watching for SCSS live reload |
| `ws` | ^8.18.0 | WebSocket server for browser live reload |

## WebXR & 3D Libraries

- **A-Frame** — WebXR scenes; loaded conditionally per-page via `use_aframe` post meta
- **CesiumJS** — 3D globe; separate Vite sub-project in `cesium/` with own `package.json`
- **A-Frame extras** bundled in `assets/js/`: physics, teleport controls, look controls, troika text, svg file

## Dev Environment

- Dev host: `obi-wan-v:3000` (single source of truth for env detection in `polys_is_dev()`)
- Staging host: contains `staging` in HTTP_HOST
- SSL: self-signed certs (`localhost.crt`, `localhost.key`) committed to repo
- Live reload: custom WSS on port 3001 (HTTPS), debounced 500ms
- Vite proxies all requests to `https://polys` (local WordPress install)

## Asset Loading Strategy

- DEV: unminified `vendor.js`, `main.js`, `style.css` with `filemtime()` cache-busting
- PROD: minified `vendor.min.js`, `main.min.js`, `style.min.css` with `filemtime()` cache-busting
- Controlled entirely in `functions/functions-enqueue.php` — no hardcoded `<script>` or `<link>` in templates
