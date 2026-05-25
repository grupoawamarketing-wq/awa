#!/usr/bin/env bash
# mcp-performance.sh — diagnóstico e troca de perfil MCP (leve vs pesado)
# Perfil LEVE (padrão): zero Chrome, zero browser MCP — ~0 MB extra
# Perfil PESADO: só Playwright (1 Chrome headless) — use só para QA visual
set -euo pipefail

ROOT="/home/jessessh/htdocs/srv1113343.hstgr.cloud"
CURSOR_MCP="/home/deploy/.cursor/mcp.json"
CURSOR_HEAVY="/home/deploy/.cursor/mcp.heavy.json"
PROJECT_MCP="$ROOT/.vscode/mcp.json"
PROJECT_HEAVY="$ROOT/.vscode/mcp.heavy.json"
CDP_SERVICE="chrome-cdp-9222.service"
LIGHT_MARKER="$ROOT/.vscode/.mcp-profile-light"

status() {
  set +e
  echo "=== Diagnóstico MCP / VPS ==="
  echo
  free -h | awk '/^Mem:/{print "RAM: "$3" usada / "$2" total ("$7" disponível)"}'
  uptime | sed 's/.*load average/load average/'
  echo

  local ps_data chrome_mb mcp_mb cursor_mb chrome_count ext_hosts
  ps_data=$(ps aux 2>/dev/null)
  chrome_mb=$(ps -C chrome -o rss= 2>/dev/null | awk '{s+=$1} END {printf "%.0f", s/1024+0}')
  mcp_mb=$(echo "$ps_data" | grep -E 'playwright-mcp|chrome-devtools-mcp|testsprite|run-test-mcp|mcp-server' | grep -v grep | awk '{s+=$6} END {printf "%.0f", s/1024+0}')
  cursor_mb=$(echo "$ps_data" | grep -E 'cursor-server|vscode-server' | grep -v grep | awk '{s+=$6} END {printf "%.0f", s/1024+0}')
  chrome_count=$(echo "$ps_data" | grep -c '[c]hrome' 2>/dev/null || true)
  chrome_count=${chrome_count:-0}
  ext_hosts=$(echo "$ps_data" | grep -c 'extensionHost' || echo 0)

  echo "Chrome headless:     ${chrome_mb:-0} MB (${chrome_count} processos)"
  echo "MCP (Node):          ${mcp_mb:-0} MB"
  echo "Cursor/VS Code:      ${cursor_mb:-0} MB"
  echo "Extension hosts:     ${ext_hosts}"
  echo

  if [[ -f "$LIGHT_MARKER" ]] || grep -q '"mcpServers": {}' "$CURSOR_MCP" 2>/dev/null; then
    echo "Perfil ativo:        LEVE (recomendado para produção)"
  else
    echo "Perfil ativo:        PESADO (Playwright — recarregue MCP após trocar)"
  fi

  if systemctl is-active --quiet "$CDP_SERVICE" 2>/dev/null; then
    echo "CDP systemd:         ATIVO (~500 MB — desnecessário, rode: $0 heavy-off)"
  else
    echo "CDP systemd:         parado"
  fi
  echo

  echo "Top CPU agora:"
  ps aux --sort=-%cpu 2>/dev/null | awk 'NR==1 || ($3>=5 && NR<=8){print}' | head -8
  set -e
}

heavy_on() {
  cp "$CURSOR_HEAVY" "$CURSOR_MCP"
  cp "$PROJECT_HEAVY" "$PROJECT_MCP"
  rm -f "$LIGHT_MARKER"
  echo "Perfil PESADO ativado (Playwright apenas — 1 Chrome)."
  echo "Recarregue MCP no Cursor: Settings → MCP → Reload"
  echo "Após QA visual, rode: $0 heavy-off"
}

heavy_off() {
  cat > "$CURSOR_MCP" <<'EOF'
{
  "mcpServers": {}
}
EOF
  cat > "$PROJECT_MCP" <<'EOF'
{
	"_comment": "Perfil LEVE — só filesystem. Browser/QA: scripts/mcp-performance.sh heavy-on",
	"servers": {
		"filesystem": {
			"type": "stdio",
			"command": "mcp-server-filesystem",
			"args": [
				"${workspaceFolder}/app/code/GrupoAwamotos",
				"${workspaceFolder}/app/code/Awa",
				"${workspaceFolder}/app/design/frontend/AWA_Custom",
				"${workspaceFolder}/app/etc",
				"${workspaceFolder}/var/log"
			],
			"env": {
				"NODE_OPTIONS": "--max-old-space-size=128"
			}
		}
	},
	"inputs": []
}
EOF
  touch "$LIGHT_MARKER"
  sudo systemctl stop "$CDP_SERVICE" 2>/dev/null || true
  sudo systemctl disable "$CDP_SERVICE" 2>/dev/null || true
  cleanup_quiet
  echo "Perfil LEVE ativado. CDP systemd parado e desabilitado."
  echo "Recarregue MCP no Cursor: Settings → MCP → Reload"
}

cleanup_quiet() {
  pkill -f 'playwright-mcp' 2>/dev/null || true
  pkill -f 'chrome-devtools-mcp' 2>/dev/null || true
  pkill -f 'testsprite-mcp' 2>/dev/null || true
  pkill -f 'run-test-mcp-server' 2>/dev/null || true
  sleep 1
  pgrep -af 'mcp-chrome' 2>/dev/null | awk '{print $1}' | xargs -r kill 2>/dev/null || true
}

cleanup() {
  echo "Encerrando processos MCP órfãos..."
  cleanup_quiet
  sudo systemctl stop "$CDP_SERVICE" 2>/dev/null || true
  echo "Feito. Rode 'status' para confirmar."
}

case "${1:-status}" in
  status|st)    status ;;
  heavy-on|on)  heavy_on ;;
  heavy-off|off|light) heavy_off ;;
  cleanup|clean) cleanup ;;
  *)
    echo "Uso: $0 {status|heavy-on|heavy-off|cleanup}"
    echo
    echo "  status     — RAM, Chrome, MCP ativos"
    echo "  heavy-on   — Playwright MCP (QA visual, ~300-500 MB)"
    echo "  heavy-off  — Perfil leve (padrão produção, ~0 MB extra)"
    echo "  cleanup    — Mata processos MCP/Chrome órfãos"
    exit 1
    ;;
esac
