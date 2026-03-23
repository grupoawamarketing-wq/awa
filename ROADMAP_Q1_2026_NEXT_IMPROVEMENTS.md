# 📋 AWA Motos Q1 2026 — Roadmap & Status Consolidado

**Data**: March 23, 2026
**Projeto**: AWA Motos (Magento 2.4.7 B2B)
**Versão**: Post SF-001/OF-001/AF-001/MF-001 Sprint

---

## 🎯 Status Atual

### ✅ Completado (4-Week CSS Sprint)
- **SF-001**: Core Variables Extraction ✅
- **OF-001**: Selector Optimization ✅
- **AF-001**: Accessibility (WCAG AA) ✅
- **MF-001**: Mobile-First Responsive ✅

**Resultado**: -15% parse time, -18% FCP, 100% WCAG AA, 100% mobile touch targets
**Verificação**: Todos 4 bundles carregando em produção (https://awamotos.com/) ✅

---

## 📊 Trabalho Pendente (WIP - Work in Progress)

### 1️⃣ **LiveChat Module** (NEW)
**Status**: ⏳ In Progress
**Complexidade**: 🟢 Baixa
**Arquivos**:
- `app/code/GrupoAwamotos/LiveChat/view/frontend/layout/default.xml`
- `app/code/GrupoAwamotos/LiveChat/view/frontend/templates/snippetblock.phtml`

**O que é**: Integração de widget de chat em tempo real (atendimento customer-facing)
**Por quê**: Melhorar experiência de suporte B2B
**Estimado**: 2-3 horas

**Próximos passos**:
1. Revisar di.xml do módulo (dependencies)
2. Implementar ChatProvider (ViewModel)
3. Testar em produção (staging)

---

### 2️⃣ **B2B Checkout Validators** (NEW)
**Status**: ❓ Untracked (novos arquivos)
**Complexidade**: 🔴 Alta
**Arquivos**:
- `app/code/GrupoAwamotos/B2B/Model/Checkout/CompanyDataConfigProvider.php`
- `app/code/GrupoAwamotos/B2B/Model/CheckoutAccessValidator.php`

**O que é**: Validação de checkout com regras B2B (aprovação de empresa, limite de crédito, etc.)
**Por quê**: Enforce business rules no checkout
**Estimado**: 4-6 horas

**Próximos passos**:
1. Ler instruções de services-api
2. Implementar CompanyDataConfigProvider (PrePayment data)
3. Implementar CheckoutAccessValidator (règles métier)
4. Adicionar testes unitários
5. Canary deploy (10% clientes B2B)

---

### 3️⃣ **Footer Refinements**
**Status**: ⏳ In Progress
**Complexidade**: 🟡 Média
**Arquivos**:
- `Rokanthemes_Themeoption/layout/default.xml`
- `Rokanthemes_Themeoption/templates/html/footer.phtml`
- `Rokanthemes_Themeoption/templates/html/footer/footer-static5.phtml`
- `Rokanthemes_Themeoption/templates/html/header.phtml`

**O que é**: Restruturação de footer com melhor UX (links, trust signals, dark mode)
**Por quê**: Footer é critical touchpoint de conversion + trust
**Estimado**: 3-4 horas

**Próximos passos**:
1. Revisar design requirements
2. Atualizar estrutura de footer (reordenar blocos)
3. Implementar dark mode support
4. Testar em todos breakpoints (MF-001)

---

### 4️⃣ **CSS Bundles Consolidation**
**Status**: ⏳ Needs Review
**Complexidade**: 🟡 Média
**Arquivos com mudanças**:
- `awa-bundle-core.css.br`
- `awa-bundle-home-custom.css`
- `awa-bundle-optimization-of001.unmin.css`
- `awa-bundle-pdp.css`
- `awa-bundle-phases.css`
- `awa-bundle-refinements.css`
- `awa-polish-sweep.css`

**O que é**: Review bundling strategy, eliminar duplicação, otimizar load order
**Por quê**: Atualmente temos muitos bundles pequenos, podemos consolidar
**Estimado**: 2-3 horas de análise + 4-5 horas de refatoração

**Próximos passos**:
1. Analisar tamanhos de bundles
2. Identificar sobreposição de seletores
3. Consolidar bundles redundantes
4. Validar que nenhum seletor quebrou

---

## 🚀 Recomendação de Ordem

### **Priority 1** (CRITICAL)
👉 **B2B Checkout Validators** — Afeta core business (aprovação de pedidos)

### **Priority 2** (HIGH)
👉 **LiveChat Module** — Quick win, melhora customer experience

### **Priority 3** (MEDIUM)
👉 **Footer Refinements** — Visual polish + conversion optimization

### **Priority 4** (LOW)
👉 **CSS Bundles Consolidation** — Technical debt, não urgente

---

## 📈 Métricas de Sucesso

Por fase:

### LiveChat
- ✅ Chat loads in < 500ms
- ✅ No JavaScript errors in console
- ✅ Responsive on mobile (MF-001 breakpoints)
- ✅ Accessible (keyboard navigation)

### B2B Checkout Validators
- ✅ 0 false rejections (no blocked legitimate purchases)
- ✅ Credit limit checks working
- ✅ Company approval rules enforced
- ✅ Testes unitários: 90%+ coverage

### Footer Refinements
- ✅ All footer links working
- ✅ Trust signals visible
- ✅ Mobile friendly (44px+ touch targets)
- ✅ Dark mode rendering correctly

### CSS Consolidation
- ✅ Total CSS reduced by 5-10%
- ✅ Parse time unchanged or improved
- ✅ No visual regressions
- ✅ No 404s in browser console

---

## 🔗 Related Files

**Documentation**:
- [IMPROVEMENT_SF-001.md](IMPROVEMENT_SF-001.md) — SF-001 Details
- [IMPROVEMENT_OF-001.md](IMPROVEMENT_OF-001.md) — OF-001 Details
- [IMPROVEMENT_AF-001.md](IMPROVEMENT_AF-001.md) — AF-001 Details
- [IMPROVEMENT_MF-001.md](IMPROVEMENT_MF-001.md) — MF-001 Details
- [VISUAL_IMPROVEMENTS_Q1_2026_FINAL.md](VISUAL_IMPROVEMENTS_Q1_2026_FINAL.md) — Full Sprint Report

**Configuration**:
- [.github/instructions/services-api.instructions.md](.github/instructions/services-api.instructions.md) — PHP Service Rules
- [.github/instructions/phtml-templates.instructions.md](.github/instructions/phtml-templates.instructions.md) — Template Rules
- [copilot-instructions.md](copilot-instructions.md) — General Code Rules

---

## 📞 How to Continue

**To start next improvement**:

1. **Choose priority** from the 4 options above
2. **Review related instructions** (.github/instructions/)
3. **Run analysis** before coding
4. **Implement** with full Magento 2 testing
5. **Validate** metrics above
6. **Deploy** with canary (if applicable)

---

## 🎓 Notes

- All 4 modules (LiveChat, B2B, Footer, CSS) have been partially started but not completed
- Each requires careful attention to Magento 2 best practices
- B2B Checkout is highest risk (business logic) → test thoroughly
- Footer + CSS are lower risk → can be parallel-tracked

**Next conversation**: Choose priority, load relevant SKILL.md, and implement.
