# AWA Motos - Sistema de Monitoramento de Logs

## Visão Geral

Sistema avançado de monitoramento contínuo de logs para Magento 2 AWA Motos, com dashboard em tempo real, alertas inteligentes, análise preditiva e integração com sistemas específicos AWA.

## Funcionalidades

### 1. Dashboard em Tempo Real
- Interface web para visualizar métricas de logs
- Gráficos de crescimento de logs por tipo
- Status de saúde do sistema em tempo real
- Lista de erros críticos detectados

### 2. Alertas Inteligentes
- Detecção automática de padrões de erro
- Alertas para problemas críticos específicos AWA:
  - NoSuchEntityException
  - Erros ERP de sincronização 
  - Problemas de cache/performance
- Notificações via email/Slack/webhook

### 3. Análise Preditiva
- Detectar tendências de crescimento de logs
- Prever quando logs podem ficar críticos
- Identificar padrões de erro recorrentes
- Sugerir otimizações automáticas

### 4. Integração com Sistemas AWA
- Monitorar especificamente logs ERP
- Acompanhar performance de consumidores
- Monitorar jobs de cron customizados AWA

### 5. API de Monitoramento
- Endpoints REST para consultar métricas
- Integração com sistemas externos
- Webhooks para eventos críticos
- Export de dados para análise

## Instalação

1. **Habilitar o módulo:**
```bash
php bin/magento module:enable GrupoAwamotos_LogMonitoring
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:clean
```

2. **Verificar instalação:**
```bash
php bin/magento module:status | grep GrupoAwamotos_LogMonitoring
```

3. **Configurar permissões:**
   - No Admin: Sistema → Permissões de Usuário → Funções
   - Adicionar permissão "AWA Log Monitoring" aos usuários necessários

## Configuração

### 1. Configurações Básicas
Acesse: **Admin → Stores → Configuration → AWA Motos → Log Monitoring**

- **Habilitar Monitoramento**: Ativar/desativar o sistema
- **Frequência de Análise**: Configurar intervalos de análise automática
- **Retenção de Dados**: Tempo de manutenção dos dados históricos

### 2. Notificações
Configure canais de notificação:

- **Email**: Lista de destinatários separados por vírgula
- **Slack**: URL do webhook Slack
- **Webhook**: URL personalizada para notificações

### 3. Configurações AWA Específicas
- **Monitoramento ERP**: Ativar análise de logs ERP
- **Monitoramento Performance**: Ativar análise de performance

## Uso

### 1. Dashboard Principal
Acesse: **Admin → System → AWA Log Monitoring → Dashboard**

- Visão geral da saúde do sistema
- Métricas AWA específicas
- Alertas ativos
- Atividade recente

### 2. Gerenciamento de Alertas
Acesse: **Admin → System → AWA Log Monitoring → Alerts**

- Lista de alertas ativos
- Reconhecer alertas
- Resolver alertas
- Filtrar por severidade

### 3. Métricas de Log
Acesse: **Admin → System → AWA Log Monitoring → Log Metrics**

- Análise detalhada de logs
- Tendências históricas
- Métricas por tipo de log

## APIs Disponíveis

### 1. Dashboard Data
```
GET /rest/V1/log-monitoring/dashboard
```
Retorna dados completos do dashboard

### 2. Saúde do Sistema
```
GET /rest/V1/log-monitoring/system-health
```
Status atual de saúde de todos os componentes

### 3. Métricas AWA
```
GET /rest/V1/log-monitoring/awa-metrics
```
Métricas específicas dos sistemas AWA Motos

### 4. Análise de Logs
```
GET /rest/V1/log-monitoring/log-analysis
GET /rest/V1/log-monitoring/log-analysis/[logType]
```
Análise detalhada de logs por tipo

### 5. Gerenciamento de Alertas
```
GET /rest/V1/log-monitoring/alerts/active
GET /rest/V1/log-monitoring/alerts/critical
PUT /rest/V1/log-monitoring/alerts/[id]/acknowledge
PUT /rest/V1/log-monitoring/alerts/[id]/resolve
```

### 6. Trigger Manual
```
POST /rest/V1/log-monitoring/trigger-analysis
```
Acionar análise manual de logs

### 7. Teste de Notificações
```
POST /rest/V1/log-monitoring/test-notifications
```
Testar todos os canais de notificação

## Cron Jobs

O sistema executa automaticamente:

- **Análise de Logs**: A cada 15 minutos (`*/15 * * * *`)
- **Verificação de Saúde**: A cada 30 minutos (`*/30 * * * *`)
- **Processamento de Alertas**: A cada 5 minutos (`*/5 * * * *`)
- **Limpeza de Dados**: Diariamente às 2h (`0 2 * * *`)

## Logs do Sistema

O módulo mantém logs próprios em:
- `var/log/awa_monitoring.log` - Logs de funcionamento do sistema
- Logs analisados: `var/log/system.log`, `var/log/exception.log`, etc.

## Troubleshooting

### Problema: Dashboard não carrega
1. Verificar se o módulo está habilitado
2. Limpar cache: `php bin/magento cache:clean`
3. Verificar logs em `var/log/awa_monitoring.log`

### Problema: Alertas não são enviados
1. Verificar configuração de notificações no Admin
2. Testar notificações via API
3. Verificar conectividade de rede para webhooks

### Problema: Análise não funciona
1. Verificar permissões do diretório `var/log/`
2. Verificar cron jobs: `php bin/magento cron:status`
3. Executar análise manual: POST `/rest/V1/log-monitoring/trigger-analysis`

### Problema: Performance lenta
1. Ajustar frequência de análise nas configurações
2. Verificar tamanho dos logs - implementar rotação se necessário
3. Limpar dados antigos manualmente

## Segurança

- Todas as APIs requerem autenticação de admin
- Dados sensíveis não são expostos em logs
- Webhooks usam HTTPS quando possível
- Rate limiting configurável para APIs

## Integração com Outros Módulos

### 1. LogRotation
O módulo integra automaticamente com `GrupoAwamotos_LogRotation` para análise de logs rotacionados.

### 2. ERP Integration
Monitora logs específicos de integração ERP e detecta padrões de erro relacionados.

## Extensibilidade

### Adicionar Novo Analyzer
1. Implementar `AnalyzerInterface`
2. Adicionar ao pool em `di.xml`
3. Configurar no cron se necessário

### Personalizar Alertas
Use o campo "Alert Thresholds (JSON)" nas configurações para definir limites customizados.

### Integrar com Sistemas Externos
Use webhooks ou APIs REST para integrar com sistemas de monitoramento externos.

## Performance e Escalabilidade

- Análise incremental de logs
- Cleanup automático de dados antigos
- Rate limiting para APIs
- Otimização de consultas de banco
- Cache de métricas calculadas

## Suporte

Para suporte e dúvidas:
1. Verificar logs do sistema
2. Consultar esta documentação
3. Testar APIs manualmente
4. Verificar configurações no Admin

## Exemplo de Resposta da API

```json
{
  "system_health": {
    "overall_status": "healthy",
    "overall_score": 87.5,
    "components": {
      "database": {"status": "healthy", "score": 90},
      "filesystem": {"status": "healthy", "score": 85},
      "cache": {"status": "warning", "score": 75}
    }
  },
  "awamotos_metrics": {
    "erp_sync": {
      "health_score": 85,
      "sync_rate": 0.95,
      "error_rate": 0.02
    }
  }
}
```

Este sistema fornece monitoramento completo e em tempo real para garantir a estabilidade e performance da plataforma AWA Motos.