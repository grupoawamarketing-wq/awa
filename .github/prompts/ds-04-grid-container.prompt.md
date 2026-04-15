---
description: "AWA DS v2 — 04: Grid, Container e Largura: define container canônico, grid responsivo de produtos, breakpoints e max-widths consistentes"
agent: "agent"
tools:
  - codebase
  - edit
  - execute
  - changes

---

> **Skill obrigatória:** `design-system`.

Garanta que todo o site usa um único sistema de container, grid e breakpoints — mobile-first, sem conflitos com o tema AYO.

---

## Larguras Máximas

```
--awa-container:         1280px  → container principal (page-main)
--awa-container-wide:    1440px  → home / seções full-width
--awa-container-narrow:  560px   → formulários (login, cadastro)
--awa-sidebar-width:     240px   → sidebar filtros PLP
--awa-container-padding: 20px    → padding lateral
```

---

## Container Canônico

```css
.awa-container,
body .page-wrapper .page-main,
body .page-wrapper .columns,
body .page-wrapper .container,
body .page-wrapper .header.content,
body .page-wrapper .footer.content {
  width: 100%;
  max-width: var(--awa-container, 1280px);
  margin-inline: auto;
  padding-inline: var(--awa-container-padding, 20px);
  box-sizing: border-box;
}

/* Container estreito — formulários */
body .page-wrapper .form-login,
body .page-wrapper .form-create-account,
body .page-wrapper .form-edit-account,
body .page-wrapper .form-address-edit {
  max-width: 560px;
  margin-inline: auto;
  width: 100%;
}
```

---

## Breakpoints Responsivos (Mobile First)

| Nome | Min-width |
|------|-----------|
| base | 0px |
| mobile-lg | 480px |
| tablet | 768px |
| desktop-sm | 992px |
| desktop | 1280px |
| wide | 1440px |

---

## Grid de Produtos (PLP)

```css
body .page-wrapper .products-grid .product-items,
body .page-wrapper ol.products.list.items.product-items {
  display: grid;
  grid-template-columns: repeat(2, 1fr); /* mobile: 2 col */
  gap: var(--awa-space-3);               /* 12px */
  list-style: none;
  padding: 0; margin: 0;
}

@media (min-width: 768px) {
  body .page-wrapper .products-grid .product-items {
    grid-template-columns: repeat(3, 1fr); /* tablet: 3 col */
    gap: var(--awa-space-4);               /* 16px */
  }
}

@media (min-width: 992px) {
  body .page-wrapper .products-grid .product-items {
    grid-template-columns: repeat(4, 1fr); /* desktop: 4 col */
    gap: var(--awa-space-5);               /* 20px */
  }
}

@media (min-width: 1440px) {
  body .page-wrapper .products-grid .product-items {
    grid-template-columns: repeat(5, 1fr); /* wide: 5 col */
  }
}
```

---

## Layout com Sidebar (Category)

```css
@media (min-width: 992px) {
  body .page-wrapper .columns {
    display: grid;
    grid-template-columns: var(--awa-sidebar-width, 240px) 1fr;
    gap: var(--awa-space-7); /* 32px */
    align-items: start;
  }
  body .page-wrapper .sidebar-main {
    position: sticky;
    top: 0;
    max-height: 100vh;
    overflow-y: auto;
  }
}

@media (max-width: 991px) {
  body .page-wrapper .columns { display: block; }
}
```

---

## Seções Full-Width (Home / Banners)

```css
.awa-full-section {
  width: 100%;
  padding-inline: 0;
}
.awa-full-section > .container {
  max-width: 1440px;
  margin-inline: auto;
  padding-inline: var(--awa-container-padding);
}
```

---

## Diagnóstico

```bash
# Larguras fixas suspeitas
grep -n "width: [0-9]\+px" \
  pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-bundle-category.unmin.css \
  | grep -v "max-width\|min-width\|height" | head -20

# Conflitos Rokanthemes
grep -n "max-width\|\.page-main\|\.columns" \
  pub/static/frontend/ayo/ayo_home5/en_US/css/themes5.css 2>/dev/null | head -20
```

---

## Arquivo de Destino

```
# Grid produtos:
pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-bundle-category.unmin.css
(seção === PRODUCT GRID ===)

# Container global:
pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-bundle-site.unmin.css
(seção === LAYOUT & CONTAINER ===)
```

```bash
cp awa-bundle-category.unmin.css awa-bundle-category.css
cp awa-bundle-site.unmin.css awa-bundle-site.css
php bin/magento cache:clean
```

## Checklist
- [ ] Container max-width 1280px em todas as páginas
- [ ] Grid produtos: 2/3/4 colunas CSS Grid (sem float)
- [ ] Sidebar 240px, sticky, apenas ≥992px
- [ ] Nenhum `float: left/right` em product-items
- [ ] Sem scroll horizontal em viewport 320px