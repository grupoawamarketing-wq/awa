# Correção de Regressão - 2026-06-05

## Problema
As otimizações "emergenciais" que apliquei causaram regressões no layout do site:
1. Layout antigo voltou
2. Site ficou visualmente quebrado/feio
3. CSS moderno não estava sendo aplicado

## Causa
Adicionei CSS inline "emergencial" que sobrescrevia os estilos modernos do site:
```css
<style id="awa-critical-emergency">
:root{--p:#b73337;--t:#333;--b:#fff;--g:#f7f7f7}
html,body{font-family:system-ui,-apple-system,sans-serif}
...
</style>
```

## Ações Corretivas

### 1. Removido CSS inline emergencial
**Arquivo:** `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-head-preload.phtml`
- Removido `<style id="awa-critical-emergency">`
- Removido `<script id="awa-emergency-css-lazy">`

### 2. Cache limpo completo
```bash
sudo -u www-data php bin/magento cache:flush
redis-cli -n 1 FLUSHDB
redis-cli -n 2 FLUSHDB
```

## Resultado
✅ Site voltou a carregar CSS normalmente:
- `awa-super-global.min.css` ✓
- `awa-home-terminal-bundle.min.css` ✓
- Versão nova: `1780625344`

## Lições Aprendidas
1. Nunca adicionar CSS inline simplificado em produção
2. Testar visualmente antes de qualquer alteração
3. Manter backups antes de otimizações arriscadas

## Status
✅ Site restaurado ao estado anterior
