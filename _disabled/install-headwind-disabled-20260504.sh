#!/usr/bin/env bash
# =============================================================================
# AWA Motos - Instalacao Headwind MDM Community (monitoramento.awamotos.com)
# =============================================================================
# Instala: PostgreSQL (container) + Headwind MDM + Nginx + SSL
#
# Uso:
#   cp infra/headwind/.env.example infra/headwind/.env
#   vim infra/headwind/.env
#   chmod +x infra/install-headwind.sh
#   sudo bash infra/install-headwind.sh
# =============================================================================

set -euo pipefail

INFRA_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HEADWIND_DIR="$INFRA_DIR/headwind"
ENV_FILE="$HEADWIND_DIR/.env"

DOMAIN="monitoramento.awamotos.com"
CONF_FILE="$DOMAIN.conf"
NGINX_AVAILABLE="/etc/nginx/sites-available"
NGINX_ENABLED="/etc/nginx/sites-enabled"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
info()    { echo -e "${BLUE}[INFO]${NC}  $1"; }
success() { echo -e "${GREEN}[OK]${NC}    $1"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $1"; }
error()   { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

[[ $EUID -ne 0 ]] && error "Execute como root: sudo bash infra/install-headwind.sh"
command -v docker  >/dev/null 2>&1 || error "Docker nao encontrado."
docker compose version >/dev/null 2>&1 || error "Docker Compose plugin nao encontrado (docker compose)."
command -v nginx   >/dev/null 2>&1 || error "Nginx nao encontrado."
command -v certbot >/dev/null 2>&1 || { warn "Certbot nao encontrado - instalando..."; apt-get update && apt-get install -y certbot python3-certbot-nginx; }

[[ -f "$ENV_FILE" ]] || error "Arquivo $ENV_FILE nao encontrado. Copie de .env.example."

set -a
source "$ENV_FILE"
set +a

for var in ADMIN_EMAIL BASE_DOMAIN SQL_BASE SQL_USER SQL_PASS SHARED_SECRET; do
  [[ -n "${!var:-}" ]] || error "Variavel obrigatoria ausente no .env: $var"
done

[[ "$BASE_DOMAIN" == "$DOMAIN" ]] || error "BASE_DOMAIN do .env deve ser $DOMAIN"

info "Subindo banco PostgreSQL do Headwind..."
cd "$HEADWIND_DIR"
docker compose pull --quiet
docker compose up -d hmdm-db
success "PostgreSQL iniciado."

info "Aplicando config temporaria do Nginx para desafio ACME..."
cat > "$NGINX_AVAILABLE/$CONF_FILE" <<EOF
server {
  listen 80;
  server_name $DOMAIN;
  location /.well-known/acme-challenge/ { root /var/www/html; }
  location / { return 301 https://\$host\$request_uri; }
}
EOF
ln -sf "$NGINX_AVAILABLE/$CONF_FILE" "$NGINX_ENABLED/$CONF_FILE"
nginx -t && systemctl reload nginx
success "Nginx temporario aplicado."

info "Emitindo/renovando certificado SSL para $DOMAIN..."
certbot --nginx \
  -d "$DOMAIN" \
  --non-interactive \
  --agree-tos \
  --email "$ADMIN_EMAIL" \
  --redirect
success "SSL configurado."

info "Aplicando config definitiva do Nginx para Headwind..."
cp "$INFRA_DIR/nginx/$CONF_FILE" "$NGINX_AVAILABLE/$CONF_FILE"
ln -sf "$NGINX_AVAILABLE/$CONF_FILE" "$NGINX_ENABLED/$CONF_FILE"
nginx -t && systemctl reload nginx
success "Nginx definitivo aplicado."

info "Subindo Headwind MDM..."
cd "$HEADWIND_DIR"
docker compose up -d

info "Aguardando Headwind MDM responder em https://127.0.0.1:8081 ..."
READY=0
for i in {1..90}; do
  if curl -kfsS https://127.0.0.1:8081 >/dev/null 2>&1; then
    READY=1
    break
  fi
  sleep 2
done

if [[ $READY -ne 1 ]]; then
  warn "Headwind ainda nao respondeu. Verifique logs: docker logs awa-hmdm -f"
else
  success "Headwind respondendo localmente."
fi

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Headwind MDM instalado${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "  Painel web: https://$DOMAIN"
echo "  Login inicial: admin / admin"
echo ""
echo "  Importante:"
echo "  - Troque a senha do admin no primeiro acesso."
echo "  - Libere a porta 31000/TCP no firewall para provisionamento."
echo "  - Confirme o DNS A de $DOMAIN apontando para este servidor."
echo ""
echo "  Logs:"
echo "    docker logs awa-hmdm -f"
echo "    docker logs awa-hmdm-db -f"
