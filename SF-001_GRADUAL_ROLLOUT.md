# SF-001 Gradual Rollout Plan

**Status**: Ready for execution after canary validation  
**Commit**: `659964cc` (main branch - already merged)  
**Total Duration**: 60 minutes (if all stages pass)

---

## Rollout Strategy: Traffic Escalation

### Phase 1: Canary (10% - CURRENT)
- **Status**: 🟢 LIVE (started ~15:00 UTC)
- **Users**: ~500-1000 requests/min
- **Duration**: 15 minutes minimum
- **Exit Criteria**:
  - ✅ 0 CSS errors in console
  - ✅ 0 network failures
  - ✅ LCP metric ≥ -5% (2660ms target)
  - ✅ Visual test passed

**When to Proceed**: After 15min with no errors → Advance to Phase 2

---

### Phase 2: Early Adopters (25%)
- **Users**: ~1500-2500 requests/min
- **Duration**: 15 minutes
- **Deployment**: Increase load balancer weight from 10% → 25%
  ```bash
  # Command to execute on load balancer:
  nginx reload with updated upstream weight
  ```
- **Exit Criteria**:
  - ✅ Error rate ≤ 0.1%
  - ✅ All CSS bundles load (no 404s)
  - ✅ No regression in LCP
  - ✅ Cache hit rate > 90%

**When to Proceed**: After 15min stable → Advance to Phase 3

---

### Phase 3: Majority (50%)
- **Users**: ~3000-5000 requests/min  
- **Duration**: 15 minutes
- **Deployment**: Increase load balancer weight from 25% → 50%
- **Exit Criteria**:
  - ✅ Error rate ≤ 0.05%
  - ✅ 95th percentile LCP < 3000ms
  - ✅ No increase in server 5xx errors
  - ✅ Real User Monitoring (RUM) shows gains

**When to Proceed**: After 15min stable → Advance to Phase 4

---

### Phase 4: Full Production (100%)
- **Users**: All ~6000-10000 requests/min
- **Duration**: Full production run
- **Deployment**: Increase load balancer weight from 50% → 100%
- **Success Criteria**:
  - ✅ Error rate < 0.05%
  - ✅ No performance regression detected
  - ✅ LCP improvement sustained (≥ -5%)
  - ✅ CSS file sizes as expected

---

## Rollback Procedure (If Issues at Any Phase)

**Time to Rollback**: < 5 minutes

```bash
# 1. Revert the commit
git revert 659964cc

# 2. Clear cache
php bin/magento cache:clean full_page block_html

# 3. Reload Nginx
nginx -s reload

# 4. Verify revert
curl -I https://awamotos.com/ | grep "X-Cache"
```

**Automatic Rollback Triggers**:
- ❌ > 5 CSS errors in any 1-minute window
- ❌ LCP regression > 500ms sustained
- ❌ > 1% of requests failing
- ❌ 5xx error rate > 1%

---

## Monitoring Checklist (Active During Rollout)

### Real-Time Monitoring Tools

**1. Browser DevTools** (Spot Checks Every 5min)
- [ ] Open home page in 3-4 browsers
- [ ] Network tab: Verify CSS load times
- [ ] Console: Check for errors
- [ ] Lighthouse: Compare LCP with baseline

**2. Server Metrics** (nginx/PHP-FPM)
```bash
# Monitor error logs
tail -f var/log/system.log | grep -i "error\|warning"

# Monitor cache hit rate
redis-cli INFO stats | grep keyspace_hits
```

**3. Load Balancer Metrics**
- Response time percentiles (p50, p95, p99)
- Error rate (4xx, 5xx)
- Upstream health checks

**4. Google Analytics / GA4**
- Real User Monitoring (Core Web Vitals)
- Session duration
- Bounce rate

---

## Timeline Estimate

```
15:00 UTC - Canary 10% starts  
15:15 UTC - Advance to 25% (if metrics OK)
15:30 UTC - Advance to 50% (if metrics OK)
15:45 UTC - Advance to 100% (if metrics OK)
16:00 UTC - Full rollout complete ✅
```

**Total Duration**: ~1 hour (if all stages pass without issues)

---

## Post-Deployment Validation

**1h After 100% Rollout**:
- [ ] Run Lighthouse test on homepage
- [ ] Verify LCP improvement (target: -7%)
- [ ] Document final metrics in SF-001_METRICS.md
- [ ] Create success announcement
- [ ] Mark SF-001 as COMPLETE

**24h After Deployment**:
- [ ] Review error logs (no spikes)
- [ ] Check real user metrics (GA4)
- [ ] Verify cache efficiency
- [ ] Monitor for any reported issues

---

## Decision Flowchart

```
START (Canary 10% Live)
  ↓
[15min Monitoring]
  ├─ Errors? ──→ ROLLBACK (git revert 659964cc)
  └─ OK? ──→ Phase 2 (25%)
    ↓
  [15min Monitoring]
    ├─ Errors? ──→ ROLLBACK
    └─ OK? ──→ Phase 3 (50%)
      ↓
    [15min Monitoring]
      ├─ Errors? ──→ ROLLBACK
      └─ OK? ──→ Phase 4 (100%)
        ↓
      [Sustained Monitoring]
        ├─ Errors? ──→ ROLLBACK
        └─ Success! ──→ MARK SF-001 COMPLETE ✅
```

---

## Files Modified by SF-001

- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-core-variables.css` (NEW)
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-core.unmin.css` (MODIFIED)
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-core.css` (MODIFIED)
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-core.css.br` (MODIFIED)
- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/layout/default_head_blocks.xml` (MODIFIED)

**Rollback Scope**: 6 files (all tracked in git commit 659964cc)

---

## Success Metrics

| Metric | Target | Threshold |
|--------|--------|-----------|
| LCP (Largest Contentful Paint) | -7% (2600ms) | ≥ -5% (2660ms) |
| FCP (First Contentful Paint) | -8% (1100ms) | ≥ -5% (1140ms) |
| CSS Parse Time | -20% (68ms) | ≥ -10% (76ms) |
| File Size (CSS) | -2.3% (1.76MB) | Verified ✅ |
| Error Rate | < 0.05% | < 0.1% |
| Cache Hit Rate | > 92% | > 90% |

