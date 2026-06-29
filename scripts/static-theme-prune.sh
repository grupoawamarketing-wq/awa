#!/usr/bin/env bash
# static-theme-prune.sh — remove static deploy de temas Ayo não usados na loja
# Tema ativo: AWA_Custom/ayo_home5_child (cadeia: ayo_home5 → ayo_default → Magento/blank)
set -euo pipefail

ROOT="/home/jessessh/htdocs/srv1113343.hstgr.cloud"
AYO_STATIC="$ROOT/pub/static/frontend/ayo"
MAGENTO_STATIC="$ROOT/pub/static/frontend/Magento"

KEEP_AYO=(ayo_default ayo_home5)

echo "=== Static theme prune ==="
if [[ ! -d "$AYO_STATIC" ]]; then
  echo "  (sem $AYO_STATIC — pulando)"
  exit 0
fi

BEFORE=$(du -sh "$ROOT/pub/static" 2>/dev/null | awk '{print $1}')

for dir in "$AYO_STATIC"/*/; do
  [[ -d "$dir" ]] || continue
  name=$(basename "$dir")
  keep=0
  for k in "${KEEP_AYO[@]}"; do
    [[ "$name" == "$k" ]] && keep=1 && break
  done
  if [[ $keep -eq 0 ]]; then
    rm -rf "$dir"
    echo "  removido: ayo/$name/"
  fi
done

if [[ -d "$MAGENTO_STATIC/luma" ]]; then
  rm -rf "$MAGENTO_STATIC/luma"
  echo "  removido: Magento/luma/"
fi

rm -rf "$ROOT/pub/static/_cache"/* 2>/dev/null || true
echo "  limpo: pub/static/_cache/"

AFTER=$(du -sh "$ROOT/pub/static" 2>/dev/null | awk '{print $1}')
echo "  pub/static: ${BEFORE} -> ${AFTER}"
echo "Feito."
