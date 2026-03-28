#!/usr/bin/env bash
# =============================================================================
# AWA Motos — Batch WebP Conversion (T2.2)
# Converte imagens JPG/PNG de produto para WebP usando cwebp.
#
# Uso:
#   bash scripts/convert-to-webp.sh [OPTIONS]
#
# Opções:
#   --quality=N     Qualidade WebP (1-100, padrão: 82)
#   --dry-run       Mostra o que seria feito sem converter
#   --force         Re-converte arquivos que já têm .webp par
#   --limit=N       Processa no máximo N imagens (útil para testes)
#   --dir=PATH      Diretório de imagens (padrão: pub/media/catalog/product)
#
# Exemplos:
#   bash scripts/convert-to-webp.sh --dry-run
#   bash scripts/convert-to-webp.sh --quality=80 --limit=100
#   bash scripts/convert-to-webp.sh --force
#
# Pré-requisitos:
#   - cwebp instalado (sudo apt install webp)
#   - Espaço suficiente em disco (~30-50% do tamanho das imagens originais)
#
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# === Defaults ===
QUALITY=82
DRY_RUN=false
FORCE=false
LIMIT=0
MEDIA_DIR="$ROOT_DIR/pub/media/catalog/product"
LOG_FILE="$ROOT_DIR/var/log/webp-conversion.log"

# === Colors ===
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
GRAY='\033[0;37m'
NC='\033[0m'

# === Parse flags ===
for arg in "$@"; do
    case "$arg" in
    --quality=*)   QUALITY="${arg#*=}" ;;
    --dry-run)     DRY_RUN=true ;;
    --force)       FORCE=true ;;
    --limit=*)     LIMIT="${arg#*=}" ;;
    --dir=*)       MEDIA_DIR="${arg#*=}" ;;
    --help|-h)
        sed -n '/^# =/,/^# =/p' "$0" | head -30
        exit 0
        ;;
    *)
        echo -e "${RED}Opção desconhecida: $arg${NC}" >&2
        exit 1
        ;;
    esac
done

# === Validações ===
if ! command -v cwebp &>/dev/null; then
    echo -e "${RED}❌ cwebp não está instalado. Execute: sudo apt install webp${NC}" >&2
    exit 1
fi

if [ ! -d "$MEDIA_DIR" ]; then
    echo -e "${RED}❌ Diretório não encontrado: $MEDIA_DIR${NC}" >&2
    exit 1
fi

mkdir -p "$(dirname "$LOG_FILE")"

# === Banner ===
echo ""
echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}  AWA Motos — Conversão WebP Batch (T2.2)${NC}"
echo -e "${BLUE}================================================${NC}"
echo -e "  Diretório : $MEDIA_DIR"
echo -e "  Qualidade : $QUALITY"
echo -e "  Dry run   : $DRY_RUN"
echo -e "  Forçar    : $FORCE"
echo -e "  Limite    : ${LIMIT:-ilimitado}"
echo -e "  Log       : $LOG_FILE"
echo ""

# === Encontra imagens (exclui cache) ===
FIND_CMD=(find "$MEDIA_DIR" -not -path '*/cache/*' \( -name '*.jpg' -o -name '*.jpeg' -o -name '*.png' \) -type f)
IMAGES=$("${FIND_CMD[@]}" 2>/dev/null | sort)
TOTAL=$(echo "$IMAGES" | grep -c '' || true)

echo -e "  Total de imagens encontradas: ${YELLOW}${TOTAL}${NC}"
echo ""

# === Estatísticas ===
CONVERTED=0
SKIPPED=0
FAILED=0
ERRORS=0
SAVED_BYTES=0
PROCESSED=0

LOG_TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
echo "[$LOG_TIMESTAMP] Iniciando conversão WebP — $TOTAL imagens, quality=$QUALITY" >> "$LOG_FILE"

# === Loop de conversão ===
while IFS= read -r img; do
    [ -z "$img" ] && continue

    # Aplica limite se definido
    if [ "$LIMIT" -gt 0 ] && [ "$PROCESSED" -ge "$LIMIT" ]; then
        echo -e "${GRAY}  [INFO] Limite de $LIMIT imagens atingido.${NC}"
        break
    fi

    WEBP="${img%.*}.webp"
    BASENAME="$(basename "$img")"

    # Pula se já tem .webp e não está em modo force
    if [ -f "$WEBP" ] && [ "$FORCE" = false ]; then
        SKIPPED=$((SKIPPED + 1))
        PROCESSED=$((PROCESSED + 1))
        continue
    fi

    if [ "$DRY_RUN" = true ]; then
        echo -e "  ${GRAY}[DRY RUN]${NC} ${BASENAME} → $(basename "$WEBP")"
        CONVERTED=$((CONVERTED + 1))
        PROCESSED=$((PROCESSED + 1))
        continue
    fi

    # Calcula tamanho original
    ORIG_SIZE=$(stat -c%s "$img" 2>/dev/null || echo "0")

    # Converte com cwebp
    if cwebp -q "$QUALITY" -quiet "$img" -o "$WEBP" 2>>"$LOG_FILE"; then
        WEBP_SIZE=$(stat -c%s "$WEBP" 2>/dev/null || echo "0")
        REDUCTION=$(( ORIG_SIZE > 0 ? (ORIG_SIZE - WEBP_SIZE) * 100 / ORIG_SIZE : 0 ))
        SAVED_BYTES=$((SAVED_BYTES + ORIG_SIZE - WEBP_SIZE))

        echo -e "  ${GREEN}✓${NC} ${BASENAME} → -${REDUCTION}% (${ORIG_SIZE}B → ${WEBP_SIZE}B)"
        echo "[$LOG_TIMESTAMP] OK: $img (-${REDUCTION}%)" >> "$LOG_FILE"
        CONVERTED=$((CONVERTED + 1))
    else
        echo -e "  ${RED}✗${NC} Falha: ${BASENAME}"
        echo "[$LOG_TIMESTAMP] FAIL: $img" >> "$LOG_FILE"
        FAILED=$((FAILED + 1))
        [ -f "$WEBP" ] && rm -f "$WEBP"
    fi

    PROCESSED=$((PROCESSED + 1))
done <<< "$IMAGES"

# === Relatório ===
SAVED_KB=$((SAVED_BYTES / 1024))
SAVED_MB=$((SAVED_KB / 1024))

echo ""
echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}  Relatório de Conversão${NC}"
echo -e "${BLUE}================================================${NC}"
echo -e "  ${GREEN}✓ Convertidas  : $CONVERTED${NC}"
echo -e "  ${GRAY}⊘ Puladas       : $SKIPPED (já têm .webp)${NC}"
echo -e "  ${RED}✗ Falhas        : $FAILED${NC}"
if [ "$DRY_RUN" = false ]; then
    echo -e "  ${YELLOW}💾 Economizados  : ${SAVED_KB}KB (~${SAVED_MB}MB)${NC}"
fi
echo ""

if [ "$DRY_RUN" = false ] && [ "$CONVERTED" -gt 0 ]; then
    echo -e "${GREEN}✅ Conversão concluída!${NC}"
    echo -e "${YELLOW}⚡ Próximo passo: ativar regra nginx WebP (ver scripts/nginx-webp-snippet.conf)${NC}"
fi

LOG_END=$(date '+%Y-%m-%d %H:%M:%S')
echo "[$LOG_END] Concluído: $CONVERTED convertidas, $SKIPPED puladas, $FAILED falhas, ${SAVED_KB}KB economizados" >> "$LOG_FILE"

[ "$FAILED" -gt 0 ] && exit 1
exit 0
