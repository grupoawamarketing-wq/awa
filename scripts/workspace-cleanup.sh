#!/usr/bin/env bash
# workspace-cleanup.sh — limpeza de artefatos de agentes, logs rotacionados e /tmp
set -euo pipefail

ROOT="/home/jessessh/htdocs/srv1113343.hstgr.cloud"
cd "$ROOT"

echo "=== Limpeza workspace AWA ==="

# E2E (Playwright specs oficiais preservados)
if [[ -x "$ROOT/scripts/e2e-cleanup.sh" ]]; then
  bash "$ROOT/scripts/e2e-cleanup.sh"
else
  echo "  (e2e-cleanup.sh não encontrado — pulando)"
fi

# Static: remove deploy de temas Ayo não usados (loja = ayo_home5_child)
if [[ -x "$ROOT/scripts/static-theme-prune.sh" ]]; then
  bash "$ROOT/scripts/static-theme-prune.sh"
fi

if [[ -x "$ROOT/scripts/media-cache-prune.sh" ]]; then
  bash "$ROOT/scripts/media-cache-prune.sh"
fi

if [[ -x "$ROOT/scripts/theme-backup-prune.sh" ]]; then
  bash "$ROOT/scripts/theme-backup-prune.sh"
fi

rm -f "$ROOT/phpunit.phar" 2>/dev/null || true
find "$ROOT/_csv" -type f -name 'catalog_product_backup_*' -mtime +30 -delete 2>/dev/null || true

# ZIPs de instalação do tema — fora do webroot (segurança + espaço)
if [[ -d "$ROOT/_arquivos_tema_Ayo" ]]; then
  ARCHIVE="${AWA_THEME_ARCHIVE:-$ROOT/_backups/theme-vendor-zips}"
  mkdir -p "$ARCHIVE"
  mv "$ROOT/_arquivos_tema_Ayo"/* "$ARCHIVE/" 2>/dev/null || true
  rmdir "$ROOT/_arquivos_tema_Ayo" 2>/dev/null || true
  echo "  movido: _arquivos_tema_Ayo/ -> $ARCHIVE/"
fi

# Imagens de audit em pub/ (não são media da loja)
find "$ROOT/pub" -maxdepth 1 -type f \( -name '*.png' -o -name '*.jpeg' -o -name '*.jpg' \) -delete 2>/dev/null || true
rm -rf "$ROOT/.kilo/node_modules" 2>/dev/null || true

echo
echo "=== Artefatos na raiz ==="
for dir in screenshots test-results playwright-report blob-report; do
  if [[ -d "$ROOT/$dir" ]]; then
    rm -rf "$ROOT/$dir" 2>/dev/null || sudo rm -rf "$ROOT/$dir" 2>/dev/null || true
    echo "  removido: $dir/"
  fi
done

# tmp/ do projeto (debug de agentes — gitignored)
if [[ -d "$ROOT/tmp" ]]; then
  rm -rf "$ROOT/tmp"/* 2>/dev/null || sudo rm -rf "$ROOT/tmp"/* 2>/dev/null || true
  echo "  limpo: tmp/"
fi

# QA screens e artifacts
rm -rf "$ROOT/var/qa-screens" 2>/dev/null || sudo rm -rf "$ROOT/var/qa-screens" 2>/dev/null || true
echo "  removido: var/qa-screens/" 2>/dev/null || true
rm -rf "$ROOT/artifacts"/* 2>/dev/null || sudo rm -rf "$ROOT/artifacts"/* 2>/dev/null || true
echo "  limpo: artifacts/"

# Exports de auditoria visual (preserva .txt)
rm -rf "$ROOT/var/export/awa_audit_screens" 2>/dev/null || sudo rm -rf "$ROOT/var/export/awa_audit_screens" 2>/dev/null || true
find "$ROOT/var/export" -maxdepth 1 -type f \( -name '*.png' -o -name '*.jpeg' -o -name '*.jpg' \) -delete 2>/dev/null || true
echo "  limpas imagens em var/export/"

# Imagens de audit (preserva .md)
find "$ROOT/audit" -maxdepth 1 -type f \( -name '*.png' -o -name '*.jpeg' -o -name '*.jpg' -o -name '*.webp' \) -delete 2>/dev/null || true
find "$ROOT/visual-qa" -type f \( -name '*.png' -o -name '*.jpeg' -o -name '*.jpg' \) -delete 2>/dev/null || true
echo "  limpas imagens em audit/ e visual-qa/"

# Imagens/HTML de audit na raiz (agentes Playwright)
find "$ROOT" -maxdepth 1 -type f \( -name '*.png' -o -name '*.jpeg' -o -name '*.jpg' -o -name '*.webp' \) -delete 2>/dev/null || true
rm -f "$ROOT/home.html" 2>/dev/null || true
echo "  removidas imagens/HTML de audit na raiz"

# var/audit e screenshots soltos em var/
rm -rf "$ROOT/var/audit"/* 2>/dev/null || sudo rm -rf "$ROOT/var/audit"/* 2>/dev/null || true
rm -f "$ROOT/var/desktop.png" 2>/dev/null || true
echo "  limpo: var/audit/ e var/desktop.png"

# var/tmp — preserva locks e flags operacionais
if [[ -d "$ROOT/var/tmp" ]]; then
  find "$ROOT/var/tmp" -type f ! -name '*.lock' \
    ! -name 'cursor-dev-active' ! -name 'manual-test-mode' ! -name 'sectra-paused-by-cursor' \
    \( -name '*.png' -o -name '*.jpeg' -o -name '*.jpg' -o -name '*.mjs' -o -name '*.html' \
       -o -name 'awa_*.js' -o -name 'uat-*' \) -delete 2>/dev/null || true
  find "$ROOT/var/tmp" -type f -mtime +5 ! -name '*.lock' \
    ! -name 'cursor-dev-active' ! -name 'manual-test-mode' ! -name 'sectra-paused-by-cursor' \
    -delete 2>/dev/null || true
  echo "  limpo: var/tmp/ (artefatos de agentes; locks preservados)"
fi

# Backups CSS tier antigos
find "$ROOT/var/backup" -maxdepth 1 -type d -name 'css_tier*' -mtime +14 -exec rm -rf {} + 2>/dev/null || true

# Reports Magento antigos
if [[ -d "$ROOT/var/report" ]]; then
  find "$ROOT/var/report" -type f -mtime +7 -delete 2>/dev/null || true
  echo "  limpo: var/report/ (>7 dias)"
fi

# Backups env.php (gitignored)
rm -f "$ROOT/app/etc/env.php.bak"* "$ROOT/app/etc/env.php.bak-premerge" 2>/dev/null || true

# tests/mcp e tests/unit legado
rm -rf "$ROOT/tests/mcp/node_modules" "$ROOT/tests/mcp/test-results" 2>/dev/null || true
rm -f "$ROOT/tests/unit/"*.test.js 2>/dev/null || true
rm -f "$ROOT"/*.bak "$ROOT/scripts/"*.bak 2>/dev/null || true

echo
echo "=== Logs rotacionados (one-shot / debug) ==="
for f in var/log/debug.log.1 var/log/b2b_razao_social_backfill.log.1 \
         var/log/b2b_phone_backfill.log.1 var/log/b2b_registration_backfill.log.1; do
  if [[ -f "$f" ]]; then
    rm -f "$f" && echo "  removido: $f"
  fi
done
# Comprimir cron.log.1 se grande
if [[ -f var/log/cron.log.1 ]] && [[ $(stat -c%s var/log/cron.log.1 2>/dev/null || echo 0) -gt 1048576 ]]; then
  gzip -f var/log/cron.log.1 2>/dev/null && echo "  comprimido: var/log/cron.log.1" || true
fi

# db.log do Magento (query log — cresce rápido com debug ativo)
if [[ -f var/debug/db.log ]] && [[ $(stat -c%s var/debug/db.log 2>/dev/null || echo 0) -gt 10485760 ]]; then
  truncate -s 0 var/debug/db.log 2>/dev/null || sudo truncate -s 0 var/debug/db.log 2>/dev/null || true
  rm -f var/debug/db.log.*.gz 2>/dev/null || sudo rm -f var/debug/db.log.*.gz 2>/dev/null || true
  echo "  truncado: var/debug/db.log (>10 MB)"
fi

# Cache Playwright MCP (perfil leve não usa)
rm -rf "$ROOT/.playwright-mcp"/* 2>/dev/null || true
if [[ -x "$ROOT/scripts/playwright-cache-prune.sh" ]]; then
  bash "$ROOT/scripts/playwright-cache-prune.sh"
fi
if [[ -x "$ROOT/scripts/cursor-server-prune.sh" ]]; then
  bash "$ROOT/scripts/cursor-server-prune.sh"
fi
if [[ -x "$ROOT/scripts/vscode-server-prune.sh" ]]; then
  bash "$ROOT/scripts/vscode-server-prune.sh"
fi

echo
echo "=== /tmp do sistema ==="
rm -f /tmp/verify*.js /tmp/verify-header.js /tmp/header_audit.* /tmp/header-audit.png \
      /tmp/header-debug-run.mjs /tmp/plp_audit.html 2>/dev/null || true
rm -f /tmp/awa*.html /tmp/awa*.png /tmp/awa*.js 2>/dev/null || true
rm -rf /tmp/playwright_chromiumdev_profile-* /tmp/playwright-artifacts-* 2>/dev/null || true
echo "  limpo /tmp (awa-*, verify*, playwright profiles)"

echo
echo "=== Processos órfãos ==="
pkill -f 'chrome-headless-shell' 2>/dev/null || true
pkill -f 'node -e.*playwright' 2>/dev/null || true
pkill -f '/tmp/verify' 2>/dev/null || true

echo
echo "=== Resumo ==="
du -sh "$ROOT/tmp" "$ROOT/tests/e2e" "$ROOT/var/log" "$ROOT/artifacts" "$ROOT/audit" 2>/dev/null || true
free -h | awk '/^Mem:/{print "RAM: "$3" usada / "$7" disponível"}'
uptime | sed 's/.*load/load/'
echo "Feito."
