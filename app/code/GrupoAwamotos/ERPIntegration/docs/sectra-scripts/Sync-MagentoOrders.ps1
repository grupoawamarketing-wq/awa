#Requires -Version 5.1
<#
.SYNOPSIS
    Importa pedidos pendentes do Magento para o Sectra (VE_PEDIDO + VE_PEDIDOITENS).

.DESCRIPTION
    Substitui a funcao "Importar Pedidos AWA" do Sectra Desktop, automatizando:
    1. GET /V1/erp/orders/pending → lista pedidos prontos
    2. Para cada pedido com registered_in_b2b=true:
       a. Gera INSERT INTO VE_PEDIDO + VE_PEDIDOITENS em transacao atomica
       b. Checa idempotencia (PEDIDOWEB duplicado → apenas reenvia ACK)
       c. Executa via sqlcmd e captura VE_PEDIDO.CODIGO (SCOPE_IDENTITY)
       d. POST /V1/erp/orders/:id/ack com erpOrderId = VE_PEDIDO.CODIGO real
    3. Pedidos com registered_in_b2b=false sao logados como pendentes

.PARAMETER ApiUrl
    URL base da loja Magento

.PARAMETER Token
    Token de integracao Magento (Bearer)

.PARAMETER SqlInstance
    Instancia SQL Server

.PARAMETER Database
    Banco Sectra

.PARAMETER LogDir
    Diretorio de logs

.PARAMETER DryRun
    Gera SQL mas nao executa

.EXAMPLE
    .\Sync-MagentoOrders.ps1 -DryRun

.NOTES
    Agendar: Task Scheduler → a cada 30 min (ou conforme demanda)
    Requer sqlcmd (SQL Server Command Line Utilities)
#>

[CmdletBinding()]
param(
    [string]$ApiUrl,
    [string]$Token,
    [string]$SqlInstance,
    [string]$Database,
    [string]$LogDir,
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ConfigFile = Join-Path $ScriptDir 'config.json'

if (Test-Path $ConfigFile) {
    $config = Get-Content $ConfigFile -Raw | ConvertFrom-Json
    if (-not $ApiUrl)       { $ApiUrl       = $config.api_url }
    if (-not $Token)        { $Token        = $config.token }
    if (-not $SqlInstance)  { $SqlInstance   = $config.sql_instance }
    if (-not $Database)     { $Database      = $config.database }
    if (-not $LogDir)       { $LogDir        = $config.log_dir }
}

if (-not $ApiUrl)       { $ApiUrl       = 'https://awamotos.com' }
if (-not $SqlInstance)  { $SqlInstance   = 'localhost' }
if (-not $Database)     { $Database      = 'INDUSTRIAL' }
if (-not $LogDir)       { $LogDir        = 'C:\SectraSync\logs' }

if (-not $Token) {
    Write-Error "Token nao informado. Use -Token ou configure em config.json"
    exit 1
}

if (-not (Test-Path $LogDir)) {
    New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
}

$timestamp = Get-Date -Format 'yyyy-MM-dd_HH-mm-ss'
$logFile = Join-Path $LogDir "orders_$timestamp.log"

function Write-Log {
    param([string]$Message, [string]$Level = 'INFO')
    $entry = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] [$Level] $Message"
    Add-Content -Path $logFile -Value $entry
    if ($Level -eq 'ERROR') { Write-Host $entry -ForegroundColor Red }
    elseif ($Level -eq 'WARN') { Write-Host $entry -ForegroundColor Yellow }
    else { Write-Host $entry }
}

function Invoke-MagentoApi {
    param([string]$Endpoint, [string]$Method = 'GET', $Body = $null)
    $headers = @{
        'Authorization' = "Bearer $Token"
        'Accept'        = 'application/json'
        'Content-Type'  = 'application/json'
    }
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    $params = @{
        Uri        = "$ApiUrl/rest/$Endpoint"
        Method     = $Method
        Headers    = $headers
        TimeoutSec = 120
    }
    if ($Body) {
        $params['Body'] = ($Body | ConvertTo-Json -Depth 10)
    }
    return Invoke-RestMethod @params
}

function ConvertTo-SqlSafe {
    param([string]$Value)
    return $Value.Replace("'", "''")
}

function Build-OrderSQL {
    <#
    .NOTES
        Returns T-SQL that:
        - Checks idempotency (PEDIDOWEB unique in VE_PEDIDO)
        - Wraps inserts in a transaction
        - Outputs marker line: ERPCODIGO=<VE_PEDIDO.CODIGO>
        Use sqlcmd flags: -h -1 (no column headers), -b (abort on error)
    #>
    param($Order)

    $c = $Order.customer
    $ec = $Order.erp_conditions
    $sa = $Order.shipping_address
    $incrementId = ConvertTo-SqlSafe $Order.increment_id

    $sql = @"
SET NOCOUNT ON;
DECLARE @pedidoId INT = 0;

-- Idempotency: return existing CODIGO if this order was already imported
IF EXISTS (SELECT 1 FROM VE_PEDIDO WHERE PEDIDOWEB = '$incrementId')
BEGIN
    SELECT @pedidoId = CODIGO FROM VE_PEDIDO WHERE PEDIDOWEB = '$incrementId';
    PRINT 'ERPCODIGO=' + CAST(@pedidoId AS NVARCHAR(20));
    PRINT 'STATUS=EXISTS';
    GOTO Fim;
END

BEGIN TRANSACTION;
BEGIN TRY

INSERT INTO VE_PEDIDO (
    FILIAL, FORNECEDOR, STATUS, PEDIDOWEB, PEDIDOCLI,
    VENDEDOR, CONDPAGTO, FATORPRECO, TRANSPPREF, TPFATOR, PERCFATOR,
    VLRBRUTO, VLRDESCONTO, VLRFRETE, VLRTOTAL,
    ENTENDERECO, ENTBAIRRO, ENTCIDADE, ENTUF, ENTCEP,
    USERNAME, DTEMISSAO
) VALUES (
    $($Order.filial), $($c.erp_code), '$($Order.erp_status)', '$incrementId', '$incrementId',
    $($ec.vendedor), $($ec.cond_pagto), $($ec.fator_preco), $($ec.transportadora), '$($ec.tp_fator)', $($ec.perc_fator),
    $($Order.subtotal), $($Order.discount), $($Order.shipping_amount), $($Order.grand_total),
    '$(ConvertTo-SqlSafe $sa.street)', '$(ConvertTo-SqlSafe $sa.neighborhood)', '$(ConvertTo-SqlSafe $sa.city)', '$(ConvertTo-SqlSafe $sa.region)', '$(ConvertTo-SqlSafe $sa.postcode)',
    '$($Order.username)', GETDATE()
);
SET @pedidoId = SCOPE_IDENTITY();

"@

    $seq = 1
    foreach ($item in $Order.items) {
        $sku = ConvertTo-SqlSafe $item.sku
        $name = ConvertTo-SqlSafe $item.name
        $sql += @"
INSERT INTO VE_PEDIDOITENS (
    PEDIDO, SEQUENCIA, MATERIAL, DESCRICAO, UNIDADE,
    QUANTIDADE, VLRUNITARIO, VLRTOTAL, VLRDESCONTO
) VALUES (
    @pedidoId, $seq, '$sku', '$name', '$($item.unit)',
    $($item.qty), $($item.unit_price), $($item.row_total), $($item.discount)
);
"@
        $seq++
    }

    $sql += @"

COMMIT TRANSACTION;
PRINT 'ERPCODIGO=' + CAST(@pedidoId AS NVARCHAR(20));
PRINT 'STATUS=IMPORTED';
GOTO Fim;

END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    DECLARE @errMsg NVARCHAR(4000) = ERROR_MESSAGE();
    DECLARE @errLine INT = ERROR_LINE();
    RAISERROR('Pedido $incrementId falhou na linha %d: %s', 16, 1, @errLine, @errMsg);
END CATCH

Fim:;
"@

    return $sql
}

function Get-ErpOrderId {
    <#
    .NOTES
        Parses sqlcmd output file for the ERPCODIGO=NNN marker.
        Returns the integer CODIGO or 0 on failure.
    #>
    param([string]$OutputFile)

    if (-not (Test-Path $OutputFile)) { return 0 }

    foreach ($line in (Get-Content $OutputFile)) {
        if ($line -match 'ERPCODIGO=(\d+)') {
            return [int]$Matches[1]
        }
    }
    return 0
}

function Get-ImportStatus {
    param([string]$OutputFile)
    if (-not (Test-Path $OutputFile)) { return 'UNKNOWN' }
    foreach ($line in (Get-Content $OutputFile)) {
        if ($line -match 'STATUS=(IMPORTED|EXISTS)') { return $Matches[1] }
    }
    return 'UNKNOWN'
}

# ── Main ───────────────────────────────────────────────────────────
try {
    Write-Log "=== Inicio da importacao de pedidos Magento → Sectra ==="

    if ($DryRun) {
        Write-Log "*** MODO DRY-RUN: SQL nao sera executado, ACK nao sera enviado ***" 'WARN'
    }

    # Step 1: Get pending orders
    Write-Log "Chamando API: GET /V1/erp/orders/pending"
    $response = Invoke-MagentoApi "V1/erp/orders/pending"

    if ($response -is [array]) { $data = $response[0] } else { $data = $response }

    $totalOrders = [int]$data.total_count
    Write-Log "API retornou $totalOrders pedidos pendentes"

    if ($data.held_count -gt 0) {
        Write-Log "$($data.held_count) pedido(s) retidos (clientes nao registrados no Sectra)" 'WARN'
    }

    if ($totalOrders -eq 0) {
        Write-Log "Nenhum pedido pendente."
        Write-Log "=== Fim (nada a fazer) ==="
        exit 0
    }

    $imported = 0
    $alreadyExisted = 0
    $skipped = 0
    $errors = 0

    foreach ($order in $data.orders) {
        $incId = $order.increment_id
        $erpCode = $order.customer.erp_code
        $registered = $order.customer.registered_in_b2b

        if (-not $registered) {
            Write-Log "SKIP pedido $incId - Cliente $erpCode NAO registrado no Sectra" 'WARN'
            $skipped++
            continue
        }

        if ($erpCode -eq 0) {
            Write-Log "SKIP pedido $incId - Cliente sem erp_code" 'WARN'
            $skipped++
            continue
        }

        try {
            Write-Log "Processando pedido $incId (cliente $erpCode, R`$ $($order.grand_total))..."

            $orderSql = Build-OrderSQL $order
            $sqlFile   = Join-Path $LogDir "order_${incId}_$timestamp.sql"
            $outFile   = Join-Path $LogDir "order_${incId}_$timestamp.out"
            $orderSql | Out-File -FilePath $sqlFile -Encoding UTF8

            if ($DryRun) {
                Write-Log "DRY-RUN: SQL gerado em $sqlFile"
                $imported++
                continue
            }

            # Execute SQL — capture output to $outFile for ERPCODIGO parsing
            # -h -1  : suppress column headers
            # -b     : abort on SQL error (sets exit code != 0)
            # -o     : redirect output to file
            $sqlcmdArgs = @('-S', $SqlInstance, '-d', $Database, '-E',
                            '-i', $sqlFile, '-o', $outFile, '-h', '-1', '-b')
            & sqlcmd @sqlcmdArgs 2>&1 | Out-Null
            $exitCode = $LASTEXITCODE

            if ($exitCode -ne 0) {
                $errDetail = if (Test-Path $outFile) { Get-Content $outFile -Raw } else { '(no output)' }
                Write-Log "ERRO sqlcmd pedido $incId (exit $exitCode): $errDetail" 'ERROR'
                $errors++
                continue
            }

            # Parse the real VE_PEDIDO.CODIGO from output
            $erpOrderId = Get-ErpOrderId $outFile
            $importStatus = Get-ImportStatus $outFile

            if ($erpOrderId -le 0) {
                Write-Log "ERRO: nao foi possivel obter VE_PEDIDO.CODIGO do output de $incId (output: $(Get-Content $outFile -Raw))" 'ERROR'
                $errors++
                continue
            }

            if ($importStatus -eq 'EXISTS') {
                Write-Log "SKIP pedido $incId - Ja existia no Sectra como VE_PEDIDO.CODIGO=$erpOrderId (reenviando ACK)" 'WARN'
                $alreadyExisted++
            } else {
                Write-Log "Pedido $incId inserido no Sectra como VE_PEDIDO.CODIGO=$erpOrderId"
            }

            # ACK to Magento with the real VE_PEDIDO.CODIGO
            $ackBody = @{
                erpOrderId = "$erpOrderId"
                message    = "Importado via Sync-MagentoOrders.ps1 (VE_PEDIDO.CODIGO=$erpOrderId, status=$importStatus)"
            }
            $ackResponse = Invoke-MagentoApi "V1/erp/orders/$incId/ack" 'POST' $ackBody
            Write-Log "ACK enviado para $incId : $($ackResponse.message)"

            $imported++

        } catch {
            Write-Log "ERRO no pedido $incId : $($_.Exception.Message)" 'ERROR'
            $errors++
        }
    }

    Write-Log "=== Resumo: $imported importados ($alreadyExisted ja existiam), $skipped pulados, $errors erros (de $totalOrders total) ==="
    Write-Log "=== Fim da importacao de pedidos ==="

    if ($errors -gt 0) { exit 1 }

} catch {
    Write-Log "ERRO FATAL: $($_.Exception.Message)" 'ERROR'
    exit 1
}
