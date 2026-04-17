#Requires -Version 5.1
<#
.SYNOPSIS
    Importa pedidos pendentes do Magento para o Sectra (VE_PEDIDO + VE_PEDIDOITENS).

.DESCRIPTION
    Substitui a funcao "Importar Pedidos AWA" do Sectra Desktop, automatizando:
    1. GET /V1/erp/orders/pending → lista pedidos prontos
    2. Para cada pedido com registered_in_b2b=true:
       a. Gera INSERT INTO VE_PEDIDO + VE_PEDIDOITENS
       b. Executa via sqlcmd
       c. POST /V1/erp/orders/:id/ack → confirma recebimento
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
    param($Order)

    $c = $Order.customer
    $ec = $Order.erp_conditions
    $sa = $Order.shipping_address
    $incrementId = ConvertTo-SqlSafe $Order.increment_id

    $sql = "-- Pedido: $($Order.increment_id) | Cliente: $($c.erp_code) | Total: R`$ $($Order.grand_total)`n"

    # INSERT VE_PEDIDO
    $sql += @"
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
DECLARE @pedidoId INT = SCOPE_IDENTITY();
"@

    # INSERT VE_PEDIDOITENS for each item
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

    return $sql
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

    if ($totalOrders -eq 0) {
        Write-Log "Nenhum pedido pendente."
        Write-Log "=== Fim (nada a fazer) ==="
        exit 0
    }

    $imported = 0
    $skipped = 0
    $errors = 0

    foreach ($order in $data.orders) {
        $incId = $order.increment_id
        $erpCode = $order.customer.erp_code
        $registered = $order.customer.registered_in_b2b

        if (-not $registered) {
            Write-Log "SKIP pedido $incId - Cliente $erpCode NAO registrado no Sectra (sync de clientes pendente)" 'WARN'
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
            $sqlFile = Join-Path $LogDir "order_${incId}_$timestamp.sql"
            $orderSql | Out-File -FilePath $sqlFile -Encoding UTF8

            if (-not $DryRun) {
                # Execute SQL
                $result = & sqlcmd -S $SqlInstance -d $Database -E -i $sqlFile -b 2>&1
                $exitCode = $LASTEXITCODE

                if ($exitCode -ne 0) {
                    Write-Log "ERRO sqlcmd pedido $incId : $result" 'ERROR'
                    $errors++
                    continue
                }

                # ACK to Magento
                Write-Log "Enviando ACK para pedido $incId..."
                $ackBody = @{
                    erpOrderId = $incId
                    message    = "Importado via Sync-MagentoOrders.ps1"
                }
                $ackResponse = Invoke-MagentoApi "V1/erp/orders/$incId/ack" 'POST' $ackBody
                Write-Log "ACK enviado: $($ackResponse.message)"
            }

            $imported++
            Write-Log "Pedido $incId importado com sucesso!"

        } catch {
            Write-Log "ERRO no pedido $incId : $($_.Exception.Message)" 'ERROR'
            $errors++
        }
    }

    Write-Log "=== Resumo: $imported importados, $skipped pulados, $errors erros (de $totalOrders total) ==="
    Write-Log "=== Fim da importacao de pedidos ==="

} catch {
    Write-Log "ERRO FATAL: $($_.Exception.Message)" 'ERROR'
    exit 1
}
