#!/usr/bin/env bash
# =============================================================================
# Instalacao manual Headwind MDM Community em Ubuntu 18.04/20.04
# Baseado no repositorio oficial: https://github.com/h-mdm/hmdm-server
# =============================================================================
# Uso:
#   chmod +x infra/headwind/install-manual-ubuntu.sh
#   sudo bash infra/headwind/install-manual-ubuntu.sh
#
# Variaveis opcionais:
#   SQL_BASE=hmdm SQL_USER=hmdm SQL_PASS=SenhaForte HMDM_DIR=/opt/hmdm-server \
#   sudo bash infra/headwind/install-manual-ubuntu.sh
# =============================================================================

set -euo pipefail

SQL_BASE="${SQL_BASE:-hmdm}"
SQL_USER="${SQL_USER:-hmdm}"
SQL_PASS="${SQL_PASS:-topsecret}"
HMDM_DIR="${HMDM_DIR:-/opt/hmdm-server}"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
info()    { echo -e "${BLUE}[INFO]${NC}  $1"; }
success() { echo -e "${GREEN}[OK]${NC}    $1"; }
error()   { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

[[ $EUID -ne 0 ]] && error "Execute como root: sudo bash infra/headwind/install-manual-ubuntu.sh"
command -v apt-get >/dev/null 2>&1 || error "Este script foi feito para Ubuntu/Debian (apt-get)."

if apt-cache show tomcat9 >/dev/null 2>&1; then
  TOMCAT_PACKAGE="tomcat9"
else
  TOMCAT_PACKAGE="tomcat8"
fi

info "Instalando dependencias: git, aapt, $TOMCAT_PACKAGE, postgresql, maven..."
apt-get update
apt-get install -y git aapt "$TOMCAT_PACKAGE" postgresql maven

info "Garantindo servicos ativos..."
systemctl enable --now postgresql "$TOMCAT_PACKAGE"
curl -fsS http://localhost:8080 >/dev/null 2>&1 || error "Tomcat nao respondeu em localhost:8080"

if [[ -d "$HMDM_DIR/.git" ]]; then
  info "Atualizando repositorio hmdm-server em $HMDM_DIR ..."
  git -C "$HMDM_DIR" fetch --all --prune
  git -C "$HMDM_DIR" checkout master
  git -C "$HMDM_DIR" pull --ff-only
else
  info "Clonando repositorio oficial hmdm-server..."
  git clone https://github.com/h-mdm/hmdm-server.git "$HMDM_DIR"
fi

cd "$HMDM_DIR"

info "Compilando hmdm-server (mvn install)..."
mvn install

info "Criando usuario e banco PostgreSQL (idempotente)..."
ROLE_EXISTS="$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${SQL_USER}'")"
if [[ "$ROLE_EXISTS" != "1" ]]; then
  sudo -u postgres psql -c "CREATE USER \"${SQL_USER}\" WITH PASSWORD '${SQL_PASS}';"
fi

DB_EXISTS="$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='${SQL_BASE}'")"
if [[ "$DB_EXISTS" != "1" ]]; then
  sudo -u postgres psql -c "CREATE DATABASE \"${SQL_BASE}\" WITH OWNER=\"${SQL_USER}\";"
fi

success "Dependencias e base preparadas."

chmod +x hmdm_install.sh
info "Executando instalador oficial (interativo): ./hmdm_install.sh"
info "Quando solicitado, informe SQL_HOST=localhost SQL_PORT=5432 SQL_BASE=${SQL_BASE} SQL_USER=${SQL_USER}"
./hmdm_install.sh
