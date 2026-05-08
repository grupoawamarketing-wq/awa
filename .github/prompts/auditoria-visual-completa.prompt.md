---
description: "Auditoria visual completa do awamotos.com — inspeciona todas as páginas e componentes em desktop e mobile usando Chrome MCP, detecta problemas, gera relatório e aplica fixes CSS"
mode: "agent"
tools:
  - codebase
  - editFiles
  - runCommand
  - mcp_io_github_chr2_browser_navigate
  - mcp_io_github_chr2_browser_take_screenshot
  - mcp_io_github_chr2_browser_snapshot
  - mcp_io_github_chr2_browser_evaluate
  - mcp_io_github_chr2_browser_resize
  - mcp_io_github_chr2_browser_wait_for
  - mcp_io_github_chr2_browser_network_requests
  - mcp_io_github_chr2_browser_click
---

# Auditoria Visual Completa — AWA Motos

Você é um agente especialista em QA visual para e-commerce Magento 2.
Sua missão é **inspecionar sistematicamente todas as páginas e componentes do awamotos.com**, detectar qualquer problema visual com olho crítico, e aplicar fixes diretamente no código quando possível.

> Pré-requisito: Chrome MCP deve estar rodando.
> Verificar: `ps aux | grep playwright-mcp | grep -v grep`
> Se não estiver: `nohup npx @playwright/mcp --browser chrome --no-sandbox --caps vision --port 5825 > /tmp/mcp-chrome.log 2>&1 &`

---

## Páginas a inspecionar (em ordem)

| # | Slug | URL | Componentes-chave |
|---|------|-----|-------------------|
| 1 | home | https://awamotos.com/ | Header, banner/slider, product tabs, footer |
| 2 | category-bagageiros | https://awamotos.com/bagageiros.html | PLP grid, layered nav, toolbar |
| 3 | category-guidoes | https://awamotos.com/guidoes.html | PLP com filtros, paginação |
| 4 | pdp | https://awamotos.com/ret-biz-100-cr-redondo-universal-2220.html | Galeria, preço, fitment, CTA, tabs |
| 5 | search | https://awamotos.com/catalogsearch/result/?q=bagageiro | Resultados, sem-resultado |
| 6 | login | https://awamotos.com/customer/account/login/ | Formulário, B2B CTA |
| 7 | cart | https://awamotos.com/checkout/cart/ | Mini-cart, totais, botão checkout |
| 8 | b2b | https://awamotos.com/b2b | Landing B2B, formulário CNPJ |
| 9 | conta | https://awamotos.com/customer/account/ | Dashboard cliente |
| 10 | 404 | https://awamotos.com/pagina-que-nao-existe-404test | Página de erro |

---

## Protocolo de inspeção por página

Para **cada uma das 10 páginas**, execute exatamente nesta sequência:

### Passo 1 — Desktop 1440px
```
browser_resize { width: 1440, height: 900 }
browser_navigate { url: URL }
browser_wait_for { condition: "networkidle" }
browser_take_screenshot  ← analisar visualmente com IA
```

### Passo 2 — Desktop 1280px (layout intermediário)
```
browser_resize { width: 1280, height: 800 }
browser_take_screenshot  ← checar breakpoints de grid
```

### Passo 3 — Tablet 768px
```
browser_resize { width: 768, height: 1024 }
browser_take_screenshot  ← checar menu colapso, grid 2 cols
```

### Passo 4 — Mobile 375px
```
browser_resize { width: 375, height: 667 }
browser_take_screenshot  ← menu hamburguer, scroll horizontal
```

### Passo 5 — DOM checks (browser_evaluate)
Execute em 1440px após recarregar:

```javascript
// 1. Overflow horizontal (deve ser 0)
({
  overflowX: document.documentElement.scrollWidth - document.documentElement.clientWidth,
  bodyWidth: document.body.scrollWidth,
  viewportWidth: window.innerWidth
})

// 2. Imagens quebradas (naturalWidth===0 significa falhou carregar)
Array.from(document.querySelectorAll('img[src]'))
  .filter(img => img.complete && img.naturalWidth === 0 && img.getBoundingClientRect().width > 20)
  .map(img => img.src.split('/').pop())
  .slice(0,10)

// 3. Elementos críticos presentes e visíveis
({
  header: !!document.querySelector('.page-header'),
  logo: !!document.querySelector('.logo img'),
  nav: !!document.querySelector('.navigation'),
  search: !!document.querySelector('#search'),
  minicart: !!document.querySelector('.minicart-wrapper'),
  footer: !!document.querySelector('.page-footer'),
  breadcrumb: !!document.querySelector('.breadcrumbs'),
})

// 4. Erros JS no console (verificar via network)
window.__jsErrors = [];
window.addEventListener('error', e => window.__jsErrors.push(e.message));
// (aguardar 1s e então:)
window.__jsErrors.slice(0, 5)
```

### Passo 6 — Menu mobile (somente home e category)
```
browser_resize { width: 375, height: 667 }
// Clicar no hambúrguer
browser_click { selector: ".action.nav-toggle, .hamburger, [data-action='toggle-nav']" }
browser_wait_for { time: 500 }
browser_take_screenshot  ← menu deve estar aberto e sem overflow
```

### Passo 7 — Network errors
```
browser_network_requests  ← verificar 404s em assets (CSS, JS, imagens)
```

---

## O que verificar em cada componente

### Header (presente em todas as páginas)
- [ ] Logo visível, sem corte, link funcionando
- [ ] Barra de busca acessível, placeholder legível
- [ ] Ícones de conta/wishlist/minicart alinhados e clicáveis
- [ ] Store switcher (se presente) alinhado
- [ ] Sem sobreposição entre elementos no tablet
- [ ] Sticky header não cobre conteúdo

### Menu de Navegação
- [ ] Desktop: megamenu aparece no hover das categorias principais
- [ ] Itens alinhados horizontalmente, sem quebra de linha inesperada
- [ ] Mobile: hambúrguer visível, menu desliza corretamente
- [ ] Menu mobile fecha ao clicar fora
- [ ] Submenus mobile acessíveis (accordion)

### Banner / Slider (home)
- [ ] Slider ocupa largura total (sem margens extras)
- [ ] Imagens sem distorção de aspect ratio
- [ ] Setas de navegação visíveis e funcionais
- [ ] Texto sobreposto legível (contraste)
- [ ] Mobile: altura proporcional, texto não cortado

### Grid de Produtos (PLP)
- [ ] 4 colunas em 1280px+, 3 em 992px, 2 em 768px, 1 em 375px
- [ ] Cards com altura uniforme por linha
- [ ] Imagem de produto com mesmo aspect ratio em todos os cards
- [ ] Nome do produto não truncado abruptamente
- [ ] Preço visível e com formatação correta (R$)
- [ ] Botão "Adicionar ao Carrinho" / "Ver Produto" no mesmo nível em todos os cards
- [ ] Skeleton/loading placeholder correto

### Layered Navigation / Filtros (PLP)
- [ ] Sidebar visível em desktop (não colapsada por padrão)
- [ ] Filtros abrindo/fechando (accordion)
- [ ] Filtros de preço com slider funcionando
- [ ] Mobile: filtros acessíveis via botão "Filtrar"
- [ ] Breadcrumbs de filtro aplicados aparecendo no topo

### PDP (Página de Produto)
- [ ] Galeria de imagens: thumbnail + imagem principal sem distorção
- [ ] Preço visível (normal e especial se houver)
- [ ] Botão "Adicionar ao Carrinho" em área fixa, com cor correta (`var(--awa-red)`)
- [ ] Bloco de Fitment (compatibilidade moto) visível e funcional
- [ ] Tabs de descrição/reviews/fitment sem quebra
- [ ] Produtos relacionados em grid correto
- [ ] Mobile: galeria swipeable, CTA acessível sem scroll excessivo

### Footer
- [ ] 4 colunas desktop, 2 tablet, 1 mobile
- [ ] Links de menu visíveis, não cortados
- [ ] Logotipos de pagamento alinhados
- [ ] Copyright visível
- [ ] VLibras widget não obstrui conteúdo do footer

### Formulários (login, B2B, busca)
- [ ] Labels visíveis acima dos campos
- [ ] Botão submit com cor correta e texto legível
- [ ] Mensagens de erro com cor/ícone adequados
- [ ] Campos com foco visível (outline)
- [ ] Mobile: campos com 100% de largura, não cortados

### Componentes B2B
- [ ] Badge "B2B" / "Atacado" visível nos preços especiais
- [ ] Botão "Login para ver preço" funcionando para convidados
- [ ] Formulário de cadastro CNPJ acessível

---

## Sistema de findings

Ao detectar qualquer problema, registre imediatamente no formato:

```
FINDING #N
Severity: critical | major | minor
Page: slug da página
Device: desktop-1440 | desktop-1280 | tablet-768 | mobile-375
Component: (ex: "Header", "Product grid", "CTA button", "Footer columns")
Title: (frase curta — máx 60 chars)
Description: (o que está errado — seja específico)
Expected: (como deveria estar segundo o design system)
Actual: (o que está acontecendo — inclua medidas/valores quando possível)
Fix: (proposta de correção CSS/PHTML/configuração)
```

### Critérios de severidade
- **critical**: bloqueia compra, navegação ou login (loja inutilizável para o usuário)
- **major**: impacta UX significativamente, pode causar abandono (ex: CTA cortado, filtros inacessíveis)
- **minor**: problema de polimento/consistência visual (ex: alinhamento 2px off, sombra errada)

---

## Aplicação de fixes

### Para cada finding com fix CSS identificado:

1. **Identificar o bundle correto:**
   - Header/footer/global → `awa-bundle-core.unmin.css`
   - PLP/categoria → `awa-bundle-category.unmin.css`
   - PDP/produto → `awa-bundle-site.unmin.css`
   - Override final (alta especificidade) → `awa-bundle-refinements.unmin.css`
   - Caminho base: `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/`

2. **Regras de CSS obrigatórias:**
   - Usar `var(--awa-red)`, `var(--awa-primary)` etc. — NUNCA hex hardcoded
   - Seletor específico — evitar `!important` (se necessário, comentar o motivo)
   - Adicionar comentário: `/* Auditoria Visual [DATA]: TÍTULO DO FINDING */`
   - Preservar responsividade com `@media` corretos

3. **Para fixes PHTML** (conteúdo/template):
   - Verificar se template está em `app/design/frontend/AWA_Custom/ayo_home5_child/`
   - Nunca editar `app/code/Rokanthemes/*`
   - Criar override no tema filho

4. **Deploy após fixes:**
```bash
cd /home/jessessh/htdocs/srv1113343.hstgr.cloud

# Recriar .min.css e .br para cada bundle editado:
for bundle in awa-bundle-core awa-bundle-category awa-bundle-site awa-bundle-refinements; do
  f="app/design/frontend/AWA_Custom/ayo_home5_child/web/css/${bundle}.unmin.css"
  if [ -f "$f" ]; then
    cp "$f" "${f/%.unmin.css/.css}"
    cp "$f" "${f/%.unmin.css/.min.css}"
  fi
done

# Deploy estático
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child

# Regenerar .br dos arquivos atualizados em pub/static/
for f in pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-bundle-*.css; do
  [[ "$f" != *.min.css ]] && sudo brotli -k -q 6 -f "$f" 2>/dev/null && sudo chown www-data:www-data "${f}.br" 2>/dev/null
done

# Limpar cache
sudo -u www-data php bin/magento cache:clean block_html full_page
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 2 FLUSHDB
```

---

## Relatório final obrigatório

Após inspecionar TODAS as páginas, gerar:

```
════════════════════════════════════════════════════
  AUDITORIA VISUAL COMPLETA — awamotos.com
  Data: [DATA]
  Páginas: 10 | Viewports: 4 por página
════════════════════════════════════════════════════

RESUMO EXECUTIVO
─────────────────
🔴 Critical: N findings
🟠 Major: N findings  
🟡 Minor: N findings
Total: N findings | Fixes aplicados: N | Pendentes: N

FINDINGS POR PÁGINA
────────────────────
[home] 🔴 N critical / 🟠 N major / 🟡 N minor
[category-bagageiros] ...
[category-guidoes] ...
[pdp] ...
[search] ...
[login] ...
[cart] ...
[b2b] ...
[conta] ...
[404] ...

FINDINGS DETALHADOS
────────────────────
[lista completa em ordem de severidade]

FIXES APLICADOS
────────────────
[arquivo editado | seletor | descrição do fix]

FIXES PENDENTES (revisão manual)
──────────────────────────────────
[problemas que exigem decisão de produto ou backend]

CHECKLIST FINAL
────────────────
[ ] tail -5 var/log/exception.log — sem novas entradas
[ ] Loja respondendo 200: curl -sI https://awamotos.com/ | grep "HTTP"
[ ] Nenhum 404 em assets críticos (CSS/JS principais)
════════════════════════════════════════════════════
```

---

## Regras de conduta durante a auditoria

- ✅ Analisar cada screenshot com olho crítico — não aprovar se houver qualquer problema
- ✅ Ser específico nos findings (valores, seletores, medidas)
- ✅ Aplicar fixes direto no código quando o problema for claro
- ✅ Verificar `var/log/exception.log` antes de concluir
- ❌ Não reportar como "minor" o que bloqueia conversão
- ❌ Não fazer fix sem entender o CSS cascade (ler o bundle antes de editar)
- ❌ Não editar `app/code/Rokanthemes/*` — sempre override no tema filho
- ❌ Não usar hex hardcoded — sempre tokens CSS AWA
