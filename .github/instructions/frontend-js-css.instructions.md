---
applyTo: "**/view/frontend/web/js/**/*.js,**/view/adminhtml/web/js/**/*.js,**/view/**/web/css/**/*.css,**/view/**/web/css/**/*.less"
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
