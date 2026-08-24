# Academy Immersive WordPress Theme (Active)

This is the **active** theme: folder `wp-content/themes/academyimmersive/`,
selected in `wp_options` (template/stylesheet = `academyimmersive`) and
under active development on branch `academy2026`. The predecessor
polysmuseum theme is **retired** — do not work in it or treat this folder
as it. Work happens exclusively here.

> Note: the GitHub repo is still historically named `polysmuseum`; a repo/
> naming cleanup is deferred. The live folder name is `academyimmersive`.

## Operating rules (hard)
- **No git commands, and no modifying/moving/renaming/deleting theme files
  without Ben's explicit per-action go-ahead.** Read-only inspection
  (wp-cli reads, grep, curl) is fine.
- Edit code **locally only**; Ben uploads to production manually (no git
  deploy). **Never touch the production DB.** One bug → one diff → explicit
  upload list.
- Always give Ben openable `file://` links when referencing saved files.

## Environment
- Local WP served at `https://obi-wan-v:3000` (Vite, HTTPS self-signed,
  proxies to `https://polys`).
- SCSS build: `npm run build-scss` (`app/scss/style.scss` → `style.css` +
  `style.min.css`).

## Canon
The institutional canon for all website-theme work lives in the Academy
Projects and Planning corpus (`Documents/Academy/BusinessPlan/Claude/`).
Start there for the canon-binding instructions, the inherited {global}
rules, the reading order, and the currency discipline.

---

*Updated 2026-08-10: rewritten to describe this folder as the active
Academy Immersive theme. The prior version was a copied legacy-polysmuseum
pointer that mislabeled this folder and referenced a sibling folder that
does not exist here. See `HANDOFF.md` for the working bug queue.*
