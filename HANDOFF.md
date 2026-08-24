# Handoff — Academy Immersive theme, bug queue (engineering track)

_Prepared 2026-08-01. Self-contained; no prior-thread context required._

## Environment
- **One theme**, folder `wp-content/themes/academyimmersive/`, **active** (`wp_options` template/stylesheet = `academyimmersive`), on branch **`academy2026`**. Repo is `github.com/powersimple/polysmuseum` (name historical; no `academyimmersive` repo yet — naming/repo cleanup deferred).
- Local WP served at **`https://obi-wan-v:3000`** (Vite, HTTPS self-signed, proxies to `https://polys`). `wp-cli` at repo root `/Users/benerwin/Clients/WebXR/thepolys/dev`.
- SCSS build: `npm run build-scss` (`app/scss/style.scss` → `style.css` + `style.min.css`).

## Operating rules (hard)
- **No git commands, and no modifying/moving/renaming/resetting/deleting theme files or folders without Ben's explicit per-action go-ahead.** Read-only inspection (wp-cli reads, grep, curl) is fine.
- **Always give Ben Finder-openable `file://` links when referencing saved files.**
- Edit code **locally only**; Ben uploads to production manually (no git deploy). Never touch the production DB. One bug → one diff → explicit upload list.
- Memory files: `no-git-or-folder-changes-without-prompt`, `theme-folder-vs-label-mismatch`, `finder-links-for-files`.

## Done this session — applied to `academyimmersive`, verified live, awaiting upload
| Bug | Fix | Upload |
|---|---|---|
| **2. Sidebar image** | `thumbnail`→`medium` (now serves 300px + srcset) | `functions/functions-events-sidebar.php` |
| **3. Sidebar padding** | top padding above "Upcoming Events" + even desktop gutters | `app/scss/partials/_events-sidebar.scss`, `style.css`, `style.min.css`, `style.min.css.map` |
| **4. Event branding** | brand-by-ancestry resolver in `polys_get_current_brand()` (metatraversal/rpg/polys event children) | `functions.php` |

**Combined upload list:**
```
functions.php
functions/functions-events-sidebar.php
app/scss/partials/_events-sidebar.scss
style.css
style.min.css
style.min.css.map
```

## Done this session — DB-level (no file upload)
- **Bug 1. Event routing** — top-level events → "No content found", nested → default posts loop. Cause: stored `rewrite_rules` had zero `event` rules. Fix = flush permalinks. Verified working locally. **Prod action (Ben's hands, may already be done during cleanup): Settings→Permalinks Save + LiteSpeed purge.**

## Next up — diagnosed, NOT applied (awaiting go-ahead)
- **Bug 5. Curated Sidebar Menu doesn't render on pages.** Cause: only `front-page.php` includes `templates/sidebar-events.php`; `page.php` never does. Meta key `sidbebar_menu` is consistent (save `functions-metabox.php:1006` ↔ read `sidebar-events.php:35`); metabox available on pages — selection saves, template just doesn't render it. **Proposed fix:** in `page.php`, gated on `sidbebar_menu` being set, add the `has-events-sidebar` layout + `get_template_part('templates/sidebar-events')` (pages without a sidebar menu unchanged). Can't verify locally (no local content has the meta; portal-crawls update is server-side). Open question: also apply to `single.php` for posts?

## Loose ends
- **FIXLOG.md** — the kickoff's engineering record was lost with a deleted worktree; not recreated (pending Ben's call).
- **Pre-existing bug (separate):** footer brand-logo SVGs missing — `get_footer_brand_logo()` points to `app/scss/partials/images/logo/*.svg` which don't exist anywhere.
- **Deferred:** consolidate repo/naming to `academyimmersive`; hardcoded `/wp-content/themes/polysmuseum/` paths (Trophy.png in `megamenu.scss`/`_brandbar.scss`, a-frame font in `page-academy-immersive-webxr.php`) worth making folder-agnostic if the folder ever renames.
