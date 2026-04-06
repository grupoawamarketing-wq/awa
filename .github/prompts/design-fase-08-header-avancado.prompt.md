---
description: "AWA Design System — Fase 8: Ajuste Sistemático do Header Profissional"
agent: "agent"
tools:
  - codebase
  - edit
  - execute
  - changes
---

# AWA Motos — Ajuste Sistemático do Header Profissional

## ⛔ LEIA ANTES DE QUALQUER EDIÇÃO

Antes de escrever uma linha de código, leia obrigatoriamente:

1. `AGENTS.md` — regras universais do workspace (proibições absolutas)
2. `.github/instructions/frontend-js-css.instructions.md` — padrões LESS e deploy
3. `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-variables.less` — tokens canônicos
4. `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-header-professional.less` — arquivo que você vai editar

Nunca edite os seguintes arquivos — são componentes finalizados e travados:
- `_awa-variables.less` (só adicionar tokens, nunca remover)
- `_extend.less` (tokens globais — não tocar)
- Qualquer arquivo em `vendor/` ou core Magento

---

## Contexto do Sistema

```
Plataforma : Magento 2.4.8-p3 Community Edition
Tema filho  : AWA_Custom/ayo_home5_child (herda de rokanthemes/ayo)
PHP         : 8.4 · Servidor: Nginx + php-fpm · OS: Ubuntu (SSH remoto)
CSS engine  : LESS compilado pelo Magento (não há watch automático)
DS gate     : body.awa-ds — obrigatório em todos os seletores scoped
Arquivo alvo: web/css/source/_awa-header-professional.less
Bundle gerado: awa-bundle-core.css (inclui o arquivo acima)
```

---

## Anatomia do Header — DOM Real Renderizado

```html
<!-- ROOT — classe gate do tema profissional -->
<header class="awa-site-header awa-header-professional">

  <!-- UTILITY BAR — fundo @awa-color-primary-dark -->
  <div class="awa-utility-bar top-header">
    <div class="container">
      <div class="awa-utility-bar__inner">
        <div class="top-bar-left">
          <!-- Localização · Horário · Telefone -->
          <a class="awa-header-quote-cta" href="/cotacao/">Solicitar Orçamento</a>
        </div>
        <div class="top-account">
          <ul class="header links">
            <li><a href="/customer/account/">Minha Conta</a></li>
            <li><a href="/b2b/account/login/">Entrar</a></li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- MAIN HEADER — fundo branco, 3 colunas flex -->
  <div class="awa-main-header">
    <div class="container">
      <div class="awa-main-header__inner">

        <!-- col 1: logo · flex: 0 0 clamp(190px,18vw,228px) -->
        <div class="awa-header-brand">
          <a class="logo" href="/"><img src="..." alt="AWA Motos" /></a>
        </div>

        <!-- col 2: busca · flex: 1 1 0 -->
        <div class="awa-header-search-col">
          <div class="awa-search-wrapper">
            <div class="block block-search"> ... </div>
          </div>
        </div>

        <!-- col 3: contato + carrinho · flex: 0 0 clamp(210px,18vw,248px) -->
        <div class="awa-header-right-col">
          <div class="awa-header-contact-slot">
            <!-- B2B trust badges (JS: opacity 0 → 1 on load) -->
            <div class="awa-b2b-trust-badges" aria-hidden="true"> ... </div>
            <!-- Links de contato (WhatsApp, Cotação) -->
            <div class="awa-header-contact-links">
              <div class="awa-header-contact-links__items">
                <a class="awa-header-contact-links__item awa-header-contact-links__item--whatsapp" href="...">
                  <span class="awa-header-contact-links__icon"><i class="..."></i></span>
                  <span class="awa-header-contact-links__text">...</span>
                </a>
              </div>
            </div>
            <!-- Bloco Rokanthemes (hotline / account) -->
            <div class="hoteline_header"> ... </div>
          </div>

          <!-- Minicart -->
          <div class="awa-header-minicart">
            <span class="awa-minicart-label">Cotação / Carrinho</span>
            <div class="minicart-wrapper">
              <a class="action showcart" href="/checkout/cart/"> ... </a>
              <!-- Flyout: .block-minicart — só visível quando ._active -->
              <div class="block-minicart"> ... </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- NAV BAR — fundo @awa-color-primary (vermelho) -->
  <div class="awa-nav-bar">
    <div class="container">
      <div class="awa-nav-bar__inner">
        <div class="awa-header-categories">
          <div class="title-category-dropdown">☰ Todas as categorias</div>
        </div>
        <nav class="awa-header-primary-nav" id="awa-primary-navigation">
          <div class="top-menu">
            <ul>
              <li><a href="...">Lançamentos</a></li>
              <li><a href="...">Catálogo</a></li>
            </ul>
          </div>
        </nav>
        <div class="awa-header-locale"> ... </div>
      </div>
    </div>
  </div>

  <!-- STICKY WRAPPER — Rokanthemes, fica dentro da nav bar no DOM -->
  <!-- Conteúdo oculto por padrão; visível só quando .awa-header-condensed ativo -->
  <div class="header-wrapper-sticky">
    <div class="container-header-sticky"> ... </div>
  </div>

</header>
```

---

## Classes de Estado — Injetadas por JavaScript (não existem no load inicial)

| Classe | Script responsável | Quando é aplicada |
|--------|-------------------|-------------------|
| `.awa-header-condensed` | `awa-header-sticky.js` | Scroll > 60px da página |
| `.awa-header-exp-b` | `awa-header-sticky.js` | Sempre (variante de layout) |
| `.is-awa-mobile-open` | JS do drawer | Menu mobile aberto |
| `._active` em `.block-minicart` | `Magento_Checkout/js/sidebar` | Minicart aberto |
| `.awa-mobile-drawer-open` | JS do drawer | Aplicado no `<body>` |

⚠️ Nunca escreva CSS que dependa dessas classes como estado inicial — elas não existem no HTML estático.

---

## Tokens Disponíveis (use-os, nunca hardcode valores)

```less
// Cores
@awa-color-primary:      #b73337;   // vermelho AWA
@awa-color-primary-dark: #8e2629;   // hover / utility bar bg
@awa-color-white:        #ffffff;
@awa-color-border:       #e5e5e5;
@awa-color-bg-soft:      #f7f7f7;
@awa-neutral-400: #94a3b8;  @awa-neutral-500: #64748b;
@awa-neutral-600: #475569;  @awa-neutral-700: #334155;
@awa-neutral-800: #1e293b;  @awa-neutral-900: #0f172a;

// Espaçamento
@awa-space-1: 4px;  @awa-space-2: 8px;   @awa-space-3: 12px;
@awa-space-4: 16px; @awa-space-5: 20px;  @awa-space-6: 24px;

// Tipografia
@awa-font-size-12: 12px;  @awa-font-size-13: 13px;
@awa-font-size-14: 14px;  @awa-font-size-15: 15px;
@awa-weight-medium: 500;  @awa-weight-semibold: 600;  @awa-weight-bold: 700;

// Shape
@awa-radius-sm: 8px;  @awa-radius-md: 10px;  @awa-radius-full: 9999px;

// Controles
@awa-control-height: 44px;  @awa-control-height-sm: 36px;

// Breakpoints
@awa-breakpoint-xs: 480px;   @awa-breakpoint-sm: 768px;
@awa-breakpoint-md: 992px;   @awa-breakpoint-lg: 1200px;

// Foco / Acessibilidade
@awa-focus-ring:        0 0 0 3px fade(@awa-color-primary, 25%);
@awa-focus-ring-offset: 2px;

// Transição
@awa-transition: 250ms ease;
```

---

## ⚠️ PROBLEMA A RESOLVER — SUBSTITUA ESTE BLOCO ANTES DE EXECUTAR

```
[DESCREVA AQUI O PROBLEMA COM PRECISÃO CIRÚRGICA]

Formato esperado:
  COMPONENTE AFETADO: qual bloco do header (ex: .awa-header-right-col)
  BREAKPOINT:         em qual viewport ocorre (ex: 768–1200px)
  SINTOMA VISUAL:     o que o usuário vê de errado (ex: texto cortado, overflow, sobreposição)
  CAUSA SUSPEITA:     se souber (ex: flex container sem min-width: 0 nos filhos)
  ESTADO JS:          ocorre no estado padrão ou em .awa-header-condensed / ._active?
```

---

## Regras de Codificação (resumo das fontes canônicas)

```
✅ Usar SEMPRE: tokens @awa-* para cores, espaços, raios, sombras e breakpoints
✅ Usar SEMPRE: body.awa-ds como scope de seletores (ou body .page-wrapper para alta especificidade)
✅ Nesting LESS: máximo 3 níveis de profundidade
✅ !important: APENAS para sobrescrever Rokanthemes/ayo parent (documente por que)
✅ prefers-reduced-motion: bloco @media obrigatório ao fim de todo bloco com transition/animation
✅ Acessibilidade: min-height 44px em elementos interativos, :focus-visible com @awa-focus-ring

❌ Nunca: valores hex, px ou rem hardcoded (use tokens)
❌ Nunca: media queries com valores literais como 768px (use @awa-breakpoint-sm)
❌ Nunca: editar _awa-variables.less, _extend.less ou arquivos vendor/core
❌ Nunca: criar novos arquivos LESS/CSS (edite _awa-header-professional.less)
❌ Nunca: LESS nesting > 3 níveis
```

---

## Formato Obrigatório do Output

Mostre **somente o trecho que muda**, em formato diff localizado:

```less
// ── LOCALIZAÇÃO no arquivo: _awa-header-professional.less, linha ~XXX ──

// ANTES (cópia exata — não resumir, não parafrasear):
.awa-header-right-col {
    flex: 0 0 ~"clamp(210px, 18vw, 248px)";
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    width: auto;
    max-width: 248px;
    min-width: 0;
}

// DEPOIS (proposta com justificativa inline):
.awa-header-right-col {
    flex: 0 0 ~"clamp(210px, 18vw, 248px)";
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    width: auto;
    max-width: 248px;
    min-width: 0;
    overflow: hidden; // [FIX] previne vazamento do texto truncado em 1024px
}
```

Não reescreva o arquivo inteiro. Não reformate linhas que não mudam.

---

## Deploy e Validação

Após editar `_awa-header-professional.less`, execute na ordem exata:

```bash
# 1. Recompilar assets estáticos (obrigatório para LESS)
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f \
  --theme AWA_Custom/ayo_home5_child

# 2. Limpar cache
sudo -u www-data php bin/magento cache:flush

# 3. Verificar se o seletor novo foi compilado no bundle correto
grep -r "SEU_SELETOR_AQUI" pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/ | head -5

# 4. Verificar logs (erros de compilação LESS aparecem aqui)
tail -20 var/log/system.log
tail -20 var/log/exception.log
```

**Validação visual (pelo Jess no browser):**
1. Hard reload: Ctrl+Shift+R
2. Abrir DevTools → Network → filtrar por `.css` → confirmar que `awa-bundle-core.css` tem timestamp novo
3. Testar nos breakpoints: 375px · 768px · 1024px · 1280px · 1440px
4. Testar estados: scroll (condensed) · minicart aberto · menu mobile aberto

---

## Referências Rápidas

| Necessidade | Onde encontrar |
|-------------|----------------|
| Todos os tokens | `web/css/source/_awa-variables.less` |
| Aplicação global dos tokens | `web/css/source/_extend.less` |
| Fixes anteriores do header | `web/css/source/_awa-ux-audit-fixes.less:604–717` |
| Regras universais do projeto | `AGENTS.md` |
| Padrões LESS e deploy | `.github/instructions/frontend-js-css.instructions.md` |
| Inventário de bundles | `CSS_INVENTORY.md` |
| Issues abertos do header | `PLANO_MELHORIAS_DESIGN_2026Q2.md § Componente: Cabeçalho Profissional` |
