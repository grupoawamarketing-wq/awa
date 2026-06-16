#!/usr/bin/env bash
# manual-test-mode.sh — pausa automações na VPS para teste manual no browser
# Uso: bash scripts/manual-test-mode.sh {on|off|status}
set -euo pipefail

ROOT="/home/jessessh/htdocs/srv1113343.hstgr.cloud"
FLAG="$ROOT/var/tmp/manual-test-mode"
LOG="$ROOT/var/log/manual_test_mode.log"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG"; }

kill_automation() {
  pkill -9 -f 'npx playwright' 2>/dev/null || true
  pkill -9 -f 'playwright test' 2>/dev/null || true
  pkill -9 -f 'node.*playwright' 2>/dev/null || true
  pkill -9 -f 'node -e.*playwright' 2>/dev/null || true
  pkill -9 -f 'playwright-mcp' 2>/dev/null || true
  pkill -9 -f 'chrome-devtools-mcp' 2>/dev/null || true
  pkill -9 -f 'testsprite-mcp' 2>/dev/null || true
  pkill -9 -f 'run-test-mcp-server' 2>/dev/null || true
  pkill -9 -f 'chromium_headless_shell|chrome-headless-shell' 2>/dev/null || true
  pkill -9 -f 'playwright_chromiumdev_profile' 2>/dev/null || true
  rm -rf /tmp/playwright_chromiumdev_profile-* 2>/dev/null || true
  sudo systemctl stop chrome-cdp-9222.service 2>/dev/null || true
}

status() {
  echo "=== Modo teste manual ==="
  if [[ -f "$FLAG" ]]; then
    echo "Estado: ATIVO (desde $(stat -c '%y' "$FLAG" 2>/dev/null | cut -d. -f1))"
    echo "Agentes/automação: NÃO devem rodar Playwright, MCP browser ou scripts em tests/e2e"
  else
    echo "Estado: INATIVO — automações permitidas"
  fi
  echo
  local n
  n=$(pgrep -cf 'playwright|chrome-headless|chromium.*headless' 2>/dev/null || echo 0)
  echo "Processos Playwright/Chrome headless: $n"
  free -h | awk '/^Mem:/{print "RAM: "$3" usada / "$7" disponível"}'
  uptime | sed 's/.*load average/load average/'
}

on() {
  mkdir -p "$(dirname "$FLAG")"
  kill_automation
  bash "$ROOT/scripts/e2e-cleanup.sh" >/dev/null 2>&1 || true
  bash "$ROOT/scripts/mcp-performance.sh" cleanup >/dev/null 2>&1 || true
  bash "$ROOT/scripts/mcp-serial-guard.sh" >/dev/null 2>&1 || true
  touch "$FLAG"
  log "manual-test-mode ON"
  status
  echo
  echo "Pronto. Teste o site no seu browser (awamotos.com)."
  echo "Para reativar automações: bash scripts/manual-test-mode.sh off"
}

off() {
  rm -f "$FLAG"
  log "manual-test-mode OFF"
  status
  echo
  echo "Automações liberadas. E2E manual: cd tests/e2e && npx playwright test"
}

case "${1:-status}" in
  on|start|enable)  on ;;
  off|stop|disable) off ;;
  status|st)        status ;;
  *)
    echo "Uso: $0 {on|off|status}"
    exit 1
    ;;
esac
