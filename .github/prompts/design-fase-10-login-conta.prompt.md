---
description: "AWA Design System — Fase 10: Login, Cadastro e Conta do Cliente"
mode: agent
tools:
  - codebase
  - terminal
  - file
---

> Skill carregada automaticamente: `design-system` (bundles, tokens, BEM header, deploy).

Você é um especialista em UI/UX Magento 2. Melhore as páginas de autenticação e
conta do cliente do AWA Motos.

PÁGINAS: customer/account/login, customer/account/create, customer/account/

LAYOUT LOGIN/CADASTRO:
- Card centralizado, max-width 480px, border-radius 12px
- Box-shadow: 0 8px 32px rgba(0,0,0,.08)
- Padding: 40px
- Logo/título no topo (h2 "Bem-vindo de volta")
- Campos usando o sistema de formulários da Fase 2

DIVISOR "OU":
- Linha horizontal com "ou" no centro
- Color: var(--awa-gray-300)

LINKS "Esqueci a senha" / "Criar conta":
- Color: var(--awa-red), text-decoration none
- Hover: text-decoration underline

PÁGINA DE CONTA (dashboard):
- Sidebar de navegação: links de conta (Pedidos, Endereços, etc.)
- Link ativo: color var(--awa-red), border-left 3px solid var(--awa-red)
- Cards de resumo: Últimos pedidos, Endereço padrão

PEDIDOS (account/order):
- Tabela de pedidos: linha par com background var(--awa-bg-soft)
- Status badges:
  * Processando: background #dbeafe, color #1e40af
  * Completo: background #dcfce7, color #166534
  * Cancelado: background #fee2e2, color #991b1b
  * Pendente: background #fef3c7, color #92400e

SELETORES:
- .customer-account-login .page-wrapper
- .customer-account-create .page-wrapper
- .customer-account-index .page-wrapper
- .sales-order-history .page-wrapper

ARQUIVO: awa-bundle-custom.unmin.css (seção "=== CUSTOMER ACCOUNT ===")