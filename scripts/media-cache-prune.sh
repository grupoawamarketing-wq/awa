#!/usr/bin/env bash
# media-cache-prune.sh — limpa cache de imagens Magento e artefatos de audit em pub/media
set -euo pipefail

ROOT="/home/jessessh/htdocs/srv1113343.hstgr.cloud"
MEDIA="$ROOT/pub/media"

echo "=== Media cache prune ==="

if [[ -d "$MEDIA/catalog/product/cache" ]]; then
  BEFORE=$(du -sh "$MEDIA/catalog/product/cache" 2>/dev/null | awk '{print $1}')
  find "$MEDIA/catalog/product/cache" -mindepth 1 -delete 2>/dev/null \
    || sudo find "$MEDIA/catalog/product/cache" -mindepth 1 -delete 2>/dev/null || true
  AFTER=$(du -sh "$MEDIA/catalog/product/cache" 2>/dev/null | awk '{print $1}')
  echo "  catalog/product/cache: ${BEFORE} -> ${AFTER}"
fi

for dir in visual-audit awa-audit; do
  if [[ -d "$MEDIA/$dir" ]]; then
    rm -rf "$MEDIA/$dir"
    echo "  removido: media/$dir/"
  fi
done

find "$MEDIA" -maxdepth 1 -type f \( -name 'va-*.png' -o -name 'visual-audit*.png' -o -name '*-audit*.png' \) -delete 2>/dev/null || true

PREPROC="$ROOT/var/view_preprocessed/pub/static/frontend/ayo"
if [[ -d "$PREPROC" ]]; then
  KEEP=(ayo_default ayo_home5)
  for dir in "$PREPROC"/*/; do
    [[ -d "$dir" ]] || continue
    name=$(basename "$dir")
    keep=0
    for k in "${KEEP[@]}"; do [[ "$name" == "$k" ]] && keep=1 && break; done
    [[ $keep -eq 0 ]] && rm -rf "$dir" && echo "  removido: view_preprocessed/ayo/$name/"
  done
fi

echo "Feito."
