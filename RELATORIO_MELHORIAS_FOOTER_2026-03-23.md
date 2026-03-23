# Relatorio - Melhorias Progressivas do Rodape (2026-03-23)

## Objetivo

Implementar evolucao segura e incremental no rodape do tema ativo, preservando as funcionalidades em producao e com rollback rapido.

## Analise Tecnica do Estado Atual

- O rodape esta centralizado em template versionado no tema filho.
- A composicao do rodape ja usa ViewModel para dados dinamicos.
- Havia base de experimentacao no header, sem paridade no rodape.

## Melhorias Implementadas Nesta Etapa

1. Instrumentacao de experimento no frontend:

- `footer.phtml` passou a expor:
  - `data-awa-footer-exp-enabled`
  - `data-awa-footer-exp-rollout`
  - `data-awa-footer-exp-seed`
- Script deterministico no cliente atribui variante:
  - `data-awa-footer-exp-variant = control|treatment`
  - classe `awa-footer-exp-b` para treatment

1. Protecao automatizada contra regressao:

- Expansao do teste unitario `FooterDataTest` para cobrir cenarios de fallback, normalizacao e seed/rollout.
- Novo teste de contrato `FooterExperimentContractTest` para validar:
  - Presenca de atributos de experimento no template.
  - Estrutura de configuracao exigida em `system.xml` e `config.xml`.

## Metricas Antes x Depois

### Qualidade

- Antes: cobertura limitada no `FooterDataTest`.
- Depois: suite com 16 testes e 44 assertions em verde.

### Risco Operacional

- Antes: sem sinalizacao de variante no DOM para rollout controlado.
- Depois: dados de rollout/seed/variante expostos no DOM com fallback para `control`.

## Deploy Incremental Recomendado

1. Fase 0: `enabled=0`, `rollout_percentage=0`
2. Fase 1: `enabled=1`, `rollout_percentage=5`
3. Fase 2: `rollout_percentage=20`
4. Fase 3: `rollout_percentage=50`
5. Fase 4: `rollout_percentage=100`

## Rollback

- Imediato: `enabled=0`
- Alternativo: `rollout_percentage=0`

## Comunicacao com Equipe

- Atualizacao por fase de rollout (5/20/50/100).
- Em cada checkpoint: erro frontend, engajamento no rodape, decisao de promover/manter/reverter.

## Validacao Executada

- Lint PHP: OK
- PHPUnit: OK (`FooterDataTest` + `FooterExperimentContractTest`)
- Resultado: `OK (16 tests, 44 assertions)`
