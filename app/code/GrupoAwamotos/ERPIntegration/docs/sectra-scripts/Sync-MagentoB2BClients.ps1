#Requires -Version 5.1
<#
.SYNOPSIS
    Sincroniza clientes B2B do Magento com o Sectra (GR_INTEGRACAOVALIDADOR).

.DESCRIPTION
    Este script substitui a funcao de cadastro de clientes do antigo OpenCardB2B.
    Ele chama a API REST do Magento para obter o T-SQL de registro e executa
    localmente via sqlcmd no servidor onde o Sectra roda.

    Fluxo:
    1. GET /rest/V1/erp/customers/b2b/sync-sql → retorna T-SQL pronto
    2. Salva o SQL em arquivo temporario
    3. Executa via sqlcmd contra o banco INDUSTRIAL
    4. Registra log de execucao

.PARAMETER ApiUrl
    URL base da loja Magento (ex: https://awamotos.com)

.PARAMETER Token
    Token de integracao Magento (Bearer token)

.PARAMETER SqlInstance
    Instancia SQL Server (default: localhost)

.PARAMETER Database
    Banco de dados Sectra (default: INDUSTRIAL)

.PARAMETER Limit
    Maximo de clientes por execucao (default: 500)

.PARAMETER LogDir
    Diretorio de logs (default: C:\SectraSync\logs)

.PARAMETER DryRun
    Se especificado, gera o SQL mas NAO executa

.EXAMPLE
    .\Sync-MagentoB2BClients.ps1
    # Executa com os parametros padrao do config.json

.EXAMPLE
    .\Sync-MagentoB2BClients.ps1 -DryRun
    # Gera o SQL mas nao executa (para revisao)

.EXAMPLE
    .\Sync-MagentoB2BClients.ps1 -Limit 50 -Verbose
    # Sincroniza ate 50 clientes com output detalhado

.NOTES
    Autor: GrupoAwamotos / ERP Integration
    Requer: sqlcmd (SQL Server Command Line Utilities)
    Agendar: Task Scheduler → diario as 06:00 e 18:00
#>

[CmdletBinding()]
param(
    [string]$ApiUrl,
    [string]$Token,
    [string]$SqlInstance,
    [string]$Database,
    [int]$Limit = 500,
    [string]$LogDir,
    [switch]$DryRun
)

# ── Config ─────────────────────────────────────────────────────────
$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ConfigFile = Join-Path $ScriptDir 'config.json'

# Load config.json defaults
if (Test-Path $ConfigFile) {
    $config = Get-Content $ConfigFile -Raw | ConvertFrom-Json
    if (-not $ApiUrl)       { $ApiUrl       = $config.api_url }
    if (-not $Token)        { $Token        = $config.token }
    if (-not $SqlInstance)  { $SqlInstance   = $config.sql_instance }
    if (-not $Database)     { $Database      = $config.database }
    if (-not $LogDir)       { $LogDir        = $config.log_dir }
}

# Fallback defaults
if (-not $ApiUrl)       { $ApiUrl       = 'https://awamotos.com' }
if (-not $SqlInstance)  { $SqlInstance   = 'localhost' }
if (-not $Database)     { $Database      = 'INDUSTRIAL' }
if (-not $LogDir)       { $LogDir        = 'C:\SectraSync\logs' }

if (-not $Token) {
    Write-Error "Token de integracao nao informado. Use -Token ou configure em config.json"
    exit 1
}

# Ensure log dir exists
if (-not (Test-Path $LogDir)) {
    New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
}

$timestamp = Get-Date -Format 'yyyy-MM-dd_HH-mm-ss'
$logFile = Join-Path $LogDir "sync_$timestamp.log"

# ── Functions ──────────────────────────────────────────────────────
function Write-Log {
    param([string]$Message, [string]$Level = 'INFO')
    $entry = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] [$Level] $Message"
    Add-Content -Path $logFile -Value $entry
    if ($Level -eq 'ERROR') {
        Write-Host $entry -ForegroundColor Red
    } elseif ($Level -eq 'WARN') {
        Write-Host $entry -ForegroundColor Yellow
    } else {
        Write-Host $entry
    }
}

# ── Main ───────────────────────────────────────────────────────────
try {
    Write-Log "=== Inicio da sincronizacao B2B Magento → Sectra ==="
    Write-Log "API: $ApiUrl | DB: $SqlInstance\$Database | Limit: $Limit"

    if ($DryRun) {
        Write-Log "*** MODO DRY-RUN: SQL nao sera executado ***" 'WARN'
    }

    # Step 1: Call Magento API
    Write-Log "Chamando API: GET /rest/V1/erp/customers/b2b/sync-sql?limit=$Limit"

    $headers = @{
        'Authorization' = "Bearer $Token"
        'Accept'        = 'application/json'
    }

    # TLS 1.2
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

    $response = Invoke-RestMethod `
        -Uri "$ApiUrl/rest/V1/erp/customers/b2b/sync-sql?limit=$Limit" `
        -Method GET `
        -Headers $headers `
        -TimeoutSec 120

    # The API returns an array with one element
    if ($response -is [array]) {
        $data = $response[0]
    } else {
        $data = $response
    }

    $count = [int]$data.count
    $sql = $data.sql

    if ($count -eq 0) {
        Write-Log "Nenhum cliente novo para sincronizar. Todos ja registrados."
        Write-Log "=== Fim da sincronizacao (nada a fazer) ==="
        exit 0
    }

    Write-Log "API retornou $count clientes para registrar"

    # Step 2: Save SQL to temp file
    $sqlFile = Join-Path $LogDir "sync_$timestamp.sql"
    $sql | Out-File -FilePath $sqlFile -Encoding UTF8
    Write-Log "SQL salvo em: $sqlFile"

    if ($DryRun) {
        Write-Log "*** DRY-RUN: SQL disponivel em $sqlFile para revisao ***" 'WARN'
        Write-Log "=== Fim da sincronizacao (dry-run) ==="
        exit 0
    }

    # Step 3: Execute via sqlcmd
    Write-Log "Executando SQL via sqlcmd..."

    $sqlcmdArgs = @(
        '-S', $SqlInstance,
        '-d', $Database,
        '-E',           # Windows Authentication (Sectra server has access)
        '-i', $sqlFile,
        '-b'            # Abort on error
    )

    $result = & sqlcmd @sqlcmdArgs 2>&1
    $exitCode = $LASTEXITCODE

    if ($exitCode -eq 0) {
        Write-Log "SQL executado com sucesso! $count clientes registrados."
        Write-Log "Output sqlcmd: $result"
    } else {
        Write-Log "ERRO ao executar SQL (exit code: $exitCode)" 'ERROR'
        Write-Log "Output: $result" 'ERROR'
        exit 1
    }

    Write-Log "=== Fim da sincronizacao ($count clientes registrados) ==="

} catch {
    $errMsg = $_.Exception.Message
    Write-Log "ERRO FATAL: $errMsg" 'ERROR'

    # If it's a connection error, give helpful message
    if ($errMsg -match 'Unable to connect|Could not resolve') {
        Write-Log "Verifique: 1) URL da API ($ApiUrl) 2) Token valido 3) Conexao de rede" 'ERROR'
    }

    exit 1
}
