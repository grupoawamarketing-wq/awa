define(['jquery'], function ($) {
    'use strict';
    return function (config, element) {
        let endpoints = config.endpoints || {};
        var $marca = $('#fitment-marca');
        var $modelo = $('#fitment-modelo');
        var $ano = $('#fitment-ano');
        var $submit = $('#fitment-submit');

        function reset(select, placeholder) {
            select.empty().append($('<option>', { value: '', text: placeholder }));
            select.prop('disabled', true);
        }

        function populate(select, items, placeholder) {
            reset(select, placeholder);
            if (items.length) {
                items.forEach(function (val) {
                    select.append($('<option>', { value: val, text: val }));
                });
                select.prop('disabled', false);
            }
        }

        $marca.on('change', function () {
            let val = this.value;
            reset($modelo, MagentoTranslate('Selecione a Marca primeiro'));
            reset($ano, MagentoTranslate('Selecione o Modelo primeiro'));
            $submit.prop('disabled', true);
            if (!val) { return; }
            $.getJSON(endpoints.models, { marca: val })
                .done(function (data) {
                    if (data.success) {
                        populate($modelo, data.items, MagentoTranslate('Selecione o Modelo')); }
                });
        });

        $modelo.on('change', function () {
            let marcaVal = $marca.val();
            let modeloVal = this.value;
            reset($ano, MagentoTranslate('Selecione o Modelo primeiro'));
            $submit.prop('disabled', true);
            if (!marcaVal || !modeloVal) { return; }
            $.getJSON(endpoints.years, { marca: marcaVal, modelo: modeloVal })
                .done(function (data) {
                    if (data.success) {
                        populate($ano, data.items, MagentoTranslate('Selecione o Ano')); }
                });
        });

        $ano.on('change', function () {
            $submit.prop('disabled', !(this.value && $modelo.val() && $marca.val()));
        });

        // Simple translation fallback
        function MagentoTranslate(str){ return str; }
    };
});
