# Plano de Correcao de Layout por Fases

> **Arquivo:** histórico técnico por fases (correções aplicadas, retrospecto, contexto capturado).
>
> **Fontes canônicas do projeto visual (consolidadas em 2026-06-28):**
> - **Tracker de bugs visuais:** [`docs/PLANO_BUGS_VISUAIS.md`](./PLANO_BUGS_VISUAIS.md) ← **canônico para bugs**
> - **Autoridade CSS / design system:** [`app/design/frontend/AWA_Custom/ayo_home5_child/DESIGN_SYSTEM_STATUS.md`](../app/design/frontend/AWA_Custom/ayo_home5_child/DESIGN_SYSTEM_STATUS.md)
>
> ⚠️ **Atenção:** existe cópia **untracked** em `app/design/frontend/AWA_Custom/ayo_home5_child/PLANO_CORRECAO_LAYOUT_FASES.md` (4164 B, Jun-23) que NÃO é canônica. É rascunho local de trabalho.
>
> Qualquer novo bug visual deve ser registrado **primeiro** em `docs/PLANO_BUGS_VISUAIS.md`, e referenciado aqui apenas se houver desdobramento de fase técnica relevante.

## Contexto

Pagina auditada: storefront AWA Motos em Magento 2.4.8-p3, tema filho `AWA_Custom/ayo_home5_child`.

Problemas visiveis na captura:

- Header com altura excessiva e muito espaco vazio.
- Campo de busca e botao de busca quebrados em duas linhas.
- Logo, busca, conta B2B e minicart sem alinhamento vertical consistente.
- Navegacao de departamentos desalinhada do header.
- Conteudo principal aparentemente ausente, colapsado ou renderizado com altura incorreta.
- Footer entrando imediatamente apos o menu.
- Footer com massa vermelha grande, colunas espalhadas e textos de baixo contraste.
- Categoria/chips no footer com espacamento e hierarquia irregulares.

## Status atual — 2026-06-23

Resumo da continuidade clean/VTEX desta rodada:

- Backlog visual capturado em 2026-06-23 para próximas fases:

### Header

- Logo pequeno demais e com pouco peso visual em desktop.
- Busca desproporcional em relação ao header: campo longo, texto/ícone pequenos e densidade fraca.
- Botão vermelho da busca aparenta bloco separado quando não integrado ao input.
- Clear da busca/autocomplete vazio pode reaparecer por cascata tardia e precisa continuar monitorado.
- Bloco `Preços B2B` tem risco recorrente de compressão e hierarquia fraca.
- Login/cadastro ficam pequenos em relação ao restante do header.
- Carrinho parece isolado visualmente quando não compartilha a mesma linguagem do bloco de conta.
- Botão `Departamentos` apresentou ruído de ícones duplicados antes do texto.
- Nav tem peso desigual: `Departamentos` muito dominante e links laterais muito leves.
- Header pode parecer miniaturizado em relação ao restante da página em viewports largos.

### Hero

- Texto do banner aparece cortado à esquerda.
- Hero parece deslocado ou clipado dentro do container.
- Composição do banner está desequilibrada: texto muito à esquerda e produto grande à direita.
- Paginação do hero é muito discreta.
- Falta respiro claro entre nav e hero.

### Benefícios pós-hero

- Cards de benefícios estão pequenos demais.
- Textos dos benefícios têm baixa legibilidade.
- Ícones dos benefícios estão fracos ou pequenos.
- Bloco `Condições B2B` parece desalinhado em relação aos outros cards.

### Categorias

- Cards de categoria estão pequenos demais.
- Ícones das categorias têm tamanhos inconsistentes.
- Setas do carrossel ficam coladas ou soltas nas laterais.
- CTA `Ver todos` está pequeno e muito discreto.
- Há espaço branco sem função entre elementos.

### Aviso B2B

- Bloco informativo B2B está visualmente fraco.
- Ícone de alerta fica pesado em comparação ao texto.
- Links dentro do aviso parecem pequenos e pouco clicáveis.
- Alinhamento interno do aviso está irregular.

### Pedidos recentes

- Card de `Pedidos feitos recentemente` parece vazio e pouco útil.
- CTAs internos estão pequenos.
- Hierarquia entre `Entrar`, `cadastre seu CNPJ`, `Ver mais vendidos` e WhatsApp é confusa.

### Prateleiras de produtos

- Cards de produto parecem pequenos demais.
- Imagens têm tamanhos e enquadramentos inconsistentes.
- Alguns cards mostram placeholder ou imagem quebrada.
- Botão `VER PREÇOS` está pálido e com baixo contraste.
- Títulos dos produtos estão pequenos demais.
- Espaçamento interno dos cards não cria hierarquia clara entre imagem, título, preço/CTA.
- Navegação e progresso dos carrosséis são discretos demais.
- CTAs `Ver todos` das seções estão pequenos e afastados.

### Destaques

- Grid de banners está quebrado: dois cards na primeira linha e um card sozinho na segunda, com vazio grande à direita.
- Banners vermelhos têm textos cortados ou pequenos.
- CTA `CONFIRA` tem baixo refinamento visual.
- Imagens dos banners parecem desalinhadas.
- Bloco não aproveita a largura disponível.

### Lançamentos

- Há produtos com imagem placeholder quebrada.
- Badges `novo` aparecem pequenos e inconsistentes.
- Cards têm densidade alta e pouca leitura.
- Botões continuam pálidos.

### Linhas em destaque

- Tabs/chips como `Bauletos` e `Retrovisores` estão pequenos e desconectados.
- Subtexto da seção está pequeno demais.
- Cards repetem problemas de imagem, CTA e hierarquia.

### Newsletter

- Newsletter está muito larga e vazia.
- Campo de email pequeno ou desalinhado.
- Botão `Receber novidades` pequeno.
- Texto explicativo pouco legível.
- Ícone à esquerda parece solto.

### Footer

- Footer está excessivamente espaçado verticalmente.
- Colunas têm textos muito pequenos.
- Linhas vermelhas sob títulos parecem pesadas.
- Bloco de atendimento/mapa está desalinhado e com baixa hierarquia.
- Ícones sociais estão pequenos demais.
- Chips de categorias no footer estão pequenos e espaçados de forma irregular.
- Bloco de pagamento/selos está visualmente fraco.
- Área legal/copyright é pequena e pouco legível.
- `Desenvolvido por` fica perdido no final.

### Página geral

- Conteúdo inteiro parece reduzido ou miniaturizado.
- Há muito vazio lateral e a página não aproveita bem o viewport desktop.
- Contraste geral está baixo em textos e botões.
- Hierarquia visual é fraca, com muitos blocos no mesmo peso.
- Muitos elementos clicáveis parecem abaixo do alvo ideal de `44px`.
- Layout parece mistura de correções parciais: header, hero, shelves e footer não compartilham a mesma densidade.
- Há indícios de imagens quebradas ou lazy-load falhando em produtos.
- Página tem excesso de altura para a quantidade real de conteúdo útil.
- Identidade vermelha é aplicada de forma inconsistente: alguns CTAs fracos e alguns blocos pesados.

- Correção 2026-06-23 (header clean r13):
  - causa raiz: a home estava recebendo cascata tardia que deixava o header em `211px`, restringia a linha principal a `822px` e reduzia a busca para `286px`;
  - reforçado contrato desktop no LESS final e no terminal `awa-align-grid-terminal`: header `156px`, promo `32px`, sticky `124px`, row `1280px`, busca `772px`, nav `48px`;
  - corrigido `.awa-nav-quick-links` que caia sobre o hero: agora fica dentro da barra em `x=268/y=111`, com containing block explícito no grid da nav;
  - corrigido mobile home para header compacto `136px`, promo `36px`, busca `316x44` e nav desktop invisível sem reservar altura útil;
  - query do terminal atualizada para `?v=20260623-header-clean-r13`, PHTML preprocessado sincronizado, CSS estático recomprimido e FPC limpo;
  - validação: `php -l` em PHP/PHTML, compile LESS de `_extend.less`, Playwright desktop `1366x768` e mobile `390x844` sem erro JS e sem overflow horizontal; busca com pseudo-ícone visível, clear/autocomplete vazio ausentes, B2B alinhado dentro do box e Departamentos com ícone único em contraste correto.

- Correção 2026-06-23 (home hero / Swiper / full width):
  - causa raiz identificada: o hero dependia de `awaHeroSlider -> swiper -> awa-swiper-shared` via AMD; em mobile/loads frios o `swiper` ficava `specified/fetched` mas nao `defined`, deixando o slider sem `awa-hero-swiper-ready`;
  - removida a dependencia bloqueante de `jquery`, `swiper` e `awa-swiper-shared` no inicializador `awa-hero-slider.js`; o hero agora usa DOM nativo, deep merge proprio e carrega `swiper-bundle.min.js` por runtime quando `window.Swiper` ainda nao existe;
  - `slider_home5.phtml` passou a antecipar o carregamento do bundle Swiper com URL versionada Magento e `fetchpriority=high`, evitando requisicao tardia no breakpoint mobile;
  - corrigido retry do inicializador: quando o modulo retorna `false`, quando RequireJS ainda nao esta pronto ou quando o carregamento fica pendente, o script reagenda de forma limitada e idempotente;
  - corrigido `requirejs-config.js` para shimar tambem o alias `swiper`, nao apenas o caminho fisico `GrupoAwamotos_Theme/js/vendor/swiper-bundle.min`;
  - hero desktop validado em `1366x768`: `awa-hero-swiper-ready=1`, `swiper-initialized=1`, `window.Swiper=function`, banner `1366px` full-width e quick links alinhados a esquerda;
  - hero mobile validado em `390x844`: `awa-hero-swiper-ready=1`, `swiper-initialized=1`, `window.Swiper=function`, wrapper mobile ativo e wrapper desktop marcado como `aria-hidden=true`;
  - arquivos publicados em `pub/static`, template copiado para `var/view_preprocessed`, gzip/brotli regenerados, `cache:flush`, Redis FPC DB2 `FLUSHDB` e reload do `php8.4-fpm` executados.

- Atualizacao 2026-06-24:
  - Corrigido import quebrado no final do _extend.less e reintegrado _awa-header-vtex-final-polish-2026-06-24.less ao bundle final.
  - Ajustado lock final no aja-css-gate.js: categoria/departamentos passou de 40px para 44px no estado final de header.
  - Confirmado compilacao do _extend.less apos ajuste com lessc sem erros.
  - Proximo passo: reduzir dependencia de locks inline em header com fallback curto e spec de 3s/9s por pagina.
  - Status tecnico 2026-06-24-post:
    - Arquivos alterados nesta passada foram validados com lessc e node --check.
    - Risco residual: ainda depende de terminal/critical em header em algumas rotas antes da consolidação completa.
    - O ciclo segue sem alterar comportamento funcional para não impactar carrinho, checkout e B2B.
- Implementado na continuidade 2026-06-23 (home / Destaques):
  - corrigido bug da secao `Destaques de produto` no mobile: CTA `Ver todos` ficava visivel fora da area util, em `x=382` com largura `86px`;
  - identificado que o CSS dedicado `_awa-home-product-promo-banners.less` nao estava presente no CSS servido da home, deixando `.awa-product-promo-banners__grid` como `display:block` e itens como links `inline`;
  - adicionado contrato duravel no LESS e lock terminal no `awa-home-body-end-bundle.css/.min.css`;
  - grid de banners agora funciona como reel horizontal no mobile e grid de 3 colunas no desktop;
  - itens promocionais passaram a `display:block`, com largura controlada, snap horizontal e imagens dentro do aspect ratio `463 / 349`;
  - CTA `.awa-shelf-view-all--desktop-only` fica oculto no mobile dessa secao e visivel no desktop.

- Validacao desta continuidade:
  - `lessc _extend.less`: passou;
  - CSS estatico `awa-home-body-end-bundle.css/.min.css` sincronizado em `pub/static` e comprimidos `.gz/.br` regenerados;
  - `cache:flush` e Redis FPC DB2 `FLUSHDB`: executados;
  - home mobile `390px`: overflow horizontal `0`, secao `Destaques de produto` caiu de `1146px` para `323px`, grid `display:flex`, CTA `display:none`, itens `320x241px`;
  - home desktop `1440px`: overflow horizontal `0`, secao `Destaques de produto` `392px`, grid `display:grid` com 3 colunas, CTA visivel `86x44px`;
  - documento total mobile caiu de `5390px` para `4567px`; desktop caiu de `4907px` para `4508px`.


- Regra operacional atual:
  - nao criar nenhum arquivo novo para refinamento visual pontual;
  - melhorar em cima das camadas ja prontas e validadas, preservando o que esta funcionando;
  - ajustes finais de header, PDP, mobile, footer e CTAs devem entrar preferencialmente em `_awa-layout-final-cleanups-2026-06.less`;
  - quando o ajuste precisa vencer HTML/CSS publicado antigo, usar o plugin terminal existente `OptimizeHeadStylesPlugin.php`, sem criar fonte novo;
  - `_extend.less` deve receber apenas imports indispensaveis; nesta fase, nao adicionar novo import para refinamento pontual;
  - qualquer correcao deve ser pequena, reversivel e validada contra home, PLP, PDP, carrinho e mobile.

- Implementado:
  - carrossel da home refinado para evitar disputa entre CTA "Ver todos" e navegacao montada no header do shelf;
  - CTAs de shelf `Mais Vendidos` e `Lancamentos` receberam classe propria `awa-shelf-view-all awa-shelf-view-all--desktop-only`, saindo do alvo generico `.awa-section-header__link/.awa-shelf__view-all` que era reaberto por JS terminal;
  - mobile mantem os CTAs de shelf ocultos e deixa o controle do carrossel como acao principal, com `navH=44px`;
  - desktop preserva os CTAs visiveis, alinhados e com alvo de `44px`;
  - terminal home passou a reaplicar ajustes aos `500ms`, `1500ms`, `3000ms`, `6000ms` e `9000ms`, cobrindo inicializacao tardia do carrossel;
  - headers de carrossel mobile com `awa-carousel-nav-host` passaram a receber `min-height:44px` apos a montagem tardia;
  - regra minificada que escondia `.awa-shelf-view-all--desktop-only` fora do escopo mobile foi corrigida;
  - footer PDP desktop teve links institucionais readequados para `44px`, removendo links de `26/28px`.
  - continuidade clean/VTEX sem criar novo partial:
    - bloco de conta B2B no header desktop ganhou largura util para nao cortar "Cadastrar";
    - PDP passa a manter a imagem SSR visivel antes da Fotorama e forca a imagem da Fotorama carregada para `opacity:1` tambem no plugin terminal existente;
    - nomes de produtos relacionados no PDP passaram a ter alvo minimo real de `44px`;
    - campo de quantidade no PDP passou para largura minima de `44px`;
    - WhatsApp mobile ficou icon-only e afastado da bottom nav, com padding extra no conteudo para nao cobrir a area util.
  - nenhum novo arquivo/partial desta continuidade permanece no tema; a continuidade usa apenas arquivos/camadas existentes.

- Validado:
  - `php -l app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Cms/templates/top-home.phtml`: passou;
  - `php -l app/code/GrupoAwamotos/Theme/Plugin/Response/OptimizeHeadStylesPlugin.php`: passou;
  - `php -l app/code/GrupoAwamotos/Theme/Model/HeaderImpeccableCascadeLockCss.php`: passou;
  - `node node_modules/less/bin/lessc app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_extend.less /tmp/awa-extend-clean-vtex-r9.css`: passou;
  - `git diff --check` nos arquivos alterados nesta rodada: passou;
  - deploy estatico/copia de `pub/static`, gzip/brotli, `cache:flush`, Redis FPC DB2 `FLUSHDB` e reload do `php8.4-fpm`: executados;
  - home desktop `1440px`: overflow horizontal `0`; `Mais Vendidos` e `Lancamentos` com CTA visivel (`display:flex`, `86x44px`);
  - home mobile `390px`: overflow horizontal `0`; `Mais Vendidos` com header `44px`, CTA oculto (`0x0`) e nav `44px`; `Lancamentos` com header `54px`, CTA oculto (`0x0`) e nav `44px`;
  - PDP desktop footer: links institucionais/acoes medidos com `44px`, sem overflow horizontal.
  - validacao final desta continuidade:
    - `php -l app/code/GrupoAwamotos/Theme/Plugin/Response/OptimizeHeadStylesPlugin.php`: passou;
    - `node node_modules/less/bin/lessc app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_extend.less /tmp/awa-extend-vtex-clean-refine-r12.css`: passou;
    - `scripts/awa-css-best-practices-audit.sh`: passou, relatorio em `var/report/awa-css-best-practices-20260623-022842.txt`;
    - deploy estatico do tema, `cache:flush`, Redis FPC DB2 `FLUSHDB` e reload do `php8.4-fpm`: executados;
    - PLP desktop `1366px`: overflow horizontal `0`, header terminal ativo, grid `160px 747.219px 340px`, busca `731x44`, bloco de conta `260x44`, texto `Precos B2B Entrar ou Cadastrar` sem corte (`clientW=153`, `scrollW=153`);
    - PDP mobile `375px`: overflow horizontal `0`, terminal live ativo, placeholder e Fotorama com `display:block`, `opacity:1`, imagem `naturalWidth=800`;
    - PDP mobile: WhatsApp `48x48`, `bottom=104px`, folga real de `16px` acima da bottom nav, quantidade `44x44` e relacionado com `min-height=44px`.

- Ainda observar nas proximas fases:
  - auditar outras secoes da home que ainda usam `.awa-section-header__link/.awa-shelf__view-all` para confirmar se todas precisam de comportamento desktop-only ou se devem continuar como CTA mobile;
  - reduzir dependencia de scripts terminais longos migrando gradualmente regras estaveis para LESS compilado e templates;
  - revisar header tablet/mobile em paginas fora da home, pois ainda ha muitos locks antigos no plugin terminal.

- Correção 2026-06-23 (header-lock): removi blocos de `homeImpeccableTerminalRules()` que fixavam alturas internas do header em `72px/76px/80px` na home, que divergiam do padrão VTEX (`156px/124px/76px/64px`). Isso reduz retorno visual para o estado antigo em atualizações tardias de terminal.
- Correção 2026-06-23 (distill fallback): alinhei fallback do lock terminal (`--awa-header-promo-h:32`, `--awa-header-main-row-h:76`, `--awa-header-nav-h:48`) para preservar a régua VTEX mesmo quando o CSS persistir em estado parcial.
- Correção 2026-06-24 (guard header post-gate):
  - Corrigi a condição em `injectPostGateHeaderFix()` para não reenfileirar blocos fallback em presença do lock principal (`v18...v14`), eliminando retorno visual para o layout antigo em atualização tardia (3s/9s).
  - Padronizei a checagem de lock para `!id(v18|v17|v16|v15|v14)` em vez de condição OR que sempre disparava fallback.
  - Resultado esperado: estabilidade do header após carga tardia sem exigir recarga/click extra.

- Correção 2026-06-23 (carrossel/visual final):
  - refinei `_awa-commercial-polish-2026-06-24.less` para remover fallback hex/hardcoded no bloco de CTAs e nav do carrossel, migrando para tokens `var(--awa-*)` e estabilizando alvo visual para `44px`;
  - removi resíduos de `font-size: 0` em contêineres visuais de card e mantive a hierarquia do espaço limpo sem perda de semântica;
  - reforcei `scheduleAdaptTerminal()` em `awa-scroll-carousel.js` para revalidar o terminal home em pulsos até ~14s, evitando retorno tardio do layout legado;
  - regenerado `awa-scroll-carousel.min.js` e atualizado `awa-shelf-carousel-loader.phtml` para `?v=20260623-carousel-runtime-v8`;
  - validação desta rodada: `node --check` no JS unmin/min e compile de `_extend.less` sem erros.
## Status anterior — 2026-06-22

Resumo consolidado da rodada atual:

Correcoes aplicadas nesta continuidade em 2026-06-22:

- Rodada clean/VTEX adicional aplicada em 2026-06-22:
  - toolbar de PLP/busca corrigido no CSS terminal e no LESS duravel: `strong.modes-mode.active` e `a.modes-mode.mode-list` agora medem `44x44px` no desktop;
  - regra antiga que deixava `mode-list` em `32x32px` foi neutralizada com seletor especifico para `.toolbar.toolbar-products .modes .modes-mode`;
  - cards de PLP/busca deixaram de criar scroll interno: `item-product` ficou com `height:auto`, `max-height:none` e `overflow:visible`;
  - footer desktop alinhado ao contrato de alvo clicavel de grandes plataformas: links institucionais, acoes de atendimento, telefone/e-mail, social links e toggles de acordeao com minimo de `44px`;
  - scripts/locks tardios que recolocavam footer em `28px`, `32px`, `34px` ou `36px` foram atualizados para nao voltar ao visual antigo apos timers, resize ou mutation observers;
  - busca/topo mantidos no contrato atual: header desktop `156px`, busca `44px`, conta/carrinho/nav sem overflow horizontal;
  - SKU/meta de produto recebeu contraste mais forte usando tokens existentes, evitando cinza claro lavado;
  - no mobile, links internos do footer podem medir `0x0` enquanto o acordeao esta fechado; isso e estado esperado. Ao abrir o painel, o alvo aplicado e `44px`.

- Validacao desta rodada clean/VTEX:
  - `node node_modules/less/bin/lessc app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_extend.less /tmp/awa-extend-clean-vtex-check.css`: passou;
  - `php -l app/code/GrupoAwamotos/Theme/Model/HeaderImpeccableCascadeLockCss.php`: passou;
  - `php -l app/code/GrupoAwamotos/Theme/Plugin/Response/OptimizeHeadStylesPlugin.php`: passou;
  - `git diff --check` nos arquivos alterados: passou;
  - deploy estatico do tema `AWA_Custom/ayo_home5_child`: executado;
  - `cache:flush`, Redis FPC DB2 `FLUSHDB` e reload do `php8.4-fpm`: executados;
  - probe Playwright CLI em PLP desktop: overflow horizontal `0`, header `156px`, modos `44x44`, footer social `44x44`, `cardScroll=[]`;
  - probe Playwright CLI em busca desktop: overflow horizontal `0`, header `156px`, modos `44x44`, footer social `44x44`, `cardScroll=[]`;
  - probe Playwright CLI em PLP mobile: overflow horizontal `0`, header `141px`, cards sem scroll interno.

- Footer recebeu correcao estrutural adicional em 2026-06-22:
  - acordeao de categorias do footer agora fica aberto no desktop e colapsado no mobile, sem reservar altura fantasma;
  - copyright/aviso legal perderam `max-width`, padding e margens legadas que inflavam o bloco;
  - trust bar mobile foi reformatada em linhas horizontais compactas;
  - newsletter mobile perdeu padding interno excessivo no campo/formulario;
  - secoes institucionais mobile foram reduzidas para acordeoes de `52px`;
  - pagamentos/selos mobile tiveram margens/list-style herdados removidos e grid de 4 colunas;
  - bloco `Desenvolvido por` fica limitado a `56px` desktop e `64px` mobile;
  - script `awa-footer-interactions.js` sincroniza desktop/mobile e evita que o estado antigo volte apos timers/mutations do tema;
  - footer PLP desktop medido em `1235px` e footer PLP mobile em `1411px`, ambos com overflow horizontal `0`;
  - `content-visibility:auto`/`contain-intrinsic-size` legados foram neutralizados no terminal real do footer (`awa-footer-terminal-lock-v1`), reduzindo dependencia de altura por JS;

- Menu `Departamentos` recebeu controle real de clique/teclado/mobile:
  - clique alterna aberto/fechado no desktop;
  - `Enter`, `Space` e `ArrowDown` abrem e movem foco para a busca/primeiro item;
  - `Esc` fecha o painel;
  - hover ganhou atraso de fechamento para reduzir flicker;
  - mobile abre drawer lateral em vez de dropdown espremido;
  - `aria-expanded`, `data-awa-menu-state` e `data-awa-menu-open` passam a refletir o estado real.
- CTAs B2B do PLP corrigidos contra regressao tardia:
  - causa raiz identificada no plugin terminal `awa-global-last-mile-layout-fixes-20260622`;
  - helper JS `width()` estava ausente e interrompia `apply()` antes de chegar nos CTAs/footer;
  - observer/timers tardios reforcados para B2B reidratado apos o primeiro paint;
  - PLP desktop validado apos 9,5s com 12 CTAs, altura minima `44px`, fundo transparente, borda `0px` e overflow horizontal `0`.
- Footer recebeu compactacao estrutural desktop/mobile:
  - links institucionais desktop foram inicialmente compactados, mas na rodada clean/VTEX foram readequados para alvo minimo de `44px`; chips informativos de categoria continuam compactos em `32px` por nao serem CTA primario;
  - bloco `Desenvolvido por` caiu de aproximadamente `201px` para `56px` no desktop e `64px` no mobile;
  - coluna Atendimento foi compactada no desktop e secoes institucionais viraram acordeoes compactos no mobile;
  - footer PLP desktop reduziu para `1235px`; footer PLP mobile reduziu para `1411px`, sem overflow horizontal.
- Nova camada LESS `_awa-layout-final-cleanups-2026-06.less` adicionada ao tema filho para menu, footer e CTAs; plugin terminal segue necessario para vencer CSS legado com alta especificidade.

- Header clean estilo grandes plataformas/VTEX refinado e revalidado em 2026-06-22:
  - desktop fechado com `header=156px`, barra B2B `32px`, sticky `124px`, linha principal `76px`, miolo `64px` e nav `48px`;
  - barra B2B sem clipping: texto branco, CTA com `32px`, botao fechar `44x32px`, overflow visivel e sem corte vertical;
  - busca em linha unica, botao de limpar (`x`) transparente em desktop/tablet/mobile e botao de busca preservado como CTA vermelho;
  - conta B2B sincronizada por `data-awa-auth-state`, exibindo apenas convidado ou cliente; `OLA!` nao fica mais visivel solto no estado guest;
  - carrinho e `Departamentos` com texto/icone branco sobre vermelho, ambos com alvo de `44px`;
  - minicart normalizado em todas as paginas auditadas: wrapper externo, `.mini-carts`, `.minicart-wrapper` e `.showcart` alinhados no mesmo eixo; fallback `.awa-header-cart-fallback` sai do fluxo quando o minicart real esta pronto;
  - grid desktop unificado entre home, PLP, busca, PDP, carrinho e 404: logo `160px`, busca `772px`, conta `220px`, carrinho `44px`, container/nav `1280px`;
  - nav desktop limpa: fundo branco, botao `Departamentos` alinhado ao container, links horizontais sem faixa rosada pesada;
  - dropdown de departamentos validado no hover: painel absoluto, scroll interno, sem overflow horizontal e sem empurrar o header;
  - tablet/mobile preservados nas auditorias concluidas: sem overflow horizontal e sem aplicar as medidas desktop via script;
  - LESS consolidado em `_awa-header-vtex-clean-2026-06.less`; plugin terminal mantido apenas para vencer locks inline legados do header.
  - regressao apos alguns segundos corrigida: critical head `awa-header-vtex-clean-critical-20260622` estava com medidas antigas (`164/36/128/80/72`) e foi alinhado ao estado final (`156/32/124/76/64`); guard terminal ampliado para observar `style/class` e eventos tardios por 70s.
- Header desktop compactado e estabilizado em `156px` no estado final validado.
- Busca do topo corrigida em estado inicial e sticky/rolado, com `x` transparente nos tres breakpoints auditados.
- Tablet: auditoria anterior sem overflow horizontal; probe atual do carrinho foi limitado por OOM da VPS.
- Hero principal corrigido para respeitar a proporcao real da imagem `1920x472`.
- Hero tablet reduziu de aproximadamente 260px para 189px.
- Conteudo principal confirmado como presente; footer nao aparece mais imediatamente apos a nav.
- Footer desktop recebeu compactacao inicial e depois nova rodada estrutural no PLP; o estado mais recente medido no PLP desktop reduziu para `1235px`, sem overflow horizontal.
- Mobile: PDP revalidado nesta rodada sem overflow horizontal, com header `141px` e busca `44px`; home/mobile anterior mantinha header `161px`.
- CTAs "Ver precos" dos cards corrigidos para um padrao unico e limpo:
  - sem icone decorativo dentro do card;
  - sem fundo duplo entre `price-label` e link;
  - `price-label` removido do DOM da home para o CTA B2B;
  - `a.b2b-login-link` agora e filho direto de `div.b2b-login-to-see-price.price-box`;
  - `div.b2b-login-to-see-price.price-box` ficou sem fundo, sem borda, sem sombra e sem padding;
  - link em fluxo normal, sem posicionamento absoluto;
  - cadeado movido para dentro do proprio link, alinhado ao texto como pseudo-elemento de 14px;
  - altura minima de 44px, texto centralizado e largura consistente;
  - fundo e borda validos tambem no mobile, sem depender de variaveis ausentes.

Metricas de validacao mais recentes do header:

| Viewport/pagina | Header | Barra B2B | Sticky/main | Busca/clear | Conta/carrinho | Nav/departamentos | Overflow horizontal | Console/status |
|---|---:|---:|---|---|---|---|---:|---|
| 1365x760 home | 156px | 32px | 124px / 76px | form 44px; `x` transparente | conta 220px; carrinho 44px alinhado | nav 48px; `Departamentos` 206x44 | 0px | passou |
| 1365x760 PLP/busca/PDP/404 | 156px | 32px | 124px / 76px | form 44px; `x` transparente | conta 220px; carrinho 44px alinhado | nav 48px; `Departamentos` 206x44 alinhado ao container `left=42.5px` | 0px | passou |
| 1365x760 carrinho | 156px | 32px | 124px / 76px | form 44px | conta 220px; carrinho 44px alinhado | `Departamentos` 206x44 visivel e alinhado | 0px | passou |
| 390x844 PDP | 141px | 36px | 96px / 88px | form 44px | conta desktop oculta; carrinho 44px | nav desktop oculta, sem reabrir por script | 0px | passou |
| 820x900 tablet | variavel por tipo de pagina | 40-44px | layout tablet preservado | form 44px | sem overflow visual | nav desktop reduzida/oculta conforme pagina | 0px em auditoria anterior | probe atual do carrinho encerrado por OOM |
| B2B login/cadastro | sem `.awa-site-header` | n/a | `page-layout-empty` | n/a | fluxo auth proprio | n/a | 0px em auditoria anterior | ausencia de header e intencional |

Auditoria multi-pagina do header em 2026-06-22:

- Antes desta rodada, PDP renderizava `Departamentos` fora da tela (`left:-9999px`) e carrinho renderizava o bloco como `display:none`.
- Corrigido no lock terminal `injectHeaderVtexCleanTerminal()` e no LESS duravel `_awa-header-vtex-clean-2026-06.less`.
- Desktop revalidado apos espera de 9s por pagina em home, PLP, PDP, carrinho e 404: busca `left=226.5 width=772`, conta `left=1050.5 width=220`, carrinho `left=1278.5 width=44`, nav/container `left=42.5 width=1280`, overflow horizontal `0`.
- Mobile revalidado no PDP; a regra nova de departamentos e protegida por `window.innerWidth >= 992`, entao nao reaplica medidas desktop no mobile.
- A execucao completa/paralela do spec e alguns probes tablet/B2B foram encerrados pela VPS com `exit 137`; por isso a estrategia correta e rodar o spec por `AWA_HEADER_AUDIT_PAGE` e `AWA_HEADER_AUDIT_VIEWPORT`, sempre isolado.

Checks automatizados desta atualizacao:

- Desktop: `tests/e2e/specs/header-all-pages-audit.spec.ts` passou isolado nesta rodada em PLP, PDP e carrinho; medicao temporal Playwright cobriu home, PLP, PDP, carrinho e 404 com overflow `0`.
- Tablet: auditoria anterior sem overflow; probe atual do carrinho foi encerrado com `exit 137` antes de gerar metricas.
- Mobile: PDP passou com header `141px`, busca `44px`, nav desktop oculta e overflow horizontal `0px`.
- Menu vertical: painel existente, visivel no hover, `overflow-y:auto`, sem overflow horizontal.
- Estabilidade temporal: desktop revalidado apos 9s por pagina sem retornar para as medidas antigas; terminal style/script ativos e critical antigo ausente.

Metricas especificas dos CTAs dos cards:

| Viewport | CTAs encontrados | Altura | Fundo transparente | Borda ausente | Pseudo-elementos | Overflow horizontal |
|---|---:|---:|---:|---:|---|---:|
| 1440x1000 | 20 | 44px | 0 | 0 | cadeado em `a::before` 14px | 0px |
| 390x1000 | 20 | 44px | 0 | 0 | cadeado em `a::before` 14px | 0px |

Validacao DOM dos CTAs:

- `.content-top-home .b2b-login-to-see-price .price-label`: 0 ocorrencias.
- `.content-top-home .b2b-login-to-see-price.price-box > a.b2b-login-link`: 20 ocorrencias.
- `.content-top-home .b2b-login-to-see-price.price-box`: fundo transparente, borda `0px`, sombra `none`, padding `0px`.
- `.content-top-home .awa-b2b-sku`: borda `0px`, padding `0px`, altura aproximada `13px`, gap interno `3px`.
- `.content-top-home .product-info`: gap reduzido para `4px`; `info-price` com padding superior `4px`.
- `.content-top-home .product-thumb`: padding e borda interna removidos; link/wrappers/imagem ocupam `100%` da area.
- `.content-top-home .product-thumb .hot-onsale`: oculto no thumbnail para nao disputar espaco com a foto.
- `.content-top-home .product-thumb img.product-image-photo`: `width:100%`, `height:100%`, `object-fit:contain`, imagem centralizada.
- Carrosseis da home corrigidos:
  - `awa-scroll-carousel.min.js` passou a carregar automaticamente na home pelo loader oficial, sem depender de clique/toque/tecla.
  - `.awa-owl-nav__btn.awa-carousel__arrow` foi movido para o header/host correto de cada vitrine pelo runtime `awa-scroll-carousel.js`.
  - Slot invisivel `.awa-owl-nav--header-slot` removido do fluxo visual.
  - Controles padronizados com alvo real de 44px, circulo visual de 38px desktop e 34px mobile, alinhados a direita, sem overlay nos cards.
  - Autoplay proprio no runtime oficial com pausa em hover/foco/toque, `IntersectionObserver` e respeito a `prefers-reduced-motion`.
  - Controle acessivel de pausar/retomar autoplay incluido no host do carrossel, com `aria-pressed`, `aria-label` dinamico e icone limpo.
  - Slides recebem `role="group"`, `aria-roledescription`, `aria-label` posicional e `aria-current` no item ativo.
  - Itens de slides fora da viewport saem da tabulacao (`tabindex=-1`) e sao restaurados quando voltam a ficar visiveis.
  - Snap refinado: passo por card/grupo previsivel, desktop em `scroll-snap` horizontal e mobile em `x mandatory`.
  - Barra de progresso discreta por vitrine, com `aria-valuetext` no formato `Item X de 12`.
  - Skeleton leve para slots de imagem em estado `awa-carousel-pending`, sem spinner central.
  - Host do carrossel passa a marcar `has-carousel-overflow` e `is-awa-not-scrollable`, removendo a reserva de espaco quando nao houver overflow real.
  - Preload leve somente da proxima imagem provavel, com limite interno estrito de 16 imagens e `fetchPriority=low` quando suportado.
  - Eventos frontend de analytics emitidos via `awa:carousel:analytics` e `dataLayer` para pause, resume, next, prev, swipe, impression, product_click e view_all_click.
  - Estados visuais que antes dependiam de inline style no runtime foram migrados para classes/atributos: `awa-carousel-mounted`, `is-awa-viewport-width-guarded`, `[hidden]` em nav/progresso.
  - `scan()` do runtime original passou a ser executado uma unica vez para evitar recursao/stack overflow.
  - Quantidade do trilho das vitrines aumentada para 12 itens, mantendo 5 visiveis no desktop por CSS.
  - Abas de "Linhas em destaque" tambem recebem nav no host do painel ativo e recalculam ao trocar aba.
  - Fallback terminal de carrossel deixou de ser emitido na home normal quando o loader oficial esta presente.
  - CSS final do host/setas consolidado em `awa-home-body-end-bundle.min.css?v=20260622-carousel-pro-controls-v4`.
  - Regras oficiais tambem registradas em `_awa-shelf-carousel.less` e `awa-shelf-carousel.min.css`.
  - CSS oficial do shelf versionado em `awa-shelf-carousel.min.css?v=20260622-carousel-css-pro-v4`.
  - Runtime atualizado para `awa-scroll-carousel.min.js?v=20260622-carousel-runtime-v7`.
  - Brotli stale de JS/CSS regenerado para nao servir assets antigos.
  - Validado sem o `<style id="awa-home-carousel-motion-terminal-20260622">`: setas continuam corretas por CSS/JS oficiais.

Metricas especificas dos carrosseis:

| Viewport | Slides por vitrine | Overflow real | Autoplay | Setas | Overflow horizontal | Console |
|---|---:|---:|---|---|---:|---|
| 1920x1400 | 12 | 1747-1823px | 28px -> 238/282px | 38px, direita do host | 0px | sem erros |
| 390x844 | 12 | 1580-1912px | 0/28px -> 178/186px | 34px, direita do host | 0px | sem erros |

Validacao especifica do runtime oficial em 2026-06-22:

| Viewport | Script servido | Movimento | Setas | Overflow horizontal | Console |
|---|---|---|---|---:|---|
| 1920x1400 | `awa-scroll-carousel.min.js?v=20260622-carousel-runtime-v7` | autoplay e seta ativos | alvo 44px, visual 38px, `navRightGap=0` | 0px | sem erros |
| 390x844 | `awa-scroll-carousel.min.js?v=20260622-carousel-runtime-v7` | snap mobile ativo | alvo 44px, visual 34px, `navRightGap=0` | 0px | sem erros |
| 1440x1200, aba niche 2 | `awa-scroll-carousel.min.js?v=20260622-carousel-runtime-v7` | painel ativo pronto | nav no host do painel, `navRightGap=0` | 0px | sem erros |
| 1366x900 runtime v7 | `awa-scroll-carousel.min.js?v=20260622-carousel-runtime-v7` | next `0px -> 238px`; slides ocultos sem tabulacao | analytics `next/impression`, 1 slide atual | 0px | sem erros |
| 390x844 runtime v7 | `awa-scroll-carousel.min.js?v=20260622-carousel-runtime-v7` | next `0px -> 166px`; slides ocultos sem tabulacao | 3 slides visiveis, 9 ocultos, 0 tabulaveis ocultos | 0px | sem erros |

Validacao especifica da camada CSS oficial em 2026-06-22:

| Viewport | CSS servido | Terminal inline removido no probe | Nav | Setas | Overflow horizontal | Console |
|---|---|---|---|---|---:|---|
| 1366x900 | `awa-home-body-end-bundle.min.css?v=20260622-carousel-pro-controls-v4` | nao emitido no HTML final; nav/progresso/mount sem `style` inline | `position:absolute`, `hostPaddingRight=150px` | alvo 44px, visual 38px | 0px | sem erros |
| 390x844 | `awa-home-body-end-bundle.min.css?v=20260622-carousel-pro-controls-v4` | nao emitido no HTML final; nav/progresso/mount sem `style` inline | `position:absolute`, `hostPaddingRight=146px` | alvo 44px, visual 34px | 0px | sem erros |

Validacao de movimento apos a camada CSS oficial:

| Acao | Resultado |
|---|---|
| Clique na seta proxima | `scrollLeft: 0 -> 238px` |
| Autoplay apos alguns segundos | `scrollLeft: 28px -> 0px` no probe sem interacao |
| Carga sem bloqueio artificial de assets | console sem erros |

Arquivos alterados ate aqui:

- `app/code/GrupoAwamotos/Theme/Plugin/Response/OptimizeHeadStylesPlugin.php`
- `app/code/GrupoAwamotos/Theme/Plugin/Response/DeferHomeScriptsPlugin.php`
- `app/code/GrupoAwamotos/Theme/Model/HeaderImpeccableCascadeLockCss.php`
- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Cms/templates/top-home.phtml`
- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-head-preload.phtml`
- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-shelf-carousel-loader.phtml`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_extend.less`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-layout-final-cleanups-2026-06.less`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-header-vtex-clean-2026-06.less`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-shelf-carousel.less`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-shelf-carousel.min.css`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-home-body-end-bundle.css`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-home-body-end-bundle.min.css`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-menu-controller.js`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-scroll-carousel.js`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-scroll-carousel.min.js`
- `docs/PLANO_CORRECAO_LAYOUT_FASES.md`
- `tests/e2e/specs/header-all-pages-audit.spec.ts`

Validacoes executadas:

```bash
php -l app/code/GrupoAwamotos/Theme/Plugin/Response/OptimizeHeadStylesPlugin.php
php -l app/code/GrupoAwamotos/Theme/Plugin/Response/DeferHomeScriptsPlugin.php
php -l app/code/GrupoAwamotos/Theme/Model/HeaderImpeccableCascadeLockCss.php
php -l app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-head-preload.phtml
node node_modules/less/bin/lessc app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_extend.less /tmp/awa-extend-header-clean-check.css
php -l app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-shelf-carousel-loader.phtml
node --check app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-scroll-carousel.js
node --check app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-scroll-carousel.min.js
node node_modules/terser/bin/terser app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-scroll-carousel.js --compress --mangle --output app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-scroll-carousel.min.js
node node_modules/less/bin/lessc app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-shelf-carousel.less /tmp/awa-shelf-carousel-check.css
brotli -f -q 11 pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/js/awa-scroll-carousel.min.js
sudo -u www-data php bin/magento cache:clean block_html full_page
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 2 FLUSHDB
sudo systemctl reload php8.4-fpm
```


Validacoes adicionais da continuidade menu/CTA/footer:

```bash
php -l app/design/frontend/AWA_Custom/ayo_home5_child/Rokanthemes_Themeoption/templates/html/footer.phtml
node --check app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-footer-interactions.js
node node_modules/less/bin/lessc app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_extend.less /tmp/awa-extend-footer-structural-v7-check.css
php -l app/code/GrupoAwamotos/Theme/Plugin/Response/OptimizeHeadStylesPlugin.php
node --check app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-menu-controller.js
node node_modules/less/bin/lessc app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_extend.less /tmp/awa-extend-final-pass-check.css
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
php bin/magento cache:clean block_html full_page layout
sudo systemctl reload php8.4-fpm
```

Resultados medidos nesta continuidade:

| Pagina/viewport | Resultado |
|---|---|
| PLP desktop 1365px apos 9s | Header `156px`, `Departamentos` `206x44px`, CTAs B2B `44px`, footer `1407px`, footer-bottom `199px`, overflow horizontal `0` |
| PLP mobile 390px apos 9s | Header `141px`, CTAs B2B `44px`, footer `1464px`, trust bar `290px`, footer-container `488px`, categorias `64px`, footer-bottom `470px`, devby `64px`, overflow horizontal `0` |
| Menu desktop PLP | Clique abre painel ~`304px`, `aria-expanded=true`, `Esc` fecha |
| Menu mobile 390px | Drawer lateral ~`335px`, overlay ativo, overflow horizontal `0` |

Validacoes adicionais desta auditoria do header:

```bash
php -l app/code/GrupoAwamotos/Theme/Plugin/Response/OptimizeHeadStylesPlugin.php
node node_modules/less/bin/lessc app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_extend.less /tmp/awa-extend-header-audit-check.css
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
php bin/magento cache:clean block_html full_page layout
sudo systemctl reload php8.4-fpm
env AWA_HEADER_AUDIT_VIEWPORT=desktop AWA_HEADER_AUDIT_PAGE=home npx playwright test tests/e2e/specs/header-all-pages-audit.spec.ts --project=chromium --reporter=line --trace=off
env AWA_HEADER_AUDIT_VIEWPORT=desktop AWA_HEADER_AUDIT_PAGE=plp-bauletos npx playwright test tests/e2e/specs/header-all-pages-audit.spec.ts --project=chromium --reporter=line --trace=off
env AWA_HEADER_AUDIT_VIEWPORT=desktop AWA_HEADER_AUDIT_PAGE=search npx playwright test tests/e2e/specs/header-all-pages-audit.spec.ts --project=chromium --reporter=line --trace=off
env AWA_HEADER_AUDIT_VIEWPORT=desktop AWA_HEADER_AUDIT_PAGE=pdp npx playwright test tests/e2e/specs/header-all-pages-audit.spec.ts --project=chromium --reporter=line --trace=off
env AWA_HEADER_AUDIT_VIEWPORT=desktop AWA_HEADER_AUDIT_PAGE=cart npx playwright test tests/e2e/specs/header-all-pages-audit.spec.ts --project=chromium --reporter=line --trace=off
env AWA_HEADER_AUDIT_VIEWPORT=desktop AWA_HEADER_AUDIT_PAGE=not-found npx playwright test tests/e2e/specs/header-all-pages-audit.spec.ts --project=chromium --reporter=line --trace=off
env AWA_HEADER_AUDIT_VIEWPORT=mobile AWA_HEADER_AUDIT_PAGE=pdp npx playwright test tests/e2e/specs/header-all-pages-audit.spec.ts --project=chromium --reporter=line --trace=off
```

Observacao operacional:

- O spec completo `header-home-layout.spec.ts` e a execucao completa de `header-all-pages-audit.spec.ts` foram encerrados com `exit 137` na VPS. Preferir probes Playwright isolados por viewport/pagina ate reduzir consumo de memoria.
- `scripts/e2e-cleanup.sh` limpa `tests/e2e` de forma agressiva; quando usado por engano nesta rodada, os arquivos rastreados deletados foram restaurados com `git restore` apenas para os paths apagados.

## Principios de correcao

- Corrigir a causa raiz antes de adicionar novas camadas visuais.
- Manter o design system AWA existente e usar tokens `var(--awa-*)`.
- Nao editar `vendor`, core Magento ou `app/code/Rokanthemes/*`.
- Evitar CSS inline em PHTML.
- Evitar hex hardcoded e `!important` novo fora de camada terminal justificada.
- Validar desktop, tablet e mobile: 1366, 768 e 390px.
- Preservar performance: nao reintroduzir bundles pesados no primeiro paint.

## Legenda de status

| Status | Significado |
|---|---|
| Implementado | Correção aplicada e validada no estado atual. |
| Parcial | Correção aplicada em parte, em camada terminal ou faltando migracao/validacao ampla. |
| Nao implementado | Ainda nao foi feito. |

Marcadores usados nas tarefas: `[x]` implementado, `[~]` parcial, `[ ]` nao implementado.

## Status por fase

| Fase | Area | Status | Observacao |
|---:|---|---|---|
| 0 | Diagnostico e baseline | Parcial | Probes atuais executados; falta baseline formal em specs. |
| 1 | Header e busca | Implementado | Header desktop refeito e validado: barra B2B sem corte, grid principal alinhado, busca limpa, conta B2B sem `OLA!` solto, carrinho 44px e responsivo sem overflow. |
| 2 | Navegacao e departamentos | Parcial | Clique, teclado basico, hover tolerante e drawer mobile implementados; ainda faltam submenus de segundo nivel, analytics e auditoria completa multi-pagina/mobile. |
| 3 | Conteudo principal | Implementado | Home nao colapsa mais para o footer. |
| 4 | Footer estrutural | Implementado parcial | Desktop/mobile compactados e sem overflow; ainda ha oportunidade futura de reduzir newsletter/trust/footer-bottom por refatoracao PHTML mais profunda. |
| 5 | Cards, CTAs e alvos clicaveis | Parcial | Home e PLP corrigidos; PDP/carrinho sem lista de cards equivalente na auditoria atual, ainda falta matriz completa de contextos. |
| 6 | Carrosseis de vitrines | Parcial | Movimento/setas/autoload, analytics frontend e CSS final do host migrados para camadas oficiais; ainda falta reduzir especificidade, validar rede lenta e merchandising real. |
| 7 | PDP e galeria | Nao implementado | Ainda requer rodada dedicada. |
| 8 | Catalogo e paginas institucionais | Nao implementado | Ainda requer rodada dedicada. |
| 9 | Consolidacao definitiva em LESS/JS | Parcial | LESS do header e carrosseis consolidado; plugin terminal ainda necessario para vencer locks inline legados do header e deve ser reduzido em rodada propria. |
| 10 | Regressao visual e performance | Parcial | Probes isolados OK; specs completas ainda nao estabilizadas na VPS. |
| 11 | Limpeza de legado e conflitos | Nao implementado | Nova fase: remover dependencias antigas, reduzir `!important`, retirar terminais redundantes e consolidar header/footer/cards/carrosseis em PHTML/LESS/JS oficiais. |
| 12 | Auditoria profunda do header | Documentado | Erros restantes catalogados por pagina, viewport, cascata, acessibilidade e performance; correcoes estruturais ainda pendentes. |

## Prioridade atual

1. Limpeza controlada de legado: inventariar e remover apenas uma camada conflitante por rodada, com probe antes/depois.
2. Footer estrutural definitivo: migrar regras estaveis do terminal para PHTML/LESS e reduzir dependencia de `vela*`, Bootstrap grid e JS corretivo.
3. Header definitivo: manter o visual atual, mas migrar locks `terminal/critical` para LESS oficial e reduzir observadores/timers.
4. Header auditoria profunda: corrigir DOM duplicado, alvos menores que 44px, overflow tecnico mobile/tablet e divergencias entre home/PLP/carrinho.
5. Cards/CTAs: remover wrappers visuais antigos (`price-label`, fundos de `price-box`, pseudo-elementos duplicados) por template/LESS, nao por patch tardio.
6. Carrosseis: manter runtime oficial e remover fallback terminal quando os specs cobrirem desktop/mobile/rede lenta.
7. Validacao responsiva: rodar probes isolados para desktop, tablet e mobile devido ao limite de memoria da VPS.

## Inventario de legado e conflito ainda ativo

Este inventario orienta a limpeza. Nada deve ser removido sem validacao visual isolada antes/depois.

| Area | Legado/conflito | Risco atual | Acao correta | Status |
|---|---|---|---|---|
| Header | `injectHeaderVtexCleanTerminal()`, `HeaderImpeccableCascadeLockCss`, critical head e timers por ate 70s | Layout pode voltar para medidas antigas se um lock falhar ou carregar fora de ordem | Migrar medidas finais para `_awa-header-vtex-clean-2026-06.less`, reduzir terminal para fallback temporario e depois remover | Nao implementado |
| Header | Seletores com `html body#html-body` repetido e `!important` em massa | Dificulta manutencao e mascara conflitos reais do tema pai | Trocar por seletores de componente com classe raiz e tokens | Nao implementado |
| Footer | Classes Rokanthemes/legadas `velaFooterTitle`, `velaContent`, `rowFlexMargin`, Bootstrap `row/col-*` junto com `awa-*` | Altura artificial, duplicidade de accordion e necessidade de JS corretivo | Refatorar PHTML do footer para estrutura AWA unica e LESS duravel | Parcial |
| Footer | Regras finais em `globalLayoutFinalCleanCss()` e script terminal com `MutationObserver` | Footer depende de correção tardia, nao apenas do CSS oficial | Migrar regras estaveis para LESS e remover script por etapas | Parcial |
| Cards/CTAs | `price-label`, `price-box`, `b2b-login-to-see-price`, pseudo cadeado e wrappers de preco B2B | Fundo duplo pode voltar em novos blocos de produto | Padronizar template/helper B2B e manter um unico contrato visual de CTA | Parcial |
| Carrosseis | Mistura de nomes `owl`, `swiper`, `awa-scroll-carousel`, fallback terminal e CSS body-end | Risco de dois runtimes disputarem setas/autoplay | Manter runtime AWA oficial, remover fallback terminal apos specs passarem | Parcial |
| CSS global | `_extend.less` historico com muitos imports comentados, camadas `PORTED`, `terminal`, `final-wins` | Dificulta saber qual camada e proprietaria de cada area | Criar mapa de ownership por area e podar camadas mortas uma por vez | Nao implementado |
| Breakpoints | Mistura `575/767/768/991/992` | Regressao em tablet e faixas intermediarias | Consolidar tokens de breakpoint e revisar media queries finais | Nao implementado |
| Assets Magento | `pub/static`, `var/view_preprocessed`, Brotli e cache-busters manuais | Alteracao pode parecer corrigida e voltar por asset stale | Checklist obrigatorio de deploy/cache/preprocessed por tipo de arquivo | Parcial |
| Performance | Muitos estilos inline/terminais e JS de re-aplicacao | Aumenta HTML, bloqueia manutencao e pode afetar PSI | Mover para CSS/JS versionado e medir peso por pagina | Nao implementado |

## Fase 11 — Limpeza de legado e conflitos

Status: Implementado parcial em 2026-06-22.

Objetivo: transformar as correcoes visuais atuais em arquitetura limpa, previsivel e sustentavel, removendo camadas antigas apenas quando a pagina continuar igual ou melhor nos probes.

Regras da fase:

1. Nunca remover duas camadas conflitantes na mesma rodada.
2. Antes de remover, medir pagina alvo em desktop, tablet e mobile quando aplicavel.
3. Depois de remover, repetir as mesmas metricas e comparar: header, maincontent, footer, overflow horizontal, console e principais CTAs.
4. Se houver regressao visual, restaurar a camada removida e documentar a causa.
5. Migrar primeiro para LESS/PHTML/JS oficial do tema filho; remover terminal somente depois.
6. Nao editar `app/code/Rokanthemes/*`, `vendor` ou core Magento.
7. Manter tokens `var(--awa-*)` e evitar hex hardcoded.
8. Reduzir `!important` somente quando a regra oficial ja estiver vencendo a cascata.

Ordem de limpeza recomendada:

1. Footer PHTML/LESS: substituir estrutura mista `vela*`/Bootstrap por estrutura AWA unica, mantendo conteudo e schema.
2. Footer terminal: remover do `globalLayoutFinalCleanCss()` apenas regras ja cobertas por LESS e validar PLP/home/PDP mobile e desktop.
3. Header LESS: mover medidas finais do `injectHeaderVtexCleanTerminal()` para `_awa-header-vtex-clean-2026-06.less` com seletor menor.
4. Header terminal: reduzir timers/MutationObserver para fallback curto; depois remover se specs multi-pagina passarem.
5. Cards B2B: limpar template/helper para gerar um unico CTA sem `price-label` visual e sem fundo de `price-box`.
6. Carrossel: remover fallback `injectHomeCarouselMotionTerminal()` quando loader oficial e CSS versionado cobrirem todos os estados.
7. `_extend.less`: agrupar imports ativos por ownership e remover comentarios/camadas historicas que nao carregam mais.
8. Breakpoints: criar contrato unico para `mobile <=767`, `tablet 768-991`, `desktop >=992` e substituir excecoes soltas.
9. Assets/cache: documentar e automatizar deploy minimo por tipo de alteracao: LESS, PHTML, JS, minificado e Brotli.
10. Specs: criar probes pequenos por area para evitar `exit 137` na VPS e bloquear regressao antes de nova limpeza.

Implementado nesta rodada da Fase 11:

- Regra estrutural adicionada em `HeaderImpeccableCascadeLockCss::footerStructuralContainmentRules()` e chamada antes das regras visuais do `footerTerminalRules()`.
- A mesma regra foi espelhada no LESS final `_awa-layout-final-cleanups-2026-06.less` e no bundle terminal `awa-commerce-impeccable-refine` para cobrir outras rotas/cascatas.
- `REFINE_QUERY` atualizado para `?v=20260622-footer-structural-cleanup-v1`.
- `content-visibility:auto`, `contain` e `contain-intrinsic-size` deixam de gerar altura fantasma em `.page_footer`, `#footer`, `.footer-bottom`, `.awa-footer-categories-expand` e `.awa-footer-devby`.
- `page_footer` passa a usar `display:flow-root`, contendo floats/filhos sem depender de altura calculada por JS.
- Cache operacional confirmado: URL limpa continuava antiga por Varnish, nao por Redis FPC; purge funcional via `PURGE` local com `X-Magento-Tags-Pattern: .*`.
- Metricas PLP apos purge: desktop `1365x900` footer `1235px`, categorias `66px`, devby `56px`, overflow `0`; mobile `390x844` footer `1411px`, categorias `58px`, devby `61px`, overflow `0`.

Checklist de aceite da limpeza:

- [ ] Header desktop continua `156px` apos 9s em home, PLP, busca, PDP, carrinho e 404.
- [ ] Header mobile continua sem nav desktop vazando e sem overflow horizontal.
- [x] Footer PLP desktop continua menor que `1407px`: medido `1235px`, sem corte e sem overflow.
- [x] Footer PLP mobile continua menor que `1464px`: medido `1411px`, com categorias colapsadas em `58px`.
- [ ] CTAs B2B mantem `44px`, fundo transparente, borda `0px` e cadeado alinhado.
- [ ] Carrosseis mantem autoplay, pausa, setas 44px, progresso, acessibilidade e `overflowX=0`.
- [ ] Console sem erros JS novos.
- [ ] `php -l` nos PHP/PHTML alterados.
- [ ] `lessc` em `_extend.less` quando LESS for alterado.
- [ ] `node --check` nos JS alterados.
- [ ] `setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child` apos LESS/JS/PHTML que afete static.
- [ ] `cache:clean block_html full_page layout` e, quando necessario, sync de `var/view_preprocessed`.

Arquivos mais provaveis na limpeza:

- `app/code/GrupoAwamotos/Theme/Plugin/Response/OptimizeHeadStylesPlugin.php`
- `app/code/GrupoAwamotos/Theme/Model/HeaderImpeccableCascadeLockCss.php`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_extend.less`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-header-vtex-clean-2026-06.less`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-layout-final-cleanups-2026-06.less`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-shelf-carousel.less`
- `app/design/frontend/AWA_Custom/ayo_home5_child/Rokanthemes_Themeoption/templates/html/footer.phtml`
- `app/design/frontend/AWA_Custom/ayo_home5_child/Rokanthemes_Themeoption/templates/html/footer/footer-static5.phtml`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-footer-interactions.js`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-scroll-carousel.js`


## Fase 0 — Diagnostico e congelamento de baseline

Status: Parcial.

Objetivo: confirmar se o problema e layout, cache, conteudo colapsado ou asset stale.

Tarefas:

1. Capturar screenshots atuais em 1366, 768 e 390px.
2. Confirmar `fullActionName` da pagina afetada.
3. Verificar se `maincontent` existe e qual altura computada possui.
4. Verificar se ha erro JS que impede widgets/blocos de renderizarem.
5. Verificar requests 404/blocked para CSS, JS e imagens.
6. Confirmar quais folhas CSS vencem a cascata no header/footer.
7. Registrar metricas antes/depois:
   - `document.body.scrollWidth - document.documentElement.clientWidth`
   - altura do header
   - altura do `maincontent`
   - altura do footer
   - elementos com `scrollHeight > clientHeight`

Validacao:

```bash
tail -20 var/log/system.log
tail -20 var/log/exception.log
cd tests/e2e && npx playwright test specs/category-plp.spec.ts --workers=1
```

## Fase 1 — Header e busca

Objetivo: transformar o header em uma linha funcional, compacta e alinhada.

Status: Implementado no estado visual atual para paginas publicas auditadas. Header e busca foram revalidados em desktop multi-pagina e mobile PDP sem overflow horizontal; auditoria tablet/B2B completa segue parcial por limite de memoria da VPS. A consolidacao definitiva para remover locks inline legados continua na Fase 9.

Problemas corrigidos nesta fase:

- Header alto demais.
- Logo solto no vazio.
- Botao de busca caindo abaixo do input.
- Busca sem composicao unificada.
- Bloco B2B quebrando texto de forma ruim.
- Minicart desalinhado.

Problemas visuais do print original tratados nesta fase:

- Barra vermelha superior cortada no topo.
- Texto da barra superior quase invisivel ou cortado.
- Botao/icone da barra superior a direita parcialmente cortado.
- Header ainda passa sensacao de espaco morto entre linha principal e menu.
- Logo pequeno demais para o espaco que ocupa.
- Logo sem alinhamento optico forte com a busca.
- Busca muito larga sem equilibrio com conta/carrinho.
- Icone `x` da busca muito proximo do botao vermelho.
- Botao de busca cria peso visual excessivo no canto direito do input.
- Bloco `Entrar ou Cadastrar` com hierarquia confusa.
- Texto `OLA!` fica solto abaixo do login, parecendo orfao.
- Icone de usuario distante do texto.
- Conta/login parece texto solto, nao um componente de acao B2B.
- Carrinho pesa mais visualmente que conta/login.
- Espacamento entre conta e carrinho irregular.
- Linha divisoria abaixo do header esta ruidosa.
- Botao `Departamentos` ainda parece desconectado da busca.
- Botao `Departamentos` pesado demais em relacao aos links do menu.
- Links `Nossas Marcas`, `Lancamentos`, `Catalogo` ficam soltos em area larga.
- Fundo rosado da nav cria segunda faixa visual pesada.
- Header parece composto por blocos independentes, nao por um sistema unico.
- Hierarquia de prioridade ainda nao esta clara: busca, departamentos, conta, carrinho.
- Acabamento ainda nao atinge o padrao clean esperado de grandes plataformas.

Plano por subfases do header:

| Subfase | Area | Status | Correcao esperada |
|---|---|---|---|
| H1 | Barra superior B2B | Implementado | Desktop validado com barra `32px`, CTA `32px`, botao fechar `44x32px`, texto branco e sem clipping. |
| H2 | Linha principal | Implementado | Linha principal desktop validada em `76px`, miolo `64px`, logo/busca/conta/carrinho no mesmo eixo. |
| H3 | Busca | Implementado | Busca validada com form `44px`, `x` transparente em desktop/tablet/mobile e botao vermelho preservado. |
| H4 | Conta/login B2B | Implementado | Estado guest/customer sincronizado; guest exibe `PREÇOS B2B Entrar ou Cadastrar` sem `OLA!` visivel solto. |
| H5 | Carrinho | Implementado | Carrinho validado como acao compacta `44x44px`, texto/icone branco e alinhado ao bloco de conta. |
| H6 | Nav/departamentos | Implementado | Nav desktop branca, `Departamentos` `206x44px` revalidado em home, PLP, busca, PDP, carrinho e 404; PDP/cart corrigidos contra `left:-9999px` e `display:none`. |
| H7 | Responsivo | Parcial | Mobile PDP validado sem overflow; auditoria tablet e auth B2B completa nao foi concluida nesta rodada por `exit 137`, mas a regra nova so aplica em desktop `>=992px`. |
| H8 | Consolidacao | Parcial | Regras consolidadas no LESS e critical head alinhado; terminal plugin ainda mantido por 70s para vencer estilos inline `!important` legados e overrides tardios. |

Tarefas:

1. [x] Definir grid desktop do header:
   - logo: largura fixa compacta;
   - busca: `minmax(420px, 1fr)`;
   - conta/carrinho: coluna fixa de acoes.
2. [x] Garantir que `#search_mini_form` seja uma linha unica.
3. [x] Acoplar visualmente input e botao de busca.
4. [x] Centralizar verticalmente logo, busca, B2B e minicart.
5. [x] Reduzir altura desktop do header para `156px`.
6. [x] Garantir targets de 44px para conta, login, cadastro, carrinho e Departamentos.
7. [x] Ajustar tablet para evitar quebra da barra de busca e fundo vermelho no `x`.
8. [x] Ajustar mobile sem aumentar CLS nem aplicar medidas desktop.
9. [~] Consolidar/remover locks inline legados em rodada separada para reduzir o plugin terminal.
10. [x] Ajustar lock runtime v18 do header para seguir o contrato final de alturas (156/124/64) e componentes 44/48, evitando retorno visual após alguns segundos.

Arquivos provaveis:

- `app/code/GrupoAwamotos/Theme/Plugin/Response/OptimizeHeadStylesPlugin.php`
- `app/code/GrupoAwamotos/Theme/Model/HeaderImpeccableCascadeLockCss.php`
- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-head-preload.phtml`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_extend.less`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-header-vtex-clean-2026-06.less`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-search-header-harden.less`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-universal-shell-2026-06.less`

Validacao:

- Busca em uma unica linha.
- Header sem espaco morto acima/abaixo.
- `button.action.search` com 44px.
- `awa-header-account-prompt` sem overflow interno.
- Minicart alinhado ao centro do header.
- Barra superior sem corte em 1366, 768 e 390px.
- Conta/login sem texto orfao e sem desalinhamento entre icone e links.
- Nav sem segunda faixa pesada e sem links soltos no vazio.

Resultado atual:

- Desktop: header publico estabilizado em `156px`; home, PLP, busca, PDP, carrinho e 404 passaram no spec isolado apos 9s.
- PDP: `Departamentos` corrigido de `left:-9999px` para posicao visivel no eixo da nav.
- Carrinho: `Departamentos` corrigido de `display:none` para bloco visivel `206x44px`.
- Mobile PDP: header `141px`, busca `44px`, nav desktop oculta, overflow horizontal `0px`.
- Tablet: auditoria anterior sem overflow; probe atual do carrinho foi encerrado por OOM, entao o status responsivo amplo permanece parcial.
- Pendente: overflow tecnico em `.header_main` no tablet por conteudo B2B oculto; nao gera corte visual, mas deve ser limpo na migracao LESS.

### Diretriz clean para header no padrao de grandes plataformas como VTEX

Objetivo: deixar o header com leitura de ecommerce profissional: logo, busca, conta B2B e carrinho em uma composicao densa, previsivel, sem espaco morto e sem aparencia de blocos soltos.

Checklist de implementacao:

- [x] Busca desktop em uma linha, com input e botao formando um unico controle funcional.
- [x] Logo, busca, conta B2B e minicart centralizados visualmente no desktop.
- [~] Header compacto em desktop e mobile PDP; ainda falta completar matriz tablet/mobile multi-pagina e remover overflow tecnico tablet.
- [~] Sticky header funcional; ainda falta validar CLS e diferenca visual entre estado normal e sticky.
- [ ] Migrar regras finais de `HeaderImpeccableCascadeLockCss` para LESS do tema filho.
- [ ] Definir altura alvo por faixa:
  - desktop: header principal entre 72px e 88px, sem faixa vazia;
  - tablet: header sem quebra de busca e sem prompt B2B oculto causando overflow tecnico;
  - mobile: busca com 44px e acoes principais sem apertar o logo.
- [ ] Transformar o bloco B2B em chip/area compacta de conta, sem parecer card isolado.
- [ ] Padronizar login/cadastro/minicart como grupo de acoes com mesmo eixo vertical, altura e raio.
- [ ] Garantir que o carrinho tenha area clicavel minima de 44x44px e icone alinhado ao centro optico.
- [ ] Ajustar logo para area fixa e previsivel, sem deslocar busca em breakpoints intermediarios.
- [ ] Deixar a busca como elemento dominante do header, com largura fluida e max-width controlado.
- [ ] Corrigir estado de foco da busca, botao, login, cadastro e minicart com outline visivel.
- [ ] Prever estados de busca:
  - vazio;
  - digitando;
  - carregando sugestoes;
  - sem resultados;
  - resultados com teclado.
- [ ] Garantir que sugestoes/autocomplete abram abaixo da busca sem serem cortadas por `overflow:hidden`.
- [ ] Eliminar CSS duplicado ou conflitante entre `awa-bundle-core`, `awa-bundle-refinements` e LESS compilado.
- [~] Reduzir dependencia de ajustes tardios; lock runtime ainda atua como fallback protegido enquanto alguns contratos ficam sendo migrados para LESS, com plano de retirada na Fase 9.
- [ ] Validar sem service worker/cache antigo para confirmar que FPC nao mascara HTML/CSS stale.

Criterios de aceite do header clean:

- Header nao deve parecer uma area vazia com itens espalhados.
- Busca deve ser o centro funcional da composicao.
- Acoes B2B e carrinho devem parecer componentes da mesma familia visual.
- Nenhum item deve quebrar linha entre 1024px e 1366px.
- Nao pode haver overflow horizontal em 390px, 768px, 1024px, 1366px e 1920px.
- Sticky header deve manter o mesmo alinhamento sem salto visual.
- A primeira dobra deve mostrar rapidamente hero/conteudo, nao uma massa de header.

## Fase 2 — Navegacao e departamentos

Status: Parcial. Visual desktop, clique, teclado basico e drawer mobile corrigidos; submenus profundos/analytics ainda pendentes.

Objetivo: alinhar a barra de navegacao com o grid do header e remover desconexao visual.

Problemas a corrigir:

- Barra de navegacao desalinhada lateralmente.
- Botao “Departamentos” largo demais.
- Links de navegacao comprimidos ou distantes.
- Fundo rosado sem hierarquia clara.

Tarefas:

1. Alinhar container da nav ao mesmo max-width do header.
2. Definir largura do botao departamentos por breakpoint.
3. Garantir 44px de altura nos links da nav.
4. Em tablet, reduzir largura de departamentos para abrir espaco aos links.
5. Evitar quebra de linha dentro de `.awa-nav-quick-links`.
6. Confirmar que dropdown de departamentos nao fica cortado por `overflow:hidden`.

Validacao:

- `.awa-nav-bar__inner` sem `scrollWidth` maior que a caixa.
- Links “Nossas Marcas”, “Lancamentos” e “Catalogo” com 44px.
- Botao departamentos alinhado com o grid do header.

Resultado atual em 2026-06-22:

- Desktop PLP: ao clicar em `Departamentos`, painel abre com `aria-expanded=true`, `data-awa-menu-state=open`, largura aproximada `304px`, `max-height` com scroll interno e overflow horizontal `0`.
- `Esc` fecha o painel e restaura `aria-expanded=false`.
- Mobile 390px: acionamento abre drawer lateral de aproximadamente `88vw`/`335px`, overlay ativo, header do drawer presente e overflow horizontal `0`.
- Ainda pendente: submenu de segundo nivel, analytics de menu, auditoria completa em PDP/carrinho/checkout e refinamento do fluxo de foco dentro de categorias profundas.

### Diretriz clean para menu vertical/departamentos no padrao de grandes plataformas como VTEX

Objetivo: transformar `Departamentos` em uma entrada clara de catalogo, com menu vertical rapido, escaneavel, acessivel e consistente com a busca. O comportamento esperado e de ecommerce maduro: abrir rapido, manter contexto, nao cobrir de forma confusa, nao empurrar layout e nao cortar submenus.

Checklist de implementacao:

- [x] Botao `Departamentos` existe e esta alinhado visualmente com a nav em desktop publico.
- [x] Definir largura fixa e consistente do botao em desktop (`206px`).
- [x] Usar icone de menu alinhado ao texto no alvo de 44px.
- [x] Criar estado ativo do botao quando o menu estiver aberto (`aria-expanded=true`).
- [x] Abrir menu abaixo do botao, preso ao mesmo eixo do container do header/nav no desktop.
- [x] Garantir que o menu nao seja cortado por `overflow:hidden`, `transform` ou stacking context do header no estado visual atual.
- [x] Definir painel vertical de categorias com largura aproximada de 304px no desktop.
- [x] Definir linhas de categoria com altura de 40px a 44px, texto alinhado e target clicavel inteiro.
- [x] Evitar lista alta demais sem controle; usar `max-height:min(70vh,560px)` e rolagem interna.
- [ ] Criar painel de segundo nivel ao lado direito para subcategorias, marcas ou linhas principais.
- [ ] Manter caminho de mouse tolerante entre coluna principal e painel secundario, sem fechar ao menor deslocamento.
- [x] Adicionar pequeno atraso controlado para hover/close, evitando flicker.
- [x] Suportar clique e hover no desktop, sem depender apenas de hover.
- [ ] Suportar teclado:
  - `Enter`/`Space` abre;
  - `Esc` fecha;
  - setas navegam itens quando aplicavel;
  - `Tab` segue fluxo previsivel;
  - foco retorna ao botao ao fechar.
- [x] Usar `aria-controls`, `aria-expanded` e labels claros no botao de departamentos.
- [ ] Adicionar foco visivel em categoria, subcategoria e links finais.
- [ ] Separar categorias por relevancia comercial, nao apenas ordem alfabetica se houver dados de venda/estoque.
- [ ] Exibir no maximo informacao util: nome da categoria, subcategorias principais e opcionalmente quantidade/linha.
- [ ] Evitar banners decorativos dentro do menu; se houver promocao, usar bloco pequeno e comercial.
- [~] Mobile/tablet: drawer lateral implementado; ainda falta auditoria completa de subnavegacao e retorno/voltar em todos os niveis.
- [ ] Garantir que o menu mobile nao dispute com bottom nav, minicart ou busca.
- [ ] Registrar eventos de analytics para abertura, categoria clicada e subcategoria clicada.
- [ ] Validar menu em home, PLP, PDP e checkout/cart para garantir que nao invade fluxos sensiveis.

Criterios de aceite do menu vertical:

- Abrir e fechar sem deslocar o header ou o conteudo.
- Categorias devem ser legiveis em varredura rapida, com altura uniforme e alinhamento consistente.
- O primeiro nivel deve parecer uma lista de catalogo, nao um bloco promocional.
- Submenus devem abrir no eixo correto, sem sobrepor de forma aleatoria a busca ou carrinho.
- Menu deve funcionar com mouse, teclado e toque.
- Desktop nao pode criar scroll horizontal.
- Mobile deve usar painel dedicado, nao um dropdown espremido.
- A cor vermelha deve indicar acao/ativo, nao preencher grandes areas do menu sem necessidade.

## Fase 3 — Conteudo principal ausente ou colapsado

Objetivo: corrigir a causa de o footer aparecer imediatamente apos a nav.

Status: concluida para a home auditada. O conteudo principal existe e renderiza entre nav e footer.

Hipoteses:

- Bloco principal nao renderizou.
- Conteudo existe, mas esta `display:none`, `height:0`, `content-visibility` incorreto ou fora da viewport.
- FPC/cache servindo HTML incompleto.
- JS que monta carrossel/grid falhou.
- Layout XML removeu container esperado.

Tarefas:

1. Inspecionar `#maincontent`, `.columns`, `.column.main` e blocos filhos.
2. Medir altura de cada container principal.
3. Verificar se ha `empty body` entre menu e footer.
4. Confirmar se o CMS/category/product block esperado aparece no DOM.
5. Se o bloco existe mas colapsa, remover altura fixa/overflow indevido.
6. Se o bloco nao existe, revisar layout XML/CMS block/cache.
7. Confirmar FPC Redis DB2 limpo apos alteracoes estruturais.

Validacao:

```bash
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 2 FLUSHDB
sudo -u www-data php bin/magento cache:clean block_html full_page
```

Critério de aceite:

- Ha conteudo principal visivel entre nav e footer.
- `#maincontent` tem altura coerente com a pagina.
- Footer nao aparece na primeira dobra em paginas que deveriam ter conteudo.

Resultado atual:

- Home desktop: hero, cards B2B, categoria e vitrines aparecem antes do footer.
- Home tablet/mobile: conteudo principal aparece logo apos o hero, sem colapso para footer.

## Fase 4 — Footer estrutural

Objetivo: reduzir massa visual, melhorar alinhamento e eliminar altura artificial.

Status: Parcial. Compactacao desktop/mobile aplicada e validada em PLP, mas a refatoracao estrutural em PHTML/LESS ainda continua pendente para reduzir dependencia de terminal.

Problemas a corrigir:

- Footer vermelho muito alto.
- Colunas espalhadas e sem titulos claros.
- Card de endereco com contraste baixo.
- Links com espacamento vertical excessivo.
- Categorias/chips desalinhados.
- Bloco inferior branco parcialmente cortado.

Tarefas:

1. Remover altura fixa ou min-height artificial do footer.
2. Garantir `height:auto`, `contain:none` e `content-visibility:visible` onde necessario.
3. Criar grid de footer consistente:
   - desktop: 3 colunas;
   - tablet: 2 colunas;
   - mobile: 1 coluna.
4. Restaurar/fortalecer titulos das colunas.
5. Ajustar contraste do card de endereco.
6. Reduzir espacamento vertical entre links.
7. Padronizar links e botoes com alvo minimo de 44px.
8. Reposicionar bloco de redes sociais junto ao atendimento.
9. Transformar categorias em faixa compacta alinhada ao container.
10. Corrigir bloco branco inferior para nao aparecer cortado.

Arquivos provaveis:

- `OptimizeHeadStylesPlugin.php` para lock terminal emergencial.
- LESS do footer no tema filho para correcao definitiva.

Validacao:

- `.page_footer.scrollHeight` proximo da altura visual.
- Nenhum texto do card de endereco com contraste insuficiente.
- Footer sem corte no final da viewport.

Resultado atual:

- PLP desktop: footer medido em `1407px`, sem overflow horizontal; PLP mobile medido em `1464px`, sem overflow horizontal.
- Links institucionais desktop foram compactados para aproximadamente `28px`; chips de categoria para `32px`.
- Bloco `Desenvolvido por` caiu de aproximadamente `201px` para `56px`.
- Coluna Atendimento caiu de aproximadamente `520px` para `419px`, ainda acima do ideal no desktop.
- Mobile 390px foi validado sem overflow horizontal: trust bar `290px`, footer-container `488px`, categorias `64px`, footer-bottom `470px`, devby `64px`.
- Tablet ainda precisa de validacao dedicada; nao considerar o footer concluido ate migrar as regras para PHTML/LESS.

Pendencias especificas:

1. Refatorar o PHTML/LESS do footer para reduzir dependencia de JS terminal.
2. Reestruturar `.footer-bottom`, que mede `199px` no PLP desktop e `470px` no PLP mobile.
3. Refatorar painel de categorias: desktop ja fica aberto e mobile colapsa em `64px`, mas ainda depende de JS/terminal.
4. Reduzir coluna Atendimento sem esconder telefone, email, endereco, acoes e redes sociais.
5. Validar tablet com probe dedicado e ajustar empilhamento responsivo.
6. Limpar contraste do card de endereco no footer.


## Fase 5 — Cards, CTAs e alvos clicaveis

Status: Parcial. Home e PLP estao corrigidos para o CTA B2B limpo; falta completar auditoria em todos os blocos de recomendacao/PDP/carrinho.

Resultado atual em 2026-06-22:

- PLP desktop validado apos 9,5s: 12 CTAs B2B com altura `44px`, fundo transparente, borda `0px`, sem overflow horizontal.
- A regressao tardia era causada por JS terminal interrompido antes do bloco de CTA; corrigido com helper `width()` e `MutationObserver`.

Objetivo: eliminar cortes pequenos e alvos abaixo de 44px que afetam usabilidade.

Problemas a corrigir:

- Links de produto com 31-40px de altura.
- CTAs “Ver precos” e “Ver todos” abaixo de 44px em contextos ainda nao auditados.
- Bottom nav mobile com 40px.
- Chips de categoria pequenos.

Tarefas:

1. [~] Padronizar `.product-item-link` com min-height por contexto.
2. [x] Padronizar CTAs de preco com 44px na home e no PLP auditado.
3. [ ] Garantir bottom nav mobile com 44px em todas as paginas.
4. [~] Ajustar chips de categoria para altura minima e padding consistente; footer desktop esta parcial.
5. [ ] Remover clamps que cortam nomes longos em cards criticos fora das vitrines ja auditadas.
6. [ ] Validar PDP, carrinho e blocos de recomendacao que nao aparecem no PLP auditado.

Validacao:

- Nenhum CTA visivel abaixo de 44px.
- Nomes longos sem `scrollHeight` maior que caixa.
- Cards mantem grid sem CLS.

## Fase 6 — Carrosseis de vitrines

Status: Parcial.

Objetivo: transformar os carrosseis da home em um componente confiavel, moderno e comparavel a grandes plataformas, sem afetar grid/cards existentes.

Implementado agora:

- [x] Carregar `awa-scroll-carousel.min.js` automaticamente na home pelo loader oficial, sem depender de clique, toque ou teclado.
- [x] Montar `.awa-owl-nav__btn.awa-carousel__arrow` no header/host correto de cada vitrine dentro de `awa-scroll-carousel.js`.
- [x] Remover o slot invisivel `.awa-owl-nav--header-slot` do fluxo visual.
- [x] Padronizar setas em 38px desktop e 34px mobile.
- [x] Alinhar setas a direita do host nas vitrines principais.
- [x] Alinhar setas no host do painel ativo em "Linhas em destaque".
- [x] Evitar overlay das setas sobre cards/produtos.
- [x] Ativar autoplay proprio em alguns segundos apos acesso a loja.
- [x] Pausar movimento em hover, foco, pointer/touch e respeitar `prefers-reduced-motion`.
- [x] Usar `IntersectionObserver` para nao mover vitrines fora da regiao visivel/proxima.
- [x] Executar `scan()` do runtime original apenas uma vez para evitar `Maximum call stack size exceeded`.
- [x] Aumentar trilho de 5 para 12 produtos, mantendo 5 visiveis no desktop via CSS.
- [x] Recalcular painel ativo ao trocar abas.
- [x] Minificar e sincronizar `awa-scroll-carousel.min.js` em `pub/static`.
- [x] Atualizar cache-buster para `20260622-carousel-runtime-v7`.
- [x] Atualizar cache-buster do CSS shelf para `20260622-carousel-css-pro-v4`.
- [x] Tornar o fallback terminal condicional: ele nao e emitido quando o loader oficial do carrossel esta presente.
- [x] Validar desktop e mobile com `overflowX = 0` e console sem erros.
- [x] Migrar CSS de host/setas para `_awa-shelf-carousel.less` e `awa-shelf-carousel.min.css`.
- [x] Consolidar regra final em `awa-home-body-end-bundle.min.css`, camada carregada apos os bundles que venciam o shelf CSS.
- [x] Atualizar `BODY_END_QUERY` para `?v=20260622-carousel-pro-controls-v4`.
- [x] Validar setas sem o terminal inline no DOM: alvo real 44px, visual 38px desktop e 34px mobile.
- [x] Regenerar Brotli de `awa-scroll-carousel.min.js.br`, `awa-shelf-carousel.min.css.br` e `awa-home-body-end-bundle.min.css.br` para evitar asset stale.
- [x] Adicionar controle acessivel de pausar/retomar autoplay.
- [x] Refinar snap por card/grupo: desktop `x`, mobile `x mandatory`.
- [x] Garantir alvo clicavel real de 44px mantendo circulo visual menor.
- [x] Implementar barra de progresso discreta por vitrine com `aria-valuetext`.
- [x] Adicionar skeleton leve para imagens enquanto o shelf esta pendente.
- [x] Remover reserva de espaco do host quando nao houver overflow real.
- [x] Precarregar somente a proxima imagem provavel, com limite e baixa prioridade.
- [x] Emitir eventos frontend de analytics para `pause`, `resume`, `next`, `prev`, `swipe`, `impression`, `product_click` e `view_all_click`.
- [x] Enviar os mesmos eventos ao `dataLayer` com origem, direcao, produto, alvo e URL quando disponiveis.
- [x] Remover estados inline do runtime em nav/progresso/mount, usando classes/atributos do CSS: `awa-carousel-mounted`, `is-awa-viewport-width-guarded` e `[hidden]`.
- [x] Controlar acessibilidade dos slides: `aria-hidden`, `aria-current`, labels posicionais e tabulacao somente nos slides visiveis.
- [x] Corrigir limite de preload para no maximo 16 imagens por sessao de runtime.

Parcialmente implementado:

- [~] Acessibilidade: foco/disabled/reduced-motion, Home/End, pause/play, progresso aria e tabulacao de slides visiveis existem; falta auditoria screen reader ampla.
- [~] Performance: runtime e CSS final carregam por camadas oficiais; fallback terminal fica no codigo apenas como contingencia se o loader oficial faltar.
- [~] Skeleton/loading: skeleton leve implementado no estado pendente; falta validar em rede lenta real.
- [~] Migrar a correcao terminal para componente oficial: comportamento JS e CSS final migrados; falta reduzir especificidade alta da camada final.

Nao implementado:

- [ ] Remover dependência de seletores com especificidade alta e `!important`.
- [ ] Remover o fallback condicional do codigo apos uma rodada ampla de regressao, se o loader oficial permanecer estavel.
- [ ] Remover dependencia operacional de Owl/Swiper legado para as vitrines AWA.
- [ ] Implementar merchandising real: ranking por venda, estoque, margem, novidade e perfil B2B.
- [ ] Personalizar vitrines por historico de compra, categoria mais comprada e disponibilidade regional.
- [ ] Conectar os eventos frontend ja emitidos a coleta oficial/server-side, funil BI ou GA4 da operacao.
- [ ] Criar spec Playwright dedicado para carrosseis da home em desktop/mobile.

Arquivos atuais:

- `app/code/GrupoAwamotos/Theme/Plugin/Response/OptimizeHeadStylesPlugin.php`
- `app/code/GrupoAwamotos/Theme/Plugin/Response/DeferHomeScriptsPlugin.php`
- `app/code/GrupoAwamotos/Theme/Model/HeaderImpeccableCascadeLockCss.php`
- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Cms/templates/top-home.phtml`
- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-shelf-carousel-loader.phtml`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-shelf-carousel.less`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-shelf-carousel.min.css`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-home-body-end-bundle.css`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-home-body-end-bundle.min.css`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-scroll-carousel.js`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-scroll-carousel.min.js`
- `var/view_preprocessed/pub/static/app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Cms/templates/top-home.phtml`
- `var/view_preprocessed/pub/static/app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-head-preload.phtml`
- `var/view_preprocessed/pub/static/app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-shelf-carousel-loader.phtml`
- `pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-shelf-carousel.min.css`
- `pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-home-body-end-bundle.min.css`
- `pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/js/awa-scroll-carousel.js`
- `pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/js/awa-scroll-carousel.min.js`

Validacao atual:

| Viewport | Slides | Movimento | Setas | Overflow horizontal | Console |
|---|---:|---|---|---:|---|
| 1366x900 | 12 | pause `0px -> 0px`; next `0px -> 238px` | alvo 44px, visual 38px | 0px | sem erros |
| 390x844 | 12 | pause `0px -> 0px`; next `0px -> 166px` | alvo 44px, visual 34px | 0px | sem erros |

Validacao apos migracao para runtime oficial:

| Viewport | Script carregado | Movimento | Estado |
|---|---|---|---|
| 1366x900 | `awa-scroll-carousel.min.js?v=20260622-carousel-runtime-v7` | next `0px -> 238px` em "Mais Vendidos" | alvo 44px, visual 38px, `overflowX=0`, console sem erros |
| 390x844 | `awa-scroll-carousel.min.js?v=20260622-carousel-runtime-v7` | next `0px -> 166px` em "Mais Vendidos" | alvo 44px, visual 34px, `overflowX=0`, console sem erros |
| 1366x900 acessibilidade | `awa-scroll-carousel.min.js?v=20260622-carousel-runtime-v7` | 12 slides, 6 visiveis, 6 ocultos | 0 focaveis tabulaveis em slides ocultos, 1 `aria-current` |
| 390x844 acessibilidade | `awa-scroll-carousel.min.js?v=20260622-carousel-runtime-v7` | 12 slides, 3 visiveis, 9 ocultos | 0 focaveis tabulaveis em slides ocultos, 1 `aria-current` |
| 1366x900 eventos | `awa-scroll-carousel.min.js?v=20260622-carousel-runtime-v7` | `next/impression` no probe direcionado; demais eventos ja validados em rodada anterior | `dataLayer` ativo |

Validacao apos migracao da camada CSS oficial:

| Viewport | CSS carregado | Terminal removido no probe | Resultado |
|---|---|---|---|
| 1366x900 | `awa-home-body-end-bundle.min.css?v=20260622-carousel-pro-controls-v4` | sim | `hostPaddingRight=150px`, alvo 44px, visual 38px, `overflowX=0`, nav/progresso/mount sem `style` inline |
| 390x844 | `awa-home-body-end-bundle.min.css?v=20260622-carousel-pro-controls-v4` | sim | `hostPaddingRight=146px`, alvo 44px, visual 34px, `overflowX=0`, nav/progresso/mount sem `style` inline |
| 1366x900 progresso | JS + CSS oficiais | sim | barra 160px, `aria-valuetext="Item 2 de 12"` |

Critério de aceite final:

- Carrossel funciona sem scripts legados conflitando.
- Movimento e controles sao perceptiveis, mas nao agressivos.
- Mobile tem swipe natural, snap previsivel e sem scroll horizontal de pagina.
- Setas/progresso/disabled comunicam estado real.
- Autoplay nao roda fora da viewport nem contra preferencia de movimento reduzido.
- Cards carregam com skeleton e sem CLS.
- Dados de vitrine sao ordenados por criterios comerciais reais.

## Fase 7 — PDP e galeria

Status: Em andamento (implementação aplicada e ajustes em CSS/JS pendentes de validação visual final).

Objetivo: corrigir cortes residuais na galeria e relacionados.

Problemas a corrigir:

- Fotorama/stage com largura interna maior que a caixa.
- Placeholder de galeria indicando altura incorreta durante carregamento.
- Produtos relacionados cortando nomes longos.

Tarefas:

1. Ajustar `.gallery-placeholder` e `.fotorama__stage` para largura do container.
2. Evitar que shaft interno gere overflow horizontal visivel.
3. Definir altura minima correta para nomes em relacionados.
4. Garantir que CTAs de login/preco em PDP tenham 44px.

Validacao:

```bash
cd tests/e2e
npx playwright test specs/pdp-impeccable-layout.spec.ts --workers=1
```

Se a VPS encerrar com `137`, executar por `--grep` individual.

## Fase 8 — Catalogo e paginas institucionais

Status: Em andamento (ajustes CSS/JS aplicados, validação UX em 390/768/1366).

Objetivo: corrigir overflow e targets pequenos fora de PLP/PDP/home.

Problemas a corrigir:

- Links do catalogo com 35-40px.
- Viewer PDF e fallback com possivel overflow.
- Header/footer compartilhados herdando problemas das demais paginas.

Tarefas:

1. Padronizar links de navegacao do catalogo com 44px.
2. Garantir viewer dentro do container.
3. Validar footer institucional.
4. Validar 390/768/1366px.

## Fase 9 — Consolidacao definitiva em LESS/JS

Status: Parcial.

Objetivo: mover correcoes estaveis da camada terminal para o design system.

Tarefas:

1. [~] Identificar regras que ficaram estaveis no plugin.
2. [~] Migrar para partials LESS adequados.
3. [x] Migrar comportamento estavel dos carrosseis para `awa-scroll-carousel.js`.
4. [x] Atualizar/minificar o JS servido pelo tema.
5. [x] Migrar host/setas dos carrosseis para CSS oficial tardio.
6. [ ] Reduzir dependencia de `!important`.
7. [~] Sincronizar artefatos estaticos do tema filho.
8. [~] Manter o plugin apenas para locks de primeiro paint e remediacoes de cache/stale.

Comando:

```bash
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
sudo -u www-data php bin/magento cache:flush
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 2 FLUSHDB
sudo systemctl reload php8.4-fpm
```

## Fase 10 — Regressao visual e performance

Status: Parcial.

Objetivo: garantir que as correcoes nao degradem PSI, CLS ou fluxos B2B.

Testes minimos:

```bash
cd tests/e2e
npx playwright test specs/b2b-register.spec.ts --workers=1
npx playwright test specs/category-plp.spec.ts --workers=1
npx playwright test specs/pdp-impeccable-layout.spec.ts --workers=1
```

Checks manuais:

- Home.
- PLP: `bauletos.html` e `retrovisores.html`.
- PDP real com galeria.
- Carrinho vazio e carrinho com item.
- Login B2B.
- Cadastro B2B.
- Catalogo.
- Carrosseis da home:
  - autoplay inicia sem clique;
  - setas alinhadas no header/host;
  - CSS oficial continua funcionando sem `awa-home-carousel-motion-terminal-20260622`;
  - abas trocam sem perder controles;
  - `overflowX = 0`;
  - console sem `Maximum call stack size exceeded`.

Critérios de aceite:

- Sem overflow horizontal em 390px.
- Header compacto e alinhado.
- Busca em uma linha.
- Conteudo principal presente.
- Footer nao ocupa a primeira dobra indevidamente.
- CTAs visiveis com 44px.
- Logs sem novas excecoes.

## Fase 11 — Limpeza de layout terminal sem JS inline

Status: Implementado parcialmente.

Objetivo: reduzir correcoes tardias por JavaScript e deixar footer/header mais previsiveis apos refresh, FPC e Varnish.

Implementado:

1. [x] Criada camada CSS terminal `footerNoJsCompactRules()` no modulo `GrupoAwamotos_Theme`.
2. [x] Inserido marcador `awa-footer-no-js-compact-v1` dentro do estilo `awa-footer-terminal-lock-v1`.
3. [x] Protegido `awa-global-last-mile-layout-fixes-js-20260622` para pular o bloco de estilos inline do footer quando o marcador CSS existir.
4. [x] Mantido fallback antigo: se o CSS terminal nao carregar, o JS ainda aplica os ajustes emergenciais do footer.
5. [x] Compactados por CSS: containment, footer-bottom, links, titulos, atendimento, categorias, copyright, devby, newsletter mobile e trust bar mobile.
6. [x] Corrigido conflito do bundle `awa-align-grid-terminal-2026-06-11` que forçava `.footer-bottom` para `clamp(16px,2.4vw,28px)` no desktop.
7. [x] Newsletter mobile validada em uma linha (`email + CTA`) sem overflow horizontal.
8. [x] Trust bar mobile convertida para grade 2x2 compacta.

Pendente:

1. [ ] Migrar a mesma camada para LESS oficial do tema filho quando a regressao visual estiver estavel.
2. [ ] Substituir o logo lazy do footer-bottom por asset com dimensoes previsiveis ou placeholder reservado para evitar medicao 0x0 antes do scroll.
2. [ ] Remover definitivamente o bloco JS de footer apos validar home, PLP, PDP, carrinho, checkout e mobile.
3. [ ] Medir novamente quantidade de `style=""` no footer apos purge completo de Varnish/FPC.
4. [ ] Criar spec Playwright dedicada para garantir `overflowX = 0`, marker CSS presente e footer sem altura fantasma.

Critério de aceite:

- Footer renderiza sem depender de altura calculada por JS.
- Nao existe overflow horizontal em 390px e 1365px.
- Header/menu/carrosseis nao sofrem regressao por causa da limpeza.
- HTML publico contem `awa-footer-no-js-compact-v1` dentro de `awa-footer-terminal-lock-v1`.

## Fase 12 — Auditoria profunda do header

Status: Parcial em execucao.


Atualizacao 2026-06-24:

- [x] Corrigido o import quebrado em `_extend.less` e reintegrado o arquivo `_awa-header-vtex-final-polish-2026-06-24.less` no bundle de estilo final.
- [x] Ajustado lock final no `awa-css-gate.js` para manter `awa-header-categories` e `cta de departamentos` em `44px` em vez de `40px`, reduzindo variacao visual tardia no topo.
- [ ] Remover mais cedo a dependencia de estilos inline de header (critical/terminal/observer) sem comprometer login B2B/cart/mobile.
- [~] Padronizar contrato mobile do `mainHeader/headerInner` para `96px` em todas as paginas publicas, eliminando cortes residuais de scrollHeight.
- [ ] Executar uma passada de spec dedicada de header (home/plp/pdp/checkout/cart) com estado apos 3s e 9s para validar que nao haja retorno ao visual antigo.

Correcao 2026-06-23 (Fase 12 - home mobile layer fix):

- Causa raiz: regra legada em `@layer awa-fixes` fixava `.header.awa-main-header` em `88px !important`; por regra de cascata, `!important` dentro da layer vencia a trava terminal nao-layered de `96px`.
- Correcao aplicada em `OptimizeHeadStylesPlugin.php`: contrato mobile `96px` replicado dentro da mesma `@layer awa-fixes`, mantendo `nav` desktop fora do fluxo no mobile.
- Validacao home mobile `390x844`: `sticky=96px`, `headerMain=96px`, `headerInner=96px`, `container=96px`, `navBar=0px/display:none`, `overflowX=0`, `inlineCount=0`.

Correcao 2026-06-23 (Fase 12 - rotas publicas mobile):

- Causa raiz adicional: em PLP/404/carrinho, o `wp-header` ainda herdava alturas antigas (`88px`/`120px`) e `.block-search .block-content` mantinha `padding:8px`, empurrando o formulario de busca para fora da linha de `44px`.
- Correcao aplicada em `OptimizeHeadStylesPlugin.php`: contrato mobile do `wp-header` replicado em `@layer awa-fixes`, `sticky` nao-home normalizado em `96px` e padding da busca mobile zerado no header.
- Validacao mobile `390x844`: home, PLP `bauletos/acessorios-para-bau.html`, carrinho e 404 com `overflowX=0`, `headerMain=96px`, `headerInner=96px`, `navBar=0px/display:none`, `inlineCount=0`.
- Observacao: a home ainda apresenta diferenca tecnica de 1px em `scrollHeight` da busca por borda interna do formulario, sem overflow horizontal e sem corte visual medido.

Correcao 2026-06-23 (Fase 12 - tablet foco e toque):

- Causa raiz: elementos visualmente ocultos do header (`nav-toggle`, links top-header legados, fallback de carrinho, quick links e nav oculto) permaneciam com `tabIndex >= 0` em tablet, criando risco de foco invisivel.
- Correcao aplicada em `OptimizeHeadStylesPlugin.php`: script terminal `awa-header-hidden-focus-sync` sincroniza `tabindex="-1"`, `aria-hidden="true"` e `inert` com o estado visual real, restaurando atributos quando o elemento volta a ficar visivel.
- Corrigido alvo do botao de fechar da barra B2B em ate `991px`, garantindo `44x44`.
- Validacao tablet `768x1024`: home, PLP `bauletos/acessorios-para-bau.html`, carrinho e 404 com `hiddenFocusable=[]`, `visibleSmall=[]`, botao fechar B2B `44x44` e script presente.

Correcao 2026-06-23 (Fase 12 - footer bottom centralizado):

- Causa raiz: o eixo do footer estava em `1280px`, mas `.footer-bottom-inner` usava grid desktop de 3 colunas; o primeiro filho `.row.awa-footer-bottom__row` caia na coluna de `148px`, enquanto seus proprios filhos somavam largura maior. Resultado: linha comprimida, altura excessiva e percepcao de footer fora do centro.
- Correcao aplicada em `HeaderImpeccableCascadeLockCss.php`: footer bottom virou grid vertical de uma coluna, com linha logo/pagamentos/selos centralizada e copyright limitado a `1040px`.
- Correcao aplicada em `OptimizeHeadStylesPlugin.php`: fallback inline global passou a mirar tambem `#footer.footer-container`, forcar `margin-left/right:auto` fisico e recebeu o mesmo contrato de grid do footer bottom para rotas sem `awa-footer-terminal-lock-v1`.
- Validado em home, PLP e 404: desktop `1440px` com footer/container `1280px`, row `760px`, copyright `1040px`, `rowOverflow=0`; mobile `390px` com footer `390px`, row/copyright `358px`, `rowOverflow=0`.

Objetivo: transformar o header em uma estrutura unica, limpa e previsivel no padrao de grandes plataformas VTEX: busca dominante, conta/carrinho compactos, menu alinhado, mobile sem corte tecnico e sem dependencia de patches tardios.

Paginas auditadas nesta rodada:

- Home `/`.
- PLP `/bauletos.html`.
- Catalogo `/catalogo`.
- Login B2B `/customer/account/login/`, redirecionando para `/b2b/account/login/`.
- Carrinho `/checkout/cart/`.
- Viewports considerados: desktop `1365x900`, tablet `768x1024` e mobile `390x844`.

Resumo das metricas encontradas:

| Contexto | Medida encontrada | Leitura |
|---|---:|---|
| Home desktop | `header=156px`, `headerInlineCount=46` | Visual estabilizado, mas ainda dependente de muitas camadas inline/terminais. |
| PLP desktop | `header=156px`, `headerInlineCount=69` | Mesmo visual, porem cascata mais pesada e com mais risco de regressao. |
| Carrinho mobile | `header=137px`, promo `40px`, main `88px`, inner `96px` | Existe corte tecnico: o miolo e maior que o container. |
| Mobile publico | busca `44px`, mas alguns wrappers com `scrollHeight=52` | A busca parece alinhada, mas labels/containers ocultos ainda causam overflow tecnico. |
| Login B2B | sem `.awa-site-header` | Fluxo auth usa shell proprio; documentar se isso e decisao de produto ou inconsistencia. |

Erros restantes catalogados:

### Cascata, legado e estabilidade

1. [ ] Existem muitas camadas de CSS inline competindo no header; home desktop mediu `46` estilos relacionados e PLP desktop mediu `69`.
2. [ ] O header ainda depende de locks `critical`, `terminal`, `last-mile` e observers para manter o estado final.
3. [ ] Ha nomes de camadas concorrentes para o mesmo objetivo: `awa-header-vtex-clean-critical-20260622`, `awa-header-distill-terminal-20260616e`, `awa-header-vtex-clean-terminal-20260622` e `awa-global-last-mile-layout-fixes-20260622`.
4. [ ] O comportamento final varia por pagina porque home, PLP, carrinho e catalogo recebem conjuntos diferentes de estilos inline.
5. [ ] O problema reportado de "corrige no refresh e volta depois" indica corrida de JS/CSS ainda possivel quando cache, customer sections ou tema pai reidratam componentes.
6. [ ] A ordem de cascata ainda depende de CSS body-end e plugin PHP, nao apenas de LESS compilado do tema filho.
7. [ ] Ha uso excessivo de seletores longos e especificidade alta para vencer o tema pai.
8. [ ] A limpeza de `!important` ainda nao foi feita no header.
9. [ ] Ainda nao existe contrato unico de altura por breakpoint documentado em LESS oficial.
10. [ ] O z-index do header usa numeros muito altos e dispersos, sem escala semantica.
11. [ ] O header ainda tem fallback visual antigo em DOM/CSS, mesmo quando o layout novo esta aparente.
12. [ ] A matriz tablet/mobile ainda nao esta totalmente automatizada por spec devido a `exit 137` na VPS.

### Estrutura DOM e duplicidade

13. [ ] Foram encontrados multiplos blocos equivalentes no desktop: logo/brand `2`, busca `2`, minicart `3` e departamentos `2` em algumas paginas.
14. [ ] Elementos desktop e mobile ficam presentes simultaneamente no DOM, com parte apenas escondida por CSS.
15. [ ] O botao mobile de menu apareceu visivel no desktop da home em probe de auditoria, com caixa aproximada `160x26`.
16. [ ] A conta B2B oculta no mobile ainda deixa footprint tecnico com `visibility:hidden` e conteudo interno medindo texto.
17. [ ] O nav desktop fica oculto no mobile, mas ainda precisa ser removido do fluxo/foco de forma mais limpa.
18. [ ] Quick links e departamentos escondidos ainda entram em contagens e podem afetar medicao/foco.
19. [ ] O minicart tem wrappers redundantes: wrapper externo, `.mini-carts`, `.minicart-wrapper`, `.showcart` e fallback.
20. [ ] O fallback `.awa-header-cart-fallback` ainda deve ser removido do fluxo quando o minicart real estiver garantido em todas as paginas.
21. [ ] O formulario de busca possui wrappers e labels ocultos que ainda geram overflow tecnico.
22. [ ] A pagina de login B2B nao compartilha o header publico; isso pode ser intencional, mas precisa decisao explicita.

### Desktop

23. [ ] O header desktop ainda ocupa `156px`, aceitavel no estado atual, mas alto para primeira dobra se comparado a headers B2B mais densos.
24. [ ] A barra B2B promocional consome `32px`; precisa validar se gera valor suficiente ou se deve virar faixa mais compacta/condicional.
25. [ ] CTA da barra B2B com `32px` de altura fica abaixo do alvo ideal de `44px` para toque.
26. [ ] Botao de fechar da barra B2B com `32px` de altura fica abaixo do alvo ideal de `44px`.
27. [ ] Botao de limpar busca mede perto de `36x44`; largura abaixo do alvo ideal de `44px`.
28. [ ] Icone de conta em PLP desktop mediu `34x34`, abaixo de `44x44`.
29. [ ] Links `Entrar` e `Cadastrar` aparecem como alvos separados com cerca de `18px` de altura em alguns probes.
30. [ ] Area B2B guest ainda mostra texto comprimido; `Entrar ou Cadastrar` pode gerar clipping vertical.
31. [ ] `.awa-header-account-prompt__line2` apresentou `scrollHeight` maior que a altura visivel.
32. [ ] Wrappers do header apresentaram `scrollHeight` maior que `height`, indicando overflow escondido.
33. [ ] A distribuicao dos links do nav difere entre home e PLP, com eixo visual nao perfeitamente identico.
34. [ ] O bloco de departamentos usa largura fixa aproximada de `206px`; precisa confirmar responsividade em desktop estreito.
35. [ ] A linha divisoria/nav ainda pode parecer uma faixa separada do header, nao uma composicao unica.
36. [ ] O campo de busca usa comportamento diferente por pagina (`grid`/`flex`), mesmo quando o visual final parece igual.
37. [ ] Labels acessiveis do minicart/search aparecem como overflow tecnico em auditoria de texto.
38. [ ] O carrinho visual esta alinhado, mas textos ocultos do link ainda criam caixas residuais pequenas.
39. [ ] A area conta/carrinho ainda parece formada por blocos independentes, nao por um cluster unico de acoes.
40. [ ] O estado sticky precisa ser comparado visualmente ao estado normal para evitar salto de alinhamento.

### Tablet

41. [ ] O tablet ainda nao tem baseline completo por pagina; execucoes maiores podem cair com `exit 137`.
42. [ ] O prompt B2B oculto pode causar overflow tecnico em tablet.
43. [ ] A busca precisa confirmar largura fluida sem quebrar entre logo, conta e carrinho.
44. [ ] A nav/departamentos precisa contrato claro: desktop reduzido, drawer ou ocultacao completa.
45. [ ] O alvo do menu e do carrinho precisa manter `44x44` em tablet.
46. [ ] O autocomplete da busca ainda precisa validacao em tablet para nao ser cortado por overflow do header.

### Mobile

47. [ ] O mobile do carrinho mediu `mainHeader=88px` e `headerInner=96px`, indicando corte tecnico vertical.
48. [ ] O container interno mobile chegou a `scrollHeight=122` dentro de altura menor, sinal de conteudo oculto.
49. [ ] Texto da barra promocional mobile mediu `height=36` e `scrollHeight=44`, indicando corte/clipping.
50. [ ] A busca mobile aparenta `44px`, mas wrappers internos podem chegar a `52px`.
51. [ ] Conta B2B escondida no mobile ainda gera textos com largura `0` e `scrollWidth` maior que `0`.
52. [ ] Labels ocultos como "Pesquisar catalogo" entram em auditoria como overflow tecnico; padrao `sr-only` precisa ser padronizado.
53. [ ] A posicao da logo varia entre paginas mobile; carrinho e paginas publicas nao seguem exatamente o mesmo eixo vertical.
54. [ ] A largura/posicao da busca mobile varia entre paginas: em algumas ocupa quase toda a largura, em outras fica deslocada por menu/logo.
55. [ ] O nav desktop precisa ficar `inert`/fora da tabulacao no mobile, nao apenas invisivel.
56. [ ] O menu mobile precisa garantir abertura sem empurrar header, sem scroll horizontal e com foco preso no drawer.
57. [ ] A barra B2B mobile precisa decidir prioridade: texto curto, CTA ou fechamento; hoje o texto ainda disputa altura.
58. [ ] O carrinho mobile deve ser icon-only visualmente, com label acessivel sem gerar caixa residual.

### Acessibilidade e interacao

59. [ ] Ha alvos clicaveis menores que `44px` em barra B2B, busca clear, conta e links de login/cadastro.
60. [ ] Elementos ocultos podem continuar focaveis se nao forem marcados como `hidden`, `aria-hidden` ou `inert` conforme o caso.
61. [ ] A ordem de foco pode passar por duplicatas desktop/mobile quando ambos existem no DOM.
62. [ ] O botao de departamentos precisa manter `aria-expanded` sincronizado em todos os estados, inclusive apos reidratacao.
63. [ ] O minicart precisa garantir nome acessivel sem criar texto visivel/overflow residual.
64. [ ] O autocomplete da busca precisa foco, fechamento por `Esc` e limite visual abaixo do header.
65. [ ] Estados hover/focus/active do cluster conta/carrinho ainda precisam padronizacao visual unica.
66. [ ] O contraste da barra B2B deve ser revalidado apos compactacao final.

### Performance, CLS e manutencao

67. [ ] Quantidade alta de styles inline aumenta custo de parse e risco de CLS tardio.
68. [ ] Observers/timers por ate dezenas de segundos podem mascarar regressao e consumir CPU no carregamento.
69. [ ] Duplicidade de DOM aumenta custo de layout, principalmente em mobile.
70. [ ] Cache Redis/FPC/Varnish pode servir HTML com camada antiga e reproduzir o "volta layout antigo".
71. [ ] Falta spec dedicada que compare estado inicial, estado apos `3s`, `9s` e estado sticky.
72. [ ] Falta auditoria do header com busca autocomplete aberta, minicart aberto e menu departamentos aberto simultaneamente.
73. [ ] Falta budget explicito de altura do header por breakpoint.
74. [ ] Falta criterio automatico para `document.documentElement.scrollWidth - innerWidth === 0` em todas as paginas auditadas.
75. [ ] Falta remover regras antigas depois que o LESS oficial assumir o contrato final.

Plano de correcao por subfases:

1. [ ] Mapear todos os emissores de CSS/JS do header e separar em: manter, migrar para LESS, remover.
2. [ ] Migrar o contrato final do header para `_awa-header-vtex-clean-2026-06.less`.
3. [ ] Reduzir `injectHeaderVtexCleanTerminal()` para fallback curto e mensuravel.
4. [ ] Remover ou neutralizar estilos `critical/terminal` duplicados quando a matriz passar.
5. [ ] Consolidar DOM do header: um logo, uma busca, um cluster de conta/carrinho e um menu por breakpoint.
6. [ ] Corrigir visibilidade do toggle mobile no desktop e garantir `display:none`/`inert` nos elementos fora do breakpoint.
7. [ ] Recriar o cluster conta B2B + carrinho como grupo compacto, com alvo total de `44px` e sem links internos de `18px`.
8. [ ] Padronizar busca como elemento dominante: altura `44px`, botao `44px`, clear `44px`, labels `sr-only` sem overflow.
9. [ ] Compactar ou condicionar a barra B2B para nao roubar altura em mobile.
10. [ ] Definir contrato mobile de linhas: promo, topo, busca, sem `height` menor que conteudo interno.
11. [ ] Padronizar nav/departamentos: desktop alinhado ao container; mobile somente drawer.
12. [ ] Criar escala de z-index para header, menu, autocomplete, minicart e drawer.
13. [ ] Validar estados abertos: departamentos, autocomplete, minicart e conta.
14. [ ] Criar spec Playwright `header-deep-contract.spec.ts` com pagina e viewport isolados.
15. [ ] Rodar probes isolados por pagina para evitar `exit 137`: home, PLP, catalogo, PDP, carrinho, 404, login/cadastro.
16. [ ] Medir antes/depois: altura do header, overflow horizontal, small clickables, text overflow, quantidade de estilos inline e duplicidade DOM.

Criterios de aceite da Fase 12:

- Header publico tem uma unica origem estrutural por breakpoint.
- Nenhum alvo clicavel visivel fica abaixo de `44x44`, salvo texto puramente informativo nao clicavel.
- Mobile nao apresenta `scrollHeight` maior que `height` em wrappers principais do header.
- Elementos ocultos por breakpoint ficam fora da tabulacao e fora do fluxo de layout.
- Home, PLP, catalogo, PDP, carrinho e 404 mantem o mesmo contrato visual apos `9s`.
- Login/cadastro B2B tem decisao documentada: manter shell sem header ou aplicar header compacto proprio.
- Quantidade de styles inline do header cai de dezenas para o minimo necessario de critical CSS.

## Ordem recomendada de execucao

1. Fase 4: refatorar footer em PHTML/LESS, reduzindo newsletter, atendimento, categorias e `.footer-bottom` sem depender de JS terminal.
2. Fase 9: migrar correcoes estaveis de menu, CTAs e footer para LESS/JS oficiais e reduzir especificidade/`!important`.
3. Fase 2: completar menu vertical com segundo nivel, foco interno, analytics e auditoria multi-pagina/mobile.
4. Fase 12: executar a limpeza profunda do header por subfase, sem mudar visual final de uma vez.
5. Fase 1: consolidar header clean em LESS, removendo overflow tecnico tablet e locks terminais.
6. Fase 5: validar CTAs e targets restantes em PDP, carrinho, recomendados e catalogo.
7. Fase 6: completar auditoria de acessibilidade/performance dos carrosseis e remover fallback condicional quando seguro.
8. Fases 7 e 8: PDP, galeria, catalogo e paginas institucionais.
9. Fase 10: regressao final isolada por pagina/viewport para evitar `exit 137` na VPS.

## Riscos

- FPC Redis e Varnish podem mascarar alteracoes; apos mudancas estruturais, limpar Redis DB2 e purgar Varnish local.
- CSS async/body-end pode vencer LESS compilado.
- Rodar Playwright em lote pode encerrar com `exit 137` por memoria; preferir `--workers=1` e specs isoladas.
- Alteracoes amplas no footer podem afetar home, PLP, PDP, carrinho e checkout simultaneamente.
- Ajustes de altura no header podem causar CLS se aplicados tarde demais.

## Pendencias abertas

- Header clean:
  - migrar regras de lock/camada terminal para LESS do tema filho;
  - remover overflow tecnico tablet do prompt B2B oculto;
  - transformar area B2B/login/cadastro/minicart em grupo compacto e alinhado;
  - validar sticky header, CLS e autocomplete da busca sem corte por overflow.
- Menu vertical/departamentos:
  - manter painel desktop entre 280px e 320px e validar em mais paginas;
  - completar foco interno e navegacao por setas dentro da lista;
  - criar segundo nivel de categorias sem flicker de hover;
  - completar fluxo mobile/tablet com voltar/fechar por nivel;
  - registrar analytics de abertura/clique;
  - validar home, PLP, PDP, carrinho e checkout para evitar sobreposicao.
- Carrosseis:
  - manter fallback condicional monitorado e remove-lo do codigo apenas depois de regressao ampla;
  - reduzir especificidade alta e `!important` da camada final quando os bundles legados forem limpos;
  - validar skeleton/loading em rede lenta real;
  - conectar merchandising real e coleta oficial/server-side dos eventos de analytics ja emitidos no frontend.
- Footer estrutural: camada CSS terminal sem JS inline implementada parcialmente; proxima etapa e migrar para LESS/PHTML oficial, remover fallback JS apos regressao ampla e validar home/PLP/PDP/tablet/mobile.
- Limpar overflow tecnico do header tablet causado por conteudo oculto no prompt B2B.
- Definir migracao definitiva das correcoes estaveis para LESS.
- Criar spec dedicada para a pagina exata da captura, com asserts de:
  - busca em linha unica;
  - header abaixo de altura maxima definida;
  - `maincontent` com altura minima;
  - footer abaixo do conteudo principal.

## Auditoria Visual Profunda (Atualização 2026-06-23)
## Estratégia aplicada
- Escopo da fase: auditoria visual e de experiência (sem alterar fluxo funcional e sem pagamento real no checkout)

- Execução por lote curto (`--workers=1`), 1 rota por etapa e viewport segmentado.
- Controle manual de memória para evitar `OOM`:
  - `tablet-*`, `notebook-*` e `390x844` com Chromium em primeiro ciclo.
  - Projetos `mobile/*`, `firefox-*` e `webkit-*` ficarão fora até instalação dos browsers faltantes no host.
- Artefatos consultados nesta iteração:
  - `tests/full-ux-audit-report.json`
  - `tests/e2e/reports/impeccable-visual-deep-audit.json`
  - `tests/e2e/reports/impeccable-b2b-flow-audit.json`
  - Screenshots em `tests/e2e/screenshots/impeccable-visual-deep-audit/*` e `tests/e2e/screenshots/impeccable-b2b-flow-audit/*`
- Comandos executados (controle de memória/sem regressão):
  - `cd tests/e2e && AWA_BASE_URL=https://awamotos.com TEST_USER=10860222000138 TEST_PASS=123awa npx playwright test tests/e2e/specs/impeccable-visual-deep-audit.spec.ts --workers=1`
  - `cd tests/e2e && AWA_BASE_URL=https://awamotos.com TEST_USER=10860222000138 TEST_PASS=123awa npx playwright test tests/e2e/specs/impeccable-b2b-flow-audit.spec.ts --workers=1`
  - `cd tests/e2e && AWA_BASE_URL=https://awamotos.com TEST_USER=10860222000138 TEST_PASS=123awa npx playwright test tests/e2e/specs/visual-audit-home-header-footer.spec.ts --workers=1`

### Estado consolidado por severidade (P0–P3)

- **P0 (bloqueio funcional): 0**
- **P1 (alto, impacto de conversão): 4**
- **P2 (médio, consistência/acessibilidade): 8**
- **P3 (baixo, polimento/técnico): 3**

### Matriz de correção por severidade, página/viewport e dependência

| Severidade | Componente | Página/rota | Viewport | Evidência | Risco | Fase recomendada | Dependência / pré-requisito |
|---|---|---|---|---|---|---|---|
| P1 | PDP → carrinho → checkout (B2B) | `/bagageiro-titan-125-modelo-00-04-fan-125-modelo-05-08-cromado-macico-3015.html` | 390x844 / 1366x768 | `add-to-cart` não confirma adição, estrutura esperada `.product-info-main` ausente (`img=false` em algumas execuções), `pageerror` e `404` | Bloqueio de conversão | Fase 7 + Fase 10 | Revisar contrato de PDP B2B (template/base, disponibilidade, login/price), fechar selectors de compra |
| P1 | Carrinho | `/checkout/cart/` | 390x844 | CTA principal de checkout não encontrado/sem estado de ação principal; botão abaixo de 44px | Bloqueio de progressão da jornada | Fase 5 + Fase 7 | Validar template/layout do bloco principal do carrinho e regra por sessão |
| P1 | Checkout | `/checkout/` | 390x844 (início de checkout) | Campos de endereço ocultos (`Rua`, `CEP`) e etapa de continuação não clicável no fluxo auditado | Bloqueio de conclusão (sem pagamento real) | Fase 7 | Estabilizar templates `shipping/address` e mensagens por estado de sessão |
| P1 | B2B conta | `/b2b/account/dashboard/`, `/customer/address/` | 390x844 | `account-nav` inconsistente e redirecionamentos fora do escopo B2B detectados | Quebra de navegação administrativa | Fase 2 | Revisar guard/contexto de customer-company e renderização de shell de dashboard |
| P2 | Home público | `/` | 390x844 / 1366x768 | 20 imagens com `naturalWidth=0` no screenshot `home-390x844.png`; falha recorrente de mídia no bloco principal | Ruído de confiança e percepção de catálogo | Fase 3 | Corrigir lazy-load/markup de mídia e fallback visual |
| P2 | PLP B2B | `/bagageiros.html` | 390x844 / 768x1024 | 9 imagens com `naturalWidth=0`; `.awa-b2b-promo-bar__cta` abaixo de 44x44 (ex.: 89x36), toque inconsistente | Fricção de uso mobile/tablet e ruído de grade | Fase 1 + Fase 3 + Fase 10 | Padronizar CTA mínimo 44x44, revisar media/srcset da grade e card template |
| P2 | Header / Catálogo | `/`, categoria e páginas de navegação | 390x844 / 768x1024 / 1366x768 | 11+ elementos abaixo de 44px, `1x1` em skip-links e overflow em rota de categoria (full-ux baseline) | Fricção de UX e legibilidade | Fase 1 + Fase 12 + Fase 10 | Padronizar skip-links, espaçamento e controle de overflow do header |
| P2 | B2B / institucional | `/sales/order/history/`, `/customer/address/` | 390x844 | `page-title` e hierarquia de cabeçalho divergentes entre módulos | Inconsistência de padrão visual | Fase 2 + Fase 8 | Padronizar `page-title`, `breadcrumb` e shell de título por módulo |
| P2 | Formulários | `/checkout/`, `/login`, PDP e páginas B2B | 390x844 / 768x1024 | Cobertura parcial de required/erro/foco; faltam asserts de tela completa em todos os caminhos | Lacuna de regressão visual e UX | Fase 3 + Fase 5 | Expandir matriz de formulário e consolidar baseline visual controlada |
| P3 | JS runtime | `/b2b/account/login/` | 390x844 | `Cannot read properties of undefined (reading 'remove')` | Ruído técnico sem impacto visual imediato | Fase 12 | Ajustar guard clause no handler JS após localizar origem |
| P3 | Marcação de formulário | `/checkout/cart/`, `/p/...` | 390x844 | Formulário sem atributo `action` em pontos de fluxo crítico | Fragilidade de semântica/submissão | Fase 3 | Revisar markup dos formulários usados no fluxo ponta a ponta |
| P3 | Infraestrutura de estilos | global | global | Dependência de overrides com alta especificidade e fallback terminal ainda não finalizados | Risco de regressão em alterações futuras | Fase 9 + Fase 11 | Migrar incrementalmente para overrides em LESS com escopo controlado |

### Matriz de componentes (estado atual x dependência x fase)

| Componente | Estado atual | Fase recomendada | Resultado esperado (aceitação) | Dependência técnica |
|---|---|---|---|---|
| Header + topo (desktop/tablet/mobile) | Quase estável, com ruído de fallback em alguns pontos | Fase 12 + Fase 9 | Contrato único por breakpoint sem retorno de estilos tardio | `awa-css-gate.js`, `awa-head-preload`, wrappers de header |
| Navegação e módulos B2B | Funcional, porém com estados inconsistentes de menu/conta | Fase 2 | Menu de segundo nível previsível, sem quebra de fluxo de página e com foco por teclado | `awa-menu-controller.js`, account-nav templates |
| Search / mini-cart | Funcional principal | Fase 1 + Fase 12 | Elementos de busca/carrinho com alvo mínimo `44x44` e estados visíveis | wrappers de top header e estilos legados |
| Home / PLP | Estrutura base estável, com lacunas de mídia/touch | Fase 3 + Fase 8 | Blocos visuais com mídia íntegra, hierarquia limpa e sem overflow | templates de produtos/categorias, regras de lazy-load |
| PDP | Fluxo B2B de compra ainda inconsistente | Fase 7 | CTA único de compra, gallery íntegra, estrutura canônica `.product.media` + `.product-info-main` | `_awa-pdp-shell-*`, template de produto |
| Cart e Checkout | Conversão parcial em execução de ponta a ponta | Fase 7 + Fase 10 | Botões primários acessíveis, campos com rótulo/erro e continuidade de etapa | regras de sessão + estado de preço/disponibilidade |
| Footer | Estável visualmente com técnica de transição | Fase 4 + Fase 11 | Espaçamento e tipografia consistentes em todo o site | Footer PHTML/JS legado |
| Acessibilidade global | Melhorias iniciadas, faltam hardening de foco e contraste | Fase 12 + Fase 10 | Checklist de foco, tabulação, touch-target e contraste no escopo P0-P3 | helpers `impeccable-*`, execução por viewport fixo |

### Plano de execução por ciclo (sem quebrar funcionalidades)

#### Ciclo A — Fechamento de risco de conversão (3–5 dias úteis)
1. Confirmar e reproduzir os erros de fluxo em `/bagageiros.html` → produto → carrinho → `/checkout/`.
2. Corrigir `P1` do PDP para aquisição: validar `add-to-cart`, `product.media`/`product-info-main` e sessão B2B.
3. Corrigir carrinho (`/checkout/cart/`) para manter CTA principal visível e clicável em mobile.
4. Reexecutar `impeccable-b2b-flow-audit.spec.ts` no checkpoint `IMPECCABLE_B2B_ROUTES` com `390x844`.

#### Ciclo B — Padronização de estabilidade (5–7 dias úteis)
1. Fechar `P2` de home/categoria/PLP:
   - imagem quebrada,
   - overflow,
   - botões < 44px,
   - fontes muito pequenas no mobile.
2. Consolidar `skip-link`, foco e legibilidade em header/mobile drawer.
3. Reexecutar `full-ux-audit.spec.ts` e `site-grid-alignment.spec.ts` (notebook-1280/1366 + tablet-768).

#### Ciclo C — Qualidade premium e polimento VTEX-like (1–2 semanas)
1. Resolver `P3` técnico:
   - remover scripts/handlers frágeis após trilha de origem,
   - ajustar formulários sem `action`.
2. Unificar overrides para LESS por camada final, reduzindo dependência de `!important` e fallback terminal.
3. Rodar suíte curta por rota e viewport fixa: `390x844`, `768x1024`, `1366x768`, `1920x1080`.
### Bloqueios técnicos remanescentes
- `visual-audit-mobile-interactions.spec.ts --project=mobile-390` não gerou validação funcional: falha de launch em `webkit` (`/home/deploy/.cache/ms-playwright/webkit-2311/pw_run.sh` não existe).
- Dependência bloqueante: mobile-390 está atrelado ao runtime webkit no projeto atual; sem instalação não há cobertura de menu mobile, filtros, carrossel e overflow em 390 nesta rodada.

### Correções aplicadas - lote layout 2026-06-23
- Aplicado ajuste LESS em `_awa-header-vtex-final-polish-2026-06-24.less` para garantir alvo real `44x44` no CTA `.awa-b2b-promo-bar__cta` da barra B2B, com foco visível e sem alterar URL/markup.
- Aplicado ajuste LESS em `_awa-layout-final-cleanups-2026-06.less` para substituir skip-links ocultos `1x1` por padrão offscreen com área real `44x44` e estado `:focus-visible` acessível.
- Aplicado ajuste LESS em `_awa-plp-consistency-pass2-2026-06.less` para reforçar botão de busca da PLP/search com `44x44`, alinhado ao grid `minmax(0, 1fr) + submit`.
- Validação local: render LESS dos três partials com `_awa-tokens`, `_awa-variables` e `_design-system` concluído sem erro.

### Observação operacional
- Nenhuma ação desta fase altera lógica B2B, ERP, checkout financeiro ou integração de pagamento.
- O foco desta etapa é fechamento de interface e risco visual sem alterar comportamento funcional implementado.
- Os próximos lotes devem manter `--workers=1`, 1 rota por execução e snapshots por ciclo para evitar regressão por OOM.
