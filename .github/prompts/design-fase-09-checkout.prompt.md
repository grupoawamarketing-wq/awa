---
description: "AWA Design System — Fase 9: Checkout Profissional"
mode: agent
tools:
  - codebase
  - terminal
---

> Skill carregada automaticamente: `design-system` (bundles, tokens, BEM header, deploy).

Você é um especialista em UI/UX Magento 2. Melhore o visual do checkout do Magento 2
mantendo toda a lógica KnockoutJS/RequireJS intacta.

PRINCÍPIO: Apenas CSS — NUNCA alterar .js ou .phtml do checkout sem necessidade.

LAYOUT CHECKOUT:
- 2 colunas: formulário (60%) + resumo do pedido (40%)
- Gap: var(--awa-space-7)
- Sticky summary em desktop (position: sticky, top: 80px)

STEPS (Endereço → Pagamento):
- Step ativo: número em circle var(--awa-red), label bold, color var(--awa-dark)
- Step completo: ✓ em circle var(--awa-green, #16a34a)
- Step pendente: número em circle var(--awa-gray-200), label color var(--awa-gray-400)
- Linha conectora: background var(--awa-gray-200), 2px height

RESUMO DO PEDIDO:
- Card: border 1px solid var(--awa-color-border), border-radius 8px, padding 24px
- Título "Resumo": font-weight 700, border-bottom 2px solid var(--awa-red), pb 12px
- Item: flex, imagem 56px, nome + qty à esquerda, preço à direita
- Subtotal / Frete / Total: tabela com font-weight 700 no total

MÉTODOS DE PAGAMENTO:
- Card de cada método: border 1.5px solid transparent, border-radius 8px, padding 16px
- Selecionado: border-color var(--awa-red), background rgba(183,51,55,.04)
- Ícone do método: height 28px

BOTÃO "FINALIZAR PEDIDO":
- PRIMARY, width 100%, height 56px, font-size 16px, font-weight 700
- Ícone de cadeado à esquerda (segurança)

SELETORES MAGENTO CHECKOUT:
- .checkout-index-index .page-wrapper
- .checkout-index-index .opc-wrapper
- .checkout-index-index .opc-progress-bar
- .checkout-index-index .payment-method

ARQUIVO: awa-bundle-custom.unmin.css (seção "=== CHECKOUT PROFESSIONAL ===")
ATENÇÃO: Testar no modo guest e logado. Não quebrar validações JS.