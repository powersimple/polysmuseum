---
title: External Integrations
focus: tech
last_mapped: 2026-04-28
---

# External Integrations

## WordPress Core Extensions

### Custom REST API Routes (namespace: `showrunner/v1`)
Registered in `functions/functions-sheets.php` and `functions/functions-rest-*.php`:

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/export` | GET | Export event/profile data formatted for Google Sheets |
| `/menus` | GET | List all nav menus |
| `/menus/{id}` | GET | Get specific menu by ID |
| `/menu-locations` | GET | List all menu locations |
| `/menu-locations/{location}` | GET | Get menu at a specific location |
| `/menu` | GET | Single menu lookup |

### Custom Post Types
Registered in `functions/functions-post-types.php`:
- `profile` — speaker/maker profiles
- `event` — conference sessions/events
- `resource` — downloadable/reference resources
- `sponsor` — event sponsors
- `social` — social posts (disabled)
- `project` — projects (partially disabled)

### Custom Taxonomies
- Custom language taxonomy (`register_taxonomy_languages`) for WPML-style language filtering
- Standard WordPress taxonomies extended with REST API support

## Authentication Systems

### Magic Link Auth (`functions/functions-magic-link-media.php`, `functions/functions-profile-edit.php`)
- Cookie-based magic link sessions for external profile editing
- User meta: `allowed_profile_post_id`, `allowed_until`
- Restricts media library to current user's uploads during session
- Admins/editors bypass restrictions

### Profile Auth Table (`functions/functions-profile-auth.php`)
- Custom DB table: `wp_profile_auth`
- Fields: `profile_id`, `email`, `auth_token` (64-char), `token_expiry`, `last_login`
- Token-based authentication for the external profile admin interface
- Created via `dbDelta()` on init

## Google Sheets Integration
- `functions/functions-sheets.php` — REST endpoint `/export` formats WordPress menu tree + event/profile data as tabular data ready for Google Sheets import
- Not a live API connection — produces exportable JSON that can be pasted/imported

## WebXR / A-Frame
- `webxr/libraries/aframe.php` — conditional A-Frame script loader
- Loaded only when `use_aframe` post meta = 1 (checked in `header.php`)
- Additional A-Frame components in `assets/js/`: physics, teleport, look controls, troika text

## CesiumJS / Geospatial
- Separate sub-project in `cesium/` with own `package.json` and `vite.config.js`
- Loaded via Vite build into `build/cesium/`
- Dev page: `page-cesium-dev.php`
- Functions: `functions/functions-cesium.php`

## Calendar / ICS
- `functions/ics.class.php` — ICS calendar file generator
- Used for generating calendar invites for events (`getCalendarInvite()` in `functions-events.php`)

## Mailing List
- `functions/functions-mailing-list.php` — extracts profile contact info (email, twitter, linkedin, etc.) from WordPress post meta
- No third-party ESP integration found; data is exported/managed internally

## WordPress Plugins Expected
- **MetaBox** (or similar) — `metaboxes.php`, `functions-metabox.php`, `metaboxes-aframe.php` define extensive custom fields using MetaBox API (`rwmb_meta`, `image_advanced` field type)
- **WPML** — `functions/functions-wpml-languages.php` present for multilingual support

## Invitations System
- `functions/invitations.php` — internal invitation management
- Referenced in several admin pages (`page-admin-invitation-brand.php`, etc.)

## Data Files
- `app/json/content.json`, `topo-world-110m.json` — static JSON for map/content rendering
- `data/` directory: `content.json`, `index.json`, `menus.json`, `profiles/` — cached/static data exports
