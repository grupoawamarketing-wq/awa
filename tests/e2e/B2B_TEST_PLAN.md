# Plano de Testes B2B — AWA Motos

## Application Overview

Plano de testes abrangente para os fluxos B2B do e-commerce AWA Motos (awamotos.com), built on Magento 2.4.8-p3 CE com o módulo customizado GrupoAwamotos_B2B. Cobre login B2B, modal "Entrar para Comprar", registro B2B, dashboard do revendedor (carregamento async de pedidos/cotações/crédito), cotações (quote request), listas de compras (shopping list), bloqueio de conta pendente e restrições de checkout. Todos os testes usam Firefox via Playwright (Chrome trava no ambiente do servidor), não usam networkidle nem isMobile. Configuração base: pw-functional.config.ts, timeout 90s, actionTimeout 15s. Projetos: func-desktop (1280×800) e func-mobile (375×667 + UA Android).

## Test Scenarios

### 1. func-b2b-login.spec.ts — Login B2B completo

**Seed:** `tests/e2e/specs/functional/func-login.spec.ts`

#### 1.1. [P0] Página de login B2B carrega sem erro HTTP

**File:** `tests/e2e/specs/functional/func-b2b-login.spec.ts`

**Steps:**
  1. Fazer GET em https://awamotos.com/b2b/account/login/ via page.request.get()
    - expect: Status HTTP < 400 (não 404, não 500)
  2. Navegar para https://awamotos.com/b2b/account/login/ com navigateTo()
    - expect: Página renderiza; h1 ou .page-title visível em até 10s

#### 1.2. [P0] Formulário de login B2B exibe campos obrigatórios

**File:** `tests/e2e/specs/functional/func-b2b-login.spec.ts`

**Steps:**
  1. Navegar para /b2b/account/login/
    - expect: Página carrega sem erro 500
  2. Verificar visibilidade do campo email: page.locator('#b2b-email, input[name="email"], #email').first()
    - expect: Campo email visível
  3. Verificar visibilidade do campo senha: page.locator('#b2b-pass, input[name="password"], #pass').first()
    - expect: Campo senha visível
  4. Verificar botão submit: page.locator('.b2b-btn-entrar, button[type="submit"]').first()
    - expect: Botão de submit visível e não desabilitado

#### 1.3. [P0] Login B2B com credenciais inválidas exibe mensagem de erro

**File:** `tests/e2e/specs/functional/func-b2b-login.spec.ts`

**Steps:**
  1. Navegar para /b2b/account/login/; se página não carregar, test.skip()
  2. Preencher #b2b-email (ou input[name="email"]) com 'usuario.invalido.awa@example.com'
  3. Preencher #b2b-pass (ou input[name="password"]) com 'senhaErrada999!'
  4. Clicar em .b2b-btn-entrar (ou button[type="submit"]) e aguardar 2s
    - expect: Página não redireciona para dashboard; URL permanece em /b2b/ ou /login/
  5. Verificar presença de mensagem de erro: page.locator('.message-error, .b2b-error, [data-ui-id="message-error"], .mage-error').first()
    - expect: Mensagem de erro visível com texto indicando credenciais inválidas

#### 1.4. [P1] Fluxo 'Esqueci minha senha' — página carrega e aceita email

**File:** `tests/e2e/specs/functional/func-b2b-login.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com/b2b/account/forgotpassword/
    - expect: Status HTTP < 400; página renderiza
  2. Localizar campo: page.locator('#b2b-forgot-email, input[name="email"], #email').first()
    - expect: Campo de email para recuperação de senha visível
  3. Preencher o campo com 'teste.recuperacao@example.com'
  4. Clicar no botão submit: page.locator('button[type="submit"], .action.submit').first()
    - expect: Mensagem de sucesso ou confirmação visível (ex: 'Se o e-mail existir...'); ou redirecionamento para login

#### 1.5. [P0] Guest clica em 'Entrar para Comprar' — modal aparece

**File:** `tests/e2e/specs/functional/func-b2b-login.spec.ts`

**Steps:**
  1. Navegar para PDP de produto conhecido: https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html
    - expect: Página de produto carrega
  2. Localizar botão guest: page.locator('.b2b-login-to-buy-btn').first(); se não visível, test.skip() (produto pode ter add-to-cart direto)
  3. Clicar no botão .b2b-login-to-buy-btn
  4. Aguardar modal: page.locator('#b2b-login-modal').waitFor({ state: 'visible', timeout: 8000 })
    - expect: Modal #b2b-login-modal visível com role="dialog" e aria-modal="true"
  5. Verificar presença de link de login: page.locator('#b2b-login-modal a[href*="b2b/account/login"]')
    - expect: Link apontando para /b2b/account/login/ está no modal
  6. Verificar presença de link de cadastro B2B: page.locator('#b2b-login-modal a[href*="b2b/register"]')
    - expect: Link apontando para /b2b/register/ está no modal

#### 1.6. [P1] Modal 'Entrar para Comprar' pode ser fechado

**File:** `tests/e2e/specs/functional/func-b2b-login.spec.ts`

**Steps:**
  1. Navegar para PDP; clicar em .b2b-login-to-buy-btn; aguardar #b2b-login-modal visível
    - expect: Modal aberto
  2. Clicar em .b2b-login-modal-close (botão [data-b2b-login-close]) dentro do modal
  3. Verificar que #b2b-login-modal não está mais visível: expect(modal).not.toBeVisible()
    - expect: Modal fechado; overlay removida ou oculta

### 2. func-b2b-registro.spec.ts — Registro e Formulários B2B

**Seed:** `tests/e2e/specs/functional/func-formularios.spec.ts`

#### 2.1. [P0] Página de registro B2B carrega sem erro HTTP

**File:** `tests/e2e/specs/functional/func-b2b-registro.spec.ts`

**Steps:**
  1. Fazer GET em https://awamotos.com/b2b/register/ via page.request.get()
    - expect: Status HTTP 200; não 404 nem 500
  2. Navegar para /b2b/register/ e verificar presença de h1 ou título da página
    - expect: Título 'Cadastro B2B', 'Seja um Revendedor' ou similar visível

#### 2.2. [P1] Formulário de registro B2B exibe campos CNPJ/Razão Social

**File:** `tests/e2e/specs/functional/func-b2b-registro.spec.ts`

**Steps:**
  1. Navegar para /b2b/register/; se não carregar, test.skip()
  2. Verificar campo CNPJ: page.locator('input[name*="cnpj"], #cnpj, input[placeholder*="CNPJ"]').first()
    - expect: Campo CNPJ visível no formulário
  3. Verificar campo email: page.locator('input[name="email"], #email, input[type="email"]').first()
    - expect: Campo email visível
  4. Verificar campo senha: page.locator('input[type="password"], #password, input[name="password"]').first()
    - expect: Campo senha visível
  5. Verificar botão de envio: page.locator('button[type="submit"], .action.submit').first()
    - expect: Botão submit visível e habilitado

#### 2.3. [P1] Submissão vazia do formulário B2B exibe validação

**File:** `tests/e2e/specs/functional/func-b2b-registro.spec.ts`

**Steps:**
  1. Navegar para /b2b/register/; se não carregar, test.skip()
  2. Clicar no botão submit sem preencher nenhum campo
  3. Aguardar 1s; contar erros: page.locator('.mage-error, .field-error, [aria-invalid="true"], :invalid')
    - expect: Pelo menos 1 erro de validação visível; formulário não foi enviado

#### 2.4. [P1] Link 'Já tenho cadastro B2B' no registro aponta para login

**File:** `tests/e2e/specs/functional/func-b2b-registro.spec.ts`

**Steps:**
  1. Navegar para /b2b/register/; se não carregar, test.skip()
  2. Localizar link para login: page.locator('a[href*="b2b/account/login"]').first()
    - expect: Link para /b2b/account/login/ existe na página de registro

#### 2.5. [P2] Página de registro B2B exibe banner/CTA de revendedor

**File:** `tests/e2e/specs/functional/func-b2b-registro.spec.ts`

**Steps:**
  1. Navegar para /b2b/register/; se não carregar, test.skip()
  2. Verificar presença de elemento de destaque B2B: page.locator('.b2b-register-hero, .b2b-landing, .b2b-benefits, [class*="b2b-"]').first()
    - expect: Elemento de identidade visual B2B presente (banner, lista de benefícios, ou hero section)

### 3. func-b2b-dashboard.spec.ts — Dashboard do Revendedor

**Seed:** `tests/e2e/specs/functional/func-login.spec.ts`

#### 3.1. [P0] Dashboard B2B redireciona guest para login

**File:** `tests/e2e/specs/functional/func-b2b-dashboard.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com/b2b/account/dashboard/ como guest (sem sessão autenticada)
  2. Verificar URL final após navegação: page.url()
    - expect: URL contém /customer/account/login ou /b2b/account/login — redirecionamento ocorreu

#### 3.2. [P0] Dashboard B2B carrega estrutura principal (usuário logado)

**File:** `tests/e2e/specs/functional/func-b2b-dashboard.spec.ts`

**Steps:**
  1. Pré-condição: autenticar via storageState ou fazer login B2B com credencial de teste antes do teste. Se não houver credencial de teste disponível, test.skip()
  2. Navegar para /b2b/account/dashboard/
    - expect: Página não redireciona para login; URL permanece em /b2b/
  3. Verificar título: page.locator('.awa-b2b-dashboard__title, h1').first()
    - expect: Título 'Painel do Revendedor' ou similar visível
  4. Verificar atalhos rápidos: page.locator('.b2b-dashboard-shortcuts').first()
    - expect: .b2b-dashboard-shortcuts visível com links de atalho

#### 3.3. [P0] Dashboard B2B — seções async renderizam (sem skeleton infinito)

**File:** `tests/e2e/specs/functional/func-b2b-dashboard.spec.ts`

**Steps:**
  1. Pré-condição: usuário logado; navegar para /b2b/account/dashboard/. Se guest, test.skip()
  2. Verificar que seção de pedidos existe: page.locator('[data-dashboard-section="orders"]').first()
    - expect: Elemento data-dashboard-section='orders' presente no DOM
  3. Aguardar até 15s que a classe .b2b-loading seja removida da seção orders: page.locator('[data-dashboard-section="orders"]:not(.b2b-loading)')
    - expect: Skeleton de loading removido; conteúdo async renderizado (tabela de pedidos ou .b2b-empty-state)
  4. Verificar seções quotes e credit da mesma forma
    - expect: Todas as 3 seções (orders, quotes, credit) exibem conteúdo real ou estado vazio — nunca skeleton permanente

#### 3.4. [P1] Dashboard — atalho 'Meus Pedidos' navega para histórico

**File:** `tests/e2e/specs/functional/func-b2b-dashboard.spec.ts`

**Steps:**
  1. Pré-condição: usuário logado; navegar para /b2b/account/dashboard/. Se guest, test.skip()
  2. Clicar no card de atalho de pedidos: page.locator('.b2b-shortcut-card[href*="sales/order/history"]').first()
  3. Verificar URL após navegação
    - expect: URL contém /sales/order/history — histórico de pedidos carregado

#### 3.5. [P1] Dashboard — link 'Dados da Conta' navega para edição de conta

**File:** `tests/e2e/specs/functional/func-b2b-dashboard.spec.ts`

**Steps:**
  1. Pré-condição: usuário logado; navegar para /b2b/account/dashboard/. Se guest, test.skip()
  2. Clicar em page.locator('.b2b-shortcut-card[href*="customer/account/edit"]').first()
  3. Verificar URL: page.url()
    - expect: URL contém /customer/account/edit — página de edição carregada sem erro

#### 3.6. [P2] Dashboard — breadcrumb exibe 'Portal B2B'

**File:** `tests/e2e/specs/functional/func-b2b-dashboard.spec.ts`

**Steps:**
  1. Pré-condição: usuário logado; navegar para /b2b/account/dashboard/. Se guest, test.skip()
  2. Verificar breadcrumb: page.locator('.breadcrumbs, nav[aria-label*="breadcrumb"]').getByText('Portal B2B')
    - expect: Texto 'Portal B2B' visível no breadcrumb da página

### 4. func-b2b-pending.spec.ts — Conta Pendente de Aprovação

**Seed:** `tests/e2e/specs/functional/func-botao-comprar.spec.ts`

#### 4.1. [P0] PDP exibe banner pendente para conta B2B aguardando aprovação

**File:** `tests/e2e/specs/functional/func-b2b-pending.spec.ts`

**Steps:**
  1. Navegar para PDP: https://awamotos.com/retrovisor-cb-300-modelo-11-padrao-yamaha-dir-esq-cromado.html
    - expect: Página carrega
  2. Verificar presença do elemento banner no DOM: page.locator('#b2b-pending-banner, .b2b-pending-banner').first()
    - expect: Elemento #b2b-pending-banner existe no DOM (pode estar hidden para não-pending)
  3. Se usuário pending estiver autenticado: verificar que banner está visível e não oculto (hidden=false)
    - expect: Para conta com status pending: banner visível com mensagem de aguardando aprovação

#### 4.2. [P0] PDP — botão 'Adicionar ao Carrinho' está ausente/desabilitado para conta pending

**File:** `tests/e2e/specs/functional/func-b2b-pending.spec.ts`

**Steps:**
  1. Pré-condição: conta B2B com status 'pending'. Se não disponível, verificar apenas estrutura DOM. Navegar para PDP.
  2. Verificar que o botão normal add-to-cart NÃO está visível: page.locator('#product-addtocart-button').isVisible()
  3. Verificar que existe botão desabilitado de 'Aguardando Aprovação': page.locator('.b2b-pending-btn, button[disabled][class*="b2b"], [data-b2b-pending]').first()
    - expect: Botão indicando estado pendente presente e disabled; ou add-to-cart ausente com banner visível

#### 4.3. [P1] Banner pendente contém link para 'Minha Conta'

**File:** `tests/e2e/specs/functional/func-b2b-pending.spec.ts`

**Steps:**
  1. Navegar para PDP; localizar #b2b-pending-banner no DOM
  2. Inspecionar conteúdo do banner: page.locator('#b2b-pending-banner a.b2b-pending-account-link, #b2b-pending-banner a[href*="customer/account"]').first()
    - expect: Link 'Minha Conta' presente dentro do banner com href apontando para /customer/account ou /b2b/account/dashboard

#### 4.4. [P1] PLP — botões de compra desabilitados para conta pending

**File:** `tests/e2e/specs/functional/func-b2b-pending.spec.ts`

**Steps:**
  1. Pré-condição: conta B2B pending autenticada; se não disponível, test.skip(). Navegar para https://awamotos.com/bagageiros-para-motos.html ou primeira categoria com produtos.
  2. Verificar se botões de add-to-cart na listagem estão desabilitados: page.locator('button.tocart, button[data-role="tocart"]').first().isDisabled()
    - expect: Botões de compra na PLP estão disabled ou substituídos por indicador de pending

#### 4.5. [P0] Guest na PDP — não vê banner pendente (banner está oculto)

**File:** `tests/e2e/specs/functional/func-b2b-pending.spec.ts`

**Steps:**
  1. Navegar para PDP sem sessão autenticada (guest)
  2. Verificar estado do banner: const banner = page.locator('#b2b-pending-banner').first(); verificar se está hidden ou não-visível
    - expect: Banner #b2b-pending-banner não visível para guest (hidden=true ou display:none ou não existe)
  3. Verificar que .b2b-login-to-buy-btn está visível para guest (botão de login para comprar)
    - expect: Botão guest 'Entrar para Comprar' visível; banner pending oculto — UX diferenciada por estado

### 5. func-b2b-shoppinglist.spec.ts — Lista de Compras e Cotações

**Seed:** `tests/e2e/specs/functional/func-carrinho.spec.ts`

#### 5.1. [P0] Página de lista de compras redireciona guest para login

**File:** `tests/e2e/specs/functional/func-b2b-shoppinglist.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com/b2b/shoppinglist/ como guest
  2. Verificar URL final: page.url()
    - expect: URL contém /login — redirecionamento de proteção de rota funcionou

#### 5.2. [P0] Página de lista de compras carrega para usuário logado

**File:** `tests/e2e/specs/functional/func-b2b-shoppinglist.spec.ts`

**Steps:**
  1. Pré-condição: usuário B2B logado; se guest, test.skip(). Navegar para /b2b/shoppinglist/
  2. Verificar que página não redirecionou para login
    - expect: URL permanece em /b2b/shoppinglist/
  3. Verificar presença de h1 ou conteúdo principal: page.locator('.page-title, h1').first()
    - expect: Título de 'Listas de Compras' ou similar visível; ou lista vazia com mensagem

#### 5.3. [P1] Página de cotações redireciona guest para login

**File:** `tests/e2e/specs/functional/func-b2b-shoppinglist.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com/b2b/quote/ como guest
  2. Verificar URL final: page.url()
    - expect: URL contém /login — rota /b2b/quote/ protegida para guests

#### 5.4. [P1] Página de cotações carrega para usuário logado

**File:** `tests/e2e/specs/functional/func-b2b-shoppinglist.spec.ts`

**Steps:**
  1. Pré-condição: usuário B2B logado; se guest, test.skip(). Navegar para /b2b/quote/
  2. Verificar que URL permanece em /b2b/quote/
    - expect: Sem redirecionamento para login
  3. Verificar presença de conteúdo de cotações: page.locator('.page-title, h1, .b2b-quotes-list, .b2b-empty-state').first()
    - expect: Lista de cotações ou mensagem de 'nenhuma cotação' visível

#### 5.5. [P1] Botão de cotação na PDP visível para usuário logado

**File:** `tests/e2e/specs/functional/func-b2b-shoppinglist.spec.ts`

**Steps:**
  1. Pré-condição: usuário B2B logado; se guest, test.skip(). Navegar para PDP: retrovisor-cb-300...
  2. Verificar botão cotação: page.locator('[data-b2b-quote-trigger], .b2b-quote-btn').first()
    - expect: Botão de cotação visível para usuário autenticado

#### 5.6. [P0] Botão de cotação na PDP não aparece para guest

**File:** `tests/e2e/specs/functional/func-b2b-shoppinglist.spec.ts`

**Steps:**
  1. Navegar para PDP como guest (sem sessão)
  2. Verificar que botão de cotação não está visível: page.locator('[data-b2b-quote-trigger], .b2b-quote-btn').first().isVisible()
    - expect: Botão de cotação ausente ou hidden para guest — funcionalidade exclusiva B2B autenticado

#### 5.7. [P1] Página de reorder histórico redireciona guest para login

**File:** `tests/e2e/specs/functional/func-b2b-shoppinglist.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com/b2b/reorder/history/ como guest
  2. Verificar URL final: page.url()
    - expect: URL contém /login — rota de reorder protegida; ou status 302/redirect detectado

#### 5.8. [P2] Página de crédito B2B redireciona guest para login

**File:** `tests/e2e/specs/functional/func-b2b-shoppinglist.spec.ts`

**Steps:**
  1. Navegar para https://awamotos.com/b2b/credit/ como guest
  2. Verificar URL após navegação
    - expect: URL contém /login ou /b2b/account — rota de crédito protegida; não exibe dados de crédito para guest
