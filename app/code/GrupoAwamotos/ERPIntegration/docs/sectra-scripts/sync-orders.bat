@echo off
REM ============================================================
REM  Import Magento Orders → Sectra (VE_PEDIDO + VE_PEDIDOITENS)
REM  Agendar no Task Scheduler: a cada 30 min
REM ============================================================
cd /d "%~dp0"
powershell.exe -ExecutionPolicy Bypass -NoProfile -File "%~dp0Sync-MagentoOrders.ps1"
if %ERRORLEVEL% NEQ 0 (
    echo [ERRO] Importacao de pedidos falhou com codigo %ERRORLEVEL%
    exit /b %ERRORLEVEL%
)
echo [OK] Importacao de pedidos concluida
