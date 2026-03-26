# 🎯 TIER 1 — Quick Wins Implementation Plan

**Status:** READY FOR EXECUTION  
**Total Effort:** 4-5 hours  
**Expected Savings:** -300-435 KB additional  
**Timeline:** This sprint (1-2 weeks)

---

## Overview

TIER 1 consists of three high-impact, low-effort optimizations:

| Item | Effort | Impact | Timeline |
|------|--------|--------|----------|
| **T1.1** CSS Deduplication | 2-3 h | -50-100 KB | Day 1 |
| **T1.2** JavaScript Minification | 30 min | -250-330 KB | Day 1 |
| **T1.3** Font Optimization | 1 h | -20-30% fonts | Day 2 |
| **TOTAL** | 4-5 h | **-300-435 KB** | **2 days** |

---

## T1.1: CSS Deduplication ✅ Analysis Complete

**Status:** Analysis report generated  
**Findings:**
- `.awa-header` duplicated in 4 files
- `.awa-category-carousel` duplicated in 6 files
- Conservative savings: 44.69 KB (2%)
- Optimistic savings: 111.71 KB (5%)
- **Selected target: 75-100 KB** (mid-range)

**Action Items:**
```
PHASE 1 (1.5 hours): Consolidation
├─ Create awa-consolidated-shared.css
├─ Extract duplicated rules
└─ Minify consolidated bundle

PHASE 2 (1 hour): Deduplication
├─ Remove rules from 10 bundle files
├─ CSS lint validation
└─ Size verification

PHASE 3 (30 min): Compression
├─ Create Brotli (.br) variant
├─ Create Gzip (.gz) fallback
└─ Update layout references
```

**Command to Execute:**
```bash
# When approved, run full automation:
# (To be implemented in next phase)
php scripts/tier1-css-consolidate-bundles.php
```

---

## T1.2: JavaScript Minification 🎯 Quick Win

**Status:** Ready for execution  
**Target Files:** 44 unminified JS files (824 KB)  
**Candidates:**
- `awa-header-minicart-ui.js` ................. 20 KB → 14 KB (-30%)
- `awa-header-a11y-performance.js` ........... 18 KB → 12.6 KB (-30%)
- `awa-quick-order.js` ....................... 17 KB → 11.9 KB (-30%)
- `tab-carousel-init.js` ..................... 16 KB → 11.2 KB (-30%)

**Action Items:**
```
PHASE 1 (15 min): Setup
├─ npm install terser (or use built-in)
└─ Create minification config

PHASE 2 (15 min): Minify All
├─ Batch minify 44 JS files
├─ Validate syntax (no errors)
└─ Calculate savings

PHASE 3: Compress  
├─ Create Brotli variants
└─ Create Gzip fallback
```

**Estimated Savings:**
```
Before: 824 KB (44 unminified files)
After minification: ~550-600 KB (-30-40%)
After Brotli compression: ~165-180 KB (-80%)
```

**Command to Execute:**
```bash
# Batch minify all JS files
for file in app/design/.../web/js/*.js; do
    if [[ ! $file =~ \.min\.js$ ]]; then
        terser "$file" --compress --mangle --output "${file%.js}.min.js"
    fi
done
```

---

## T1.3: Font Optimization 📝 Setup

**Status:** Ready for analysis  
**Current Setup:**
- ✅ Google Fonts with `display=swap` (good!)
- ❌ Missing: Font subsetting (latin-only)
- ❌ Missing: WOFF2-specific format detection
- ❌ Missing: `font-display: optional`

**Action Items:**
```
PHASE 1 (20 min): Font Analysis
├─ Audit font-family declarations
├─ Check character set loading
└─ Identify non-critical fonts

PHASE 2 (30 min): Subsetting
├─ Configure font-face with subset="latin"
├─ Keep unicode-range declarations
└─ Validate font loading

PHASE 3 (10 min): Optimization
├─ Set font-display: optional for non-critical
├─ Update preload headers
└─ Test FCP impact
```

**Estimated Savings:**
```
Current font payload: ~50 KB (compressed)
After subsetting: ~35 KB (-30%)
Impact on FCP: -50-100ms
```

**Files to Update:**
- `app/design/.../layout/head.phtml`
- `app/design/.../template/html/head/fonts.phtml`

---

## Execution Checklist

### Pre-Flight (Before starting)
- [ ] Git branch: `git checkout -b tier1-quick-wins`
- [ ] Backup CSS: `tar czf css_backup.tar.gz app/design/.../web/css/`
- [ ] Backup JS: `tar czf js_backup.tar.gz app/design/.../web/js/`
- [ ] Clear cache: `php bin/magento cache:clean`

### T1.1 Execution (Day 1, Hours 1-3)
- [ ] Run CSS consolidation script
- [ ] Verify no CSS rules lost (diff check)
- [ ] Lint check: `php -l` on all CSS
- [ ] Size validation
- [ ] Git commit: "feat: t1.1 - css deduplication"

### T1.2 Execution (Day 1, Hours 3-4)
- [ ] Run JS minification script
- [ ] Validate syntax: `node -c` on all JS
- [ ] Test: `php bin/magento setup:static-content:deploy pt_BR -f`
- [ ] Size validation
- [ ] Git commit: "feat: t1.2 - javascript minification"

### T1.3 Execution (Day 2, Hour 1)
- [ ] Update font declarations
- [ ] Test on 4 viewports
- [ ] Monitor FCP via RUM
- [ ] Git commit: "feat: t1.3 - font optimization"

### Post-Flight (After deployment)
- [ ] Run tests: `bin/magento static-content:deploy`
- [ ] Monitor logs: `tail -f var/log/{system,exception}.log`
- [ ] Browser test: Desktop (1920px) + Mobile (375px)
- [ ] Check Core Web Vitals
- [ ] Performance snapshot: `mcp_io_github_chr_performance_start_trace`

---

## Success Criteria

### T1.1 Success
- ✅ CSS file sizes reduced by 75-100 KB
- ✅ No CSS rules lost
- ✅ Layout renders correctly on 4 viewports
- ✅ No FOUC (Flash of Unstyled Content)
- ✅ Performance gains: -15-20% FCP

### T1.2 Success
- ✅ JS files minified, 250-330 KB saved
- ✅ All JS files valid (no syntax errors)
- ✅ No functionality broken
- ✅ Page interactivity maintained
- ✅ Console clean (no JS errors)

### T1.3 Success
- ✅ Font subsetting applied (latin-only)
- ✅ Font-display optimized
- ✅ FCP improved by 50-100ms
- ✅ No font fallback degradation

### Overall TIER 1 Success
- ✅ Total bandwidth reduction: -300-435 KB per page
- ✅ Page load time: -15-25%
- ✅ Zero errors in logs
- ✅ All tests passing
- ✅ Ready for production merge

---

## Timeline

```
DAY 1 (Wednesday)
├─ 09:00 - 10:30: T1.1 CSS Consolidation
├─ 10:30 - 11:30: T1.1 Deduplication & Validation
├─ 11:30 - 12:00: T1.1 Compression & Git Commit
├─ 12:00 - 13:00: LUNCH
├─ 13:00 - 13:30: T1.2 JS Minification Setup
├─ 13:30 - 14:00: T1.2 Batch Minify All Files
└─ 14:00 - 14:30: T1.2 Compression & Git Commit

DAY 2 (Thursday)
├─ 09:00 - 09:20: T1.3 Font Analysis
├─ 09:20 - 09:50: T1.3 Font Subsetting & Optimization
├─ 09:50 - 10:00: T1.3 Git Commit
├─ 10:00 - 10:30: Final Testing (4 viewports)
├─ 10:30 - 11:00: Log Monitoring & Performance Check
└─ 11:00 - 11:30: Documentation & Deployment Sign-off
```

---

## Risk Mitigation

| Risk | Mitigation |
|------|-----------|
| CSS rules lost during consolidation | Git diff review + CSS linter + visual test |
| JS minification breaks functionality | Unit tests + console check + interaction test |
| Font subsetting missing required chars | Unicode-range verification + fallback fonts |
| Browser compatibility issues | Test on Chrome, Firefox, Safari, Edge |
| FOUC (unstyled content flash) | Verify font preload headers + CSS order |

---

## Resources

- CSS Lint: `php -l` or online tool
- JS Minifier: Terser (npm) or built-in tools
- Performance Monitor: RUM tracker (already deployed)
- Git Diff: `git diff --cached` for review

---

## Approval & Sign-Off

**Prepared by:** Infrastructure Team  
**Date:** 2026-03-26  
**Status:** ✅ Ready for Execution  
**Approval needed:** Yes (before proceeding)

---

**Next Action:** Execute T1.1 when approved

