# ============================================================
# Find-SqlCredentials.ps1
# Busca credenciais do SQL Server nos arquivos de config do Sectra
# Execute no servidor Sectra (192.168.0.252) via PowerShell Admin
# ============================================================

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  BUSCA DE CREDENCIAIS SQL SERVER" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# 1. Search Sectra config files (INI, XML, CONFIG, JSON)
Write-Host "--- 1. Arquivos de Config do Sectra ---" -ForegroundColor Yellow

$searchPaths = @(
    "C:\SECTRA",
    "C:\Script",
    "C:\inetpub",
    "C:\Program Files\SECTRA",
    "C:\Program Files (x86)\SECTRA"
)

$configPatterns = @("*.ini", "*.config", "*.xml", "*.json", "*.cfg", "*.conf", "*.properties", "*.txt")
$keywords = @("password", "senha", "pwd", "sa", "Data Source", "Server=", "Initial Catalog", "User ID", "uid=", "connection")

foreach ($path in $searchPaths) {
    if (Test-Path $path) {
        Write-Host "`n  Buscando em: $path" -ForegroundColor Green
        foreach ($pattern in $configPatterns) {
            $files = Get-ChildItem -Path $path -Filter $pattern -Recurse -ErrorAction SilentlyContinue |
                     Where-Object { $_.Length -lt 1MB }
            foreach ($file in $files) {
                $content = Get-Content $file.FullName -ErrorAction SilentlyContinue -Raw
                if ($content) {
                    foreach ($kw in $keywords) {
                        if ($content -match $kw) {
                            Write-Host "    ENCONTRADO: $($file.FullName)" -ForegroundColor Red
                            # Show relevant lines
                            $lines = $content -split "`n"
                            foreach ($line in $lines) {
                                if ($line -match $kw) {
                                    Write-Host "      > $($line.Trim())" -ForegroundColor White
                                }
                            }
                            break
                        }
                    }
                }
            }
        }
    }
}

# 2. Check Registry for SQL Server connection strings
Write-Host "`n--- 2. Registry (SECTRA) ---" -ForegroundColor Yellow
$regPaths = @(
    "HKLM:\SOFTWARE\SECTRA",
    "HKLM:\SOFTWARE\WOW6432Node\SECTRA",
    "HKCU:\SOFTWARE\SECTRA",
    "HKLM:\SOFTWARE\SECTRA\SECTRA_LOCAL",
    "HKLM:\SOFTWARE\WOW6432Node\SECTRA\SECTRA_LOCAL"
)

foreach ($regPath in $regPaths) {
    if (Test-Path $regPath) {
        Write-Host "  ENCONTRADO: $regPath" -ForegroundColor Red
        Get-ItemProperty $regPath -ErrorAction SilentlyContinue | Format-List
        # Check subkeys
        Get-ChildItem $regPath -Recurse -ErrorAction SilentlyContinue | ForEach-Object {
            Write-Host "    SubKey: $($_.PSPath)" -ForegroundColor White
            Get-ItemProperty $_.PSPath -ErrorAction SilentlyContinue | Format-List
        }
    }
}

# 3. Check ODBC DSN entries
Write-Host "`n--- 3. ODBC DSN Entries ---" -ForegroundColor Yellow
$dsnPaths = @(
    "HKLM:\SOFTWARE\ODBC\ODBC.INI",
    "HKCU:\SOFTWARE\ODBC\ODBC.INI",
    "HKLM:\SOFTWARE\WOW6432Node\ODBC\ODBC.INI"
)

foreach ($dsnPath in $dsnPaths) {
    if (Test-Path $dsnPath) {
        Get-ChildItem $dsnPath -ErrorAction SilentlyContinue | ForEach-Object {
            Write-Host "  DSN: $($_.PSChildName)" -ForegroundColor Green
            Get-ItemProperty $_.PSPath -ErrorAction SilentlyContinue | Format-List
        }
    }
}

# 4. Check SQL Server Configuration Manager (find instances)
Write-Host "`n--- 4. SQL Server Services ---" -ForegroundColor Yellow
Get-Service -Name "*SQL*" -ErrorAction SilentlyContinue | ForEach-Object {
    Write-Host "  $($_.Name): $($_.Status) ($($_.DisplayName))" -ForegroundColor White
}

# 5. Check for connection string files
Write-Host "`n--- 5. Buscando connection strings globalmente ---" -ForegroundColor Yellow
$globalSearch = Get-ChildItem -Path "C:\" -Include "*.ini","*.config","web.config","app.config","appsettings.json","connectionStrings.config" -Recurse -ErrorAction SilentlyContinue -Depth 4 |
    Where-Object { $_.Length -lt 500KB }

foreach ($file in $globalSearch) {
    $content = Get-Content $file.FullName -ErrorAction SilentlyContinue -Raw
    if ($content -and ($content -match "INDUSTRIAL" -or $content -match "201\.33\.193\.193" -or $content -match "Data Source.*SQL")) {
        Write-Host "  CONNECTION STRING em: $($file.FullName)" -ForegroundColor Red
        $lines = $content -split "`n"
        foreach ($line in $lines) {
            if ($line -match "(INDUSTRIAL|Data Source|Server=|password|pwd|sa|201\.33)" ) {
                Write-Host "    > $($line.Trim())" -ForegroundColor White
            }
        }
    }
}

# 6. Check for SECTRA INI files specifically
Write-Host "`n--- 6. SECTRA INI/Config Files ---" -ForegroundColor Yellow
$sectraInis = Get-ChildItem -Path "C:\SECTRA" -Include "*.ini","*.cfg","*.config","SECTRA.*" -Recurse -ErrorAction SilentlyContinue
foreach ($ini in $sectraInis) {
    Write-Host "  Arquivo: $($ini.FullName) ($($ini.Length) bytes)" -ForegroundColor Green
    Get-Content $ini.FullName -ErrorAction SilentlyContinue | ForEach-Object {
        if ($_ -match "(server|host|database|user|password|pwd|sa|port|instance|connection)" ) {
            Write-Host "    > $_" -ForegroundColor White
        }
    }
}

# 7. Try sqlcmd with sa and common passwords
Write-Host "`n--- 7. Testando acesso sqlcmd com sa ---" -ForegroundColor Yellow
$sqlServer = "201.33.193.193,1433"
$commonPasswords = @(
    "sa",
    "Sa123456",
    "INDUSTRIAL",
    "Sectra123",
    "sectra",
    "SECTRA",
    "admin",
    "Admin123",
    ""
)

# First check if sqlcmd is available
$sqlcmd = Get-Command sqlcmd -ErrorAction SilentlyContinue
if ($sqlcmd) {
    Write-Host "  sqlcmd encontrado: $($sqlcmd.Source)" -ForegroundColor Green

    # Try Windows Auth first with different service accounts
    Write-Host "  Testando Windows Auth..." -ForegroundColor Cyan
    try {
        $result = & sqlcmd -S $sqlServer -d INDUSTRIAL -E -Q "SELECT SUSER_NAME() AS [user]" -h -1 -W 2>&1
        if ($result -notmatch "Error|Login failed") {
            Write-Host "  WINDOWS AUTH FUNCIONA! User: $result" -ForegroundColor Red
        }
    } catch {}
} else {
    Write-Host "  sqlcmd NAO encontrado no PATH" -ForegroundColor Red
    # Try to find it
    $found = Get-ChildItem "C:\Program Files\Microsoft SQL Server" -Filter "sqlcmd.exe" -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($found) {
        Write-Host "  sqlcmd encontrado em: $($found.FullName)" -ForegroundColor Green
    }
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  BUSCA CONCLUIDA" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "PROXIMO PASSO:" -ForegroundColor Yellow
Write-Host "  Se encontrou credenciais com escrita (usuario sa ou similar)," -ForegroundColor White
Write-Host "  informe o usuario e senha para configurar no Magento Admin:" -ForegroundColor White
Write-Host "  Stores > Config > GrupoAwamotos > ERP Integration > Conexao de Escrita" -ForegroundColor Green
