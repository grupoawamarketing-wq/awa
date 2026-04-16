---
description: "Diagnostica e corrige problemas de performance no Magento 2 (lentidão, high CPU, slow queries, cache miss)"
agent: "Debugger"
tools:
  - codebase
  - execute
  - terminal
---

Diagnostique e corrija o problema de performance descrito abaixo no Magento 2 AWA Motos.

## Problema

`$DESCRICAO_DO_PROBLEMA`

Exemplos: "Página de categoria demorando 8s", "CPU 100% após deploy", "Cache não está sendo populado", "Queries lentas no checkout"

## Passos de Diagnóstico (executar na ordem)

### 1. Logs recentes
```bash
tail -50 var/log/system.log
tail -50 var/log/exception.log
tail -20 var/log/debug.log 2>/dev/null || echo "debug.log ausente"
```

### 2. Status dos serviços
```bash
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' ping
curl -s http://localhost:9200/_cluster/health | python3 -m json.tool
sudo systemctl status php8.4-fpm --no-pager
```

### 3. Cache hit rate
```bash
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' info stats | grep -E "hits|misses|keys_expired"
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 1 info keyspace
```

### 4. Queries lentas MySQL
```bash
mysql -u "$MAGENTO_DB_USER" -p"$MAGENTO_DB_PASS" "$MAGENTO_DB_NAME" \
  -e "SELECT * FROM information_schema.processlist WHERE time > 2 ORDER BY time DESC LIMIT 10;"
```

### 5. Indexer status
```bash
sudo -u www-data php bin/magento indexer:status
```

### 6. PHP-FPM workers
```bash
sudo -u www-data php -r "echo opcache_get_status()['opcache_statistics']['num_cached_scripts'] . ' scripts em OPcache\n';" 2>/dev/null || echo "OPcache não acessível via CLI"
ps aux | grep php-fpm | grep -v grep | wc -l
```

## Contexto do Ambiente

- **Stack:** Magento 2.4.8-p3 + PHP 8.4 + MySQL (Percona 8.4) + OpenSearch + Redis
- **Redis DB 0:** sessions | **DB 1:** cache | **DB 2:** FPC
- **Logs principais:** `var/log/system.log`, `var/log/exception.log`, `var/log/erp_integration.log`
- **Módulos críticos:** ERPIntegration (cron), B2B (checkout), Fitment (catálogo)

## O que NÃO fazer

- Não desabilitar cache em produção sem antes identificar a causa
- Não rodar `setup:di:compile` durante diagnóstico (muda o estado)
- Não reiniciar serviços sem capturar os logs antes

## Resultado Esperado

Identifique a causa raiz, proponha e aplique a correção mínima necessária. Se for uma query lenta, otimize com índice. Se for cache miss, verifique TTL e tags. Se for leak de memória, identifique o módulo responsável.
