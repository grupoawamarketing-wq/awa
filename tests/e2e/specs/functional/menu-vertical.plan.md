# Plano de Testes — Menu Vertical Lateral (Rokanthemes_VerticalMenu)

## Application Overview

Plano de testes para o menu vertical lateral da loja AWA Motos (awamotos.com), implementado pelo módulo Rokanthemes_VerticalMenu no tema Ayo. O menu fica no lado esquerdo da homepage e páginas de categoria, exibe hierarquia de categorias de motos (marcas → modelos → tipos de peça) e possui comportamento distinto entre desktop (hover expandindo submenus) e mobile (drawer off-canvas via botão hambúrguer). Seletores principais: `.awa-vertical-nav`, `.vertical-menu-custom-block`, `.vertical-menu`, `.awa-vertical-menu`. Módulo: Rokanthemes_VerticalMenu. Tema: Ayo (ayo_home5_child). Config. Playwright: tests/e2e/pw-functional.config.ts — projetos func-desktop (1280x800/Firefox) e func-mobile (375x667/Firefox).

## Test Scenarios

### 1. Menu Vertical — Presença e Estrutura (Desktop)

**Seed:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

#### 1.1. 01 — Contêiner do menu vertical é renderizado na homepage

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com (viewport 1280×800)
    - expect: A página carrega com status 200 sem erros críticos no console
  2. Localizar o contêiner do menu vertical usando os seletores: `.awa-vertical-nav`, `.vertical-menu-custom-block`, `.vertical-menu`, `#vertical-menu-wrapper`, `[data-block='vertical-menu']`
    - expect: Pelo menos um seletor resolve para um elemento presente no DOM
    - expect: O elemento contêiner está visível na viewport (não display:none nem visibility:hidden)
  3. Verificar posição do menu no layout
    - expect: O menu está posicionado no lado esquerdo da página (bounding box com left entre 0px e 300px)
    - expect: O menu não sobrepõe o conteúdo principal da página

#### 1.2. 02 — Menu vertical contém itens de categoria de nível 1

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800
    - expect: Homepage carrega com sucesso
  2. Localizar todos os itens `li` de primeiro nível: `.vertical-menu > ul > li`, `.awa-vertical-menu > ul > li`, `.vertical-menu-custom-block ul.level0 > li`
    - expect: Existem pelo menos 3 itens de nível 1
    - expect: Cada item tem um elemento `<a>` com texto visível e href válido (não '#' e não vazio)
  3. Para cada um dos primeiros 5 itens, verificar o `textContent` do link e o atributo `href`
    - expect: Texto não é vazio nem apenas whitespace
    - expect: href começa com 'https://awamotos.com' ou '/' (URL relativa válida)
    - expect: href não é '#' nem 'javascript:void(0)'

#### 1.3. 03 — Ícone de seta aparece em itens pai (com filhos)

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800
    - expect: Menu vertical está visível
  2. Localizar itens de nível 1 que possuem filhos: `.vertical-menu > ul > li.parent`, `.awa-vertical-menu > ul > li.has-children`
    - expect: Ao menos um item pai é encontrado
  3. Verificar via `getComputedStyle` a presença de indicador visual (ícone de seta) em itens com filhos
    - expect: Itens pai têm ícone de expandir/submenu visível (opacity > 0, display != none)
    - expect: Itens folha (sem filhos) não têm ícone de seta ou têm ícone desabilitado

#### 1.4. 04 — Imagens de categoria no menu carregam corretamente

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800
    - expect: Menu vertical está visível
  2. Localizar todas as `<img>` dentro do menu: `.vertical-menu img, .awa-vertical-menu img, .vertical-menu-custom-block img`
    - expect: Elementos de imagem são encontrados (ou teste é pulado se não houver imagens no menu)
  3. Para cada imagem (máximo 10): verificar `naturalWidth > 0` e `complete === true`
    - expect: Todas as imagens de categoria carregam sem 404
    - expect: Nenhuma imagem tem `alt` vazio
    - expect: Imagens são thumbnails apropriados (width <= 200px) — não full-size

### 2. Menu Vertical — Toggle Abrir/Fechar (Desktop)

**Seed:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

#### 2.1. 05 — Menu inicia expandido por padrão em desktop

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800 sem qualquer interação prévia
    - expect: Em desktop, lista de categorias está visível sem necessidade de clique no toggle
    - expect: OU existe um estado 'collapsed por padrão' com toggle visível — ambos são estados válidos a documentar
  2. Verificar estado inicial: checar se `.awa-vertical-nav`, `.vertical-menu-custom-block` está visível ou se existe classe `.is-open`, `.active`, `.expanded` no contêiner
    - expect: Estado inicial é consistente entre reloads de página
    - expect: Estado inicial é consistente entre homepage e páginas de categoria

#### 2.2. 06 — Clicar no toggle colapsa o menu (se toggle existir em desktop)

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800
    - expect: Menu vertical está presente
  2. Localizar botão toggle: `.vertical-menu-toggle`, `.awa-vertical-toggle`, `[data-action='toggle-vertical-menu']`, botão próximo ao contêiner `.awa-vertical-nav`. Se não existir em desktop, pular o teste
    - expect: Botão toggle é encontrado e está visível (ou teste é pulado com console.warn se não houver toggle em desktop)
  3. Clicar no botão toggle. Aguardar 500ms para animação de colapso
    - expect: Lista de categorias fica oculta (display:none, height:0 ou fora da viewport)
    - expect: Classe de estado muda (ex: `.collapsed`, `.closed`, remoção de `.open`)
    - expect: Conteúdo principal pode ocupar mais espaço horizontal
  4. Clicar novamente no toggle. Aguardar 500ms
    - expect: Menu se expande novamente com animação
    - expect: Lista de categorias volta a ser visível
    - expect: Estado retorna ao inicial — comportamento é toggle idempotente

### 3. Menu Vertical — Submenus e Hover (Desktop)

**Seed:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

#### 3.1. 07 — Hover em item de nível 1 com filhos exibe submenu de nível 2

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800
    - expect: Menu vertical está visível com itens de nível 1
  2. Localizar o primeiro item de nível 1 com filhos (`.parent` ou `.has-children`). Fazer hover com `element.hover()` e aguardar 500ms
    - expect: Submenu de nível 2 fica visível (`.level1`, `.submenu`, `.dropdown-content` dentro do item pai)
    - expect: Submenu aparece adjacente ao item pai (à direita ou logo abaixo)
    - expect: Nenhum overflow horizontal indesejado
  3. Verificar conteúdo do submenu de nível 2
    - expect: Submenu tem pelo menos 2 itens
    - expect: Cada item tem href válido (não '#', não vazio)
    - expect: Itens correspondem a modelos de moto (ex: CG 160, Titan, Fan, Bros 160, XRE 300)
  4. Mover o mouse para fora do item (hover no body). Aguardar 500ms
    - expect: Submenu fecha ou fica oculto
    - expect: Nenhum resíduo visual (submenu fantasma) permanece na tela

#### 3.2. 08 — Hover em item de nível 2 exibe submenu de nível 3 (se existir)

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800
    - expect: Menu vertical está visível
  2. Fazer hover no item de nível 1 pai para expandir o nível 2. Após 400ms, fazer hover em um item de nível 2 que possua filhos (`.parent` dentro do `.level1`). Aguardar 400ms
    - expect: Submenu de nível 3 fica visível (`.level2`, segundo `.submenu` aninhado)
    - expect: Submenu de nível 3 aparece sem sobrepor ou deslocar o submenu de nível 2
    - expect: Posicionamento correto: não sai da viewport
  3. Verificar itens do submenu de nível 3
    - expect: Itens têm links válidos (ex: tipos de peça — bagageiro, retrovisor, acessório)
    - expect: Se não houver nível 3, teste é pulado com console.info indicando que a hierarquia tem apenas 2 níveis

#### 3.3. 09 — Hover em item folha (sem filhos) NÃO exibe submenu

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800
    - expect: Menu vertical está visível
  2. Localizar um item de nível 1 que NÃO possui `.parent` ou `.has-children`. Fazer hover sobre ele por 600ms
    - expect: Nenhum submenu aparece na tela
    - expect: Não há elemento `.submenu` ou `.level1` visível associado a este item
    - expect: Cursor muda para `pointer` mas nenhum dropdown é exibido

#### 3.4. 10 — Submenu não é cortado pela borda inferior da viewport

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800
    - expect: Menu vertical está visível
  2. Fazer hover no último item de nível 1 com filhos (o mais próximo ao rodapé). Aguardar 500ms
    - expect: Submenu de nível 2 fica totalmente dentro da viewport (bounding box bottom <= window.innerHeight)
    - expect: Submenu não é cortado pelo limite inferior da tela
    - expect: Se necessário, submenu abre para cima (flip vertical) para evitar overflow

#### 3.5. 11 — Somente um submenu de nível 1 fica aberto por vez

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800
    - expect: Menu vertical está visível com múltiplos itens pai
  2. Fazer hover no primeiro item pai e aguardar 400ms. Em seguida, mover hover para o segundo item pai e aguardar 400ms
    - expect: O submenu do primeiro item fecha quando o hover sai
    - expect: O submenu do segundo item abre corretamente
    - expect: Não há dois submenus de nível 2 abertos simultaneamente
    - expect: Não há bug de submenu 'preso' aberto após hover sair

### 4. Menu Vertical — Navegação por Clique (Desktop)

**Seed:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

#### 4.1. 12 — Clicar em item de nível 1 navega para a categoria correta

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800
    - expect: Menu vertical está visível com itens de categoria
  2. Registrar o texto e href do primeiro item de nível 1. Clicar no link do item
    - expect: Navegação ocorre para a URL registrada no href
    - expect: Página de categoria carrega com status 200
    - expect: URL da barra de endereços corresponde ao href do item clicado
  3. Verificar a página de categoria carregada
    - expect: Título da página (`<h1>`) contém o nome da categoria clicada
    - expect: Breadcrumb exibe o caminho correto (Home > Categoria)
    - expect: Grid de produtos da categoria está presente (não está vazia ou com erro)

#### 4.2. 13 — Clicar em item de nível 2 (no submenu) navega para subcategoria de modelo

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800
    - expect: Menu vertical está visível
  2. Fazer hover no primeiro item pai de nível 1 para expandir o submenu. Aguardar 400ms. Clicar em um item de nível 2 (ex: link de modelo de moto como 'CG 160')
    - expect: Navegação ocorre para URL da subcategoria (ex: /honda/cg-160.html ou similar)
    - expect: Página de subcategoria carrega com status 200
    - expect: H1 da página contém o nome do modelo de moto clicado
  3. Verificar os produtos listados na subcategoria
    - expect: Grid de produtos exibe itens compatíveis com o modelo de moto selecionado
    - expect: Paginação funciona se houver mais de uma página
    - expect: Filtros da categoria (layered navigation) são exibidos

#### 4.3. 14 — Menu vertical permanece funcional na página de categoria

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800. Clicar em uma categoria do menu. Aguardar carregamento
    - expect: Página de categoria carrega com sucesso
  2. Na página de categoria, localizar o menu vertical na sidebar esquerda
    - expect: Menu vertical ainda está presente e visível na página de categoria
    - expect: A categoria atual está destacada com classe `.active` ou `.current`
    - expect: Itens do menu ainda são clicáveis
  3. Fazer hover em um item pai do menu na página de categoria. Aguardar 400ms
    - expect: Submenu abre corretamente (sem comportamento regressivo pós-navegação)
    - expect: Não há erros de JavaScript no console

#### 4.4. 15 — Item da categoria atual está destacado no menu

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar diretamente para uma URL de categoria conhecida da AWA Motos com viewport 1280×800
    - expect: Página de categoria carrega com status 200
  2. Localizar o item do menu vertical correspondente à categoria atual e verificar suas classes CSS
    - expect: Item correspondente tem classe `.active`, `.current`, `.ui-state-active` ou similar
    - expect: Item está visualmente destacado (cor de fundo diferente, texto em negrito, ou borda lateral colorida)

### 5. Menu Vertical — Comportamento Mobile (375px)

**Seed:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

#### 5.1. 16 — Menu vertical fica oculto por padrão em viewport mobile (375px)

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 375×667
    - expect: Homepage carrega no viewport mobile
  2. Verificar visibilidade do contêiner principal do menu: `.awa-vertical-nav`, `.vertical-menu-custom-block`
    - expect: Em mobile, a lista de categorias do menu vertical está oculta por padrão (display:none, visibility:hidden, height:0 ou fora da viewport)
    - expect: O conteúdo ocupa toda a largura da tela sem menu lateral
    - expect: Não há overflow horizontal causado por menu parcialmente visível

#### 5.2. 17 — Botão hambúrguer do menu vertical está visível em mobile

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 375×667
    - expect: Homepage carrega
  2. Localizar o botão hambúrguer/trigger do menu vertical lateral: `.vertical-menu-toggle`, `.awa-vertical-trigger`, botão com ícone de lista/categorias próximo à área de conteúdo
    - expect: Botão toggle do menu vertical está visível e acessível por toque
    - expect: Botão tem tamanho mínimo de 44×44px (touch target WCAG 2.5.5)
    - expect: Botão tem `aria-label` descritivo (ex: 'Abrir categorias', 'Menu de categorias')

#### 5.3. 18 — Clicar no hambúrguer abre drawer off-canvas do menu vertical

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 375×667. Localizar o botão toggle do menu vertical e clicar nele. Aguardar 700ms para animação
    - expect: Drawer/painel lateral do menu vertical desliza da esquerda (ou de cima) para dentro da viewport
    - expect: Overlay escuro semitransparente cobre o conteúdo principal ao fundo
    - expect: Menu vertical com lista de categorias está completamente visível dentro do drawer
  2. Verificar dimensões do drawer aberto via bounding box
    - expect: Drawer não ultrapassa 100% da largura da viewport (375px)
    - expect: Drawer tem altura suficiente para exibir as categorias
    - expect: Overlay tem opacity > 0 e cobre a área de conteúdo

#### 5.4. 19 — Botão fechar (X) dentro do drawer fecha o menu em mobile

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 375×667. Abrir o drawer do menu vertical clicando no toggle
    - expect: Drawer abre com categorias visíveis
  2. Localizar botão de fechar dentro do drawer: `.close`, `.btn-close`, `button[aria-label*='fechar' i]`, `button[aria-label*='close' i]`, `.vertical-menu-close`. Clicar no botão
    - expect: Drawer fecha com animação (desliza para fora da viewport)
    - expect: Overlay desaparece
    - expect: Conteúdo principal volta a ocupar tela inteira sem interferência do menu
    - expect: Foco retorna ao botão de toggle (comportamento de acessibilidade ideal)

#### 5.5. 20 — Clicar no overlay fecha o drawer em mobile

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 375×667. Abrir o drawer do menu vertical
    - expect: Drawer aberto com overlay visível
  2. Localizar o overlay (`.overlay`, `.modal-overlay`, `.awa-overlay`) e clicar nele fora da área do drawer
    - expect: Drawer fecha automaticamente ao clicar fora (no overlay)
    - expect: Comportamento é consistente com padrão UX de modal/drawer mobile

#### 5.6. 21 — Itens de categoria são clicáveis em mobile e navegam corretamente

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 375×667. Abrir o drawer do menu vertical
    - expect: Drawer abre com categorias visíveis
  2. Localizar e clicar em um item de categoria de nível 1 dentro do drawer
    - expect: Drawer fecha após clicar no item (ou navega diretamente)
    - expect: Navegação ocorre para a página de categoria
    - expect: Página de categoria carrega corretamente em viewport mobile
    - expect: Grid de produtos é responsivo e usa layout mobile

#### 5.7. 22 — Submenus expandem por toque (accordion) em mobile — sem hover

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 375×667. Abrir o drawer do menu vertical
    - expect: Drawer aberto com categorias visíveis
  2. Tocar (clicar) em um item de categoria que possui filhos (seta/ícone indicando submenu). Aguardar 500ms
    - expect: Submenu de nível 2 expande inline (accordion) OU navega para a categoria pai
    - expect: Expansão por toque é animada e suave (sem salto brusco de layout)
    - expect: Submenus não dependem de hover (comportamento touch-friendly)
  3. Tocar novamente no mesmo item pai
    - expect: Submenu colapsa (accordion toggle) OU comportamento é documentado como 'navega direto'
    - expect: Não há travamento de tela ou loop de animação

#### 5.8. 23 — Menu mobile não causa overflow horizontal

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 375×667. Abrir e fechar o drawer do menu vertical
    - expect: Drawer abre e fecha sem erros ou resíduos visuais
  2. Verificar overflow horizontal após interação: `document.documentElement.scrollWidth > document.documentElement.clientWidth + 4`
    - expect: scrollWidth não excede clientWidth em mais de 4px
    - expect: Usuário não consegue rolar horizontalmente a página após interação com menu

### 6. Menu Vertical — Acessibilidade e Teclado

**Seed:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

#### 6.1. 24 — Menu vertical tem role semântico de navegação

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800
    - expect: Menu vertical está visível
  2. Inspecionar o contêiner do menu vertical e verificar: `role`, `aria-label`, ou uso de elemento `<nav>`
    - expect: Contêiner tem `role='navigation'` OU é um elemento `<nav>` semântico
    - expect: Elemento `<nav>` ou role tem `aria-label` descritivo (ex: 'Categorias', 'Menu de Categorias')
    - expect: Não há duas instâncias de `<nav aria-label='Categorias'>` na mesma página (evitar duplicatas)

#### 6.2. 25 — Links do menu são focáveis por Tab com focus ring visível

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800. Posicionar foco antes do menu. Pressionar Tab para navegar até o primeiro link do menu vertical
    - expect: Primeiro link do menu recebe foco via Tab
    - expect: Focus ring visível ao redor do item focado (outline não é `none` sem alternativa)
    - expect: Focus ring tem contraste suficiente para ser distinguível (WCAG AA)
  2. Pressionar Tab mais vezes para navegar pelos itens do menu
    - expect: Ordem de foco segue a ordem visual dos itens (de cima para baixo)
    - expect: Foco não pula itens do menu
    - expect: Foco não fica preso dentro do menu (pode sair com Tab/Shift+Tab)

#### 6.3. 26 — Submenu acessível por teclado (Enter expande, Escape fecha)

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800. Usar Tab para focar um item pai no menu vertical (que possui submenu)
    - expect: Item pai está focado com focus ring visível
  2. Pressionar Enter ou Space no item pai focado. Aguardar 400ms
    - expect: Submenu de nível 2 expande e fica visível, OU navegação ocorre para URL da categoria pai (ambos são comportamentos válidos)
    - expect: Se expandiu: item pai tem `aria-expanded='true'`
  3. Se submenu abriu: pressionar Escape
    - expect: Submenu fecha
    - expect: Foco retorna ao item pai
    - expect: Item pai tem `aria-expanded='false'` após fechamento

#### 6.4. 27 — Atributos aria-expanded corretos nos itens pai

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800. Localizar itens pai no menu vertical
    - expect: Itens pai encontrados
  2. Verificar o atributo `aria-expanded` nos links/botões dos itens pai antes de expandir
    - expect: Antes de expandir: `aria-expanded='false'` ou atributo ausente (se hover-only sem toggle explícito)
  3. Fazer hover ou clicar para expandir o submenu. Verificar `aria-expanded` novamente
    - expect: Após expandir: `aria-expanded='true'`
    - expect: Submenus associados têm `aria-hidden='false'` quando abertos

### 7. Menu Vertical — Edge Cases e Resiliência

**Seed:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

#### 7.1. 28 — Menu funciona na página de categoria (não apenas na home)

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar diretamente para uma URL de categoria de primeiro nível com viewport 1280×800
    - expect: Página de categoria carrega com status 200
  2. Localizar o menu vertical na sidebar esquerda da página de categoria
    - expect: Menu vertical está presente e visível na página de categoria
    - expect: Menu exibe as categorias corretamente (não está vazio nem com erro de render)
    - expect: Categoria atual está destacada no menu
  3. Fazer hover em um item pai no menu da página de categoria
    - expect: Submenu abre corretamente (sem regressão na página de categoria)
    - expect: Comportamento é idêntico ao da homepage

#### 7.2. 29 — Nenhum erro de JavaScript relacionado ao menu no console

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800. Coletar mensagens de console antes de interagir com o menu
    - expect: Nenhum erro JavaScript crítico antes da interação
  2. Fazer hover em 3 itens do menu (incluindo 1 item pai) e clicar em 1 link. Coletar console novamente
    - expect: Nenhum `TypeError`, `ReferenceError` ou `Uncaught` relacionado ao VerticalMenu
    - expect: Nenhum erro 404 de recursos JS/CSS do módulo VerticalMenu
    - expect: Nenhum erro de CORS no carregamento de categorias

#### 7.3. 30 — Menu se adapta corretamente ao resize de viewport (desktop ↔ mobile)

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800. Verificar que menu vertical está visível e funcional
    - expect: Menu visível em desktop
  2. Redimensionar viewport para 375×667. Aguardar 500ms
    - expect: Menu vertical se adapta: lista de categorias oculta, botão hambúrguer aparece
    - expect: Não há elementos residuais de desktop visíveis em mobile
  3. Redimensionar de volta para 1280×800. Aguardar 500ms
    - expect: Menu vertical volta ao layout de desktop
    - expect: Lista de categorias está visível novamente sem necessidade de refresh
    - expect: Submenus funcionam corretamente após resize

#### 7.4. 31 — Categoria sem produtos não causa erro 500 no menu

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800. Identificar no menu vertical categorias que possam ter poucos ou nenhum produto. Clicar em uma dessas categorias
    - expect: Página de categoria carrega sem erro 500
    - expect: Se não há produtos: mensagem 'Nenhum produto foi encontrado' é exibida (não página em branco)
    - expect: Menu vertical na sidebar ainda aparece mesmo na categoria vazia
    - expect: Não há redirecionamento inesperado para 404

#### 7.5. 32 — Scroll da página não causa comportamento incorreto no menu sticky

**File:** `tests/e2e/specs/functional/func-menu-vertical.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com com viewport 1280×800. Verificar se o menu vertical tem posicionamento `sticky` ou `fixed`
    - expect: Menu vertical renderizado e visível
  2. Rolar a página para baixo até 800px de scroll. Aguardar 300ms
    - expect: Se menu é sticky: permanece visível na área de sidebar durante o scroll
    - expect: Se menu é estático: sai da viewport normalmente ao rolar (sem travar ou piscar)
    - expect: Em nenhum caso o menu sobrepõe o header ou o conteúdo de forma incorreta
    - expect: Menu não tremula ou causa reflow visual ao rolar
