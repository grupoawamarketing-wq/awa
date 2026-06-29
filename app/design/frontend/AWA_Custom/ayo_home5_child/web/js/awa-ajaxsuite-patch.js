/**
 * AWA AjaxSuite patch — passthrough para o módulo original Rokanthemes_AjaxSuite.
 *
 * Compatibilidade retroativa com browsers que possuem em cache merged bundles antigos
 * contendo o alias: 'rokanthemes/ajaxsuite': 'js/awa-ajaxsuite-patch'
 */
define(['Rokanthemes_AjaxSuite/js/ajaxsuite'], function (ajaxsuite) {
    'use strict';

    return ajaxsuite;
});
