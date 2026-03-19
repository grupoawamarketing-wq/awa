SHELL := /usr/bin/env bash
.DEFAULT_GOAL := help

LOCALE ?= pt_BR
JOBS ?= 4
BASE_URL ?= https://awamotos.com/
AYO_PDP_PATH ?=
MAGENTO ?= ./bin/magento-www

.PHONY: help
help: ## Mostra esta ajuda
	@echo "Targets disponíveis:" 
	@grep -E '^[a-zA-Z0-9_.-]+:.*## ' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*## "}; {printf "  %-28s %s\n", $$1, $$2}'
	@echo ""
	@echo "Variáveis:" 
	@echo "  LOCALE=$(LOCALE)" 
	@echo "  JOBS=$(JOBS)" 
	@echo "  BASE_URL=$(BASE_URL)" 
	@echo "  AYO_PDP_PATH=$(AYO_PDP_PATH)"

.PHONY: smoke-frontend
smoke-frontend: ## Smoke test HTTP do frontend (BASE_URL)
	@./scripts/smoke_frontend.sh --url "$(BASE_URL)"

.PHONY: smoke-frontend-insecure
smoke-frontend-insecure: ## Smoke test HTTP do frontend (TLS insecure)
	@./scripts/smoke_frontend.sh --url "$(BASE_URL)" --insecure

.PHONY: doctor
doctor: ## Diagnóstico rápido (não destrutivo)
	@./scripts/magento_doctor.sh

.PHONY: store-setup
store-setup: ## Reaplica seed idempotente (CMS/tema/categorias) do GrupoAwamotos
	@$(MAGENTO) grupoawamotos:store:setup

.PHONY: predeploy
predeploy: ## Checagem pré-deploy (não destrutiva)
	@./scripts/predeploy_check.sh

.PHONY: postdeploy
postdeploy: ## Verificação pós-deploy (não destrutiva)
	@./scripts/postdeploy_verify.sh

.PHONY: fix-permissions
fix-permissions: ## Corrige permissões de var/, pub/, generated/
	@./scripts/fix_permissions.sh

.PHONY: deploy
deploy: ## Deploy (sequência sagrada) com LOCALE/JOBS
	@./scripts/deploy_sagrado.sh --locale "$(LOCALE)" --jobs "$(JOBS)"

.PHONY: deploy-prod
deploy-prod: ## Deploy em production (sem manutenção)
	@./scripts/deploy_sagrado.sh --mode production --locale "$(LOCALE)" --jobs "$(JOBS)"

.PHONY: deploy-prod-maint
deploy-prod-maint: ## Deploy em production com maintenance
	@./scripts/deploy_sagrado.sh --mode production --maintenance --locale "$(LOCALE)" --jobs "$(JOBS)"

.PHONY: deploy-prod-maint-clean
deploy-prod-maint-clean: ## Deploy em production + maintenance + limpeza de estáticos (cuidado)
	@./scripts/deploy_sagrado.sh --mode production --maintenance --clean-static --locale "$(LOCALE)" --jobs "$(JOBS)"

.PHONY: cache-status
cache-status: ## Status do cache
	@$(MAGENTO) cache:status

.PHONY: cache-flush
cache-flush: ## Flush do cache
	@$(MAGENTO) cache:flush

.PHONY: frontend
frontend: ## Deploy frontend RÁPIDO (detecta tema ativo automaticamente)
	@./scripts/deploy_frontend.sh

.PHONY: frontend-full
frontend-full: ## Deploy frontend COMPLETO (limpa tudo, DI, todos temas)
	@./scripts/deploy_frontend.sh --full

.PHONY: ayo-child-audit
ayo-child-audit: ## Auditoria de paridade/ativos do child theme Ayo Home5 (read-only)
	@php ./dev/tools/ayo_child_theme_audit.php

.PHONY: ayo-child-theme-status
ayo-child-theme-status: ## Status do tema do store default (Ayo Home5 base vs child)
	@php ./dev/tools/ayo_child_theme_switch.php status --store-code default

.PHONY: ayo-child-theme-activate
ayo-child-theme-activate: ## Ativa o child theme AWA_Custom/ayo_home5_child no store default
	@php ./dev/tools/ayo_child_theme_switch.php activate --store-code default

.PHONY: ayo-child-theme-rollback
ayo-child-theme-rollback: ## Rollback para ayo/ayo_home5 no store default
	@php ./dev/tools/ayo_child_theme_switch.php rollback --store-code default

.PHONY: ayo-child-js-check
ayo-child-js-check: ## Sintaxe JavaScript do child theme Ayo Home5
	@find app/design/frontend/AWA_Custom/ayo_home5_child/web/js -type f -name '*.js' -print0 | xargs -0 -n1 node --check

.PHONY: ayo-child-php-check
ayo-child-php-check: ## Sintaxe PHP/PHTML do child theme e auditorias Ayo
	@find app/design/frontend/AWA_Custom/ayo_home5_child -type f \( -name '*.php' -o -name '*.phtml' \) -print0 | xargs -0 -n1 php -l
	@find dev/tools -maxdepth 1 -type f \( -name 'ayo_child_theme*.php' -o -name 'ayo_home5*.php' \) -print0 | xargs -0 -n1 php -l

.PHONY: ayo-child-layout-check
ayo-child-layout-check: ## Validação de layout XML (temas frontend + módulos app/code)
	@php ./dev/tools/validate_layout_xml.php

.PHONY: ayo-child-template-audit
ayo-child-template-audit: ## Auditoria de anti-patterns em PHTML/PHP do child theme
	@php ./dev/tools/ayo_child_theme_template_audit.php

.PHONY: ayo-child-html-audit
ayo-child-html-audit: ## Auditoria HTML da home (dup duplicidades/legado do parent)
	@php ./dev/tools/ayo_child_theme_html_audit.php --url "$(BASE_URL)"

.PHONY: ayo-child-routes-audit
ayo-child-routes-audit: ## Auditoria HTML de rotas críticas (home/PLP/busca/cart/auth/B2B)
	@php ./dev/tools/ayo_child_theme_routes_audit.php --base-url "$(BASE_URL)" $(if $(AYO_PDP_PATH),--pdp-path "$(AYO_PDP_PATH)",)

.PHONY: ayo-home5-homepage-audit
ayo-home5-homepage-audit: ## Auditoria de alinhamento Home5 (CMS page vs top-home template/blocos)
	@php ./dev/tools/ayo_home5_homepage_alignment_audit.php --store-code default

.PHONY: ayo-home5-css-audit
ayo-home5-css-audit: ## Auditoria de CSS da Home5 (cascata, ordem, 404, duplicidades)
	@php ./dev/tools/ayo_home5_css_cascade_audit.php --url "$(BASE_URL)"

.PHONY: ayo-home5-homepage-cms-fix-dry
ayo-home5-homepage-cms-fix-dry: ## Dry-run: corrige aliases block_id inválidos na CMS page Home5
	@php ./dev/tools/ayo_home5_homepage_cms_blockid_repair.php --store-code default

.PHONY: ayo-home5-homepage-cms-fix-apply
ayo-home5-homepage-cms-fix-apply: ## Apply: corrige aliases block_id inválidos na CMS page Home5 + cache clean
	@php ./dev/tools/ayo_home5_homepage_cms_blockid_repair.php --store-code default --apply
	@php ./bin/magento cache:clean layout block_html full_page

.PHONY: ayo-home5-render-mode-status
ayo-home5-render-mode-status: ## Status do modo Home5 (template-driven vs CMS-driven) no child theme
	@php ./dev/tools/ayo_home5_render_mode_switch.php status

.PHONY: ayo-home5-render-mode-template
ayo-home5-render-mode-template: ## Define Home5 em template-driven (top-home.phtml) + cache clean
	@php ./dev/tools/ayo_home5_render_mode_switch.php template
	@php ./bin/magento cache:clean layout block_html full_page

.PHONY: ayo-home5-render-mode-cms
ayo-home5-render-mode-cms: ## Define Home5 em CMS-driven (restaura cms_page_content) + cache clean
	@php ./dev/tools/ayo_home5_render_mode_switch.php cms
	@php ./bin/magento cache:clean layout block_html full_page

.PHONY: ayo-home5-stage-sync-dry
ayo-home5-stage-sync-dry: ## Dry-run: cria/atualiza CMS page stage Home5 com baseline CMS-driven
	@php ./dev/tools/ayo_home5_stage_page_sync.php --store-code default

.PHONY: ayo-home5-stage-sync-apply
ayo-home5-stage-sync-apply: ## Apply: cria/atualiza CMS page stage Home5 + cache clean
	@php ./dev/tools/ayo_home5_stage_page_sync.php --store-code default --apply
	@php ./bin/magento cache:clean block_html full_page

.PHONY: ayo-home5-demo-stage-sync-dry
ayo-home5-demo-stage-sync-dry: ## Dry-run: cria/atualiza CMS page stage Home5 demo (en_5) em CMS-driven
	@php ./dev/tools/ayo_home5_stage_page_sync.php --store-code default --stage-identifier homepage_ayo_home5_demo_stage --title "Homepage Ayo Home 5 Demo Stage (en_5)" --content-file ./dev/tools/ayo_home5_demo_stage_content.html

.PHONY: ayo-home5-demo-stage-sync-apply
ayo-home5-demo-stage-sync-apply: ## Apply: cria/atualiza CMS page stage Home5 demo (en_5) + cache clean
	@php ./dev/tools/ayo_home5_stage_page_sync.php --store-code default --stage-identifier homepage_ayo_home5_demo_stage --title "Homepage Ayo Home 5 Demo Stage (en_5)" --content-file ./dev/tools/ayo_home5_demo_stage_content.html --apply
	@php ./bin/magento cache:clean layout block_html full_page

.PHONY: ayo-home5-demo-cutover-status
ayo-home5-demo-cutover-status: ## Status do cutover da homepage demo (config + render mode)
	@php ./dev/tools/ayo_home5_homepage_cutover.php status --store-code default --target-homepage homepage_ayo_home5_demo_stage

.PHONY: ayo-home5-demo-cutover-apply
ayo-home5-demo-cutover-apply: ## Cutover da home para homepage_ayo_home5_demo_stage (CMS-driven) + cache + smoke
	@php ./dev/tools/ayo_home5_homepage_cutover.php apply --store-code default --target-homepage homepage_ayo_home5_demo_stage --sync-render-mode --cache-clean
	@$(MAKE) smoke-frontend

.PHONY: ayo-home5-demo-cutover-rollback
ayo-home5-demo-cutover-rollback: ## Rollback do último cutover da home + restore render mode + cache + smoke
	@php ./dev/tools/ayo_home5_homepage_cutover.php rollback --store-code default --sync-render-mode --cache-clean
	@$(MAKE) smoke-frontend

.PHONY: ayo-child-verify
ayo-child-verify: ## Validação rápida do child theme ativo (audit + smoke frontend)
	@$(MAKE) ayo-child-php-check
	@$(MAKE) ayo-child-js-check
	@$(MAKE) ayo-child-layout-check
	@$(MAKE) ayo-child-template-audit
	@$(MAKE) ayo-child-audit
	@$(MAKE) smoke-frontend
	@$(MAKE) ayo-child-html-audit

.PHONY: ayo-child-verify-critical
ayo-child-verify-critical: ## Validação ampliada do child theme (verify + rotas críticas)
	@$(MAKE) ayo-child-verify
	@$(MAKE) ayo-child-routes-audit

.PHONY: indexer-status
indexer-status: ## Status dos indexadores
	@$(MAGENTO) indexer:status

.PHONY: reindex
reindex: ## Reindexar tudo
	@$(MAGENTO) indexer:reindex

.PHONY: mode-show
mode-show: ## Mostrar modo (developer/production)
	@$(MAGENTO) deploy:mode:show

.PHONY: mode-dev
mode-dev: ## Alterar para developer
	@$(MAGENTO) deploy:mode:set developer

.PHONY: mode-prod
mode-prod: ## Alterar para production
	@$(MAGENTO) deploy:mode:set production

.PHONY: logs
logs: ## Tail de logs (system + exception)
	@./scripts/logs_tail.sh

.PHONY: security-audit
security-audit: ## Auditoria rápida de segurança (não destrutiva)
	@./scripts/security_audit.sh

.PHONY: hardening-report
hardening-report: ## Relatório de hardening (não destrutivo)
	@./scripts/hardening_report.sh

.PHONY: hardening
hardening: ## Executa security-audit + hardening-report
	@$(MAKE) security-audit
	@$(MAKE) hardening-report

.PHONY: varnish-vcl
varnish-vcl: ## Gerar VCL do Varnish em var/varnish/magento.vcl
	@./scripts/generate_varnish_vcl.sh

.PHONY: enable-2fa-dry
enable-2fa-dry: ## Mostra o que faria para habilitar 2FA
	@./scripts/enable_2fa.sh

.PHONY: enable-2fa
enable-2fa: ## Habilita 2FA (ATENÇÃO) - requer acesso admin
	@./scripts/enable_2fa.sh --apply

.PHONY: cron-check
cron-check: ## Verifica se cron está “vivo” (baseado no magento.cron.log)
	@./scripts/cron_health.sh

.PHONY: permissions-dry
permissions-dry: ## Mostra as ações de permissões (dry-run)
	@./scripts/permissions_reset.sh

.PHONY: permissions
permissions: ## Aplica reset de permissões (requer privilégios para chown)
	@./scripts/permissions_reset.sh --apply

.PHONY: permissions-harden
permissions-harden: ## Remove permissões world-writable (o+w) (dry-run)
	@./scripts/permissions_harden.sh

.PHONY: permissions-harden-apply
permissions-harden-apply: ## Remove permissões world-writable (o+w) (aplica)
	@./scripts/permissions_harden.sh --apply

.PHONY: permissions-lockdown
permissions-lockdown: ## Lockdown de permissões (dry-run)
	@./scripts/permissions_lockdown.sh

.PHONY: permissions-lockdown-apply
permissions-lockdown-apply: ## Lockdown de permissões (aplica)
	@./scripts/permissions_lockdown.sh --apply

# ====== ERP Integration ======

.PHONY: erp-sync-products
erp-sync-products: ## Sincroniza produtos do ERP (texto/preço/status)
	@$(MAGENTO) erp:sync:products

.PHONY: erp-sync-images
erp-sync-images: ## Sincroniza imagens de produtos do ERP
	@$(MAGENTO) erp:sync:images

.PHONY: erp-sync-images-force
erp-sync-images-force: ## Sincroniza imagens do ERP (força mesmo se desabilitado)
	@$(MAGENTO) erp:sync:images --force

.PHONY: erp-sync-all
erp-sync-all: ## Sincroniza tudo: produtos + imagens + estoque + preços
	@echo "=== Sincronizando Produtos ==="
	@$(MAGENTO) erp:sync:products
	@echo ""
	@echo "=== Sincronizando Imagens ==="
	@$(MAGENTO) erp:sync:images
	@echo ""
	@echo "=== Sincronizando Estoque ==="
	@$(MAGENTO) erp:sync:stock
	@echo ""
	@echo "=== Sincronizando Preços ==="
	@$(MAGENTO) erp:sync:prices
	@echo ""
	@echo "=== Flush de cache ==="
	@$(MAGENTO) cache:flush
	@echo "✅ Sync completo!"

.PHONY: erp-fix-images
erp-fix-images: ## Diagnóstico + correção + sync de imagens ERP (all-in-one)
	@php scripts/fix_and_sync_erp_images.php

.PHONY: erp-diagnose-images
erp-diagnose-images: ## Diagnóstico de imagens ERP (somente leitura)
	@php scripts/diagnostico_imagens_erp.php

.PHONY: erp-status
erp-status: ## Status da integração ERP (conexão, tabelas, contagens)
	@$(MAGENTO) erp:status
