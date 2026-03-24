#!/usr/bin/env bash
# =============================================================================
# convert-webp.sh — Converter imagens JPG/PNG para WebP no catálogo Magento
# =============================================================================
# Usa cwebp (libwebp) para converter imagens que ainda não têm versão WebP.
# O Nginx já está configurado com content negotiation ($webp_uri map).
#
# Uso:
#   bash scripts/convert-webp.sh              # Converter todas faltantes
#   bash scripts/convert-webp.sh --dry-run    # Apenas listar o que faria
#   bash scripts/convert-webp.sh --quality 80 # Qualidade customizada (padrão: 82)
#
# Requer: cwebp (apt install webp)
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
MEDIA_DIR="$PROJECT_ROOT/pub/media/catalog/product"

# Parâmetros padrão
QUALITY=82
DRY_RUN=false
PARALLEL=4
MIN_SIZE=2048  # Ignorar imagens menores que 2KB (thumbs / placeholders)

# Parse args
while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run) DRY_RUN=true; shift ;;
        --quality) QUALITY="$2"; shift 2 ;;
        --parallel) PARALLEL="$2"; shift 2 ;;
        *) echo "Uso: $0 [--dry-run] [--quality N] [--parallel N]"; exit 1 ;;
    esac
done

if ! command -v cwebp &>/dev/null; then
    echo "ERRO: cwebp não encontrado. Instale com: sudo apt install webp"
    exit 1
fi

echo "=== AWA Motos — Conversão WebP ==="
echo "Diretório: $MEDIA_DIR"
echo "Qualidade: $QUALITY"
echo "Paralelo: $PARALLEL processos"
echo "Dry run: $DRY_RUN"
echo ""

# Contar totais
total=0

convert_to_webp() {
    local src="$1"
    local dst="${src%.*}.webp"
    local src_size
    src_size=$(stat -c%s "$src" 2>/dev/null || echo 0)

    # Ignorar minúsculas
    if [[ "$src_size" -lt "$MIN_SIZE" ]]; then
        return 1
    fi

    # Já existe WebP
    if [[ -f "$dst" ]]; then
        return 2
    fi

    if [[ "$DRY_RUN" == true ]]; then
        echo "  [DRY] $src → $dst"
        return 0
    fi

    if cwebp -q "$QUALITY" -m 6 -mt "$src" -o "$dst" -quiet 2>/dev/null; then
        local dst_size
        dst_size=$(stat -c%s "$dst" 2>/dev/null || echo 0)
        local pct=$((dst_size * 100 / src_size))
        local saved_bytes=$((src_size - dst_size))
        echo "  ✓ $(basename "$src"): ${src_size}B → ${dst_size}B (${pct}%) saved ${saved_bytes}B"
        echo "$saved_bytes"
        return 0
    else
        echo "  ✗ ERRO: $(basename "$src")"
        return 3
    fi
}

echo "[1/2] Escaneando imagens..."

# Criar lista temporária
TMPFILE=$(mktemp)
find "$MEDIA_DIR" \( -name '*.jpg' -o -name '*.jpeg' -o -name '*.png' \) -size +"${MIN_SIZE}c" > "$TMPFILE"
total=$(wc -l < "$TMPFILE")
echo "  Encontradas: $total imagens (acima de ${MIN_SIZE}B)"

# Filtrar apenas as que não têm WebP
CONVERT_LIST=$(mktemp)
while IFS= read -r f; do
    webp="${f%.*}.webp"
    if [[ ! -f "$webp" ]]; then
        echo "$f" >> "$CONVERT_LIST"
    fi
done < "$TMPFILE"
rm -f "$TMPFILE"

to_convert=$(wc -l < "$CONVERT_LIST")
already=$((total - to_convert))
echo "  Já convertidas: $already"
echo "  Para converter: $to_convert"
echo ""

if [[ "$to_convert" -eq 0 ]]; then
    echo "Nada para converter!"
    rm -f "$CONVERT_LIST"
    exit 0
fi

echo "[2/2] Convertendo..."
counter=0
total_saved=0

while IFS= read -r f; do
    counter=$((counter + 1))
    result=$(convert_to_webp "$f" 2>&1) || true
    if [[ "$result" =~ ^[0-9]+$ ]]; then
        total_saved=$((total_saved + result))
    fi
    # Progresso a cada 100
    if [[ $((counter % 100)) -eq 0 ]]; then
        echo "  ... $counter / $to_convert processadas"
    fi
done < "$CONVERT_LIST"

rm -f "$CONVERT_LIST"

echo ""
echo "=== Conversão concluída ==="
echo "Processadas: $counter"
echo "Espaço economizado: $((total_saved / 1024 / 1024))MB"
echo ""
echo "O Nginx já serve WebP automaticamente via content negotiation."
echo "Não é necessário limpar cache do Magento."
