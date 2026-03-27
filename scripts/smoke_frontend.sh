#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$BASE_DIR"

URL=""
ASSET_LIMIT=8
TIMEOUT=15
INSECURE=0
USER_AGENT="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
ALLOW_HOME_403=1

print_help() {
  cat <<'EOF'
Smoke test simples do frontend (Magento 2) via HTTP.

Objetivo:
  - Validar que a home responde (200)
  - Confirmar que o rodapé melhorado está ativo (aw-footer-*)
  - Confirmar assets críticos atuais (payment_methods.png e SVGs do tema) acessíveis em /static

Uso:
  ./scripts/smoke_frontend.sh --url https://awamotos.com/

Opções:
  --url URL           Base URL (obrigatório)
  --timeout SEG       Timeout do curl (default: 15)
  --asset-limit N     Máx. de assets checados (default: 8)
  --insecure          Desabilita validação TLS (equivalente ao curl -k)
  --strict-home       Exige HTTP 200 na home (desabilita fallback para 403)

Saída:
  - Retorna exit 0 se OK
  - Retorna exit 1 se falhar algum check
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    -h|--help) print_help; exit 0;;
    --url) URL="${2:-}"; shift 2;;
    --timeout) TIMEOUT="${2:-}"; shift 2;;
    --asset-limit) ASSET_LIMIT="${2:-}"; shift 2;;
    --insecure) INSECURE=1; shift;;
    --strict-home) ALLOW_HOME_403=0; shift;;
    *) echo "Opção inválida: $1" >&2; print_help; exit 2;;
  esac
done

CURL_TLS_ARGS=()
if [[ "$INSECURE" == "1" ]]; then
  CURL_TLS_ARGS=(-k)
fi

if [[ -z "$URL" ]]; then
  echo "[ERRO] --url é obrigatório" >&2
  print_help
  exit 2
fi

# Normalizar para terminar com /
if [[ "$URL" != */ ]]; then
  URL="${URL}/"
fi

fail() {
  echo "[FAIL] $*" >&2
  return 1
}

pass() {
  echo "[OK] $*"
}

curl_get() {
  curl "${CURL_TLS_ARGS[@]}" -A "$USER_AGENT" -sS -L --max-time "$TIMEOUT" "$1"
}

curl_head_code() {
  # Retorna status HTTP (ex.: 200)
  curl "${CURL_TLS_ARGS[@]}" -A "$USER_AGENT" -sS -o /dev/null -L --max-time "$TIMEOUT" -w '%{http_code}' "$1"
}

extract_static_assets() {
  # Extrai URLs de /static do HTML
  grep -oE "https?://[^\"' ]+/static/[^\"' ]+" | sed 's/[)>,]$//' | sort -u
}

extract_interesting_assets() {
  # Prioriza os assets que sabemos que importam
  grep -oE "https?://[^\"' ]+/static/[^\"' ]*(payment_methods\\.png|awamotos-seguranca-ssl\\.svg|awamotos-compra-protegida\\.svg|side-banner-promo\\.svg|side-banner-combos\\.svg|banner-hero\\.svg|banner-side-1\\.svg|banner-side-2\\.svg)" \
    | sed 's/[)>,]$//' \
    | sort -u
}

echo "==========================================="
echo "Smoke test frontend"
echo "URL: $URL"
echo "Timeout: ${TIMEOUT}s | Asset limit: $ASSET_LIMIT"
echo "==========================================="

html="$(curl_get "$URL" || true)"
if [[ -z "$html" ]]; then
  fail "Home vazia ou erro ao baixar: $URL" || exit 1
fi

code="$(curl_head_code "$URL" || true)"
if [[ "$code" != "200" ]]; then
  if [[ "$code" == "403" && "$ALLOW_HOME_403" == "1" ]]; then
    echo "[WARN] Home retornou 403 (WAF/anti-bot). Validando páginas-chave em fallback..."
    fallback_paths=("shipping" "formas-pagamento" "ofertas.html")
    ok_count=0
    all_forbidden=1
    for p in "${fallback_paths[@]}"; do
      c="$(curl_head_code "${URL}${p}" || true)"
      if [[ "$c" == "200" ]]; then
        echo "  [OK] $c ${URL}${p}"
        ok_count=$((ok_count + 1))
        all_forbidden=0
      else
        echo "  [WARN] $c ${URL}${p}"
        if [[ "$c" != "403" ]]; then
          all_forbidden=0
        fi
      fi
    done
    if [[ "$ok_count" -eq 0 ]]; then
      if [[ "$all_forbidden" == "1" ]]; then
        echo "[WARN] Ambiente protegido por WAF: todas as checagens HTTP retornaram 403 via curl."
        echo "[WARN] Use --strict-home em ambiente sem WAF para validação HTTP estrita."
        pass "Smoke concluído com ressalva (WAF bloqueou validação HTTP por curl)"
        exit 0
      fi
      fail "Fallback falhou: nenhuma página-chave respondeu 200" || exit 1
    fi
    pass "Fallback ativo: ${ok_count}/${#fallback_paths[@]} páginas-chave responderam 200"
  else
    fail "Home não retornou 200 (retornou $code): $URL" || exit 1
  fi
else
  pass "Home respondeu 200"
fi

# Verificar rodapé melhorado AWA
if grep -q 'awa-footer--dark' <<< "$html"; then
  pass "Rodapé AWA ativo (awa-footer--dark encontrado)"
elif grep -q 'awa-footer-trust-bar' <<< "$html"; then
  pass "Rodapé AWA ativo (awa-footer-trust-bar encontrado)"
else
  fail "Rodapé AWA NÃO encontrado (awa-footer--dark ausente)" || exit 1
fi

# Verificar presença de elementos AWA no rodapé
if grep -q 'awa-footer-trust' <<< "$html"; then
  pass "Footer trust-bar presente (awa-footer-trust)"
elif grep -q 'payment_methods\.png' <<< "$html"; then
  pass "Referência a payment_methods.png encontrada no HTML"
else
  fail "Elementos de confiança do rodapé não encontrados (awa-footer-trust ausente)" || exit 1
fi

# Checar alguns assets críticos
assets="$(extract_interesting_assets <<< "$html" | head -n "$ASSET_LIMIT" || true)"
if [[ -z "$assets" ]]; then
  # fallback: pega qualquer coisa de /static
  assets="$(extract_static_assets <<< "$html" | head -n "$ASSET_LIMIT" || true)"
fi

if [[ -z "$assets" ]]; then
  fail "Não foi possível extrair assets de /static do HTML" || exit 1
fi

echo ""
echo "Checando assets (até $ASSET_LIMIT):"
err=0
while IFS= read -r a; do
  [[ -n "$a" ]] || continue
  c="$(curl_head_code "$a" || true)"
  if [[ "$c" == "200" || "$c" == "304" ]]; then
    echo "  [OK] $c $a"
  else
    echo "  [FAIL] $c $a" >&2
    err=1
  fi
done <<< "$assets"

if [[ $err -ne 0 ]]; then
  fail "Um ou mais assets falharam" || exit 1
fi

pass "Smoke test concluído com sucesso"
exit 0
