# WhatsApp Commerce AWA Motos — Estado Atual

> **Atualizado:** 2026-05-23  
> **Stack ativa:** Evolution API + Typebot + módulo Magento `GrupoAwamotos_WhatsAppCommerce`

---

## Removido (2026-05-23)

| Ferramenta | Status |
|------------|--------|
| **Chatwoot** | Módulo Magento, widget, rotas `/chatwoot/*`, infra Docker e nginx removidos |
| **N8N** | Webhooks Magento, cron SocialPostPublisher, nginx `n8n.awamotos.com`, flags Evolution removidos |

Documentação histórica anterior a esta data não reflete o ambiente atual.

---

## Stack em produção

| Serviço | URL | Função |
|---------|-----|--------|
| Evolution API | `https://wpp.awamotos.com` | Gateway WhatsApp (envio/recebimento) |
| Typebot Viewer | `https://bot.awamotos.com` | Fluxos conversacionais publicados |
| Typebot Builder | `https://bot-builder.awamotos.com` | Editor de fluxos |
| Magento REST | `https://awamotos.com/rest/V1/awa-whatsapp/*` | Catálogo, opt-in, atendentes, health check |

### Magento — `GrupoAwamotos_WhatsAppCommerce`

- Notificações de pedido via **MessageSender** (Evolution API), sem webhook externo
- REST API para Typebot: catálogo, opt-in/opt-out, atendentes, health check
- Crons ativos: B2B alerts, health check, review request, meta description, retargeting
- Admin: Stores > Configuration > WhatsApp Commerce

### Infra

```bash
# Instalação/atualização (Evolution + Typebot apenas)
sudo bash infra/install.sh

# Evolution compose: sem CHATWOOT_ENABLED / N8N_ENABLED
infra/evolution/docker-compose.yaml
```

---

## Instalação rápida

1. Configurar `infra/evolution/.env` e `infra/typebot/.env` a partir dos `.env.example`
2. `sudo bash infra/install.sh`
3. Escanear QR Code em `https://wpp.awamotos.com`
4. Magento Admin: habilitar WhatsApp Commerce, URL Evolution = `https://wpp.awamotos.com`

---

## Validação

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://awamotos.com/
curl -s -o /dev/null -w "%{http_code}\n" https://awamotos.com/chatwoot/webhook/receive  # esperado: 404
curl -s https://awamotos.com/rest/V1/awa-whatsapp/health
```

---

## Referências

- Módulo: `app/code/GrupoAwamotos/WhatsAppCommerce/`
- Nginx: `infra/nginx/wpp.awamotos.com.conf`, `bot.awamotos.com.conf`, `bot-builder.awamotos.com.conf`
- Remoções documentadas em `docs/AWA-IMPLEMENTACAO-UX-B2B.md` (seção Chatwoot/N8N)
