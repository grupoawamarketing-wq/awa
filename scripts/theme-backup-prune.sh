#!/usr/bin/env bash
# theme-backup-prune.sh — remove instaladores ZIP/PBIX já extraídos no projeto
# Mantém apenas patches pequenos. Sobrescreva AWA_THEME_KEEP=all para não apagar nada.
set -euo pipefail

ROOT="/home/jessessh/htdocs/srv1113343.hstgr.cloud"
DIR="${AWA_THEME_ARCHIVE:-$ROOT/_backups/theme-vendor-zips}"

[[ "${AWA_THEME_KEEP:-}" == "all" ]] && echo "AWA_THEME_KEEP=all — pulando" && exit 0
[[ ! -d "$DIR" ]] && exit 0

echo "=== Theme backup prune ==="
BEFORE=$(du -sh "$DIR" 2>/dev/null | awk '{print $1}')

for f in \
  "ayo.zip" \
  "base_package_2.3.x.zip" \
  "awa_v3 (1) (1).pbix" \
  "MegaMenuProforMagento2-2.3.0-CE.zip"
do
  if [[ -f "$DIR/$f" ]]; then
    rm -f "$DIR/$f" && echo "  removido: $f"
  fi
done

AFTER=$(du -sh "$DIR" 2>/dev/null | awk '{print $1}')
echo "  $DIR: ${BEFORE} -> ${AFTER}"
echo "Feito."
