# AWA Motos — Master Design System Prompt
> Documento de referência para prompts de melhoria visual progressiva.
> Cada fase é independente. Execute uma por vez. Sempre deploy antes de continuar.

---

## REGRAS GLOBAIS (incluir em TODOS os prompts)

```
CONTEXTO TÉCNICO:
- Magento 2.4.8-p3 · PHP 8.4 · tema: AWA_Custom/ayo_home5_child
- CSS: bundles estáticos em app/design/frontend/AWA_Custom/ayo_home5_child/web/css/
- Tokens: variáveis CSS em awa-core-variables.unmin.css
- Bundles sync (no <head>): awa-bundle-core.css, awa-bundle-vendor-libs.css
- Bundles async (preload): awa-bundle-custom.css, awa-bundle-site.css, awa-bundle-phases.css
- PHP: versão 8.4-fpm com opcache.validate_timestamps=0 (restart obrigatório após PHP changes)

PRINCÍPIOS DE SEGURANÇA:
1. Usar SEMPRE tokens existentes: var(--awa-red), var(--awa-red-dark), var(--awa-white),
   var(--awa-gray-500), var(--awa-space-*), var(--awa-radius-*), var(--awa-shadow-*)
2. Escopar TODOS os seletores com: body .page-wrapper [seletor]
3. Usar !important APENAS quando necessário para vencer custom_default.css
4. Adicionar ao arquivo correto conforme contexto:
   - Crítico/header/nav → awa-bundle-core.css
   - Custom overrides → awa-bundle-custom.css
   - Bundles externos/vendor → awa-bundle-vendor-libs.css (não editar)
5. DEPLOY APÓS CADA MUDANÇA CSS (obrigatório — quebra cache do navegador):
   sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
   sudo -u www-data php bin/magento cache:flush
   (só template .phtml: cache:clean block_html full_page é suficiente)
6. APÓS MUDANÇAS PHP/DI: setup:di:compile + systemctl restart php8.4-fpm
7. NÃO alterar: themes5.css, vendor/*, styles-l.css, styles-m.css, custom_default.css
8. NÃO quebrar: header profissional (awa-utility-bar / awa-main-header / awa-nav-bar),
   sticky header JS, B2B gate (addtocart.phtml / b2b_secondary_ctas.phtml)
```

---

## FASE 1 — SISTEMA DE BOTÕES GLOBAL

```prompt
Você é um especialista em UI/UX Magento 2. Implemente um sistema de botões consistente
para o site AWA Motos seguindo o design system existente.

ESCOPO: Apenas botões — não alterar layout, cores de fundo de seções, ou tipografia.

OBJETIVO: Padronizar todos os botões do site em 3 variantes:

VARIANTE PRIMARY (Ação principal):
- Background: var(--awa-red, #b73337)
- Color: #fff
- Border: none
- Border-radius: var(--awa-radius-sm, 8px)
- Padding: 0 var(--awa-space-6) → altura mínima: 44px (WCAG 2.5.8)
- Font-weight: 600
- Font-size: var(--awa-text-sm, 14px)
- Text-transform: uppercase
- Letter-spacing: 0.04em
- Transition: background 200ms, box-shadow 200ms, transform 150ms
- Hover: background var(--awa-red-dark, #8e2629), translateY(-1px), shadow
- Focus-visible: outline 2px var(--awa-red-dark), outline-offset 2px

VARIANTE SECONDARY (Ação secundária):
- Background: transparent
- Color: var(--awa-red)
- Border: 1.5px solid var(--awa-red)
- Border-radius: var(--awa-radius-sm)
- Padding: igual ao primary
- Hover: background var(--awa-red), color #fff

VARIANTE GHOST (Terciária):
- Background: transparent
- Color: var(--awa-gray-500)
- Border: 1px solid var(--awa-color-border, #e5e5e5)
- Hover: border-color var(--awa-red), color var(--awa-red)

SELETORES A PADRONIZAR (manter especificidade adequada):
- body .page-wrapper .action.primary (add to cart, checkout, etc.)
- body .page-wrapper .action.tocart
- body .page-wrapper .action.login
- body .page-wrapper button[type="submit"] dentro de forms Magento
- body .page-wrapper .btn-primary (Rokanthemes)
- body .page-wrapper .button.btn-cart
- Manter exceção: botões dentro de .nav-sections (cor branca, sem borda)

ARQUIVO: awa-bundle-custom.unmin.css (nova seção "=== BUTTON SYSTEM ===")

DEPLOY OBRIGATÓRIO:
```bash
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
sudo -u www-data php bin/magento cache:flush
```
```

---

## FASE 2 — SISTEMA DE FORMULÁRIOS GLOBAL

```prompt
Você é um especialista em UI/UX Magento 2. Implemente um sistema de formulários
consistente para AWA Motos. Não alterar outros elementos além de inputs/forms.

ELEMENTOS A PADRONIZAR:

INPUT / TEXTAREA / SELECT:
- Height: 44px (inputs), auto (textarea), 44px (select)
- Border: 1.5px solid var(--awa-color-border, #e5e5e5)
- Border-radius: var(--awa-radius-sm, 8px)
- Padding: 0 var(--awa-space-4) (12px lateral)
- Font-size: var(--awa-text-sm, 14px)
- Color: var(--awa-gray-700, #333)
- Background: #fff
- Transition: border-color 200ms, box-shadow 200ms
- Focus: border-color var(--awa-red), box-shadow 0 0 0 3px rgba(183,51,55,.12)
- Placeholder: color var(--awa-gray-400, #aaa)
- Error state: border-color #dc2626, background rgba(220,38,38,.04)
- Disabled: background var(--awa-bg-soft, #f7f7f7), opacity .65, cursor not-allowed

LABELS:
- Font-size: var(--awa-text-xs, 12px)
- Font-weight: 600
- Color: var(--awa-gray-600, #475569)
- Margin-bottom: var(--awa-space-1, 4px)
- Text-transform: uppercase
- Letter-spacing: 0.04em

GRUPOS (fieldset / .field):
- Gap entre campos: var(--awa-space-5, 20px)
- .required label::after: content " *", color var(--awa-red)

MENSAGENS DE ERRO:
- Color: #dc2626
- Font-size: var(--awa-text-xs)
- Margin-top: var(--awa-space-1)
- Display: flex, gap: 4px (ícone + texto)

SELETORES MAGENTO:
- body .page-wrapper .field input[type=text|email|tel|password|number]
- body .page-wrapper .field select
- body .page-wrapper .field textarea
- body .page-wrapper .field .label
- body .page-wrapper .mage-error (mensagens de erro)
- body .page-wrapper .field-error

ARQUIVO: awa-bundle-custom.unmin.css (nova seção "=== FORM SYSTEM ===")

DEPLOY OBRIGATÓRIO:
```bash
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
sudo -u www-data php bin/magento cache:flush
```
```

---

## FASE 3 — TIPOGRAFIA E HIERARQUIA GLOBAL

```prompt
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

DEPLOY OBRIGATÓRIO:
```bash
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
sudo -u www-data php bin/magento cache:flush
```
```

---

## FASE 4 — CONTAINER, GRID E ESPAÇAMENTOS

```prompt
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

DEPLOY OBRIGATÓRIO:
```bash
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
sudo -u www-data php bin/magento cache:flush
```
```

---

## FASE 5 — CARDS DE PRODUTO (PLP)

```prompt
Você é um especialista em UI/UX Magento 2. Melhore os cards de produto na listagem
(PLP/Category) do AWA Motos. Não alterar estrutura do header ou footer.

CARD DE PRODUTO — DESIGN PROFISSIONAL B2B:

Estrutura visual:
- Background: #fff
- Border: 1px solid var(--awa-color-border, #e5e5e5)
- Border-radius: var(--awa-radius-sm, 8px)
- Box-shadow: 0 2px 8px rgba(0,0,0,.06)
- Overflow: hidden
- Transition: box-shadow 200ms, transform 200ms
- Hover: box-shadow 0 8px 24px rgba(0,0,0,.12), translateY(-2px)

Imagem do produto:
- Aspect-ratio: 1/1 (quadrada)
- Object-fit: contain
- Background: var(--awa-bg-soft, #f7f7f7)
- Padding: var(--awa-space-4)

Informações:
- Padding: var(--awa-space-4)
- Nome do produto: 2 linhas max (line-clamp 2), font-size 14px, weight 500
- SKU/Referência: font-size 11px, color var(--awa-gray-400), margin-bottom 4px
- Preço: font-size 18px, weight 700, color var(--awa-red)
- Botão "Adicionar": width 100%, margin-top var(--awa-space-3)

Badge "Novo" / "Promoção":
- Position: absolute, top 8px, left 8px
- Background var(--awa-red), color #fff
- Font-size 10px, font-weight 700, text-transform uppercase
- Padding: 2px 8px, border-radius var(--awa-radius-full)

SELETORES MAGENTO:
- body .page-wrapper .products.list .item.product
- body .page-wrapper .product-item-info
- body .page-wrapper .product-item-photo
- body .page-wrapper .product-item-name
- body .page-wrapper .price-box .price

ARQUIVO: awa-bundle-category.unmin.css (seção "=== PRODUCT CARD ===")

DEPLOY OBRIGATÓRIO:
```bash
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
sudo -u www-data php bin/magento cache:flush
```
```

---

## FASE 6 — PÁGINA DE PRODUTO (PDP)

```prompt
Você é um especialista em UI/UX Magento 2. Melhore a página de produto (PDP) do
AWA Motos com design B2B profissional. Não alterar header/footer/nav.

LAYOUT PDP (Desktop ≥992px):
- 2 colunas: imagem 50% / info 50%
- Gap: var(--awa-space-7, 32px)
- Imagem principal: border 1px solid var(--awa-color-border), border-radius 8px

GALERIA DE IMAGENS:
- Thumbs: 60x60px, border 1.5px solid transparent
- Thumb ativa: border-color var(--awa-red)
- Hover thumb: border-color var(--awa-red-mid)

BLOCO DE INFORMAÇÕES:
- Nome: h1, font-size 24px, font-weight 700, color var(--awa-dark), margin-bottom 8px
- SKU: font-size 12px, color var(--awa-gray-400), margin-bottom 16px
- Preço: font-size 32px, font-weight 700, color var(--awa-red)
- Preço antigo: font-size 18px, line-through, color var(--awa-gray-400), margin-left 8px
- Disponibilidade: badge verde (#16a34a) "Em Estoque" / vermelho "Indisponível"

BOTÕES PDP:
- "Adicionar ao Carrinho": PRIMARY full-width 52px height, font-size 16px
- "Solicitar Orçamento": SECONDARY full-width, margin-top 8px
- Ícone de WhatsApp no botão de orçamento

ABAS (tabs):
- Descrição / Especificações / Compatibilidade / Avaliações
- Tab ativa: border-bottom 2px solid var(--awa-red), color var(--awa-red)
- Tab inativa: color var(--awa-gray-500), hover color var(--awa-red)

ARQUIVO: awa-pdp-b2b-pro.unmin.css (já existe — adicionar seções)

DEPLOY OBRIGATÓRIO:
```bash
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
sudo -u www-data php bin/magento cache:flush
```
```

---

## FASE 7 — RODAPÉ PROFISSIONAL

```prompt
Você é um especialista em UI/UX Magento 2. Implemente um rodapé profissional B2B
para AWA Motos. Não alterar nada acima da tag <footer>.

ESTRUTURA DO RODAPÉ (4 colunas desktop):
1. Coluna Marca: Logo + descrição empresa + redes sociais
2. Coluna Links: Links institucionais (Sobre, Contato, Blog)
3. Coluna Serviços: Minha Conta, Pedidos, Rastreamento, Atacado
4. Coluna Contato: Telefone, WhatsApp, E-mail, Horário

DESIGN:
- Background: var(--awa-dark, #333) (cinza escuro corporativo)
- Color: rgba(255,255,255,.85)
- Padding: 64px 0 32px (desktop), 40px 0 24px (mobile)
- Links: color rgba(255,255,255,.7), hover color #fff, hover text-decoration none
- Títulos de coluna: color #fff, font-size 12px, font-weight 700, text-transform uppercase, letter-spacing .06em, margin-bottom 16px
- Divisor: border-top 1px solid rgba(255,255,255,.12) antes do copyright

BARRA DE COPYRIGHT:
- Background: rgba(0,0,0,.2) (mais escuro que o footer)
- Padding: 16px 0
- Font-size: 12px, color rgba(255,255,255,.5)
- Layout: flex, space-between (texto + badges de pagamento)

BADGES DE PAGAMENTO:
- Visa, Mastercard, Pix, Boleto — SVG inline ou img
- Height: 24px, opacity .7

SELETORES:
- body .page-wrapper .page-footer (container principal)
- body .page-wrapper .footer.content
- body .page-wrapper .footer-links (se existir)

VERIFICAR: header.phtml e layout XML do Rokanthemes para entender a estrutura do footer atual
ARQUIVO: awa-bundle-custom.unmin.css (seção "=== FOOTER PROFESSIONAL ===")

DEPLOY OBRIGATÓRIO:
```bash
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
sudo -u www-data php bin/magento cache:flush
```
```

---

## FASE 8 — HEADER AVANÇADO (complemento)

```prompt
Você é um especialista em UI/UX Magento 2. O header básico já está implementado.
Adicione refinamentos avançados SEM alterar o que já funciona.

VERIFICAR ANTES: Ler awa-header-professional.less e header.phtml antes de qualquer edição.

REFINAMENTOS A ADICIONAR:

1. MEGA MENU DROPDOWN:
- Background: #fff, border-radius 0 0 8px 8px, box-shadow 0 16px 32px rgba(0,0,0,.12)
- Colunas: grid de 3-4 colunas com títulos em var(--awa-red)
- Imagem destacada na última coluna (categoria em promoção)
- Animação: opacity 0→1 + translateY(-4px→0), duration 180ms

2. BARRA DE PESQUISA AVANÇADA:
- Autocomplete: card com shadow, borda arredondada 8px
- Sugestões de produtos: imagem 48x48 + nome + preço
- Categorias sugeridas: badge com cor de categoria
- "Ver todos os resultados": link em var(--awa-red) no final

3. MINICART FLYOUT:
- Width: 380px, fixed position, slide da direita
- Header: "Seu Carrinho (N itens)"
- Item: imagem 64px + nome + qty + preço
- Footer: subtotal + botão "Finalizar Compra" PRIMARY full-width

4. STICKY HEADER CONDENSADO:
- Já implementado via JS — verificar comportamento ao scroll
- Garantir que transition: height 220ms funciona suavemente

ARQUIVO: _awa-header-professional.less (recompilar para bundle)
ATENÇÃO: Após editar o .less, recompilar manualmente para awa-bundle-core.css.

DEPLOY OBRIGATÓRIO:
```bash
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
sudo -u www-data php bin/magento cache:flush
```
```

---

## FASE 9 — CHECKOUT PROFISSIONAL

```prompt
Você é um especialista em UI/UX Magento 2. Melhore o visual do checkout do Magento 2
mantendo toda a lógica KnockoutJS/RequireJS intacta.

PRINCÍPIO: Apenas CSS — NUNCA alterar .js ou .phtml do checkout sem necessidade.

LAYOUT CHECKOUT:
- 2 colunas: formulário (60%) + resumo do pedido (40%)
- Gap: var(--awa-space-7)
- Sticky summary em desktop (position: sticky, top: 80px)

STEPS (Endereço → Pagamento):
- Step ativo: número em circle var(--awa-red), label bold, color var(--awa-dark)
- Step completo: ✓ em circle var(--awa-green, #16a34a)
- Step pendente: número em circle var(--awa-gray-200), label color var(--awa-gray-400)
- Linha conectora: background var(--awa-gray-200), 2px height

RESUMO DO PEDIDO:
- Card: border 1px solid var(--awa-color-border), border-radius 8px, padding 24px
- Título "Resumo": font-weight 700, border-bottom 2px solid var(--awa-red), pb 12px
- Item: flex, imagem 56px, nome + qty à esquerda, preço à direita
- Subtotal / Frete / Total: tabela com font-weight 700 no total

MÉTODOS DE PAGAMENTO:
- Card de cada método: border 1.5px solid transparent, border-radius 8px, padding 16px
- Selecionado: border-color var(--awa-red), background rgba(183,51,55,.04)
- Ícone do método: height 28px

BOTÃO "FINALIZAR PEDIDO":
- PRIMARY, width 100%, height 56px, font-size 16px, font-weight 700
- Ícone de cadeado à esquerda (segurança)

SELETORES MAGENTO CHECKOUT:
- .checkout-index-index .page-wrapper
- .checkout-index-index .opc-wrapper
- .checkout-index-index .opc-progress-bar
- .checkout-index-index .payment-method

ARQUIVO: awa-bundle-custom.unmin.css (seção "=== CHECKOUT PROFESSIONAL ===")
ATENÇÃO: Testar no modo guest e logado. Não quebrar validações JS.

DEPLOY OBRIGATÓRIO:
```bash
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
sudo -u www-data php bin/magento cache:flush
```
```

---

## FASE 10 — LOGIN, CADASTRO E CONTA DO CLIENTE

```prompt
Você é um especialista em UI/UX Magento 2. Melhore as páginas de autenticação e
conta do cliente do AWA Motos.

PÁGINAS: customer/account/login, customer/account/create, customer/account/

LAYOUT LOGIN/CADASTRO:
- Card centralizado, max-width 480px, border-radius 12px
- Box-shadow: 0 8px 32px rgba(0,0,0,.08)
- Padding: 40px
- Logo/título no topo (h2 "Bem-vindo de volta")
- Campos usando o sistema de formulários da Fase 2

DIVISOR "OU":
- Linha horizontal com "ou" no centro
- Color: var(--awa-gray-300)

LINKS "Esqueci a senha" / "Criar conta":
- Color: var(--awa-red), text-decoration none
- Hover: text-decoration underline

PÁGINA DE CONTA (dashboard):
- Sidebar de navegação: links de conta (Pedidos, Endereços, etc.)
- Link ativo: color var(--awa-red), border-left 3px solid var(--awa-red)
- Cards de resumo: Últimos pedidos, Endereço padrão

PEDIDOS (account/order):
- Tabela de pedidos: linha par com background var(--awa-bg-soft)
- Status badges:
  * Processando: background #dbeafe, color #1e40af
  * Completo: background #dcfce7, color #166534
  * Cancelado: background #fee2e2, color #991b1b
  * Pendente: background #fef3c7, color #92400e

SELETORES:
- .customer-account-login .page-wrapper
- .customer-account-create .page-wrapper
- .customer-account-index .page-wrapper
- .sales-order-history .page-wrapper

ARQUIVO: awa-bundle-custom.unmin.css (seção "=== CUSTOMER ACCOUNT ===")

DEPLOY OBRIGATÓRIO:
```bash
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
sudo -u www-data php bin/magento cache:flush
```
```

---

## WORKFLOW DE EXECUÇÃO

```
Para cada fase:
1. Leia os arquivos relevantes ANTES de editar
2. Implemente no .unmin.css correto
3. Execute:
   cp [arquivo].unmin.css pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/[arquivo].css
   cp [arquivo].unmin.css pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/[arquivo].min.css
   php bin/magento cache:clean full_page block_html
4. Verifique no browser (hard refresh Ctrl+Shift+R)
5. Teste mobile (DevTools → toggle device)
6. Só avance para próxima fase se a atual estiver OK

TESTE DE REGRESSÃO após cada fase:
- Header: logo, busca, carrinho, nav bar, benefits bar
- Mobile: menu hamburguer, busca expandida
- FOUC: atualizar 3x seguidas e observar flash
```

---

## TOKENS DE REFERÊNCIA RÁPIDA

```css
/* Cores */
--awa-red: #b73337          /* Primária */
--awa-red-dark: #8e2629     /* Hover/escuro */
--awa-dark: #333333         /* Texto principal */
--awa-gray-500: #666666     /* Texto secundário */
--awa-gray-400: #94a3b8     /* Placeholder/muted */
--awa-white: #fff
--awa-bg-soft: #f7f7f7      /* Fundo suave */
--awa-color-border: #e5e5e5 /* Bordas */

/* Espaçamento */
--awa-space-1: 4px  --awa-space-2: 8px   --awa-space-3: 12px
--awa-space-4: 16px --awa-space-5: 20px  --awa-space-6: 24px
--awa-space-7: 32px --awa-space-8: 40px  --awa-space-9: 48px

/* Tipografia */
--awa-text-xs: 12px  --awa-text-sm: 14px  --awa-text-base: 16px
--awa-text-lg: 18px  --awa-text-xl: 24px  --awa-text-2xl: 32px

/* Utilitários */
--awa-radius-sm: 8px   --awa-radius-full: 9999px
--awa-weight-semibold: 600  --awa-weight-bold: 700
--awa-container: 1280px
```
