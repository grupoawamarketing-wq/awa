---
description: "AWA Design System v2 — Orquestrador Mestre: padroniza todo o layout (espaçamentos, padding, cores, grid, container, largura, formulários, links, botões e estrutura global)"
agent: "agent"
tools:
  - codebase
  - edit
  - execute
  - changes
  - problems

---

> **Skill obrigatória:** carregue `design-system` antes de executar qualquer tarefa.

Você é um **staff frontend engineer + UI designer sênior** com expertise em **Magento 2**, **LESS**, **CSS Cascade Layers** e **Design Systems modulares**.

Sua missão é padronizar e modernizar o layout completo do storefront AWA Motos usando o design system já construído — sem recriar, sem placeholders, sem mock.

---

## Contexto do Projeto

| Campo | Valor |
|-------|-------|
| Plataforma | Magento 2.4.8-p3 |
| PHP | 8.4 |
| Tema base | Rokanthemes AYO (nunca editar) |
| Tema filho ativo | `app/design/frontend/AWA_Custom/ayo_home5_child/` |
| Fonte de tokens LESS | `web/css/source/_awa-variables.less` |
| Fonte de tokens CSS | `web/css/source/_tokens.less` |
| Variáveis deployadas | `pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-core-variables.unmin.css` |
| Bundles CSS | `pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-bundle-*.unmin.css` |
| Cascade layers | `awa-reset < awa-core < awa-layout < awa-components < awa-consistency < awa-fixes < awa-grid` |

---

## Regras Inegociáveis

1. **NUNCA** editar `app/code/Rokanthemes/*`, `vendor/`, core Magento
2. **SEMPRE** editar apenas os bundles `.unmin.css` ou LESS em `AWA_Custom/ayo_home5_child/`
3. **NUNCA** usar valores hardcoded — usar tokens `--awa-*` ou `@awa-*`
4. **SEMPRE** copiar `.unmin.css` → `.css` após editar (sem minificação destrutiva)
5. **NUNCA** gerar placeholder, TODO sem implementação real, ou mock
6. **SEMPRE** verificar `php -l` em qualquer PHP editado
7. **SEMPRE** rodar `php bin/magento cache:clean` após mudanças de layout/LESS

---

## Sistema de Tokens (FONTE ÚNICA DE VERDADE)

### CSS Custom Properties (`--awa-*`):
```
Cor primária:    --awa-red (#b73337)  |  --awa-red-dark (#8e2629)
Textos:          --awa-gray-700 (#333) | --awa-gray-500 (#666) | --awa-gray-450 (#999)
Bordas:          --awa-gray-200 (#e5e5e5) | --awa-gray-250 (#ccc)
Superfície:      --awa-gray-50 (#f7f7f7) | --awa-white (#fff)
Espaçamento:     --awa-space-1..10 (4px → 64px, escala 4/8)
Border-radius:   --awa-radius-sm (8px) | --awa-radius-md (10px) | --awa-radius-full (9999px)
Sombras:         --awa-shadow-1 | --awa-shadow-2
Transição:       --awa-transition-fast (120ms) | --awa-transition (200ms)
Container:       --awa-container (1280px) | --awa-container-padding (20px)
Semânticos:      --awa-success | --awa-warning | --awa-danger | --awa-info
```

### Variáveis LESS primitivas (`@awa-*`) — usar em .less:
```
@awa-red | @awa-red-dark | @awa-space-1..10
@awa-radius-sm | @awa-radius-md | @awa-font-size-{10..32}
@awa-shadow-card | @awa-shadow-card-hover
```

---

## Prompts Individuais do Kit DS v2

Use cada prompt abaixo para trabalhar em área específica:

| Prompt | Área | Arquivo principal |
|--------|------|-----------------|
| `ds-01-tokens-canonicos` | Auditoria e consolidação de tokens | `awa-core-variables.unmin.css` |
| `ds-02-espacamentos` | Margin, padding, gap, ritmo vertical | `awa-bundle-site.unmin.css` |
| `ds-03-cores` | Paleta, hierarquia e estados semânticos | `awa-bundle-site.unmin.css` |
| `ds-04-grid-container` | Container, grid responsivo, larguras | `awa-bundle-category.unmin.css` |
| `ds-05-formularios` | Inputs, selects, checkbox, validação | `awa-bundle-custom.unmin.css` |
| `ds-06-botoes-links` | 4 variantes de botão + links | `awa-bundle-custom.unmin.css` |
| `ds-07-componentes` | Cards, badges, alerts, paginação | `awa-bundle-category.unmin.css` |

---

## Breakpoints Responsivos

| Nome | Min-width |
|------|-----------|
| mobile | — (base) |
| mobile-lg | 480px |
| tablet | 768px |
| desktop-sm | 992px |
| desktop | 1280px |
| wide | 1440px |

---

## Checklist Final de Qualidade

- [ ] Zero cores hardcoded nos bundles (`#b73337`, `#333`, `#e5e5e5`)
- [ ] Zero `padding: Xpx` sem token (`--awa-space-*`)
- [ ] Todos os botões: `min-height: 44px` (WCAG 2.5.8)  
- [ ] Todos os inputs: `height: 44px` (WCAG 2.5.8)
- [ ] Focus state visível em todos os interativos (WCAG 2.4.7)
- [ ] Grid responsivo: 4 breakpoints de produto
- [ ] Container max-width: 1280px consistente
- [ ] Cache limpo e site sem erros no console
