---
description: "Diagnostica e corrige problemas no módulo GrupoAwamotos_ERPIntegration — falha de conexão SQL Server, sync de estoque/catálogo/pedidos, erros de cron, mapeamento de dados"
agent: "Debugger"
tools:
  - codebase
  - execute
  - problems
---

Diagnostique e corrija o problema no módulo `GrupoAwamotos_ERPIntegration`.

## Variáveis

- **Sintoma:** `$SINTOMA` (ex: "estoque não atualiza", "pedidos não sincronizam", "erro de conexão")
- **Período:** `$PERIODO` (ex: "desde ontem às 14h", "após deploy de hoje")

## Passo 1 — Coletar evidências

```bash
# Log dedicado do ERP
tail -100 var/log/erp_integration.log

# Erros gerais
tail -50 var/log/exception.log | grep -i erp
tail -50 var/log/system.log | grep -i erp

# Status dos jobs cron
mysql -u"$MAGENTO_DB_USER" -p"$MAGENTO_DB_PASS" "$MAGENTO_DB_NAME" \
  -e "SELECT job_code, status, messages, scheduled_at, executed_at, finished_at
      FROM cron_schedule
      WHERE job_code LIKE '%erp%'
      ORDER BY scheduled_at DESC LIMIT 20;"
```

## Passo 2 — Checar conexão SQL Server

```bash
# Teste de conexão via CLI
php bin/magento erp:connection:test

# Se falhar, verificar extensão
php -m | grep sqlsrv

# Verificar credenciais em env.php
grep -A10 "erp" app/etc/env.php
```

## Passo 3 — Analisar o módulo

Inspecionar em ordem:
1. `app/code/GrupoAwamotos/ERPIntegration/etc/crontab.xml` — jobs registrados
2. `app/code/GrupoAwamotos/ERPIntegration/Cron/` — classes de cron
3. `app/code/GrupoAwamotos/ERPIntegration/Model/Sync/` — lógica de sync
4. `app/code/GrupoAwamotos/ERPIntegration/Model/Connection/` — conexão SQL Server

## Passo 4 — Diagnóstico por sintoma

### Estoque não atualiza
- Verificar cron `erp_sync_stock` no `cron_schedule`
- Checar `Model/Sync/Stock.php` — query SQL Server e mapeamento `sku → qty`
- Verificar se `cataloginventory_stock_item` está sendo atualizado

### Pedidos não sincronizam para o ERP
- Verificar Observer de `sales_order_place_after` (se existir)
- Checar fila de pedidos pendentes: `php bin/magento erp:sync:orders --pending`
- Verificar tabela intermediária de pedidos enviados ao ERP

### Erro de conexão
- Checar se `sqlsrv` PHP extension está carregada
- Verificar host, porta, credenciais em `app/etc/env.php`
- Testar conectividade: `timeout 5 bash -c "echo > /dev/tcp/ERP_HOST/1433" && echo OK`

### Dados duplicados / mapeamento incorreto
- Rever queries no `Model/Sync/` — joins e filtros de data
- Verificar campo de controle (ex: `last_sync_at`, `erp_id`)
- Checar se há índice na coluna de referência cruzada

## Passo 5 — Executar sync manual

```bash
# Sync de estoque
php bin/magento erp:sync:stock --dry-run
php bin/magento erp:sync:stock

# Sync de pedidos
php bin/magento erp:sync:orders --pending

# Verificar resultado
tail -30 var/log/erp_integration.log
```

## Regras de Correção

- NUNCA escreva dados direto no ERP — apenas leitura do SQL Server
- SEMPRE use `$this->logger->error()` com contexto rico (sku, order_id, query)
- Se a query falhar, logue a query completa + parâmetros
- Nunca silenciar `catch {}` — no mínimo logar
- Após correção: rode `php -l` no arquivo editado e limpe cache
- Documente na correção: causa raiz, o que foi alterado, como testar
