# Header & Vertical Menu — Visual Audit Report
**Date:** 2026-05-08  
**Scope:** awamotos.com header and vertical menu (nav bar row)  
**Viewport tested:** 1280×800 desktop, 375×812 mobile

---

## Summary

All 5 structural bugs identified and fixed via `awa-header-audit-fix.css` (loads last, sheet 31).

---

## Issues Found & Fixed

### Issue 1 — Nav Bar Background (Red → Gray on Desktop)
- **Root cause:** Section 18 in `awa-visual-bugfix.css` (line ~14009), appended without `@media` query, using selector `#html-body .page-wrapper ... .header-control.awa-nav-bar { background: var(--awa-red) !important }` with specificity `(1,5,0)` — overrides ALL `@media (min-width:992px)` gray-background rules from the same file.
- **Fix:** `html #html-body .page-wrapper ... .header-control.header-nav.awa-nav-bar { background: var(--awa-bg-subtle, #f7f7f7) !important }` — adds `html` as ancestor tag selector for specificity `(1,5,1)` > `(1,5,0)`, inside `@media (min-width:992px)`, loads LAST.
- **Result:** `rgb(163,59,59)` → `rgb(247,247,247)` ✓

### Issue 2 — Nav Links Text Color
- **Root cause:** Section 18b in `awa-visual-bugfix.css` set white text (`color: var(--awa-vbf-c29)`) for nav links — designed for the red nav bar background.
- **Fix:** Override to dark text `var(--awa-text, #1a1a1a)` on gray background.
- **Result:** White → dark text ✓

### Issue 3 — 8px Trigger Alignment Gap
- **Root cause:** `nav.navigation.verticalmenu.side-verticalmenu` (direct parent of trigger button) has `padding-top: 8px` from the Rokanthemes theme default CSS. This pushed the trigger 8px below the nav bar top edge.
- **Fix:** `padding-top: 0 !important; padding-bottom: 0 !important` on the nav element in nav bar context.
- **Result:** Trigger `top: 173` → `top: 166` (aligned with categories container) ✓

### Issue 4 — Categories Wrapper Height Mismatch (46px vs 56px)
- **Root cause:** `awa-super-home.css` sets `height: var(--awa-size-46, 46px) !important` for `body.cms-homepage_ayo_home5 .page-wrapper .header-control.header-nav .menu_left_home1` with specificity `(0,5,1)`. This caused `.awa-header-categories` to be 46px while the trigger inside was 56px — trigger overflowed the wrapper.
- **Fix:** `html #html-body .page-wrapper .header-control.header-nav .awa-header-categories.menu_left_home1 { height: 56px; min-height: 56px }` — specificity `(1,5,1)` beats `(0,5,1)`.
- **Result:** 46px → 56px ✓

### Issue 5 — max-height:46px Conflict on Trigger
- **Root cause:** `awa-vertical-menu-desktop-final.css` set `max-height: 46px !important` on the trigger, conflicting with `min-height: 56px !important` from `awa-visual-bugfix.css`. Browser resolves to 56px (min-height wins) but the logical contradiction remained.
- **Fix:** Override with `max-height: 56px !important` in the fix file (loads last, same specificity wins by source order).
- **Result:** `maxHeight: "46px"` → `maxHeight: "56px"` ✓

### Issue 6 — Duplicate Borders on Nav-Sections Dropdown
- **Fix:** Reinforced `border: none !important; box-shadow: none !important` on `.sections.nav-sections.category-dropdown` and `.section-items.nav-sections.category-dropdown-items` in nav bar context.

---

## Files Changed

| File | Action |
|------|--------|
| `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-header-audit-fix.css` | **Created** — new CSS fix file |
| `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-header-audit-fix-css.phtml` | **Created** — blocking link template |
| `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/layout/default.xml` | **Edited** — added `awa.header.audit.fix.css` block after `awa.visual.consistency.css` |

---

## Before vs After Measurements (1280px)

| Metric | Before | After |
|--------|--------|-------|
| Nav bar background | `rgb(163,59,59)` red | `rgb(247,247,247)` gray ✓ |
| Nav bar height | 56px | 58px (+ 2px border) |
| Categories height | 46px | 56px ✓ |
| Trigger top | 173px (8px gap) | 166px (0px gap) ✓ |
| Trigger height | 56px | 56px |
| Trigger maxHeight | 46px (conflict) | 56px ✓ |
| Trigger background | red (brand) | red (brand, correct) |
| Trigger alignment | 8px from nav bar top | flush with nav bar ✓ |

---

## Key Diagnostic Findings

1. **DOM reality:** `#html-body` is the `<body id="html-body">` element, NOT `<html>`. The correct prefix for higher specificity is `html #html-body` (html→body ancestor chain).
2. **OPcache:** `opcache.validate_timestamps=0` — always restart PHP-FPM after PHTML changes.
3. **Brotli:** Nginx serves `.br` files. After CSS changes: regenerate brotli + restart Nginx.
4. **Varnish static purge:** Requires exact versioned URL: `/static/version1778220260/frontend/...` and `X-Pool: www` header.
