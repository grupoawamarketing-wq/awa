#!/bin/bash
# check_nginx_errors.sh — Analisa erros 4xx/5xx do nginx access.log AWA
# Uso: bash scripts/check_nginx_errors.sh

LOG="/home/awamotos/logs/nginx/access.log"

if ! sudo test -f "$LOG"; then
    echo "Log não encontrado: $LOG"
    exit 1
fi

echo "=== Erros nginx (acumulado do log atual) ==="
echo ""

echo "--- Status codes 4xx/5xx mais frequentes ---"
sudo awk '$9 ~ /^[45][0-9][0-9]$/ {count[$9]++} END {for(s in count) print count[s], s}' "$LOG" | sort -rn | head -10

echo ""
echo "--- URLs com 5xx (top 20) ---"
sudo awk '$9 ~ /^5[0-9][0-9]$/ {print $9, $7}' "$LOG" | sort | uniq -c | sort -rn | head -20

echo ""
echo "--- URLs com 403 (top 10) ---"
sudo awk '$9 == "403" {print $7}' "$LOG" | sort | uniq -c | sort -rn | head -10

echo ""
echo "--- URLs com 404 (top 10, sem assets estáticos) ---"
sudo awk '$9 == "404" && $7 !~ /\.(ico|png|jpg|gif|svg|js|css|woff|woff2|ttf|map)($|\?)/' "$LOG" \
    | awk '{print $7}' | sed 's/?.*$//' | sort | uniq -c | sort -rn | head -10

echo ""
echo "--- Total de requests hoje ($(date +"%d/%b/%Y")) ---"
sudo grep -c "$(date +"%d/%b/%Y")" "$LOG" 2>/dev/null || echo "0"

echo ""
LINES=$(sudo wc -l < "$LOG" 2>/dev/null || echo "?")
SIZE=$(sudo du -sh "$LOG" 2>/dev/null | cut -f1 || echo "?")
echo "Log: $LOG ($LINES linhas, $SIZE)"
