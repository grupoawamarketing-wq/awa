# Checklist De Code Review

## Objetivo
Padronizar o review para reduzir regressao, melhorar seguranca e aumentar previsibilidade de entrega.

## Regras Gerais
- o review foca em comportamento, risco e manutencao
- estilo automatico e responsabilidade de lint/formatter
- comentarios devem ser objetivos e acionaveis

## Checklist Geral
- [ ] objetivo do PR esta claro
- [ ] mudanca e pequena e coesa
- [ ] nomes estao claros e consistentes
- [ ] tratamento de erro e adequado
- [ ] logs sao uteis e nao vazam dados sensiveis
- [ ] documentacao foi atualizada quando aplicavel
- [ ] plano de teste foi executado ou justificado

## Checklist Magento (Backend)
- [ ] sem `ObjectManager` direto
- [ ] DI via construtor
- [ ] service contracts e repositories quando aplicavel
- [ ] plugins/observers com responsabilidade clara
- [ ] exceptions nao sao engolidas sem acao
- [ ] impacto em cache, indexacao e setup foi considerado

## Checklist Magento (Frontend)
- [ ] layout XML e templates nao quebram pagina
- [ ] JS inicializa via `x-magento-init` ou `data-mage-init` corretamente
- [ ] output em PHTML esta escapado quando aplicavel
- [ ] CSS esta escopado e nao vaza para paginas nao relacionadas
- [ ] impacto em performance foi considerado (bundles, carregamento, bloqueio de render)

## Checklist API
- [ ] status codes corretos
- [ ] validacao de input no backend
- [ ] retorno de erro padronizado
- [ ] autorizacao revisada
- [ ] compatibilidade preservada ou versao documentada

## Checklist Banco / Schema
- [ ] mudanca de schema e segura
- [ ] indices considerados para colunas usadas em busca/join
- [ ] migracao reversivel ou plano de rollback definido
- [ ] impacto em volume de dados avaliado

## Checklist Seguranca
- [ ] sem segredos hardcoded
- [ ] sem dados pessoais em logs
- [ ] protecao contra injection/XSS/CSRF considerada
- [ ] rate limiting considerado em pontos sensiveis
- [ ] mensagens de erro nao vazam detalhes internos

## Checklist Testes
- [ ] testes novos quando necessario
- [ ] teste de regressao para bug recorrente
- [ ] cobertura proporcional ao risco
- [ ] testes sao deterministas e legiveis

## Saida Do Review
Antes de aprovar, confirme:
- [ ] riscos conhecidos estao listados
- [ ] validacoes foram executadas
- [ ] criterio de aceite foi atendido
