# Adoção Dos Padroes No Modulo B2B

## Objetivo
Transformar os padroes de governanca do repositorio em pratica diaria no dominio `GrupoAwamotos/B2B`, sem refactor amplo e sem atrito desnecessario.

## Escopo
Este guia cobre:
- padronizacao incremental do modulo `app/code/GrupoAwamotos/B2B`
- checklists de PR e smoke por area (auth, cadastro, checkout, admin)
- validacoes minimas locais e em CI

## Ponto De Partida (O Que Ja Existe)
O modulo B2B ja contem:
- controllers (frontend e admin)
- blocks e templates (PHTML)
- plugins (checkout, customer, catalog, admin)
- UI components e dataproviders
- layouts e assets em `view/*`

Esse modulo e considerado critico porque atua em:
- autenticacao e cadastro B2B
- escondendo/exibindo preco e regras de compra
- customizacoes de checkout e carrinho
- aprovacao e fluxos admin

## Como Adotar (Passo A Passo)

### 1) Padronizar Sem Refactor Grande
- nao renomear classes publicas sem necessidade
- manter compatibilidade de rotas e handles
- focar em padroes para novos arquivos e mudancas pontuais

### 2) Garantir Qualidade Minima Em Cada PR
Antes de abrir PR que mexa no B2B:
```bash
composer quality:php-syntax
composer quality:governance
composer quality:docs
composer quality:commits
```

Se a mudanca for em PHP do modulo:
```bash
phpcs --standard=phpcs.xml app/code/GrupoAwamotos/B2B
```

### 3) Checklist De PR (B2B)
- [ ] descreveu impacto em auth/cadastro/checkout/admin
- [ ] rodou smoke do fluxo afetado (ver `docs/smoke-checklist.md`)
- [ ] nao adicionou `ObjectManager` direto
- [ ] tratamento de erro revisado (sem engolir excecao)
- [ ] logs sem dados sensiveis
- [ ] verificou impacto em cache e indexacao quando aplicavel

## Padroes Por Subdominio

### Auth / Cadastro B2B
- validacao de input no backend
- mensagens de erro seguras e previsiveis
- evitar dependencias de seletor fragil no frontend
- documentar fluxo e riscos em PR

### Checkout / Precos / Restricoes
- plugins em checkout precisam ser pequenos e bem nomeados
- impacto em performance deve ser considerado (plugins rodam muito)
- qualquer mudanca deve ter smoke do carrinho e checkout

### Admin (Aprovacao / Credito / CNPJ)
- UI components com naming consistente
- data providers com validacao e fallbacks
- logs auditaveis quando houver mudanca de status, credito, aprovacao

## Backlog De Padronizacao (Incremental)
Itens tipicos que valem atacar quando surgirem mudancas na area:
- separar logica pesada de PHTML para `ViewModel`/Service
- reduzir plugins "faz tudo" em funcoes menores
- padronizar respostas JSON em endpoints `Controller/Ajax/*`
- adicionar testes de regressao para bugs recorrentes

## Smoke Recomendado (B2B)
Use o checklist base e complete com:
- login B2B
- cadastro B2B (etapas e validacoes)
- carrinho e checkout com regras B2B
- tela admin de aprovacao/credito quando alterada

Referencias:
- `docs/smoke-checklist.md`
- `docs/code-review-checklist.md`
- `docs/frontend-standards.md`
- `docs/api-standards.md`
- `docs/testing-strategy.md`

## Ownership
Mudancas em B2B devem respeitar owners definidos em `.github/CODEOWNERS`.
Quando a mudanca envolver checkout/auth/ERP, trate como "area critica" (idealmente 2 aprovacoes).
