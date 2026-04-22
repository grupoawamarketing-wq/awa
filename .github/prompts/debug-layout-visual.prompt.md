---
description: "Investiga bug visual ou layout quebrado — captura screenshot, analisa DOM, identifica CSS/PHTML culpado, corrige sem afetar o que está funcionando"
agent: "Awa"
tools:
  - codebase
  - editFiles
  - runCommand
  - fetch
---

# Debug de Layout Visual — AWA Motos

Investigue e corrija o bug visual descrito. Siga o protocolo abaixo para não introduzir regressões.

## Workflow Obrigatório

### 1. Capturar estado atual (Chrome MCP)
Use as ferramentas Chrome MCP nesta ordem:
1. Navegue para a página com o bug
2. Tire screenshot (desktop 1920px)
3. Mude para mobile (375px) e tire outro screenshot
4. Analise o DOM com `take_snapshot` — identifique o elemento problemático

### 2. Identificar o CSS culpado
```bash
# Qual bundle cobre o elemento?
grep -rn "SELETOR_PROBLEMÁTICO" pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-bundle-*.css

# Verificar qual bundle carrega por último e pode estar sobrescrevendo
# Ordem de cascata (última ganha):
# 1. styles-m.css / styles-l.css
# 2. themes.css / themes5.css
# 3. awa-bundle-core.css
# 4. awa-bundle-category.css
# 5. awa-bundle-phases.css
# 6. awa-bundle-site.css
# 7. awa-bundle-refinements.css
```

### 3. Identificar o template culpado (se for PHTML)
```bash
# Encontrar qual template renderiza o elemento
grep -rn "classe-do-elemento" app/design/frontend/AWA_Custom/ayo_home5_child/ --include="*.phtml"
grep -rn "classe-do-elemento" app/code/GrupoAwamotos/*/view/frontend/templates/ --include="*.phtml"

# Verificar se o preprocessed está desatualizado (produção)
ls -la var/view_preprocessed/pub/static/app/design/frontend/AWA_Custom/ayo_home5_child/
```

### 4. Auditoria de regressão ANTES de editar
```bash
# Listar todos os seletores do bloco afetado no bundle que vai editar
# Para garantir que a mudança não quebra o entorno
grep -n "BLOCO_MAE" pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-bundle-BUNDLE.css | head -20
```

### 5. Corrigir no lugar correto
| Área afetada | Arquivo fonte para editar |
|-------------|--------------------------|
| Header / Footer | `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-core.unmin.css` |
| PLP / Categoria | `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-category.unmin.css` |
| PDP / Produto | `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-site.unmin.css` |
| Override final | `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-refinements.unmin.css` |
| Tokens de cor | `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-core-variables.unmin.css` |
| Template PHTML | `app/design/frontend/AWA_Custom/ayo_home5_child/[Vendor_Module]/templates/arquivo.phtml` |

**Regras de edição:**
- Usar `var(--awa-red)`, NUNCA hex hardcoded
- `!important` só em `refinements`, com comentário explicando o motivo
- BEM: `html body .componente__elemento` para ganhar especificidade sem `!important`

### 6. Deploy
```bash
# CSS/LESS alterado
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
sudo -u www-data php bin/magento cache:flush

# PHTML alterado — copiar para preprocessed ANTES do cache:clean
sudo -u www-data cp app/design/frontend/AWA_Custom/ayo_home5_child/[Module]/templates/[file].phtml \
  var/view_preprocessed/pub/static/app/design/frontend/AWA_Custom/ayo_home5_child/[Module]/templates/[file].phtml
sudo -u www-data php bin/magento cache:clean block_html full_page

# Bump CACHE_VERSION no sw.js se editou bundle CSS
grep -n "CACHE_VERSION" pub/sw.js  # sw.js fica em pub/sw.js, nao dentro do tema
```

### 7. Validação pós-fix (Chrome MCP)
1. Screenshot desktop (1920px) — confirmar que o bug foi corrigido
2. Screenshot mobile (375px) — confirmar que mobile não regrediu
3. Screenshot das áreas adjacentes (header, footer, seções próximas)
4. `tail -5 var/log/exception.log` — sem novas entradas

### 8. Checklist anti-regressão
- [ ] Bug visual corrigido na área descrita
- [ ] Áreas adjacentes intactas (header, footer, mobile)
- [ ] `exception.log` sem novas entradas
- [ ] Service worker não está servindo versão em cache (verificar no DevTools → Application → Service Workers)
- [ ] Nenhum `!important` adicionado sem comentário
- [ ] Nenhum hex hardcoded — usar tokens `var(--awa-*)`

## Playwright — Testes Visuais Existentes
Os specs já existem em `tests/e2e/specs/`. Para rodar após a correção:
```bash
cd tests/e2e
# Rodar spec específico
npx playwright test specs/visual-audit-home-header-footer.spec.ts --headed

# Atualizar baseline (só após confirmar visualmente que está correto)
npx playwright test specs/visual-audit-home-header-footer.spec.ts --update-snapshots

# Ver relatório
npx playwright show-report reports/html
```

> ⚠️ `--update-snapshots` sobrescreve o baseline. Só rode após confirmar visualmente que o layout está correto.
