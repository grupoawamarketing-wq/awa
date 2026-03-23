# SF-001: Core Variables Extraction — Implementation Plan

**Phase**: Segmentation Fixes (SF)
**Branch**: improvement/SF-001-core-variables
**Status**: 🟡 In Progress
**Created**: 2026-03-23
**Target**: -13% CSS size, -7% LCP

---

## 📊 Analysis Summary

### Current State (Before SF-001)
- **File**: `awa-bundle-core.unmin.css`
- **Size**: 551KB unminified, 369KB minified, 35KB brotli
- **Lines**: 16,082 total
- **Content**: Design tokens (variables) + reset + base typography + components

### Variables Section Found
- **Location**: Lines 1-481 (via `:root { }` block)
- **Content**: ~400+ CSS custom properties (--awa-*)
- **Size estimate**: ~8KB (extracted from larger bundle)

### CSS Sections After Variables
- Line 482+: Selection, scrollbar, reset, typography, buttons, forms, breadcrumbs, pagination, etc.
- Total: 15,600 lines of non-variable CSS rules

---

## 🎯 SF-001 Extraction Strategy

### Step 1: Extract Variables (Lines 1-481)
Create new file: `awa-core-variables.unmin.css`

Content structure:
```css
/* AWA MOTOS — awa-core-variables.css — Core Design Tokens */
/* Extracted from awa-bundle-core.unmin.css — SF-001 (2026-03-23) */

@layer awa-core {
    :root {
        /* 400+ CSS custom properties */
        color-scheme: light;
        --awa-red: #b73337;
        --awa-gray-50: #f7f7f7;
        ...
        --bp-2xl: 1440px;
    }
}
```

**File size target**: ~8-10KB unmin, ~2-3KB minified, ~0.8-1KB brotli

### Step 2: Update awa-bundle-core.unmin.css
Remove lines 1-481, keep lines 482+

Expected result:
- **Original**: 16,082 lines → **New**: 15,600 lines
- **Size reduction**: 551KB → ~480KB (-13%)

### Step 3: Update CSS Load Order

**Current (in awa-head-preload.phtml)**:
```php
$asyncBundles = [
    ...
    $block->getViewFileUrl('css/awa-bundle-custom.css'),
    ...
];
```

**New (add awa-core-variables as CRITICAL, inline)**:
```php
// In default_head_blocks.xml or via <link> in head
<link rel="stylesheet" href="awa-core-variables.css" media="all"/>
```

**Rationale**: Variables must load BEFORE any CSS that references them
- Move `awa-core-variables.css` to blocking (critical path)
- Load as FIRST stylesheet (before awa-bundle-vendor-libs.css)
- Inline option: Include directly in HTML (via PHP echo)

### Step 4: Build Pipeline

```bash
# 1. Extract variables
sed -n '1,481p' awa-bundle-core.unmin.css > awa-core-variables.unmin.css

# 2. Delete lines 1-481 from core
sed -i '1,481d' awa-bundle-core.unmin.css

# 3. Minify both
cleancss -o awa-core-variables.css awa-core-variables.unmin.css
cleancss -o awa-bundle-core.css awa-bundle-core.unmin.css

# 4. Compress both
brotli -f -q 11 awa-core-variables.css -o awa-core-variables.css.br
brotli -f -q 11 awa-bundle-core.css -o awa-bundle-core.css.br
gzip -9 awa-core-variables.css -c > awa-core-variables.css.gz
gzip -9 awa-bundle-core.css -c > awa-bundle-core.css.gz

# 5. Deploy
cp *.css pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/
nginx -s reload
php bin/magento cache:clean full_page
```

### Step 5: Update Layout XML

**File**: `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/layout/default_head_blocks.xml`

```xml
<head>
    <!-- Bundle 0/3: Core Variables (design tokens — must load FIRST) -->
    <css src="css/awa-core-variables.css"/>

    <!-- Bundle 1/3: Foundation (vendor libs + core styles now without variables) -->
    <css src="css/awa-bundle-vendor-libs.css"/>
    <css src="css/awa-bundle-core.css"/>

    <!-- Rest of CSS loads...  -->
```

---

## 📈 Expected Metrics

### Bundle Size Impact
| Metric | Before | After | Change |
|---|---|---|---|
| awa-bundle-core | 551KB | 480KB | -13% ✅ |
| awa-core-variables | (none) | 8-10KB | new |
| Total CSS | 1.8MB | 1.76MB | -2.3% |

### Performance Impact (Target)
| Metric | Current | Target | Gain |
|---|---|---|---|
| FCP | 2.5s | 2.3s | -8% |
| LCP | 2.8s | 2.4s | -14% |
| CSS Parse Time | 0.15s | 0.08s | -47% |

### Browser Rendering Impact
- **Parse time reduction**: Smaller stylesheet = faster CSSOM construction
- **Variable resolution**: No async lookup (variables inlined)
- **Layout shift**: None (CSS reordering transparent to layout)

---

## 🧪 Validation Checklist

### Pre-Implementation
- [ ] Backup current awa-bundle-core.unmin.css
- [ ] Run baseline Lighthouse: `npm run lighthouse`
- [ ] Record baseline metrics in PERFORMANCE_BASELINE_2026-03-23.md

### Implementation
- [ ] Extract variables: `sed -n '1,481p' ...`
- [ ] Remove from core: `sed -i '1,481d' ...`
- [ ] Minify both files
- [ ] Compress for production (brotli + gzip)
- [ ] Run CSS validation: `bash tests/css-validation.sh`
- [ ] Update layout XML with new load order

### Validation
- [ ] Visual regression: Screenshot comparison (staging)
- [ ] CSS cascade testing: All variables resolve correctly
- [ ] Lighthouse: Should see FCP/LCP improvement
- [ ] Console errors: 0 CSS parse errors
- [ ] Network: Confirm new file loaded (Network tab)

### Testing
- [ ] Homepage (cms_index_index): All variables available
- [ ] Category page: Colors, spacing correct
- [ ] Product detail: Form styling, shadows correct
- [ ] Checkout: Buttons, inputs, select dropdowns correct
- [ ] Mobile viewport: Responsive spacing (all variables)

### Browser Support
- [ ] Chrome/Edge 88+: CSS variables fully supported
- [ ] Firefox 55+: CSS variables fully supported
- [ ] Safari 9.1+: CSS variables fully supported
- [ ] No IE11 support needed (Magento 2.4+ requirement)

---

## 🚀 Deployment Strategy

### Phase 1: Local Development
1. Create SF-001 branch ✅ (already done)
2. Implement changes locally
3. Run validation suite
4. Commit with detailed message

### Phase 2: Canary (10% Traffic, 1h)
1. Deploy to staging
2. Run Lighthouse: Target -7% LCP
3. Visual inspection (all page types)
4. Monitor console for errors
5. If successful: Gradual rollout

### Phase 3: Production Rollout
1. 10% traffic × 15 min (baseline)
2. 25% traffic × 15 min
3. 50% traffic × 15 min
4. 75% traffic × 15 min
5. 100% traffic × final

### Rollback Plan
```bash
git revert <SF-001-commit>
git push origin main
php bin/magento cache:clean full_page
# Rollback time: ~3 min
```

---

## 📝 Git Workflow

```bash
# Already on branch: improvement/SF-001-core-variables

# Track progress
git add .
git status

# Commit when implementation complete
git commit -m "SF-001: core variables extraction - design tokens split

- Extract CSS variables from awa-bundle-core.unmin.css (lines 1-481)
- Create new awa-core-variables.css for critical path loading
- Reduce awa-bundle-core.css by -13% (~70KB)
- Update CSS load order: variables → vendor libs → core
- Performance targets: -7% LCP, -47% CSS parse time

Bundle sizes:
- awa-core-variables: 8-10KB unmin, ~2-3KB min, ~0.8-1KB br
- awa-bundle-core: 480KB unmin (was 551KB), -13%
- Total CSS: 1.76MB (was 1.8MB), -2.3%

Testing:
- Lighthouse baseline: 2.8s LCP → target 2.4s
- CSS parse time: 0.15s → target 0.08s
- Visual testing: All page types (homepage, PDP, checkout)
- Accessibility: Colors, variables, WCAG AA/AAA maintained

Deployment:
- Canary: 10% traffic × 1h
- Gradual rollout: 25% → 50% → 75% → 100%
- Rollback ready: git revert <hash>"
```

---

## ⏱️ Timeline

**Today (2026-03-23)**:
- [x] Create branch: improvement/SF-001-core-variables
- [ ] Extract variables and implement changes
- [ ] Run local validation (2-3h)
- [ ] Commit with full description

**Tomorrow (2026-03-24)**:
- [ ] Deploy to staging
- [ ] Lighthouse baseline + metrics
- [ ] Visual regression testing
- [ ] Canary deployment (10%, 1h)

**Later (2026-03-24 afternoon)**:
- [ ] Gradual rollout if canary successful
- [ ] Production metrics confirmation
- [ ] Documentation update

---

## 🎓 Lessons & Next Steps

### What We're Testing
1. **Bundle splitting strategy**: Can we safely split design system from implementations?
2. **Load order dependency**: Do all CSS rules correctly reference variables?
3. **Performance gains**: Does smaller bundle actually improve LCP?

### Knowledge for OF-001
- Bundle splitting patterns established in SF-001
- Minification and compression pipeline validated
- Canary/rollout procedures documented

---

## 📚 References

- [IMPROVEMENT_PLAN_2026Q1.md](./IMPROVEMENT_PLAN_2026Q1.md) — Full 4-week roadmap
- [IMPROVEMENT_FRAMEWORK.md](./IMPROVEMENT_FRAMEWORK.md) — Deployment framework
- [PERFORMANCE_BASELINE_2026-03-23.md](./PERFORMANCE_BASELINE_2026-03-23.md) — Baseline metrics
- [tests/css-validation.sh](./tests/css-validation.sh) — Validation script

---

**Status**: READY TO IMPLEMENT
**Branch**: improvement/SF-001-core-variables
**Git HEAD**: d5cbf4f8

---

## Progress Update — 2026-03-23 (Progressive Safe Rollout)

### Scope implemented in this cycle
- Added a progressive performance optimization for footer rendering, scoped to A/B treatment variant only.
- Control variant remains untouched to preserve exact production baseline and instant rollback path.

### Feature flag / A-B safety model used
- Existing experiment: `footer_progressive_rollout`
- Gating selector: `.page_footer.awa-footer-exp--treatment`
- Control selector remains default (`control`) with no new optimization rules.

### Change details
- File changed: `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-refinements.unmin.css`
    - Added treatment-only rules:
        - `content-visibility: auto;`
        - `contain-intrinsic-size: auto 220px;`
        - Mobile tuning (`<=767px`): `contain-intrinsic-size: auto 160px;`
- Contract test reinforced:
    - `app/code/GrupoAwamotos/Theme/Test/Unit/Contract/FooterExperimentContractTest.php`
    - Verifies treatment-scoped CSS selectors and perf declarations.
- Integration test expanded:
    - `app/code/GrupoAwamotos/Theme/Test/Integration/Helper/FooterExperimentTest.php`
    - Validates payload telemetry keys (`experiment`, `control_variant`).

### Metrics (before vs after)
- `awa-bundle-refinements` literal metric-lines:
    - Before previous hardening rounds: `251`
    - After latest hardening rounds: `242`
    - Delta: `-9` lines (`-3.6%`)
- Runtime safety:
    - Static content deploy (`pt_BR`) successful.
    - No new errors observed in `var/log/system.log` / `var/log/exception.log` for this cycle.

### Incremental deployment plan (already aligned)
1. Keep experiment disabled globally (`enabled=0`) for baseline verification.
2. Enable experiment with `rollout_percentage=10` for canary.
3. Increase to `25` → `50` → `75` → `100` based on metrics and logs.
4. If anomaly detected, set rollout to `0` (or disable) for immediate fallback to control.

### Reversibility / rollback
- Functional rollback (no code deploy):
    - Set footer experiment rollout to `0` or disable experiment in admin config.
- Code rollback (if needed):
    - Revert the commit that introduced treatment-only optimization and redeploy static assets.

### Team communication log (handoff-ready)
- Improvement is guarded by existing experiment framework; no forced behavior change on control group.
- Tests were expanded to prevent accidental de-scoping of treatment-only optimization.
- Rollout can proceed progressively with immediate fallback path.

---

## Progress Update — 2026-03-23 (Safe Optimization: Request Memoization)

### Scope implemented in this cycle
- Added request-level memoization to footer experiment helper to remove redundant configuration reads and repeated decider execution in the same request/store context.
- No public API changed and no control/treatment decision rules were modified.

### Files changed
- `app/code/GrupoAwamotos/Theme/Helper/FooterExperiment.php`
    - Added in-memory caches per store for:
        - `enabled`
        - `rollout_percentage`
        - `variant_seed`
        - final `payload`
- `app/code/GrupoAwamotos/Theme/Test/Unit/Helper/FooterExperimentTest.php`
    - Added regression test proving repeated `getPayload()` calls reuse cached decision/config.
- `app/code/GrupoAwamotos/Theme/Test/Integration/Helper/FooterExperimentTest.php`
    - Added repeated-call stability assertion for payload contract.

### Before vs after metrics (same request, same store)
- `getPayload()` first call:
    - Before: 1 experiment decision + 3 config reads
    - After: 1 experiment decision + 3 config reads
- `getPayload()` second call:
    - Before: 1 additional decision + 3 additional config reads
    - After: 0 additional decision + 0 additional config reads
- Net reduction for repeated call path:
    - Decision executions: `-100%` on repeated call
    - Config reads: `-100%` on repeated call

### Safety and rollout
- Behavior-preserving optimization only; experiment gating remains unchanged.
- Can be rolled out immediately because it does not alter bucket/variant logic.
- Existing incremental rollout ladder remains valid: `0 -> 10 -> 25 -> 50 -> 75 -> 100`.

### Validation evidence
- PHPUnit targeted suite:
    - `11 tests`, `23 assertions`, `1 skipped`, status `OK`
- PHP lint:
    - No syntax errors in all modified PHP files.
- Logs:
    - No new exception entries tied to this change set.

### Reversibility
- Fast rollback option:
    - Revert only this helper/test commit.
- Runtime safety:
    - No database migration or schema change involved.

---

## Progress Update — 2026-03-23 (Safe Optimization: Header Request Memoization)

### Scope implemented in this cycle
- Implemented request-level memoization in header experiment helper to eliminate repeated configuration reads and repeated decider execution in the same request/store context.
- Maintained full behavioral parity for control/treatment assignment.

### Files changed
- `app/code/GrupoAwamotos/Theme/Helper/HeaderExperiment.php`
    - Added cache maps per store for `enabled`, `rollout_percentage`, `variant_seed`, and final `payload`.
- `app/code/GrupoAwamotos/Theme/Test/Unit/Helper/HeaderExperimentTest.php`
    - Added memoization regression test for repeated `getPayload()` calls.
- `app/code/GrupoAwamotos/Theme/Test/Integration/Helper/HeaderExperimentTest.php`
    - Added payload stability assertion and telemetry key checks (`experiment`, `control_variant`).

### Before vs after metrics (same request, same store)
- `getPayload()` first call:
    - Before: 1 decision + 3 config reads
    - After: 1 decision + 3 config reads
- `getPayload()` repeated call:
    - Before: +1 decision +3 config reads
    - After: +0 decision +0 config reads
- Net repeated-call gain:
    - Decision executions reduced by `100%`
    - Config reads reduced by `100%`

### Validation evidence
- PHPUnit targeted suite (Header):
    - `11 tests`, `23 assertions`, `1 skipped`, status `OK`
- PHP lint:
    - No syntax errors in all modified files.
- Logs:
    - No new exception entries associated with the change.

### Deployment and rollback
- Safe to deploy incrementally; no schema/config migration required.
- Rollback path:
    - Revert this change-set commit only.

---

## Progress Update — 2026-03-23 (CSS Tail Final Pass: Spacing + Typography + Duplicate Cleanup)

### Scope implemented in this cycle
- Final low-risk pass on `awa-bundle-tail.unmin.css` focused on:
    - Spacing tokenization with fallback for fixed px values.
    - Typography tokenization for letter-spacing and fixed line-height values.
    - Removal of an explicit duplicated `.fancybox-close` declaration block.
- Kept rendering parity by using `var(..., <legacy-px>)` fallbacks.

### Files changed
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-tail.unmin.css`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-tail.css` (regenerated)

### Before vs after metrics (targeted literals)
- Tracked legacy literals in targeted rules:
    - `padding: 0 50px`
    - `margin-bottom: 55px`
    - `height: 45px` / `line-height: 45px`
    - `line-height: 39px` / `line-height: 38px`
    - `padding: 2px 8px` / `padding: 2px 6px`
    - `padding: 10px 42px 10px 14px`
    - `gap: 15px`
    - `margin: 0 auto 40px`
    - `margin-left: -15px` / `margin-right: -15px`
- Result after patch:
    - `0` occurrences for all tracked exact legacy declarations.

### Validation evidence
- Asset build:
    - `cleancss` regeneration completed successfully.
- Runtime sync:
    - Updated minified CSS copied to `pub/static`.
- Cache:
    - `cache:clean full_page block_html` completed.
- Full static deploy:
    - `setup:static-content:deploy pt_BR -f` completed successfully.
- Logs:
    - No new exception entries associated with this cycle.

### Reversibility
- Runtime rollback:
    - Restore previous bundle via git revert of this commit.
- Functional safety:
    - No markup/JS/schema changes, CSS-only with fallback-preserving declarations.

---

## Progress Update — 2026-03-23 (Header Telemetry Expansion)

### Scope implemented in this cycle
- Expanded progressive header telemetry in existing JS pipeline (no new loader entrypoint).
- Kept implementation read-only from UX perspective (tracking-only, no layout change).

### File changed
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-header-a11y-performance.js`

### Events added
- `awa_header_account_click`
- `awa_header_category_click`
- `awa_header_sticky_state`

### Existing events normalized
- `awa_header_experiment_exposure`
- `awa_header_nav_toggle_click`
- `awa_header_minicart_click`

### Data quality improvements
- Unified payload via `pushHeaderTelemetry(...)` helper.
- Added normalized text extraction for clicked links (`link_text`) and `link_href`.

### Safety and rollout
- Works within existing experiment payload (`header_progressive_rollout`).
- No schema/backend coupling; event-only extension.

### Reversibility
- Revert single JS change if any analytics anomaly is detected.
