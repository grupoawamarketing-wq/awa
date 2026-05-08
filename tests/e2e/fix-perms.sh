#!/bin/bash
# Fix permissões de artefatos root criados por subprocessos do Firefox/Playwright.
# Executar antes de npx playwright test para evitar EACCES no cleanup inicial.
TESTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
for RESULTS_DIR in "${TESTS_DIR}/test-results" "${TESTS_DIR}/test-results-safe"; do
  if [ -d "${RESULTS_DIR}" ]; then
    sudo chown -R "$(id -u):$(id -g)" "${RESULTS_DIR}" 2>/dev/null || true
  fi
done
