@echo off
REM ============================================================
REM  SYNC COMPLETO: Clientes B2B + Pedidos (Magento → Sectra)
REM  Agendar: Task Scheduler → a cada 30 min
REM
REM  Ordem: 1) Sincroniza clientes  2) Importa pedidos
REM  Assim os clientes novos ja estao registrados quando
REM  os pedidos deles forem importados.
REM ============================================================
cd /d "%~dp0"

echo ============================================
echo  ETAPA 1: Sincronizando clientes B2B...
echo ============================================
powershell.exe -ExecutionPolicy Bypass -NoProfile -File "%~dp0Sync-MagentoB2BClients.ps1"
if %ERRORLEVEL% NEQ 0 (
    echo [AVISO] Sync de clientes teve erro, mas continuando com pedidos...
)

echo.
echo ============================================
echo  ETAPA 2: Importando pedidos...
echo ============================================
powershell.exe -ExecutionPolicy Bypass -NoProfile -File "%~dp0Sync-MagentoOrders.ps1"
if %ERRORLEVEL% NEQ 0 (
    echo [ERRO] Importacao de pedidos falhou com codigo %ERRORLEVEL%
    exit /b %ERRORLEVEL%
)

echo.
echo ============================================
echo  CONCLUIDO: Clientes + Pedidos sincronizados
echo ============================================
