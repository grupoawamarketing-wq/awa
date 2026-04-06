# 🤖 VS Code Copilot — Kit de Autonomia Máxima

## O que é isso?

Um kit completo de configuração para transformar o GitHub Copilot Agent no VS Code em um programador autônomo que:
- Não para pra pedir permissão a cada ação
- Conhece seu projeto, stack e regras de código
- Implementa código real (zero mocks/placeholders)
- Corrige erros automaticamente
- Tem personas especializadas para diferentes tarefas

---

## 📦 Conteúdo do Kit

### settings.json
Configuração completa do VS Code com todas as 50+ settings otimizadas para autonomia máxima. Inclui auto-approve de terminal com lista granular de comandos seguros/bloqueados.

### AGENTS.md
Arquivo universal de instruções reconhecido por múltiplos agents (Copilot, Claude Code, Cline, Cursor). Define ambiente, regras de código, e proibições.

### .github/copilot-instructions.md
Instruções globais específicas do Copilot. Define stack, padrões de código, naming, estrutura de pastas, e contexto de negócio.

### .github/agents/ (6 Custom Agents)

| Agent | Comando | Função |
|-------|---------|--------|
| **Awa** | `/agents` → Awa | Agente principal AWA Motos — Magento 2 especialista |
| **Implementador** | `/agents` → Implementador | Implementa features completas com código real |
| **Revisor** | `/agents` → Revisor | Revisa código sem modificar |
| **Arquiteto** | `/agents` → Arquiteto | Planeja arquitetura antes de implementar |
| **Debugger** | `/agents` → Debugger | Diagnostica causa raiz antes de corrigir |
| **Explore** | subagent read-only | Explora codebase sem modificar arquivos |

### .github/instructions/ (9 Instruction Files)

| Arquivo | Aplica a | Função |
|---------|----------|--------|
| codacy | `**` | Análise estática automática com Codacy CLI |
| controllers | `**/Controller/**/*.php` | Regras para Controllers Magento |
| frontend-js-css | `**/web/js/**`, `**/web/css/**` | Regras para JS (RequireJS/Knockout) e LESS |
| layout-xml | `**/layout/**/*.xml` | Regras para Layout XMLs |
| magento-database | `db_schema.xml`, `ResourceModel/**` | Declarative Schema e queries |
| observers-plugins-cron | `Observer/`, `Plugin/`, `Cron/` | Event-driven e jobs |
| phtml-templates | `**/*.phtml` | Templates — escape de XSS, Blocks |
| services-api | `Model/`, `Api/`, `Helper/` | Service Contracts e Repository Pattern |
| tests | `**/Test/**`, `**/tests/**` | PHPUnit para Magento 2 |

### .github/prompts/ (23 Slash Commands)

| Comando | Função |
|---------|--------|
| `/criar-modulo` | Novo módulo GrupoAwamotos completo |
| `/criar-crud` | CRUD completo (db_schema + Model + Repository + Admin Grid) |
| `/implementar-cli` | Comando `bin/magento` em módulo existente |
| `/implementar-api` | Integração completa com API externa |
| `/depurar-erp` | Diagnóstico de problemas no módulo ERPIntegration |
| `/corrigir-bug` | Debug com diagnóstico de causa raiz |
| `/refatorar` | Refatoração segura |
| `/auditar-projeto` | Auditoria completa do projeto |
| `/criar-componente` | Componente Knockout.js/PHTML |
| `/design-fase-01..10` | Design System AWA — fases de UI progressiva |
| `/elevar-ui-ux-premium` | Elevação premium de UI/UX |
| `/modernizar-layout-completo` | Modernização de layout |
| `/ux-designer` | Consultoria UX para o projeto |

### .github/skills/ (2 Skills)

| Skill | Carregamento | Função |
|-------|----------|--------|
| `skilawa` | automático (descrição match) | Padrões AWA — criar módulo, Observer, Plugin, CLI, ERP, B2B |
| `design-system` | automático (CSS/LESS/PHTML) | Design System AWA — tokens, BEM, deploy |

---

## 🚀 Como Instalar

### Opção 1: Script automático
```bash
bash setup-copilot.sh /caminho/do/seu/projeto
```

### Opção 2: Manual
1. Copie a pasta `.github/` para a raiz do seu projeto
2. Copie `AGENTS.md` para a raiz do seu projeto
3. Copie o conteúdo de `settings.json` para suas settings do VS Code

### Settings do VS Code
1. `Ctrl+Shift+P` → "Open User Settings (JSON)"
2. Cole o conteúdo do `settings.json` (merge com suas settings existentes)
3. `Ctrl+Shift+P` → "Reload Window"

---

## 🎯 Como Usar

### Agent Mode básico
1. Abra o Chat do Copilot (`Ctrl+L`)
2. Selecione "Agent" no dropdown de modo
3. Digite seu pedido e deixe o agent trabalhar

### Slash Commands
1. No chat, digite `/` e selecione o comando
2. Exemplo: `/implementar-api` e descreva qual API integrar

### Custom Agents
1. No chat, digite `/agents` ou clique no dropdown de agents
2. Selecione o agent especializado (Implementador, Revisor, etc.)
3. O agent vai seguir as regras específicas da persona

### Dica de Ouro
Para máxima autonomia, use o agent dentro de um **Dev Container**. Isso permite ativar `chat.tools.autoApprove: true` com segurança total, pois tudo roda isolado.

---

## ⚠️ Personalização

O `copilot-instructions.md` e `AGENTS.md` estão configurados para Magento 2.4.8-p3 + PHP 8.4 + AWA Motos. Para adaptar a outro projeto:

- Altere o namespace (`GrupoAwamotos` → seu namespace)
- Ajuste os comandos bin/magento conforme sua versão
- Personalize o contexto de negócio em `copilot-instructions.md`
- Os agents e prompts seguem padrões Magento 2 genéricos

---

## 📝 Notas

- Settings são para **VS Code Workspace** — aplicam apenas ao projeto atual
- Se quiser aplicar globalmente, coloque em User Settings
- Instruction files são lidos automaticamente pelo Copilot
- AGENTS.md é reconhecido por Copilot, Claude Code, e Cursor
- Prompts aparecem como slash commands no chat
- Custom agents aparecem no dropdown de agents

Criado e evoluído por Jess @ AWA Motos — 2026

---

## Atualizações Técnicas — Header Ayo Home5 (2026-03-23)

- Refatoração de semântica no header com landmarks e atributos ARIA para navegação, busca e minicart.
- Padronização do comportamento responsivo do menu hamburger com fallback em JavaScript e sincronização de `aria-expanded`.
- Otimização de performance no minicart com inicialização deferida em arquivo dedicado para reduzir trabalho no carregamento inicial.
- Melhoria de busca com `aria-busy`, painel de sugestões com status em `aria-live` e estado visual controlado para digitação/autocomplete.
- Lazy loading de elementos não críticos do header via loader em idle (`requestIdleCallback` com fallback).
- Reforço de compatibilidade cross-browser com detecção de suporte a listeners passivos e fallback para assinatura tradicional.
- Ajustes visuais com transições suaves e respeito a `prefers-reduced-motion`.
- Cobertura de testes unitários ampliada no `HeaderData` para cenários de normalização de paths e falhas seguras.

### Arquivos alterados nesta rodada

- `app/design/frontend/AWA_Custom/ayo_home5_child/Rokanthemes_Themeoption/templates/html/header.phtml`
- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Search/templates/form.mini.phtml`
- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Checkout/templates/cart/minicart.phtml`
- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-custom-js-loader.phtml`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-header-a11y-performance.js`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-minicart-defer-init.js`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-header-professional.less`
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-search-professional.less`
- `app/code/GrupoAwamotos/Theme/ViewModel/HeaderData.php`
- `app/code/GrupoAwamotos/Theme/Test/Unit/ViewModel/HeaderDataTest.php`

### Validação executada

- `php -l` nos templates e classes alteradas.
- `node --check` nos arquivos JS novos/refatorados.
- `make ayo-child-js-check`.
- `phpunit` para testes unitários de `HeaderData` e `FooterData`.
- `bin/magento cache:flush` após alterações.

### Rollout incremental, A/B e reversão

- Configuração adicionada em `Stores > Configuration > AWA Motos > Tema & Contato > Header Experimentação`.
- Feature flag: `grupoawamotos_theme/header_experiment/enabled`.
- Percentual de rollout: `grupoawamotos_theme/header_experiment/rollout_percentage`.
- Seed de variação: `grupoawamotos_theme/header_experiment/variant_seed`.
- Variação persistida por visitante via `localStorage` com bucket fixo (A/B estável).
- Exposição enviada para `dataLayer` com evento `awa_header_experiment_exposure`.
- Eventos adicionais para instrumentação de conversão:
  - `awa_header_nav_toggle_click`
  - `awa_header_search_focus`
  - `awa_header_search_submit`
  - `awa_header_minicart_click`
- Reversão imediata: definir `enabled=0` ou `rollout_percentage=0` e limpar cache.
