---
description: "AWA Design System — Fase 6: Página de Produto (PDP)"
mode: agent
tools:
  - codebase
  - terminal
  - file
---

> Skill carregada automaticamente: `design-system` (bundles, tokens, BEM header, deploy).

Você é um especialista em UI/UX Magento 2. Melhore a página de produto (PDP) do
AWA Motos com design B2B profissional. Não alterar header/footer/nav.

LAYOUT PDP (Desktop ≥992px):
- 2 colunas: imagem 50% / info 50%
- Gap: var(--awa-space-7, 32px)
- Imagem principal: border 1px solid var(--awa-color-border), border-radius 8px

GALERIA DE IMAGENS:
- Thumbs: 60x60px, border 1.5px solid transparent
- Thumb ativa: border-color var(--awa-red)
- Hover thumb: border-color var(--awa-red-mid)

BLOCO DE INFORMAÇÕES:
- Nome: h1, font-size 24px, font-weight 700, color var(--awa-dark), margin-bottom 8px
- SKU: font-size 12px, color var(--awa-gray-400), margin-bottom 16px
- Preço: font-size 32px, font-weight 700, color var(--awa-red)
- Preço antigo: font-size 18px, line-through, color var(--awa-gray-400), margin-left 8px
- Disponibilidade: badge verde (#16a34a) "Em Estoque" / vermelho "Indisponível"

BOTÕES PDP:
- "Adicionar ao Carrinho": PRIMARY full-width 52px height, font-size 16px
- "Solicitar Orçamento": SECONDARY full-width, margin-top 8px
- Ícone de WhatsApp no botão de orçamento

ABAS (tabs):
- Descrição / Especificações / Compatibilidade / Avaliações
- Tab ativa: border-bottom 2px solid var(--awa-red), color var(--awa-red)
- Tab inativa: color var(--awa-gray-500), hover color var(--awa-red)

ARQUIVO: awa-bundle-custom.unmin.css (seção "=== PDP PROFESSIONAL ===")