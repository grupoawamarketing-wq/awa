@echo off
REM ============================================================
REM  Sync Magento B2B Clients → Sectra (GR_INTEGRACAOVALIDADOR)
REM  Agendar no Task Scheduler: diario 06:00 e 18:00
REM ============================================================
cd /d "%~dp0"
powershell.exe -ExecutionPolicy Bypass -NoProfile -File "%~dp0Sync-MagentoB2BClients.ps1"
if %ERRORLEVEL% NEQ 0 (
    echo [ERRO] Sincronizacao falhou com codigo %ERRORLEVEL%
    exit /b %ERRORLEVEL%
)
echo [OK] Sincronizacao concluida com sucesso
