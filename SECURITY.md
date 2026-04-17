# Politica De Seguranca

## Escopo
Este repositorio contem uma loja Magento 2 com modulos customizados, integracoes ERP, fluxos B2B e customizacoes de tema. Toda vulnerabilidade deve ser tratada como informacao sensivel e reportada de forma privada.

## Como Reportar
Nao abra vulnerabilidades em issue publica.

Use um dos canais privados abaixo:
- mantenedor tecnico do projeto
- canal interno de incidentes de seguranca
- responsavel operacional da plataforma

Ao reportar, inclua:
- resumo do problema
- impacto estimado
- passos para reproduzir
- area afetada
- evidencias tecnicas
- sugestao de mitigacao, se houver

## O Que E Considerado Incidente
- exposicao de credenciais, tokens, chaves ou segredos
- falha de autenticacao ou autorizacao
- vazamento de dados pessoais, comerciais ou financeiros
- elevacao de privilegio
- injection, XSS, CSRF, SSRF, RCE ou path traversal
- falhas em integracoes externas que permitam abuso ou fraude

## Tempo De Resposta Recomendado
- triagem inicial: ate 1 dia util
- classificacao de severidade: ate 2 dias uteis
- plano de mitigacao: proporcional ao impacto

## Boas Praticas Obrigatorias
- nunca commite segredos ou credenciais
- valide toda entrada externa no backend
- escape saida quando aplicavel em templates
- nao exponha stack trace para usuario final
- registre logs sem dados sensiveis
- use DI, contratos claros e tratamento explicito de erro
- revise impacto de seguranca em PRs de autenticacao, checkout, ERP e dados de cliente

## Checklist De Validacao Antes De Merge
- [ ] sem segredos hardcoded
- [ ] sem credenciais em fixtures, logs ou exemplos
- [ ] inputs externos validados
- [ ] autorizacao revisada
- [ ] mensagens de erro seguras
- [ ] dependencias novas justificadas
- [ ] impacto em dados pessoais revisado

## Dependencias E Atualizacoes
- acompanhe advisories de Composer e do ecossistema Magento
- priorize atualizacoes de seguranca em extensoes e modulos customizados criticos
- documente mitigacoes temporarias quando nao for possivel corrigir imediatamente

## Observacoes Operacionais
- alteracoes em `app/etc/env.php` exigem comunicacao explicita
- cambios em cache, indexacao, filas e integracoes devem considerar impacto operacional
- toda vulnerabilidade corrigida deve gerar rastreabilidade em changelog interno ou ticket
