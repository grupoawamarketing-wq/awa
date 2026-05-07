#!/bin/bash
# Monitora acessos 403 e 404 nos logs do nginx (TICKET-003)

LOG_FILE=${1:-/var/log/nginx/access.log}

if [ ! -r "$LOG_FILE" ]; then
    echo "Erro: Não é possível ler o arquivo de log $LOG_FILE"
    echo "Uso: $0 [caminho/para/access.log]"
    exit 1
fi

echo "Iniciando monitoramento de erros 403 e 404 no nginx..."
echo "Lendo: $LOG_FILE"
echo "------------------------------------------------------"

# Tail contínuo usando awk para filtrar códigos 403/404 e extrair a URL
tail -n 100 -f "$LOG_FILE" | awk '
  $9 ~ /^(403|404)$/ {
      printf "[%s] %s | ALERTA: Código %s | URL: %s\n", $4, $1, $9, $7;
  }
'
