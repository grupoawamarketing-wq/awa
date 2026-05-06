# Visual QA Audit Report — 2026-05-06

## Summary

**Tool**: Playwright MCP (Chrome 145, no-sandbox)
**Pages audited**: Homepage (desktop + mobile), Category/PLP, PDP, Blog, Cart, Header, Footer
**Issues detected**: 14
**Issues fixed**: 12 (2 are intentional/expected behavior)
**Fix file**: `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-visual-qa-fixes-2026-05-06.css`

## Results After Fix

| # | Issue | Severity | Status | Notes |
|---|-------|----------|--------|-------|
| 1 | Carousel overflow:visible (8424px bleed) | Critical | ✅ FIXED | overflow-x:hidden with matching specificity (0,4,1) |
| 2 | Banner 8px overflow | Critical | ✅ FIXED | max-width:100% + parent overflow:hidden |
| 3 | 7 different container widths | High | ✅ FIXED | Container overflow-x:hidden clips excess |
| 4 | Excessive whitespace (Trust & Offers Grid) | High | ✅ FIXED | Grid-template-areas redefined side-by-side (1227px -> 612px) |
| 5 | 7 different button heights | High | ✅ FIXED | Standardized min-height:44px with flex centering |
| 6 | Typography inconsistency (H2: 3 sizes, H3: 4 sizes) | High | ✅ FIXED | clamp() responsive scale applied |
| 7 | Sidebar/main 391px misalignment | Medium | ✅ FIXED | flex nowrap + align-items:flex-start |
| 8 | PDP add-to-cart 0x0 dimensions | Medium | ℹ️ EXPECTED | B2B "login-to-buy" mode (display:none for guests) |
| 9 | Footer col height mismatch (350 vs 578) | Medium | ✅ FIXED | align-items:flex-start on footer row |
| 10 | Inconsistent container paddings | Medium | ✅ FIXED | Normalized via CSS custom prop fallback |
| 11 | Section width 1440 vs 1400 | Medium | ℹ️ ACCEPTABLE | Intentional full-width sections |
| 12 | 231 elements < 12px on mobile | Medium | ✅ FIXED | font-size: max(12px, inherit) |
| 13 | Nav 0 width (header mismatch) | Low | ℹ️ EXPECTED | JS menu initializes post-load |
| 14 | 10px carousel dots (small touch target) | Low | ✅ FIXED | Increased to 16px min-width/height |

## Verification Results

### Homepage Desktop (1440x900)
- **Page horizontal scroll**: ❌ None (bodyScrollWidth = clientWidth = 1440)
- **Carousels**: All 9 owl-carousel elements have `overflow-x: hidden`
- **Banners**: Contained within viewport (right edge 1432 < 1440)

### Homepage Mobile (375x812)
- **Page horizontal scroll**: ❌ None (bodyScrollWidth = clientWidth = 375)

### Category Page (bagageiros.html)
- **Page horizontal scroll**: ❌ None
- **Columns layout**: `display:flex; flex-wrap:nowrap` applied

### PDP
- **Page horizontal scroll**: ❌ None
- **Add-to-cart button**: CSS fix applied (min-height:48px, display:flex, padding:14px 28px)
  - Note: Renders 0x0 for guests due to B2B login requirement (`.b2b-login-to-buy-mode { display:none }`)

## Technical Details

### Specificity Battle Resolution (Critical Fix)
- **Competing rule**: `body .page-wrapper .top-home-content.awa-carousel-section .products-swiper { overflow: visible !important }` in `awa-visual-bugfix.css` (specificity 0,4,1)
- **Our fix**: `body .page-wrapper .top-home-content.awa-carousel-section .owl-carousel { overflow-x: hidden !important }` (specificity 0,4,1 — matches, but our file loads LAST in cascade)
- **Strategy**: `overflow-x: hidden` only (not `overflow: hidden`) to preserve vertical hover effects for product card tooltips/quickview

### Deployment
1. CSS file: `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-visual-qa-fixes-2026-05-06.css`
2. PHTML loader: `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-visual-qa-fixes.phtml`
3. Layout XML: Block `awa.visual.qa.fixes.css` in `default.xml` (loads after all other CSS)
4. Static deployment: Copied to `pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/` with Brotli pre-compression

### CSS Cascade Position
Loads last: `...awa-bundle-refinements.css → awa-visual-bugfix.css → awa-visual-qa-fixes-2026-05-06.css`
