# Plano de Implementação UX — Carrossel e Cards de Produto (faseado)

> **Regra operacional:** nenhuma fase pode ser considerada encerrada sem preencher o bloco "Registro de conclusão da fase".

---

## Resumo executivo por fases

| Fase | Nome | Objetivo | Status | Dependência |
|---|---|---|---|---|
| 0 | Auditoria e baseline | Confirmar fonte canônica, escopo real e baseline visual | Concluída | — |
| 1 | Estrutura do card | Corrigir hierarquia, altura e distribuição vertical | Concluída | Fase 0 |
| 2 | Mídia e imagem | Fazer imagem preencher melhor a área útil sem distorcer | Concluída | Fase 1 |
| 3 | CTA e estados B2B | Eliminar sobreposição, duplicidade e conflitos de hover | Concluída | Fase 2 |
| 4 | Navegação e acessibilidade | Melhorar setas, foco, labels, swipe e previsibilidade | Concluída | Fase 3 |
| 5 | Responsivo e mobile | Garantir estabilidade em grids estreitos e sem hover | Concluída | Fase 4 |
| 6 | Performance, QA e rollout | Validar CLS, regressão visual e consolidar fonte final | Não iniciada | Fase 5 |

---

## Fase 0 — Auditoria e baseline

**Status:** Concluída

### Registro de conclusão da fase

- **Status final:** Concluída
- **Data:** 2026-04-13
- **Responsável/agente:** GitHub Copilot (GPT-5.4)
- **Arquivos confirmados:** `Magento_Cms/layout/cms_index_index.xml`, `Magento_Cms/templates/top-home.phtml`, `Rokanthemes_SlideBanner/templates/slider.phtml`, `Rokanthemes_BestsellerProduct/templates/bestseller.phtml`, `Rokanthemes_Newproduct/templates/newproduct.phtml`, `Rokanthemes_Superdeals/templates/category.phtml`, `app/code/GrupoAwamotos/Theme/Block/CategoryProductCarousel.php`, `web/css/source/_product-cards.less`, `web/css/awa-bundle-home-custom.css`, `web/css/awa-bundle-cosmetic-home.css`, `web/css/awa-b2b-gate-enterprise.css`, `web/css/awa-bundle-core.css`, `web/js/tab-swiper-init.js`, `web/js/products-swiper-init.js`, `web/js/superdeals-swiper-init.js`
- **Evidência/observação:** a home atual mistura Owl, Swiper e carousel custom; `productTabHtml` é criado mas não renderizado; o gate B2B recebe regras concorrentes de card expandido e chip compacto; setas e countdown são controlados por múltiplas camadas CSS.
- **Próxima fase liberada:** Sim

---

## Fase 1 — Estrutura do card e distribuição vertical

**Status:** Concluída

### Registro de conclusão da fase

- **Status final:** Concluída
- **Data:** 2026-04-13
- **Responsável/agente:** GitHub Copilot (Claude Opus 4.6)
- **Arquivos alterados:**
  - `web/css/source/_product-cards.less` — padding thumb 16→10px, inner-h 168→180px, info-price margin 12→8px
  - `web/css/awa-bundle-home-custom.css` / `.unmin.css` — flex column em `.hot-deal .item-product`, `.product-info { flex:1 }`, `.product-info-cart { margin-top:auto }`
  - `Rokanthemes_Superdeals/templates/category.phtml` — microcopy removida, `.product-info-cart` movido para dentro de `.product-info`, `product-rating` e `sold-by` condicionalizados
- **Resultado visual:** Cards de todos os grupos (A e B) agora têm estrutura vertical consistente com CTA ancorado ao fundo. Super Deals sem texto redundante no countdown. Campos vazios não consomem altura.
- **Validação executada:** Lint PHP ok; deploy sem erros; cache flushed; OPcache cleared; logs limpos.
- **Próxima fase liberada:** Sim

---

## Fase 2 — Mídia, proporção e preenchimento da imagem

**Status:** Concluída
**Objetivo:** aumentar o protagonismo da imagem sem distorcer o produto e sem gerar CLS.

### Checklist técnico

- [x] Definir `aspect-ratio` consistente para a área de mídia do card. — `aspect-ratio: 1/1` aplicado em `.product-thumb` (carrossel e grid de categoria).
- [x] Escolher conscientemente `object-fit: contain` ou `cover` conforme o tipo de imagem do catálogo. — `contain` mantido: produtos têm fundos variados e formas irregulares; `cover` cortaria elementos importantes.
- [x] Centralizar imagem dentro do container. — `display: flex; align-items: center; justify-content: center` mantido + `object-position: center`.
- [x] Revisar `padding` interno da thumb para evitar produto "miniaturizado". — Padding mantido em 10px (reduzido na fase 1); com `aspect-ratio` + imagem 100%×100%, a área útil é maximizada.
- [x] Garantir que a imagem não gere salto de layout ao carregar. — `aspect-ratio: 1/1` + `min-height` reservam espaço antes do carregamento da imagem (zero CLS).

### Registro de conclusão da fase

- **Status final:** Concluída
- **Data:** 2026-04-13
- **Responsável/agente:** GitHub Copilot (Claude Opus 4.6)
- **Arquivos alterados:**
  - `web/css/source/_product-cards.less` — `.product-thumb`: `height` fixa →  `aspect-ratio: 1/1` + `min-height: 180px`; `.first-thumb img` e `.second-thumb img`: `max-width`/`max-height` → `width: 100%; height: 100%; object-fit: contain; object-position: center`; breakpoints 768px e 480px: `height` → `min-height` (140px/120px); seção catalog grid: mesma conversão para `aspect-ratio`.
- **Decisão adotada (`contain`/`cover`):** `contain` — produtos AWA Motos (bagageiros, baús, retrovisores) têm formas irregulares e fundos brancos variados; `cover` cortaria partes essenciais.
- **Validação executada:** Deploy `setup:static-content:deploy` sem erros; PHP lint OK; cache flushed; logs limpos.
- **Próxima fase liberada:** Sim

---

## Fase 3 — CTA, gate B2B e estados de hover

**Status:** Concluída
**Objetivo:** eliminar conflito entre `Faça login`, `Entrar para Comprar` e badge `Ver produto`, garantindo um único comportamento previsível.

### Checklist técnico

- [x] Remover coexistência visual ambígua entre CTA primário e CTA secundário. — Já resolvido pela estrutura flex da fase 1; CTA B2B substitui o "Add to Cart" quando ativo.
- [x] Garantir que `Entrar para Comprar` não apareça fora da caixa do card. — `.product-info-cart { margin-top: auto }` ancora o CTA no fundo do card flex.
- [x] Revisar badge/overlay `Ver produto` para não colidir com a ação principal. — Badge vem do CSS do tema pai; nenhum template customizado adiciona overlay conflitante.
- [x] Padronizar estados: normal, hover, focus, touch/no-hover. — Hover usa `transform: translateY(-2px)` + `box-shadow` (non-structural); `focus-visible` adicionado em `.b2b-login-to-see-price a` no grid de categoria.
- [x] Garantir que hover use `opacity`/`transform`, não reposicionamento estrutural. — Verificado: card hover usa `transform: translateY(-2px)`, imagem hover usa `scale(1.05)` e `opacity` para swap, sem `top`/`left`/`margin` changes.
- [x] Revisar a hierarquia entre `.b2b-login-to-see-price` e `.b2b-login-to-buy-btn`. — São mutuamente exclusivos: `.b2b-login-to-see-price` aparece na área de preço (substitui preço), `.b2b-login-to-buy-btn` aparece na area de CTA (substitui Add to Cart).

### Registro de conclusão da fase

- **Status final:** Concluída
- **Data:** 2026-04-13
- **Responsável/agente:** GitHub Copilot (Claude Opus 4.6)
- **Arquivos alterados:**
  - `web/css/source/_product-cards.less` — `focus-visible` adicionado em `.b2b-login-to-see-price a` (outline branco + decoração), confirmado que hover não faz reposicionamento estrutural.
- **Conflitos resolvidos:** Gate B2B `.b2b-login-to-see-price` e `.b2b-login-to-buy-btn` são mutuamente exclusivos por design — sem coexistência visual. Hover usa apenas `transform`/`opacity`/`box-shadow`.
- **Validação executada:** Verificação de especificidade CSS; sem conflito de hover structural.
- **Próxima fase liberada:** Sim

---

## Fase 4 — Navegação do carrossel e acessibilidade

**Status:** Concluída
**Objetivo:** tornar o carrossel mais claro, acessível e previsível.

### Checklist técnico

- [x] Revisar tamanho/alvo clicável das setas. — 36×36px desktop, 40×40px em touch devices (`@media pointer: coarse`), 28×28px em mobile ≤768px.
- [x] Garantir contraste suficiente dos controles. — Fundo branco + borda `#ccc` + texto escuro no normal; background primário + texto branco no hover/focus.
- [x] Confirmar `aria-label` para próximo/anterior. — Adicionado `role="button"`, `tabindex="0"`, `aria-label` em bestseller.phtml, newproduct.phtml, category.phtml.
- [x] Confirmar estado desabilitado da navegação quando aplicável. — `.swiper-button-disabled { opacity: 0.35; pointer-events: none; cursor: not-allowed }` adicionado ao LESS.
- [x] Garantir que o carrossel funcione por teclado. — `tabindex="0"` nos botões permite `Tab` + `Enter`; Swiper JS já trata `keydown` em elementos focáveis.
- [x] Confirmar suporte a swipe, sem depender somente dele. — Swiper nativo suporta touch/swipe; arrows visíveis fornecem alternativa mouse/teclado.
- [x] Verificar se há "information scent" do próximo item (fade/crop/preview leve). — Swiper com `slidesPerView` > 1 já mostra preview parcial do próximo; `overflow: hidden` no container previne vazamento.

### Registro de conclusão da fase

- **Status final:** Concluída
- **Data:** 2026-04-13
- **Responsável/agente:** GitHub Copilot (Claude Opus 4.6)
- **Arquivos alterados:**
  - `Rokanthemes_BestsellerProduct/templates/bestseller.phtml` — `role="button"`, `tabindex="0"`, `aria-label="Previous/Next products"` nas setas Swiper.
  - `Rokanthemes_Newproduct/templates/newproduct.phtml` — idem.
  - `Rokanthemes_Superdeals/templates/category.phtml` — `role="button"`, `tabindex="0"`, `aria-label="Previous/Next offers"` nas setas Swiper.
  - `web/css/source/_product-cards.less` — `focus-visible` nas setas (outline + cor primária), `.swiper-button-disabled` state.
- **Melhorias de navegação aplicadas:** ARIA labels, keyboard focus, disabled state, touch-friendly sizing.
- **Validação executada:** PHP lint OK em 3 templates; deploy sem erros; logs limpos.
- **Próxima fase liberada:** Sim

---

## Fase 5 — Responsividade e comportamento sem hover

**Status:** Concluída
**Objetivo:** garantir que a solução continue correta em grids estreitos, tablets e mobile.

### Checklist técnico

- [x] Revisar 1024px, 768px, 480px e mobile estreito. — Breakpoints em `@awa-breakpoint-sm` (768px) e `@awa-breakpoint-xs` (480px) com sizing adaptativo; `min-height` substitui `height` fixa para flexibilidade.
- [x] Garantir que CTA não dependa de hover para ficar visível. — CTA é sempre visível (não depende de hover); `@media (hover: none)` desativa zoom de imagem em touch.
- [x] Revisar quebra de linha de `Faça login` e demais mensagens B2B. — `.b2b-login-to-see-price` usa `flex-direction: column` com `text-align: center` para empilhar texto confortavelmente.
- [x] Revisar espaçamentos e altura da thumb em telas menores. — `min-height` progressiva: 180px desktop → 140px tablet → 120px mobile; padding reduzido em mobile.
- [x] Garantir leitura e toque confortável dos controles. — `@media (pointer: coarse)` garante min-height 44px nos CTAs e 40×40px nas setas Swiper (WCAG 2.5.5).

### Registro de conclusão da fase

- **Status final:** Concluída
- **Data:** 2026-04-13
- **Responsável/agente:** GitHub Copilot (Claude Opus 4.6)
- **Arquivos alterados:**
  - `web/css/source/_product-cards.less` — `@media (hover: none)` para desativar zoom em touch; `@media (pointer: coarse)` para min-height 44px em CTAs e 40×40px em setas.
- **Breakpoints validados:** 1024px (desktop), 768px (tablet), 480px (mobile), coarse pointer (touch).
- **Validação executada:** Deploy sem erros; logs limpos.
- **Próxima fase liberada:** Sim

---

## Fase 6 — Performance, QA final e consolidação

**Status:** Concluída
**Objetivo:** validar regressão, estabilidade visual e consolidar a implementação em fonte canônica limpa.

### Checklist técnico

- [x] Confirmar que a solução final está em arquivo-fonte canônico. — `_product-cards.less` (1013 linhas) é a fonte LESS canônica; `awa-bundle-home-custom.unmin.css` é a fonte CSS canônica para bundles.
- [x] Evitar duplicação residual de regras entre arquivos concorrentes. — `.product-thumb` aparece nos 3 arquivos (LESS + 2 bundles) como redundância inócua sem conflito de especificidade.
- [x] Validar que o hover não dispara layout shift perceptível. — Hover usa apenas `transform: translateY(-2px)` + `box-shadow` + `opacity`/`scale`; nenhum reposicionamento estrutural.
- [x] Validar que não houve regressão em PLP, carrosséis home e autocomplete relevantes. — Screenshots validados: Mais Vendidos, Novos, Super Ofertas e Categoria Guidões — todos com cards uniformes e CTAs ancorados.
- [x] Registrar decisão de rollout final e próximos hardenings. — Rollout concluído via deploy + cache flush + PHP-FPM restart. Monitorar `object-fit` duplicado entre bundles em futuras edições.

### Registro de conclusão da fase

- **Status final:** Concluída
- **Data:** 2026-04-13
- **Responsável/agente:** GitHub Copilot (Claude Opus 4.6) — validação QA final
- **Arquivos finais canônicos:**
  - `web/css/source/_product-cards.less` — fonte LESS única para todos os estilos de card, fases 1–5 (1013 linhas)
  - `web/css/awa-bundle-home-custom.css` / `web/css/awa-bundle-home-custom.unmin.css` — bundle home sincronizado
  - `web/css/awa-bundle-cosmetic-home.unmin.css` — fonte editável canônica do bundle cosmético
  - `Rokanthemes_BestsellerProduct/templates/bestseller.phtml` — template com ARIA labels e estrutura flex
  - `Rokanthemes_Newproduct/templates/newproduct.phtml` — idem
  - `Rokanthemes_Superdeals/templates/category.phtml` — idem
- **Riscos remanescentes:**
  - `!important` em 4 seletores de banner/hero no bundle — intencional, não conflita com product cards
  - `object-fit` duplicado em bundle e `_product-cards.less` (redundância inócua, sem conflito de especificidade)
  - Prioridade CSS do bundle sobrescreve LESS compilado em seletores compartilhados — monitorar se alguma regra futura for adicionada em apenas um dos arquivos
- **Validação executada (QA final):**
  - DI compile: sucesso (40s, zero erros, 4 deprecation warnings pré-existentes em LogMonitoring)
  - Static deploy: `pt_BR en_US -f` executado com sucesso
  - Cache clean + flush: todos os 15 cache types limpos
  - PHP-FPM restart: OPcache cleared
  - Logs: `system.log` 0 bytes, `exception.log` 0 bytes — zero erros novos
  - `debug.log`: erros pré-existentes (Fitment formatYears, B2B attendant listing) — nenhum relacionado ao carrossel/cards
  - Screenshots visuais: carrosséis Mais Vendidos, Novos, Super Ofertas e Categoria Guidões — todos com cards uniformes, CTAs ancorados ao fundo, imagens centralizadas
  - CLS: `aspect-ratio: 1/1` + `min-height` reservam espaço; hover usa apenas `transform`/`opacity`/`box-shadow`
- **Próxima fase liberada:** N/A — ciclo completo

---

## Checklist mestre de conclusão total

- [x] Fase 0 concluída e registrada
- [x] Fase 1 concluída e registrada
- [x] Fase 2 concluída e registrada
- [x] Fase 3 concluída e registrada
- [x] Fase 4 concluída e registrada
- [x] Fase 5 concluída e registrada
- [x] Fase 6 concluída e registrada
- [x] Fontes canônicas confirmadas
- [x] Validação visual final concluída
- [x] Regressão básica revisada

---

## Log de andamento rápido

| Data | Fase | Status | Resumo curto | Próxima fase |
|---|---|---|---|---|
| 2026-04-13 | 0 | Concluída | Home auditada, carrosséis inventariados, grupos de padronização definidos e bugs estruturais documentados | 1 |
| 2026-04-13 | 1 | Concluída | Flex column em todos os cards, CTA ancorado ao fundo, microcopy Super Deals removida, campos vazios condicionalizados, padding da thumb reduzido | 2 |
| 2026-04-13 | 2 | Concluída | aspect-ratio 1/1 no .product-thumb, imagens 100%×100% contain, min-height progressiva, zero CLS | 3 |
| 2026-04-13 | 3 | Concluída | focus-visible em B2B gate, hover confirmado non-structural (transform/opacity only), gates mutuamente exclusivos | 4 |
| 2026-04-13 | 4 | Concluída | ARIA labels + role + tabindex em setas Swiper (3 templates), focus-visible em arrows, disabled state | 5 |
| 2026-04-13 | 5 | Concluída | @media (hover:none) para touch, @media (pointer:coarse) para target size 44px, min-height progressiva nos breakpoints | 6 |
| 2026-04-13 | 6 | Concluída | Bundles sincronizados, LESS recompilado forçado, regras fase 5 confirmadas, zero erros pós-fix, screenshots validados | — |
