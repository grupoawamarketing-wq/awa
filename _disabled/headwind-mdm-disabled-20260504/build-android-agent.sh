#!/usr/bin/env bash
# =============================================================================
# Build do agente Android Headwind (repositorio oficial hmdm-android)
# =============================================================================
# Uso:
#   export ANDROID_SDK_ROOT=/opt/android-sdk
#   chmod +x infra/headwind/build-android-agent.sh
#   infra/headwind/build-android-agent.sh
#
# Opcional:
#   WORK_DIR=/opt/hmdm-android infra/headwind/build-android-agent.sh
# =============================================================================

set -euo pipefail

REPO_URL="https://github.com/h-mdm/hmdm-android.git"
WORK_DIR="${WORK_DIR:-$HOME/hmdm-android}"
SDK_DIR="${ANDROID_SDK_ROOT:-${ANDROID_HOME:-}}"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
info()    { echo -e "${BLUE}[INFO]${NC}  $1"; }
success() { echo -e "${GREEN}[OK]${NC}    $1"; }
error()   { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

command -v git >/dev/null 2>&1 || error "Git nao encontrado."
command -v java >/dev/null 2>&1 || error "Java nao encontrado."

[[ -n "$SDK_DIR" ]] || error "Defina ANDROID_SDK_ROOT (ou ANDROID_HOME) antes de executar."
[[ -d "$SDK_DIR" ]] || error "Diretorio do SDK nao encontrado: $SDK_DIR"

if [[ -d "$WORK_DIR/.git" ]]; then
  info "Atualizando repositorio existente em $WORK_DIR ..."
  git -C "$WORK_DIR" fetch --all --prune
  git -C "$WORK_DIR" checkout master
  git -C "$WORK_DIR" pull --ff-only
else
  info "Clonando repositorio oficial hmdm-android..."
  git clone "$REPO_URL" "$WORK_DIR"
fi

cd "$WORK_DIR"
printf "sdk.dir=%s\n" "$SDK_DIR" > local.properties

chmod +x gradlew
info "Compilando APK release..."
./gradlew clean assembleRelease

APK_PATH="$WORK_DIR/app/build/outputs/apk/release/app-release.apk"
[[ -f "$APK_PATH" ]] || error "APK nao encontrado em $APK_PATH"

success "APK gerado: $APK_PATH"
