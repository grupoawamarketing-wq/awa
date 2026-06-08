/**
 * AMD bridge for vendored @floating-ui/core + @floating-ui/dom UMD builds.
 */
define([
    'js/vendor/floating-ui.core.umd',
    'js/vendor/floating-ui.dom.umd'
], function () {
    'use strict';

    return window.FloatingUIDOM || {};
});
