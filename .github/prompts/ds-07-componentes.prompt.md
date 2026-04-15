---
description: "AWA DS v2 — 07: Componentes UI: cards de produto, badges, tags, alerts, paginação, breadcrumbs e tooltip padronizados"
agent: "agent"
tools:
  - codebase
  - edit
  - execute
  - changes

---

> **Skill obrigatória:** `design-system`.

Padronize todos os componentes reutilizáveis do site AWA Motos usando tokens canônicos.

---

## Card de Produto

```css
/* === CARD PRODUTO — PADRÃO DEFINITIVO === */
body .page-wrapper .product-item-info {
  background: var(--awa-white); border: 1px solid var(--awa-gray-200);
  border-radius: var(--awa-radius-md, 10px); overflow: hidden;
  display: flex; flex-direction: column; height: 100%;
  transition: box-shadow var(--awa-transition), transform var(--awa-transition);
}
body .page-wrapper .product-item-info:hover {
  box-shadow: var(--awa-shadow-2, 0 8px 24px rgba(17,24,39,0.10));
  transform: translateY(-2px); border-color: var(--awa-gray-250);
}

/* Imagem */
body .page-wrapper .product-item-photo {
  overflow: hidden; position: relative;
  background: var(--awa-gray-50); aspect-ratio: 1 / 1;
}
body .page-wrapper .product-item-photo .product-image-photo {
  width: 100%; height: 100%; object-fit: contain;
  padding: var(--awa-space-3); box-sizing: border-box;
  transition: transform 300ms ease;
}
body .page-wrapper .product-item-info:hover .product-image-photo {
  transform: scale(1.04);
}

/* Corpo */
body .page-wrapper .product-item-details {
  padding: var(--awa-space-3) var(--awa-space-4);
  flex: 1; display: flex; flex-direction: column; gap: var(--awa-space-2);
}

/* Nome */
body .page-wrapper .product-item-name {
  font-size: var(--awa-text-sm, 13px); font-weight: 600;
  color: var(--awa-gray-700); line-height: 1.4;
  display: -webkit-box; -webkit-line-clamp: 2;
  -webkit-box-orient: vertical; overflow: hidden;
}
body .page-wrapper .product-item-name a { color: inherit; text-decoration: none; }
body .page-wrapper .product-item-name a:hover { color: var(--awa-red); }

/* Preço */
body .page-wrapper .product-item-details .price {
  font-size: var(--awa-text-md, 15px); font-weight: 700; color: var(--awa-red);
}
body .page-wrapper .product-item-details .old-price .price {
  font-size: var(--awa-text-sm); font-weight: 400;
  color: var(--awa-gray-450); text-decoration: line-through;
}

/* Ação */
body .page-wrapper .product-item-actions {
  padding: 0 var(--awa-space-4) var(--awa-space-4); margin-top: auto;
}
body .page-wrapper .product-item-actions .action.tocart { width: 100%; justify-content: center; }

/* SKU */
body .page-wrapper .product-item-sku {
  font-size: var(--awa-text-xs, 11px); color: var(--awa-gray-450); font-family: monospace;
}
```

---

## Badges de Produto

```css
.product-badge, .product-label {
  position: absolute; top: var(--awa-space-2); left: var(--awa-space-2);
  z-index: var(--awa-z-base, 1); font-size: var(--awa-text-xs, 11px);
  font-weight: 700; line-height: 1; padding: 4px 8px;
  border-radius: var(--awa-radius-full); white-space: nowrap;
  text-transform: uppercase; letter-spacing: 0.05em;
}
.product-badge--new  { background: var(--awa-info);    color: #fff; }
.product-badge--sale { background: var(--awa-red);     color: #fff; }
.product-badge--hot  { background: var(--awa-warning); color: #fff; }
.product-badge--off  { background: var(--awa-success); color: #fff; }
.product-badge--out  { background: var(--awa-gray-450);color: #fff; }
```

---

## Tags / Chips

```css
.awa-tag {
  display: inline-flex; align-items: center; gap: var(--awa-space-1);
  padding: 3px 10px; font-size: var(--awa-text-xs); font-weight: 600;
  border-radius: var(--awa-radius-full); border: 1px solid transparent;
  white-space: nowrap; line-height: 1.4;
}
.awa-tag--default  { background: var(--awa-gray-50);  color: var(--awa-gray-500); border-color: var(--awa-gray-200); }
.awa-tag--primary  { background: var(--awa-red-light); color: var(--awa-red-dark); }
.awa-tag--success  { background: var(--awa-success-bg); color: var(--awa-success); }
.awa-tag--warning  { background: var(--awa-warning-bg); color: var(--awa-warning); }
.awa-tag--danger   { background: var(--awa-danger-bg);  color: var(--awa-danger); }
.awa-tag--info     { background: var(--awa-info-bg);    color: var(--awa-info); }
```

---

## Alerts

```css
.awa-alert, body .page-wrapper .messages .message {
  display: flex; align-items: flex-start; gap: var(--awa-space-3);
  padding: var(--awa-space-4); border-radius: var(--awa-radius-sm);
  border-left: 4px solid; margin-bottom: var(--awa-space-4);
  font-size: var(--awa-text-base); line-height: 1.5;
}
body .page-wrapper .message.success, .awa-alert--success {
  background: var(--awa-success-bg); color: var(--awa-success); border-color: var(--awa-success);
}
body .page-wrapper .message.notice, .awa-alert--warning {
  background: var(--awa-warning-bg); color: var(--awa-warning); border-color: var(--awa-warning);
}
body .page-wrapper .message.error, .awa-alert--error {
  background: var(--awa-danger-bg); color: var(--awa-danger); border-color: var(--awa-danger);
}
body .page-wrapper .message.info, .awa-alert--info {
  background: var(--awa-info-bg); color: var(--awa-info); border-color: var(--awa-info);
}
```

---

## Paginação

```css
body .page-wrapper .pages {
  display: flex; align-items: center; justify-content: center;
  gap: var(--awa-space-1); padding-block: var(--awa-space-7);
  list-style: none; margin: 0;
}
body .page-wrapper .pages-item-page .page,
body .page-wrapper .pages-item-next .action,
body .page-wrapper .pages-item-previous .action {
  display: inline-flex; align-items: center; justify-content: center;
  width: 40px; height: 40px; border-radius: var(--awa-radius-sm);
  border: 1px solid var(--awa-gray-200); background: var(--awa-white);
  color: var(--awa-gray-700); font-size: var(--awa-text-sm); font-weight: 500;
  text-decoration: none; transition: all var(--awa-transition-fast); cursor: pointer;
}
body .page-wrapper .pages-item-page .page:hover,
body .page-wrapper .pages-item-next .action:hover {
  background: var(--awa-gray-50); border-color: var(--awa-red); color: var(--awa-red);
}
body .page-wrapper .pages-item-page.current .page {
  background: var(--awa-red); border-color: var(--awa-red);
  color: var(--awa-white); font-weight: 700; pointer-events: none;
}
```

---

## Breadcrumb

```css
body .page-wrapper .breadcrumbs {
  padding-block: var(--awa-space-3); margin-bottom: var(--awa-space-5);
}
body .page-wrapper .breadcrumbs .items {
  display: flex; flex-wrap: wrap; align-items: center; gap: var(--awa-space-1);
  list-style: none; padding: 0; margin: 0; font-size: var(--awa-text-xs, 11px);
}
body .page-wrapper .breadcrumbs .item { display: flex; align-items: center; gap: var(--awa-space-1); color: var(--awa-gray-500); }
body .page-wrapper .breadcrumbs .item::after { content: '/'; color: var(--awa-gray-250); font-size: 10px; }
body .page-wrapper .breadcrumbs .item:last-child::after { display: none; }
body .page-wrapper .breadcrumbs .item a { color: var(--awa-gray-500); text-decoration: none; }
body .page-wrapper .breadcrumbs .item a:hover { color: var(--awa-red); }
body .page-wrapper .breadcrumbs .item strong { color: var(--awa-gray-700); font-weight: 600; }
```

---

## Arquivo de Destino

| Componente | Bundle |
|------------|--------|
| Card, grid, badges | `awa-bundle-category.unmin.css` |
| Alerts | `awa-bundle-site.unmin.css` |
| Paginação, breadcrumb | `awa-bundle-category.unmin.css` |
| Tags `.awa-tag` | `awa-bundle-site.unmin.css` |

```bash
cp awa-bundle-category.unmin.css awa-bundle-category.css
cp awa-bundle-site.unmin.css awa-bundle-site.css
php bin/magento cache:clean
```

## Checklist
- [ ] Card: hover `translateY(-2px)` + shadow
- [ ] Imagem: `aspect-ratio: 1/1` + `object-fit: contain`
- [ ] Nome: `-webkit-line-clamp: 2`
- [ ] Preço: `--awa-red`, tachado `--awa-gray-450`
- [ ] 5 variantes de badge
- [ ] 4 variantes de alert
- [ ] Paginação: página ativa com `--awa-red`
- [ ] Breadcrumb: separador `/`, hover vermelho