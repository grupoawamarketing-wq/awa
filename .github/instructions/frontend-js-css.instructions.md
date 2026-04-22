---
applyTo: "**/view/frontend/web/js/**/*.js,**/view/adminhtml/web/js/**/*.js,**/view/**/web/css/**/*.css,**/view/**/web/css/**/*.less,**/AWA_Custom/ayo_home5_child/web/css/**/*.css,**/AWA_Custom/ayo_home5_child/web/css/**/*.less"
---

# Regras para Frontend JS e Estilos (Magento 2)

## JavaScript — RequireJS

### Componente com Widget jQuery
```javascript
define([
    'jquery',
    'mage/translate',
    'Magento_Ui/js/modal/alert'
], function ($, $t, alertModal) {
    'use strict';

    return function (config, element) {
        var $root = $(element);

        // Event handlers via delegação
        $root.on('click', '[data-action="submit"]', function (event) {
            event.preventDefault();
            // lógica
        });
    };
});
```

### Inicialização no Template PHTML
```html
<!-- data-mage-init (inline) -->
<div data-mage-init='{"GrupoAwamotos_ModuleName/js/component": {"option": "value"}}'>
</div>

<!-- x-magento-init (script block) -->
<script type="text/x-magento-init">
{
    "#element-id": {
        "GrupoAwamotos_ModuleName/js/component": {
            "option": "value"
        }
    }
}
</script>
```

### Componente Knockout.js (UI Component)
```javascript
define([
    'uiComponent',
    'ko'
], function (Component, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'GrupoAwamotos_ModuleName/component-template'
        },

        initialize: function () {
            this._super();
            this.items = ko.observableArray([]);
            return this;
        }
    });
});
```

### requirejs-config.js
```javascript
var config = {
    map: {
        '*': {
            'componentAlias': 'GrupoAwamotos_ModuleName/js/component'
        }
    },
    paths: {
        'externalLib': 'GrupoAwamotos_ModuleName/js/lib/external'
    }
};
```

## Padrões Obrigatórios JS
- SEMPRE `'use strict';` dentro do callback do `define`
- jQuery via RequireJS (`define(['jquery'], function($) { ... })`) — NUNCA CDN
- Form key: usar `window.FORM_KEY` ou `$.mage.cookies.get('form_key')`
- Escapar HTML ao inserir dados dinâmicos no DOM (XSS)
- Tradução: usar `$t('texto')` via `mage/translate`
- Event delegation: `$container.on('event', 'selector', handler)` para conteúdo dinâmico
- AJAX: usar `$.ajax` com form_key no header ou dados

## CSS / LESS

### Estrutura de Arquivo CSS
```css
/**
 * Módulo: GrupoAwamotos_ModuleName
 * Componente: nome-do-componente
 */
.module-component {
    /* Custom properties herdando do tema Ayo */
    --component-primary: var(--aw-primary, #b73337);

    max-width: 1200px;
    margin: 0 auto;
}

.module-component__element {
    /* BEM-like naming */
}

.module-component--modifier {
    /* Variação */
}
```

### LESS (quando necessário)
- Usar variáveis do tema Ayo (consultar `_variables.less` do tema)
- Mixins do Magento: `lib/web/css/source/lib/`
- Import via `_module.less` na raiz do web/css

## Padrões Obrigatórios CSS
- Prefixar classes com namespace do módulo (evitar conflito)
- Usar CSS custom properties com fallback (ex: `var(--aw-primary, #b73337)`)
- Media queries para responsividade
- NUNCA `!important` sem justificativa real
- NUNCA estilos inline no PHP/PHTML — usar classes CSS

## NUNCA
- `document.write()` ou `eval()`
- jQuery via CDN ou tag `<script>` global
- Manipulação direta do DOM sem escape (XSS)
- `console.log` em produção
- CSS que sobrescreve estilos globais do tema sem namespace
- LESS com nesting acima de 3 níveis

---

## Deploy Obrigatório — Tema AWA_Custom/ayo_home5_child

Após editar qualquer arquivo CSS/LESS do tema filho, o deploy é **obrigatório** (sem ele o browser usa o cache da versão anterior):

```bash
# CSS ou LESS alterado
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child
sudo -u www-data php bin/magento cache:flush

# Apenas .phtml alterado (sem CSS)
sudo -u www-data php bin/magento cache:clean block_html full_page
```

> Bundles editáveis: `awa-bundle-core.unmin.css`, `awa-bundle-custom.unmin.css`, `awa-bundle-site.unmin.css`, `awa-bundle-phases.unmin.css`
> Tokens: `awa-core-variables.unmin.css` — usar sempre `var(--awa-red)`, nunca hex hardcoded.
> Detalhes completos → skill `design-system`

---

## Protocolo Anti-Regressão (obrigatório antes de editar CSS/LESS)

### 1. Auditoria do estado atual
Antes de qualquer edição CSS, execute:
```bash
# Verificar qual bundle já define o seletor alvo
grep -r "seletor-alvo" pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/ --include="*.css" -l

# Ver onde o seletor é definido com contexto
grep -n "seletor-alvo" pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-bundle-*.css
```

### 2. Mapa de bundles por contexto
| Área da página | Bundle fonte (editável) | Bundle deployado |
|----------------|------------------------|-----------------|
| Header / Footer | `awa-bundle-core.unmin.css` | `awa-bundle-core.css` |
| PLP / Categoria | `awa-bundle-category.unmin.css` | `awa-bundle-category.css` |
| PDP / Produto | `awa-bundle-site.unmin.css` | `awa-bundle-site.css` |
| Variáveis / Tokens | `awa-core-variables.unmin.css` | `awa-core-variables.css` |
| Overrides finais | `awa-bundle-refinements.unmin.css` | `awa-bundle-refinements.css` |

Regra: edite sempre o **menor bundle** que cobre o contexto. Não coloque CSS de PDP no `core`.

### 3. Service Worker — bump obrigatório após editar bundles
O `sw.js` faz cache agressivo dos bundles CSS. Após editar qualquer `awa-bundle-*.unmin.css`, incremente `CACHE_VERSION`:
```bash
grep -n "CACHE_VERSION" pub/sw.js  # sw.js fica em pub/sw.js, nao dentro do tema
# Edite o valor e copie para pub/static
```

### 4. Checklist pós-deploy
```bash
# 1. Verificar deploy completou sem erro
tail -5 var/log/system.log

# 2. Verificar sem regressão de exception
tail -5 var/log/exception.log

# 3. Confirmar arquivo deployado tem o conteúdo esperado
grep "seletor-editado" pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-bundle-*.css
```

No browser: **Disable cache** (DevTools Network) + desregistrar Service Worker (DevTools Application → Service Workers → Unregister) antes de verificar.

### 5. Regras de especificidade (evitar guerra de !important)
- Use seletores compostos (`html body .awa-header__nav`) para ganhar especificidade sem `!important`
- `!important` só é aceito em `awa-bundle-refinements.unmin.css` (bundle final) e deve ter comentário explicando o motivo
- Nunca use `#id` para estilizar componentes reutilizáveis — use classes BEM
