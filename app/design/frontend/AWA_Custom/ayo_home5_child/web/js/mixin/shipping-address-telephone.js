/**
 * Oculta telefones placeholder no endereço de entrega do checkout.
 */
define([], function () {
    'use strict';

    function isPlaceholderTelephone(telephone) {
        var digits = String(telephone || '').replace(/\D/g, '');

        return digits === '' || digits === '0000000000' || digits === '00000000000';
    }

    return function (Component) {
        return Component.extend({
            /**
             * @return {String}
             */
            getDisplayTelephone: function () {
                var telephone = this.address() ? this.address().telephone : '';

                return isPlaceholderTelephone(telephone) ? '' : String(telephone || '');
            }
        });
    };
});
