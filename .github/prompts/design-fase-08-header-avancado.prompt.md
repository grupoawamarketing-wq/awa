---
description: "AWA Design System — Fase 8: Header Avançado (Complemento)"
mode: agent
tools:
  - codebase
  - terminal
---

> Skill carregada automaticamente: `design-system` (bundles, tokens, BEM header, deploy).

Você é um especialista em UI/UX Magento 2. O header básico já está implementado
com classes BEM (awa-utility-bar / awa-main-header / awa-nav-bar).
Adicione refinamentos avançados SEM alterar o que já funciona.

VERIFICAR ANTES: Ler _awa-header-professional.less e header.phtml antes de qualquer edição.

REFINAMENTOS A ADICIONAR:

1. MEGA MENU DROPDOWN:
- Background: #fff, border-radius 0 0 8px 8px, box-shadow 0 16px 32px rgba(0,0,0,.12)
- Colunas: grid de 3-4 colunas com títulos em var(--awa-red)
- Imagem destacada na última coluna (categoria em promoção)
- Animação: opacity 0→1 + translateY(-4px→0), duration 180ms

2. BARRA DE PESQUISA AVANÇADA:
- Autocomplete: card com shadow, borda arredondada 8px
- Sugestões de produtos: imagem 48x48 + nome + preço
- Categorias sugeridas: badge com cor de categoria
- "Ver todos os resultados": link em var(--awa-red) no final

3. MINICART FLYOUT:
- Width: 380px, fixed position, slide da direita
- Header: "Seu Carrinho (N itens)"
- Item: imagem 64px + nome + qty + preço
- Footer: subtotal + botão "Finalizar Compra" PRIMARY full-width

4. STICKY HEADER CONDENSADO:
- Já implementado via JS — verificar comportamento ao scroll
- Garantir que transition: height 220ms funciona suavemente

ARQUIVO: _awa-header-professional.less → recompilar para awa-bundle-core.unmin.css