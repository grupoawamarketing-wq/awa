# Análise de blocos da página de produto

- Repositório: `/home/user/htdocs/srv1113343.hstgr.cloud`
- Data: `2026-03-30`
- Escopo: estrutura da PDP a partir do layout Magento, overrides do tema child, CSS/JS de responsividade e evidências visuais disponíveis em artifacts

## Fontes principais

- Layout base Magento: `vendor/magento/module-catalog/view/frontend/layout/catalog_product_view.xml`
- Tema pai Ayo: `app/design/frontend/ayo/ayo_default/Magento_Catalog/layout/catalog_product_view.xml`
- Tema child ativo: `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Catalog/layout/catalog_product_view.xml`
- CTA principal: `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Catalog/templates/product/view/addtocart.phtml`
- Galeria: `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Catalog/templates/product/view/gallery.phtml`
- Descrição simplificada: `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Catalog/templates/product/view/awa-pdp-tabs.phtml`
- CSS de layout/comportamento: `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-pdp-b2b-pro.unmin.css`
- Sticky CTA mobile: `app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-pdp-sticky-cta.js`

## Resumo executivo

A PDP atual foi simplificada pelo tema child para priorizar conversão: galeria, título, preço, botão de compra e descrição. O layout XML remove breadcrumbs, reviews, overview, addto, mailto, social, atributos adicionais, related e upsell, mas o CSS e os artifacts mostram que ainda existem variações históricas e B2B que renderizaram elementos extras em execuções anteriores. Na prática, há três camadas a considerar:

1. **Estrutura base Magento/Ayo**: galeria à esquerda e informações de compra à direita.
2. **Modo básico do child atual**: simplifica a página e esconde blocos secundários.
3. **Variações históricas/B2B**: mostram review summary, atributos, compatibilidade e sticky CTA em screenshots de artifacts.

## Representação visual

### Desktop

```text
+----------------------------------------------------------------------------------+
| Header global / topo utilitário / busca / minicart / navegação                  |
+----------------------------------------------------------------------------------+
| Breadcrumbs (historicamente visíveis, removidos no layout atual)                |
+--------------------------------------+-------------------------------------------+
| Galeria principal                    | Coluna de informações                     |
| - imagem principal                   | - título                                 |
| - thumbnails                         | - reviews summary (variante histórica)   |
| - zoom trigger                       | - preço / estoque / SKU                  |
|                                      | - selo promocional                        |
|                                      | - quantidade + botão comprar             |
|                                      | - ações secundárias / social (histórico) |
+--------------------------------------+-------------------------------------------+
| Descrição simplificada / tabs customizadas / conteúdo técnico                    |
+----------------------------------------------------------------------------------+
| Compatibilidade / aplicação (quando Fitment/B2B aparece em variações)            |
+----------------------------------------------------------------------------------+
| Related / Upsell (removidos no layout atual)                                     |
+----------------------------------------------------------------------------------+
```

### Mobile

```text
+--------------------------------------+
| Header compacto / busca              |
+--------------------------------------+
| Breadcrumbs curtos ou ocultos        |
+--------------------------------------+
| Galeria ocupa largura total          |
+--------------------------------------+
| Título                               |
| Reviews summary (histórico)          |
| Preço / estoque                      |
| Botão comprar                        |
+--------------------------------------+
| Descrição / tabs simplificadas       |
+--------------------------------------+
| Compatibilidade / aplicação          |
+--------------------------------------+
| Sticky CTA fixo no rodapé            |
+--------------------------------------+
```

## Inventário detalhado de blocos

### 1. Header global da página

- **Função**: contexto global da loja, navegação, conta, busca e minicart.
- **Conteúdo**: topo utilitário com localização/telefone, logo, campo de busca, minicart e links de conta.
- **Posicionamento**: acima da PDP; ocupa largura total e antecede qualquer conteúdo do produto.
- **Interações**: busca, acesso à conta, wishlist, comparação e carrinho.
- **Responsividade**:
  - desktop: barra horizontal completa;
  - tablet: busca central continua dominante;
  - mobile: header reduzido, foco visual em logo + busca.
- **Evidência visual**:
  - `artifacts/header-audit-20260324-after/pdp-desktop1440.png`
  - `artifacts/header-audit-20260324-after/pdp-tablet768.png`
  - `artifacts/header-audit-20260324-after/pdp-mobile375.png`

### 2. Breadcrumbs

- **Função**: indicar a trilha da navegação até o produto.
- **Conteúdo**: links do catálogo até a página atual.
- **Posicionamento**: logo acima do grid principal.
- **Interações**: clique para voltar à categoria ou home.
- **Estado atual**:
  - o layout child remove o bloco `breadcrumbs`;
  - screenshots históricas ainda mostram o bloco, indicando divergência entre evidência visual antiga e layout atual.
- **Responsividade**:
  - desktop/tablet: linha completa;
  - mobile: versão abreviada.

### 3. Grid principal da PDP

- **Função**: organizar a galeria e a coluna de compra.
- **Posicionamento**: container principal de conteúdo.
- **Hierarquia visual**:
  - desktop: 40% mídia e 58% informação;
  - mobile: empilhado verticalmente.
- **Interações**: nenhuma direta; estrutura o restante dos blocos.
- **Responsividade**:
  - `display:flex` em coluna no mobile;
  - `row` no desktop a partir de 992px.

### 4. Galeria de imagens

- **Função**: exibir imagem principal do produto e miniaturas.
- **Conteúdo**:
  - imagem LCP com `fetchpriority="high"` e `loading="eager"`;
  - JSON da galeria Magento;
  - thumbnails abaixo da mídia principal.
- **Posicionamento**: coluna esquerda no desktop; topo da página no mobile.
- **Interações**:
  - troca de imagem por miniatura;
  - zoom/magnifier;
  - navegação de setas.
- **Responsividade**:
  - ocupa quase 100% da largura no mobile;
  - mantém proporção quadrada para inicialização correta do gallery.js;
  - permanece coluna fixa no desktop.
- **Observação**: é um dos blocos mais estáveis entre código e screenshots.

### 5. Título do produto

- **Função**: apresentar o nome comercial do item.
- **Conteúdo**: `page.main.title`.
- **Posicionamento**: topo da coluna direita.
- **Interações**: nenhuma.
- **Responsividade**:
  - fonte com `clamp(22px, 3vw, 32px)`;
  - quebra em múltiplas linhas em tablet/mobile.

### 6. Resumo de avaliações

- **Função**: expor prova social e acesso a reviews.
- **Conteúdo**:
  - resumo da nota;
  - links “Adicionar sua Avaliação” e total de avaliações.
- **Posicionamento**: abaixo do título, acima do preço.
- **Interações**:
  - navegação para área de reviews;
  - clique para enviar avaliação.
- **Estado atual**:
  - `product.info.review`, `reviews.tab` e `product.review.form` são removidos no layout atual;
  - mesmo assim, há CSS dedicado e screenshots históricas/mobile aprovadas mostram esse bloco.
- **Responsividade**:
  - desktop/tablet: bloco inline ao lado ou abaixo do título;
  - mobile: aparece empilhado em card compacto.

### 7. Bloco de preço

- **Função**: destacar valor, desconto e condição visual de compra.
- **Conteúdo**:
  - preço principal;
  - preço antigo quando existe;
  - possível badge de desconto;
  - overlay de gate B2B em cenários restritos.
- **Posicionamento**: acima do box de compra, na coluna de informação.
- **Interações**:
  - nenhuma direta no valor;
  - em gate B2B, CTA para login/cadastro.
- **Estado atual**:
  - o layout preserva `product.info.price`;
  - o CSS de “modo básico” oculta visualmente `.product-info-price` e `.price-box`, sugerindo divergência entre intenção de layout e camadas de estilo.
- **Responsividade**:
  - desktop: card com destaque;
  - mobile: continua visível por captura aprovada e sticky CTA reutiliza o preço.

### 8. Indicador de estoque

- **Função**: comunicar disponibilidade.
- **Conteúdo**:
  - badge “Em estoque”, “Apenas X em estoque” ou crítico;
  - ponto animado de status.
- **Posicionamento**: logo acima do `box-tocart`.
- **Interações**: informativo.
- **Estado atual**:
  - renderizado no `addtocart.phtml`;
  - o CSS de modo básico o oculta em algumas variantes.
- **Responsividade**:
  - badge inline em desktop;
  - mobile aparece logo abaixo do resumo de reviews nas capturas.

### 9. SKU e microdados de compra

- **Função**: exibir código comercial/técnico.
- **Conteúdo**: SKU do produto e, em cenários B2B, variações de tabela/preço.
- **Posicionamento**: próximo ao preço.
- **Interações**: informativo.
- **Estado atual**:
  - `product.info.sku` e `product.info.stock.sku` foram removidos no layout;
  - existe CSS para `.awa-b2b-pdp-sku`, mas a camada atual tende a escondê-lo.

### 10. Box de compra

- **Função**: reunir quantidade e CTA principal.
- **Conteúdo**:
  - stepper de quantidade;
  - botão principal `#product-addtocart-button`;
  - eventuais child blocks adicionais do form.
- **Posicionamento**: abaixo do preço/estoque.
- **Interações**:
  - incremento/decremento de quantidade;
  - submit do add to cart;
  - validação do form Magento.
- **Estado atual**:
  - o template custom mantém stepper e botão;
  - o CSS de modo básico esconde a quantidade e deixa o CTA ocupar a largura total.
- **Responsividade**:
  - desktop: stepper + botão em bloco destacado;
  - mobile: o botão continua visível e pode ser espelhado pelo sticky CTA.

### 11. Gate B2B de compra

- **Função**: substituir o fluxo de compra por login/cadastro em contextos restritos.
- **Conteúdo**:
  - headline;
  - descrição;
  - CTA primário e secundário.
- **Posicionamento**: ocupa o lugar do box de compra quando `canAddToCart()` retorna falso.
- **Interações**: direciona para autenticação e/ou cadastro.
- **Responsividade**:
  - desktop: card na coluna direita;
  - mobile: ocupa o fluxo principal, antes do sticky CTA ser habilitado.

### 12. Descrição simplificada

- **Função**: substituir tabs padrão do Magento por um bloco único de descrição.
- **Conteúdo**:
  - título “Descrição”;
  - HTML da descrição longa ou, como fallback, da short description.
- **Posicionamento**: abaixo do grid principal.
- **Interações**: leitura apenas.
- **Estado atual**:
  - é o bloco efetivamente ativo no child;
  - não há múltiplas tabs no template atual, apenas um painel.
- **Responsividade**:
  - desktop: caixa ampla com padding generoso;
  - mobile: bloco contínuo abaixo do CTA.

### 13. Tabs customizadas e seções técnicas

- **Função**: abrigar descrição, especificações e reviews em versões mais completas da PDP.
- **Conteúdo previsto no CSS**:
  - navegação horizontal;
  - painéis;
  - tabela de especificações;
  - resumo/histograma de reviews.
- **Posicionamento**: abaixo da dobra principal.
- **Interações**:
  - clique nas tabs;
  - foco acessível via `aria-selected`;
  - leitura de painéis dinâmicos.
- **Estado atual**:
  - o CSS e os testes Playwright presumem múltiplas tabs;
  - o template ativo renderiza só descrição.
- **Conclusão**: há discrepância entre o desenho visual esperado e a implementação ativa.

### 14. Tabela de atributos / especificações

- **Função**: listar dados técnicos do produto.
- **Conteúdo**: pares atributo/valor.
- **Posicionamento**: historicamente abaixo da descrição ou dentro de tabs.
- **Interações**: informativo.
- **Estado atual**:
  - `product.attributes` e `product.attributes.above.fold` foram removidos;
  - screenshots históricas mostram tabela de marca/modelo/ano em algumas versões.

### 15. Compatibilidade / aplicação

- **Função**: informar com quais motos o item é compatível.
- **Conteúdo**:
  - marcas e modelos;
  - badges de posição/OEM/notas;
  - disclaimer final.
- **Posicionamento**: após `product.info.details`.
- **Interações**: leitura, sem ação direta.
- **Estado atual**:
  - o módulo Fitment adiciona esse bloco;
  - o módulo está desabilitado na configuração atual, mas há evidência visual histórica/mobile mostrando a seção “Compatibilidade / Aplicação”.
- **Responsividade**:
  - desktop: bloco largo abaixo da descrição;
  - mobile: card vertical com listas em pilha.

### 16. Sidebar de conversão

- **Função**: concentrar elementos auxiliares de conversão.
- **Conteúdo previsto no CSS/testes**:
  - card promocional;
  - botão de WhatsApp;
  - share.
- **Posicionamento**:
  - sticky no desktop a partir de 1200px;
  - abaixo do conteúdo principal em breakpoints menores.
- **Estado atual**:
  - o layout child remove `awa.pdp.b2b.sidebar`;
  - existe template `awa-pdp-sidebar.phtml`, mas sem wiring ativo encontrado.
- **Conclusão**: bloco planejado, porém aparentemente órfão no estado atual.

### 17. Ações secundárias

- **Função**: wishlist, compare, print, compartilhar e mailto.
- **Conteúdo**: links sociais/auxiliares.
- **Posicionamento**: historicamente em `product.info.extrahint`.
- **Interações**: compartilhar, comparar, salvar.
- **Estado atual**:
  - removidos no layout atual;
  - CSS ainda possui estilos para esse grupo, indicando legado.

### 18. Sticky CTA mobile

- **Função**: manter ação de compra sempre acessível no mobile.
- **Conteúdo**:
  - label “Comprar agora”;
  - preço atual;
  - botão “Comprar”.
- **Posicionamento**: fixo no rodapé da viewport móvel.
- **Interações**:
  - scroll automático até o add to cart original;
  - clique no CTA principal;
  - sincronização por MutationObserver com preço/estado do botão.
- **Comportamento**:
  - aparece quando a galeria sai da área visível;
  - não aparece em contextos de gate/login pendente;
  - respeita `prefers-reduced-motion`.
- **Evidência visual**:
  - `artifacts/playwright-smoke/latest-mobile-pdp-b2b-approved-debug.png`
  - `artifacts/playwright-smoke/latest-mobile-pdp-b2b-approved-sticky.png`

### 19. Related e upsell

- **Função**: aumentar descoberta e ticket médio.
- **Conteúdo**: sliders/listas de produtos.
- **Posicionamento**: final da PDP.
- **Estado atual**:
  - tema pai cria sliders;
  - tema child remove `catalog.product.related`, `product.info.related` e `product.info.upsell`.

### 20. JSON-LD e preload LCP

- **Função**: otimização técnica de SEO e performance.
- **Conteúdo**:
  - `awa.product.jsonld`;
  - preload da imagem principal.
- **Posicionamento**:
  - JSON-LD no conteúdo;
  - preload em `head.additional`.
- **Interações**: não visuais, mas influenciam renderização e indexação.

## Comportamento por breakpoint

### Desktop 1024–1440

- Header completo.
- Grid em duas colunas.
- Galeria fixa na esquerda.
- Coluna de compra com títulos maiores, preço destacado e CTA robusto.
- Sidebar de conversão é prevista apenas em larguras maiores, mas não há confirmação de renderização ativa no estado atual.

### Tablet ~768

- Mantém duas colunas em evidências históricas.
- Busca continua visível e ocupa grande área do topo.
- Título quebra mais cedo.
- A proximidade entre galeria e buy box aumenta, mas sem sobreposição aparente.

### Mobile 375–480

- Layout empilhado.
- Galeria assume largura quase total.
- Título e avaliações aparecem logo após a mídia.
- CTA principal continua visível no fluxo.
- Sticky CTA no rodapé reforça a compra quando o usuário desce para reviews/compatibilidade.

## Discrepâncias importantes

1. **Breadcrumbs**: screenshots históricas mostram breadcrumbs, mas o layout atual os remove.
2. **Reviews**: screenshots e CSS mostram avaliações ativas, porém o layout atual remove review summary, tab e form.
3. **Tabs**: testes e CSS pressupõem múltiplas tabs, mas o template ativo renderiza só um painel de descrição.
4. **Sidebar**: CSS e specs Playwright esperam sidebar de conversão, porém o layout remove a sidebar custom.
5. **Compatibilidade/Fitment**: há evidência visual e template ativo no módulo, mas o módulo está desabilitado hoje.

## Evidências visuais recomendadas

- Desktop 1440: `artifacts/header-audit-20260324-after/pdp-desktop1440.png`
- Tablet 768: `artifacts/header-audit-20260324-after/pdp-tablet768.png`
- Mobile 375: `artifacts/header-audit-20260324-after/pdp-mobile375.png`
- Mobile com sticky CTA e compatibilidade: `artifacts/playwright-smoke/latest-mobile-pdp-b2b-approved-debug.png`
- Mobile com review form + sticky CTA: `artifacts/playwright-smoke/latest-mobile-pdp-b2b-approved-sticky.png`
- Desktop B2B aprovado: `artifacts/playwright-smoke/latest-desktop-pdp-b2b-approved.png`

## Conclusão

A página de produto foi redesenhada para um fluxo mais direto de compra, mas o repositório ainda preserva sinais fortes de versões anteriores e de um modo B2B mais rico. Para documentação de produto e UI, a leitura correta é tratar a PDP em duas visões:

- **PDP ativa simplificada**: galeria + título + compra + descrição;
- **PDP estendida histórica/B2B**: avaliações, atributos, compatibilidade, sidebar e sticky CTA.

Isso evita confundir a intenção atual do layout com elementos que continuam presentes em CSS, testes e artifacts, mas já não estão necessariamente ligados ao runtime ativo.
