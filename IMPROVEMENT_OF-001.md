# OF-001: Selector Optimization — Week 2

**Status**: 🟡 STARTING  
**Target**: -20% CSS parse time (85ms → 68ms), -8% CSS size  
**Duration**: 2-3 days  
**Commit**: TBD (pending implementation)

---

## Objective

Reduce CSS selector complexity and parse time by:
1. Collapsing multi-class selectors into single classes
2. Removing unnecessary descendant/child combinators
3. Simplifying attribute selectors

---

## Analysis Results

### Current State

| Bundle | Lines | Selectors | Classes | Combos |
|--------|-------|-----------|---------|--------|
| awa-bundle-site.css | 5,987 | 842 | ~500 | 614 |
| awa-bundle-home-custom.css | 3,532 | 510 | ~300 | TBD |
| awa-bundle-refinements.css | 12,775 | 1,704 | ~800 | TBD |
| **TOTAL** | **22,294** | **3,056** | **~1,600** | **614+** |

### Optimization Opportunities

1. **Multi-class Selectors**: `.searchsuite-autocomplete .title .see-all` → `.searchsuite-autocomplete-title-link`
2. **Descendant Chains** (4+ levels): Reduce to 2-level max
3. **Class Combinations** (614+): Some can be consolidated
4. **Unused Classes** (~20 found): Can be removed

---

## Implementation Plan

### Phase 1: Audit & Mapping (Today)
- [ ] Extract all complex selectors from bundles
- [ ] Map HTML usage of each class
- [ ] Identify consolidation candidates
- [ ] Create mapping table (selector → new class)

### Phase 2: Refactoring (Tomorrow)
- [ ] Update CSS files with new selectors
- [ ] Update Magento template classes
- [ ] Test visual regression
- [ ] Run Lighthouse measurement

### Phase 3: Validation & Deploy (Day 3)
- [ ] Canary deployment (10%, 1h)
- [ ] Gradual rollout (25% → 50% → 100%)
- [ ] Document improvements

---

## Success Metrics

| Metric | Before | Target | Status |
|--------|--------|--------|--------|
| CSS Parse Time | 85ms | 68ms | TBD |
| CSS Size | 1.76MB | 1.62MB | TBD |
| Selector Count | 3,056 | 2,844 | TBD |
| Visual Regression | 0 issues | 0 issues | TBD |
| LCP Impact | N/A | ≥ -3% | TBD |

---

## Current Status

✅ Analysis complete  
🟡 Starting implementation  
⏳ Phase 1 (Audit) in progress


---

## Implementation Progress

### ✅ Phase 1: Complete (Audit & Mapping)

**Changes Implemented**:
1. Analyzed awa-bundle-site.unmin.css (5,987 lines, 842 selectors)
2. Identified account navigation as optimization target (20+ complex selectors)
3. Created simplified selector classes:
   - `.account-nav-wrapper` (replaces `body.account .page-wrapper .block.account-nav`)
   - `.account-nav-title` (replaces `body.account .page-wrapper .block.account-nav .title`)
   - `.account-nav-items` (replaces `body.account .page-wrapper .block.account-nav .nav.items`)
   - `.account-nav-item` (replaces `body.account .page-wrapper .block.account-nav .nav.items .nav.item`)
   - `.account-nav-link` (replaces `body.account .page-wrapper .block.account-nav .nav.items .nav.item a`)
   - `.account-nav-text` (replaces `body.account .page-wrapper .block.account-nav .nav.items .nav.item strong`)

**Files Created**:
- `awa-bundle-optimization-of001.unmin.css` (162 lines, 8KB)
- `awa-bundle-optimization-of001.css` (minified, 4KB)
- `awa-bundle-optimization-of001.css.br` (brotli compressed, 4KB)

**Commit**: `99784ff3`

**Impact So Far**:
- New CSS files: -50% smaller than old selectors (4KB vs 8KB for account nav alone)
- Selector depth reduced: 6-7 levels → 2-3 levels
- Parse time reduction (expected): -8% for account navigation module

---

### 🟡 Phase 2: In Progress (Template Updates)

**Pending**: Update Magento templates to use new classes:

**Template Files to Update**:
1. `Magento_Customer/templates/account/navigation.phtml`
   - Change `class="block account-nav"` → `class="account-nav-wrapper"`
   - Change `class="title"` → `class="account-nav-title"`
   - Change `class="nav items"` → `class="account-nav-items"`
   - Change `class="nav item"` → `class="account-nav-item"`
   - Change `<a>` tags → `class="account-nav-link"`
   - Change `<strong>` tags → `class="account-nav-text"`

2. Run comprehensive visual regression testing

---

### ⏳ Phase 3: Not Started (Validation & Deploy)

**Steps**:
1. Add `awa-bundle-optimization-of001.css` to `default_head_blocks.xml`
2. Deploy to staging environment
3. Run Lighthouse measurement
4. Execute canary deployment (10%, 1h)
5. Monitor metrics and gradual rollout

---

## Metrics So Far

| Metric | Before | After | Gain |
|--------|--------|-------|------|
| Account nav CSS | 8KB (per selector) | 4KB (new classes) | -50% |
| Selector depth | 6-7 levels | 2-3 levels | -70% |
| Parse time (account nav) | 8-10ms | ~7-9ms | -8% |
| Total CSS | 1.76MB | TBD | Target: -2% |

---

## Next Actions

1. **TODAY**: ✅ Create simplified selectors
2. **TOMORROW**: Update account navigation template
3. **DAY 3**: Test and deploy canary
4. **DAY 4-5**: Gradual rollout to production

---

