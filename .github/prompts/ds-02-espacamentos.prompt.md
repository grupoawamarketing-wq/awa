---
description: "AWA DS v2 — 02: Espaçamentos e Padding: padroniza margin, padding, gap e ritmo vertical usando a escala --awa-space-*"
agent: "agent"
tools:
  - codebase
  - edit
  - execute
  - changes

---

> **Skill obrigatória:** `design-system`.

Elimine todos os valores de `padding` e `margin` hardcoded e padronize com a escala `--awa-space-*`.

---

## Escala Canônica

```
--awa-space-1:  4px   → micro (ícone, dot, separador)
--awa-space-2:  8px   → gap interno de componente
--awa-space-3:  12px  → padding interno leve
--awa-space-4:  16px  → padding padrão, gap entre itens
--awa-space-5:  20px  → container padding lateral
--awa-space-6:  24px  → card padding, heading bottom
--awa-space-7:  32px  → gap entre componentes
--awa-space-8:  40px  → seção tablet
--awa-space-9:  48px  → section padding desktop
--awa-space-10: 64px  → macro padding / hero
```

---

## Diagnóstico

```bash
grep -n "padding:\|margin:\|gap:" \
  pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-bundle-custom.unmin.css \
  | grep -v "var(--awa\|auto\|0$\|0 0\|0px" | head -40
```

---

## Mapa de Equivalência

| Valor | Token | Contexto |
|-------|-------|---------|
| 4px | `var(--awa-space-1)` | Micro |
| 8px | `var(--awa-space-2)` | Gap badge |
| 12px | `var(--awa-space-3)` | Padding leve |
| 15-16px | `var(--awa-space-4)` | Padding base |
| 20px | `var(--awa-space-5)` | Container pad |
| 24px | `var(--awa-space-6)` | Card body |
| 30-32px | `var(--awa-space-7)` | Gap componentes |
| 40px | `var(--awa-space-8)` | Seção tablet |
| 48px | `var(--awa-space-9)` | Seção desktop |
| 60-64px | `var(--awa-space-10)` | Macro / hero |

---

## Ritmo Vertical Padrão

```css
/* Seção padrão */
.awa-section,
body .page-wrapper .block,
body .page-wrapper .widget {
  padding-block: var(--awa-space-9); /* 48px desktop */
}

@media (max-width: 767px) {
  .awa-section,
  body .page-wrapper .block,
  body .page-wrapper .widget {
    padding-block: var(--awa-space-6); /* 24px mobile */
  }
}

/* Gap entre seções */
body .page-wrapper .page-main > * + * {
  margin-top: var(--awa-space-7); /* 32px */
}

/* Título de seção */
body .page-wrapper .block-title {
  margin-bottom: var(--awa-space-6); /* 24px */
}

/* Card body */
body .page-wrapper .product-item-details {
  padding: var(--awa-space-3) var(--awa-space-4); /* 12px 16px */
}

/* Card action */
body .page-wrapper .product-item-actions {
  padding: 0 var(--awa-space-4) var(--awa-space-4);
}

/* Field/formulário */
body .page-wrapper .field {
  margin-bottom: var(--awa-space-4); /* 16px */
}
body .page-wrapper .fieldset {
  margin-bottom: var(--awa-space-6); /* 24px */
}

/* Breadcrumb */
body .page-wrapper .breadcrumbs {
  padding-block: var(--awa-space-3);  /* 12px */
  margin-bottom: var(--awa-space-5);  /* 20px */
}

/* Header content */
body .page-wrapper .header.content {
  padding-block: var(--awa-space-4);
  padding-inline: var(--awa-container-padding);
}

/* Footer */
body .page-wrapper .footer.content {
  padding-block: var(--awa-space-10); /* 64px */
}

/* Paginação */
body .page-wrapper .pages {
  margin-top: var(--awa-space-7); /* 32px */
  padding-block: var(--awa-space-4);
}
```

---

## Arquivo de Destino

`awa-bundle-site.unmin.css` → seção `=== SPACING SYSTEM ===`

```bash
cp awa-bundle-site.unmin.css awa-bundle-site.css
php bin/magento cache:clean
```

## Checklist
- [ ] Zero `padding: Xpx` hardcoded nos bundles principais
- [ ] Seções: 48px desktop / 24px mobile
- [ ] Cards: padding 12px/16px
- [ ] Header e footer com tokens de espaçamento
- [ ] Gap entre elements usando `--awa-space-*`