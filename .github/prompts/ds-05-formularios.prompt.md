---
description: "AWA DS v2 — 05: Formulários: padroniza inputs, selects, checkboxes, radio, labels, fieldsets e estados de validação (WCAG 2.1 AA)"
agent: "agent"
tools:
  - codebase
  - edit
  - execute
  - changes

---

> **Skill obrigatória:** `design-system`.

Padronize todos os campos de formulário do site — inputs, selects, textareas, checkboxes, radio buttons e estados de validação.

---

## Especificações WCAG 2.5.8

| Propriedade | Valor | Token |
|-------------|-------|-------|
| Input height | 44px | touch target mínimo |
| Textarea min-height | 120px | — |
| Border | 1.5px solid | `--awa-gray-250` |
| Border radius | 8px | `--awa-radius-sm` |
| Border focus | `--awa-red` | #b73337 |
| Focus ring | `0 0 0 3px red-light` | não outline |
| Font size | 14px | `--awa-text-base` |
| Placeholder | `--awa-gray-450` | #999 |

---

## CSS Completo

```css
/* === FIELDSET === */
body .page-wrapper .fieldset, body .page-wrapper fieldset {
  border: none; padding: 0; margin: 0 0 var(--awa-space-6) 0;
}
body .page-wrapper .fieldset > .legend, body .page-wrapper fieldset > legend {
  font-size: var(--awa-text-lg, 18px); font-weight: 700;
  color: var(--awa-gray-700); margin-bottom: var(--awa-space-4);
  width: 100%; padding: 0;
}

/* === FIELD (wrapper) === */
body .page-wrapper .field {
  margin-bottom: var(--awa-space-4);
  display: flex; flex-direction: column; gap: 6px;
}

/* === LABEL — sempre acima === */
body .page-wrapper .field label,
body .page-wrapper label {
  font-size: var(--awa-text-sm, 13px); font-weight: 600;
  color: var(--awa-gray-700); line-height: 1.3; display: block;
}
body .page-wrapper .field.required label::after,
body .page-wrapper .field._required label::after {
  content: ' *'; color: var(--awa-danger, #dc2626); font-weight: 700;
}

/* === INPUT / SELECT / TEXTAREA === */
body .page-wrapper input[type="text"],
body .page-wrapper input[type="email"],
body .page-wrapper input[type="password"],
body .page-wrapper input[type="number"],
body .page-wrapper input[type="tel"],
body .page-wrapper input[type="date"],
body .page-wrapper input[type="search"],
body .page-wrapper select,
body .page-wrapper textarea {
  width: 100%; height: 44px; padding: 0 14px;
  font-size: var(--awa-text-base, 14px); font-family: inherit;
  color: var(--awa-gray-700); background: var(--awa-white);
  border: 1.5px solid var(--awa-gray-250, #ccc);
  border-radius: var(--awa-radius-sm, 8px);
  outline: none; box-sizing: border-box;
  appearance: none; -webkit-appearance: none;
  transition: border-color var(--awa-transition-fast),
              box-shadow var(--awa-transition-fast);
}
body .page-wrapper textarea {
  height: auto; min-height: 120px; padding: 12px 14px;
  resize: vertical; line-height: 1.6;
}
body .page-wrapper input::placeholder,
body .page-wrapper textarea::placeholder {
  color: var(--awa-gray-450, #999); opacity: 1;
}
body .page-wrapper input:focus,
body .page-wrapper select:focus,
body .page-wrapper textarea:focus {
  border-color: var(--awa-red, #b73337);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--awa-red) 15%, transparent);
}
body .page-wrapper input:disabled,
body .page-wrapper select:disabled {
  background: var(--awa-gray-50); color: var(--awa-gray-450);
  cursor: not-allowed; opacity: 0.7;
}

/* === SELECT — seta personalizada === */
body .page-wrapper select {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23666' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 14px center;
  padding-right: 40px; cursor: pointer;
}

/* === CHECKBOX e RADIO === */
body .page-wrapper input[type="checkbox"],
body .page-wrapper input[type="radio"] {
  width: 18px; height: 18px; min-width: 18px;
  appearance: none; -webkit-appearance: none;
  border: 1.5px solid var(--awa-gray-250); background: var(--awa-white);
  cursor: pointer; position: relative;
  transition: border-color 120ms ease, background 120ms ease;
  vertical-align: middle;
}
body .page-wrapper input[type="checkbox"]  { border-radius: var(--awa-radius-xs, 4px); }
body .page-wrapper input[type="radio"]     { border-radius: 50%; }
body .page-wrapper input[type="checkbox"]:checked,
body .page-wrapper input[type="radio"]:checked {
  background: var(--awa-red); border-color: var(--awa-red);
}
body .page-wrapper input[type="checkbox"]:checked::after {
  content: ''; position: absolute; top: 2px; left: 5px;
  width: 5px; height: 9px; border: 2px solid #fff;
  border-top: none; border-left: none; transform: rotate(45deg);
}
body .page-wrapper input[type="radio"]:checked::after {
  content: ''; position: absolute; top: 3px; left: 3px;
  width: 8px; height: 8px; border-radius: 50%; background: #fff;
}

/* === VALIDAÇÃO === */
body .page-wrapper .field._error input,
body .page-wrapper .field._error select {
  border-color: var(--awa-danger, #dc2626);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--awa-danger) 12%, transparent);
}
body .page-wrapper .mage-error, body .page-wrapper .field-error {
  color: var(--awa-danger, #dc2626); font-size: var(--awa-text-xs, 11px);
  margin-top: var(--awa-space-1); display: flex; align-items: center; gap: 4px;
}
body .page-wrapper .field .note {
  font-size: var(--awa-text-xs, 11px); color: var(--awa-gray-500);
  margin-top: var(--awa-space-1);
}
```

---

## Arquivo de Destino

`awa-bundle-custom.unmin.css` → seção `=== FORM SYSTEM ===`

```bash
cp awa-bundle-custom.unmin.css awa-bundle-custom.css
php bin/magento cache:clean
# Testar: /customer/account/login, /checkout, formulários B2B
```

## Checklist
- [ ] Todos os inputs: `height: 44px`
- [ ] Selects com seta customizada
- [ ] Checkbox/radio com visual custom vermelho
- [ ] Label sempre acima (nunca placeholder substitui label)
- [ ] Asterisco vermelho em campos obrigatórios
- [ ] Focus ring vermelho
- [ ] Mensagem de erro `--awa-danger` em 11px
- [ ] Fieldsets sem border/padding browser