---
title: Testing
focus: quality
last_mapped: 2026-04-28
---

# Testing

## Test Framework

**None detected.** No test framework, test runner, or test files found in the codebase.

- No `*.test.php`, `*.spec.js`, `*Test.php` files
- No PHPUnit, Jest, Vitest, Mocha, or Cypress configuration
- No `tests/` or `__tests__/` directory
- No test-related scripts in `package.json`

## Manual / Ad-Hoc Testing

The codebase relies on manual testing and several in-theme debugging tools:

### Admin Audit Pages
- `page-audit.php` (61KB) — content audit tool; lists and inspects content
- `page-audit-images.php` — image audit tool
- `functions/functions-audit.php` — audit helper functions
- `functions/functions-audit-images.php` — image audit functions

### Debug Output
- `POLYSMUSEUM_SHOW_TEMPLATE_DEBUG` constant — shows template path overlay when enabled
- `POLYSMUSEUM_DEPLOY_CHECK` date constant — verify production file freshness
- WP_DEBUG-gated debug output scattered in function files
- Admin-only debug HTML blocks in some page templates

### Dev Environment Indicators
- Page title prefixed with `🅳🅴🆅` on dev host (`obi-wan-v:3000`)
- Page title prefixed with `🆂🆃🅰🅶🅸🅽🅶` on staging host

### Manual Test Pages
- `page-test.php` — minimal test page (57 bytes)
- `page-query.php` — query debugging
- `page-hashes.php` — hash/data testing

### REST API Testing
- `data/page-ingest.php` — data ingestion/testing script
- REST endpoints accessible via browser or API client for manual verification

## Coverage

| Area | Status |
|------|--------|
| PHP unit tests | None |
| JavaScript unit tests | None |
| Integration tests | None |
| E2E tests | None |
| Visual regression | None |
| REST API tests | Manual only |
| Accessibility testing | Manual; WCAG 2.1 AA noted as target for megamenu |

## Recommendations

High priority areas that would benefit from testing:
1. `functions-megamenu.php` — `get_megamenu_data()` SQL query and tree building
2. REST API endpoints in `functions-rest-*.php` — response shape and auth
3. `functions-profile-auth.php` — token generation and validation
4. `megamenu-controller.js` — keyboard navigation and ARIA state management
