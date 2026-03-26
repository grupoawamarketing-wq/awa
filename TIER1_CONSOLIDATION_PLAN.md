# ✅ TIER 1.1 — CSS Deduplication & Consolidation Plan

**Status:** READY TO IMPLEMENT
**Effort:** 2-3 hours
**Impact:** -50-75 KB saved

---

## Overview

CSS files contain duplicate rules scattered across 38+ files:
- **.awa-header** appears in 14 files
- **.awa-category-carousel** appears in 21 files
- Other reusable patterns detected in 3+ files

---

## Strategy

### Phase 1: Create Consolidated Bundle ✅
**New file:** `awa-consolidated-shared.css` (minified version included)

**Contains:**
1. All `.awa-header` rules
2. All `.awa-category-carousel` rules
3. Common utility classes (appears 3+ times)

**Expected size:** ~8-12 KB (minified)

### Phase 2: Remove Duplicates from Individual Files
For each bundle file:
1. Remove `.awa-header` rules (keep original, but reference from consolidated)
2. Remove `.awa-category-carousel` rules
3. Preserve all unique rules
4. Ensure CSS validity with lint check

**Affected files:**
```
awa-bundle-core.css ................. -15 KB
awa-bundle-custom.css ............... -8 KB
awa-bundle-cosmetic.css ............. -5 KB
awa-bundle-site.css ................. -12 KB
awa-bundle-home-custom.css .......... -10 KB
awa-bundle-pdp.css .................. -8 KB
[... 8 more files]
────────────────────────────────────────
TOTAL SAVINGS: ~75-105 KB
```

### Phase 3: Update References
Update layout XML to include consolidated bundle BEFORE individual bundles:

```xml
<!-- app/design/frontend/AWA_Custom/ayo_home5_child/layout/default.xml -->
<link rel="stylesheet" href="/css/awa-consolidated-shared.css"/>
<link rel="stylesheet" href="/css/awa-bundle-core.css"/>
<link rel="stylesheet" href="/css/awa-bundle-custom.css"/>
```

### Phase 4: Minification & Compression
1. Minify consolidated bundle (-15-20%)
2. Create `.br` Brotli variant (-85-90%)
3. Create `.gz` Gzip backup (-75-80%)

### Phase 5: Testing & Validation
1. CSS lint check (no errors)
2. Browser rendering test (all 4 breakpoints)
3. Performance check (no FOUC — Flash of Unstyled Content)
4. Git diff review (ensure rules preserved)

---

## Implementation Roadmap

```
HOUR 1-2: Create consolidated file
  ├─ Extract all .awa-header rules
  ├─ Extract all .awa-category-carousel rules  
  └─ Extract other reusable patterns

HOUR 2-3: Remove duplicates
  ├─ Strip from core bundle
  ├─ Strip from custom bundles
  └─ Validate CSS syntax

HOUR 3: Minify & compress
  ├─ Run UglifyCSS / cssnano
  ├─ Apply Brotli compression
  └─ Generate .gz fallback

HOUR 4: Test & Deploy
  ├─ Browser test (desktop/mobile)
  ├─ Check Core Web Vitals
  ├─ Git commit
  └─ Monitor logs
```

---

## Expected Outcomes

**Before:**
```
CSS files: 120 (many with duplicates)
awa-bundle-core.css: 338 KB
awa-bundle-custom.css: 104 KB
────────────────────────────────
Total: 11 MB
```

**After:**
```
CSS files: 121 (+1 consolidated)
awa-consolidated-shared.css: 10 KB (minified)
awa-bundle-core.css: 323 KB (-15 KB)
awa-bundle-custom.css: 96 KB (-8 KB)
────────────────────────────────
Total: 10.85-10.9 MB

SAVINGS: 75-100 KB (-0.7% to -0.9%)
```

---

## Safety Measures

1. ✅ Git branch before starting: `git checkout -b tier1-css-dedup`
2. ✅ Backup original files: `tar czf css_backup.tar.gz app/design/frontend/AWA_Custom/ayo_home5_child/web/css/`
3. ✅ Run CSS linter after each file: `php scripts/lint-css.php <file>`
4. ✅ Test layout: `php bin/magento setup:static-content:deploy pt_BR -f`
5. ✅ Browser test before pushing: 4 viewports (375px, 768px, 1024px, 1920px)
6. ✅ Monitor: `tail -f var/log/system.log` for any errors

---

## Next Action

When ready, run:
```bash
php scripts/tier1-css-deduplication.php
```

This will:
1. Create consolidated file
2. Remove duplicates
3. Minify & compress
4. Validate integrity
5. Generate report

Then:
```bash
git add -A
git commit -m "feat: tier1 - css deduplication (-75KB)"
git push origin tier1-css-dedup
```

**Status:** ✅ Ready to begin
**Approval needed:** Yes (before executing)
