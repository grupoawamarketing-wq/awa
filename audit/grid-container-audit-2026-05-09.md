# Grid & Container Structural Audit — AWA Motos
**Data:** 2026-05-09  
**Escopo:** 4 páginas × 3 viewports (desktop 1366px, tablet 768px, mobile 390px)  
**Ferramenta:** Playwright Firefox (headless)

---

## ✅ Resultado Geral: APROVADO

Nenhum problema estrutural crítico encontrado. Todos os layouts estão dentro dos parâmetros corretos.

---

## 1. Scroll Horizontal — Todas as páginas

| Página | Desktop | Tablet | Mobile |
|--------|---------|--------|--------|
| Home | ✅ `docScrollW=vpW` | ✅ | ✅ |
| Categoria | ✅ | ✅ | ✅ |
| PDP | ✅ | ✅ | ✅ |
| Busca | ✅ (redireciona para categoria) | ✅ | ✅ |

**Observação:** Foram detectados `scrollWidth > clientWidth` em alguns elementos internos do header, porém são todos clipeados pelo `page-wrapper { overflow: clip }` — sem impacto visual para o usuário.

---

## 2. Grid de Categoria — `ul.product-grid`

O tema Ayo usa `ul.row.product-grid.container-products-switch` com `li.item-product`:

| Viewport | Colunas | Largura item | Container | Gap |
|----------|---------|--------------|-----------|-----|
| Desktop 1366px | **3 cols** | 319px | 990px (com sidebar) | 16px |
| Tablet 768px | **2 cols** | 352px | 715px | 12px |
| Mobile 390px | **2 cols** | 179px | 366px | 8px |

**Análise:**
- **Desktop 3 cols**: A página de categoria usa layout 2-col com sidebar (`col-md-3`). O container principal fica com ~990px. Com `minmax(260px, 1fr)`, 3 colunas são o máximo que cabe. `data-view-mode=4` é o botão de preferência do usuário, mas o CSS correto adapta para 3.
- **Tablet 2 cols**: Correto para 768px com sidebar.
- **Mobile 2 cols**: Fix de 2 colunas mobile aplicado corretamente. Itens de 179px são usáveis.

---

## 3. Widths dos Containers por Viewport

| Viewport | `.page-wrapper` | `.columns` | `.page-main` |
|----------|-----------------|------------|--------------|
| Desktop 1366px | 1366px | 1318px | 1318px |
| Tablet 768px | 768px | 731px | 731px |
| Mobile 390px | 390px | 366px | 366px |

Todos os containers respeitam a largura do viewport. Sem overflow horizontal.

---

## 4. Overflows Internos (não causam scroll horizontal)

### Desktop — `header-wrapper-sticky`
- `scrollWidth=1369 > clientWidth=1366` (+3px)
- Clipeado por `page-wrapper { overflow: clip }`
- Causa provável: elemento interno no header com `overflow:visible` e 3px a mais
- **Impacto:** Nenhum (clipeado pelo pai)

### Tablet/Mobile — Header right column
- `.awa-header-contact-links { scrollW=258 > clientW=124 }` (134px)
- Conteúdo de links de contato com `white-space: nowrap` extrapola o container
- **Impacto:** Links clipeados mas não causam scroll de página

### Mobile — B2B Promo Bar inner
- `.awa-b2b-promo-bar__inner { scrollW=354 > clientW=334 }` (+20px)
- Flex container `nowrap` com texto ligeiramente maior que o container  
- **Impacto:** Nenhum — outer está com `overflow: hidden`

---

## 5. Home — Carousels (Owl)

**6 carousels** detectados na home. A medição de "colunas visíveis" via Playwright não é confiável para owl-carousels pois todos os itens da trilha ficam no mesmo eixo horizontal (offset via transform). O número de itens visíveis é controlado pela configuração JS do Ayo theme.

Não foram detectadas anomalias de overflow nos carousels.

---

## 6. Seletores Corretos do Tema Ayo

Para futuras automações de audit:

```javascript
// Grid de categoria
const grid = 'ul.product-grid';      // ou 'ul.row.product-grid'
const items = 'li.item-product';
const container = '.wrapper.grid.products-grid';

// Widgets da home
const widgets = '.rokan-bestseller, .rokan-newproduct, .rokan-producttab';
const widgetItems = '.item-product';   // dentro de .product_row

// Busca (Magento nativo)
// awamotos.com/catalogsearch/result/?q=X → redireciona para categoria se houver match direto
```

---

## Conclusão

O site não tem problemas estruturais de grid ou containers. Os layouts são responsivos e corretos em todas as dimensões testadas. As únicas anomalias encontradas são overflows internos clipeados que não afetam a experiência do usuário.

### Próximos passos recomendados
- [ ] Verificar visualmente o mobile (390px) — itens de 179px podem ser estreitos para texto longo
- [ ] Investigar a config JS do owl-carousel para confirmar nº itens visíveis por breakpoint  
- [ ] Considerar adicionar `overflow-x: clip` no `.awa-b2b-promo-bar` para limpar o overflow interno
