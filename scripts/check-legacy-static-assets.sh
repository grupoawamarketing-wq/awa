#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="${ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
BASE_URL="${BASE_URL:-https://awamotos.com}"
BASE_URL="${BASE_URL%/}"

current_version=""
if [[ -f "$ROOT_DIR/pub/static/deployed_version.txt" ]]; then
    current_version="$(tr -d '[:space:]' < "$ROOT_DIR/pub/static/deployed_version.txt")"
fi

default_versions="1782344458"
if [[ -n "$current_version" ]]; then
    default_versions="$default_versions $current_version"
fi

LEGACY_STATIC_VERSIONS="${LEGACY_STATIC_VERSIONS:-$default_versions}"
THEME_PATH="frontend/AWA_Custom/ayo_home5_child/pt_BR/css"

legacy_files=(
    "awa-plp-shell-final.css"
    "awa-pdp-shell-final.css"
    "awa-cart-shell-final.css"
    "awa-impeccable-layout-2026-06-16.orig.css"
    "awa-home-shell-final.min.css"
    "awa-social-proof.css"
    "awa-bundle-site.css"
    "email-fonts.min.css"
)

curl_args=(--silent --show-error --location --output /dev/null --write-out '%{http_code} %{content_type} %{size_download} %{time_total}')
if [[ "${CURL_INSECURE:-0}" == "1" ]]; then
    curl_args=(-k "${curl_args[@]}")
fi

failures=0

check_url() {
    local label="$1"
    local url="$2"
    local result code content_type size elapsed

    result="$(curl "${curl_args[@]}" "$url" || true)"
    read -r code content_type size elapsed <<< "$result"

    if [[ "$code" != "200" || "$content_type" != text/css* ]]; then
        printf 'FAIL %-42s code=%s type=%s size=%s time=%ss url=%s
' "$label" "$code" "$content_type" "$size" "$elapsed" "$url"
        failures=$((failures + 1))
        return
    fi

    printf 'OK   %-42s code=%s type=%s size=%s time=%ss
' "$label" "$code" "$content_type" "$size" "$elapsed"
}

printf 'Legacy static CSS smoke check
'
printf 'BASE_URL=%s
' "$BASE_URL"
printf 'VERSIONS=%s

' "$LEGACY_STATIC_VERSIONS"

for version in $LEGACY_STATIC_VERSIONS; do
    for file in "${legacy_files[@]}"; do
        check_url "version${version}/${file}" "$BASE_URL/static/version${version}/${THEME_PATH}/${file}"
    done
done

printf '
'
for file in "${legacy_files[@]}"; do
    check_url "direct/${file}" "$BASE_URL/static/${THEME_PATH}/${file}"
done

if (( failures > 0 )); then
    printf '
Legacy static CSS smoke check failed: %d issue(s).
' "$failures" >&2
    exit 1
fi

printf '
Legacy static CSS smoke check passed.
'
