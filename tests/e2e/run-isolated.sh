#!/usr/bin/env bash
# run-isolated.sh — Executa Playwright/Chrome isolado num cgroup v2 (systemd scope)
# para NUNCA degradar a sessao SSH, o VS Code Server, o PHP-FPM/MySQL ou a VPS.
#
# Como funciona:
#   - systemd-run --user --scope cria um cgroup dedicado com CPU/RAM limitados.
#   - CPUWeight baixo faz o scheduler do kernel ceder CPU a processos interativos
#     (SSH/IDE) sob contencao — a VPS continua responsiva mesmo com Chrome a 100%.
#   - MemoryMax impoe um teto rigido: se o Chrome estourar, o OOM mata SO o cgroup
#     de teste, protegendo Magento/MySQL/SSH de serem mortos pelo OOM global.
#   - nice/ionice rebaixam ainda mais prioridade de CPU e de I/O de disco.
#   - Limpa processos orfaos antes e depois (browsers zumbis sao a causa #1 de
#     lentidao acumulada na VPS).
#
# Uso:
#   ./run-isolated.sh                         # roda `playwright test` (config padrao)
#   ./run-isolated.sh test:mcp-visual         # roda um script npm de tests/e2e
#   ./run-isolated.sh --config=pw-pdp.config.ts specs/pdp.spec.ts   # args diretos
#
# Variaveis de ajuste (override por env):
#   PW_CPU_QUOTA   (default 400%)  — max. de CPU; 400% = 4 de 8 cores
#   PW_CPU_WEIGHT  (default 20)    — peso de CPU sob contencao (default systemd=100)
#   PW_MEM_HIGH    (default 3G)    — throttle suave de memoria
#   PW_MEM_MAX     (default 4G)    — teto rigido (OOM mata so este cgroup)
#   PW_IO_WEIGHT   (default 20)    — peso de I/O de disco
#   PW_NICE        (default 15)    — niceness (0..19, maior = menos prioridade)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

PW_CPU_QUOTA="${PW_CPU_QUOTA:-400%}"
PW_CPU_WEIGHT="${PW_CPU_WEIGHT:-20}"
PW_MEM_HIGH="${PW_MEM_HIGH:-3G}"
PW_MEM_MAX="${PW_MEM_MAX:-4G}"
PW_IO_WEIGHT="${PW_IO_WEIGHT:-20}"
PW_NICE="${PW_NICE:-15}"

kill_orphans() {
  pkill -9 -x 'chrome-headless-shell' 2>/dev/null || true
  pkill -9 -x 'chromium'              2>/dev/null || true
  pkill -9 -x 'chromium-browser'      2>/dev/null || true
  pkill -9 -x 'google-chrome'         2>/dev/null || true
  pkill -9 -x 'firefox'               2>/dev/null || true
  pkill -9 -x 'firefox-bin'           2>/dev/null || true
  pkill -9 -f 'Web Content'           2>/dev/null || true
}

cleanup() {
  kill_orphans
  systemctl --user stop "$SCOPE_UNIT" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

echo "-> Limpando browsers orfaos antes de iniciar..."
kill_orphans

if [[ $# -eq 0 ]]; then
  CMD=(npx playwright test)
elif [[ "${1}" != -* ]]; then
  SCRIPT_NAME="$1"; shift
  CMD=(npm run "$SCRIPT_NAME" --)
  [[ $# -gt 0 ]] && CMD+=("$@")
else
  CMD=(npx playwright test "$@")
fi

SCOPE_UNIT="pw-e2e-$$"

echo "-> Isolando em cgroup '${SCOPE_UNIT}.scope':"
echo "    CPUQuota=${PW_CPU_QUOTA}  CPUWeight=${PW_CPU_WEIGHT}  IOWeight=${PW_IO_WEIGHT}"
echo "    MemoryHigh=${PW_MEM_HIGH}  MemoryMax=${PW_MEM_MAX}  nice=${PW_NICE}"
echo "    Comando: ${CMD[*]}"
echo

exec systemd-run --user --scope \
  --unit="$SCOPE_UNIT" \
  --expand-environment=no \
  -p "CPUQuota=${PW_CPU_QUOTA}" \
  -p "CPUWeight=${PW_CPU_WEIGHT}" \
  -p "IOWeight=${PW_IO_WEIGHT}" \
  -p "MemoryHigh=${PW_MEM_HIGH}" \
  -p "MemoryMax=${PW_MEM_MAX}" \
  -p "MemorySwapMax=0" \
  -p "OOMPolicy=kill" \
  nice -n "${PW_NICE}" ionice -c2 -n7 "${CMD[@]}"
