# Sync Magento B2B → Sectra (Substituicao do OpenCardB2B)

## O que faz

Este script substitui a funcao "Cadastro de Cliente" do antigo **OpenCardB2B** que parou em 01/11/2024.

Ele registra automaticamente os clientes B2B do Magento na tabela `GR_INTEGRACAOVALIDADOR` do Sectra,
permitindo que o processo "Importar Pedidos AWA" reconheca os clientes e importe pedidos sem erro.

**Antes (OpenCart):**
```
Sectra Desktop → GET OpenCart API → Sectra grava SQL Server
```

**Agora (Magento):**
```
Task Scheduler → PowerShell → GET Magento API → sqlcmd → SQL Server
```

Mesmo fluxo, mesma logica. O Magento gera o T-SQL pronto, o script so executa localmente.

---

## Requisitos no Servidor Sectra

1. **PowerShell 5.1+** (ja incluso no Windows Server 2016+)
2. **sqlcmd** (SQL Server Command Line Utilities)
3. **Acesso de rede** a `https://awamotos.com` (porta 443)
4. **Windows Authentication** com permissao de INSERT em `GR_INTEGRACAOVALIDADOR`

---

## Instalacao

### 1. Copiar arquivos

Copie a pasta inteira para o servidor Sectra:

```
C:\SectraSync\
  ├── Sync-MagentoB2BClients.ps1   (script principal)
  ├── config.json                    (configuracoes)
  ├── sync-b2b.bat                   (wrapper para Task Scheduler)
  └── logs\                          (criado automaticamente)
```

### 2. Editar config.json

```json
{
    "api_url": "https://awamotos.com",
    "token": "SUBSTITUIR_PELO_TOKEN_DA_INTEGRACAO_SECTRA",
    "sql_instance": "localhost",
    "database": "INDUSTRIAL",
    "log_dir": "C:\\SectraSync\\logs",
    "limit": 500
}
```

| Campo | Descricao |
|-------|-----------|
| `api_url` | URL da loja Magento (nao mude) |
| `token` | Token de integracao Magento (Bearer) |
| `sql_instance` | Instancia SQL Server local (ou `SERVIDOR\INSTANCIA`) |
| `database` | Banco Sectra (normalmente `INDUSTRIAL`) |
| `log_dir` | Onde salvar logs e SQLs executados |
| `limit` | Max clientes por execucao (500 recomendado) |

### 3. Teste manual (dry-run)

Abra PowerShell como Administrador no servidor Sectra:

```powershell
cd C:\SectraSync
.\Sync-MagentoB2BClients.ps1 -DryRun -Verbose
```

Isso vai:
- Chamar a API do Magento
- Gerar o arquivo SQL em `C:\SectraSync\logs\`
- **NAO executar** o SQL (modo dry-run)

Abra o arquivo `.sql` gerado e revise antes de prosseguir.

### 4. Teste real (primeiro sync)

```powershell
.\Sync-MagentoB2BClients.ps1 -Limit 10
```

Sincroniza apenas 10 clientes para validar. Verifique no Sectra:

```sql
SELECT TOP 10 * FROM GR_INTEGRACAOVALIDADOR
WHERE DTSINCRONIZACAO >= DATEADD(MINUTE, -5, GETDATE())
ORDER BY DTSINCRONIZACAO DESC
```

### 5. Agendar no Task Scheduler

1. Abrir **Task Scheduler** → Create Task
2. **General tab:**
   - Name: `Sync Magento B2B Clients`
   - Run whether user is logged on or not
   - Run with highest privileges
3. **Triggers tab:**
   - New → Daily → 06:00
   - New → Daily → 18:00
4. **Actions tab:**
   - Program: `C:\SectraSync\sync-b2b.bat`
   - Start in: `C:\SectraSync`
5. **Settings tab:**
   - Allow task to be run on demand
   - Stop if runs longer than 10 minutes

---

## Fix Imediato: Cliente 2541

Para desbloquear o pedido 200008 AGORA, execute direto no SQL Server:

```sql
-- Fix cliente 2541 (MAM DISTRIBUIDORA DE MOTO PECAS LTDA)
DECLARE @maxExt INT;
SELECT @maxExt = ISNULL(MAX(CAST(CHAVEEXTERNA AS INT)), 10000)
FROM GR_INTEGRACAOVALIDADOR
WHERE INTEGRACAOORIGEM = 'FEB11981-5319-49EB-9F1E-4BA02BD22B90';

INSERT INTO GR_INTEGRACAOVALIDADOR(INTEGRACAOORIGEM, CHAVE, VALIDADOR, CHAVEEXTERNA, DTSINCRONIZACAO)
VALUES(
    '7D4C6FBD-62CF-427F-A0ED-3C06602F05D7',
    '2541',
    UPPER(CONVERT(VARCHAR(32), HASHBYTES('MD5', '{"CODIGO":2541,"source":"magento_b2b"}'), 2)),
    '2541',
    GETDATE()
);

INSERT INTO GR_INTEGRACAOVALIDADOR(INTEGRACAOORIGEM, CHAVE, VALIDADOR, CHAVEEXTERNA, DTSINCRONIZACAO)
VALUES(
    'FEB11981-5319-49EB-9F1E-4BA02BD22B90',
    '2541;1',
    UPPER(CONVERT(VARCHAR(32), HASHBYTES('MD5', '{"CODIGO":2541,"ENDERECO":1,"source":"magento_b2b"}'), 2)),
    CAST(@maxExt + 1 AS VARCHAR(20)),
    GETDATE()
);
```

Depois, re-importe o pedido 200008 no Sectra → "Importar Pedidos AWA".

---

## Monitoramento

### Logs
Cada execucao gera dois arquivos em `C:\SectraSync\logs\`:
- `sync_YYYY-MM-DD_HH-mm-ss.log` — log de execucao
- `sync_YYYY-MM-DD_HH-mm-ss.sql` — SQL executado (para auditoria)

### Verificar no Sectra
```sql
-- Ultimos clientes sincronizados
SELECT TOP 20 *
FROM GR_INTEGRACAOVALIDADOR
WHERE INTEGRACAOORIGEM = '7D4C6FBD-62CF-427F-A0ED-3C06602F05D7'
ORDER BY DTSINCRONIZACAO DESC;

-- Total registrados
SELECT COUNT(*) AS total
FROM GR_INTEGRACAOVALIDADOR
WHERE INTEGRACAOORIGEM = '7D4C6FBD-62CF-427F-A0ED-3C06602F05D7';
```

### Verificar via API Magento
```bash
# Quantos faltam registrar
curl -H "Authorization: Bearer TOKEN" \
  "https://awamotos.com/rest/V1/erp/customers/b2b/sync-sql?limit=1"
# Se count=0, todos sincronizados
```

---

## Troubleshooting

| Problema | Solucao |
|----------|---------|
| `Unable to connect to remote server` | Verificar firewall: porta 443 para awamotos.com |
| `401 Unauthorized` | Token expirado. Gerar novo em Magento Admin → System → Integrations |
| `sqlcmd not recognized` | Instalar SQL Server Command Line Utilities |
| `INSERT duplicate key` | Normal — cliente ja registrado. Script ignora automaticamente |
| `Todos ja registrados` | Nada a fazer — sync esta em dia |

---

## Comparativo OpenCardB2B vs Magento Sync

| Aspecto | OpenCardB2B (antigo) | Magento Sync (novo) |
|---------|---------------------|---------------------|
| Plataforma | Desktop C# | PowerShell + API REST |
| Frequencia | Manual ou agendado | Task Scheduler (2x/dia) |
| Direcao | Sectra puxa de OpenCart | Sectra puxa de Magento |
| SQL gerado | Pelo app desktop | Pela API Magento |
| Execucao SQL | Local (sqlcmd equiv.) | Local (sqlcmd) |
| Logs | Nenhum | Completo em C:\SectraSync\logs |
| Credenciais SQL | Windows Auth | Windows Auth (mesmo) |
