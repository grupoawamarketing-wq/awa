#!/bin/bash
# Fix permissões de artefatos root criados por subprocessos do Firefox/Playwright.
# Executar antes de npx playwright test para evitar EACCES no cleanup inicial.
TESTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RESULTS_DIR="${TESTS_DIR}/test-results"
if [ -d "${RESULTS_DIR}" ]; then
  sudo chown -R "$(id -u):$(id -g)" "${RESULTS_DIR}" 2>/dev/null || true
fi
