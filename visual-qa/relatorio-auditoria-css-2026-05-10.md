# Relatório Visual QA — Auditoria CSS AWA Motos
**Data:** 2026-05-10  
**Tema:** `AWA_Custom/ayo_home5_child`  
**Escopo:** CSS estático (`web/css/*.css`), cascade order, orphans, Brotli

---

## Sumário Executivo

| Severidade | Qtd | Área |
|-----------|-----|------|
| 🔴 CRÍTICO | 2 | Brotli ausente em CSS ativos + conflito cascade categoria |
| 🟠 MAJOR | 5 | Orphans mortos, cascade conflicts, .bak em pub/static |
| 🟡 MENOR | 4 | Tokens não-semânticos, max-height, .bak cleanup |

---

## Correções Já Aplicadas (neste commit)

- ✅ Gerado `.br` para 7 CSS ativos sem Brotli
- ✅ Removidos 5 `.br.stale` de `pub/static` e `web/css/`
- ✅ Removido `awa-visual-bugfix.css.bak-v41` (615KB) de `pub/static`
- ✅ Removidos 3 `.bak` restantes em `pub/static`

---

## 🔴 CRÍTICO

### C1 — 7 CSS Ativos sem Compressão Brotli (CORRIGIDO)

**Nginx usa `brotli_static on`** — sem `.br`, browsers modernos recebiam CSS descomprimido.

| Arquivo | Status |
|---------|--------|
| `awa-super-home.css` | ✅ `.br` gerado |
| `awa-bestseller-fixes.css` | ✅ `.br` gerado |
| `awa-card-image-hero.css` | ✅ `.br` gerado |
| `awa-home-gap-fix.css` | ✅ `.br` gerado |
| `awa-vertical-menu-desktop-final.css` | ✅ `.br` gerado |
| `awa-plp-final-polish.css` | ✅ `.br` gerado |
| `awa-layout-canonical.css` | ✅ `.br` gerado |

---

### C2 — Conflito de Cascade: `.awa-category-carousel__item` (CLS)

**Problema:** A largura do carrossel de categorias tem valores conflitantes:

| Arquivo | `width` | `min-height` |
|---------|---------|-------------|
| `awa-super-global.css` | `calc((100% - 4*24px)/5.6)` | `210px` |
| `awa-layout-bundle.css` | `140px !important` | `180px !important` |
| `awa-layout-fixed.css` | `140px !important` | `180px !important` |

`awa-layout-bundle.css` carrega DEPOIS e vence com `!important`. O `calc()` do `super-global` é aplicado na primeira pintura causando micro-reflow (CLS).

**Correção pendente:** Remover bloco de `awa-super-global.css` e consolidar em token:
```css
/* awa-design-tokens.css */
:root { --awa-category-card-w: 140px; --awa-category-card-min-h: 180px; }
```

---

## 🟠 MAJOR

### M1 — Breadcrumb: 4 definições conflitantes (cascade redundante)

`body .page-wrapper .breadcrumbs .item` redefinido em 4 arquivos:

| Arquivo | `display` | `color` |
|---------|-----------|---------|
| `awa-super-global.css` | `inline-flex !important` | `var(--awa-sg-c12)` |
| `awa-layout-bundle.css` | `flex !important` | `var(--awa-lc-c29)` |
| `awa-layout-canonical.css` | `flex !important` | `var(--awa-lc-c29)` |
| `awa-visual-bugfix.css` | `inline-flex` | `var(--awa-gray-500)` |

`flex` vence (carregado por último), mas `inline-flex` seria mais correto para breadcrumbs.

---

### M2 — CSS Mortos em `web/css/` (nunca carregados em produção)

| Arquivo | Status | Motivo |
|---------|--------|--------|
| `awa-ux-responsiveness.css` | **DEAD** | Mergeado em `awa-layout-bundle.css` L7555 |
| `awa-clean-ui-final.css` | **DEAD** | Mergeado em `awa-layout-bundle.css` L6176 |
| `awa-carousel-modern.css` | **DEAD** | `<remove>` explícito em `cms_index_index.xml` |
| `awa-home-carousel-standard.css` | **DEAD** | `<remove>` explícito em `cms_index_index.xml` |
| `awa-icon-fonts-bundle.css` | **DEAD** | Apenas em `.bak3` phtml (nunca registrado) |
| `awa-grid-system.css` | **DEAD** | Apenas em `.bak` phtmls e comentários |
| `awa-visual-consistency-2026-05-07.css` | **POSSIVELMENTE DEAD** | Phtml não registrado em layout XML |
| `awa-deep-audit-2026-05-08.css` | **POSSIVELMENTE DEAD** | Phtml não registrado em layout XML |
| `awa-visual-audit-final-2026-05-03.css` | **POSSIVELMENTE DEAD** | Phtmls não registrados em layout XML |

**Arquivos legítimos (carregados):**
| Arquivo | Mecanismo |
|---------|-----------|
| `awa-layout-fixed.css` | B2B layout XML |
| `awa-header-premium.css` | Mergeado em `_awa-consolidated.less` |
| `awa-layout-canonical.css` | `awa-styles-l-last.phtml` (default.xml) |
| `awa-critical-fold.css` | Inline via `awa-critical-inline.phtml` |
| `awa-audit-bundle.css` | `awa-audit-bundle-css.phtml` (default.xml) |
| `awa-visual-polish-r2.css` | `awa-audit-bundle-css.phtml` (default.xml) |

---

### M3 — 402 Conflitos de Cascade (análise automatizada, 7 arquivos principais)

Top conflitos:
1. `.awa-category-carousel__item` — width/min-height (veja C2)
2. `body .page-wrapper .breadcrumbs .item*` — 8 propriedades (veja M1)
3. `.awa-b2b-sku__label/.value` — color com tokens aliases diferentes
4. `::-webkit-scrollbar-thumb` — background com 3 tokens aliases
5. `body .page-wrapper .breadcrumbs .items` — gap: 4px vs 0

---

### M4 — 60+ `max-height: [px] !important` — Risco de Clipagem

Por arquivo: `awa-layout-bundle.css` (14), `awa-bundle-refinements.css` (12), `awa-super-global.css` (9), outros (25+).

Valores fixos em ícones/labels podem clipar conteúdo em DPI alto ou após zoom (WCAG SC 1.4.4).

---

### M5 — `.bak` em pub/static (CORRIGIDO)

- ✅ Removido `awa-visual-bugfix.css.bak-v41` (615KB)
- ✅ Removidos `awa-design-tokens.css.bak-20260507`, `awa-visual-qa-*.bak-*`

---

## 🟡 MENOR / REDUNDÂNCIA

### R1 — 152 Tokens CSS Não-Semânticos

| Prefixo | Qtd | Arquivo origem |
|---------|-----|----------------|
| `--awa-sg-c*` | 64 | `awa-super-global.css` |
| `--awa-lc-c*` | 44 | `awa-layout-bundle.css` |
| `--awa-vbf-c*` | 44 | `awa-visual-bugfix.css` |

Alias sem semântica, dificultam manutenção. Migrar gradualmente para tokens semânticos.

### R2 — 4 `.bak` em `web/css/` (source)
```
awa-design-tokens.css.bak-20260507
awa-visual-bugfix.css.bak-v41
awa-visual-qa-category.css.bak-20260508
awa-visual-qa-product.css.bak-20260508
```
Podem ser removidos — Git já versiona o histórico.

### R3 — Scrollbar com 3 tokens divergentes

`::-webkit-scrollbar-thumb { background }` definida em 3 arquivos com aliases diferentes. Consolidar em `awa-bundle-refinements.css` com token semântico.

### R4 — PHMLs não-registrados (possível dead code)
`awa-visual-consistency-css.phtml`, `awa-deep-audit-css.phtml`, `awa-visual-audit-final-css.phtml` e `-v2.phtml` não aparecem em nenhum layout XML.

---

## Mapa de Cascade Order (confirmado)

```
1.  awa-super-global.css         (preload async)
2.  awa-super-home.css           (preload async — homepage)
3.  awa-bundle-refinements.css   (preload async)
4.  awa-design-tokens.css        (preload async)
5.  awa-visual-bugfix.css        (preload async, v=82)
6.  css/styles-m.css             (preload async — reloaded)
7.  css/styles-l.css             (preload async — reloaded)
8.  awa-third-party-bundle.css   (preload async)
9.  awa-interaction-widgets.css  (preload async)
10. css/themes.css               (preload async)
11. awa-layout-bundle.css        (preload async)
12. awa-carousel-bundle.css      (preload async)
13. awa-vertical-menu-modern.css (preload async)
14. awa-bestseller-fixes.css     (preload async)
15. awa-card-image-hero.css      (preload async)
16. awa-home-gap-fix.css         (preload async)
17. awa-vertical-menu-desktop-final.css (preload async)
18. awa-plp-final-polish.css     (preload async)
19. awa-critical-fold.css        [INLINE via awa-critical-inline.phtml]
20. awa-layout-canonical.css     (awa-styles-l-last.phtml)
21. awa-audit-bundle.css         (awa-audit-bundle-css.phtml)
22. awa-visual-polish-r2.css     (awa-audit-bundle-css.phtml — LAST/MAX PRIORITY)
23. awa-layout-fixed.css         [B2B pages only, layout XML]
```

---

## Plano de Ação

| Prioridade | Ação | Esforço |
|-----------|------|---------|
| 🔴 DONE | Gerar .br para 7 CSS | ✅ feito |
| 🔴 DONE | Limpar .bak/.stale pub/static | ✅ feito |
| 🟠 NEXT | Remover 6 CSS definitivamente mortos | 30min |
| 🟠 NEXT | Consolidar breadcrumb CSS | 1h |
| 🟠 NEXT | Resolver conflito .category-carousel | 30min |
| 🟡 LATER | Migrar 152 tokens para semânticos | 1-2 dias |
| 🟡 LATER | Auditar max-height fixo (WCAG) | 2h |

*Próximo passo sugerido: browser testing com Playwright/Firefox para validar visualmente.*
