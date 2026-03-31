---
description: "AWA Design System — Fase 4: Container, Grid e Espaçamentos"
agent: "agent"
tools:
  - codebase
  - edit
  - execute
  - changes

---

> Skill carregada automaticamente: `design-system` (bundles, tokens, BEM header, deploy).

Você é um especialista em UI/UX Magento 2. Normalize o sistema de container, grid e
espaçamentos do AWA Motos. NÃO alterar background colors ou estrutura de header/nav.

CONTAINER PADRÃO:
- Max-width: var(--awa-container, 1280px)
- Padding lateral: var(--awa-space-4, 16px) em mobile, var(--awa-space-6, 24px) em ≥768px
- Margin: 0 auto

SELETORES DE CONTAINER A NORMALIZAR:
- .container, .page-wrapper > .container, .columns, .column.main
- Inspecionar e remover max-width conflitantes do themes5.css via override

GRID DE PRODUTOS (PLP / Category):
- Desktop (≥992px): 4 colunas, gap var(--awa-space-5, 20px)
- Tablet (768–991px): 3 colunas, gap var(--awa-space-4)
- Mobile (480–767px): 2 colunas, gap var(--awa-space-3)
- Mobile pequeno (<480px): 1 coluna

ESPAÇAMENTOS DE SEÇÕES:
- Margem entre seções: var(--awa-space-10, 64px) desktop, var(--awa-space-7, 32px) mobile
- Padding interno de cards: var(--awa-space-4, 16px) a var(--awa-space-5, 20px)
- Gap entre itens em listas: var(--awa-space-3, 12px)

SIDEBAR (filtros PLP):
- Width: 240px fixo em desktop
- Gap com conteúdo principal: var(--awa-space-7, 32px)

ARQUIVO: awa-bundle-custom.unmin.css (seção "=== LAYOUT SYSTEM ===")
ATENÇÃO: Verificar conflitos com grid do Rokanthemes antes de fazer override.
