/**
 * Fallback de imagem no resumo do checkout quando imageData não carrega.
 * require.toUrl resolve contra o baseUrl do RequireJS (tema/locale corretos).
 */
define([], function () {
    'use strict';

    var PLACEHOLDER = 'Magento_Catalog/images/product/placeholder/small_image.jpg';

    function buildPlaceholderUrl() {
        return window.require && window.require.toUrl
            ? window.require.toUrl(PLACEHOLDER)
            : PLACEHOLDER;
    }

    return function (Component) {
        return Component.extend({
            /**
             * @param {Object} item
             * @return {String|null}
             */
            getSrc: function (item) {
                var src = this._super(item);

                return src || buildPlaceholderUrl();
            },

            /**
             * Fallback visual quando a URL da mídia falha no carregamento.
             *
             * @param {Object} item
             * @param {Event} event
             */
            onImageError: function (item, event) {
                var target = event && event.currentTarget;
                var placeholder = buildPlaceholderUrl();

                if (!target || target.getAttribute('data-awa-img-fallback') === '1') {
                    return;
                }

                target.setAttribute('data-awa-img-fallback', '1');
                target.src = placeholder;
            }
        });
    };
});
