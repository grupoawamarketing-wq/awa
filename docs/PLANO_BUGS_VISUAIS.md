# Plano de Bugs Visuais e Melhorias — AWA Motos

> **Living document** — atualizar status a cada correção aplicada.
> Auditoria inicial: 2026-06-24 | Varredura profunda: 2026-06-25
> Inspecionadas: Home, Categoria, PDP, Busca, 404

---

## Status Legend

| Badge | Significado |
|-------|-------------|
| `[ ]` | Não iniciado |
| `[~]` | Em progresso |
| `[x]` | Corrigido |
| `[s]` | Adiado (snooze) |
| `[n]` | Não será corrigido (won't fix) |

---

## Dashboard

| Fase | Total | Feitos | Falsos positivos | Pendentes |
|------|-------|--------|------------------|-----------|
| Fase 0 — Críticos | 2 | 1 | 1 | 0 |
| Fase 1 — Alto impacto | 5 | 4 | 0 | 1 |
| Fase 2 — Acessibilidade | 3 | 1 | 2 | 0 |
| Fase 3 — Melhorias | 5 | 4 | 0 | 1 |
| Fase 4 — Header | 4 | 1 | 2 | 1 |
| Fase 5 — Auditoria Geral | 3 | 3 | 0 | 0 |
| **Total** | **22** | **14** | **5** | **3** |

---

## Fase 0 — Críticos (Bloqueadores de Conversão)

> Corrigir imediatamente — impacto direto em vendas ou erros que o usuário vê.

---

### BUG-01 · Links "Ver todos" apontando para 404

- **Status:** `[x]`
- **Severidade:** 🔴 Crítico
- **Páginas afetadas:** Home
- **Data detectada:** 2026-06-24
- **Data corrigida:** 2026-06-24

**Resolução:** `top-home.phtml` e `HomeRecentOrders.php` — URLs corrigidas: carousel → `bauletos.html`, bestsellers/recent-orders → `ofertas.html`. Validado: 0 ocorrências de `default-category` no HTML renderizado.

---

### BUG-02 · CSS stylesheet carregado 2× em todas as páginas

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

## Fase 1 — Alto Impacto (SEO e Conversão)

---

### BUG-03 · Schema.org `Product` ausente na PDP

- **Status:** `[x]`
- **Severidade:** 🟠 Alto (SEO — perde rich snippets no Google)
- **Páginas afetadas:** Todas as PDPs
- **Data detectada:** 2026-06-24
- **Data corrigida:** 2026-06-24

**Resolução:** Adicionado bloco `awa.schema.product.jsonld` em `catalog_product_view.xml` do tema filho, usando `ProductStructuredData` ViewModel. PDP agora emite 4 blocos JSON-LD: `Organization`, `WebSite`, `Product` (com `name`, `sku`, `image`, `offers`, `brand`) e `BreadcrumbList`. Validado via curl.

---

### BUG-04 · Preços invisíveis para visitantes não logados

- **Status:** `[ ]`
- **Severidade:** 🟠 Alto (barreira de conversão)
- **Páginas afetadas:** Home, Categoria, PDP, Busca
- **Data detectada:** 2026-06-24
- **Data corrigida:** —

**Decisão necessária antes de corrigir:**
- [ ] **É intencional?** (modelo 100% B2B sem preço público) → marcar como `[n]`, melhorar apenas o visual do notice
- [ ] **Ou deve mostrar preço de varejo para visitantes?** → configurar Grupos de Cliente no Magento

*Nota: MEL-02 (visual do pricing notice B2B) já foi implementado como melhoria intermediária.*

---

### BUG-08 · PDP sem `og:image:width` / `og:image:height`

- **Status:** `[x]`
- **Severidade:** 🟠 Alto (SEO — previews sociais com dimensões incorretas, Facebook/WhatsApp podem renderizar errado)
- **Páginas afetadas:** Todas as PDPs
- **Data detectada:** 2026-06-25
- **Data corrigida:** 2026-06-25

**Resolução:** `awa-og-meta.phtml` — adicionado `getimagesize()` no bloco do produto. Obtém o caminho físico via `BP . '/pub/media/catalog/product' . $product->getImage()` e chama `getimagesize()`. Validado: PDP agora emite `og:image:width=1500 og:image:height=1500` para produtos com imagens 1500×1500px.

---

### BUG-09 · Schema.org `brand.name` retornando nome de moto (ex: "Kawasaki")

- **Status:** `[x]`
- **Severidade:** 🟠 Alto (SEO — Product schema com brand incorreta)
- **Páginas afetadas:** PDPs com atributo `manufacturer` preenchido com compatibilidade de moto
- **Data detectada:** 2026-06-25
- **Data corrigida:** 2026-06-25
- **Commit:** `eb4de2dd`

**Resolução:** `ProductStructuredData.php` — adicionada constante `MOTO_BRANDS` com lista de marcas de moto (Honda, Kawasaki, Yamaha, etc.). Método `resolveBrandName()` detecta quando o atributo `manufacturer` contém nome de moto e retorna `'AWA Motos'` como brand real do produto. Validado: `brand.name = "AWA Motos"`.

---

### BUG-10 · Category `og:image` sem `og:image:width` / `og:image:height`

- **Status:** `[x]`
- **Severidade:** 🟠 Alto (SEO)
- **Páginas afetadas:** Páginas de categoria com imagem cadastrada
- **Data detectada:** 2026-06-25
- **Data corrigida:** 2026-06-25

**Resolução:** `awa-og-meta.phtml` — adicionado `getimagesize()` no bloco de categoria. Obtém path físico da imagem via `pub/media/catalog/category/{filename}`. Validado: categoria emite `og:image:width=300 og:image:height=300`.

---

## Fase 2 — Acessibilidade e Qualidade de Markup

---

### BUG-05 · 8 imagens com `alt=""` na home

- **Status:** `[n]`
- **Severidade:** 🟡 Médio (WCAG 2.1 AA)
- **Data detectada:** 2026-06-24
- **Data corrigida:** N/A — Falso positivo

**Resolução — Falso positivo:**
Todas as 7 imagens com `alt=""` têm `aria-hidden="true"` (imagens decorativas com acessibilidade no elemento pai). Padrão WCAG correto.

---

### BUG-06 · Tag `<head>` duplicada no DOM

- **Status:** `[n]`
- **Severidade:** 🟡 Médio
- **Data detectada:** 2026-06-24
- **Data corrigida:** N/A — Falso positivo

**Resolução — Falso positivo:**
A segunda ocorrência de `<head>` (pos. 74764) está dentro de um comentário HTML. As OG meta tags estão corretamente dentro do `<head>` real (que fecha em pos. 76397).

---

### BUG-07 · 5 produtos com imagem placeholder na home

- **Status:** `[x]`
- **Severidade:** 🟡 Médio (visual)
- **Páginas afetadas:** Home (carrosséis)
- **Data detectada:** 2026-06-24
- **Data corrigida:** 2026-06-24

**Resolução:** Removidas as 4 entradas de `core_config_data` com as imagens ChatGPT (`ChatGPT_Image_8_de_mai._de_2026_15_13_21*.webp`). Magento usa placeholder padrão. Redis DB2 e Varnish purgados. Validado: 0 ocorrências de `ChatGPT` no HTML da home.

---

## Fase 3 — Melhorias (Nice-to-Have)

---

### MEL-01 · Consolidar e reduzir quantidade de arquivos CSS

- **Status:** `[ ]`
- **Severidade:** 🟢 Baixo (performance)
- **Data detectada:** 2026-06-24

*Complexidade alta — requer auditoria de dependências entre bundles. Deferred.*

---

### MEL-02 · Melhorar visual do pricing-notice B2B

- **Status:** `[x]`
- **Severidade:** 🟢 Baixo (UX)
- **Data detectada:** 2026-06-24
- **Data corrigida:** 2026-06-25
- **Commit:** `61609497`

**Resolução:** Adicionado card `.awa-b2b-credit-notice` em `awa-pdp-premium.css` (~120 linhas). Notice com ícone de cadeado, texto contextual e CTA de login/cadastro. CSS deployed, brotli regenerado, Varnish purgado. Validado: 8 regras CSS `b2b-login-to-see-price` presentes no CSS servido.

---

### MEL-03 · Adicionar `loading="lazy"` nas imagens dos carrosséis

- **Status:** `[x]`
- **Severidade:** 🟢 Baixo (performance / CLS)
- **Data detectada:** 2026-06-24
- **Data corrigida:** 2026-06-25

**Resolução:** Já implementado nos templates de carrossel existentes. Validado: imagens de carrossel têm `loading="lazy"` no HTML renderizado.

---

### MEL-04 · Open Graph sem `og:price:amount` em produto compartilhado

- **Status:** `[x]`
- **Severidade:** 🟢 Baixo (social media preview)
- **Data detectada:** 2026-06-24
- **Data corrigida:** 2026-06-25

**Resolução:** A propriedade correta é `product:price:amount` (não `og:price:amount`). Implementado no `ViewModel/OpenGraph.php` — `getMetaData()` inclui `price_amount` via `$product->getFinalPrice()`. Validado: PDP emite `<meta property="product:price:amount" content="...">` e `product:price:currency = BRL`.

---

### MEL-05 · SVGs sem atributos `width`/`height` explícitos no footer

- **Status:** `[x]`
- **Severidade:** 🟢 Baixo (boas práticas — fallback quando CSS não carrega)
- **Páginas afetadas:** Footer (email icon + social media icons)
- **Data detectada:** 2026-06-25
- **Data corrigida:** 2026-06-25

**Resolução:** `footer-static5.phtml` — adicionados `width="24" height="24"` no SVG do ícone de e-mail da newsletter e `width="20" height="20"` nos SVGs de links de redes sociais. CSS continua fazendo override mas os atributos servem como fallback seguro.

---

## Histórico de Correções

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

---

## Como Atualizar Este Documento

1. Ao **iniciar** uma correção: trocar `[ ]` por `[~]` + adicionar data
2. Ao **concluir**: trocar `[~]` por `[x]` + preencher "Data corrigida" + adicionar linha no Histórico
3. Ao **descartar**: trocar por `[n]` + adicionar justificativa em itálico abaixo
4. Atualizar o **Dashboard** manualmente (contar `[x]` por fase)
5. Commit com mensagem: `docs: atualiza PLANO_BUGS_VISUAIS — [BUG-XX] corrigido`

---

## Fase 4 — Auditoria do Header (2026-06-24)

> Auditoria profunda de todos os elementos do header: logo, vmenu, search, minicart, account nav, skip-nav, semântica, performance e acessibilidade.

---

### HEADER-01 · Imagens duplicadas no menu vertical

- **Status:** `[ ]`
- **Severidade:** 🔴 Visual (bug de conteúdo — 3 pares de categorias com a mesma imagem)
- **Data detectada:** 2026-06-24
- **Tipo:** Tarefa de conteúdo — requer upload de imagens no admin

**Categorias com imagens erradas (exibidas no vmenu):**

| Categoria | Imagem atual | Deveria ser |
|-----------|-------------|-------------|
| Protetores de Carter | `cat-suporte.jpg` (foto de suporte) | Imagem específica de protetor de carter |
| Antenas | `cat-pisca.jpg` (foto de pisca/lente) | Imagem específica de antena |
| Carcaças | `cat-outros.png` (genérico) | Imagem específica de carcaça |

**Fix:** Admin → Catálogo → Categorias → selecionar cada categoria → campo "Imagem" → fazer upload de foto adequada.

---

### HEADER-02 · Logo sem `fetchpriority="high"` (home page)

- **Status:** `[n]`
- **Severidade:** 🟠 Performance — Falso positivo
- **Data detectada:** 2026-06-24
- **Data fechado:** 2026-06-24

**Resolução — Falso positivo:**
O template `logo.phtml` já implementa a lógica correta: na home page, `fetchpriority` é **intencionalmente omitido** para não competir com o hero slider (LCP real da home). Nas demais páginas, `fetchpriority="high"` é aplicado automaticamente.

---

### HEADER-03 · Skip-nav link ausente

- **Status:** `[n]`
- **Severidade:** 🟡 Acessibilidade — Falso positivo
- **Data detectada:** 2026-06-24
- **Data fechado:** 2026-06-24

**Resolução — Falso positivo:**
Os links de skip-nav estão presentes no HTML (pos. 76564, antes do `<header>`) via template `Magento_Theme/templates/html/skip-links.phtml` (override AWA customizado). A auditoria checou apenas os primeiros 1000 chars do HTML e não localizou os links que aparecem em posição mais tardia.

---

### HEADER-04 · SVG do hamburger sem `width`/`height`

- **Status:** `[x]`
- **Severidade:** 🟡 Markup — baixo impacto
- **Data detectada:** 2026-06-24
- **Data corrigida:** 2026-06-24
- **Commit:** `a45a6184`

**Descrição:**
O SVG hambúrguer do vmenu trigger (`<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">`) não tinha atributos `width`/`height` explícitos.

**Fix:**
Adicionados `width="24" height="24"` no arquivo `Rokanthemes_VerticalMenu/templates/sidemenu.phtml`.

---

### Outros elementos verificados e OK

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

## Fase 5 — Auditoria Geral (2026-06-25)

> Varredura de qualidade em todas as páginas: acessibilidade, SEO e markup.

---

### BUG-12 · Newsletter popup sem `aria-label` no input

- **Status:** `[x]`
- **Severidade:** 🟡 Médio (WCAG 2.1 — input sem label acessível)
- **Páginas afetadas:** Todas (popup newsletter aparece em todas as páginas)
- **Data detectada:** 2026-06-25
- **Data corrigida:** 2026-06-25
- **Commit:** `441cb308`

**Resolução:** `Rokanthemes_Themeoption/templates/newsletterpopup.phtml` — adicionado `aria-label="Seu e-mail"` ao `input#newsletter-popup`. Validado no HTML renderizado.

---

### BUG-13 · Footer trust icons SVG sem `width`/`height`

- **Status:** `[x]`
- **Severidade:** 🟢 Baixo (boas práticas — fallback quando CSS não carrega)
- **Páginas afetadas:** Footer (todas as páginas)
- **Data detectada:** 2026-06-25
- **Data corrigida:** 2026-06-25
- **Commit:** `441cb308`

**Resolução:** `Rokanthemes_Themeoption/templates/html/footer.phtml` — 4 SVGs com `<use href="#awa-icon-*">` (truck, shield, gear, b2b) agora têm `width="24" height="24"`. Validado no HTML renderizado.

---

### SEO-01 · Meta description da home com 169 chars (excede 160)

- **Status:** `[x]`
- **Severidade:** 🟢 Baixo (SEO — Google pode truncar em SERPs)
- **Páginas afetadas:** Home (design/head/default_description)
- **Data detectada:** 2026-06-25
- **Data corrigida:** 2026-06-25

**Resolução:** Atualizado `core_config_data` (`design/head/default_description`) via SQL. Novo valor (139 chars): *"AWA Motos — Especialistas em peças e acessórios para motos. Baús, bagageiros, retrovisores, luvas e muito mais. Entrega para todo o Brasil."* FPC purgado e Varnish purgado. Validado: 139 chars no HTML ao vivo.

---

### Páginas auditadas e sem issues

| Página | Resultado |
|--------|-----------|
| Search (`/catalogsearch/result/`) | noindex/nofollow ✅ correto; imgs sem alt são KO bindings ✅ |
| 404 | noindex/nofollow ✅; H1 "Página não encontrada" ✅ |
| robots.txt | User-agent, Disallow adequados ✅; Sitemap directive ✅ |
| Sitemap.xml | HTTP 200 ✅ |
| Links target=_blank sem noopener | Nenhum encontrado em home/cat/pdp ✅ |
| Google Fonts externos | Não utilizados ✅ |
| iframes na home | Nenhum ✅ |
