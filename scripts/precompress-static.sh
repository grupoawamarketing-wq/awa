#!/usr/bin/env bash
# =============================================================================
# precompress-static.sh — Pré-comprimir assets estáticos com Brotli + Gzip
# =============================================================================
# Gera arquivos .br (Brotli nível 11) e .gz (Gzip nível 9) para CSS/JS/SVG
# para uso com brotli_static on e gzip_static on no Nginx.
#
# Uso: bash scripts/precompress-static.sh [--pub-only]
#   --pub-only: comprime apenas pub/static (uso pós-deploy em produção)
#
# Requer: brotli (apt install brotli)
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Mínimo de bytes para justificar pré-compressão
MIN_SIZE=1024

# Diretórios fonte
THEME_CSS="$PROJECT_ROOT/app/design/frontend/AWA_Custom/ayo_home5_child/web/css"
THEME_JS="$PROJECT_ROOT/app/design/frontend/AWA_Custom/ayo_home5_child/web/js"
THEME_FONTS="$PROJECT_ROOT/app/design/frontend/AWA_Custom/ayo_home5_child/web/fonts"

# Diretório público
PUB_STATIC="$PROJECT_ROOT/pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR"

# User path (para symlinks do Magento developer mode)
USER_PATH="/home/user/htdocs/srv1113343.hstgr.cloud"

PUB_ONLY=false
[[ "${1:-}" == "--pub-only" ]] && PUB_ONLY=true

compress_file() {
    local f="$1"
    local sz
    sz=$(wc -c < "$f")
    if [[ $sz -lt $MIN_SIZE ]]; then
        return
    fi
    brotli -f -q 11 -o "${f}.br" "$f"
    gzip -f -k -9 "$f"
    local br_sz gz_sz pct
    br_sz=$(wc -c < "${f}.br")
    gz_sz=$(wc -c < "${f}.gz")
    pct=$((br_sz * 100 / sz))
    echo "  $(basename "$f"): ${sz}B → br:${br_sz}B (${pct}%) gz:${gz_sz}B"
}

create_pub_symlinks() {
    local src_dir="$1"
    local pub_dir="$2"
    local user_src_dir="$3"
    local count=0

    [[ -d "$pub_dir" ]] || return

    for ext in br gz; do
        for f in "$src_dir"/*."$ext"; do
            [[ -f "$f" ]] || continue
            local base
            base=$(basename "$f")
            ln -sf "$user_src_dir/$base" "$pub_dir/$base"
            count=$((count + 1))
        done
    done
    echo "  → $count symlinks em $(basename "$pub_dir")/"
}

echo "=== AWA Motos — Pré-compressão Estática ==="
echo ""

if [[ "$PUB_ONLY" == true ]]; then
    echo "[pub/static] Comprimindo CSS/JS em pub/static..."
    find "$PUB_STATIC" \( -name '*.css' -o -name '*.js' \) -size "+${MIN_SIZE}c" -not -name '*.br' -not -name '*.gz' | while read -r f; do
        # Só comprimir se é arquivo real (não symlink) OU se o .br não existe
        if [[ ! -f "${f}.br" ]] || [[ "$f" -nt "${f}.br" ]]; then
            compress_file "$f"
        fi
    done
    echo "Pronto!"
    exit 0
fi

echo "[1/4] Comprimindo CSS bundles..."
for f in "$THEME_CSS"/*.css; do
    [[ -f "$f" ]] || continue
    [[ "$f" == *.unmin.css ]] && continue
    compress_file "$f"
done

echo ""
echo "[2/4] Comprimindo JS..."
for f in "$THEME_JS"/*.js; do
    [[ -f "$f" ]] || continue
    sz=$(wc -c < "$f")
    [[ $sz -lt $MIN_SIZE ]] && continue
    compress_file "$f"
done

echo ""
echo "[3/4] Comprimindo SVG fonts..."
for f in "$THEME_FONTS"/*.svg 2>/dev/null; do
    [[ -f "$f" ]] || continue
    compress_file "$f"
done

echo ""
echo "[4/4] Criando symlinks em pub/static..."
CSS_USER="$USER_PATH/app/design/frontend/AWA_Custom/ayo_home5_child/web/css"
JS_USER="$USER_PATH/app/design/frontend/AWA_Custom/ayo_home5_child/web/js"

create_pub_symlinks "$THEME_CSS" "$PUB_STATIC/css" "$CSS_USER"
create_pub_symlinks "$THEME_JS" "$PUB_STATIC/js" "$JS_USER"

echo ""
echo "=== Pré-compressão concluída ==="
echo "Nginx serve automaticamente via brotli_static on / gzip_static on"
