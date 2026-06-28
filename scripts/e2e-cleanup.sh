#!/usr/bin/env bash
# e2e-cleanup.sh — remove artefatos e scripts ad-hoc de sessões de agentes
set -euo pipefail

ROOT="/home/jessessh/htdocs/srv1113343.hstgr.cloud"
E2E="$ROOT/tests/e2e"

echo "=== Limpeza E2E ==="

for dir in test-results test-results-safe reports playwright-report audit_results screenshots; do
  if [[ -d "$E2E/$dir" ]]; then
    rm -rf "$E2E/$dir" 2>/dev/null || sudo rm -rf "$E2E/$dir" 2>/dev/null || true
    echo "  removido: $dir/"
  fi
done

find "$E2E" -maxdepth 1 -type f \( -name '*.mjs' -o -name '*.js' -o -name '*.png' -o -name '*.cjs' \) \
  ! -name 'playwright.config.ts' -delete 2>/dev/null || true

find "$E2E" -maxdepth 1 -type f \( -name 'out*.txt' -o -name 'out-*.txt' -o -name 'audit_*.json' -o -name '*_result.json' \
  -o -name 'results.json' -o -name 'header_css_source.txt' -o -name 'search_input_rules.txt' -o -name 'test_out*.txt' -o -name 'test_output.txt' \
  -o -name 'mobile_*_dom.html' \) -delete 2>/dev/null || true

rm -f "$E2E"/specs/debug_*.spec.ts "$E2E"/specs/debug_*.js \
      "$E2E"/specs/inspect_*.spec.ts "$E2E"/specs/pdp_debug.spec.ts \
      "$E2E"/specs/seed.spec.ts "$E2E"/specs/vm-final.spec.ts \
      "$E2E"/specs/screenshot.spec.ts "$E2E"/specs/screenshot_carousels.spec.ts 2>/dev/null || true

rm -rf "$E2E/scripts/" 2>/dev/null || true

rm -f /tmp/verify*.js /tmp/verify-header.js /tmp/header_audit.* /tmp/header-audit.png \
      /tmp/header-debug-run.mjs /tmp/plp_audit.html 2>/dev/null || true

pkill -f 'chrome-headless-shell' 2>/dev/null || true
pkill -f 'node -e.*playwright' 2>/dev/null || true

echo
du -sh "$E2E" 2>/dev/null || true
echo "Feito. Estrutura vazia em tests/e2e/ — pronta para nova suite."
