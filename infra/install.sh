#!/usr/bin/env bash
# AWA Motos — Instalação da Stack WhatsApp Commerce
# Uso: sudo bash infra/install.sh
set -euo pipefail

INFRA_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
NGINX_AVAILABLE="/etc/nginx/sites-available"
NGINX_ENABLED="/etc/nginx/sites-enabled"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
info()    { echo -e "${BLUE}[INFO]${NC}  $1"; }
success() { echo -e "${GREEN}[OK]${NC}    $1"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $1"; }
error()   { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

[[ $EUID -ne 0 ]] && error "Execute como root: sudo bash infra/install.sh"
command -v docker >/dev/null 2>&1 || error "Docker não encontrado."
command -v certbot >/dev/null 2>&1 || { warn "Instalando certbot..."; apt-get install -y certbot python3-certbot-nginx; }

for svc in evolution n8n typebot; do
  [[ ! -f "$INFRA_DIR/$svc/.env" ]] && error "Crie $INFRA_DIR/$svc/.env a partir do .env.example"
  grep -q "TROQUE_POR" "$INFRA_DIR/$svc/.env" && error "infra/$svc/.env tem valores TROQUE_POR_* por preencher"
done

info "Criando rede Docker awa-infra..."
docker network create awa-infra 2>/dev/null || success "Rede awa-infra já existe."

for svc in evolution n8n typebot; do
  info "Iniciando $svc..."
  cd "$INFRA_DIR/$svc"
  docker compose pull --quiet
  docker compose up -d
  success "$svc iniciado."
done

info "Aguardando Evolution API..."
for i in {1..30}; do
  curl -sf http://127.0.0.1:8080 >/dev/null 2>&1 && break || sleep 2
done

info "Configurando Nginx temporário para emissão de SSL..."
for conf in wpp.awamotos.com n8n.awamotos.com bot-builder.awamotos.com bot.awamotos.com; do
  cat > "$NGINX_AVAILABLE/$conf.conf" <<NGINXEOF
server {
  listen 80;
  server_name $conf;
  location /.well-known/acme-challenge/ { root /var/www/html; }
  location / { return 301 https://\$host\$request_uri; }
}
NGINXEOF
  ln -sf "$NGINX_AVAILABLE/$conf.conf" "$NGINX_ENABLED/$conf.conf"
done
nginx -t && systemctl reload nginx

info "Emitindo certificados SSL..."
certbot --nginx \
  -d wpp.awamotos.com \
  -d n8n.awamotos.com \
  -d bot-builder.awamotos.com \
  -d bot.awamotos.com \
  --non-interactive --agree-tos \
  --email "jess@awamotos.com" \
  --redirect

info "Instalando configs Nginx definitivas..."
for conf in wpp.awamotos.com n8n.awamotos.com bot-builder.awamotos.com bot.awamotos.com; do
  cp "$INFRA_DIR/nginx/$conf.conf" "$NGINX_AVAILABLE/$conf.conf"
done
nginx -t && systemctl reload nginx

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Instalação concluída!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "  Evolution API  → https://wpp.awamotos.com"
echo "  N8N            → https://n8n.awamotos.com"
echo "  Typebot Viewer → https://bot.awamotos.com"
echo "  Typebot Builder→ https://bot-builder.awamotos.com"
echo ""
echo "  Próximos passos:"
echo "  1. Acesse https://wpp.awamotos.com — escaneie o QR Code da instância"
echo "  2. No Magento Admin: Stores > Smart Suggestions > WhatsApp"
echo "     Provider = evolution, URL = https://wpp.awamotos.com"
echo "  3. Configure o inbox WhatsApp no Chatwoot apontando para wpp.awamotos.com"
echo ""
