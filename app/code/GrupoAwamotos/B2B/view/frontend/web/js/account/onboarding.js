define([
    'jquery',
    'shepherd',
    'mage/translate',
    'mage/cookies'
], function ($, Shepherd, $t) {
    'use strict';

    const TOUR_SEEN_KEY = 'awa_b2b_tour_seen';

    return function () {
        const tour = new Shepherd.Tour({
            useModalOverlay: true,
            defaultStepOptions: {
                classes: 'shepherd-theme-custom',
                scrollTo: { behavior: 'smooth', block: 'nearest' },
                cancelIcon: {
                    enabled: true
                }
            }
        });

        const markTourSeen = function () {
            const date = new Date();
            date.setTime(date.getTime() + (365 * 24 * 60 * 60 * 1000));
            $.mage.cookies.set(TOUR_SEEN_KEY, '1', { expires: date });

            try {
                window.localStorage.setItem(TOUR_SEEN_KEY, '1');
            } catch (e) {
                // ignore storage failures (private mode, etc.)
            }
        };

        const hasSeenTour = function () {
            if ($.mage.cookies.get(TOUR_SEEN_KEY)) {
                return true;
            }

            try {
                return window.localStorage.getItem(TOUR_SEEN_KEY) === '1';
            } catch (e) {
                return false;
            }
        };

        const resolveAttachTarget = function (selector, fallbackSelector) {
            if ($(selector).length) {
                return selector;
            }

            if (fallbackSelector && $(fallbackSelector).length) {
                return fallbackSelector;
            }

            return null;
        };

        const addAnchoredStep = function (stepConfig) {
            const selector = resolveAttachTarget(stepConfig.attachTo, stepConfig.fallbackAttachTo);

            if (!selector) {
                return;
            }

            tour.addStep({
                id: stepConfig.id,
                text: stepConfig.text,
                attachTo: {
                    element: selector,
                    on: stepConfig.on || 'bottom'
                },
                buttons: stepConfig.buttons
            });
        };

        addAnchoredStep({
            id: 'welcome',
            text: $t('Bem-vindo ao Portal B2B Grupo AWA! Este é o seu novo centro de comando para compras no atacado.'),
            attachTo: '.b2b-dashboard-welcome',
            fallbackAttachTo: '.b2b-dashboard-header',
            on: 'bottom',
            buttons: [
                {
                    text: $t('Próximo'),
                    action: tour.next
                }
            ]
        });

        if ($('.b2b-analytics-section').length) {
            addAnchoredStep({
                id: 'analytics',
                text: $t('Acompanhe seu desempenho de compras com gráficos em tempo real. Identifique tendências e planeje seu estoque.'),
                attachTo: '.b2b-analytics-section',
                on: 'top',
                buttons: [
                    {
                        text: $t('Anterior'),
                        action: tour.back,
                        classes: 'shepherd-button-secondary'
                    },
                    {
                        text: $t('Próximo'),
                        action: tour.next
                    }
                ]
            });
        }

        if ($('.b2b-quickorder-shortcut').length) {
            addAnchoredStep({
                id: 'quickorder',
                text: $t('Ganhe tempo com o Pedido Rápido. Adicione múltiplos SKUs de uma vez ou importe sua planilha CSV.'),
                attachTo: '.b2b-quickorder-shortcut',
                on: 'bottom',
                buttons: [
                    {
                        text: $t('Anterior'),
                        action: tour.back,
                        classes: 'shepherd-button-secondary'
                    },
                    {
                        text: $t('Próximo'),
                        action: tour.next
                    }
                ]
            });
        }

        if ($('.b2b-subscriptions-shortcut').length) {
            addAnchoredStep({
                id: 'subscriptions',
                text: $t('Configure Assinaturas Recorrentes para seus itens de maior giro e nunca fique sem estoque.'),
                attachTo: '.b2b-subscriptions-shortcut',
                on: 'top',
                buttons: [
                    {
                        text: $t('Anterior'),
                        action: tour.back,
                        classes: 'shepherd-button-secondary'
                    },
                    {
                        text: $t('Próximo'),
                        action: tour.next
                    }
                ]
            });
        }

        tour.addStep({
            id: 'finish',
            text: $t('Pronto! Você está pronto para decolar. Caso precise de ajuda, entre em contato com seu atendente comercial.'),
            buttons: [
                {
                    text: $t('Finalizar'),
                    action: tour.complete
                }
            ]
        });

        tour.on('complete', markTourSeen);
        tour.on('cancel', markTourSeen);

        const startTour = function (force) {
            if (tour.isActive()) {
                return;
            }

            if (!force && hasSeenTour()) {
                return;
            }

            tour.start();
        };

        if (!hasSeenTour()) {
            window.setTimeout(function () {
                startTour(false);
            }, 1200);
        }

        $(document).on('click', '.trigger-b2b-tour', function (event) {
            event.preventDefault();
            startTour(true);
        });
    };
});
