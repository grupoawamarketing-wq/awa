#!/usr/bin/env bash

set -euo pipefail

CONVENTIONAL_PATTERN='^(feat|fix|refactor|perf|test|docs|build|ci|chore|revert)(\([a-z0-9._/-]+\))?!?: .+'

validate_subject() {
  local subject="$1"

  if [[ ! "$subject" =~ $CONVENTIONAL_PATTERN ]]; then
    echo "Commit fora do padrao semantico: $subject"
    return 1
  fi

  return 0
}

subjects=()

if [ "${1:-}" != "" ]; then
  while IFS= read -r line; do
    [ -n "$line" ] && subjects+=("$line")
  done < <(git log --format=%s "$1")
elif [ -n "${GITHUB_BASE_REF:-}" ]; then
  git fetch --no-tags --depth=200 origin "${GITHUB_BASE_REF}:${GITHUB_BASE_REF}" >/dev/null 2>&1 || true

  while IFS= read -r line; do
    [ -n "$line" ] && subjects+=("$line")
  done < <(git log --format=%s "${GITHUB_BASE_REF}..HEAD")
else
  subjects+=("$(git log -1 --format=%s HEAD)")
fi

if [ "${#subjects[@]}" -eq 0 ]; then
  echo "Nenhum commit encontrado para validar."
  exit 0
fi

for subject in "${subjects[@]}"; do
  validate_subject "$subject"
done

echo "Mensagens de commit validadas com Conventional Commits."
