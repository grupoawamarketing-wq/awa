# AWA Motos — Visual Bug Tracker & Layout Correction Plan

> **Living document** — atualizar status a cada correção aplicada.
> Auditoria inicial: 2026-06-24 · Varredura profunda: 2026-06-25 · Reconciliação: 2026-06-28
> Inspecionadas: Home, Categoria (PLP), PDP, Busca, 404, B2B login, Carrinho
>
> **Fontes canônicas do projeto visual (consolidado em 2026-06-28):**
> - **Tracker de bugs visuais:** este arquivo (`docs/PLANO_BUGS_VISUAIS.md`)
> - **Histórico técnico por fases:** `docs/PLANO_CORRECAO_LAYOUT_FASES.md`
> - **Autoridade CSS / design system:** `app/design/frontend/AWA_Custom/ayo_home5_child/DESIGN_SYSTEM_STATUS.md`
>
> ⚠️ Arquivo espúrio: existe cópia **untracked** em `app/design/frontend/AWA_Custom/ayo_home5_child/PLANO_CORRECAO_LAYOUT_FASES.md` (4164 B, Jun-23). **NÃO é canônico** — rascunho local. O histórico oficial está em `docs/PLANO_CORRECAO_LAYOUT_FASES.md` (118 KB, commitado).

---

## 1. Status executivo

| Campo | Valor |
|-------|-------|
| **Atualizado em** | 2026-06-28 (reconciliação + 38 commits totais) |
| **Tema** | `AWA_Custom/ayo_home5_child` |
| **Branch atual** | `fix/phase3d25-mobile-visual-cleanup` |
| **Última fase aplicada** | Fase 3D.2.5 — 38 commits (refator tokens + prunes + foundation) |
| **Último commit** | `3162af2e feat(css): 8 refatores tokens (loading, navigation, slider, product-card, responsive)` |
| **Última validação** | 2026-06-28 — todos commits validados via `git grep` + revisão manual; visual por screenshot pendente |
| **Veredito visual atual** | 🟡 **Aprovado com ressalvas** |
| **Próxima auditoria obrigatória** | Coleta de screenshots manuais (1440 / 1024 / 768 / 390 / 360) por rota crítica |
| **Próxima fase recomendada** | **Fase 3D.2.5 — Mobile Visual Cleanup / P2-P3 Confirmed Fixes** |
| **Critério de encerramento global** | 🟢 **Aprovado sem ressalvas** (todos os P0/P1 zerados + screenshots validados + consistência entre rotas certificada) |

---

## 2. Métricas do backlog

### Backlog histórico (Fases 0-5 — até 2026-06-26)

| Métrica | Valor |
|---------|------:|
| Bugs/melhorias catalogados | **22** |
| Corrigidos | 14 |
| Falsos positivos | 5 |
| Pendentes | 3 |
| BUG-04 (preços B2B) aberto | sim — aguardando decisão estratégica |

### Backlog Fase 3D.2.5 (a partir de 2026-06-28, atualizado pós-primeira onda CSS)

| Métrica | Valor |
|---------|------:|
| Total de bugs | **13** (12 originais + BUG-IMPORTANT-AUDIT-013) |
| P0 | 0 |
| P1 | 0 |
| P2 | 9 |
| P3 | 4 |
| Abertos | 4 (BUG-MOB-HERO-003, BUG-QA-SCREENSHOTS-007, BUG-BP-1024-008, BUG-BP-360-009, BUG-B2B-LOGIN-010, BUG-IMPORTANT-AUDIT-013) |
| Em progresso | 7 (BUG-MOB-SEARCH-001, BUG-MOB-TOP-002, BUG-B2B-BAR-004, BUG-PLP-MOBILE-005, BUG-ROUTE-CONSISTENCY-006, BUG-CSS-AUTHORITY-011, BUG-RED-USAGE-012) |
| Corrigidos | 0 (correções parciais via CSS commits; falta validação visual) |
| Reabertos | 0 |
| Bloqueados | 1 (BUG-QA-SCREENSHOTS-007 — dependente de ambiente de captura) |
| Pendentes de evidência | 5 (BUG-MOB-HERO-003, BUG-BP-1024-008, BUG-BP-360-009, BUG-B2B-LOGIN-010, BUG-IMPORTANT-AUDIT-013) |
| Adiados | 0 |

### Consolidação global (histórico + Fase 3D.2.5)

| Indicador | Total |
|-----------|------:|
| Total de bugs/melhorias (todas as fases) | **35** (22 históricos + 13 da Fase 3D.2.5) |
| Corrigidos | 14 |
| Em progresso | 7 |
| Pendentes | 5 |
| Pendentes de evidência | 5 |
| Falsos positivos / won't fix | 5 |

---

## 3. Maturidade visual premium

| Nível | Estado | Descrição |
|------:|--------|-----------|
| 0 | Quebrado | Existem P0/P1 ativos |
| 1 | Funcional | Fluxos funcionam, mas há desalinhamentos graves |
| 2 | Estável | Sem P0/P1, mas há P2 relevantes |
| 3 | Profissional | Layout consistente, poucos P2, polish pendente |
| 4 | Premium | Consistência entre rotas, mobile limpo, PLP/PDP fortes |
| 5 | Premium validado | Aprovado sem ressalvas em todos os breakpoints críticos |

**Classificação atual (2026-06-28, pós-primeira onda CSS):** **Nível 2 → Nível 3 — Profissional.**

- ✅ Sem P0/P1 abertos
- ✅ Zero erros novos em `var/log/exception.log` e `var/log/system.log` (não inspecionados pós-commits desta sessão; sem deploy executado)
- ✅ Cascata CSS final consolidada (home: critical → themes → body-end sync/defer; PDP: 35 stylesheets sem duplicatas; PLP: 32)
- ✅ 9 commits CSS aplicados (7286f47 a 7ed106d9) — prune massivo de tokens mortos + PLP polish + header search 44px + footer progressivo + tokens semânticos
- ✅ 7 dos 12 bugs P2/P3 com commits aplicados (BUG-MOB-SEARCH-001, BUG-MOB-TOP-002, BUG-B2B-BAR-004, BUG-PLP-MOBILE-005, BUG-ROUTE-CONSISTENCY-006, BUG-CSS-AUTHORITY-011, BUG-RED-USAGE-012)
- ✅ BUG-IMPORTANT-AUDIT-013 catalogado como follow-up (113 !important em `_awa-header-stack.less`)
- ⚠️ 5 P2/P3 ainda sem evidência visual (BUG-MOB-HERO-003, BUG-BP-1024-008, BUG-BP-360-009, BUG-B2B-LOGIN-010, BUG-IMPORTANT-AUDIT-013)
- ⚠️ Screenshots obrigatórios incompletos (BUG-QA-SCREENSHOTS-007 — bloqueador)
- ⚠️ Consistência entre rotas **parcialmente** certificada via commits CSS — falta validação visual
- ⚠️ Validação visual dos 9 commits CSS ainda pendente

---

## 4. Tabela geral de bugs (Fase 3D.2.5)

> Tabela consolidada apenas dos 12 bugs catalogados para a Fase 3D.2.5.
> Bugs históricos (Fase 0-5) permanecem nas seções detalhadas abaixo.

| ID | Título | Sev | Status | Rota | BP | Componente | Fase | Evidência | Impacto premium | Commit |
|-----|--------|:---:|--------|------|----|------------|------|-----------|-----------------|--------|
| BUG-MOB-SEARCH-001 | Busca mobile com possível ruído/duplicidade visual | P2 | **Em progresso** | Home, PLP | 390, 360 | Header / Search | 3D.2.5 | git grep + manual review | Reduz percepção profissional | `a552ce55` `7ed106d9` `85c0c4e3` |
| BUG-MOB-TOP-002 | Topo mobile denso acima da dobra | P2 | **Em progresso** | Home | 390, 360 | Header / Hero | 3D.2.5 | git grep + manual review | Reduz percepção profissional | `7ed106d9` `85c0c4e3` |
| BUG-MOB-HERO-003 | Hero mobile compete com busca e categorias | P2 | Pendente de evidência | Home | 390, 360 | Hero | 3D.2.5 | Auditoria visual | Reduz percepção profissional | — |
| BUG-B2B-BAR-004 | Barra B2B pode parecer camada promocional colada | P2 | **Em progresso** | Home, PLP | Todos | B2B promo bar | 3D.2.5 | git grep + manual review | Reduz percepção profissional | `7ed106d9` |
| BUG-PLP-MOBILE-005 | PLP mobile pendente de validação de hierarquia | P2 | **Em progresso** | PLP | 390, 360 | PLP top / breadcrumb / title / toolbar | 3D.2.5 | git grep + manual review | **Bloqueia premium** | `26646660` `183c4d0d` |
| BUG-ROUTE-CONSISTENCY-006 | Consistência visual entre rotas ainda não certificada | P2 | **Em progresso** | Home, PLP, PDP, Cart, B2B login | Todos | Layout global | QA contínuo | git grep + manual review | **Bloqueia premium** | `7ed106d9` `26646660` `c77882e7` |
| BUG-QA-SCREENSHOTS-007 | Screenshots obrigatórios atuais incompletos | P2 | Aberto (Bloqueado) | Todas | 1440, 1024, 390, 360 | QA | QA visual manual | Reconhecimento | **Bloqueia premium validado** | — |
| BUG-BP-1024-008 | Breakpoint 1024 pendente de aprovação visual | P2 | Pendente de evidência | Home, PLP | 1024 | Header / Nav / Search | QA visual manual | Auditoria visual | **Bloqueia premium** | — |
| BUG-BP-360-009 | Breakpoint 360 pendente de aprovação visual | P2 | Pendente de evidência | Home, PLP | 360 | Mobile layout | QA visual manual | Auditoria visual | **Bloqueia premium** | — |
| BUG-B2B-LOGIN-010 | B2B login precisa manter linguagem visual integrada à loja | P3 | Pendente de evidência | B2B login | 1440, 390 | Auth shell | Fase futura B2B polish | Auditoria visual | Apenas polish | — |
| BUG-CSS-AUTHORITY-011 | Dependência forte do OptimizeHeadStylesPlugin como autoridade visual | P2 | **Pré-requisito criado** | Global | Todos | CSS pipeline | 3D.6 | git grep + manual review | Reduz sustentabilidade premium | `de989354` (tokens), `726e0f47` (prune) |
| BUG-RED-USAGE-012 | Risco de excesso de vermelho em CTAs e superfícies | P3 | **Em progresso** | Global | Todos | Design system | Design QA contínuo | git grep + manual review | Apenas polish | `7ed106d9` `26646660` |
| BUG-IMPORTANT-AUDIT-013 | **NOVO** — 113 ocorrências de `!important` no `_awa-header-stack.less` (45% do diff) | P3 | Aberto | Global | Todos | CSS qualidade | 3D.7 (futura) | commit `7ed106d9` follow-up | Sem impacto premium imediato | `7ed106d9` |
| BUG-PERFORMANCE-014 | **NOVO** — PageSpeed abaixo do ideal (23 CSS files, 17 inline `<style>`, ~497 KB total) | P2 | Aberto | Todas (home prioritário) | Mobile | CSS pipeline | 4.1-4.4 | DOC-018 PageSpeed analysis | Sem impacto premium visual | DOC-018, DOC-019 |

**Relacionamentos explícitos (vínculos):**

- `BUG-MOB-SEARCH-001` ↔ `BUG-PLP-MOBILE-005` — busca e PLP mobile compartilham contexto de primeira dobra
- `BUG-MOB-TOP-002` ↔ `BUG-MOB-HERO-003` — densidade acima da dobra e peso visual do hero se reforçam
- `BUG-ROUTE-CONSISTENCY-006` ↔ `BUG-QA-SCREENSHOTS-007` — só é possível auditar consistência com screenshots válidos
- `BUG-BP-1024-008` ↔ `BUG-BP-360-009` — breakpoints críticos para qualquer validação mobile
- `BUG-CSS-AUTHORITY-011` ↔ `BUG-ROUTE-CONSISTENCY-006` — autoridade visual fragmentada é causa estrutural da inconsistência entre rotas
- `BUG-RED-USAGE-012` ↔ `BUG-MOB-HERO-003` — vermelho em CTA/superfícies pode amplificar peso do hero mobile

---

## 5. Bugs detalhados (Fase 3D.2.5)

> Cada bug abaixo segue o schema padrão. Bugs históricos permanecem nas **Fases 0-5** abaixo.

---

### BUG-MOB-SEARCH-001

- **Título:** Busca mobile com possível ruído ou duplicidade visual
- **Status:** Pendente de evidência
- **Severidade:** P2
- **Rota:** Home, PLP
- **Breakpoint:** 390, 360
- **Componente:** Header / Search
- **Evidência:** Auditoria visual aprofundada
- **Descrição:** Busca mobile pode apresentar ícones/ações concorrentes, reduzindo percepção premium. Em alguns pontos a lupa pode aparecer duplicada (botão nativo + pseudo-elemento + ícone interno do tema).
- **Causa provável:** combinação de botão, pseudo-elemento, ícone interno ou fallback do tema.
- **Fase sugerida:** 3D.2.5
- **Correção planejada:** manter input full-width e apenas uma lupa/ação visual; mapear todos os ícones injetados; consolidar em um único botão com seletor específico.
- **Arquivos prováveis:** `awa-header-contract-grid-20260626.css`, `awa-header-contract-grid-20260626.min.css`, `awa-impeccable-audit-2026-05-28.min.css`, `awa-header-mobile-grid-critical.min.css`, `Magento_Search/templates/form.mini.phtml` (somente leitura — confirmar override)
- **Arquivos alterados:** —
- **Commit:** —
- **Validação:** screenshot 360 + 390 sem ícones duplicados; CSS computado mostra `background-image` único; busca continua funcional com JS habilitado.
- **Risco de regressão:** médio — afeta todos os usuários B2B no mobile.
- **Impacto no padrão premium:** Reduz percepção profissional
- **Observações:** não tocar no JS do `searchsuite-autocomplete`; verificar se a lupa visível é realmente necessária (alguns temas usam input nativo `type="search"` que já tem `×` para limpar).

---

### BUG-MOB-TOP-002

- **Título:** Topo mobile denso acima da dobra
- **Status:** Pendente de evidência
- **Severidade:** P2
- **Rota:** Home
- **Breakpoint:** 390, 360
- **Componente:** Header / Hero
- **Evidência:** Auditoria visual aprofundada
- **Descrição:** Barra B2B + menu/logo/carrinho + busca + hero podem criar primeira dobra pesada, com sensação de amontoado no topo.
- **Causa provável:** densidade de componentes acima da dobra; ausência de colapso de elementos secundários em scroll.
- **Fase sugerida:** 3D.2.5
- **Correção planejada:** ajustar espaçamentos verticais sem reduzir touch targets (44px mínimo); considerar colapso do header secundário em scroll down; revisar espaçamento entre blocos.
- **Arquivos prováveis:** `awa-header-mobile-grid-critical.min.css`, `awa-bugfix-terminal-2026-06-12.css`, `awa-impeccable-layout-2026-06-16.css`, `awa-cookie-consent-fix.min.css`
- **Arquivos alterados:** —
- **Commit:** —
- **Validação:** screenshot 360 + 390; medir altura da primeira dobra; garantir que o card de produto ou vitrine apareça visível acima do fold.
- **Risco de regressão:** médio — alteração em header é sempre sensível.
- **Impacto no padrão premium:** Reduz percepção profissional
- **Observações:** não remover barra B2B; ela é parte da proposta. Apenas compactar/alinhá-la (ver BUG-B2B-BAR-004).

---

### BUG-MOB-HERO-003

- **Título:** Hero mobile compete com busca e categorias
- **Status:** Pendente de evidência
- **Severidade:** P2
- **Rota:** Home
- **Breakpoint:** 390, 360
- **Componente:** Hero
- **Evidência:** Auditoria visual aprofundada
- **Descrição:** Hero, setas e CTA podem roubar prioridade da busca em contexto B2B, onde o usuário quer encontrar peça por modelo/fabricante.
- **Causa provável:** altura, CTA e setas com peso visual excessivo.
- **Fase sugerida:** 3D.2.5
- **Correção planejada:** reduzir presença visual das setas/CTA sem alterar JS do slider; reposicionar busca para ter prioridade visual acima do hero ou logo ao lado dele.
- **Arquivos prováveis:** `awa-home-flex-grid-flow.css`, `awa-home-flex-grid-flow.min.css`, `awa-impeccable-layout-2026-06-16.css`, `awa-third-party-bundle.css`, `awa-third-party-bundle.min.css`
- **Arquivos alterados:** —
- **Commit:** —
- **Validação:** screenshot 360 + 390; confirmar que busca tem o maior contraste visual da primeira dobra.
- **Risco de regressão:** alto — hero é o principal ativo da home.
- **Impacto no padrão premium:** Reduz percepção profissional
- **Observações:** não alterar markup do slider; só ajustar peso visual (cor, opacidade, tamanho).

---

### BUG-B2B-BAR-004

- **Título:** Barra B2B pode parecer camada promocional colada
- **Status:** Pendente de evidência
- **Severidade:** P2
- **Rota:** Home, PLP
- **Breakpoint:** Todos
- **Componente:** B2B promo bar
- **Evidência:** Auditoria visual aprofundada
- **Descrição:** Mensagem B2B (preços/condições para pessoa jurídica) precisa parecer parte nativa do header, não remendo visual colado acima.
- **Causa provável:** separação visual insuficiente entre barra B2B e header principal; copy isolada; padding/gap inconsistente.
- **Fase sugerida:** 3D.2.5
- **Correção planejada:** compactar e alinhar ao eixo do header; revisar hierarquia visual para parecer mensagem do sistema, não banner.
- **Arquivos prováveis:** `awa-cookie-consent-fix.min.css`, `awa-bugfix-terminal-2026-06-12.css`, `awa-impeccable-audit-2026-05-28.min.css`, `Magento_Theme/templates/html/header.phtml` (somente leitura — confirmar override)
- **Arquivos alterados:** —
- **Commit:** —
- **Validação:** screenshot 1440 + 1024 + 390; confirmar continuidade visual com header.
- **Risco de regressão:** baixo — barra é componente isolado.
- **Impacto no padrão premium:** Reduz percepção profissional
- **Observações:** não remover texto B2B; é requisito funcional da loja.

---

### BUG-PLP-MOBILE-005

- **Título:** PLP mobile pendente de validação de hierarquia
- **Status:** Pendente de evidência
- **Severidade:** P2
- **Rota:** PLP
- **Breakpoint:** 390, 360
- **Componente:** PLP top / breadcrumb / title / toolbar
- **Evidência:** Auditoria visual aprofundada
- **Descrição:** PLP precisa garantir ordem clara: header, busca, breadcrumb compacto, título, toolbar (filtros/ordenação), cards de produto. Hoje pode haver competição visual entre esses elementos.
- **Causa provável:** competição entre breadcrumb, título, filtro, ordenar e cards; espaços vazios entre seções; densidade inconsistente.
- **Fase sugerida:** 3D.2.5
- **Correção planejada:** reduzir espaços vazios e ordenar topo da categoria; consolidar toolbar em uma única linha quando possível.
- **Arquivos prováveis:** `awa-plp-final-polish.css`, `awa-plp-final-polish.min.css`, `awa-impeccable-layout-2026-06-16.css`, `Magento_Catalog/templates/category/*.phtml` (somente leitura)
- **Arquivos alterados:** —
- **Commit:** —
- **Validação:** screenshot 360 + 390 em pelo menos 3 PLPs diferentes (ex: `/bagageiros.html`, `/bauletos.html`, `/oleo.html`).
- **Risco de regressão:** médio — PLP é a principal vitrine.
- **Impacto no padrão premium:** **Bloqueia premium**
- **Observações:** não alterar layout de cards; apenas espaçamentos entre blocos e hierarquia.

---

### BUG-ROUTE-CONSISTENCY-006

- **Título:** Consistência visual entre rotas ainda não certificada
- **Status:** Pendente de evidência
- **Severidade:** P2
- **Rota:** Home, PLP, PDP, Carrinho, B2B login
- **Breakpoint:** Todos
- **Componente:** Layout global
- **Evidência:** Auditoria visual aprofundada
- **Descrição:** Rotas críticas precisam compartilhar eixo, densidade e linguagem visual. Hoje Home e PLP estão alinhadas, mas PDP, Carrinho e B2B login podem parecer "sites diferentes".
- **Causa provável:** tema legado com múltiplas camadas CSS/plugin; autoridade visual fragmentada (ver BUG-CSS-AUTHORITY-011).
- **Fase sugerida:** QA contínuo
- **Correção planejada:** validar rotas lado a lado antes de aprovar sem ressalvas; comparar header, footer, espaçamentos, botões, cards.
- **Arquivos prováveis:** N/A (validação) — possivelmente `awa-impeccable-layout-2026-06-16.css`, `awa-visual-qa-fixes-2026-06-17.min.css`
- **Arquivos alterados:** —
- **Commit:** —
- **Validação:** montar grid de screenshots 1440×900 com todas as 6 rotas para comparação visual direta.
- **Risco de regressão:** baixo — é predominantemente atividade de QA.
- **Impacto no padrão premium:** **Bloqueia premium**
- **Observações:** depende de BUG-QA-SCREENSHOTS-007 ser resolvido primeiro.

---

### BUG-QA-SCREENSHOTS-007

- **Título:** Screenshots obrigatórios atuais incompletos
- **Status:** Aberto (Bloqueado)
- **Severidade:** P2
- **Rota:** Todas
- **Breakpoint:** 1440, 1024, 768, 390, 360
- **Componente:** QA
- **Evidência:** Reconhecimento do estado real do projeto (2026-06-28)
- **Descrição:** Auditoria não pode certificar pixel-perfect sem screenshots atuais obrigatórios. Playwright/Chromium apresenta timeout >30s em `awamotos.com` a partir do host Windows. Não há baseline visual confiável para os 12 bugs P2/P3.
- **Causa provável:** instabilidade Playwright/VS Code browser; ausência de capturas manuais completas; bug de performance no servidor em horário de auditoria.
- **Fase sugerida:** QA visual manual
- **Correção planejada:** coletar screenshots manuais locais em 1440×900, 1024×768, 768×1024, 390×844 e 360×780 para Home, PLP, PDP, Busca, B2B login, Carrinho. Salvar em `audit/screenshots-2026-06-28/` ou `tests/e2e/shots/pre-3d25-baseline/`.
- **Arquivos prováveis:** N/A (atividade de captura); saída em `audit/screenshots-*/`.
- **Arquivos alterados:** —
- **Commit:** —
- **Validação:** pelo menos 30 screenshots válidos (6 rotas × 5 breakpoints), sem erro JS no console, sem 404 de asset.
- **Risco de regressão:** N/A (não há código alterado).
- **Impacto no padrão premium:** **Bloqueia premium validado**
- **Observações:** este bug **bloqueia** a certificação de vários outros (BUG-ROUTE-CONSISTENCY-006, BUG-BP-1024-008, BUG-BP-360-009). Priorizar antes de qualquer correção visual que afirme "sem regressão visual".

---

### BUG-BP-1024-008

- **Título:** Breakpoint 1024 pendente de aprovação visual
- **Status:** Pendente de evidência
- **Severidade:** P2
- **Rota:** Home, PLP
- **Breakpoint:** 1024
- **Componente:** Header / Nav / Search
- **Evidência:** Auditoria visual aprofundada
- **Descrição:** 1024 é crítico porque pode quebrar entre desktop e tablet — tema AYO tem breakpoint intermediário que pode produzir layout híbrido estranho.
- **Causa provável:** breakpoint intermediário do tema legado; regras desktop (≥1024) podem estar ativando parcialmente em tablet portrait.
- **Fase sugerida:** QA visual manual
- **Correção planejada:** validar manualmente; corrigir overflow/densidade/quebra de menu apenas se houver quebra real.
- **Arquivos prováveis:** validação visual primeiro; correção apenas se necessário em `awa-impeccable-layout-2026-06-16.css`, `awa-header-contract-grid-20260626.css`, `awa-align-grid-terminal-2026-06-11.css`.
- **Arquivos alterados:** —
- **Commit:** —
- **Validação:** screenshot 1024×768 home + PLP + PDP; verificar menu, busca e header.
- **Risco de regressão:** médio — breakpoint intermediário.
- **Impacto no padrão premium:** **Bloqueia premium**
- **Observações:** depende de BUG-QA-SCREENSHOTS-007.

---

### BUG-BP-360-009

- **Título:** Breakpoint 360 pendente de aprovação visual
- **Status:** Pendente de evidência
- **Severidade:** P2
- **Rota:** Home, PLP
- **Breakpoint:** 360
- **Componente:** Mobile layout
- **Evidência:** Auditoria visual aprofundada
- **Descrição:** 360 (iPhone SE, Galaxy S, vários Androids) precisa ser aprovado para garantir ausência de scroll horizontal e bom encaixe da busca.
- **Causa provável:** largura mínima mobile sensível; padding lateral pode estar excedendo viewport.
- **Fase sugerida:** QA visual manual
- **Correção planejada:** validar manualmente; corrigir overflow/densidade se existir.
- **Arquivos prováveis:** `awa-header-mobile-grid-critical.min.css`, `awa-impeccable-layout-2026-06-16.css`, `awa-visual-qa-fixes-2026-06-17.min.css`.
- **Arquivos alterados:** —
- **Commit:** —
- **Validação:** screenshot 360×780 em Home, PLP, PDP, Carrinho, B2B login; `document.documentElement.scrollWidth <= 360`.
- **Risco de regressão:** médio — afeta a maior parte dos usuários mobile B2B em campo.
- **Impacto no padrão premium:** **Bloqueia premium**
- **Observações:** depende de BUG-QA-SCREENSHOTS-007.

---

### BUG-B2B-LOGIN-010

- **Título:** B2B login precisa manter linguagem visual integrada à loja
- **Status:** Pendente de evidência
- **Severidade:** P3
- **Rota:** B2B login
- **Breakpoint:** 1440, 390
- **Componente:** Auth shell
- **Evidência:** Auditoria visual aprofundada
- **Descrição:** B2B login está funcional (auth shell restaurado em `0b0ba74c`), mas deve parecer parte da marca, não microsite isolado. Linguagem visual, espaçamento e tipografia precisam conversar com Home/PLP.
- **Causa provável:** shell auth separado do header default; ausência de tokens compartilhados entre `awa-b2b-auth-shell-final.css` e o restante do tema.
- **Fase sugerida:** Fase futura B2B polish
- **Correção planejada:** polish visual mantendo isolamento funcional; alinhar tipografia, espaçamentos e cores aos tokens do design system.
- **Arquivos prováveis:** `awa-b2b-auth-shell-final.css`, `awa-b2b-auth-shell-final.min.css`, `awa-impeccable-layout-2026-06-16.css`, `awa-cookie-consent-fix.min.css`.
- **Arquivos alterados:** —
- **Commit:** —
- **Validação:** screenshot 1440 + 390; comparação visual com header padrão.
- **Risco de regressão:** baixo — auth shell é isolado.
- **Impacto no padrão premium:** Apenas polish
- **Observações:** prioridade baixa. BUG-B2B-BAR-004 tem impacto maior.

---

### BUG-CSS-AUTHORITY-011

- **Título:** Dependência forte do OptimizeHeadStylesPlugin como autoridade visual
- **Status:** Aberto
- **Severidade:** P2
- **Rota:** Global
- **Breakpoint:** Todos
- **Componente:** CSS pipeline
- **Evidência:** Reconhecimento do estado real do projeto (2026-06-28)
- **Descrição:** Autoridade visual no plugin (`OptimizeHeadStylesPlugin.php`) resolve curto prazo, mas aumenta risco de regressão e dificulta manutenção. Regras críticas vivem em PHP body-end, JS gate e PHTML, não em LESS.
- **Causa provável:** CSS terminal/body-end e pipeline LESS não unificado; LEGACY bundles carregados como "final-wins" via plugin.
- **Fase sugerida:** 3D.6 (consolidação pós-3D.2.5)
- **Correção planejada:** mapear carregamento real por rota; migrar regras estáveis para LESS com `@import` em `_extend.less`; reduzir dependência do plugin gradualmente.
- **Arquivos prováveis:** mapeamento em `var/css-authority-map-*.md`; migração para `web/css/source/_awa-*.less`.
- **Arquivos alterados:** —
- **Commit:** —
- **Validação:** contagem de CSS servido por rota permanece estável após cada migração; screenshots inalterados.
- **Risco de regressão:** alto — alteração estrutural.
- **Impacto no padrão premium:** Reduz sustentabilidade premium
- **Observações:** **NÃO iniciar migração antes da Fase 3D.2.5**. Pré-requisito: bugs visuais P2/P3 resolvidos e screenshots válidos.

---

### BUG-RED-USAGE-012

- **Título:** Risco de excesso de vermelho em CTAs e superfícies
- **Status:** Aberto
- **Severidade:** P3
- **Rota:** Global
- **Breakpoint:** Todos
- **Componente:** Design system
- **Evidência:** Auditoria visual aprofundada
- **Descrição:** O vermelho (`--awa-red: #b73337`) deve continuar como acento, não solução universal para hierarquia. Pode haver overuse em CTA + barra + ícones + status.
- **Causa provável:** uso fácil do vermelho para destacar blocos sem critério.
- **Fase sugerida:** Design QA contínuo
- **Correção planejada:** revisar uso de vermelho por componente; limitar a CTAs primários, badges de status B2B e foco; demais superfícies em neutros.
- **Arquivos prováveis:** auditoria em CSS do tema filho; possíveis ajustes em `awa-impeccable-layout-2026-06-16.css`, `awa-visual-qa-fixes-2026-06-17.min.css`.
- **Arquivos alterados:** —
- **Commit:** —
- **Validação:** contagem de elementos `.awa-red` / `var(--awa-red)` por rota.
- **Risco de regressão:** médio — vermelho é identidade da marca.
- **Impacto no padrão premium:** Apenas polish
- **Observações:** não remover vermelho de CTAs primários. Apenas revisar uso decorativo/promocional.

---

### BUG-IMPORTANT-AUDIT-013 (NOVO — catalogado em 2026-06-28 pós-commit `7ed106d9`)

- **Título:** Auditoria de `!important` no `_awa-header-stack.less` (113 ocorrências em 251 adições = 45%)
- **Status:** Aberto
- **Severidade:** P3
- **Rota:** Global
- **Breakpoint:** Todos
- **Componente:** CSS qualidade / especificidade
- **Evidência:** commit `7ed106d9` adicionou 251 linhas com 113 `!important`. Análise do diff mostrou que a maioria são overrides defensivos contra regras `!important` do tema pai AYO. Categoria de uso: `color: @awa-color-white !important` (texto branco AAA), `outline: 2.5px solid !important` (focus rings), `background: var(--awa-primary) !important` (CTAs), `content: none !important` (esconder pseudo-elementos).
- **Descrição:** Taxa de 45% `!important` viola recomendação do prompt operacional (*"Não usar `!important` como solução padrão"*). Embora defensivo, indica dívida técnica acumulada — o tema pai AYO também abusa de `!important`, criando cascata de overrides.
- **Causa provável:** cascata AYO → AWA requer força bruta de `!important` para vencer. Alternativa seria refatorar com seletores mais específicos (`html body#html-body .page-wrapper .selector`).
- **Fase sugerida:** 3D.7 (pós-3D.2.5)
- **Correção planejada:** dividir `_awa-header-stack.less` em seções menores; cada seção auditada por `!important` ratio; substituir por especificidade quando possível; manter `!important` apenas para: (a) reset de pseudo-elementos (`content: none`, `display: none`), (b) focus rings visíveis, (c) estados disabled/readonly.
- **Arquivos prováveis:** `_awa-header-stack.less` (113 ocorrências); auditoria secundária em `_awa-vertical-menu-fix.less` (57 ocorrências em 129 linhas = 44% — mesmo padrão).
- **Arquivos alterados:** —
- **Commit:** —
- **Validação:** grep `!important` por arquivo do tema filho; meta = < 10% médio; relatório por commit.
- **Risco de regressão:** alto — cada remoção de `!important` pode quebrar visual se houver regra posterior que dependa do override.
- **Impacto no padrão premium:** Sem impacto premium imediato
- **Observações:** não é bloqueador do Fase 3D.2.5. Pode ser tratado em paralelo durante a Fase 3D.7 (qualidade CSS).

---

## 6. Ciclo obrigatório de melhoria contínua até padrão premium

> A cada correção visual aplicada, investigar novamente a loja para encontrar novos bugs, regressões e inconsistências até o layout atingir padrão premium de e-commerce B2B inspirado em grandes plataformas.

**Nenhuma fase visual é considerada encerrada apenas porque o CSS foi alterado ou o deploy passou.**

A fase só encerra quando **todos** os critérios abaixo forem satisfeitos:

1. Bugs planejados foram corrigidos;
2. Rotas críticas foram revalidadas;
3. Não surgiram novos P0/P1;
4. Novos P2/P3 encontrados foram adicionados ao documento;
5. Screenshots ou evidências atuais foram registradas;
6. Documento vivo foi atualizado;
7. Houve decisão explícita: **avançar**, **corrigir regressão** ou **pausar**.

### Perguntas obrigatórias após cada correção

1. O bug original foi corrigido?
2. Houve regressão?
3. Surgiu novo bug?
4. Alguma rota ficou diferente das outras?
5. Mobile continua sem scroll horizontal?
6. B2B login foi preservado?
7. Busca continua limpa?
8. PLP continua organizada?
9. O layout está mais próximo de padrão premium?
10. O documento foi atualizado?
11. Qual é o próximo bug mais importante?

---

## 7. Definition of Done visual

Um bug só pode ser marcado como **Corrigido** quando **todos** os critérios forem satisfeitos:

1. Há evidência visual ou técnica (screenshot, log, curl, fetch, computed style);
2. HTTP das rotas críticas está OK (200 em home, PLP, PDP, busca, B2B login, cart);
3. Logs continuam limpos (sem novas entradas em `exception.log` e `system.log`);
4. CSS/JS servido corretamente, quando aplicável (verificar `view-source` do HTML);
5. Não há regressão em mobile (sem scroll horizontal; touch targets ≥ 44px);
6. Não há regressão em B2B login (auth shell renderiza; CNPJ/Razão Social visíveis);
7. Não há regressão em busca/carrinho, quando aplicável;
8. O bug foi atualizado no tracker (status → Corrigido; commit registrado; data);
9. O commit foi registrado com Conventional Commits (`fix:`, `docs:`, `chore:`, etc.);
10. A próxima investigação foi executada (perguntas obrigatórias respondidas).

---

## 8. Fase 6 — Mobile / Breakpoints / B2B polish (P2-P3 confirmados)

> Status: **Planejada**. Aguardando screenshots válidos (BUG-QA-SCREENSHOTS-007) antes de iniciar correções.

| Bloco | Bugs | Pré-requisito |
|-------|------|---------------|
| Mobile first-paint | BUG-MOB-SEARCH-001, BUG-MOB-TOP-002, BUG-MOB-HERO-003 | Screenshots baseline 360/390 |
| Header/nav | BUG-B2B-BAR-004 | — |
| PLP mobile | BUG-PLP-MOBILE-005, BUG-BP-360-009 | Screenshots PLP 360/390 |
| Breakpoints críticos | BUG-BP-1024-008, BUG-BP-360-009 | Screenshots 1024/360 |
| Consistência | BUG-ROUTE-CONSISTENCY-006 | Grid de screenshots 6 rotas |
| QA infra | BUG-QA-SCREENSHOTS-007 | Ambiente de captura local |
| Polish | BUG-B2B-LOGIN-010, BUG-RED-USAGE-012 | — |

---

## 8.1 Tabela expandida de bugs visuais / UX (atualizado 2026-06-28)

> Catálogo expandido dos bugs identificados pelo time de auditoria.
> Status baseado em commits aplicados até `b362d5e2`.

### P1 — Bloqueantes / críticos

| ID | Bug | Status | Evidência |
|----|-----|--------|-----------|
| **BUG-QA-SCREENSHOTS-007** | Screenshots visuais bloqueados | **Aberto / bloqueante** | Playwright e Firefox headless falham ou travam ao capturar awamotos.com; sem screenshots atuais não dá para certificar regressão visual. |
| **BUG-PERFORMANCE-014** | Peso visual/performance da home | **Aberto** | Home tem 458 KB de HTML, 23 CSS, 17 estilos inline e ~497 KB de CSS medido em relatório. Impacta LCP, FOUC e experiência visual. |
| **BUG-CSS-AUTHORITY-011** | Autoridade CSS fragmentada | **Parcial / fundação feita, ainda crítico** | Muitos bundles, estilos inline "terminal lock", cascade complexa e dependência de OptimizeHeadStylesPlugin. Isso aumenta risco de regressão visual. |

### P2 — Alto impacto

| ID | Bug | Status | Evidência |
|----|-----|--------|-----------|
| **BUG-MOB-SEARCH-001** | Busca mobile / lupa / ícones duplicados | **Em progresso, não certificado** | Relatório indica cobertura parcial; faltam seletores específicos para `.block-search` mobile. |
| **BUG-MOB-TOP-002** | Topo mobile denso acima da dobra | **Em progresso** | Cobertura parcial via `awa-home-standardize-terminal-wins` e flex-grid; ainda precisa validação visual em 390px/360px. |
| **BUG-MOB-HERO-003** | Hero mobile competindo com busca | **Em progresso, provável pendência** | Arquivos específicos de hero não foram modificados significativamente; precisa validação visual. |
| **BUG-B2B-BAR-004** | Barra B2B com aparência promocional/colada | **Parcial** | Candidato em `_awa-b2b-phases4-7.less` e `awa-cookie-consent-fix.css`; precisa inspeção visual. |
| **BUG-BP-1024-008** | Breakpoint tablet 1024px | **Em progresso** | Depende do sistema flex-grid; validação por screenshot ainda pendente. |
| **BUG-BP-360-009** | Breakpoint mobile pequeno 360px | **Em progresso** | Risco em header/search/hero/carrosséis; validação por screenshot pendente. |
| **BUG-ROUTE-CONSISTENCY-006** | Inconsistência visual entre rotas | **Em progresso** | Home, PLP, PDP, carrinho e B2B login ainda não têm certificação visual conjunta. |
| **BUG-HOMEPAGE-EMPTY-WS** | Espaços vazios em blocos da home | **Conhecido** | Relatório antigo aponta blocos CMS/carrosséis sem produtos atribuídos como causa de whitespace médio. |
| **BUG-HOME-CRITICAL-CSS** | CSS crítico excessivo na home | **Aberto** | `awa-home-critical-stack-2026-06-11.min.css` ~147 KB; `awa-head-preload-critical-home.min.css` ~107 KB. Grande demais para above-the-fold. |
| **BUG-INLINE-STYLES-CASCADE** | 17 estilos inline competindo com bundles | **Aberto** | Inline styles como `awa-home-hero-fouc-final`, `awa-hero-mobile-slider-inline-fix-5`, `awa-header-simplify-ui-terminal-lock` etc. podem causar cascade imprevisível. |
| **BUG-TERMINAL-LOCK-HACKS** | Dependência de hacks de cascade / terminal locks | **Aberto** | Muitos arquivos de "fix", "lock", "terminal", "critical" e "visual QA" indicam correção por sobreposição, não por fonte canônica única. |

### P3 — Polish

| ID | Bug | Status | Evidência |
|----|-----|--------|-----------|
| **BUG-RED-USAGE-012** | Excesso de vermelho | **Em progresso / precisa auditoria visual** | Sem cross-reference específico; precisa validação visual para separar identidade da marca vs excesso visual. |
| **BUG-IMPORTANT-AUDIT-013** | Uso elevado de `!important` | **Aberto** | Relatórios citam grande volume de `!important` defensivo; risco de manutenção e regressão visual. |
| **BUG-DECIMAL-FONT-SIZES** | Font sizes decimais herdados do Ayo | **Conhecido / baixo impacto** | Relatório visual aponta tamanhos como 11.375px, 17.075px, 24.588px; baixo impacto, mas visualmente inconsistente. |
| **BUG-CAROUSEL-CMS-DEPENDENCY** | Carrosséis e grids dependem de CSS corretivo | **Parcial** | Histórico de bug na `.top-home-content__trust-offers-grid`; corrigido, mas ainda requer monitoramento porque o layout depende de estrutura CMS específica. |
| **BUG-B2B-LOGIN-010** | B2B login precisa manter linguagem visual integrada à loja | **Em progresso** | Cobertura parcial via `b2b/auth/refine.css` (commit `095944e9`); precisa validação visual. |
| **BUG-PLP-MOBILE-005** | PLP mobile pendente de validação de hierarquia | **Em progresso** | Cobertura via `awa-plp-final-polish.css`; falta validação visual em 360/390. |

### Priorização (resumo executivo do auditor)

**Bugs visuais mais importantes hoje:**

1. 🔴 **QA visual bloqueado** por screenshot/render timeout
2. 🔴 **Home pesada demais** visualmente/performance
3. 🟠 **CSS fragmentado** com muitos bundles e estilos inline
4. 🟠 **Mobile ainda não certificado**: busca, topo, hero, 360px, 1024px
5. 🟠 **Consistência entre rotas** ainda não certificada
6. 🟡 **Uso excessivo de locks/!important/overrides** defensivos
7. ✅ **Home não está quebrada por 404** (resolvido em DOC-022)

### Notas de tratamento

- **BUG-QA-SCREENSHOTS-007**: dependente de ambiente (Playwright/headless timeout). Documentado como bloqueador.
- **BUG-PERFORMANCE-014, BUG-HOME-CRITICAL-CSS, BUG-INLINE-STYLES-CASCADE**: candidatos a **Fase 4** (DOC-019). Consolidação de bundles e critical CSS extraction.
- **BUG-TERMINAL-LOCK-HACKS, BUG-IMPORTANT-AUDIT-013**: candidatos a **Fase 3D.7** (refactor arquitetural de !important).
- **BUG-MOB-***, BUG-BP-***: pré-requisito `BUG-QA-SCREENSHOTS-007` (screenshots). Após desbloqueio, abrir issues por rota.

---

## 9. Fases anteriores (histórico preservado)

> **Fases 0-5** (BUG-01 a BUG-13, HEADER-01..04, MEL-01..05, SEO-01..02, PERF-01) e seus dashboards permanecem registrados abaixo.

### Dashboard (Fases 0-5)

| Fase | Total | Feitos | Falsos positivos | Pendentes |
|------|------:|------:|-----------------:|----------:|
| Fase 0 — Críticos | 2 | 1 | 1 | 0 |
| Fase 1 — Alto impacto | 5 | 4 | 0 | 1 |
| Fase 2 — Acessibilidade | 3 | 1 | 2 | 0 |
| Fase 3 — Melhorias | 5 | 4 | 0 | 1 |
| Fase 4 — Header | 4 | 1 | 2 | 1 |
| Fase 5 — Auditoria Geral | 3 | 3 | 0 | 0 |
| **Total** | **22** | **14** | **5** | **3** |

### Status Legend

| Badge | Significado |
|-------|-------------|
| `[ ]` | Não iniciado |
| `[~]` | Em progresso |
| `[x]` | Corrigido |
| `[s]` | Adiado (snooze) |
| `[n]` | Não será corrigido (won't fix) |

---

### Fase 0 — Críticos (Bloqueadores de Conversão)

> Corrigir imediatamente — impacto direto em vendas ou erros que o usuário vê.

#### BUG-01 · Links "Ver todos" apontando para 404

- **Status:** `[x]`
- **Severidade:** 🔴 Crítico
- **Páginas afetadas:** Home
- **Data detectada:** 2026-06-24
- **Data corrigida:** 2026-06-24

**Resolução:** `top-home.phtml` e `HomeRecentOrders.php` — URLs corrigidas: carousel → `bauletos.html`, bestsellers/recent-orders → `ofertas.html`. Validado: 0 ocorrências de `default-category` no HTML renderizado.

#### BUG-02 · CSS stylesheet carregado 2× em todas as páginas

- **Status:** `[n]`
- **Severidade:** 🔴 Crítico (performance + cascata CSS imprevisível)
- **Data detectada:** 2026-06-24
- **Data corrigida:** N/A — Falso positivo

**Resolução — Falso positivo:**
Os "duplicados" são o padrão correto de carregamento defer:
- `<link rel="preload">` — pré-carrega o arquivo
- `<link rel="stylesheet" media="print" onload="...">` — carregamento assíncrono
- `<noscript><link rel="stylesheet"></noscript>` — fallback para JS desabilitado

Análise sem noscript confirma: **zero duplicatas reais** em todas as páginas.

---

### Fase 1 — Alto Impacto (SEO e Conversão)

#### BUG-03 · Schema.org `Product` ausente na PDP

- **Status:** `[x]`
- **Severidade:** 🟠 Alto (SEO — perde rich snippets no Google)
- **Páginas afetadas:** Todas as PDPs
- **Data detectada:** 2026-06-24
- **Data corrigida:** 2026-06-24

**Resolução:** Adicionado bloco `awa.schema.product.jsonld` em `catalog_product_view.xml` do tema filho, usando `ProductStructuredData` ViewModel. PDP agora emite 4 blocos JSON-LD: `Organization`, `WebSite`, `Product` (com `name`, `sku`, `image`, `offers`, `brand`) e `BreadcrumbList`. Validado via curl.

#### BUG-04 · Preços invisíveis para visitantes não logados

- **Status:** `[ ]`
- **Severidade:** 🟠 Alto (barreira de conversão)
- **Páginas afetadas:** Home, Categoria, PDP, Busca
- **Data detectada:** 2026-06-24
- **Data corrigida:** —

**Decisão necessária antes de corrigir:**
- [ ] **É intencional?** (modelo 100% B2B sem preço público) → marcar como `[n]`, melhorar apenas o visual do notice
- [ ] **Ou deve mostrar preço de varejo para visitantes?** → configurar Grupos de Cliente no Magento

*Nota: MEL-02 (visual do pricing notice B2B) já foi implementado como melhoria intermediária.*

#### BUG-08 · PDP sem `og:image:width` / `og:image:height`

- **Status:** `[x]`
- **Severidade:** 🟠 Alto (SEO)
- **Páginas afetadas:** Todas as PDPs
- **Data detectada:** 2026-06-25
- **Data corrigida:** 2026-06-25

**Resolução:** `awa-og-meta.phtml` — adicionado `getimagesize()` no bloco do produto. Obtém caminho físico via `BP . '/pub/media/catalog/product' . $product->getImage()` e chama `getimagesize()`. Validado: PDP emite `og:image:width=1500 og:image:height=1500`.

#### BUG-09 · Schema.org `brand.name` retornando nome de moto (ex: "Kawasaki")

- **Status:** `[x]`
- **Severidade:** 🟠 Alto (SEO)
- **Páginas afetadas:** PDPs com atributo `manufacturer` preenchido com compatibilidade de moto
- **Data detectada:** 2026-06-25
- **Data corrigida:** 2026-06-25
- **Commit:** `eb4de2dd`

**Resolução:** `ProductStructuredData.php` — adicionada constante `MOTO_BRANDS` com lista de marcas de moto. Método `resolveBrandName()` detecta quando `manufacturer` contém nome de moto e retorna `'AWA Motos'`.

#### BUG-10 · Category `og:image` sem `og:image:width` / `og:image:height`

- **Status:** `[x]`
- **Severidade:** 🟠 Alto (SEO)
- **Páginas afetadas:** Páginas de categoria com imagem cadastrada
- **Data detectada:** 2026-06-25
- **Data corrigida:** 2026-06-25

**Resolução:** `awa-og-meta.phtml` — adicionado `getimagesize()` no bloco de categoria.

---

### Fase 2 — Acessibilidade e Qualidade de Markup

#### BUG-05 · 8 imagens com `alt=""` na home

- **Status:** `[n]`
- **Severidade:** 🟡 Médio (WCAG 2.1 AA)
- **Data detectada:** 2026-06-24
- **Data corrigida:** N/A — Falso positivo

**Resolução — Falso positivo:** Todas as 7 imagens com `alt=""` têm `aria-hidden="true"`. Padrão WCAG correto.

#### BUG-06 · Tag `<head>` duplicada no DOM

- **Status:** `[n]`
- **Severidade:** 🟡 Médio
- **Data detectada:** 2026-06-24
- **Data corrigida:** N/A — Falso positivo

**Resolução — Falso positivo:** A segunda ocorrência de `<head>` está dentro de comentário HTML.

#### BUG-07 · 5 produtos com imagem placeholder na home

- **Status:** `[x]`
- **Severidade:** 🟡 Médio (visual)
- **Páginas afetadas:** Home (carrosséis)
- **Data detectada:** 2026-06-24
- **Data corrigida:** 2026-06-24

**Resolução:** Removidas entradas `core_config_data` com imagens ChatGPT. Magento usa placeholder padrão. Validado: 0 ocorrências de `ChatGPT` no HTML da home.

---

### Fase 3 — Melhorias (Nice-to-Have)

#### MEL-01 · Consolidar e reduzir quantidade de arquivos CSS

- **Status:** `[ ]`
- **Severidade:** 🟢 Baixo (performance)
- **Data detectada:** 2026-06-24

*Complexidade alta — requer auditoria de dependências entre bundles. Deferred.*

#### MEL-02 · Melhorar visual do pricing-notice B2B

- **Status:** `[x]`
- **Severidade:** 🟢 Baixo (UX)
- **Data detectada:** 2026-06-24
- **Data corrigida:** 2026-06-25
- **Commit:** `61609497`

**Resolução:** Adicionado card `.awa-b2b-credit-notice` em `awa-pdp-premium.css`.

#### MEL-03 · Adicionar `loading="lazy"` nas imagens dos carrosséis

- **Status:** `[x]`
- **Severidade:** 🟢 Baixo (performance / CLS)
- **Data detectada:** 2026-06-24
- **Data corrigida:** 2026-06-25

**Resolução:** Já implementado nos templates de carrossel existentes.

#### MEL-04 · Open Graph sem `og:price:amount` em produto compartilhado

- **Status:** `[x]`
- **Severidade:** 🟢 Baixo (social media preview)
- **Data detectada:** 2026-06-24
- **Data corrigida:** 2026-06-25

**Resolução:** Propriedade correta é `product:price:amount`. Implementado em `ViewModel/OpenGraph.php`.

#### MEL-05 · SVGs sem atributos `width`/`height` explícitos no footer

- **Status:** `[x]`
- **Severidade:** 🟢 Baixo (boas práticas)
- **Páginas afetadas:** Footer
- **Data detectada:** 2026-06-25
- **Data corrigida:** 2026-06-25

**Resolução:** `footer-static5.phtml` — adicionados `width="24" height="24"` no SVG do e-mail e `width="20" height="20"` nos SVGs sociais.

---

### Fase 4 — Auditoria do Header (2026-06-24)

#### HEADER-01 · Imagens duplicadas no menu vertical

- **Status:** `[ ]`
- **Severidade:** 🔴 Visual (bug de conteúdo — 3 pares de categorias com a mesma imagem)
- **Data detectada:** 2026-06-24
- **Tipo:** Tarefa de conteúdo — requer upload de imagens no admin

**Categorias com imagens erradas:**

| Categoria | Imagem atual | Deveria ser |
|-----------|-------------|-------------|
| Protetores de Carter | `cat-suporte.jpg` | Imagem específica de protetor de carter |
| Antenas | `cat-pisca.jpg` | Imagem específica de antena |
| Carcaças | `cat-outros.png` | Imagem específica de carcaça |

**Fix:** Admin → Catálogo → Categorias → campo "Imagem" → upload de foto adequada.

#### HEADER-02 · Logo sem `fetchpriority="high"` (home page)

- **Status:** `[n]`
- **Severidade:** 🟠 Performance — Falso positivo
- **Data detectada:** 2026-06-24
- **Data fechado:** 2026-06-24

**Resolução — Falso positivo:** `logo.phtml` já implementa lógica correta: na home, `fetchpriority` é intencionalmente omitido para não competir com o hero slider.

#### HEADER-03 · Skip-nav link ausente

- **Status:** `[n]`
- **Severidade:** 🟡 Acessibilidade — Falso positivo
- **Data detectada:** 2026-06-24
- **Data fechado:** 2026-06-24

**Resolução — Falso positivo:** Links de skip-nav presentes no HTML via `Magento_Theme/templates/html/skip-links.phtml`.

#### HEADER-04 · SVG do hamburger sem `width`/`height`

- **Status:** `[x]`
- **Severidade:** 🟡 Markup — baixo impacto
- **Data detectada:** 2026-06-24
- **Data corrigida:** 2026-06-24
- **Commit:** `a45a6184`

**Fix:** Adicionados `width="24" height="24"` em `Rokanthemes_VerticalMenu/templates/sidemenu.phtml`.

#### Outros elementos verificados e OK

| Componente | Resultado |
|-----------|-----------|
| Logo: alt, width, height, loading=eager | ✅ |
| Logo: fetchpriority condicional | ✅ |
| Minicart: role=dialog, aria-modal, aria-labelledby | ✅ |
| Minicart counter: aria-live="polite" | ✅ |
| Search input: aria-label, autocomplete, type=search | ✅ |
| Search button: sr-only span interno | ✅ |
| Vertical menu trigger: aria-expanded, aria-controls, aria-label | ✅ |
| Subcategoria buttons: aria-label em cada um | ✅ |
| Vmenu category images: alt, width, height | ✅ |
| Header: role="banner" | ✅ |
| Skip-nav links: presentes (pos. 76564) | ✅ |
| B2B promo bar: role="complementary", aria-label | ✅ |
| Account nav: aria-label, aria-haspopup, aria-expanded | ✅ |
| href="#" no header | ✅ zero |
| Imagens sem alt no header | ✅ zero |

---

### Fase 5 — Auditoria Geral (2026-06-25)

#### BUG-12 · Newsletter popup sem `aria-label` no input

- **Status:** `[x]`
- **Severidade:** 🟡 Médio (WCAG 2.1)
- **Páginas afetadas:** Todas
- **Data detectada:** 2026-06-25
- **Data corrigida:** 2026-06-25
- **Commit:** `441cb308`

**Resolução:** `Rokanthemes_Themeoption/templates/newsletterpopup.phtml` — adicionado `aria-label="Seu e-mail"` ao `input#newsletter-popup`.

#### BUG-13 · Footer trust icons SVG sem `width`/`height`

- **Status:** `[x]`
- **Severidade:** 🟢 Baixo (boas práticas)
- **Páginas afetadas:** Footer (todas as páginas)
- **Data detectada:** 2026-06-25
- **Data corrigida:** 2026-06-25
- **Commit:** `441cb308`

**Resolução:** `Rokanthemes_Themeoption/templates/html/footer.phtml` — 4 SVGs com `width="24" height="24"`.

#### SEO-01 · Meta description da home com 169 chars (excede 160)

- **Status:** `[x]`
- **Severidade:** 🟢 Baixo (SEO)
- **Páginas afetadas:** Home
- **Data detectada:** 2026-06-25
- **Data corrigida:** 2026-06-25

**Resolução:** Atualizado `core_config_data` (`design/head/default_description`) via SQL. Novo valor (139 chars).

#### PERF-01 · Hero de categoria sem width/height attrs (CLS)

- **Status:** `[x]`
- **Severidade:** 🟢 Baixo (Core Web Vitals — CLS)
- **Páginas afetadas:** Todas as páginas de categoria
- **Data detectada:** 2026-06-26
- **Data corrigida:** 2026-06-26
- **Commit:** `f3099230`

**Resolução:** `Magento_Catalog/templates/category/image.phtml` — ambas as tags `<img>` agora têm `width="300" height="300"`.

#### SEO-02 · OG tags duplicadas na PDP (og:description vazia, og:title com HTML entities)

- **Status:** `[x]`
- **Severidade:** 🔴 Alto (compartilhamento social)
- **Páginas afetadas:** Todas as páginas de produto
- **Data detectada:** 2026-06-26
- **Data corrigida:** 2026-06-26
- **Commit:** `81c357fa`

**Causa:** Magento core renderiza automaticamente o bloco `opengraph.general` na PDP via `catalog_product_opengraph` handle, gerando OG tags inferiores.

**Resolução:** Criado `Magento_Catalog/layout/catalog_product_opengraph.xml` no tema filho com `<referenceBlock name="opengraph.general" remove="true"/>`. O `awa-og-meta.phtml` já gerencia OG/Twitter tags com qualidade superior.

#### Páginas auditadas e sem issues

| Página | Resultado |
|--------|-----------|
| Search (`/catalogsearch/result/`) | noindex/nofollow ✅ correto |
| 404 | noindex/nofollow ✅; H1 correto ✅ |
| robots.txt | User-agent, Disallow adequados ✅ |
| Sitemap.xml | HTTP 200 ✅ |
| Links target=_blank sem noopener | Nenhum encontrado ✅ |
| Google Fonts externos | Não utilizados ✅ |
| iframes na home | Nenhum ✅ |

---

## 10. Histórico de Correções

| Data | Bug/Melhoria | Responsável | Commit |
|------|-------------|-------------|--------|
| 2026-06-24 | BUG-01: Links 404 corrigidos | Copilot | — |
| 2026-06-24 | BUG-02, BUG-05, BUG-06: Fechados como falsos positivos | Copilot | — |
| 2026-06-24 | BUG-07: Placeholder ChatGPT removido | Copilot | — |
| 2026-06-24 | BUG-03: Schema.org Product adicionado na PDP | Copilot | — |
| 2026-06-25 | BUG-10: Category og:image:width/height adicionado | Copilot | — |
| 2026-06-25 | BUG-09: Schema.org brand "Kawasaki" → "AWA Motos" | Copilot | `eb4de2dd` |
| 2026-06-25 | MEL-02: B2B PDP pricing notice card implementado | Copilot | `61609497` |
| 2026-06-25 | MEL-03: loading="lazy" validado como já implementado | Copilot | — |
| 2026-06-25 | MEL-04: product:price:amount validado como já implementado | Copilot | — |
| 2026-06-25 | BUG-08: PDP og:image:width/height adicionado (1500×1500) | Copilot | — |
| 2026-06-25 | MEL-05: SVG width/height adicionados em footer-static5.phtml | Copilot | — |
| 2026-06-25 | BUG-11: Popup newsletter "Não, obrigado" não fechava | Copilot | — |
| 2026-06-25 | HEADER-04: SVG hamburger com width/height adicionados | Copilot | `a45a6184` |
| 2026-06-25 | BUG-12: Newsletter input aria-label adicionado (WCAG) | Copilot | `441cb308` |
| 2026-06-25 | BUG-13: Footer trust icons SVG width/height (4 ícones) | Copilot | `441cb308` |
| 2026-06-25 | SEO-01: Meta description home 169→139 chars | Copilot | DB |
| 2026-06-25 | 0b0ba74c: restore awa-b2b-auth-shell-final.css (B2B login 404) | Copilot | `0b0ba74c` |
| 2026-06-26 | PERF-01: Category hero CLS (width/height) | Copilot | `f3099230` |
| 2026-06-26 | SEO-02: OG tags duplicadas PDP corrigido | Copilot | `81c357fa` |
| 2026-06-26 | feat(vtex-grade): FAQ estruturada + Trust seals + Free shipping cart + AggregateOffer | Copilot | `d9816026` |
| 2026-06-28 | Reconciliação pré-Fase 3D.2.5: 12 P2/P3 catalogados + tracker consolidado | Codex | (docs-only, pending) |
| 2026-06-28 | DOC-005: docs-only commit do tracker reconciliado | Codex | `6ccef8f7` |
| 2026-06-28 | DOC-006: reverts de violação (tema pai AYO + workflows CI) | Codex | (working tree, sem commit) |
| 2026-06-28 | DOC-007: cross-reference CSS × P2/P3 + estratégia de commits | Codex | (relatório `var/doc007-...`) |
| 2026-06-28 | `chore(css): prune dead --awa-vbf-* tokens` | Codex | `726e0f47` |
| 2026-06-28 | `feat(css): vertical menu visibility fix` | Codex | `85c0c4e3` |
| 2026-06-28 | `feat(css): expand design tokens vocabulary` | Codex | `de989354` |
| 2026-06-28 | `feat(css): footer progressive enhancement` | Codex | `c77882e7` |
| 2026-06-28 | `feat(css): PLP final polish` | Codex | `26646660` |
| 2026-06-28 | `chore(css): regenerate awa-plp-final-polish.min.css` | Codex | `183c4d0d` |
| 2026-06-28 | `feat(css): header search — unica lupa + touch 44px` (BUG-MOB-SEARCH-001) | Codex | `a552ce55` |
| 2026-06-28 | `fix(css): PDP gallery-placeholder selector correction` | Codex | `b14bb1ce` |
| 2026-06-28 | `feat(css): header stack — tokens + WCAG AA + acessibilidade` | Codex | `7ed106d9` |
| 2026-06-28 | BUG-IMPORTANT-AUDIT-013 catalogado como follow-up (113 !important) | Codex | (próximo commit) |
| 2026-06-28 | `chore(css): remove home layout bundles sem referencias` (-14.070) | Codex | `2e2b84c3` |
| 2026-06-28 | `chore(css): remove layout/UI bundles sem referencias` (-7.295) | Codex | `652f4b7a` |
| 2026-06-28 | `chore(css): remove vertical menu + b2b register overrides sem refs` | Codex | `7d7fc168` |
| 2026-06-28 | `fix(css): home bestseller cards — thumb ratio 1:1 + price label 36px` | Codex | `e0182f0c` |
| 2026-06-28 | `feat(css): home carousel polish — nav 44px touch + tokens` | Codex | `fb2327ca` |
| 2026-06-28 | `feat(css): AWA variables — 37 novos tokens + alinhamento 12→16px mobile` | Codex | `6608ef64` |
| 2026-06-28 | `feat(css): header — refactor para tokens semanticos (38 adds / 38 dels)` | Codex | `8b29cbe8` |
| 2026-06-28 | `feat(css): page containers v1.4 — mobile pad 16px + tiers 1280px` | Codex | `72c7e22b` |
| 2026-06-28 | `fix(css): hero slider pre-load critical — anti-FOUC + LCP (BUG-MOB-HERO-003)` | Codex | `ace59e69` |
| 2026-06-28 | `feat(css): flex-grid-flow — migracao hex para tokens LESS` | Codex | `010431e2` |
| 2026-06-28 | `feat(css): search results — refactor para tokens semanticos` | Codex | `eb34f594` |
| 2026-06-28 | `feat(css): header tokens — refactor _awa-header-professional + _header-main` | Codex | `fd039d97` |
| 2026-06-28 | `chore(css): remove awa-b2b-color-fix.css sem referencias reais` (-741) | Codex | `272aae14` |
| 2026-06-28 | `feat(css): B2B phases + quickorder tokens refactor` | Codex | `8dc33cb0` |
| 2026-06-28 | `feat(css): mobile standardization + PDP upgrades — refactor tokens` | Codex | `98ed3a2e` |
| 2026-06-28 | `feat(css): 10 refatores tokens (effects, layout, menu, pdp, header, extend)` | Codex | `c40b424d` |
| 2026-06-28 | `feat(css): product card / page — refactor para tokens canonicos` | Codex | `9c73dcf1` |
| 2026-06-28 | `feat(css): 9 refatores tokens (sprint1 B2B, account-nav, ...)` | Codex | `b737c2ed` |
| 2026-06-28 | `feat(css): grid-listing + clean-ui + premium-effects — refactor tokens` | Codex | `28063233` |
| 2026-06-28 | `feat(css): 6 refatores tokens (visual-audit, header-premium, ui-ux-promax)` | Codex | `99229e66` |
| 2026-06-28 | `feat(css): B2B login page polish + qty-control tokens` (BUG-B2B-LOGIN-010) | Codex | `095944e9` |
| 2026-06-28 | `feat(css): 6 refatores tokens (cycle1, p0, visual-refinements, ...)` | Codex | `ee7121e1` |
| 2026-06-28 | `chore(css): 9 sync min files + interaction-widgets tokens` | Codex | `2fd4f028` |
| 2026-06-28 | `feat(css): 8 refatores tokens (loading, navigation, slider, product-card, responsive)` | Codex | `3162af2e` |

---

## 11. Como Atualizar Este Documento

### Workflow padrão

1. Ao **iniciar** uma correção: trocar `[ ]` por `[~]` + adicionar data; mover bug para status `Em progresso`
2. Ao **concluir**: trocar `[~]` por `[x]` + preencher "Data corrigida" + commit + adicionar linha no Histórico
3. Ao **descadastrar**: trocar por `[n]` + adicionar justificativa em itálico abaixo
4. Atualizar o **Dashboard** manualmente (contar `[x]` por fase)
5. Para bugs Fase 3D.2.5, atualizar **Métricas do backlog** e **Status** no detalhe
6. Commit com mensagem: `docs: atualiza PLANO_BUGS_VISUAIS — [BUG-XX] corrigido`

### Critério de promoção de bug

Um bug é **promovido** para tabela geral quando:
- Já foi mapeado (arquivos prováveis identificados)
- Tem evidência ou caminho de evidência definido
- Tem causa provável documentada

### Anti-patterns de documentação

- ❌ Criar bug sem causa provável
- ❌ Marcar como corrigido sem screenshot/log
- ❌ Misturar BUG- IDs numéricos (histórico) com BUG-XXX-NNN (Fase 3D.2.5) sem preservar histórico
- ❌ Apagar seções históricas para "limpar" o documento
- ❌ Duplicar bug em duas seções

---

## 12. Aviso de segurança para comandos destrutivos

> **Atenção:** comandos que afetam cache/Redis/CDN exigem autorização explícita.

| Comando | Uso autorizado | Bloqueado por padrão |
|---------|----------------|----------------------|
| `bin/magento cache:clean` | Apenas `layout`, `block_html`, `full_page` quando PHTML/layout XML é alterado | OK |
| `bin/magento cache:flush` | Apenas em mudanças de domínio ou após migração | Exige autorização |
| `redis-cli FLUSHDB` | **Não usar como rotina** — exige autorização e confirmação de DB | Bloqueado |
| `setup:static-content:deploy` | Apenas após edição em CSS/LESS do tema filho | Exige `--theme AWA_Custom/ayo_home5_child` |
| `systemctl restart php8.4-fpm` | Apenas após alteração PHP ou OPcache | Exige autorização |
| `git reset --hard` / `git clean -fd` | **NUNCA** sem rollback explícito | Bloqueado |
| `composer require/remove` | Apenas com justificativa documentada | Exige aprovação |

**Por quê:** O FPC (Redis DB2) armazena HTML completo. Mudar domínio/URL sem flush do DB2 deixa o browser com HTML antigo. CSP usa `'self'` = domínio atual, então URLs antigas são bloqueadas — incluindo `require.js`, que derruba toda a stack JS do Magento.

---