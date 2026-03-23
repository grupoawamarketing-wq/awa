# 📅 IMPROVEMENT PLAN — Q1 2026 (4 Semanas)

**Versão**: 1.0  
**Data Inicial**: 2026-03-24  
**Data Final**: 2026-04-20  
**Responsável**: Jess + Dev Team  
**Status**: 🟡 Planning (Ready to Start)

---

## 📌 VISÃO GERAL

4 fases de melhoria contínua, cada uma entregando valor mensurável e independente:

```
SEMANA 1      SEMANA 2      SEMANA 3      SEMANA 4
SF-001        OF-001        AF-001        MF-001
Segmentation  Optimization  Accessibility Mobile-First
-13% CSS      -20% Parse    100% WCAG     +20% UX
```

---

## 🎯 FASE 1: SF (Segmentation Fixes) — Semana 1

### SF-001: Core Variables Extraction

**Objetivo**: Split `awa-bundle-core` em micro-bundles  
**Target**: -13% CSS size, -7% LCP  
**Duração**: 2-3 dias  

#### Escopo

```
ANTES:
awa-bundle-core.css (552KB, 16,082 lines)
  ├── CSS variables (5KB)
  ├── Spacing tokens (12KB)
  ├── Typography (18KB)
  ├── Base elements (45KB)
  └── Component library (472KB)

DEPOIS:
awa-core-variables.css (5KB) — inline no <head>
awa-core-spacing.css (12KB) — loaded async
awa-base-elements.css (35KB) — critical path
awa-components.css (420KB) — deferred
```

#### Técnica

1. **Pré-compilação**: Extract CSS variables → `awa-core-variables.unmin.css`
2. **Splitting**: 4 novos bundles conforme estrutura acima
3. **Validation**: CSS syntax + variable resolution
4. **Testing**: 
   - Visual regression (screenshot comparison)
   - Performance (Lighthouse +5% target)
   - Accessibility (no regressions)
5. **Deployment**: Canary 10% traffic × 1h → Production gradual

#### Métricas

| Métrica | Baseline | Target | Ganho |
|---|---|---|---|
| Core size | 552KB | 478KB | -13% ✅ |
| Parse time | 0.15s | 0.08s | -47% |
| LCP | 2.8s | 2.4s | -14% |
| FID | 85ms | 45ms | -47% |
| Requests | 18 CSS | 19 CSS | +1 (trade-off) |

#### Checklist

- [ ] Branch criada: `improvement/SF-001-core-variables`
- [ ] Variables extracted
- [ ] 4 bundles compilados
- [ ] Validation suite passou
- [ ] Lighthouse baseline (staging)
- [ ] Visual regression check
- [ ] PR criada + reviewed
- [ ] Canary deployment (10%, 1h)
- [ ] Production rollout
- [ ] Metrics documented

#### Rollback

```bash
git revert <commit-hash>
php bin/magento cache:clean full_page
# Rollback time: ~3 min
```

---

## 🎯 FASE 2: OF (Optimization Fixes) — Semana 2

### OF-001: Selector Optimization

**Objetivo**: Simplify CSS selectors, reduce parse time  
**Target**: -20% parse time, -8% CSS size  
**Duração**: 2-3 dias

#### Escopo

```
PROBLEMA:
body .page-wrapper .page_footer .awa-footer-brands .brand-item img { }
body .page-wrapper .page_footer .awa-pay-logo img { }
(specificity 0.2.5 — overkill)

SOLUÇÃO:
.awa-footer-brands .brand-item img { }
.awa-pay-logo img { }
(specificity 0.1.2 — lean)
```

#### Técnica

1. **Audit**: Identify selectors with specificity > 0.2.3
2. **Refactor**: Simplify while maintaining cascade integrity
3. **Consolidation**: Merge duplicate rules
4. **Testing**: 
   - Selector validation (specificity check)
   - Cascade testing (no unintended overrides)
   - Performance (CSS Analyzer metrics)
5. **Deployment**: Same as SF-001

#### Métricas

| Métrica | Baseline | Target | Ganho |
|---|---|---|---|
| Parse time | 0.15s | 0.08s | -47% |
| CSS size | 1.8MB | 1.65MB | -8% |
| Specificity avg | 0.2.2 | 0.1.1 | -50% |
| Rule consolidation | — | 50 merged | N/A |

#### Checklist

- [ ] Selector audit completed
- [ ] Refactoring rules documented
- [ ] Minification verified
- [ ] Cascade testing passed
- [ ] Lighthouse +3% improvement (target)
- [ ] Production deployment

---

## 🎯 FASE 3: AF (Accessibility Fixes) — Semana 3

### AF-001: WCAG 2.2 AAA Compliance

**Objetivo**: Garantir 100% WCAG 2.2 AAA compliance  
**Target**: 100% pass rate, +25 Lighthouse score  
**Duração**: 2-3 dias

#### Escopo

1. **Color Contrast**: All text 4.5:1 (AAA) or 7:1 (AAA+)  
2. **Focus Indicators**: Visible on all interactive elements  
3. **Touch Targets**: Min 48×48px (WCAG AAA) or 44×44px (AA)
4. **Skip Links**: Keyboard navigation fully functional
5. **Dark Mode**: All colors tested in dark theme
6. **Mobile**: Portrait + landscape fully tested

#### Técnica

1. **Audit**: WCAG checker tool + manual review
2. **Testing**: Accessibility validator + keyboard navigation
3. **Fixes**:
   - Add `:focus-visible` outlines (3px, 2px space)
   - Increase touch targets (buttons, links)
   - Improve color contrast ratios
4. **Deployment**: Standard canary → production

#### Métricas

| Métrica | Baseline | Target | Ganho |
|---|---|---|---|
| WCAG pass rate | ~85% | 100% | +15pp |
| Color contrast fail | 8 elements | 0 | ✅ |
| Touch target fail | 12 elements | 0 | ✅ |
| Focus indicators | ~70% visible | 100% | ✅ |
| Lighthouse a11y | 82 | 100 | +18 pts |

#### Checklist

- [ ] WCAG audit completed
- [ ] Color contrast fixes applied
- [ ] Touch targets resized
- [ ] Focus indicators added
- [ ] Keyboard navigation tested
- [ ] Axe DevTools 0 violations
- [ ] Production deployment

---

## 🎯 FASE 4: MF (Mobile-First Fixes) — Semana 4

### MF-001: Responsive Refinements

**Objetivo**: Optimize mobile experiences  
**Target**: +20 Lighthouse mobile score  
**Duração**: 2-3 dias

#### Escopo

1. **Breakpoints**: Audit all `@media` queries
2. **Mobile Layout**: Test on 375px, 425px, 768px viewports
3. **Touch**: Increase spacing on mobile
4. **Typography**: Font sizes for readability
5. **Performance**: Defer non-critical CSS on mobile

#### Técnica

1. **Audit**: Current breakpoints vs Mobile-first plan
2. **Testing**: 3 real devices (iPhone, Android, iPad)
3. **Fixes**:
   - Consolidate breakpoints (12 → 5)
   - Defer below-fold CSS
   - Optimize font loading
4. **Deployment**: Standard canary → production

#### Métricas

| Métrica | Baseline | Target | Ganho |
|---|---|---|---|
| Mobile Lighthouse | 72 | 92 | +20 pts |
| LCP (mobile) | 4.2s | 3.2s | -24% |
| CLS (mobile) | 0.08 | 0.02 | -75% |
| Bootstrap time | 890ms | 650ms | -27% |

#### Checklist

- [ ] Mobile audit completed (3 devices)
- [ ] Breakpoints consolidated
- [ ] Touch spacing increased
- [ ] Font loading optimized
- [ ] Lighthouse mobile 92+ score
- [ ] Production deployment

---

## 🔄 PROCESSO UNIVERSAL (Todas as Fases)

### 1. Preparação (1 dia)

```bash
# 1. Branch creation
git checkout -b improvement/SF-001-core-variables

# 2. Baseline measurement
npm run lighthouse -- https://awamotos.com

# 3. Code audit
bash tests/css-validation.sh awa-bundle-core.unmin.css
```

### 2. Implementação (1-2 dias)

```bash
# 1. Edit CSS files
vi app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-*.unmin.css

# 2. Minify
cleancss -O1 -o output.css input.unmin.css

# 3. Compress
brotli -f -q 11 output.css -o output.css.br
gzip -9 output.css -c > output.css.gz

# 4. Test locally
bash tests/css-validation.sh output.css
```

### 3. Validação (1 dia)

```bash
# 1. Visual regression
npm run screenshot-compare

# 2. Performance
npm run lighthouse -- https://staging.awamotos.com

# 3. Accessibility
npm run axe-check

# 4. Responsiveness
npm run responsive-test -- 375px 425px 768px 1024px
```

### 4. Canary Deployment (1h)

```bash
# 1. Deploy to 10% traffic
# (A/B test infrastructure)

# 2. Monitor for 1h
# - Browser console errors: 0
# - 404s for CSS: 0
# - Performance degradation: < 2%

# 3. Gradual rollout
# 25% → 50% → 75% → 100% (over 2h)
```

### 5. Production Notification

```
[#awa-improvements] ✅ SF-001 complete
- CSS: -13% (552KB → 478KB)
- LCP: -14% (2.8s → 2.4s)
- Rollback ready: git revert <hash>
- Metrics: https://link-to-dashboard
```

---

## 📊 TIMELINE VISUAL

```
       MON       TUE       WED       THU       FRI
WK1 |----------|----------|----------|----------|
         SF-001: Preparation → Implementation → Validation → Canary → Prod

WK2 |----------|----------|----------|----------|
     Monitoring → OF-001: Prep → Implementation → Validation → Canary → Prod

WK3 |----------|----------|----------|----------|
     Monitoring → AF-001: Prep → Implementation → Validation → Canary → Prod

WK4 |----------|----------|----------|----------|
     Monitoring → MF-001: Prep → Implementation → Validation → Canary → Prod
```

---

## 🎯 SUCCESS CRITERIA

### SF-001 Success
- ✅ CSS size -13% (552KB → 478KB)
- ✅ LCP -7% (2.8s → 2.4s+)
- ✅ Zero visual regressions
- ✅ Zero JS errors in console
- ✅ No 404s for CSS files

### OF-001 Success
- ✅ Parse time -20% (0.15s → 0.08s)
- ✅ CSS size -8% (1.8MB → 1.65MB)
- ✅ Specificity avg < 0.1.1
- ✅ LCP stable (no regression)

### AF-001 Success
- ✅ WCAG AAA 100% pass rate
- ✅ Axe DevTools 0 violations
- ✅ Lighthouse a11y 100
- ✅ Dark mode fully tested

### MF-001 Success
- ✅ Mobile Lighthouse 92+
- ✅ LCP (mobile) -20%
- ✅ CLS (mobile) < 0.05
- ✅ 3 real devices tested

---

## 🚨 RISK MITIGATION

### Risk: CSS Parsing Failures
- **Mitigation**: `bash tests/css-validation.sh` before every commit
- **Detection**: Automated validation in CI/CD
- **Rollback**: `git revert` < 3 min

### Risk: Visual Regressions
- **Mitigation**: Screenshot comparison on staging
- **Detection**: Manual visual review + automated tools
- **Rollback**: Feature flag disable + git revert

### Risk: Performance Regression
- **Mitigation**: Canary 10% traffic × 1h
- **Detection**: Lighthouse + RUM monitoring
- **Rollback**: Automatic if LCP > 5s or CLS > 0.1

### Risk: Accessibility Failures
- **Mitigation**: Axe DevTools check before merge
- **Detection**: Manual keyboard navigation test
- **Rollback**: Revert commit

---

## 📞 CONTACTS

| Role | Name | Contact |
|---|---|---|
| Lead | Jess | Slack @jess |
| Review | Dev Team | GitHub PRs |
| Monitor | QA | Lighthouse dashboard |
| Rollback | DevOps | < 5 min response |

---

## 📚 REFERÊNCIAS

- [IMPROVEMENT_FRAMEWORK.md](./IMPROVEMENT_FRAMEWORK.md) — Deployment process
- [IMPROVEMENTS_DASHBOARD_2026Q1.md](./IMPROVEMENTS_DASHBOARD_2026Q1.md) — Progress tracking
- [PERFORMANCE_BASELINE_2026-03-23.md](./PERFORMANCE_BASELINE_2026-03-23.md) — Current metrics
- [tests/css-validation.sh](./tests/css-validation.sh) — Validation script
- [README_IMPROVEMENTS.md](./README_IMPROVEMENTS.md) — Executive summary

---

**Next Step**: Approve timeline → Start SF-001 branch  
**Approval Checklist**:
- [ ] 4-week timeline acceptable
- [ ] Success metrics understood
- [ ] Risk mitigation approved
- [ ] Rollback procedure accepted
- [ ] Ready to start SF-001

**Questions?** Contact Jess (@jess on Slack)

---

*Plan created: 2026-03-23 15:50 UTC*  
*Version: 1.0*  
*Status: AWAITING APPROVAL*
