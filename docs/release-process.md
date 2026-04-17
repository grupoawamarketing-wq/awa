# Processo De Release E Deploy (Magento)

## Objetivo
Padronizar release, deploy e rollback para reduzir risco operacional e garantir rastreabilidade.

## Principios
- prefira releases pequenas e frequentes
- deploy deve ser repetivel e documentado
- rollback deve ser possivel sem improviso
- toda mudanca critica precisa de evidencias e plano de reversao

## Escopo
Aplica-se a:
- modulos customizados em `app/code/*`
- tema em `app/design/frontend/*`
- configuracoes de build, CI e scripts do repositorio

## Checklist Pre Release
- [ ] PR aprovado e CI verde
- [ ] impactos descritos no PR: cache, indexacao, setup, static content, schema, API
- [ ] `RELEASE_TEMPLATE.md` preenchido quando aplicavel
- [ ] sem segredos em codigo, logs ou configuracoes
- [ ] logs e monitoramento considerados
- [ ] plano de rollback documentado

## Tipos De Mudanca E Validacoes

### Mudanca De PHP (modulos)
- `setup:upgrade` quando houver alteracao de `db_schema.xml`, `di.xml`, `events.xml` ou registros de modulo
- `di:compile` quando houver impacto em DI, interceptors, plugins ou proxies

### Mudanca De Frontend (tema)
- `static-content:deploy` quando houver alteracao em CSS, JS, templates e layout que dependam de build/asset
- validar pagina critica afetada (home, plp, pdp, carrinho, checkout, login)

### Mudanca De Configuracao
- mudanças em `app/etc/env.php` exigem comunicacao e janela controlada
- valide cache e indexers ao final

## Comandos Operacionais (Padrao)
Importante: execute como `www-data` em ambiente Magento.

```bash
sudo -u www-data php bin/magento maintenance:enable

sudo -u www-data php bin/magento cache:flush
sudo -u www-data php bin/magento setup:upgrade
sudo -u www-data php bin/magento setup:di:compile
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR en_US -f
sudo -u www-data php bin/magento indexer:reindex

sudo -u www-data php bin/magento cache:flush
sudo -u www-data php bin/magento maintenance:disable
```

## Verificacoes Pos Deploy
- [ ] `var/log/exception.log` sem novos erros
- [ ] `var/log/system.log` sem erros criticos
- [ ] paginas criticas renderizando corretamente
- [ ] checkout e login funcionando
- [ ] integracoes criticas sem falha (ERP, fila, busca, redis)

## Rollback
Escolha a estrategia conforme o tipo de mudanca:

### Codigo (PHP/tema)
- reverter commit(s) via git para o estado anterior
- repetir os passos de deploy

### Schema / Dados
- prefira mudancas compatíveis e migrações seguras
- se nao houver rollback automatico, documente mitigacao e procedimento manual

### Configuracao
- reverter alteracoes em configuracao
- flush de cache e revalidacao

## Comunicacao
- releases de impacto devem avisar stakeholders
- registre no changelog interno ou ticket
- mantenha rastreabilidade: PR, release, data, responsavel, risco e validacao
