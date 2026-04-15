---
description: "AWA DS v2 — 01: Tokens Canônicos: audita e consolida CSS custom properties, elimina duplicidades e hardcodes"
agent: "agent"
tools:
  - codebase
  - edit
  - execute
  - changes

---

> **Skill obrigatória:** `design-system` (bundles, tokens, deploy).

Você é o guardião do design system AWA Motos. Audite, consolide e garanta que o sistema de tokens CSS seja a **fonte única de verdade**.

---

## Diagnóstico Inicial

```bash
# Cores hardcoded nos bundles
grep -rn "#b73337\|#8e2629\|#333333\|#666666\|#e5e5e5\|#f7f7f7" \
  pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/*.unmin.css \
  | grep -v "^Binary\|awa-core-variables" | wc -l

# Total de tokens definidos
grep "^\s*--awa-" \
  pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-core-variables.unmin.css | wc -l
```

---

## Dois Namespaces Ativos

| Namespace | Arquivo | Uso recomendado |
|-----------|---------|-----------------|
| `--awa-red` / `--awa-gray-*` | `awa-core-variables.unmin.css` | bundles legados |
| `--awa-primary` / `--awa-font-size-*` | `_tokens.less` | LESS novos |
| `@awa-*` | `_awa-variables.less` | arquivos .less novos |

---

## Tokens Canônicos Obrigatórios

Garanta que `awa-core-variables.unmin.css` define TODOS estes tokens:

```css
:root {
  /* CORES PRIMÁRIAS */
  --awa-red:          #b73337;
  --awa-red-dark:     #8e2629;
  --awa-red-light:    color-mix(in srgb, var(--awa-red) 16%, transparent);
  --awa-primary:      var(--awa-red);  /* alias para novos componentes */
  --awa-primary-hover: var(--awa-red-dark);

  /* ESCALA CINZA */
  --awa-white:        #ffffff;
  --awa-gray-50:      #f7f7f7;
  --awa-gray-200:     #e5e5e5;
  --awa-gray-250:     #cccccc;
  --awa-gray-450:     #999999;
  --awa-gray-500:     #666666;
  --awa-gray-700:     #333333;
  --awa-gray-950:     #111111;

  /* ESPAÇAMENTO (escala 4/8/16) */
  --awa-space-1: 4px;  --awa-space-2: 8px;   --awa-space-3: 12px;
  --awa-space-4: 16px; --awa-space-5: 20px;  --awa-space-6: 24px;
  --awa-space-7: 32px; --awa-space-8: 40px;  --awa-space-9: 48px;
  --awa-space-10: 64px;

  /* TIPOGRAFIA */
  --awa-text-xs:   11px;  --awa-text-sm:   13px;
  --awa-text-base: 14px;  --awa-text-md:   15px;
  --awa-text-lg:   18px;  --awa-text-xl:   24px;

  /* BORDER RADIUS */
  --awa-radius-xs: 4px;  --awa-radius-sm: 8px;
  --awa-radius-md: 10px; --awa-radius-lg: 16px;
  --awa-radius-full: 9999px;

  /* SOMBRAS */
  --awa-shadow-1: 0 2px 8px rgba(0,0,0,0.08);
  --awa-shadow-2: 0 8px 24px rgba(17,24,39,0.10);
  --awa-shadow-3: 0 16px 48px rgba(17,24,39,0.16);

  /* TRANSIÇÕES */
  --awa-transition-fast: 120ms ease;
  --awa-transition:      200ms ease;
  --awa-transition-slow: 350ms ease;

  /* LAYOUT */
  --awa-container:         1280px;
  --awa-container-padding: 20px;
  --awa-sidebar-width:     240px;

  /* SEMÂNTICOS */
  --awa-success:    #2d7a3a;  --awa-success-bg: #e8f5e9;
  --awa-warning:    #b87a00;  --awa-warning-bg: #fff8e1;
  --awa-danger:     #dc2626;  --awa-danger-bg:  #fdecea;
  --awa-info:       #0ea5e9;  --awa-info-bg:    #e3f2fd;

  /* Z-INDEX */
  --awa-z-dropdown: 100; --awa-z-sticky: 200;
  --awa-z-overlay:  300; --awa-z-modal:  400; --awa-z-toast: 500;
}
```

---

## Substituições Obrigatórias

| Hardcode | Token |
|----------|-------|
| `#b73337` | `var(--awa-red)` |
| `#8e2629` | `var(--awa-red-dark)` |
| `#333` / `#333333` | `var(--awa-gray-700)` |
| `#666` / `#666666` | `var(--awa-gray-500)` |
| `#999` / `#999999` | `var(--awa-gray-450)` |
| `#e5e5e5` | `var(--awa-gray-200)` |
| `#f7f7f7` | `var(--awa-gray-50)` |
| `#fff` / `#ffffff` | `var(--awa-white)` |
| `padding: 16px` | `padding: var(--awa-space-4)` |
| `padding: 24px` | `padding: var(--awa-space-6)` |
| `gap: 16px` | `gap: var(--awa-space-4)` |

---

## Arquivo de Destino

```
pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-core-variables.unmin.css
```

```bash
cp awa-core-variables.unmin.css awa-core-variables.css
php bin/magento cache:clean
```

## Checklist
- [ ] `--awa-primary` sincronizado com `--awa-red` (#b73337)
- [ ] Todos os tokens canônicos presentes
- [ ] Zero hardcodes de cor nos bundles principais
- [ ] Zero padding/margin hardcoded sem token