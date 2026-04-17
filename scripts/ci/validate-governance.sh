#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

REQUIRED_FILES=(
  "$ROOT_DIR/CONTRIBUTING.md"
  "$ROOT_DIR/SECURITY.md"
  "$ROOT_DIR/RELEASE_TEMPLATE.md"
  "$ROOT_DIR/docs/development-standards.md"
  "$ROOT_DIR/docs/release-process.md"
  "$ROOT_DIR/docs/code-review-checklist.md"
  "$ROOT_DIR/docs/api-standards.md"
  "$ROOT_DIR/docs/testing-strategy.md"
  "$ROOT_DIR/docs/module-template.md"
  "$ROOT_DIR/docs/frontend-standards.md"
  "$ROOT_DIR/docs/smoke-checklist.md"
  "$ROOT_DIR/docs/ownership-model.md"
  "$ROOT_DIR/docs/b2b-adoption.md"
  "$ROOT_DIR/.github/CODEOWNERS"
  "$ROOT_DIR/.github/pull_request_template.md"
  "$ROOT_DIR/.github/ISSUE_TEMPLATE/bug_report.md"
  "$ROOT_DIR/.github/ISSUE_TEMPLATE/feature_request.md"
  "$ROOT_DIR/.github/ISSUE_TEMPLATE/config.yml"
)

for file in "${REQUIRED_FILES[@]}"; do
  if [ ! -f "$file" ]; then
    echo "Arquivo obrigatorio ausente: $file"
    exit 1
  fi
done

if ! grep -q "Conventional Commits" "$ROOT_DIR/CONTRIBUTING.md"; then
  echo "CONTRIBUTING.md deve mencionar Conventional Commits."
  exit 1
fi

if ! grep -q "Checklist De Seguranca" "$ROOT_DIR/docs/development-standards.md"; then
  echo "O guia de padroes deve conter checklist de seguranca."
  exit 1
fi

if ! grep -q "Status Codes" "$ROOT_DIR/docs/api-standards.md"; then
  echo "O guia de API deve documentar status codes."
  exit 1
fi

if ! grep -q "Testes Unitarios" "$ROOT_DIR/docs/testing-strategy.md"; then
  echo "A estrategia de testes deve documentar testes unitarios."
  exit 1
fi

if ! grep -q "Estrutura Minima" "$ROOT_DIR/docs/module-template.md"; then
  echo "O template de modulo deve documentar estrutura minima."
  exit 1
fi

if ! grep -q "Layout XML" "$ROOT_DIR/docs/frontend-standards.md"; then
  echo "O guia frontend deve documentar Layout XML."
  exit 1
fi

if ! grep -q "Smoke Geral" "$ROOT_DIR/docs/smoke-checklist.md"; then
  echo "O smoke checklist deve documentar validacoes gerais."
  exit 1
fi

if ! grep -q "Areas Criticas" "$ROOT_DIR/docs/ownership-model.md"; then
  echo "O modelo de ownership deve documentar areas criticas."
  exit 1
fi

if ! grep -q "Padroes Por Subdominio" "$ROOT_DIR/docs/b2b-adoption.md"; then
  echo "O guia de adocao B2B deve documentar padroes por subdominio."
  exit 1
fi

if ! grep -q "Como Reportar" "$ROOT_DIR/SECURITY.md"; then
  echo "SECURITY.md deve definir como reportar vulnerabilidades."
  exit 1
fi

if ! grep -q "SemVer" "$ROOT_DIR/RELEASE_TEMPLATE.md"; then
  echo "RELEASE_TEMPLATE.md deve mencionar SemVer."
  exit 1
fi

if ! grep -q "^\\*" "$ROOT_DIR/.github/CODEOWNERS"; then
  echo "CODEOWNERS deve definir owner padrao."
  exit 1
fi

echo "Arquivos e padroes minimos de governanca validados."
