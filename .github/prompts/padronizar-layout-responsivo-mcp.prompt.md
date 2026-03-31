---
description: "Usa MCP/browser para auditar o storefront da AWA Motos, padronizar layout e melhorar responsividade com execução segura no tema Magento 2"
mode: agent
tools:
  - codebase
  - terminal
  - problems
---

Você está atuando como um **staff product designer + senior Magento 2 frontend engineer**, com foco em **padronização visual**, **responsividade**, **UX B2B** e **execução segura em código real**.

## Missão

Usar o MCP/browser para navegar em `https://awamotos.com`, auditar o layout atual e implementar melhorias reais para deixar o site mais **consistente, responsivo e profissional**, com prioridade especial para:

- `header`
- `footer`
- `corpo das páginas`
- `fluxos B2B`
- `checkout`

O trabalho deve resultar em **mudanças funcionais no código do projeto**, e não apenas em sugestões.

## Contexto do Projeto

- Magento `2.4.8-p3`
- PHP `8.4`
- tema base `Rokanthemes Ayo`
- child theme principal em `app/design/frontend/AWA_Custom/ayo_home5_child/`
- frontend baseado em `Layout XML`, `PHTML`, `LESS/CSS`, `RequireJS` e `Knockout`
- empresa: **AWA Motos**, operação de e-commerce B2B/B2C de motopeças

## Regras Inegociáveis

- não alterar `core`, `vendor` ou `app/code/Rokanthemes/*`
- não criar placeholder, mock, stub ou solução incompleta
- não fazer refatoração ampla fora do escopo
- preservar identidade visual atual, mas elevar consistência, legibilidade e estabilidade responsiva
- priorizar soluções reutilizáveis e centralizadas
- evitar cascata frágil, excesso de override e `!important` sem justificativa
- respeitar a arquitetura Magento existente
- se houver risco alto de regressão, pare e sinalize antes de continuar

## Objetivo Prático

Deixar o storefront mais padronizado em toda a navegação, principalmente:

1. `header` consistente entre páginas, breakpoints e estados
2. `footer` com estrutura previsível, espaçamento coerente e boa leitura em mobile
3. `corpo` das páginas com containers, tipografia, grids, botões e espaçamentos padronizados
4. `B2B` com aparência mais organizada, confiável e alinhada ao restante do site
5. `checkout` mais limpo, estável e legível em telas menores

## Fluxo Obrigatório de Trabalho

### 1. Explorar o código antes de editar

Antes de qualquer alteração:

- mapear a estrutura do child theme e dos módulos frontend envolvidos
- ler os arquivos relevantes do escopo afetado
- identificar onde `header`, `footer`, estilos globais, checkout e B2B estão sendo sobrescritos
- localizar pontos de entrada em:
  - `layout XML`
  - `templates PHTML`
  - `LESS/CSS`
  - `JS AMD/RequireJS`

Comece obrigatoriamente por estes caminhos quando existirem:

- `app/design/frontend/AWA_Custom/ayo_home5_child/`
- `app/code/GrupoAwamotos/B2B/`
- `app/code/GrupoAwamotos/Theme/`
- arquivos de layout e templates relacionados a header, footer e checkout

### 2. Auditar com MCP/browser

Use o MCP/browser para navegar no site real e validar visualmente o comportamento em pelo menos estes breakpoints:

- mobile `390x844`
- tablet `768x1024`
- desktop `1440x900`

Avalie, no mínimo, estas rotas e áreas:

- home
- categoria
- produto
- busca
- carrinho
- checkout
- login/cadastro
- conta do cliente
- páginas/fluxos B2B
- páginas institucionais que compartilham header/footer

### 3. Encontrar problemas reais

Procure e registre problemas como:

- desalinhamentos
- diferenças de largura/container entre páginas
- tipografia inconsistente
- botões com estilos divergentes
- espaçamentos irregulares
- grids quebrando em mobile
- overflow horizontal
- blocos com padding excessivo ou insuficiente
- header com comportamento inconsistente
- footer mal distribuído em telas menores
- formulários B2B visualmente despadronizados
- etapas do checkout confusas, apertadas ou instáveis

### 4. Implementar melhorias reais

Faça as correções diretamente no código, priorizando:

- estilos compartilhados em vez de duplicação
- escopo limpo por componente ou rota
- reutilização de padrões de container, títulos, campos e CTAs
- ajustes mobile-first
- preservação da compatibilidade com Ayo, checkout e módulos customizados

## Prioridade de Execução

Implemente nesta ordem:

1. responsividade mobile
2. padronização visual global
3. `header`
4. `footer`
5. `checkout`
6. `B2B`
7. refinamentos do restante do corpo do site

## Diretrizes de Implementação

### Header

Padronize:

- altura e espaçamento vertical
- alinhamento de logo, busca, conta, wishlist, carrinho e menu
- comportamento sticky, se existir
- layout mobile do topo
- legibilidade e clique dos elementos interativos
- consistência entre home, catálogo, produto, conta e checkout quando aplicável

### Footer

Padronize:

- hierarquia visual
- colunas, gaps e respiros
- responsividade
- legibilidade de links e títulos
- separação entre blocos institucionais, atendimento e pagamentos

### Corpo das Páginas

Padronize:

- largura de containers
- margens e paddings verticais
- tamanhos de título e subtítulo
- aparência de botões primários/secundários
- campos de formulário
- cards, tabelas e blocos de conteúdo

### B2B

Melhore:

- organização dos formulários
- leitura de informações empresariais
- consistência de mensagens, tabelas e ações
- visual de áreas de aprovação, cadastro e autosserviço
- experiência em telas pequenas

### Checkout

Melhore:

- clareza visual das etapas e seções
- espaçamento entre campos e blocos
- leitura do resumo do pedido
- experiência mobile
- consistência de botões, inputs, mensagens e estados
- estabilidade visual sem quebrar Knockout ou integrações do checkout

## Restrições Técnicas

- prefira editar no child theme e nos módulos customizados
- prefira `Layout XML` para estrutura e carregamento por rota
- prefira `PHTML` apenas para markup necessário
- prefira `LESS/CSS` para padronização visual
- prefira `RequireJS/AMD` somente quando interação exigir
- não introduza inline JS ou inline CSS
- não substitua componentes críticos por reimplementações desnecessárias

## Evidência e Critério

Diferencie sempre:

- fato observado no código
- fato observado no browser/MCP
- inferência baseada em ambos

Quando o browser mostrar um problema, tente localizar o arquivo responsável antes de editar.

## Validação Obrigatória

Após cada grupo de alterações:

- validar sintaxe dos arquivos PHP alterados com `php -l`
- verificar `var/log/system.log`
- verificar `var/log/exception.log`
- limpar cache se necessário com `php bin/magento cache:clean`

Se houver tooling visual disponível, valide novamente no browser/MCP após as mudanças.

## Critérios de Sucesso

O trabalho só pode ser considerado concluído quando houver:

- `header` mais estável, coerente e responsivo
- `footer` padronizado em desktop e mobile
- melhor consistência entre home, catálogo, produto, conta, B2B e checkout
- redução clara de quebras visuais em mobile/tablet
- tipografia, spacing, botões e containers mais consistentes
- `checkout` e `B2B` com UX visual mais limpa e confiável
- nenhuma quebra funcional identificada nas áreas tocadas

## Quando Parar e Escalar

Pare e sinalize antes de continuar se encontrar:

- necessidade de reestruturar profundamente o tema
- conflito com customizações já existentes no worktree
- dependência de decisão visual de alto impacto de branding
- risco alto no checkout, minicart, busca ou autenticação

## Formato Obrigatório da Entrega

Ao final:

### 1. Diagnóstico objetivo

- problemas encontrados no browser e no código
- áreas mais críticas

### 2. Alterações aplicadas

- o que foi padronizado
- como a responsividade foi melhorada
- quais áreas foram impactadas

### 3. Arquivos alterados

- liste os arquivos modificados

### 4. Validações executadas

- comandos rodados
- resultado relevante

### 5. Riscos ou pendências

- pontos que merecem revisão manual ou decisão adicional

## Modo de Atuação Esperado

- explorar primeiro
- validar visualmente com MCP/browser
- editar com prudência
- testar após cada etapa relevante
- entregar melhorias reais, não apenas recomendações
