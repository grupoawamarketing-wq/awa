---
description: "AWA Design System — Fase 2: Sistema de Formulários Global"
mode: agent
tools:
  - codebase
  - terminal
---

> Skill carregada automaticamente: `design-system` (bundles, tokens, BEM header, deploy).

Você é um especialista em UI/UX Magento 2. Implemente um sistema de formulários
consistente para AWA Motos. Não alterar outros elementos além de inputs/forms.

ELEMENTOS A PADRONIZAR:

INPUT / TEXTAREA / SELECT:
- Height: 44px (inputs), auto (textarea), 44px (select)
- Border: 1.5px solid var(--awa-color-border, #e5e5e5)
- Border-radius: var(--awa-radius-sm, 8px)
- Padding: 0 var(--awa-space-4) (12px lateral)
- Font-size: var(--awa-text-sm, 14px)
- Color: var(--awa-gray-700, #333)
- Background: #fff
- Transition: border-color 200ms, box-shadow 200ms
- Focus: border-color var(--awa-red), box-shadow 0 0 0 3px rgba(183,51,55,.12)
- Placeholder: color var(--awa-gray-400, #aaa)
- Error state: border-color #dc2626, background rgba(220,38,38,.04)
- Disabled: background var(--awa-bg-soft, #f7f7f7), opacity .65, cursor not-allowed

LABELS:
- Font-size: var(--awa-text-xs, 12px)
- Font-weight: 600
- Color: var(--awa-gray-600, #475569)
- Margin-bottom: var(--awa-space-1, 4px)
- Text-transform: uppercase
- Letter-spacing: 0.04em

GRUPOS (fieldset / .field):
- Gap entre campos: var(--awa-space-5, 20px)
- .required label::after: content " *", color var(--awa-red)

MENSAGENS DE ERRO:
- Color: #dc2626
- Font-size: var(--awa-text-xs)
- Margin-top: var(--awa-space-1)
- Display: flex, gap: 4px (ícone + texto)

SELETORES MAGENTO:
- body .page-wrapper .field input[type=text|email|tel|password|number]
- body .page-wrapper .field select
- body .page-wrapper .field textarea
- body .page-wrapper .field .label
- body .page-wrapper .mage-error (mensagens de erro)
- body .page-wrapper .field-error

ARQUIVO: awa-bundle-custom.unmin.css (nova seção "=== FORM SYSTEM ===")