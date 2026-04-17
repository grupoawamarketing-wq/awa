#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DOC_FILES=(
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
)

missing=0

for file in "${DOC_FILES[@]}"; do
  if [ ! -f "$file" ]; then
    echo "Arquivo de documentacao ausente: $file"
    missing=1
    continue
  fi

  while IFS= read -r match; do
    target="${match#*\`}"
    target="${target%\`*}"

    if [[ "$target" == docs/* ]] || [[ "$target" == .github/* ]] || [[ "$target" == scripts/* ]] || [[ "$target" == *.md ]]; then
      if [ ! -e "$ROOT_DIR/$target" ]; then
        echo "Referencia quebrada em $file -> $target"
        missing=1
      fi
    fi
  done < <(grep -o '\`[^`]*\`' "$file" || true)
done

if [ "$missing" -ne 0 ]; then
  exit 1
fi

echo "Referencias internas basicas de documentacao validadas."
