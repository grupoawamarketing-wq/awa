# AWA Design System — Token Reference

**Versão:** 1.0 | **Última atualização:** 2026-03-30

Esta é a documentação completa dos design tokens canonicais para o sistema AWA.

---

## 📌 Importância

- **Fonte única de verdade:** `source/_awa-variables.less`
- **Escopo LESS:** `@awa-*` (usado em arquivos `.less`)
- **Escopo CSS:** `--awa-*` (disponível em `:root` via `_extend.less`)
- **Nunca hardcode:** Sempre use um token. Se não existir, crie um.

---

## 🎨 Brand Identity Colors

Cores primárias da marca AWA Motos. Usadas em buttons, links, highlights.

| Token | Valor | Uso |
|---|---|---|
| `@awa-color-primary` | `#b73337` | Button BG, link, primary actions |
| `@awa-color-primary-dark` | `#8e2629` | Hover states, active states |
| `@awa-color-text-primary` | `#333333` | Body text, main content |
| `@awa-color-button-text` | `#ffffff` | Text on primary button |
| `@awa-color-link` | = `@awa-color-primary` | Anchor links |
| `@awa-color-link-hover` | = `@awa-color-primary-dark` | Anchor hover |

**Exemplo em LESS:**
```less
.btn-primary {
    background: @awa-color-primary;
    color: @awa-color-button-text;
    
    &:hover {
        background: @awa-color-primary-dark;
    }
}
```

**Exemplo em CSS (via :root):**
```css
button.primary {
    background: var(--awa-color-primary);
    color: var(--awa-color-button-text);
}
```

---

## 📏 Spacing Scale

Escala harmônica 4/8px. Use para margin, padding, gap.

| Token | Valor | Caso de Uso |
|---|---|---|
| `@awa-space-1` | 4px | Tiny gaps, icon margin |
| `@awa-space-2` | 8px | Button padding, small gap |
| `@awa-space-3` | 12px | Component internal spacing |
| `@awa-space-4` | 16px | Default gap, section padding |
| `@awa-space-5` | 20px | Larger component spacing |
| `@awa-space-6` | 24px | Section gap |
| `@awa-space-7` | 32px | Major section spacing |
| `@awa-space-8` | 40px | Hero spacing |
| `@awa-space-9` | 48px | Page-level spacing |
| `@awa-space-10` | 64px | Very large section |

**Exemplo:**
```less
.card {
    padding: @awa-space-4;
    margin-bottom: @awa-space-6;
    
    .card-title {
        margin-bottom: @awa-space-3;
    }
}
```

---

## 📐 Gap Scale

Para `gap` em flex/grid. Alias semântico da spacing.

| Token | Valor | Uso |
|---|---|---|
| `@awa-gap-xs` | 4px | Tight grid |
| `@awa-gap-sm` | 8px | Compact layout |
| `@awa-gap-md` | 12px | Normal gap |
| `@awa-gap-lg` | 16px | Loose layout |
| `@awa-gap-xl` | 24px | Large gap |
| `@awa-gap-2xl` | 32px | Very large gap |

---

## 🔘 Border Radius

Rounded corners para cards, buttons, inputs.

| Token | Valor | Uso |
|---|---|---|
| `@awa-radius-2xs` | 4px | Tiny radius (chips, badges) |
| `@awa-radius-xs` | 6px | Small radius |
| `@awa-radius-sm` | 8px | Button, input |
| `@awa-radius-md` | 10px | Card default |
| `@awa-radius-lg` | 16px | Large card, modal |
| `@awa-radius-full` | 9999px | Pill button, circular |

---

## 🌑 Typography — Font Sizes

Escala harmônica 10–32px.

| Token | Valor | Contexto |
|---|---|---|
| `@awa-font-size-10` | 10px | Meta text, copyright |
| `@awa-font-size-12` | 12px | Small labels, helper text |
| `@awa-font-size-13` | 13px | Form labels |
| `@awa-font-size-14` | 14px | Body small, secondary text |
| `@awa-font-size-15` | 15px | Button text |
| `@awa-font-size-16` | 16px | Body default |
| `@awa-font-size-18` | 18px | Heading 4 |
| `@awa-font-size-20` | 20px | Heading 3 |
| `@awa-font-size-24` | 24px | Heading 2 |
| `@awa-font-size-32` | 32px | Heading 1 |

**Text scale aliases** (for convenience):
```
@awa-text-xs   = 12px    @awa-text-sm   = 14px
@awa-text-base = 16px    @awa-text-lg   = 18px
@awa-text-xl   = 24px    @awa-text-2xl  = 32px
```

---

## 📏 Typography — Line Heights

Para legibilidade e espaçamento vertical.

| Token | Valor | Uso |
|---|---|---|
| `@awa-line-height-tight` | 1.2 | Headings, compact |
| `@awa-line-height-compact` | 1.3 | SubHeadings |
| `@awa-line-height-normal` | 1.4 | Tight body text |
| `@awa-line-height-base` | 1.5 | Body default (recomendado) |
| `@awa-line-height-relaxed` | 1.6 | Loose body text |
| `@awa-line-height-loose` | 1.8 | Very readable, accessibility |

**Exemplo:**
```less
h1 {
    font-size: @awa-font-size-32;
    line-height: @awa-line-height-tight;
    font-weight: @awa-weight-bold;
}

p {
    font-size: @awa-font-size-16;
    line-height: @awa-line-height-base;
    font-weight: @awa-weight-normal;
}
```

---

## 🎚 Typography — Font Weights

| Token | Valor | Uso |
|---|---|---|
| `@awa-weight-normal` | 400 | Body text |
| `@awa-weight-medium` | 500 | Form labels |
| `@awa-weight-semibold` | 600 | Secondary headings |
| `@awa-weight-bold` | 700 | Headings |

---

## 🎴 Shadows

Para elevation e depth.

| Token | Valor | Uso |
|---|---|---|
| `@awa-shadow-card` | `0 8px 24px rgba(17, 24, 39, .06)` | Card default |
| `@awa-shadow-card-hover` | `0 12px 32px rgba(17, 24, 39, .10)` | Card hover, lifted |

---

## ⚪ Surface & Border Colors

| Token | Valor | Uso |
|---|---|---|
| `@awa-color-white` | `#ffffff` | Background, button bg |
| `@awa-color-border` | `#e5e5e5` | Input border, divider |
| `@awa-color-bg-soft` | `#f7f7f7` | Soft background, section bg |

---

## 🩶 Neutral / Gray Scale (Slate-based, 9 steps)

Cores neutras para texto secundário, backgrounds, borders.

| Token | Valor | Luminância |
|---|---|---|
| `@awa-neutral-50` | `#f8fafc` | Muito claro (backgrounds) |
| `@awa-neutral-100` | `#f1f5f9` | Muito claro |
| `@awa-neutral-200` | `#e2e8f0` | Claro (borders) |
| `@awa-neutral-300` | `#cbd5e1` | Claro-médio |
| `@awa-neutral-400` | `#94a3b8` | Médio |
| `@awa-neutral-500` | `#64748b` | Médio-escuro |
| `@awa-neutral-600` | `#475569` | Escuro (secondary text) |
| `@awa-neutral-700` | `#334155` | Muito escuro (labels) |
| `@awa-neutral-800` | `#1e293b` | Muito escuro (primary text) |
| `@awa-neutral-900` | `#0f172a` | Extremamente escuro |

**Legacy aliases:** `--awa-gray-*` (maps to `--awa-neutral-*`)

---

## ✅ State / Semantic Colors

Para feedback visual (success, error, warning, info).

### Success
| Token | Valor | Uso |
|---|---|---|
| `@awa-color-success` | `#16a34a` | Success badge, checkmark |
| `@awa-color-success-light` | `#dcfce7` | Success background |
| `@awa-color-success-text` | `#166534` | Success text |

### Warning
| Token | Valor | Uso |
|---|---|---|
| `@awa-color-warning` | `#d97706` | Warning icon |
| `@awa-color-warning-light` | `#fef3c7` | Warning background |
| `@awa-color-warning-text` | `#92400e` | Warning text |

### Error
| Token | Valor | Uso |
|---|---|---|
| `@awa-color-error` | = `@awa-color-primary` | Error badge (reusa brand red) |
| `@awa-color-error-bg` | `fade(@awa-color-primary, 8%)` | Error background (subtle) |
| `@awa-color-error-text` | = `@awa-color-primary-dark` | Error text |

### Info
| Token | Valor | Uso |
|---|---|---|
| `@awa-color-info` | `#2563eb` | Info icon |
| `@awa-color-info-light` | `#eff6ff` | Info background |
| `@awa-color-info-text` | `#1e40af` | Info text |

---

## ⏱ Transitions

| Token | Valor | Uso |
|---|---|---|
| `@awa-transition` | `250ms ease` | Default animation duration |

**Exemplo:**
```less
.button {
    transition: background @awa-transition, color @awa-transition;
    
    &:hover {
        background: @awa-color-primary-dark;
    }
}
```

---

## 📑 Z-Index Scale

Para controlar stacking context. Veja `source/_z-index.less` para detalhes.

| Token | Valor | Contexto |
|---|---|---|
| `@awa-z-dropdown` | 100 | Select, autocomplete |
| `@awa-z-tooltip` | 150 | Hover tooltips |
| `@awa-z-sticky` | 200 | Fixed header, sticky nav |
| `@awa-z-floating-btn` | 250 | FAB, chat widget |
| `@awa-z-overlay` | 500 | Loading spinner, dim |
| `@awa-z-modal-backdrop` | 999 | Modal background |
| `@awa-z-modal` | 1000 | Modal dialog |
| `@awa-z-alert-backdrop` | 1099 | Toast background |
| `@awa-z-alert` | 1100 | Toast message |
| `@awa-z-debug` | 9999 | Dev tools only |

---

## 🔌 Layout / Container

| Token | Valor | Uso |
|---|---|---|
| `@awa-container-max-width` | 1280px | Page max width |
| `@awa-container-pad` | 16px | Page horizontal padding |

---

## 📱 Breakpoints

| Token | Valor | Device |
|---|---|---|
| `@awa-breakpoint-xs` | 480px | Mobile small |
| `@awa-breakpoint-sm` | 768px | Tablet portrait |
| `@awa-breakpoint-md` | 992px | Tablet landscape |
| `@awa-breakpoint-lg` | 1200px | Desktop |

**Exemplo:**
```less
.container {
    padding: @awa-space-4;
    
    @media (max-width: @awa-breakpoint-sm) {
        padding: @awa-space-3;
    }
}
```

---

## 🎯 Control / Button Heights

WCAG 2.5.8 compliant (minimum 24×24, recommended 44px for touch).

| Token | Valor | Uso |
|---|---|---|
| `@awa-control-height-sm` | 36px | Compact button |
| `@awa-control-height` | 44px | Standard button (recomendado) |
| `@awa-control-height-lg` | 54px | Large button |

---

## 🎯 Focus Ring (WCAG AA)

Para acessibilidade.

| Token | Valor | Uso |
|---|---|---|
| `@awa-focus-ring` | `0 0 0 3px fade(@awa-color-primary, 25%)` | Focus outline |
| `@awa-focus-ring-offset` | 2px | Space between element e ring |

**Exemplo:**
```less
input:focus-visible {
    outline: none;
    box-shadow: @awa-focus-ring;
}
```

---

## 🔗 How to Use

### In LESS Files
```less
@import '_awa-variables';  // At top of file

.my-component {
    background: @awa-color-white;
    padding: @awa-space-4;
    border-radius: @awa-radius-md;
    box-shadow: @awa-shadow-card;
    font-size: @awa-font-size-16;
    line-height: @awa-line-height-base;
    color: @awa-color-text-primary;
}
```

### In CSS/HTML (via :root)
```css
.my-component {
    background: var(--awa-color-white);
    padding: var(--awa-space-4);
    border-radius: var(--awa-radius-md);
    box-shadow: var(--awa-shadow-card);
    font-size: var(--awa-font-size-16);
    color: var(--awa-color-text-primary);
}
```

### LESS Interpolation (Advanced)
```less
@color-value: @awa-color-primary;
.element::before {
    color: @{color-value};  // Interpolation
}
```

---

## ⚠️ Dos and Don'ts

✅ **DO:**
- Use `@awa-*` tokens em todos os LESS files
- Use `--awa-*` em CSS components que precisam ser dinâmicas
- Criar novo token se não existir (adicione a `_awa-variables.less`)
- Documentar novos tokens aqui
- Use tokens em `_extend.less` para expor como CSS custom props

❌ **DON'T:**
- Hardcode hex colors (`#b73337`) — use `@awa-color-primary`
- Hardcode spacing (`16px`) — use `@awa-space-4`
- Hardcode radius (`8px`) — use `@awa-radius-sm`
- Criar aliases sem padrão — mantenha nomenclatura consistente
- Ignorar stylelint warnings sobre hardcoded colors

---

## 📚 Related Files

- **Source:** `source/_awa-variables.less` (tokens LESS)
- **Bridge:** `source/_extend.less` (LESS → CSS vars)
- **Z-Index:** `source/_z-index.less` (z-index centralized)
- **Linter:** `.stylelintrc.json` (hardcode detection)

---

## 📞 Questions?

Consulte `AUDIT_VISUAL.md` para histórico de decisões de tokens.
