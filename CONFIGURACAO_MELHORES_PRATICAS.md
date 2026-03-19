# ⚙️ Configuração de Melhores Práticas - Magento 2 B2B

**Data:** 17/02/2026
**Sistema:** Magento 2.4.8 + ERP Integration
**Ambiente:** Produção

---

## 📋 ÍNDICE

1. [Performance](#performance)
2. [Segurança](#segurança)
3. [Cache e Indexação](#cache-e-indexação)
4. [Emails](#emails)
5. [Cron Jobs](#cron-jobs)
6. [Monitoramento](#monitoramento)
7. [Backup](#backup)
8. [SEO](#seo)
9. [B2B Específico](#b2b-específico)
10. [ERP Integration](#erp-integration)

---

## 🚀 PERFORMANCE

### **1. Modo de Produção**

**Status Atual:** Developer Mode
**Recomendado:** Production Mode

```bash
# ANTES DE IR PARA PRODUÇÃO:

# 1. Compilar DI
php bin/magento setup:di:compile

# 2. Deploy static content (PT-BR e EN-US)
php bin/magento setup:static-content:deploy pt_BR en_US -f

# 3. Mudar para modo produção
php bin/magento deploy:mode:set production

# 4. Limpar e habilitar cache
php bin/magento cache:clean
php bin/magento cache:enable
```

### **2. Otimização de Assets**

```bash
# Merge e Minify (via CLI é mais confiável que via admin)
php bin/magento config:set dev/js/merge_files 1
php bin/magento config:set dev/css/merge_css_files 1
php bin/magento config:set dev/js/minify_files 1
php bin/magento config:set dev/css/minify_files 1
php bin/magento config:set dev/js/enable_js_bundling 0  # Desabilitar bundling (pode causar problemas)

# Image optimization
php bin/magento config:set dev/image/default_adapter GD2
php bin/magento config:set dev/image/adapters Imagick,GD2
```

### **3. Flat Catalog (Recomendado para >1000 produtos)**

**Status Atual:** Habilitado (category), Habilitado (product)

```bash
# Se ainda não habilitado:
php bin/magento config:set catalog/frontend/flat_catalog_category 1
php bin/magento config:set catalog/frontend/flat_catalog_product 1

# Reindexar
php bin/magento indexer:reindex catalog_category_flat
php bin/magento indexer:reindex catalog_product_flat
```

**⚠️ Atenção:**
- Flat catalog melhora performance mas aumenta tamanho do banco
- Com 731 produtos: RECOMENDADO
- Reindexar após cada mudança de produto

### **4. Redis Cache**

**Status Atual:** ✅ Já configurado

```php
// Verificar em app/etc/env.php
'cache' => [
    'frontend' => [
        'default' => [
            'backend' => 'Magento\Framework\Cache\Backend\Redis',
            'backend_options' => [
                'server' => '127.0.0.1',
                'database' => '0',
                'port' => '6379',
            ]
        ],
        'page_cache' => [
            'backend' => 'Magento\Framework\Cache\Backend\Redis',
            'backend_options' => [
                'server' => '127.0.0.1',
                'database' => '1',
                'port' => '6379',
            ]
        ]
    ]
]
```

**✅ Configuração Ideal (já aplicada)**

### **5. Session Storage**

**Status Atual:** ✅ Redis (database 2)

```php
// Verificar em app/etc/env.php
'session' => [
    'save' => 'redis',
    'redis' => [
        'host' => '127.0.0.1',
        'port' => '6379',
        'database' => '2',
        // ... outras configs
    ]
]
```

**✅ Configuração Ideal (já aplicada)**

### **6. PHP Optimization**

Verificar php.ini:
```ini
memory_limit = 2G                    # Para Magento 2
max_execution_time = 18000           # Para imports/reindex
upload_max_filesize = 64M
post_max_size = 64M
realpath_cache_size = 10M
realpath_cache_ttl = 7200

# OPcache (CRÍTICO)
opcache.enable=1
opcache.memory_consumption=512
opcache.interned_strings_buffer=12
opcache.max_accelerated_files=60000
opcache.save_comments=1
opcache.validate_timestamps=0        # Produção
```

```bash
# Verificar PHP atual
php -i | grep -E "memory_limit|max_execution_time|opcache"
```

---

## 🔒 SEGURANÇA

### **1. Admin URL Customizada**

**Atual:** `/admin_49fxvZi8FI` ✅ **Já customizada!**

**Recomendações:**
- ✅ URL customizada dificulta ataques de força bruta
- Não compartilhar URL publicamente
- Usar sempre HTTPS

### **2. Two-Factor Authentication (2FA)**

```bash
# Habilitar 2FA para todos os admins
php bin/magento config:set twofactorauth/general/force_providers google,duo

# Lista de admins sem 2FA
php bin/magento security:tfa:google:set-secret <username>
```

**Status:** Disponível, configurar por admin

### **3. Recaptcha**

```bash
# Habilitar reCAPTCHA v3 (recomendado)
php bin/magento config:set recaptcha_frontend/type_for/customer_login recaptcha_v3
php bin/magento config:set recaptcha_frontend/type_for/customer_create recaptcha_v3
```

**Necessita:** Google reCAPTCHA API keys

### **4. Content Security Policy**

**Módulo:** GrupoAwamotos_CspFix ✅ Habilitado

```bash
# Verificar CSP
php bin/magento config:show csp/mode/storefront/report_only
```

### **5. Permissões de Arquivos**

**Executar regularmente:**
```bash
find var generated vendor pub/static pub/media app/etc -type f -exec chmod 644 {} \;
find var generated vendor pub/static pub/media app/etc -type d -exec chmod 755 {} \;
chmod u+x bin/magento

# Proprietário correto
chown -R www-data:www-data var generated pub/static pub/media app/etc
```

### **6. Database Security**

**Recomendações:**
- ✅ Senha forte (Aw4m0t0s2025Mage) - boa!
- ⚠️ Não usar root em produção
- ✅ Conexão localhost (mais seguro)
- Considerar: Database backups criptografados

### **7. HTTPS Enforcement**

```bash
# Forçar HTTPS
php bin/magento config:set web/secure/use_in_frontend 1
php bin/magento config:set web/secure/use_in_adminhtml 1
php bin/magento config:set web/cookie/cookie_httponly 1
php bin/magento config:set web/cookie/cookie_secure 1
```

---

## 💾 CACHE E INDEXAÇÃO

### **1. Tipos de Cache**

**Status Atual:** ✅ Todos habilitados (15/15)

```bash
# Verificar status
php bin/magento cache:status

# Habilitar todos
php bin/magento cache:enable

# Flush vs Clean
php bin/magento cache:clean    # Limpa tags inválidas (mais rápido)
php bin/magento cache:flush    # Remove tudo (use após mudanças grandes)
```

### **2. Indexadores**

**Status Atual:** ✅ Todos Ready (16/16)

**Configuração Recomendada:**
```bash
# Modo Schedule (produção)
php bin/magento indexer:set-mode schedule

# Modo Realtime (desenvolvimento)
# php bin/magento indexer:set-mode realtime

# Verificar
php bin/magento indexer:status
```

**⚠️ Cron deve estar rodando para modo Schedule!**

### **3. Varnish (Full Page Cache)**

**Atual:** Usando Redis para Full Page Cache ✅

**Para alta performance (>1000 visitas/dia):**
```bash
# Gerar VCL para Varnish 7
php bin/magento varnish:vcl:generate --export-version=7 > varnish.vcl

# Configurar Magento para usar Varnish
php bin/magento config:set system/full_page_cache/caching_application 2
php bin/magento config:set system/full_page_cache/varnish/backend_host 127.0.0.1
php bin/magento config:set system/full_page_cache/varnish/backend_port 8080
```

---

## 📧 EMAILS

### **1. SMTP Configuration**

**Módulo:** GrupoAwamotos_SmtpFix ✅ Habilitado

**Configurar via Admin:**
```
Stores → Configuration → Advanced → System → Mail Sending Settings

- SMTP Host: smtp.gmail.com (ou servidor SMTP)
- SMTP Port: 587 (TLS) ou 465 (SSL)
- SMTP Username: seu-email@gmail.com
- SMTP Password: app-specific password
- SMTP Auth: Login
- SMTP SSL: TLS
```

**Testar envio:**
```bash
php bin/magento dev:email:send --to="seu-email@exemplo.com" --subject="Teste" --body="Teste de email"
```

### **2. Transactional Emails**

**Configurar remetentes:**
```
Stores → Configuration → General → Store Email Addresses

- General Contact
- Sales Representative
- Customer Support
- Custom Email 1 (para B2B)
- Custom Email 2 (para carrinho abandonado)
```

### **3. Email Templates**

**Customizar templates:**
```
Marketing → Email Templates

Templates importantes:
- New Order (pedidos)
- Invoice (faturas)
- Shipment (envios)
- Abandoned Cart (carrinho abandonado)
- Quote Response (cotações B2B)
```

### **4. Abandoned Cart Email**

**Módulo:** GrupoAwamotos_AbandonedCart

```
Stores → Configuration → Sales → Abandoned Cart

- Enable: Yes
- Sender Email: Custom Email 2
- Send After (hours): 1, 24, 72 (múltiplos emails)
- Discount Type: Percentage
- Discount Value: 10% (exemplo)
```

---

## ⏰ CRON JOBS

### **1. Configuração do Sistema**

**Verificar crontab:**
```bash
crontab -l | grep magento
```

**Deve conter:**
```bash
* * * * * /usr/bin/php /home/user/htdocs/srv1113343.hstgr.cloud/bin/magento cron:run 2>&1 | grep -v "Ran jobs by schedule" >> /home/user/htdocs/srv1113343.hstgr.cloud/var/log/magento.cron.log
* * * * * /usr/bin/php /home/user/htdocs/srv1113343.hstgr.cloud/update/cron.php >> /home/user/htdocs/srv1113343.hstgr.cloud/var/log/update.cron.log
* * * * * /usr/bin/php /home/user/htdocs/srv1113343.hstgr.cloud/bin/magento setup:cron:run >> /home/user/htdocs/srv1113343.hstgr.cloud/var/log/setup.cron.log
```

**Instalar via Magento:**
```bash
php bin/magento cron:install
```

### **2. Cron Jobs Críticos**

**ERP Integration:**
```
- erp_integration_stock_sync: A cada 5 minutos
- erp_integration_customer_sync: A cada 10 minutos
- erp_integration_order_queue: A cada 5 minutos
- erp_integration_forecast_update: Diariamente
```

**Abandoned Cart:**
```
- abandonedcart_process: A cada 15 minutos
- abandonedcart_send: A cada 1 hora
- abandonedcart_cleanup: Diariamente às 3h
```

**Smart Suggestions:**
```
- smart_suggestions_generate: Segundas às 6h
- smart_suggestions_rfm_calculate: Conforme configurado
```

**Magento Core:**
```
- catalog_index_refresh_price: A cada minuto
- newsletter_send_all: A cada 1 minuto
- backend_clean_cache: Diariamente às 2h
```

### **3. Monitorar Cron**

```bash
# Ver crons em execução
php bin/magento cron:run --group default

# Ver logs
tail -f var/log/magento.cron.log

# Ver schedule no banco
mysql -u magento -p'Aw4m0t0s2025Mage' -D magento -e "SELECT * FROM cron_schedule WHERE status='running' ORDER BY scheduled_at DESC LIMIT 10"
```

### **4. Cleanup de Cron**

```bash
# Limpar cron schedule antiga (executar mensalmente)
mysql -u magento -p'Aw4m0t0s2025Mage' -D magento -e "DELETE FROM cron_schedule WHERE status IN ('success', 'missed') AND scheduled_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
```

---

## 📊 MONITORAMENTO

### **1. Logs a Monitorar**

**Diariamente:**
```bash
# Erros críticos
grep -i "critical\|emergency" var/log/system.log | tail -20

# Exceções
tail -50 var/log/exception.log

# ERP sync issues
grep -i "error\|fail" var/log/erp_integration.log | tail -20
```

**Semanalmente:**
```bash
# Performance issues
grep -i "slow query\|timeout" var/log/system.log | tail -50

# Deadlocks
grep -i "deadlock" var/log/*.log
```

### **2. Circuit Breaker Status**

```bash
# Verificar Circuit Breaker (ERP)
php bin/magento erp:circuit-breaker --status

# Se OPEN (problemas):
# 1. Verificar conexão ERP
# 2. Ver logs: var/log/erp_integration.log
# 3. Reset manual se necessário:
php bin/magento erp:circuit-breaker --reset
```

### **3. Health Checks**

**Script de monitoramento diário:**
```bash
#!/bin/bash
# Salvar como: scripts/health_check.sh

echo "=== Magento Health Check $(date) ===" >> var/log/health_check.log

# 1. Cache
echo "Cache Status:" >> var/log/health_check.log
php bin/magento cache:status >> var/log/health_check.log

# 2. Indexers
echo "Indexer Status:" >> var/log/health_check.log
php bin/magento indexer:status >> var/log/health_check.log

# 3. ERP Connection
echo "ERP Status:" >> var/log/health_check.log
php bin/magento erp:connection:test >> var/log/health_check.log

# 4. Disk Space
echo "Disk Space:" >> var/log/health_check.log
df -h >> var/log/health_check.log

# 5. Database Size
echo "Database Size:" >> var/log/health_check.log
mysql -u "$MAGENTO_DB_USER" -p"$MAGENTO_DB_PASS" -D "$MAGENTO_DB_NAME" -e "SELECT table_schema AS 'Database', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.TABLES WHERE table_schema = 'magento'" >> var/log/health_check.log

echo "===================================" >> var/log/health_check.log
echo "" >> var/log/health_check.log
```

**Adicionar ao cron:**
```bash
0 8 * * * /home/jessessh/htdocs/srv1113343.hstgr.cloud/scripts/health_check.sh
```

### **4. New Relic / APM (Recomendado para Produção)**

```bash
# Instalar New Relic PHP Agent
# https://docs.newrelic.com/docs/apm/agents/php-agent/installation/php-agent-installation-overview/

# Ou alternativas open-source:
# - Datadog
# - Prometheus + Grafana
```

---

## 💾 BACKUP

### **1. Estratégia de Backup**

**Diário:**
- Database
- app/etc/
- pub/media/

**Semanal:**
- Backup completo

**Mensal:**
- Backup offsite

### **2. Scripts de Backup**

**Database Backup:**
```bash
#!/bin/bash
# scripts/backup_database.sh

BACKUP_DIR="/backup/magento/database"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="${MAGENTO_DB_NAME}"
DB_USER="${MAGENTO_DB_USER}"
DB_PASS="${MAGENTO_DB_PASS}"

mkdir -p $BACKUP_DIR

# Backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/magento_$DATE.sql.gz

# Manter últimos 7 dias
find $BACKUP_DIR -name "magento_*.sql.gz" -mtime +7 -delete

echo "Backup criado: magento_$DATE.sql.gz"
```

**Media Backup:**
```bash
#!/bin/bash
# scripts/backup_media.sh

BACKUP_DIR="/backup/magento/media"
DATE=$(date +%Y%m%d)
SOURCE_DIR="/home/jessessh/htdocs/srv1113343.hstgr.cloud/pub/media"

mkdir -p $BACKUP_DIR

# Backup incremental (mais rápido)
rsync -av --delete $SOURCE_DIR/ $BACKUP_DIR/latest/

# Snapshot semanal (domingo)
if [ $(date +%u) -eq 7 ]; then
    cp -al $BACKUP_DIR/latest $BACKUP_DIR/snapshot_$DATE
fi

echo "Media backup: latest"
```

**Cron para backups:**
```bash
# Database: diariamente às 2h
0 2 * * * /home/jessessh/htdocs/srv1113343.hstgr.cloud/scripts/backup_database.sh

# Media: diariamente às 3h
0 3 * * * /home/jessessh/htdocs/srv1113343.hstgr.cloud/scripts/backup_media.sh
```

### **3. Restore Procedure**

```bash
# Restore Database
gunzip < /backup/magento/database/magento_YYYYMMDD_HHMMSS.sql.gz | mysql -u "$MAGENTO_DB_USER" -p"$MAGENTO_DB_PASS" "$MAGENTO_DB_NAME"

# Restore Media
rsync -av /backup/magento/media/latest/ /home/jessessh/htdocs/srv1113343.hstgr.cloud/pub/media/

# Depois do restore:
php bin/magento cache:flush
php bin/magento indexer:reindex
```

---

## 🔍 SEO

### **1. URLs**

```bash
# URL rewrites (já deve estar habilitado)
php bin/magento config:set web/seo/use_rewrites 1

# Trailing slash
php bin/magento config:set catalog/seo/product_use_categories 1
```

### **2. Meta Tags**

**Configurar via Admin:**
```
Stores → Configuration → General → Design → HTML Head

- Default Title: Grupo Awamotos - Peças Automotivas
- Title Suffix: | Awamotos
- Default Description: (descrição da loja)
- Default Keywords: peças, automotivas, etc
```

### **3. Rich Snippets**

**Módulo:** GrupoAwamotos_SchemaOrg ✅ Habilitado

**Verifica:**
- Product schema
- Breadcrumbs
- Organization schema
- OpenGraph tags

**Testar:** https://search.google.com/test/rich-results

### **4. Sitemap**

```bash
# Gerar sitemap
php bin/magento sitemap:generate

# Configurar geração automática
php bin/magento config:set sitemap/generate/enabled 1
php bin/magento config:set sitemap/generate/time 02,00,00
php bin/magento config:set sitemap/generate/frequency D
```

**Submeter ao Google:**
```
Google Search Console → Sitemaps
URL: https://seusite.com/sitemap.xml
```

---

## 🏢 B2B ESPECÍFICO

### **1. Aprovação de Clientes**

**Workflow Recomendado:**
```
1. Cliente se cadastra
2. Admin recebe notificação
3. Admin verifica CNPJ
4. Admin aprova/rejeita
5. Se aprovado:
   - Sincroniza com ERP
   - Vincula transportadora
   - Define limite de crédito
6. Cliente recebe email de boas-vindas
```

**Configurar notificações:**
```
Stores → Configuration → B2B → Customer Registration

- Send Admin Notification: Yes
- Admin Email: vendas@awamotos.com
- Auto Approve: No (recomendado para B2B)
```

### **2. Pricing Strategy**

**Grupos de Clientes:**
```sql
-- Criar grupos via SQL ou Admin
INSERT INTO customer_group (customer_group_code, tax_class_id) VALUES
('b2b_gold', 3),
('b2b_silver', 3),
('b2b_bronze', 3);
```

**Tier Prices:**
```
Catalog → Products → [Produto] → Advanced Pricing

- Customer Group: B2B Gold
- Qty: 10
- Price: R$ 90,00 (10% desconto)
```

### **3. Quote Request (RFQ)**

**Ativar funcionalidade:**
```
Stores → Configuration → B2B → Quote Requests

- Enable Quote Requests: Yes
- Min Quote Amount: R$ 500,00
- Max Quote Validity: 30 dias
- Auto Send Quote Email: Yes
```

### **4. Credit Limit**

**Configuração padrão:**
```
Customers → All Customers → [Cliente] → B2B Credit

- Credit Limit: R$ 10.000,00
- Currency: BRL
- Allow Exceed: No
```

**Sincronizar com ERP:**
- Campo ERP: `LIMITECREDITO` em `FN_FORNECEDORES`
- Sync automático via cron

### **5. Order Approval**

**Workflow multinível:**
```
Stores → Configuration → B2B → Order Approval

- Require Approval: Yes
- Approval Threshold: R$ 5.000,00
- Required Levels: 2
  - Level 1: Manager (< R$ 10k)
  - Level 2: Director (>= R$ 10k)
```

---

## 🔗 ERP INTEGRATION

### **1. Connection Pooling**

**Otimizar conexões:**
```php
// app/etc/env.php - Seção ERP
'erp' => [
    'connection' => [
        'pool_size' => 5,          // Máximo 5 conexões simultâneas
        'idle_timeout' => 30,      // Timeout para conexões ociosas
        'connect_timeout' => 10,   // Timeout para conectar
    ]
]
```

### **2. Circuit Breaker Tuning**

**Ajustar thresholds:**
```bash
# Via CLI (se disponível) ou Admin
php bin/magento config:set erp_integration/circuit_breaker/failure_threshold 5
php bin/magento config:set erp_integration/circuit_breaker/timeout 60
php bin/magento config:set erp_integration/circuit_breaker/recovery_timeout 300
```

**Parâmetros:**
- `failure_threshold`: Falhas antes de abrir (padrão: 5)
- `timeout`: Timeout por requisição em segundos (padrão: 60)
- `recovery_timeout`: Tempo em half-open em segundos (padrão: 300)

### **3. Sync Frequency**

**Recomendações:**
```
Stock Sync: A cada 5 minutos (tempo real)
Customer Sync: A cada 10 minutos
Product Sync: A cada 1 hora
Order Sync: A cada 5 minutos
Price Sync: A cada 30 minutos
```

**Configurar via crontab.xml ou:**
```bash
php bin/magento config:set erp_integration/sync/stock_frequency "*/5 * * * *"
php bin/magento config:set erp_integration/sync/customer_frequency "*/10 * * * *"
```

### **4. Error Handling**

**Queue para retry:**
```
Stores → Configuration → ERP Integration → Error Handling

- Enable Retry Queue: Yes
- Max Retries: 3
- Retry Delays: 5, 30, 120 (minutos)
- Send Error Notifications: Yes
- Error Email: ti@awamotos.com
```

### **5. Monitoring**

**Comandos de diagnóstico:**
```bash
# Status completo
php bin/magento erp:diagnose

# Testar conexão
php bin/magento erp:connection:test

# Ver estatísticas
php bin/magento erp:stats

# Circuit breaker status
php bin/magento erp:circuit-breaker --status
```

---

## 📝 CHECKLIST FINAL - PRODUÇÃO

### **Antes do Deploy**

- [ ] Modo produção ativado
- [ ] Static content deployado (pt_BR, en_US)
- [ ] DI compilado
- [ ] Cache habilitado (todos os tipos)
- [ ] Indexadores em modo schedule
- [ ] Cron configurado e rodando
- [ ] HTTPS forçado
- [ ] Admin URL customizada
- [ ] 2FA habilitado para admins
- [ ] Backups configurados (diário)
- [ ] Monitoramento configurado
- [ ] SMTP configurado e testado
- [ ] Emails transacionais testados
- [ ] Circuit Breaker ERP funcionando
- [ ] Sync ERP testado
- [ ] Transportadoras configuradas
- [ ] B2B workflow testado
- [ ] Carrinho abandonado testado
- [ ] Sugestões testadas

### **Pós Deploy**

- [ ] Testar fluxo completo de compra
- [ ] Testar cadastro B2B
- [ ] Testar cotação (RFQ)
- [ ] Verificar sincronização ERP
- [ ] Monitorar logs por 24h
- [ ] Treinar equipe
- [ ] Documentação entregue
- [ ] Plano de suporte definido

---

## 🚨 TROUBLESHOOTING COMUM

### **Performance Lenta**

```bash
# 1. Verificar indexadores
php bin/magento indexer:status

# 2. Reindexar se necessário
php bin/magento indexer:reindex

# 3. Limpar cache
php bin/magento cache:clean

# 4. Verificar logs
tail -f var/log/system.log | grep -i slow

# 5. Verificar queries lentas
mysql -u magento -p'Aw4m0t0s2025Mage' -e "SHOW PROCESSLIST"
```

### **Erro 500**

```bash
# 1. Ver logs
tail -f var/log/exception.log
tail -f var/log/system.log

# 2. Permissões
chmod -R 755 var generated pub

# 3. Limpar generated
rm -rf generated/code/* generated/metadata/*

# 4. Recompilar
php bin/magento setup:di:compile
```

### **ERP Sync Falha**

```bash
# 1. Testar conexão
php bin/magento erp:connection:test

# 2. Ver circuit breaker
php bin/magento erp:circuit-breaker --status

# 3. Se OPEN, reset
php bin/magento erp:circuit-breaker --reset

# 4. Ver logs
tail -f var/log/erp_integration.log
```

---

## 📞 CONTATOS E SUPORTE

### **Equipe Técnica**
```
TI: ti@awamotos.com
Vendas: vendas@awamotos.com
Suporte: suporte@awamotos.com
```

### **Documentação**
```
STATUS_SISTEMA_COMPLETO.md - Status atual
CONFIGURACAO_MELHORES_PRATICAS.md - Este arquivo
RESUMO_IMPLEMENTACAO_FINAL.md - Resumo implementação
```

### **Comandos Rápidos**
```bash
# Status geral
php bin/magento erp:status && php bin/magento cache:status && php bin/magento indexer:status

# Limpar tudo
php bin/magento cache:flush && php bin/magento indexer:reindex

# Health check
./scripts/health_check.sh

# Backup agora
./scripts/backup_database.sh && ./scripts/backup_media.sh
```

---

**Documento criado em:** 17/02/2026 02:20
**Versão:** 1.0
**Status:** ✅ Pronto para implementação
**Próxima revisão:** Após 30 dias em produção
