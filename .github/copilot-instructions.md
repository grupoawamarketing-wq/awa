# Copilot Instructions — AWA Motos / Magento 2

## Sobre o Desenvolvedor
- Nome: Jess
- Empresa: AWA Motos — distribuidora de peças para motos (Araraquara, SP, Brasil)
- Foco: E-commerce Magento 2, B2B, integração ERP, automações

## Stack Principal
- **Plataforma:** Magento 2.4.8-p3 (Community Edition)
- **PHP:** 8.4
- **Banco:** MySQL (via Magento ORM / Zend/DB)
- **Servidor:** Nginx + PHP-FPM
- **Cache:** Redis (sessions + cache)
- **Search:** Elasticsearch / OpenSearch
- **Frontend:** Magento Luma/Ayo theme (Knockout.js, RequireJS, LESS)
- **OS:** Ubuntu (via SSH remoto)
- **Versionamento:** Git com conventional commits

## Estrutura do Projeto (Magento 2)
```
app/
├── code/
│   ├── GrupoAwamotos/      # Módulos customizados da AWA (31 módulos)
│   │   ├── AbandonedCart/   # Recuperação de carrinhos abandonados (e-mail + cupons)
│   │   ├── B2B/             # Sistema B2B (grupos, aprovação, cotações, CNPJ)
│   │   ├── BrazilCustomer/  # Atributos brasileiros (CPF, CNPJ, PF/PJ)
│   │   ├── CarrierSelect/   # Gestão de transportadoras customizadas
│   │   ├── CatalogFix/      # Fixes para bugs do Magento 2.4.x no catálogo
│   │   ├── CspFix/          # Fix de escrita atômica no sri-hashes.json (CSP)
│   │   ├── ERPIntegration/  # Integração com ERP via SQL Server
│   │   ├── FakePurchase/    # Notificações simuladas de compra (desativado)
│   │   ├── Fitment/         # Compatibilidade peças x motos (aplicação)
│   │   ├── LayoutFix/       # Correção de layout admin (notification.messages)
│   │   ├── MaintenanceMode/ # Modo manutenção com whitelist IP e código secreto
│   │   ├── OfflinePayment/  # Pagamento "A Combinar" para B2B
│   │   ├── SalesIntelligence/ # Dashboard de inteligência de vendas e previsão
│   │   ├── SchemaOrg/       # Dados estruturados JSON-LD e Open Graph
│   │   ├── SmartSuggestions/ # Sugestões de recompra (análise RFM)
│   │   ├── SmtpFix/         # Fix SMTP para Magento 2.4.8 + Symfony Mailer
│   │   ├── SocialProof/     # Prova social real (visualizações, mais vendido)
│   │   ├── StoreSetup/      # CLI para setup automático da loja
│   │   ├── Theme/           # Customizações do tema (store switcher, bandeiras)
│   │   ├── Chatwoot/       # Integração chat Chatwoot (admin + CSP + webhook)
│   │   ├── CookieConsent/  # Banner LGPD de cookies
│   │   ├── LeadLovers/     # Integração LeadLovers (automação de marketing)
│   │   ├── LiveChat/       # Chat ao vivo com contexto de cliente
│   │   ├── LogMonitoring/  # Monitoramento de logs e alertas (Cron + admin + API)
│   │   ├── MarketingIntelligence/ # Campanhas e segmentação de marketing
│   │   ├── PreprocessedFallback/ # Fallback PHP para var/view_preprocessed (Plugin)
│   │   ├── ProductIntelligence/  # Recomendações de produtos (ML + Cron + widget)
│   │   ├── RelatedProducts/ # Produtos relacionados customizados
│   │   ├── RexisML/        # Engine de recomendações ML (API + Cron + widget)
│   │   ├── TawkIntegration/ # Integração Tawk.to live chat (DB + admin)
│   │   └── WhatsAppCommerce/ # Comércio via WhatsApp (API + Cron + webhook)
│   ├── Awa/                 # Módulos do namespace Awa
│   │   ├── DashboardFix/    # Fix do grid Last Orders (null billing address)
│   │   └── RealTimeDashboard/ # Dashboard admin em tempo real
│   ├── Ayo/                 # Módulos do namespace Ayo
│   │   └── Curriculo/       # Sistema "Trabalhe Conosco" (candidaturas)
│   └── Rokanthemes/         # Tema Ayo e extensões (27 módulos)
│       ├── Themeoption/     # Core do tema (header, footer, cores, fontes, sticky)
│       ├── CustomMenu/      # Menu horizontal com megamenu (Classic/Full/Static)
│       ├── VerticalMenu/    # Menu vertical lateral com megamenu
│       ├── SlideBanner/     # Slideshow/banner rotativo
│       ├── ProductTab/      # Tabs de produtos (New, OnSale, Best, Featured, etc.)
│       ├── Categorytab/     # Tabs de categorias com carrossel
│       ├── OnePageCheckout/ # Checkout em página única
│       ├── LayeredAjax/     # Navegação layered com AJAX + slider preço
│       ├── Blog/            # Sistema de blog integrado
│       ├── QuickView/       # Modal de visualização rápida de produto
│       ├── Superdeals/      # Ofertas com countdown
│       ├── PriceCountdown/  # Contador regressivo de preço especial
│       ├── SearchSuiteAutocomplete/ # Busca com autocomplete
│       ├── SearchbyCat/     # Busca filtrada por categoria
│       ├── BestsellerProduct/ # Produtos mais vendidos
│       ├── MostviewedProduct/ # Produtos mais visualizados
│       ├── Newproduct/      # Produtos novos
│       ├── Onsaleproduct/   # Produtos em promoção
│       ├── Featuredpro/     # Produtos em destaque
│       ├── Toprate/         # Produtos melhor avaliados
│       ├── Testimonials/    # Depoimentos de clientes
│       ├── Brand/           # Gestão de marcas
│       ├── Instagram/       # Feed do Instagram
│       ├── Faq/             # Perguntas frequentes
│       ├── StoreLocator/    # Localizador de lojas
│       ├── AjaxSuite/       # Suite AJAX (add to cart, wishlist, compare)
│       └── RokanBase/       # Base/core das extensões
├── design/
│   └── frontend/
│       └── ayo/             # Tema Ayo (múltiplas variantes)
│           ├── ayo_default/ # Tema principal ativo
│           ├── ayo_home2/ a ayo_home16/  # Variantes de homepage
│           └── ayo_home*_rtl/  # Variantes RTL
├── etc/
│   └── env.php              # Configurações de ambiente
pub/
├── media/                   # Imagens de produtos
└── static/                  # Assets compilados
var/
├── log/                     # Logs (system.log, exception.log)
└── cache/                   # Cache de arquivos
```

## Padrões de Código — Magento 2

### PHP
- Seguir PSR-12 (coding style)
- Type hints em TODOS os parâmetros e retornos de método
- NUNCA usar `mixed` sem necessidade — use tipos específicos
- Classes finais quando não são extensíveis
- Injeção de dependência via construtor (DI), NUNCA ObjectManager direto
- DocBlocks com `@param`, `@return`, `@throws`
- Usar `declare(strict_types=1)` em todo arquivo PHP

### Magento Module Structure
```
app/code/GrupoAwamotos/NomeModulo/
├── registration.php
├── etc/
│   ├── module.xml
│   ├── di.xml               # Dependency Injection
│   ├── routes.xml            # Rotas frontend
│   ├── adminhtml/
│   │   ├── routes.xml        # Rotas admin
│   │   └── system.xml        # Configurações admin
│   ├── frontend/
│   │   └── routes.xml
│   └── db_schema.xml         # Schema do banco
├── Model/                    # Business logic
├── Api/                      # Service contracts (interfaces)
│   └── Data/                 # Data interfaces
├── Controller/               # Controllers
│   ├── Adminhtml/            # Admin controllers
│   └── Index/                # Frontend controllers
├── Block/                    # View blocks
├── view/
│   ├── frontend/
│   │   ├── layout/           # XML layouts
│   │   ├── templates/        # PHTML templates
│   │   └── web/              # JS, CSS, images
│   └── adminhtml/
│       ├── layout/
│       ├── templates/
│       └── web/
├── Setup/                    # Install/Upgrade scripts
├── Observer/                 # Event observers
├── Plugin/                   # Interceptors (plugins)
├── Cron/                     # Cron jobs
└── Helper/                   # Helper classes
```

### Naming (Magento)
- Módulos: `VendorName_ModuleName` (PascalCase)
- Classes: PascalCase com namespace completo
- Métodos: camelCase
- Constantes: UPPER_SNAKE_CASE
- Tabelas DB: `vendor_module_entity` (snake_case)
- Eventos: `vendor_module_action` (snake_case)
- Layouts: `vendor_module_controller_action.xml` (snake_case)

### Error Handling
- Usar try/catch em operações de banco e API
- NUNCA silenciar erros com catch vazio
- Log com `$this->logger->error()` (Psr\Log\LoggerInterface)
- Retornar mensagens amigáveis via `$this->messageManager`
- Usar exceções específicas do Magento quando possível

### Banco de Dados (Magento)
- Usar `db_schema.xml` para esquemas (declarative schema)
- NUNCA manipular SQL direto — usar Repository Pattern
- Usar Collections para queries
- Paginação obrigatória em listagens
- Índices em colunas usadas em WHERE/JOIN

## Regras de Conduta do Agent

### SEMPRE faça:
1. Leia os arquivos existentes ANTES de criar novos
2. Verifique `etc/module.xml` e `di.xml` antes de modificar módulos
3. Rode `php bin/magento setup:di:compile` mentalmente para validar DI
4. Use injeção de dependência, NUNCA ObjectManager::getInstance()
5. Mantenha consistência com o código existente do módulo
6. Implemente tratamento de erro real em toda integração
7. Verifique `var/log/system.log` e `var/log/exception.log` após mudanças

### NUNCA faça:
1. ❌ Não gere código mock, placeholder, ou "TODO: implementar"
2. ❌ Não use ObjectManager diretamente (exceto em scripts de teste)
3. ❌ Não crie arquivos duplicados ou redundantes
4. ❌ Não altere `app/etc/env.php` sem avisar
5. ❌ Não assuma a estrutura — sempre leia os arquivos primeiro
6. ❌ Não deixe `var_dump`, `print_r`, `echo` em código de produção
7. ❌ Não faça commit de secrets, tokens, ou senhas
8. ❌ Não rode `setup:upgrade` sem avisar (pode afetar produção)
9. ❌ Não altere arquivos do core ou vendor

## Comandos do Projeto
- **Cache:** `php bin/magento cache:clean && php bin/magento cache:flush`
- **Compile:** `php bin/magento setup:di:compile`
- **Deploy:** `php bin/magento setup:static-content:deploy pt_BR -f`
- **Upgrade:** `php bin/magento setup:upgrade`
- **Reindex:** `php bin/magento indexer:reindex`
- **Mode:** `php bin/magento deploy:mode:show`
- **Module status:** `php bin/magento module:status`
- **Config:** `php bin/magento config:show [path]`
- **Logs:** `tail -50 var/log/system.log` / `tail -50 var/log/exception.log`

## Contexto de Negócio
- **Produtos:** Bagageiros, baús, retrovisores, acessórios para motos
- **Motos foco:** Honda CG 160, Titan, Fan, Bros 160, XRE 300, CB 300, Yamaha Fazer 250, Factor 150
- **Tema:** Rokanthemes Ayo (customizado, 27 extensões)
- **ERP:** Integração via SQL Server (módulo ERPIntegration)
- **B2B:** Sistema de clientes empresariais com aprovação, cotações e CNPJ
- **Fitment:** Compatibilidade de peças por modelo de moto
- **SEO:** Schema.org JSON-LD + Open Graph
- **Inteligência:** SalesIntelligence com previsão de demanda e alertas

## Tema Ayo (Rokanthemes) — Referência Rápida

> Fonte canônica: `docs/theme-ayo.md` (manter este bloco como resumo e atualizar o documento canônico primeiro).

Resumo operacional (detalhes completos em `docs/theme-ayo.md`):

- Módulos Rokanthemes ativos: 27
- Templates-chave: `header.phtml`, `header/logo.phtml`, `footer.phtml`
- Blocos CMS críticos: footer/menu/menu vertical/sidebar
- Configurações principais: Theme Settings, Menu, Slider, Layered Ajax, One Page Checkout
- Regra de ouro: nunca editar `app/code/Rokanthemes/*`; sempre sobrescrever no tema em `app/design/frontend/ayo/...`

## Frontend — Proteção de Layout

> Regras obrigatórias para qualquer edição visual (CSS, LESS, PHTML, layout XML).

### Protocolo antes de editar

1. **Identifique a zona** — verifique se o arquivo pertence ao tema filho (`AWA_Custom/ayo_home5_child`) ou a um módulo customizado (`app/code/GrupoAwamotos/`). Nunca edite `app/code/Rokanthemes/*`.
2. **Leia o bundle correto** — para CSS, identifique qual bundle gerencia a área:
   - Header/footer → `awa-bundle-core.unmin.css`
   - Páginas de categoria/PLP → `awa-bundle-category.unmin.css`
   - PDP (produto) → `awa-bundle-site.unmin.css`
   - Variáveis globais → `awa-core-variables.unmin.css` (tokens)
3. **Verifique `var/view_preprocessed`** — em produção, templates PHTML são servidos de `var/view_preprocessed/pub/static/app/design/frontend/AWA_Custom/ayo_home5_child/`. Após criar override, copie manualmente o arquivo para lá antes de limpar cache.
4. **Nunca use hex hardcoded** — use sempre `var(--awa-red)`, `var(--awa-primary)` etc. do `awa-core-variables.unmin.css`.

### Cascata CSS (ordem de prioridade, última ganha)

1. `styles-m.css` / `styles-l.css` (LESS compilado Magento)
2. `themes.css` / `themes5.css` (tema Ayo pai)
3. `awa-bundle-core.css` — base global AWA
4. `awa-bundle-category.css` — PLP específico
5. `awa-bundle-phases.css` — variáveis CSS, `!important` pontual
6. `awa-bundle-site.css` — "final wins" geral
7. `awa-bundle-refinements.css` — carrega por último, overrides globais

Para novos estilos que precisam ter prioridade: adicionar no bundle de menor nível que abrange o contexto, **com seletor específico**, evitando `!important`.

### Deploy após edição

```bash
# CSS/LESS alterado
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
sudo -u www-data php bin/magento cache:flush

# Apenas PHTML alterado
sudo -u www-data php bin/magento cache:clean block_html full_page

# Se var/view_preprocessed estiver desatualizado
sudo -u www-data cp app/design/frontend/AWA_Custom/ayo_home5_child/[Vendor_Module]/templates/[file].phtml \
  var/view_preprocessed/pub/static/app/design/frontend/AWA_Custom/ayo_home5_child/[Vendor_Module]/templates/[file].phtml
sudo -u www-data php bin/magento cache:clean block_html full_page
```

### Checklist pós-edição de layout

- [ ] Página modificada renderiza sem erros (sem 500, sem 0 bytes)
- [ ] `tail -5 var/log/exception.log` sem novas entradas
- [ ] Áreas adjacentes não regrediram (header, footer, mobile)
- [ ] Inspecionar no browser sem service worker (`Disable cache` + unregister SW se necessário)

### Proibições de layout

- ❌ Editar `app/code/Rokanthemes/*` — usar override no tema filho
- ❌ `!important` sem comentário explicando motivo
- ❌ CSS inline no PHP/PHTML (usar classes)
- ❌ Hex hardcoded — usar tokens CSS (`var(--awa-*)`)
- ❌ `setup:static-content:deploy` sem `--theme AWA_Custom/ayo_home5_child` para mudanças no tema filho (mais lento e pode causar diferença de comportamento)

## Ferramentas de Debug Visual

### Chrome MCP — Playwright MCP (investigação em tempo real)
Servidor: `io.github.chr` → tools prefixadas com `mcp_io_github_chr_*`. Carregar antes de usar.
Instalação: `playwright-mcp` global + Google Chrome 145 (`--browser chrome --no-sandbox --caps vision`).

Fluxo para investigar layout quebrado:
1. `browser_navigate` → URL da página com problema
2. `browser_take_screenshot` → estado atual desktop
3. `browser_resize` `{"width": 375, "height": 812}` → `browser_take_screenshot` mobile
4. `browser_snapshot` → inspecionar DOM (accessibility tree) sem executar JS
5. `browser_evaluate` → `getComputedStyle(document.querySelector('.seletor'))` para confirmar qual CSS está ativo
6. `browser_network_requests` → verificar recursos bloqueados/com erro
7. Busca em bundle CSS → via filesystem MCP ou `browser_evaluate` com fetch+text

### Playwright (testes visuais automatizados)
Specs em `tests/e2e/specs/` — cobrem home, header, footer, PDP, categoria, checkout, 404, B2B, acessibilidade.

```bash
cd tests/e2e

# Rodar spec visual
npx playwright test specs/visual-audit-home-header-footer.spec.ts

# Criar/atualizar baseline (só após confirmar visualmente!)
npx playwright test --update-snapshots

# Relatório HTML
npx playwright show-report reports/html
```

> ⚠️ O diretório `tests/e2e/snapshots/` ainda não tem baseline gerado. Antes de usar `toHaveScreenshot`, rode `--update-snapshots` uma vez com o layout em estado correto.

## Procedimentos Operacionais Críticos

### Mudança de Domínio / URL Base
Após qualquer alteração de `web/secure/base_url` ou `web/unsecure/base_url`, execute **obrigatoriamente** nesta ordem:

```bash
sudo -u www-data php bin/magento cache:flush
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 1 FLUSHDB  # Redis DB1: cache Magento
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 2 FLUSHDB  # Redis DB2: FPC (Full Page Cache)
sudo -u www-data php bin/magento indexer:reindex catalog_url
```

**Por quê:** O FPC armazena HTML completo incluindo URLs absolutas. Se o domínio mudou mas o FPC não foi limpo, o browser receberá HTML com URLs do domínio antigo. O CSP usa `'self'` = domínio atual, então todas as referências ao domínio antigo serão bloqueadas — incluindo `require.js`, que derruba toda a stack JavaScript do Magento.

### Redis AWA — Mapa de bancos
| DB | Conteúdo | Comando flush |
|----|----------|--------------|
| 0 | Sessions | `redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 0 FLUSHDB` |
| 1 | Cache Magento (config, block, layout) | `redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 1 FLUSHDB` |
| 2 | FPC — Full Page Cache (HTML completo) | `redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 2 FLUSHDB` |

> `php bin/magento cache:flush` faz flush do DB1 via Magento. O DB2 (FPC) precisa ser limpo separadamente via redis-cli.
