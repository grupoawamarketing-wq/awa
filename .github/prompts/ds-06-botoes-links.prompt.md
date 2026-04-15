---
description: "AWA DS v2 — 06: Botões e Links: padroniza 4 variantes de botão (primary, secondary, ghost, danger), estados interativos e links globais"
agent: "agent"
tools:
  - codebase
  - edit
  - execute
  - changes

---

> **Skill obrigatória:** `design-system`.

Padronize 100% dos botões e links do site AWA Motos com o sistema de design — 4 variantes, estados consistentes e WCAG 2.5.8 (min 44px).

---

## 4 Variantes de Botão

| Variante | Seletor principal | Uso |
|----------|-------------------|-----|
| Primary | `.action.primary`, `.action.tocart` | Ação principal |
| Secondary | `.action.secondary` | Ação secundária |
| Ghost | `.action.back`, `.action.cancel` | Terciária/cancelar |
| Danger | `.action.delete` | Ação destrutiva |

---

## CSS Completo — Botões

```css
/* === BASE === */
body .page-wrapper .action,
body .page-wrapper button,
body .page-wrapper [type="button"],
body .page-wrapper [type="submit"] {
  display: inline-flex; align-items: center; justify-content: center;
  gap: var(--awa-space-2); min-height: 44px; padding: 0 var(--awa-space-6);
  font-size: var(--awa-text-base, 14px); font-family: inherit; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.04em; line-height: 1;
  white-space: nowrap; cursor: pointer; text-decoration: none;
  border: none; border-radius: var(--awa-radius-sm, 8px);
  transition: background-color var(--awa-transition),
              border-color var(--awa-transition),
              color var(--awa-transition),
              box-shadow var(--awa-transition), transform 150ms ease;
  user-select: none; vertical-align: middle; box-sizing: border-box;
}
body .page-wrapper .action:active { transform: translateY(1px); }
body .page-wrapper .action:disabled,
body .page-wrapper .action.disabled {
  opacity: 0.55; cursor: not-allowed; pointer-events: none; transform: none !important;
}

/* === PRIMARY === */
body .page-wrapper .action.primary,
body .page-wrapper .action.tocart,
body .page-wrapper .btn-primary {
  background: var(--awa-red, #b73337);
  color: var(--awa-white, #fff);
  border: 1.5px solid var(--awa-red);
  box-shadow: 0 2px 8px color-mix(in srgb, var(--awa-red) 24%, transparent);
}
body .page-wrapper .action.primary:hover,
body .page-wrapper .action.tocart:hover {
  background: var(--awa-red-dark, #8e2629) !important;
  border-color: var(--awa-red-dark) !important;
  color: var(--awa-white) !important;
  box-shadow: 0 6px 18px color-mix(in srgb, var(--awa-red) 36%, transparent);
  transform: translateY(-1px); text-decoration: none;
}
body .page-wrapper .action.primary:focus-visible {
  outline: 2px solid var(--awa-red-dark); outline-offset: 2px;
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--awa-red) 20%, transparent);
}

/* === SECONDARY === */
body .page-wrapper .action.secondary,
body .page-wrapper .btn-secondary {
  background: transparent; color: var(--awa-red);
  border: 1.5px solid var(--awa-red); box-shadow: none;
}
body .page-wrapper .action.secondary:hover {
  background: var(--awa-red) !important; color: var(--awa-white) !important;
  border-color: var(--awa-red) !important; transform: translateY(-1px);
  text-decoration: none;
}

/* === GHOST === */
body .page-wrapper .action.back,
body .page-wrapper .action.cancel,
body .page-wrapper .btn-ghost {
  background: transparent; color: var(--awa-gray-500);
  border: 1px solid var(--awa-gray-200);
  font-weight: 500; text-transform: none; letter-spacing: 0;
}
body .page-wrapper .action.back:hover,
body .page-wrapper .action.cancel:hover  {
  background: var(--awa-gray-50) !important;
  border-color: var(--awa-red) !important;
  color: var(--awa-red) !important; text-decoration: none;
}

/* === DANGER === */
body .page-wrapper .action.delete,
body .page-wrapper .btn-danger {
  background: var(--awa-danger, #dc2626); color: var(--awa-white);
  border: 1.5px solid var(--awa-danger);
}
body .page-wrapper .action.delete:hover {
  background: #991b1b !important; border-color: #991b1b !important;
  text-decoration: none;
}

/* === TAMANHOS === */
body .page-wrapper .action.btn-sm { min-height: 36px; height: 36px; padding: 0 var(--awa-space-3); }
body .page-wrapper .action.btn-lg { min-height: 52px; padding: 0 var(--awa-space-7); }
body .page-wrapper .action.icon-only { padding: 0; width: 44px; height: 44px; border-radius: var(--awa-radius-sm); }

/* === EXCEÇÃO: botões em menu/nav === */
body .page-wrapper .nav-sections .action,
body .page-wrapper .navigation .action {
  background: transparent !important; border: none !important;
  color: inherit !important; box-shadow: none !important;
  min-height: auto !important; text-transform: none !important;
  font-weight: normal !important; letter-spacing: 0 !important;
}
```

---

## CSS Completo — Links

```css
/* === BASE === */
body .page-wrapper a {
  color: var(--awa-red, #b73337); text-decoration: none;
  transition: color var(--awa-transition-fast, 120ms ease);
}
body .page-wrapper a:hover { color: var(--awa-red-dark, #8e2629); }
body .page-wrapper a:focus-visible {
  outline: 2px solid var(--awa-red); outline-offset: 2px; border-radius: 2px;
}

/* Links em texto corrido — COM sublinhado */
body .page-wrapper .std a,
body .page-wrapper .description a,
body .page-wrapper .cms-content a {
  text-decoration: underline; text-underline-offset: 3px;
  text-decoration-color: color-mix(in srgb, var(--awa-red) 40%, transparent);
}

/* Links de navegação */
body .page-wrapper .header.links a,
body .page-wrapper .footer.links a { color: inherit !important; }
body .page-wrapper .header.links a:hover { color: var(--awa-red) !important; }
```

---

## Arquivo de Destino

`awa-bundle-custom.unmin.css` → substituir seção `=== BUTTON SYSTEM ===`

```bash
cp awa-bundle-custom.unmin.css awa-bundle-custom.css
php bin/magento cache:clean
```

## Checklist
- [ ] `.action.primary` → `var(--awa-red)` + `min-height: 44px`
- [ ] `.action.secondary` → outline vermelho
- [ ] `.action.back` → ghost neutro
- [ ] `.action.delete` → `var(--awa-danger)`
- [ ] Focus ring em todos os botões (WCAG 2.4.7)
- [ ] Hover: `translateY(-1px)` + shadow
- [ ] Disabled: opacity 0.55, pointer-events none
- [ ] Links com `var(--awa-red)` sem hardcode
- [ ] Exceção: botões de menu sem estilo de button