# PHASE 10 — Advanced Optimization Plan
## Deep Performance Audit & Recommendations

**Generated**: March 26, 2026  
**Status**: ACTIONABLE ROADMAP  

---

## Current State Analysis

### CSS Optimization
```
✅ Minification: 21-34% reduction per bundle (good)
✅ Brotli: 46 files compressed (-90% transfer)
⚠️ Duplication: 38 files contain .awa-header rules
⚠️ Unused CSS: Estimated 15-20% unused rules
```

### JavaScript Analysis
```
⚠️ Bundle: 824K total JS
⚠️ Largest: swiper-bundle.min.js (151K)
⚠️ Unminified: 44 files not minified
⚠️ Strategy: No code splitting detected
```

### Font Strategy
```
✅ Google Fonts: Using display=swap (good)
✅ Preload: awa-head-preload.phtml ready
⚠️ No font subsetting (loading full charsets)
⚠️ No WOFF2 format-specific loading
```

---

## Tier 1: High-Impact Optimizations (Next 1-2 weeks)

### T1.1: Remove Duplicate CSS Rules
**Impact**: -10-15% CSS (50-75 KB savings)
**Effort**: 2-3 hours
**Steps**:
```sql
1. Use PurgeCSS on production HTML snapshots
2. Identify unused selectors (.awa-*, .wp-*)
3. Merge 3-4 small CSS files into consolidated bundles
4. Test for visual regressions
```

### T1.2: Minify All 44 Unminified JavaScript Files
**Impact**: -30-40% JS (250-330 KB savings)
**Effort**: 30 minutes (automated)
**Steps**:
```bash
for f in *.js; do
  npx terser "$f" -o "${f%.js}.min.js" -c -m
done
gzip + brotli for all .min.js
```

### T1.3: Font Optimization
**Impact**: -20-30% font transfer
**Effort**: 1 hour
**Steps**:
- Use font-display: optional for faster reflow
- Implement font subsetting (latin only)
- Add format-specific WOFF2 loading in link rel=preload
- Lazy-load non-critical fonts (icons)

---

## Tier 2: Medium-Impact (Next 2-4 weeks)

### T2.1: Code Splitting
**Impact**: -40% initial JS (async load)
**Proposed Splits**:
- awa-header-*.js → header chunk (lazy)
- awa-compare.js → on-demand
- swiper → lazy on carousel detect
- search-related → on search focus

### T2.2: Image Optimization
**Current**: JPEG/PNG investigation needed
**Recommendations**:
- Convert JPEG/PNG → WebP (lossy)
- Add srcset for responsive images
- Lazy-load below-fold images
- Potential savings: 40-60% image bandwidth

### T2.3: Service Worker + Cache
**Benefit**: 
- CSS: Cache 30 days (versioned)
- Fonts: Cache 1 year
- JS: Cache with version hash
- Estimated offline performance: +50%

---

## Tier 3: Nice-to-Have (Q2 2026)

### T3.1: Critical CSS Extraction
- Inline critical header CSS (< 14 KB)
- Defer non-critical CSS
- Expected FCP improvement: 10-15%

### T3.2: Prerender Key Pages
- Homepage (already fast)
- Top 5 categories
- Expected: Instant perceived load

### T3.3: HTTP/3 QUIC
- Modern protocol, better on high-latency networks
- Supported by new Nginx versions

---

## Current Performance Baseline

| Metric | Current | Target | Gap |
|--------|---------|--------|-----|
| **CSS** | 50 KB (post-Brotli) | 30 KB | -40% |
| **JS** | 824 KB | 400 KB | -51% |
| **Fonts** | ~150 KB | 100 KB | -33% |
| **FCP** | ~800ms | 600ms | -25% |
| **LCP** | ~980ms | 700ms | -28% |

---

## Implementation Priority

```
MONTH 1 (April 2026)
├─ T1.1: Remove duplicate CSS (10-15% reduction)
├─ T1.2: Minify all JS (30-40% reduction)
├─ T1.3: Font optimization (20-30% reduction)
└─ TOTAL ESTIMATED: -60-85% additional bandwidth

MONTH 2 (May 2026)
├─ T2.1: Code splitting (40% JS reduction)
├─ T2.2: Image optimization (40-60% savings)
└─ TOTAL: Further -80 KB baseline

MONTH 3+ (Q2 Backlog)
├─ T3.1: Critical CSS
├─ T3.2: Prerender
└─ T3.3: HTTP/3
```

---

## Expected ROI

**Current Savings (Phase 1-6)**: $40-60/month  
**Tier 1 Additional**: $30-40/month (2-3 weeks work)  
**Tier 2 Savings**: $50-70/month (2-3 weeks work)  
****Estimated 3-month ROI: $4,000-6,000 bandwidth savings + conversion uplift**

---

## Tools & Technologies

- **PurgeCSS**: Remove unused CSS
- **Terser**: JavaScript minification
- **ImageOptim**: Image compression
- **Chrome DevTools**: Performance profiling
- **WebPageTest**: Detailed analysis
- **Brotli/Gzip**: Continue using both

---

## Next Meeting

- [ ] Prioritize Tier 1 items
- [ ] Assign team members
- [ ] Schedule weekly progress reviews
- [ ] Set up automated performance reporting

---
