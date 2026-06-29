(function (w, d) {
    'use strict';

    var cfgNode = d.getElementById('awa-mirasvit-ac-bootstrap-config');
    if (!cfgNode || !cfgNode.textContent) {
        return;
    }

    var cfg;
    try {
        cfg = JSON.parse(cfgNode.textContent);
    } catch (e) {
        return;
    }

    var selector = cfg.selector || 'input#search, input#mobile_search, .minisearch input[type="text"]';
    var started = false;

    function ensureTemplates(done) {
        if (d.getElementById('searchAutocompletePlaceholder')) {
            done();
            return;
        }

        var templatesUrl = cfg.templatesUrl || '';
        if (!templatesUrl) {
            done();
            return;
        }

        fetch(templatesUrl, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('mirasvit templates fetch failed');
                }
                return response.text();
            })
            .then(function (html) {
                var host = d.createElement('div');
                host.hidden = true;
                host.id = 'awa-mirasvit-templates-host';
                host.innerHTML = html;
                (d.body || d.documentElement).appendChild(host);
                done();
            })
            .catch(function () {
                done();
            });
    }

    function bootstrapInPageAutocomplete() {
        if (started) {
            return;
        }
        started = true;

        w.require([
            'jquery',
            'Mirasvit_SearchAutocomplete/js/in-page'
        ], function ($, InPage) {
            $(d).ready(function () {
                $('#search_mini_form').prop('minSearchLength', 10000);

                var $input = $(selector);
                $input.each(function (index, searchInput) {
                    var $searchInput = $(searchInput);
                    var hasPanel = $searchInput.nextAll('#search_autocomplete, .search-autocomplete, .searchsuite-autocomplete').length > 0;

                    if (!hasPanel || $searchInput.data('awaMirasvitInPageInit')) {
                        return;
                    }

                    $searchInput.data('awaMirasvitInPageInit', 1);
                    new InPage($searchInput, cfg.config);
                });
            });
        });
    }

    function bootstrapAutocomplete() {
        if (started) {
            return;
        }
        started = true;

        ensureTemplates(function () {
            var loadModules = function () {
                w.require([
                    'jquery',
                    'Mirasvit_SearchAutocomplete/js/autocomplete',
                    'Mirasvit_SearchAutocomplete/js/typeahead'
                ], function ($, autocomplete, typeahead) {
                    $(d).ready(function () {
                        $('#search_mini_form').prop('minSearchLength', 10000);

                        var $input = $(selector);
                        $input.each(function (index, searchInput) {
                            var $searchInput = $(searchInput);
                            var hasPanel = $searchInput.nextAll('#search_autocomplete, .search-autocomplete, .searchsuite-autocomplete').length > 0;

                            if (!hasPanel || $searchInput.data('awaMirasvitAutocompleteInit')) {
                                return;
                            }

                            $searchInput.data('awaMirasvitAutocompleteInit', 1);

                            if (cfg.isTypeaheadEnabled) {
                                new typeahead($searchInput).init(cfg.config);
                            }
                            new autocomplete($searchInput).init(cfg.config);
                        });
                    });
                });
            };

            if (typeof w.awaRunWhenRequire === 'function') {
                w.awaRunWhenRequire(loadModules, { key: 'mirasvit-autocomplete' });
            } else {
                loadModules();
            }
        });
    }

    function bootstrap() {
        if (cfg.isInPageLayout) {
            bootstrapInPageAutocomplete();
        } else {
            bootstrapAutocomplete();
        }
    }

    if (cfg.isHomePage || cfg.deferBootstrap) {
        function onSearchIntent(event) {
            var target = event && event.target;
            if (!target || typeof target.closest !== 'function') {
                return;
            }

            if (!target.closest('#search, #mobile_search, .minisearch, #search_mini_form, .block-search')) {
                return;
            }

            bootstrap();
        }

        ['focusin', 'pointerdown', 'touchstart'].forEach(function (evtName) {
            d.addEventListener(evtName, onSearchIntent, { passive: true, capture: true });
        });

        d.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && String(event.key).toLowerCase() === 'k') {
                bootstrap();
                return;
            }

            if (event.key === '/' && !(event.target && (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA'))) {
                bootstrap();
            }
        }, { capture: true });

        /* Intent-only: evita require/jQuery no load (home + carrinho/checkout) */
    } else {
        bootstrap();
    }
}(window, document));
