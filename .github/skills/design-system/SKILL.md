---
name: design-system
description: "Skill do Design System AWA Motos. Use ao editar CSS, LESS, templates PHTML do tema ayo_home5_child, ou implementar melhorias visuais progressivas. Contém bundles, tokens, estrutura BEM do header e fluxo de deploy correto."
---

# AWA Motos — Design System Skill

## Tema Ativo
- **Path:** `app/design/frontend/AWA_Custom/ayo_home5_child`
- **Parent:** `Rokanthemes/ayo_home5` (nunca editar arquivos parente)
- **Stack:** Magento 2.4.8-p3 · PHP 8.4 · LESS compilado em bundles estáticos

---

## Estrutura de Bundles CSS

### Bundles editáveis (source em `web/css/`)
| Bundle | Arquivo unmin | Quando usar |
|--------|--------------|-------------|
| `awa-bundle-core.css` | `awa-bundle-core.unmin.css` | Header, nav, sticky, crítico above-fold |
| `awa-bundle-custom.css` | `awa-bundle-custom.unmin.css` | Overrides de módulos, PDP, PLP, checkout |
| `awa-bundle-site.css` | `awa-bundle-site.unmin.css` | Estilos de seções/páginas específicas |
| `awa-bundle-phases.css` | `awa-bundle-phases.unmin.css` | Melhorias visuais progressivas (fases) |
| `awa-b2b-gate-enterprise.unmin.css` | (direto) | Componentes B2B gate |

### Bundles NÃO editar
- `awa-bundle-vendor-libs.css` — bibliotecas externas
- `themes5.css` — tema pai (parente)
- `styles-l.css`, `styles-m.css` — Magento base
- `custom_default.css` — overrides do parente (vencer com `!important` quando necessário)

### LESS sources em `web/css/source/`
| Arquivo | Conteúdo |
|---------|---------|
| `_awa-variables.less` | Variáveis LESS do tema filho |
| `_awa-header-professional.less` | Estilos header BEM completo |
| `_awa-search-autocomplete.less` | Dropdown de busca |
| `_z-index.less` | Camadas z-index nomeadas |
| `_extend.less` | Extensões de base do LESS |

---

## Tokens CSS — `awa-core-variables.unmin.css`

### Cores
```css
--awa-red: #b73337
--awa-red-dark: #8e2629
--awa-red-light: #e8474c
--awa-white: #ffffff
--awa-black: #1a1a1a
--awa-gray-100: #f5f5f5
--awa-gray-200: #e5e7eb
--awa-gray-500: #6b7280
--awa-gray-700: #374151
--awa-color-border: #e5e5e5
--awa-text-on-dark: #ffffff
```

### Espaçamentos
```css
--awa-space-1: 4px   --awa-space-2: 8px   --awa-space-3: 12px
--awa-space-4: 16px  --awa-space-5: 20px  --awa-space-6: 24px
--awa-space-8: 32px  --awa-space-10: 40px --awa-space-12: 48px
```

### Tipografia
```css
--awa-text-xs: 11px   --awa-text-sm: 13px  --awa-text-base: 15px
--awa-text-lg: 17px   --awa-text-xl: 20px  --awa-text-2xl: 24px
--awa-weight-normal: 400  --awa-weight-medium: 500
--awa-weight-semibold: 600  --awa-weight-bold: 700
```

### Utilitários
```css
--awa-radius-sm: 8px   --awa-radius-md: 12px  --awa-radius-lg: 16px
--awa-shadow-sm: 0 1px 3px rgba(0,0,0,.10)
--awa-shadow-md: 0 4px 12px rgba(0,0,0,.12)
--awa-shadow-lg: 0 8px 24px rgba(0,0,0,.15)
--awa-transition-fast: 150ms ease
--awa-transition-base: 200ms ease
```

---

## Estrutura BEM do Header — `header.phtml`

```
<div class="top-header awa-utility-bar">
  <div class="awa-utility-bar__inner">
    <div class="top-bar-left">   ← links/tel esq
    <div class="top-bar-right">  ← locale/login dir

<header class="header awa-main-header">
  <div class="awa-main-header-inner-wrap">
    <div class="awa-main-header__inner wp-header">
      <div class="awa-header-brand">          ← logo
      <div class="awa-header-search-col top-search">  ← busca
      <div class="awa-header-right-col">      ← minicart + contato
        (oculto em checkout)

<div class="header-control header-nav awa-nav-bar">
  <div class="awa-nav-bar__inner">
    <div class="awa-header-categories menu_left_home1">  ← menu vertical
    <nav class="awa-header-primary-nav menu_primary"
         id="awa-primary-navigation">         ← nav principal
    <div class="awa-header-locale">           ← idioma/moeda
```

### JS: mobile state
- Classe `is-awa-mobile-open` adicionada em `#awa-primary-navigation` pelo JS do header
- Controla visibilidade do menu em mobile

---

## Princípios de CSS

1. **Tokens sempre** — usar `var(--awa-red)`, nunca `#b73337` hardcoded
2. **Escopo obrigatório** — `body .page-wrapper [seletor]` (especificidade Magento)
3. **`!important` controlado** — só para vencer `custom_default.css`
4. **BEM para novos componentes** — `.awa-[bloco]__[elemento]--[modificador]`
5. **Não editar vendor/parente** — apenas `ayo_home5_child`

---

## Fluxo de Deploy por Tipo de Mudança

### CSS / LESS (qualquer bundle)
```bash
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
sudo -u www-data php bin/magento cache:flush
```
> Obrigatório — gera novo hash de versão, força reload no browser

### Apenas template `.phtml` (sem CSS)
```bash
sudo -u www-data php bin/magento cache:clean block_html full_page
```

### Módulo PHP / DI (plugin, observer, model)
```bash
sudo -u www-data php bin/magento setup:di:compile
sudo systemctl restart php8.4-fpm
sudo -u www-data php bin/magento cache:flush
```

### Layout XML
```bash
sudo -u www-data php bin/magento cache:clean layout block_html full_page
```

---

## Referência: 10 Fases do Design System

As fases de melhoria progressiva estão documentadas em `DESIGN_SYSTEM_PROMPT.md` na raiz do projeto. Cada fase é independente e inclui escopo, seletores e bloco DEPLOY.

| Fase | Escopo |
|------|--------|
| 1 | Sistema de Botões |
| 2 | Formulários |
| 3 | Tipografia e Hierarquia |
| 4 | Container, Grid, Espaçamentos |
| 5 | Cards de Produto (PLP) |
| 6 | Página de Produto (PDP) |
| 7 | Rodapé |
| 8 | Header Avançado |
| 9 | Checkout |
| 10 | Login / Cadastro / Conta |

---

## Não Quebrar

- `awa-utility-bar` / `awa-main-header` / `awa-nav-bar` (header BEM implementado)
- Sticky header JS (`is-awa-sticky` classe no `<header>`)
- B2B gate: `addtocart.phtml` (condition `$b2bGateState !== 'guest'`) e `b2b_secondary_ctas.phtml`
- Preload hints: `awa-head-preload.phtml` emite `<link rel="preload">` para bundles async
