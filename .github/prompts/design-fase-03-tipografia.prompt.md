---
description: "AWA Design System — Fase 3: Tipografia e Hierarquia Global"
agent: "agent"
tools:
  - codebase
  - edit
  - execute
  - changes

---

> Skill carregada automaticamente: `design-system` (bundles, tokens, BEM header, deploy).

Você é um especialista em UI/UX Magento 2. Padronize a hierarquia tipográfica do site
AWA Motos. Não alterar cores de fundo ou layout estrutural.

ESCALA TIPOGRÁFICA (usar tokens existentes):
- h1: var(--awa-text-2xl, 32px), weight 700, color var(--awa-dark, #333), lh 1.2
- h2: var(--awa-text-xl, 24px), weight 700, color var(--awa-dark), lh 1.3
- h3: var(--awa-text-lg, 18px), weight 600, color var(--awa-dark), lh 1.4
- h4: var(--awa-text-base, 16px), weight 600, color var(--awa-dark), lh 1.4
- h5/h6: var(--awa-text-sm, 14px), weight 600, color var(--awa-gray-600), lh 1.5
- p (body): var(--awa-text-sm, 14px), weight 400, color var(--awa-gray-600), lh 1.6
- small/caption: var(--awa-text-xs, 12px), color var(--awa-gray-500)

LINKS GERAIS (fora de nav/header):
- Color: var(--awa-red) — mas NÃO usar !important no a global
- Escopar em: body .page-wrapper .main a, body .page-wrapper .content a
- Hover: var(--awa-red-dark), text-decoration underline

BREADCRUMBS:
- Font-size: var(--awa-text-xs)
- Color: var(--awa-gray-500)
- Separador: / em rgba(0,0,0,.3)
- Item atual: color var(--awa-dark), font-weight 500

PREÇOS:
- Preço principal: var(--awa-text-xl), font-weight 700, color var(--awa-red)
- Preço antigo/riscado: var(--awa-text-sm), color var(--awa-gray-400), text-decoration line-through
- Badge "DESCONTO": background var(--awa-red), color #fff, font-size 11px, border-radius var(--awa-radius-full)

SELETORES: Escopar tudo em body .page-wrapper
ARQUIVO: awa-bundle-custom.unmin.css (seção "=== TYPOGRAPHY SYSTEM ===")
