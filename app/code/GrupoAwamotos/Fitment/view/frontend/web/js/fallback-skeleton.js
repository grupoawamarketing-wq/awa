define(['jquery'], function ($) {
    'use strict';
    return function () {
        var $skeleton = $('#fallback-skeleton');
        var $grid = $('#fallback-products-list');
        if ($skeleton.length && $grid.length) {
            // Grace period para permitir pintura inicial
            setTimeout(function(){
                $skeleton.fadeOut(150, function(){ $skeleton.remove(); });
                let count = $grid.find('.product-item').length;
                var $live = $('#products-count-live');
                if ($live.length) {
                    $live.text(count + ' produtos carregados');
                }
            }, 60);
        }
    };
});