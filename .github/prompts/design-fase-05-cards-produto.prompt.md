---
description: "AWA Design System — Fase 5: Cards de Produto (PLP)"
agent: "agent"
tools:
  - codebase
  - changes

---

> Skill carregada automaticamente: `design-system` (bundles, tokens, BEM header, deploy).

Você é um especialista em UI/UX Magento 2. Melhore os cards de produto na listagem
(PLP/Category) do AWA Motos. Não alterar estrutura do header ou footer.

CARD DE PRODUTO — DESIGN PROFISSIONAL B2B:

Estrutura visual:
- Background: #fff
- Border: 1px solid var(--awa-color-border, #e5e5e5)
- Border-radius: var(--awa-radius-sm, 8px)
- Box-shadow: 0 2px 8px rgba(0,0,0,.06)
- Overflow: hidden
- Transition: box-shadow 200ms, transform 200ms
- Hover: box-shadow 0 8px 24px rgba(0,0,0,.12), translateY(-2px)

Imagem do produto:
- Aspect-ratio: 1/1 (quadrada)
- Object-fit: contain
- Background: var(--awa-bg-soft, #f7f7f7)
- Padding: var(--awa-space-4)

Informações:
- Padding: var(--awa-space-4)
- Nome do produto: 2 linhas max (line-clamp 2), font-size 14px, weight 500
- SKU/Referência: font-size 11px, color var(--awa-gray-400), margin-bottom 4px
- Preço: font-size 18px, weight 700, color var(--awa-red)
- Botão "Adicionar": width 100%, margin-top var(--awa-space-3)

Badge "Novo" / "Promoção":
- Position: absolute, top 8px, left 8px
- Background var(--awa-red), color #fff
- Font-size 10px, font-weight 700, text-transform uppercase
- Padding: 2px 8px, border-radius var(--awa-radius-full)

SELETORES MAGENTO:
- body .page-wrapper .products.list .item.product
- body .page-wrapper .product-item-info
- body .page-wrapper .product-item-photo
- body .page-wrapper .product-item-name
- body .page-wrapper .price-box .price

ARQUIVO: awa-bundle-custom.unmin.css (seção "=== PRODUCT CARD ===")
