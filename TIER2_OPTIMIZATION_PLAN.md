# TIER 2 Optimization Plan — Advanced Performance

**Project:** AWA Motos — Next Phase of Optimization
**Timeline:** Apr 1-30, 2026 (Estimated)
**Priority:** HIGH (follows TIER 1 completion)
**Status:** 📋 PLANNED (not yet started)

---

## 📊 TIER 2 OVERVIEW

After TIER 1 (-170 KB, +300-500ms FCP), focus shifts to:
- **Code splitting** for JavaScript (reduce initial load)
- **Image optimization** (WebP, AVIF, lazy loading, responsive)
- **Service Worker** (offline support, cache strategies)
- **Advanced CSS** (critical CSS extraction, async loading)

**Estimated Combined Impact:**
```
Code Splitting:        -50-100 KB (initial JS)
Image Optimization:    -200-500 KB per page (format + sizing)
Service Worker Cache:  -2-5s (repeat visits)
Critical CSS:          -100-200ms (FCP)

Total Estimated:       -250-800 KB + -2-5s (repeat visitors)
```

---

## 🎯 T2.1: CODE SPLITTING & LAZY LOADING

### Objective
Reduce JavaScript initial load by splitting large bundles and lazy-loading non-critical code.

### Current State
```
Current JS Architecture:
├─ Single awa-bundle.min.js (~35-50 KB after Brotli) ← Loaded on every page
├─ Included: Header, Footer, Toast, Back-to-top, Search, etc.
├─ Problem: All JS loads even if not needed on page
└─ Opportunity: Split by feature/page type

Examples:
├─ B2B modules: Only needed on /b2b/* pages
├─ Product filters: Only on /catalog/category/* pages
├─ Toast/modal: Load on-demand (click trigger)
└─ Search autocomplete: Load when input focused
```

### Strategy

**Phase 1A: Identify Lazy-Load Candidates**
```js
Components suitable for lazy loading:
├─ awa-quick-order.js      (B2B only, ~3 KB)
├─ awa-toast.js            (On-demand modal, ~2 KB)
├─ awa-search-autocomplete.js (On focus event, ~4 KB)
├─ awa-product-compare.js   (B2C feature, ~5 KB)
└─ awa-wishlist-sync.js     (Logged-in only, ~4 KB)

Total Lazy Load Candidates: ~18 KB
Impact: Remove from critical path → Load only when needed
```

**Phase 1B: Route-Based Code Splitting**
```js
RequireJS Bundles:
├─ global-bundle      (core: header, footer, toast)
├─ catalog-bundle     (product listing: filters, carousel, compare)
├─ product-bundle     (detail: gallery, reviews, recommendations)
├─ b2b-bundle         (b2b: register, quick-order, quotations)
└─ checkout-bundle    (checkout: validation, payment methods)

Loading Strategy:
├─ global: loaded unconditionally
├─ others: loaded on route match
└─ fallback: if routes not available, degrade gracefully
```

**Phase 1C: RequireJS Dynamic Loading**
```js
// Current (loads everything):
require(['awa-back-to-top', 'awa-toast', 'awa-search'], function(...) {...});

// Proposed (lazy load):
require(['awa-back-to-top']),              // Load immediately
require(['awa-toast-lazy'], function() {  // Load on-demand
  document.querySelector('.modal-btn').addEventListener('click', () => {
    require(['awa-toast'], function(Toast) { Toast.show(); });
  });
});
```

### Implementation Details

**Files to Modify:**
```
app/design/frontend/AWA_Custom/ayo_home5_child/web/js/
├─ config.js (RequireJS configuration)
│  └─ Add bundles configuration section
├─ app.js (main entry point)
│  └─ Conditional requires based on page context
└─ [new] lazy-load-bundles.js
   └─ Utility for dynamic loading with fallback
```

**New Files to Create:**
```
scripts/
├─ tier2_analyze_js_usage.php
│  └─ Analyze which JS files used on which pages
├─ tier2_split_bundles.sh
│  └─ Create bundles and verify splits
└─ tier2_test_lazy_loading.js
   └─ Verify bundles load correctly on-demand
```

### Metrics

**Success Criteria:**
```
Initial JS size:       50 KB → 30-35 KB (-30-40%)
FCP improvement:       Additional -100-200ms
Time to Interactive:   Reduced by ~200ms
Lighthouse JS score:   +15-25 points
```

**Testing:**
```
Lighthouse audit script:
├─ Run on catalog page (uses catalog-bundle)
├─ Run on B2B register (uses b2b-bundle)
├─ Run on checkout (uses checkout-bundle)
└─ Compare FCP/LCP/CLS vs baseline
```

---

## 🎯 T2.2: IMAGE OPTIMIZATION

### Objective
Reduce image transfer size via modern formats (WebP, AVIF), responsive sizing, and lazy loading.

### Current State
```
Image Architecture:
├─ Product images:      JPG (~500-800 KB per page, 8-12 images)
├─ Banner images:       JPG (~200-400 KB)
├─ Icon materials:      PNG (~50-100 KB, 20+ icons)
├─ Social proof images: JPG (~100-200 KB)
└─ Total per page:      ~1-1.5 MB (largest optimization opportunity!)

Current Issues:
├─ No WebP fallback (modern browsers can save 30-40%)
├─ No AVIF support (newest browsers can save 50%)
├─ No responsive sizing (same image on mobile as desktop)
├─ No lazy loading on below-fold images
└─ PNG icons not SVG optimized
```

### Strategy

**Phase 2A: WebP Conversion**
```
Tool: ImageMagick + cwebp
├─ Convert all JPG → WebP (with JPG fallback)
├─ Convert PNG → WebP (for icons compatible)
└─ Compression settings: quality 80 (WebP) vs 85 (JPG)

Expected savings:
├─ JPG → WebP: -30-40% smaller
├─ PNG → WebP: -25-35% smaller
│
Example:
├─ product.jpg (500 KB) → product.webp (300 KB) ✓
├─ banner.jpg (250 KB) → banner.webp (150 KB) ✓
└─ icon.png (50 KB) → icon.webp (35 KB) ✓
```

**Phase 2B: AVIF Support (Nice-to-have)**
```
Tool: avifenc
├─ Generate AVIF variants for modern browsers (Chrome, Firefox, Safari 16+)
├─ Still include WebP/JPG fallback for older browsers
└─ Quality: speed 6 (balanced quality/speed)

Expected savings:
├─ JPG → AVIF: -50-70% smaller (best possible)
│
Example:
├─ product.jpg (500 KB) → product.avif (200-250 KB) ✓✓
```

**Phase 2C: Responsive Images**
```html
<!-- Before: Single image, always full width -->
<img src="product-full.jpg" alt="..."/>

<!-- After: Responsive srcset with multiple sizes -->
<picture>
  <source srcset="product-320.avif 320w, product-640.avif 640w" type="image/avif"/>
  <source srcset="product-320.webp 320w, product-640.webp 640w" type="image/webp"/>
  <img src="product-640.jpg" alt="..." loading="lazy"/>
</picture>

Breakpoints:
├─ Mobile (320px):      320px image
├─ Tablet (768px):      640px image
├─ Desktop (1024px):    1024px image
└─ Wide (1920px):       1920px image
```

**Phase 2D: Lazy Loading**
```html
<!-- Native lazy loading (img) -->
<img src="..." loading="lazy"/>

<!-- Intersection Observer for fine control -->
<img data-src="product.jpg" class="lazy-image"/>
<script>
  const images = document.querySelectorAll('.lazy-image');
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.src = entry.target.dataset.src;
        observer.unobserve(entry.target);
      }
    });
  });
  images.forEach(img => observer.observe(img));
</script>
```

### Implementation Details

**Files to Modify:**
```
app/design/frontend/AWA_Custom/ayo_home5_child/
├─ Phtml templates (product, category, homepage)
│  └─ Add picture/source tags with srcset
├─ CSS (background-image usage)
│  └─ Use image-set() for modern browsers
└─ Web/images/ directories
   └─ Add WebP/AVIF variants
```

**Automation Scripts:**
```
scripts/
├─ tier2_convert_images_webp.sh
│  └─ Batch convert JPG/PNG → WebP
├─ tier2_convert_images_avif.sh
│  └─ Batch convert JPG → AVIF
├─ tier2_generate_responsive_variants.sh
│  └─ Generate 320/640/1024/1920px variants
└─ tier2_update_image_templates.php
   └─ Update phtml templates with picture tags
```

### Metrics

**Success Criteria:**
```
Image file sizes:       -30-50% (via WebP)
Per-page image load:    1.2 MB → 600-800 KB (-40-50%)
FCP improvement:        Additional -100-300ms
Paint timing:           Visible speed improvement
Lighthouse score:       +20-30 points
```

**Testing:**
```
Pagespeed testing:
├─ Compare before/after images in Network tab
├─ Verify WebP negotiation (Content-Negotiation header)
├─ Test lazy loading (scroll, verify images load)
├─ Cross-browser test (Chrome/Firefox/Safari/Edge)
└─ Mobile testing (verify responsive sizing)
```

---

## 🎯 T2.3: SERVICE WORKER & OFFLINE SUPPORT

### Objective
Enable offline capability and reduce repeat-visit load times via service worker caching.

### Strategy

**Phase 3A: Service Worker Installation**
```
File: pub/sw.js (already exists - extend it)

Current state: Basic structure, needs enhancement

Caching Strategy:
├─ Cache-first:   Static assets (CSS, JS, fonts, images)
├─ Network-first: HTML pages (always fetch new)
├─ Stale-while-revalidate: API calls (serve cache, update in background)
└─ Network-only:  Checkout pages (never cache sensitive data)
```

**Phase 3B: Cache Configuration**
```js
const CACHE_NAMES = {
  STATIC:      'awa-static-v1',      // CSS, JS, fonts
  IMAGES:      'awa-images-v1',      // Product images (WebP/AVIF)
  PAGES:       'awa-pages-v1',       // HTML pages
  API:         'awa-api-v1',         // GraphQL/REST responses
};

// Static assets: Cache all after first load
const staticAssets = [
  '/static/*/css/awa-*.min.css.br',
  '/static/*/js/awa-*.min.js.br',
  '/static/*/fonts/montserrat*.woff2',
  '/media/catalog/category/*.webp',
];

// Pages: HTML routes (network-first to ensure freshness)
const htmlRoutes = [
  '/pt_br/',
  '/pt_br/catalog/category/*',
  '/pt_br/product/*',
  '/pt_br/b2b/*',
];

// Checkout: Never cache (security)
const neverCache = [
  '/checkout/*',
  '/customer/account/*',
];
```

**Phase 3C: Offline Fallback**
```js
// If network fails, show offline page
self.addEventListener('fetch', event => {
  if (event.request.method === 'GET' && event.request.mode === 'navigate') {
    event.respondWith(
      caches.match(event.request)
        .then(response => response || fetch(event.request))
        .catch(() => caches.match('/offline.html'))
    );
  }
});

// Create offline.html with cached content
// Show: "You're offline. Here's what we cached for you."
// Allow: Browse previously visited products, read cached docs
```

### Implementation Details

**Files to Modify/Create:**
```
pub/
├─ sw.js (enhance from basic to full strategy)
├─ offline.html (new - offline fallback page)
└─ sw-config.js (new - cache configuration)

app/design/frontend/AWA_Custom/ayo_home5_child/
├─ Phtml templates/
│  └─ Register service worker on page load
└─ web/js/
   └─ sw-register.js (new - registration script)
```

**Registration Script:**
```js
// web/js/sw-register.js
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' })
      .then(reg => {
        console.log('Service Worker registered:', reg);
        // Check for updates every hour
        setInterval(() => reg.update(), 3600000);
      })
      .catch(err => console.log('SW registration failed:', err));
  });
}
```

### Metrics

**Success Criteria:**
```
Offline capability:       ✅ Browsable offline
Repeat visit load time:   -2-5s (cache hits)
Network requests:         -40-60% (cached assets)
Conversion impact:        -0.5-1% (faster load = less bounce)
Lighthouse PWA score:     +20-30 points
```

**Testing:**
```
Chrome DevTools testing:
├─ Offline mode: Verify page still loads
├─ Cache storage: Check cached assets (~5-10 MB)
├─ Network throttling: Verify cache cuts latency
├─ Update check: Verify SW updates work
└─ Cache expiry: Clean old caches monthly
```

---

## 🎯 T2.4: CRITICAL CSS EXTRACTION

### Objective
Identify and inline critical CSS above-the-fold to improve FCP further.

### Strategy

**Phase 4A: Critical Path Analysis**
```
Above-fold elements:
├─ Header (logo, nav, search)
├─ Hero banner (large image/slider)
├─ Featured products carousel (first 4 products)
└─ Footer (minimal, less critical)

Typical critical CSS size: 15-30 KB (inlined in <head>)
Remaining CSS: Loaded asynchronously (non-render-blocking)
```

**Phase 4B: Extraction Tool**
```
Option 1: Critical CSS extraction tool
├─ Tool: critical-css npm package
├─ Input: URL, viewport (mobile/desktop)
├─ Output: Minimal critical CSS file
└─ Integration: Inline in <head>, load rest async

Option 2: Manual analysis
├─ Inspect above-fold via DevTools
├─ Identify CSS rules needed
├─ Extract to separate file
└─ Inline in template
```

**Phase 4C: Async CSS Loading**
```html
<!-- Before: Render-blocking -->
<link rel="stylesheet" href="style.css"/>

<!-- After: Critical inline + rest async -->
<style>
  /* Critical CSS inlined (~20 KB) */
  body { font-family: Montserrat; }
  .header { background: #f5f5f5; }
  ... // ~50 rules
</style>

<link rel="preload" as="style" href="style-full.css"
      onload="this.onload=null;this.rel='stylesheet'"/>
<noscript><link rel="stylesheet" href="style-full.css"/></noscript>
```

### Implementation Details

Files:
```
app/design/frontend/AWA_Custom/ayo_home5_child/
├─ templates/page/2columns-left.phtml
│  └─ Add <style> block with critical CSS
├─ web/css/style-critical.css (new)
│  └─ Extract critical rules only
└─ web/css/style-full.css (existing)
   └─ Rename/refactor for async loading
```

### Metrics

**Success Criteria:**
```
Critical CSS inlined:     ~20-25 KB
FCP improvement:          Additional -50-100ms
Render blocking time:     Reduced by ~50%
Lighthouse score:         +10-15 points (Paint metrics)
```

---

## 📋 TIER 2 PRIORITIZATION

### Quick Wins (Week 1-2)
```
1. ✅ T2.2 Phase 2A: WebP conversion (biggest bang for buck)
   - Impact: -200-300 KB per-page (images)
   - Effort: 3-4 hours (automation + testing)
   - Risk: Low (fallback to JPG)
   - Result: Immediate 30% reduction in image load

2. ✅ T2.1 Phase 1A: Identify lazy-load candidates
   - Impact: -30-50 KB JS initial load
   - Effort: 2 hours (analysis + marking)
   - Risk: Low (optional bundle)
   - Result: Blueprint for Phase 1B/1C
```

### Medium Effort (Week 3-4)
```
3. ✅ T2.2 Phase 2C: Responsive images / Lazy loading
   - Impact: Additional -100-150 KB (sizing + below-fold)
   - Effort: 8 hours (template updates + testing)
   - Risk: Medium (requires phtml changes)
   - Result: Significant performance boost on mobile

4. ✅ T2.1 Phase 1B/1C: Implement code splitting
   - Impact: -30-50 KB initial JS load
   - Effort: 8-12 hours (bundle config + testing)
   - Risk: Medium (RequireJS config complexity)
   - Result: Faster first paint, better multi-platform UX
```

### Advanced (Week 5-6)
```
5. ✅ T2.3: Service Worker enhancement
   - Impact: -2-5s repeat visits, offline support
   - Effort: 10-12 hours (caching strategy + offline UX)
   - Risk: Medium (cache invalidation management)
   - Result: PWA-like experience, improved retention

6. ✅ T2.4: Critical CSS extraction
   - Impact: Additional -50-100ms FCP
   - Effort: 6-8 hours (critical path analysis + inlining)
   - Risk: Low (purely CSS, no logic changes)
   - Result: Fine-grained paint performance
```

---

## 📊 COMBINED TIER 2 IMPACT

```
Component          Savings      Time      Effort    Risk
────────────────────────────────────────────────────────
T2.1 Code Split    -50 KB       -100ms    Medium    Medium
T2.2 Images        -300 KB      -300ms    Medium    Low
T2.3 Service WW    0 KB initial -2-5s rep High     Medium
T2.4 Critical CSS  0 KB         -50ms     Low       Low
────────────────────────────────────────────────────────
TOTAL              -350 KB      -450ms    ~40h      Low
```

### Expected Results Post-TIER 1+2

```
FCP (First Contentful Paint):
  Before optimization:  ~2.5s
  After TIER 1:         ~2.0s (-500ms, -20%)
  After TIER 2:         ~1.5s (-1s total, -40%)
  Target:               <1.5s (green Lighthouse) ✅

LCP (Largest Contentful Paint):
  Target improvement:   Similar to FCP (-300-500ms)

CLS (Cumulative Layout Shift):
  Expected:             No change (already optimized)

Overall Lighthouse Score:
  Before:               ~65 (orange)
  After TIER 1:         ~80 (green)
  After TIER 2:         ~90 (green, excellent)
```

---

## 🚀 NEXT STEPS

### Immediate (After TIER 1 Production Deploy)
1. ✅ TIER 1 final testing (browser + Lighthouse)
2. ✅ Production deployment approval
3. ✅ RUM metrics monitoring (1-2 week baseline)

### TIER 2 Kickoff (Early April)
1. Create detailed TIER 2.1 implementation spec
2. Analyze JS dependencies for code splitting
3. Batch convert images to WebP
4. Plan service worker rollout strategy

### Sequential Execution
```
Week 1:  T2.2 WebP conversion + fallback testing
Week 2:  T2.1 Code split planning + bundle analysis
Week 3:  T2.2 Responsive images + lazy loading
Week 4:  T2.1 Code split implementation + testing
Week 5:  T2.3 Service Worker + offline UX
Week 6:  T2.4 Critical CSS extraction
Week 7:  Full integration testing + production deploy
Week 8:  RUM metrics analysis + optimization tweaks
```

---

## 📎 REFERENCE

**TIER 1 Status:** ✅ COMPLETE (TIER1_COMPLETION_REPORT.md)
**Project Baseline:** TIER1_STATUS_CURRENT.md
**Previous Improvements:** IMPROVEMENT_PLAN_2026Q1.md
**Architecture Docs:** docs/theme-ayo.md

---

**Plan Created:** 26 Mar 2026
**Next Review:** 02 Apr 2026 (after TIER 1 production)
**Owner:** AWA Performance Team
