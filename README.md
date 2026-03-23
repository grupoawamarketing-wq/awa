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

### .github/agents/ (5 Custom Agents)

| Agent | Comando | Função |
|-------|---------|--------|
| **Implementador** | `/agents` → Implementador | Implementa features completas com código real |
| **Revisor** | `/agents` → Revisor | Revisa código sem modificar |
| **Arquiteto** | `/agents` → Arquiteto | Planeja antes de implementar |
| **Debugger** | `/agents` → Debugger | Diagnostica causa raiz antes de corrigir |
| **MercadoLivre** | `/agents` → MercadoLivre | Especialista em API e SEO do ML |

### .github/instructions/ (4 Instruction Files)

| Arquivo | Aplica a | Função |
|---------|----------|--------|
| react-components | `*.tsx` | Regras para componentes React |
| services-api | `services/**/*.ts` | Regras para services e APIs |
| tests | `*.test.ts, *.spec.ts` | Regras para testes |
| prisma-database | `*.prisma, prisma/**` | Regras para Prisma/DB |

### .github/prompts/ (7 Slash Commands)

| Comando | Função |
|---------|--------|
| `/implementar-api` | Integração completa com API externa |
| `/criar-crud` | CRUD completo (DB + API + Service + Hook) |
| `/criar-componente` | Componente React com testes |
| `/refatorar` | Refatoração segura com testes |
| `/corrigir-bug` | Debug com diagnóstico de causa raiz |
| `/auditar-projeto` | Auditoria completa do projeto |
| `/otimizar-anuncio-ml` | Otimização de anúncio Mercado Livre |

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

O `copilot-instructions.md` vem configurado para a stack React + TypeScript + Node.js. **Personalize** para cada projeto:

- Mude a stack se for diferente (Vue, Angular, Python, etc.)
- Ajuste os comandos (npm → pnpm, yarn, etc.)
- Adicione contexto específico do projeto
- Ajuste a estrutura de pastas

Os agents e prompts são genéricos o suficiente para funcionar em qualquer projeto TypeScript/React, mas personalize conforme necessário.

---

## 📝 Notas

- Settings são para **VS Code Workspace** — aplicam apenas ao projeto atual
- Se quiser aplicar globalmente, coloque em User Settings
- Instruction files são lidos automaticamente pelo Copilot
- AGENTS.md é reconhecido por Copilot, Claude Code, e Cursor
- Prompts aparecem como slash commands no chat
- Custom agents aparecem no dropdown de agents

Criado por Claude para Jess @ AWA Motos — Fevereiro 2026

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
