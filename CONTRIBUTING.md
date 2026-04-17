# Contributing

## Objetivo
Este repositório adota um conjunto minimo de padroes para manter previsibilidade em codigo, revisao, testes e releases. Toda contribuicao deve seguir estas diretrizes antes de abrir um PR.

## Escopo
As regras abaixo se aplicam principalmente a:
- modulos customizados em `app/code/GrupoAwamotos`
- modulos internos em `app/code/Awa` e `app/code/Ayo`
- tema customizado em `app/design/frontend/AWA_Custom/ayo_home5_child`
- automacoes, scripts e testes mantidos no repositorio

## Regras Basicas
- leia o modulo e suas dependencias antes de editar qualquer arquivo
- nunca use `ObjectManager` direto em codigo de producao
- use `declare(strict_types=1)` em novos arquivos PHP
- siga `PSR-12`, `phpcs.xml` e `.editorconfig`
- evite placeholders, mocks incompletos e comentarios do tipo `TODO` sem implementacao
- nao altere `vendor/` nem core do Magento

## Convencoes De Nomenclatura
- classes e interfaces: `PascalCase`
- metodos e variaveis: `camelCase`
- constantes: `SCREAMING_SNAKE_CASE`
- arquivos frontend: `kebab-case`
- nomes devem refletir intencao, nao abreviacoes vagas

## Estrutura Recomendada
Ao criar ou evoluir modulos Magento, prefira a organizacao:

```txt
app/code/Vendor/Modulo/
├── Api/
├── Api/Data/
├── Block/
├── Controller/
├── Cron/
├── Helper/
├── Model/
├── Observer/
├── Plugin/
├── etc/
└── view/
```

## Commits
Use `Conventional Commits`:

```txt
feat(b2b): adiciona validacao de CNPJ no cadastro
fix(theme): corrige carregamento de css no checkout
refactor(schemaorg): extrai geracao de open graph para view model
test(fitment): cobre filtro por modelo de moto
ci(workflows): adiciona esteira inicial de quality gates
```

Regras:
- titulo curto, objetivo e no imperativo
- um assunto principal por commit
- descreva o motivo no corpo quando a mudanca nao for obvia

## Branches
Padrao sugerido:

```txt
feature/nome-curto
fix/nome-curto
refactor/nome-curto
hotfix/nome-curto
chore/nome-curto
```

## Validacoes Minimas Antes Do PR
- sintaxe PHP valida
- `phpcs.xml` sem novos desvios relevantes
- formatter aplicado quando necessario
- testes afetados executados ou justificativa registrada no PR
- impacto em cache, setup, indexacao ou deploy descrito quando aplicavel

## Comandos Recomendados
Validacao local minima:

```bash
composer quality:install-hooks
composer quality:php-syntax
composer quality:governance
composer quality:commits
composer quality:docs
composer quality:all
php -l app/code/GrupoAwamotos/Modulo/Arquivo.php
phpcs --standard=phpcs.xml app/code/GrupoAwamotos
php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff
```

Para habilitar a validacao local de mensagens de commit, execute ao menos uma vez:

```bash
composer quality:install-hooks
```

Validacoes operacionais em ambiente Magento:

```bash
tail -20 var/log/system.log
tail -20 var/log/exception.log
sudo -u www-data php bin/magento cache:clean
```

## Pull Requests
Todo PR deve conter:
- objetivo da mudanca
- impacto funcional e tecnico
- plano de teste
- risco conhecido
- evidencias quando a mudanca afetar UI, API ou integracoes

## Code Review
O review deve priorizar:
- regressao funcional
- seguranca
- aderencia a arquitetura Magento
- clareza dos nomes e do fluxo
- cobertura de testes em fluxos criticos

Use o checklist do template de PR e o guia em `docs/development-standards.md`.
Para revisoes detalhadas, use tambem `docs/code-review-checklist.md`.

## Versionamento
Adote `SemVer`:
- `MAJOR`: quebra de compatibilidade
- `MINOR`: nova funcionalidade compativel
- `PATCH`: correcao compativel

Mudancas em contrato de API, schema ou comportamento publico devem registrar impacto de versao no PR.

## Documentacao
Atualize documentacao quando houver mudanca em:
- contrato de API
- fluxo operacional
- configuracao obrigatoria
- comportamento de modulo reutilizavel

Guias operacionais complementares:
- `docs/release-process.md`
- `docs/code-review-checklist.md`
- `docs/api-standards.md`
- `docs/testing-strategy.md`
- `docs/module-template.md`
- `docs/frontend-standards.md`
- `docs/smoke-checklist.md`
- `docs/ownership-model.md`
- `docs/b2b-adoption.md`

## Seguranca
Nunca:
- comite segredos ou credenciais
- exponha dados sensiveis em logs
- silencie excecoes sem tratamento
- aceite input externo sem validacao

Para padroes completos, consulte `docs/development-standards.md`.
