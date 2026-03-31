---
description: "AWA Design System — Fase 1: Sistema de Botões Global"
mode: agent
tools:
  - codebase
  - terminal
---

> Skill carregada automaticamente: `design-system` (bundles, tokens, BEM header, deploy).

Você é um especialista em UI/UX Magento 2. Implemente um sistema de botões consistente
para o site AWA Motos seguindo o design system existente.

ESCOPO: Apenas botões — não alterar layout, cores de fundo de seções, ou tipografia.

OBJETIVO: Padronizar todos os botões do site em 3 variantes:

VARIANTE PRIMARY (Ação principal):
- Background: var(--awa-red, #b73337)
- Color: #fff
- Border: none
- Border-radius: var(--awa-radius-sm, 8px)
- Padding: 0 var(--awa-space-6) → altura mínima: 44px (WCAG 2.5.8)
- Font-weight: 600
- Font-size: var(--awa-text-sm, 14px)
- Text-transform: uppercase
- Letter-spacing: 0.04em
- Transition: background 200ms, box-shadow 200ms, transform 150ms
- Hover: background var(--awa-red-dark, #8e2629), translateY(-1px), shadow
- Focus-visible: outline 2px var(--awa-red-dark), outline-offset 2px

VARIANTE SECONDARY (Ação secundária):
- Background: transparent
- Color: var(--awa-red)
- Border: 1.5px solid var(--awa-red)
- Border-radius: var(--awa-radius-sm)
- Padding: igual ao primary
- Hover: background var(--awa-red), color #fff

VARIANTE GHOST (Terciária):
- Background: transparent
- Color: var(--awa-gray-500)
- Border: 1px solid var(--awa-color-border, #e5e5e5)
- Hover: border-color var(--awa-red), color var(--awa-red)

SELETORES A PADRONIZAR (manter especificidade adequada):
- body .page-wrapper .action.primary (add to cart, checkout, etc.)
- body .page-wrapper .action.tocart
- body .page-wrapper .action.login
- body .page-wrapper button[type="submit"] dentro de forms Magento
- body .page-wrapper .btn-primary (Rokanthemes)
- body .page-wrapper .button.btn-cart
- Manter exceção: botões dentro de .nav-sections (cor branca, sem borda)

ARQUIVO: awa-bundle-custom.unmin.css (nova seção "=== BUTTON SYSTEM ===")