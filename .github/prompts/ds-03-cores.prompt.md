---
description: "AWA DS v2 — 03: Cores e Feedback: padroniza paleta, hierarquia visual e estados semânticos usando tokens --awa-*"
agent: "agent"
tools:
  - codebase
  - edit
  - execute
  - changes

---

> **Skill obrigatória:** `design-system`.

Garanta que 100% das decisões de cor usam tokens canônicos, criando hierarquia visual clara e feedback consistente.

---

## Paleta Canônica

### Marca
```
Primária:    --awa-red        #b73337  → botões, links ativos, preço
Hover:       --awa-red-dark   #8e2629  → hover/focus sobre primária
Bg sutil:    --awa-red-light  16% mix  → hover bg leve
```

### Neutros
```
--awa-white       #ffffff  → card, input, modal
--awa-gray-50     #f7f7f7  → superfície (page-wrapper, sidebar)
--awa-gray-200    #e5e5e5  → borda padrão
--awa-gray-250    #cccccc  → borda forte, divisor
--awa-gray-450    #999999  → placeholder, label secundário
--awa-gray-500    #666666  → texto muted, metadado
--awa-gray-700    #333333  → texto principal, heading
--awa-gray-950    #111111  → texto sobre fundo escuro
```

### Feedback Semântico
```
--awa-success  #2d7a3a  --awa-success-bg  #e8f5e9
--awa-warning  #b87a00  --awa-warning-bg  #fff8e1
--awa-danger   #dc2626  --awa-danger-bg   #fdecea
--awa-info     #0ea5e9  --awa-info-bg     #e3f2fd
```

---

## Hierarquia de Cores

```css
/* Textos — 4 níveis de hierarquia */
h1, h2, h3, h4, .page-title { color: var(--awa-gray-700); }
p, .std, body              { color: var(--awa-gray-700); }
.meta, .sku, time          { color: var(--awa-gray-500); }
::placeholder, .hint       { color: var(--awa-gray-450); }

/* Backgrounds */
.page-wrapper              { background: var(--awa-gray-50); }
.product-item-info,
.block, .modal-inner-wrap  { background: var(--awa-white); }
.footer.content            { background: var(--awa-gray-950); color: var(--awa-gray-50); }

/* Links */
body .page-wrapper a       { color: var(--awa-red); text-decoration: none; }
body .page-wrapper a:hover { color: var(--awa-red-dark); }

/* Bordas */
input, select, .product-item { border-color: var(--awa-gray-200); }
input:focus, select:focus     { border-color: var(--awa-red); }
hr, .divider                  { border-color: var(--awa-gray-200); }

/* Preços */
.price { color: var(--awa-red); font-weight: 700; }
.old-price .price { color: var(--awa-gray-450); text-decoration: line-through; }
```

---

## Mensagens do Magento

```css
body .page-wrapper .message.success {
  background: var(--awa-success-bg); color: var(--awa-success);
  border-left: 3px solid var(--awa-success);
}
body .page-wrapper .message.notice {
  background: var(--awa-warning-bg); color: var(--awa-warning);
  border-left: 3px solid var(--awa-warning);
}
body .page-wrapper .message.error {
  background: var(--awa-danger-bg); color: var(--awa-danger);
  border-left: 3px solid var(--awa-danger);
}
body .page-wrapper .message.info {
  background: var(--awa-info-bg); color: var(--awa-info);
  border-left: 3px solid var(--awa-info);
}
```

---

## Badges de Produto

```css
.product-badge--new  { background: var(--awa-info);    color: #fff; }
.product-badge--sale { background: var(--awa-red);     color: #fff; }
.product-badge--hot  { background: var(--awa-warning); color: #fff; }
.product-badge--off  { background: var(--awa-success); color: #fff; }
.product-badge--out  { background: var(--awa-gray-450);color: #fff; }
```

---

## Diagnóstico

```bash
# Cores hardcoded a substituir
grep -n "color: #b73337\|color: #333\|background.*#f7f7f7\|border.*#e5e5e5" \
  pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-bundle-site.unmin.css \
  | grep -v "var(--awa" | head -20
```

---

## Arquivo de Destino

`awa-bundle-site.unmin.css` → seção `=== COLOR SYSTEM ===`

```bash
cp awa-bundle-site.unmin.css awa-bundle-site.css
php bin/magento cache:clean
```

## Checklist
- [ ] Zero `color: #b73337` / `color: #333` hardcoded
- [ ] Hierarquia 4 níveis de texto implementada
- [ ] 4 variantes de mensagem Magento com semântica correta
- [ ] Links: `--awa-red` / hover `--awa-red-dark`
- [ ] Preço destaque `--awa-red`, tachado `--awa-gray-450`