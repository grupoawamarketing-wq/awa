# Modelo De Ownership

## Objetivo
Definir ownership por dominio para:
- acelerar code review
- reduzir regressao em areas criticas
- garantir que mudancas sensiveis tenham revisao adequada

## Regras
- todo PR deve ter ao menos 1 aprovacao de owner da area afetada
- mudancas em areas criticas exigem 2 aprovacoes quando possivel
- estilos e formatacao sao responsabilidade do lint/formatter, nao do review

## Areas Criticas (Recomendado 2 Aprovacoes)
- checkout e pagamento
- autenticacao e cadastro
- integracoes ERP e sincronizacao
- SEO e metadata (SchemaOrg / OpenGraph)
- mudancas em `app/etc/` e configuracoes sensiveis

## Como Manter Atualizado
- atualize `.github/CODEOWNERS` quando novos modulos forem criados
- owners devem refletir pessoas ou times responsaveis
- revise o ownership a cada trimestre ou quando houver mudanca no time

## Convencao
- use owners por dominio (ex: `@time-b2b`, `@time-erp`) quando existir
- use owners individuais apenas como fallback
