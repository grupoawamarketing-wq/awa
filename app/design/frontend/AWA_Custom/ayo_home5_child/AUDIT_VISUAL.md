# AUDIT_VISUAL.md — Auditoria de Design Tokens
**AWA Motos — ayo_home5_child theme**
**Data:** 2026-03-30 | **Escopo:** LESS source tokens

---

## 1. Diagnóstico: Fontes de Tokens LESS

O tema acumulou **3 arquivos de token LESS** com valores divergentes,
mais **2 arquivos CSS** com custom properties hardcoded (sem referência a variáveis LESS).

| Arquivo | Papel declarado | Problema |
|---|---|---|
| `source/_variables.less` | Overrides Magento Blank | Redefine tokens AWA com valores diferentes de `_awa-variables.less` |
| `source/_awa-variables.less` | "Fonte canônica" | Nunca importado na chain LESS do Magento (era órfão) |
| `source/_awa-tokens.less` | "Compat layer" | Só fazia `@import '_awa-variables'` — redundante |
| `source/_extend.less` (`:root {}`) | Expõe CSS custom props | Valores hardcoded — não referenciavam variáveis LESS |
| `web/css/layers/tokens.css` | Bridge CSS Layers | Fallbacks descasados do token canônico |
| `web/css/awa-design-tokens.css` | (deprecated) | Auto-declarado deprecated; valores `--awa-gap-md: 16px` conflitam com `@awa-gap-md: 12px` |

---

## 2. Tabela de Conflitos: Valores por Arquivo

> **Negrito** = valor canônico resolvido e implementado na Fase 1.

### 2.1 Paleta de Cores

| Token LESS | `_variables.less` (antes) | `_awa-variables.less` | `_extend.less` CSS var | Canônico |
|---|---|---|---|---|
| `@awa-color-bg-soft` | `#ffffff` ❌ | `#f7f7f7` ✓ | `#f7f7f7` ✓ | **`#f7f7f7`** |
| Demais cores | Idênticos | Idênticos | Idênticos | ✓ sem conflito |

### 2.2 Border Radius

| Token LESS | `_variables.less` (antes) | `_awa-variables.less` | `_extend.less` CSS var | Canônico |
|---|---|---|---|---|
| `@awa-radius-sm` | `6px` ❌ | `8px` ✓ | `--awa-radius-sm: 8px` ✓ | **`8px`** |
| `@awa-radius-md` | `10px` ✓ | `10px` ✓ | `--awa-radius-md: 10px` ✓ | **`10px`** |

> **Efeito colateral resolvido:** O seletor de input em `_extend.less` usava `border-radius: 6px` hardcoded.
> Após Fase 1, passa a usar `@awa-radius-sm` = 8px. Mudança visual mínima, justificada.

### 2.3 Sombras

| Token LESS | `_variables.less` (antes) | `_awa-variables.less` (antes) | Canônico |
|---|---|---|---|
| `@awa-shadow-card` | `0 8px 24px rgba(17,24,39,.06)` ✓ | `0 2px 10px rgba(0,0,0,.06)` ❌ | **`0 8px 24px rgba(17,24,39,.06)`** |
| `@awa-shadow-card-hover` | `0 12px 32px rgba(17,24,39,.10)` ✓ | `0 6px 18px rgba(0,0,0,.10)` ❌ | **`0 12px 32px rgba(17,24,39,.10)`** |

> Nota: `_extend.less` linha ~44 tinha `--awa-shadow-card: var(--awa-shadow-sm, 0 1px 3px …)` —
> referenciava `--awa-shadow-sm` indefinida. Corrigido na Fase 1 para usar LESS variable.

### 2.4 Escala de Espaçamento

A divergência mais significativa: duas escalas incompatíveis coexistiam.

| Passo | `_variables.less` `@awa-space-*` | `_awa-variables.less` `@space-*` (era órfão) | `_extend.less` `--awa-space-*` | Canônico |
|---|---|---|---|---|
| 1 | 4px ✓ | 4px ✓ | 4px ✓ | **4px** |
| 2 | 8px ✓ | 8px ✓ | 8px ✓ | **8px** |
| 3 | 12px ✓ | 12px ✓ | 12px ✓ | **12px** |
| 4 | 16px ✓ | 16px ✓ | 16px ✓ | **16px** |
| 5 | 20px ✓ | **24px ❌** | 20px ✓ | **20px** |
| 6 | 24px ✓ | **32px ❌** | 24px ✓ | **24px** |
| 7 | 32px ✓ | **40px ❌** | 32px ✓ | **32px** |
| 8 | 40px ✓ | **48px ❌** | 40px ✓ | **40px** |
| 9 | 48px ✓ | ausente | 48px ✓ | **48px** |
| 10 | 64px ✓ | ausente | 64px ✓ | **64px** |

> `_awa-variables.less` estava deslocado 1 passo no sentido de valores maiores (steps 5–8).
> Como `_awa-variables.less` era **órfão** (não importado pelo compilador), o compilador
> sempre usou os valores de `_variables.less`. Após Fase 1, `_awa-variables.less` tem a
> escala corrigida e é a única fonte.

### 2.5 Gap Scale

| Token | `_variables.less` | `_awa-variables.less` | `awa-design-tokens.css` (deprecated) | Canônico |
|---|---|---|---|---|
| `@awa-gap-xs` | ausente | 4px | ausente | **4px** |
| `@awa-gap-sm` | ausente | 8px | 8px ✓ | **8px** |
| `@awa-gap-md` | ausente | 12px ✓ | **16px ❌** | **12px** |
| `@awa-gap-lg` | ausente | 16px | ausente | **16px** |
| `@awa-gap-xl` | ausente | 24px | 24px ✓ | **24px** |
| `@awa-gap-2xl` | ausente | 32px | ausente | **32px** |

> `--awa-gap-*` CSS vars **não existiam no browser** antes da Fase 1 (awa-design-tokens.css
> está deprecated e não é carregado). Adicionadas em `_extend.less` na Fase 1.

---

## 3. Problema: CSS Custom Props Hardcoded em `_extend.less`

Antes da Fase 1, o bloco `:root {}` de `_extend.less` continha 17 valores hardcoded
que deveriam referenciar variáveis LESS:

```
--awa-color-primary: #b73337;          ← deveria ser @awa-color-primary
--awa-radius-sm: 8px;                  ← deveria ser @awa-radius-sm
--awa-shadow-card: var(--awa-shadow-sm, 0 1px 3px …); ← var indefinida
... (total: 17 valores primitivos hardcoded)
```

**Impacto:** mudança no `_awa-variables.less` não refletia automaticamente
nos CSS custom properties expostos ao browser.

**Resolução Fase 1:** Todos os valores primitivos do `:root {}` agora usam
interpolação LESS (`@awa-color-primary`, `@awa-space-1`, etc.).

---

## 4. Problema: Cores Hardcoded nos Source LESS

Existem **69 ocorrências** de cores primitivas hardcoded nos arquivos LESS de `source/`:
- `#b73337` — cor primária
- `#8e2629` — cor primária dark
- `rgba(183, 51, 55, …)` — variações de opacidade da cor primária

**Arquivos afetados:**
- `_awa-header-professional.less` (861 linhas)
- `_awa-search-professional.less`
- `_awa-ux-audit-fixes.less` (811 linhas)
- `_awa-b2b-phases4-7.less` (267 linhas)

**Status:** Documentado. **Não corrigido na Fase 1** (risco de regressão visual;
deve ser feito com testes visuais por página).

**Recomendação:** substituir progressivamente por `@awa-color-primary`,
`@awa-color-primary-dark` e funções LESS (`fade(@awa-color-primary, 8%)`).

---

## 5. Naming Inconsistency Map

| Problema | Antes | Depois (Fase 1) |
|---|---|---|
| Dois arquivos de token | `_variables.less` + `_awa-variables.less` | Apenas `_awa-variables.less` |
| Prefixo duplo | `@space-*` e `@awa-space-*` duplicados | `@space-*` internos + `@awa-space-*` API pública |
| Aliases B2B redundantes | `@b2b-primary: @awa-color-primary` (e 13 outros) | Removidos |
| `_awa-tokens.less` redundante | Apenas `@import '_awa-variables'` | Arquivo deletado |
| CSS vars hardcoded | `:root { --awa-color-primary: #b73337; }` | `:root { --awa-color-primary: @awa-color-primary; }` |
| `--awa-gap-*` indefinidas no browser | Não existiam | Definidas via LESS vars em `_extend.less` |

---

## 6. Ações Realizadas (Fase 1 + Fase 2 parcial + Fase 20)

- [x] `_awa-variables.less` — fonte canônica única; paleta reduzida a 6 tokens de Brand Identity
- [x] `_variables.less` — refatorado como shim Magento; importa `_awa-variables.less`
- [x] `_awa-tokens.less` — deletado (conteúdo absorvido por `_awa-variables.less`)
- [x] `_extend.less` — `:root {}` usa interpolação LESS; componentes usam `@awa-*` vars e `fade()`
- [x] `.bak` / `.backup` files — 13 arquivos de backup deletados do diretório CSS
- [x] `awa-design-tokens.css` — deletado (auto-declarado deprecated)
- [x] **55 ocorrências de cores hardcoded substituídas** nos 4 LESS source files:
  - `_awa-header-professional.less` — `rgba(183,51,55,X)` → `fade(@awa-color-primary, X%)`; fallbacks CSS var removidos
  - `_awa-search-professional.less` — idem; `linear-gradient` atualizado para CSS vars
  - `_awa-ux-audit-fixes.less` — `#b73337 !important` → `@awa-color-primary !important`
  - `_awa-b2b-phases4-7.less` — fallbacks e rgba convertidos
- [x] `@import (once) '_awa-variables'` adicionado no topo dos 4 arquivos acima

### Fase 20 — Semantic Tokens + Estados + Acessibilidade (2026-03-30)

- [x] `_awa-variables.less` — expandido com:
  - Neutral/gray scale de 9 passos (`@awa-neutral-50` → `@awa-neutral-900`, Slate-based)
  - State colors: success (`#16a34a`), warning (`#d97706`), error (alias `@awa-color-primary`), info (`#2563eb`)
  - Typography weights: `@awa-weight-normal/medium/semibold/bold`
  - Text scale aliases: `@awa-text-xs` → `@awa-text-2xl`
  - Radius estendido: `@awa-radius-2xs` (4px), `@awa-radius-xs` (6px), `@awa-radius-lg` (16px), `@awa-radius-full` (pill)
  - Control heights: `@awa-control-height` (44px WCAG), sm (36px), lg (54px)
  - Surface tokens: `@awa-color-white`, `@awa-color-border`, `@awa-color-bg-soft`
  - Focus ring: `@awa-focus-ring`, `@awa-focus-ring-offset`
- [x] `_extend.less` — expõe todos os novos tokens como CSS custom properties em `:root {}`
  - Inclui aliases `--awa-gray-*` compatíveis com `awa-bundle-accessibility-af001.css`
  - Inclui `--awa-white`, `--awa-weight-*`, `--awa-text-*`, `--awa-control-height*` (eram indefinidos)
- [x] `layers/tokens.css` — fallback `--awa-layer-radius-sm` corrigido de `6px` → `8px` (canônico)
- [x] `source/_awa-phase20-states.less` — novo partial importado em `_extend.less`:
  - Skip-to-content link (WCAG 2.4.1)
  - Sistema de focus rings global (WCAG 2.4.11/2.4.12)
  - Form validation states (error border + shadow + mensagem inline com ícone)
  - Flash messages modernizadas (success, error, warning, notice/info) com border-left 4px
  - Labels tipográficos melhorados + asterisco de campo obrigatório
  - `prefers-reduced-motion` global (WCAG 2.3.3)
  - Skeleton loader refinado com shimmer animation
  - Link underlines em corpo de texto (WCAG 1.4.1)

## 7. Fase 2 — CSS Cleanup Concluída ✅

- [x] `layers/` orphaned deletados: `base.css`, `components.css`, `legacy-bridge.css`,
      `utilities.css`, `themeoption-safety.css`, `tokens.css`, `pages/home.css`, `pdp.css`, `plp-search.css`
      — confirmados sem referência em layout XML, PHTML ou CSS @import.
      Preservados: `layers/pages/account-b2b.css` e `cart-checkout.css` (ativos em layout XML).
- [x] `awa-bundle-mobilefast-mf001.css` e `awa-bundle-optimization-of001.css` deletados
- [x] **~78 arquivos CSS removidos** (Merged + Orphaned + Deprecated + Backups + Layers)
- [x] **Resultado final: 162 → 46 arquivos CSS (-72%)** — zero impacto em produção
- [x] LESS chain validada sem erros (`npx lessc _extend.less`)

## 8. Pendências Remanescentes

- [ ] Migrar valores hardcoded em `_awa-ux-audit-fixes.less` para `var(--awa-neutral-*)` e
      `var(--awa-color-*)` usando os tokens adicionados na Fase 20
      (ex: `#475569` → `var(--awa-neutral-600)`, `#e2e8f0` → `var(--awa-neutral-200)`)
- [ ] Executar `php bin/magento setup:static-content:deploy pt_BR en_US -f` para compilar
      LESS das Fases 1 + 20 e validar em produção
