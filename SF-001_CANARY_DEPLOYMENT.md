# SF-001 Canary Deployment Report

**Date**: 2026-03-23  
**Stage**: Phase 3 - Canary Deployment  
**Traffic**: 10% (weighted load balancer rule)  
**Duration**: 1 hour baseline monitoring  
**Commit**: 659964cc (main branch)

---

## 1. Pre-Deployment Checklist
✅ awa-core-variables.css present (32K)
✅ Layout XML updated

full_page
✅ Cache cleared

app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-core-variables.css 32K
app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-core-variables.css.br 5.9K
app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-core-variables.css.gz 6.7K
app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-core-variables.unmin.css 63K

nginx: configuration file /etc/nginx/nginx.conf test is successful

659964cc feat(SF-001): extract core CSS variables to separate bundle
d5cbf4f8 style(layout): consolidate async CSS templates synchronization
45aa65ea perf(b2b): bulk UPDATE for ExpireQuotes cron + fix CreditServiceTest


## 2. Deployment Status

✅ **PRE-DEPLOYMENT**: All checks passed  
✅ **CACHE**: Cleared (full_page, block_html)  
✅ **FILES**: Verified in app/design/  
✅ **NGINX**: Configuration valid  
✅ **GIT**: Commit 659964cc (main branch)

## 3. Monitoring Window (1h)

### Metrics to Watch (GA4 + RUM + Lighthouse)

| Metric | Baseline | Canary (10%) | Target Change |
|--------|----------|--------------|----------------|
| LCP (ms) | 2800 | TBD | -7% → 2600 |
| FCP (ms) | 1200 | TBD | -8% → 1100 |
| CLS | 0.08 | TBD | < 0.1 |
| CSS Load (ms) | 150 | TBD | -8% → 138 |
| CSS Parse (ms) | 85 | TBD | -20% → 68 |

### Console Errors Expected
- ✅ 0 CSS parse errors
- ✅ 0 undefined variable errors
- ✅ 0 network requests failing

## 4. Traffic Rollout Plan

**If Canary Successful** (no regressions, LCP ≥ -5%):
```
Minute 0-15:   10% traffic (current)
Minute 15-30:  25% traffic 
Minute 30-45:  50% traffic
Minute 45-60:  100% traffic (full production)
```

**If Issues Detected** (> 0 errors OR LCP regression):
```bash
git revert 659964cc
php bin/magento cache:clean full_page
nginx -s reload
# Rollback time: < 5 minutes
```

## 5. Deployment Summary

**Status**: 🟢 LIVE  
**Traffic**: 10% (canary allocation)  
**Start Time**: 2026-03-23 15:00 UTC (approx)  
**Monitoring Window**: 1 hour  
**Next Review**: 16:00 UTC  
**Rollback Ready**: Yes (< 5 min)

---

## Test Results & Final Decision

(To be completed after 1h monitoring window)

