---
description: "Modelo console — auditar, reproduzir e corrigir bugs visuais AWA (Playwright Docker + CSS + deploy)"
applyTo: "**"
---

# AWA Visual Bug — Prompt Console

Copie o bloco abaixo, preencha os campos `[...]` e cole no agente (Cursor / Copilot / Claude).

```
╔══════════════════════════════════════════════════════════════════════════════╗
║  AWA MOTOS — CORRIGIR BUG VISUAL (console prompt)                            ║
╚══════════════════════════════════════════════════════════════════════════════╝

> CONTEXTO
  Loja:     awamotos.com (Magento 2.4.8 · tema AWA_Custom/ayo_home5_child)
  Ambiente: VPS produção — NÃO rodar Playwright pesado fora do Docker
  Regra:    fix mínimo · tokens var(--awa-*) · sem editar Rokanthemes/core/vendor

> BUG
  Página:   [ex: home / PLP categoria X / PDP SKU Y / checkout / header global]
  URL:      [https://awamotos.com/...]
  Viewport: [390 mobile | 768 tablet | 1366 notebook | 1920 desktop]
  Sintoma:  [ex: menu cortado, grid desalinhado, botão fora do container, CLS]
  Esperado: [descreva o layout correto em 1–2 frases]
  Evidência: [screenshot path / seletor CSS / print do DevTools se tiver]

> PROTOCOLO — EXECUTE NA ORDEM (não pule etapas)

  ┌─ 1. REPRODUZIR ─────────────────────────────────────────────────────────┐
  │ Browser MCP ou screenshot antes de editar qualquer arquivo.             │
  │ Desktop 1366 + mobile 390. Registrar seletor exato do elemento.         │
  └─────────────────────────────────────────────────────────────────────────┘

  ┌─ 2. DIAGNOSTICAR CSS ───────────────────────────────────────────────────┐
  │ Identificar bundle/zona (último na cascata que cobre o contexto):       │
  │   header/footer  → awa-bundle-core.unmin.css                            │
  │   PLP/categoria  → awa-bundle-category.unmin.css                        │
  │   PDP/produto    → awa-bundle-site.unmin.css                            │
  │   override final → awa-bundle-refinements.unmin.css                     │
  │   tokens         → awa-core-variables.unmin.css                         │
  │                                                                          │
  │ Comandos:                                                                │
  │   grep -rn "SELETOR" app/design/frontend/AWA_Custom/ayo_home5_child/web/css/
  │   grep -rn "SELETOR" app/design/frontend/AWA_Custom/ayo_home5_child/ --include="*.less"
  │   grep -rn "classe"  app/design/frontend/AWA_Custom/ayo_home5_child/ --include="*.phtml"
  │                                                                          │
  │ Ler: DESIGN_SYSTEM_STATUS.md · .cursor/rules/awa-design-system.mdc      │
  └─────────────────────────────────────────────────────────────────────────┘

  ┌─ 3. CORRIGIR (mínimo) ──────────────────────────────────────────────────┐
  │ • Preferir LESS partial em web/css/source/ (import via _extend.less)    │
  │ • Hex proibido — usar var(--awa-red), var(--awa-primary), etc.          │
  │ • !important só em refinements, com comentário                          │
  │ • Especificidade: html body .componente__elemento                       │
  │ • PHTML: override no tema filho, nunca Rokanthemes                      │
  └─────────────────────────────────────────────────────────────────────────┘

  ┌─ 4. DEPLOY ─────────────────────────────────────────────────────────────┐
  │ CSS/LESS alterado:                                                       │
  │   sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f \
  │     --theme AWA_Custom/ayo_home5_child                                   │
  │   sudo -u www-data php bin/magento cache:flush                           │
  │   redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 2 FLUSHDB   # FPC            │
  │                                                                          │
  │ PHTML alterado (copiar ANTES do cache:clean):                            │
  │   sudo -u www-data cp app/design/.../templates/arquivo.phtml \           │
  │     var/view_preprocessed/pub/static/app/design/.../arquivo.phtml        │
  │   sudo -u www-data php bin/magento cache:clean block_html full_page      │
  └─────────────────────────────────────────────────────────────────────────┘

  ┌─ 5. VALIDAR (Playwright Docker — seguro p/ SSH) ────────────────────────┐
  │ Sincronizar e rodar isolado (2 CPU · 4 GB RAM · workers=1):             │
  │                                                                          │
  │   /opt/playwright-job/sync.sh                                            │
  │   /opt/playwright-job/run.sh test \                                      │
  │     --config=pw-mcp-visual.safe.config.ts \                              │
  │     --workers=1                                                          │
  │                                                                          │
  │ Spec da área afetada (escolha um):                                       │
  │   specs/visual-audit-home-header-footer.spec.ts   # home/header/footer   │
  │   specs/visual-audit-search-category.spec.ts      # PLP/busca            │
  │   specs/visual-audit-pdp-login.spec.ts            # PDP/login            │
  │   specs/visual-audit-cart-checkout-404.spec.ts    # cart/checkout        │
  │   specs/visual-audit-core-regression.spec.ts      # core desktop+mobile  │
  │   specs/mcp-visual-ops.spec.ts                    # QA MCP (safe config) │
  │                                                                          │
  │ Exemplo filtrado:                                                        │
  │   /opt/playwright-job/run.sh test \                                      │
  │     specs/visual-audit-home-header-footer.spec.ts \                      │
  │     --workers=1 --project=notebook-1366                                  │
  │                                                                          │
  │ Monitorar carga: docker stats playwright-job                             │
  │ Logs: tail -5 var/log/exception.log                                      │
  └─────────────────────────────────────────────────────────────────────────┘

  ┌─ 6. BASELINE (só se layout confirmado visualmente) ─────────────────────┐
  │   /opt/playwright-job/run.sh test \                                      │
  │     specs/[SPEC].spec.ts \                                               │
  │     --config=pw-visual-core.config.ts \                                  │
  │     --update-snapshots --workers=1                                       │
  │ ⚠ Nunca atualizar snapshot com bug ainda presente.                       │
  └─────────────────────────────────────────────────────────────────────────┘

> ENTREGA ESPERADA
  [ ] Causa raiz (arquivo:linha + regra CSS/HTML conflitante)
  [ ] Diff mínimo aplicado
  [ ] Screenshots antes/depois (desktop + mobile)
  [ ] Playwright da área passou no Docker
  [ ] Áreas adjacentes sem regressão (header, footer, menu, mobile)
  [ ] exception.log limpo

> NÃO FAZER
  ✗ Rodar suite completa (~9000 testes) sem filtro de spec/project
  ✗ Playwright nativo no host com muitos workers (compete com SSH)
  ✗ Editar pub/static/ diretamente
  ✗ Hex hardcoded · !important sem comentário · refatorar fora do escopo
```

---

## Variantes rápidas (one-liners)

### Só auditar (sem corrigir ainda)
```
Audite visualmente [URL] em 390 e 1366. Liste seletor, bundle CSS culpado,
conflito de cascata e fix proposto. Não edite arquivos ainda.
```

### Corrigir header/menu
```
Bug visual no header/menu em [URL]. Siga visual-bug-console: reproduzir →
awa-bundle-core → deploy → /opt/playwright-job/run.sh test specs/header-layout.spec.ts --workers=1
```

### Regressão pós-deploy CSS
```
Após deploy CSS em [arquivo], rode visual-core no Docker e compare snapshots:
/opt/playwright-job/sync.sh && /opt/playwright-job/run.sh test --config=pw-visual-core.config.ts --workers=1
```

### Flakiness / timeout no Docker
```
Teste Playwright falha na 1ª tentativa (2min) e passa no retry — investigue
navigationTimeout, cold start Chromium, e se --ipc=host + --shm-size=1g estão ativos.
Não aumente workers; mantenha --workers=1.
```

---

## Mapa spec → página

| Área | Spec | Config recomendada |
|------|------|--------------------|
| Home + header + footer | `visual-audit-home-header-footer.spec.ts` | `pw-visual-audit.config.ts` |
| Core regression | `visual-audit-core-regression.spec.ts` | `pw-visual-core.config.ts` |
| PLP / busca | `visual-audit-search-category.spec.ts` | `pw-visual-audit.config.ts` |
| PDP / login | `visual-audit-pdp-login.spec.ts` | `pw-visual-audit.config.ts` |
| Cart / checkout | `visual-audit-cart-checkout-404.spec.ts` | `pw-visual-audit.config.ts` |
| MCP QA seguro | `mcp-visual-ops.spec.ts` | `pw-mcp-visual.safe.config.ts` |
| Smoke rápido | `specs/smoke/home.spec.ts` | default |
| Deep audit | `specs/deep-visual/*.visual.spec.ts` | `pw-deep-audit.config.ts` |

---

## Referências no repo

- Prompt debug layout: `.github/prompts/debug-layout-visual.prompt.md`
- QA agent estático: `.github/prompts/visual-qa-agent.prompt.md`
- Design system: `app/design/frontend/AWA_Custom/ayo_home5_child/DESIGN_SYSTEM_STATUS.md`
- Docker job: `/opt/playwright-job/run.sh` · `/opt/playwright-job/sync.sh`
