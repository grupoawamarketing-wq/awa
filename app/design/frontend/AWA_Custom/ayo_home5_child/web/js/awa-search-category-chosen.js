define([
    'jquery',
    'rokanthemes/choose'
], function ($) {
    'use strict';

    return function (config, element) {
        var $select = $(element);
        var options = $.extend({}, config || {});

        if (!$select.length) {
            return;
        }

        if ($select.data('awaChosenInit')) {
            return;
        }

        $select.attr('data-awa-component', $select.attr('data-awa-component') || 'search-category-select');

        if (typeof $select.chosen === 'function' && !$select.data('chosen')) {
            $select.chosen(options);
        }

        $select.data('awaChosenInit', 1);
    };
});
