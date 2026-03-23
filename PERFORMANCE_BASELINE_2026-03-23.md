# Performance Baseline — 2026-03-23
**Commit**: e40a1c16 (QF-1 a QF-3)

## CSS Metrics (Static Analysis)

### Bundle Sizes
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-core.css | 372K |
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-core.unmin.css | 552K |
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-custom.css | 104K |
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-custom.unmin.css | 128K |
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-home-custom.css | 120K |
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-home-custom.unmin.css | 164K |
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-phases.css | 124K |
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-phases.unmin.css | 192K |
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-site.css | 176K |
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-site.unmin.css | 224K |
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-visual-fixes-critical.css | 48K |
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-visual-fixes-critical-optimized.css | 8.0K |
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-visual-fixes-critical.unmin.css | 100K |

### CSS Statistics

| Bundle | Lines | Rules | Variables |
|---|---|---|---|
| core | 16082 | 2182 | 0 |
| site | 5987 | 842 | 0 |
| refinements | 12775 | 1704 | 0 |
| home-custom | 3531 | 510 | 0 |
| custom | 3913 | 538 | 0 |
| phases | 5855 | 733 | 0 |
| visual-fixes-critical | 2482 | 326 | 0 |

## Selector Complexity Analysis

| Bundle | Max Specificity | Avg Specificity | Duplication % |
|---|---|---|---|
| core | 0.3.1 | 0.1.8 | 2.1% |
| site | 0.2.2 | 0.0.9 | 1.8% |
| refinements | 0.2.1 | 0.1.0 | 3.2% |

## Web Vitals (Estimated from Lighthouse)
*To be measured via production monitoring*

- **LCP (Largest Contentful Paint)**: TBD (baseline measurement needed)
- **FID (First Input Delay)**: TBD
- **CLS (Cumulative Layout Shift)**: TBD
- **Core Web Vitals Score**: TBD

## Next Steps
1. Set up Lighthouse CI
2. Add CSS parsing benchmark to CI
3. Monitor production metrics

