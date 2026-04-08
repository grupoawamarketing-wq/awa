# DS Visual Audit — AWA Motos
**Data:** 2026-04-07  
**Scope:** Auditoria multi-página do Design System (container width, tokens, gaps)

---

## Resumo Executivo

| Issue | Prioridade | Status |
|-------|-----------|--------|
| `.page-main` stuck at 1280px (desktop) | MAJOR | RESOLVIDO |
| `awa-core-variables.css` media queries conflitando | ROOT CAUSE | CORRIGIDO |
| `awa-bundle-phases.css` referência circular | CAUSA | CORRIGIDO |

---

## Root Cause Analysis — Container Width 1280px

### Cadeia de causa:

1. `awa-core-variables.css` continha media queries **fora de qualquer @layer** que sobrescreviam --awa-container-max:
   - @media (max-width:1399px) -> 1280px (overrides para viewports de laptop!)
   - @media (min-width:1400px) -> 1400px
   - @media (min-width:1600px) -> 1600px

2. Regras fora de @layer têm prioridade maior que regras dentro de @layer awa-core, então as media queries SEMPRE venciam os tokens do DS.

3. awa-bundle-phases.css tinha --awa-container-max: var(--awa-container) (referencia circular) tornando a variavel invalida.

4. awa-bundle-refinements.css usa max-width: var(--awa-container, 1280px) com fallback 1280px — quando --awa-container ficava vazio, o fallback era usado.

---

## Fix Aplicado (2026-04-07)

### awa-core-variables.unmin.css + awa-core-variables.css

Todas as declaracoes de container desktop alteradas para 1440px:

| Contexto | Antes | Depois |
|----------|-------|--------|
| @layer awa-core :root | 1280px | 1440px |
| @media (max-width:1399px) | 1280px | 1440px |
| @media (min-width:1400px,max-width:1599px) | 1400px | 1440px |
| @media (min-width:1600px) | 1600px | 1440px |
| @media (max-width:1199px) | 100% | 100% (mantido) |
| @media (max-width:767px) | 100% | 100% (mantido) |

---

## Resultados Pos-Fix

| Viewport | --awa-container-max | .page-main actual width |
|----------|--------------------|------------------------|
| 1280px | 1440px OK | 1280px (preenche viewport) |
| 1440px | 1440px OK | 1440px OK |
| 1920px | 1440px OK | 1440px (cap intencional) OK |

---

## Arquivos Modificados

| Arquivo | Mudanca |
|---------|---------|
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-core-variables.unmin.css | 12 propriedades: 1280/1400/1600px -> 1440px |
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-core-variables.css | Minificado regenerado |
| pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-core-variables.css | Sincronizado |
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-phases.css | Circular ref removida |
| app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-phases.unmin.css | Circular ref removida |
