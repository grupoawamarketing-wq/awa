#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TARGETS=(
  "$ROOT_DIR/app/code/GrupoAwamotos"
  "$ROOT_DIR/app/code/Awa"
  "$ROOT_DIR/app/code/Ayo"
  "$ROOT_DIR/scripts"
)

declare -a PHP_FILES=()

for target in "${TARGETS[@]}"; do
  if [ -d "$target" ]; then
    while IFS= read -r -d '' file; do
      PHP_FILES+=("$file")
    done < <(find "$target" -type f -name '*.php' -print0)
  fi
done

if [ "${#PHP_FILES[@]}" -eq 0 ]; then
  echo "Nenhum arquivo PHP customizado encontrado para validar."
  exit 0
fi

for file in "${PHP_FILES[@]}"; do
  php -l "$file" >/dev/null
done

echo "Sintaxe PHP validada em ${#PHP_FILES[@]} arquivos."
