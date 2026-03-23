/**
 * AWA Search Recent — buscas recentes via localStorage + atalho de teclado /
 *
 * Integra com o painel de autocomplete existente (#search_autocomplete).
 * Exibe até 5 buscas recentes quando o input está em foco e vazio (< 2 chars).
 * Mirasvit assume quando o usuário digita 2+ chars — sem conflito.
 */
define([], function () {
    'use strict';

    var STORAGE_KEY = 'awa_recent_searches';
    var MAX_ITEMS   = 5;

    /* ---- storage helpers ---- */

    function getRecent() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function saveRecent(list) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
        } catch (e) {
            /* quota exceeded or private mode — silently ignore */
        }
    }

    function addRecent(query) {
        var trimmed = String(query || '').trim();

        if (trimmed.length < 2) {
            return;
        }

        var list = getRecent().filter(function (s) {
            return s !== trimmed;
        });

        list.unshift(trimmed);
        saveRecent(list.slice(0, MAX_ITEMS));
    }

    /* ---- render ---- */

    function escapeAttr(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    var LISTBOX_ID = 'awa-recent-listbox';

    function buildHTML(searches) {
        var items = searches.map(function (s, i) {
            var id = 'awa-recent-opt-' + i;
            return '<li class="awa-recent-item" role="option" id="' + id + '">' +
                '<svg class="awa-recent-item__icon" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<polyline points="1 4 1 10 7 10"/>' +
                '<path d="M3.51 15a9 9 0 1 0 .49-3.07"/>' +
                '</svg>' +
                '<button type="button" class="awa-recent-item__query" data-awa-recent-query="' + escapeAttr(s) + '" ' +
                'tabindex="-1">' +
                escapeAttr(s) +
                '</button>' +
                '<button type="button" class="awa-recent-item__remove" data-awa-recent-remove="' + i + '" ' +
                'aria-label="Remover busca: ' + escapeAttr(s) + '" tabindex="-1">&#x2715;</button>' +
                '</li>';
        }).join('');

        return '<div class="awa-recent-searches" data-awa-recent-panel>' +
            '<div class="awa-recent-searches__header">' +
            '<span class="awa-recent-searches__title">Buscas recentes</span>' +
            '<button type="button" class="awa-recent-searches__clear" data-awa-clear-all>Limpar tudo</button>' +
            '</div>' +
            '<ul class="awa-recent-searches__list" role="listbox" id="' + LISTBOX_ID + '" aria-label="Buscas recentes">' +
            items +
            '</ul>' +
            '</div>';
    }

    /* ---- component ---- */

    return function (config, element) {
        var opts     = config || {};
        var input    = element.querySelector('[data-awa-search-input]') ||
                       element.querySelector('#search');
        var panel    = element.querySelector('[data-awa-search-panel]') ||
                       element.querySelector('#search_autocomplete');
        var form     = element.querySelector('form');
        var maxItems = opts.maxItems || MAX_ITEMS;

        if (!input || !panel) {
            return;
        }

        /* --- show / hide recent panel --- */

        function isAutocompleteActive() {
            /* Mirasvit or other autocomplete is active when panel has real results */
            return !!panel.querySelector('[data-mirasvit-id], .search-autocomplete-item, .item');
        }

        function showRecent() {
            if (isAutocompleteActive()) {
                return;
            }

            var searches = getRecent().slice(0, maxItems);

            if (!searches.length) {
                return;
            }

            /* remove stale recent panel if any */
            hideRecentPanel();

            var html = buildHTML(searches);
            panel.insertAdjacentHTML('afterbegin', html);
            panel.removeAttribute('hidden');
            panel.setAttribute('aria-hidden', 'false');
            input.setAttribute('aria-expanded', 'true');
        }

        function hideRecentPanel() {
            var existing = panel.querySelector('[data-awa-recent-panel]');

            if (existing) {
                existing.remove();
            }
        }

        function maybeClearPanel() {
            if (!panel.querySelector('[data-awa-recent-panel]')) {
                return; /* autocomplete panel — don't touch */
            }

            if (input.value.trim().length < 2) {
                return; /* keep showing */
            }

            hideRecentPanel();
        }

        function closeIfEmpty() {
            /* hide panel completely when no recent + no autocomplete results */
            if (
                !panel.querySelector('[data-awa-recent-panel]') &&
                !isAutocompleteActive()
            ) {
                panel.setAttribute('hidden', '');
                panel.setAttribute('aria-hidden', 'true');
                input.setAttribute('aria-expanded', 'false');
            }
        }

        /* --- event binding --- */

        input.addEventListener('focus', function () {
            if (input.value.trim().length < 2) {
                showRecent();
            }
        });

        input.addEventListener('input', function () {
            var val = input.value.trim();

            if (val.length >= 2) {
                hideRecentPanel();
            } else if (val.length === 0) {
                showRecent();
            }
        });

        /* delegate clicks inside panel */
        panel.addEventListener('click', function (e) {
            var queryBtn  = e.target.closest('[data-awa-recent-query]');
            var removeBtn = e.target.closest('[data-awa-recent-remove]');
            var clearBtn  = e.target.closest('[data-awa-clear-all]');

            if (queryBtn) {
                var q = queryBtn.getAttribute('data-awa-recent-query');
                input.value = q;
                addRecent(q);

                if (form) {
                    form.submit();
                }

                return;
            }

            if (removeBtn) {
                var idx     = parseInt(removeBtn.getAttribute('data-awa-recent-remove'), 10);
                var current = getRecent();
                current.splice(idx, 1);
                saveRecent(current);

                /* re-render */
                hideRecentPanel();

                if (current.length) {
                    showRecent();
                } else {
                    closeIfEmpty();
                }

                return;
            }

            if (clearBtn) {
                saveRecent([]);
                hideRecentPanel();
                closeIfEmpty();
            }
        });

        /* save on form submit */
        if (form) {
            form.addEventListener('submit', function () {
                addRecent(input.value);
            });
        }

        /* ---- Arrow key / Escape navigation inside the recent panel ---- */

        function getQueryButtons() {
            return Array.prototype.slice.call(
                panel.querySelectorAll('.awa-recent-item__query')
            );
        }

        function setActiveDescendant(id) {
            if (id) {
                input.setAttribute('aria-activedescendant', id);
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        }

        /* ArrowDown from input → focus first item */
        input.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'Escape') return;
            var recentPanel = panel.querySelector('[data-awa-recent-panel]');
            if (!recentPanel) return;

            e.preventDefault();

            if (e.key === 'Escape') {
                hideRecentPanel();
                closeIfEmpty();
                return;
            }

            var btns = getQueryButtons();
            if (!btns.length) return;

            if (e.key === 'ArrowDown') {
                btns[0].focus();
                setActiveDescendant(btns[0].closest('[role="option"]').id);
            } else if (e.key === 'ArrowUp') {
                btns[btns.length - 1].focus();
                setActiveDescendant(btns[btns.length - 1].closest('[role="option"]').id);
            }
        });

        /* Arrow navigation between items; Escape returns to input */
        panel.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'Escape') return;
            var btn = e.target.closest('.awa-recent-item__query, .awa-recent-item__remove');
            if (!btn) return;

            e.preventDefault();
            var btns = getQueryButtons();

            if (e.key === 'Escape') {
                hideRecentPanel();
                closeIfEmpty();
                setActiveDescendant(null);
                input.focus();
                return;
            }

            /* Only navigate within query buttons */
            var queryBtn = e.target.closest('.awa-recent-item__query');
            if (!queryBtn) return;

            var idx = btns.indexOf(queryBtn);
            var next;

            if (e.key === 'ArrowDown') {
                next = btns[idx + 1];
                if (next) {
                    next.focus();
                    setActiveDescendant(next.closest('[role="option"]').id);
                }
            } else if (e.key === 'ArrowUp') {
                if (idx === 0) {
                    setActiveDescendant(null);
                    input.focus();
                } else {
                    next = btns[idx - 1];
                    next.focus();
                    setActiveDescendant(next.closest('[role="option"]').id);
                }
            }
        });

        /* Reset aria-activedescendant when focus returns to input */
        input.addEventListener('focus', function () {
            setActiveDescendant(null);
        });

        /* close on outside click */
        document.addEventListener('click', function (e) {
            if (!element.contains(e.target) && !panel.contains(e.target)) {
                hideRecentPanel();
                closeIfEmpty();
            }
        });

        /* ---- global keyboard shortcut: / to focus search ---- */

        document.addEventListener('keydown', function (e) {
            if (e.key !== '/') {
                return;
            }

            if (e.ctrlKey || e.metaKey || e.altKey) {
                return;
            }

            var active = document.activeElement;
            var tag    = active ? active.tagName.toLowerCase() : '';

            if (tag === 'input' || tag === 'textarea' || tag === 'select' ||
                (active && active.isContentEditable)) {
                return;
            }

            e.preventDefault();
            input.focus();
            input.select();
        });
    };
});
