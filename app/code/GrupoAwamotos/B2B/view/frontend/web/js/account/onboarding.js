define([
    'jquery',
    'shepherd',
    'mage/translate',
    'mage/cookies'
], function ($, Shepherd, $t) {
    'use strict';

    return function (config) {
        const tour = new Shepherd.Tour({
            useModalOverlay: true,
            defaultStepOptions: {
                classes: 'shepherd-theme-custom',
                scrollTo: { behavior: 'smooth', block: 'center' },
                cancelIcon: {
                    enabled: true
                }
            }
        });

        // Step 1: Welcome
        tour.addStep({
            id: 'welcome',
            text: $t('Bem-vindo ao Portal B2B Grupo AWA! Este é o seu novo centro de comando para compras no atacado.'),
            attachTo: {
                element: '.b2b-dashboard-welcome',
                on: 'bottom'
            },
            buttons: [
                {
                    text: $t('Próximo'),
                    action: tour.next
                }
            ]
        });

        // Step 2: Analytics
        if ($('.b2b-analytics-section').length) {
            tour.addStep({
                id: 'analytics',
                text: $t('Acompanhe seu desempenho de compras com gráficos em tempo real. Identifique tendências e planeje seu estoque.'),
                attachTo: {
                    element: '.b2b-analytics-section',
                    on: 'top'
                },
                buttons: [
                    {
                        text: $t('Anterior'),
                        action: tour.back
                    },
                    {
                        text: $t('Próximo'),
                        action: tour.next
                    }
                ]
            });
        }

        // Step 3: Quick Order
        if ($('.b2b-quickorder-shortcut').length) {
            tour.addStep({
                id: 'quickorder',
                text: $t('Ganhe tempo com o Pedido Rápido. Adicione múltiplos SKUs de uma vez ou importe sua planilha CSV.'),
                attachTo: {
                    element: '.b2b-quickorder-shortcut',
                    on: 'bottom'
                },
                buttons: [
                    {
                        text: $t('Anterior'),
                        action: tour.back
                    },
                    {
                        text: $t('Próximo'),
                        action: tour.next
                    }
                ]
            });
        }

        // Step 4: Subscriptions
        if ($('.b2b-subscriptions-shortcut').length) {
            tour.addStep({
                id: 'subscriptions',
                text: $t('Configure Assinaturas Recorrentes para seus itens de maior giro e nunca fique sem estoque.'),
                attachTo: {
                    element: '.b2b-subscriptions-shortcut',
                    on: 'top'
                },
                buttons: [
                    {
                        text: $t('Anterior'),
                        action: tour.back
                    },
                    {
                        text: $t('Próximo'),
                        action: tour.next
                    }
                ]
            });
        }

        // Final Step
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

        // Auto-start only once
        const tourCookie = 'awa_b2b_tour_seen';
        if (!$.mage.cookies.get(tourCookie)) {
            setTimeout(() => {
                tour.start();
                // Set cookie for 1 year
                const date = new Date();
                date.setTime(date.getTime() + (365 * 24 * 60 * 60 * 1000));
                $.mage.cookies.set(tourCookie, '1', { expires: date });
            }, 2000);
        }

        // Manual trigger
        $(document).on('click', '.trigger-b2b-tour', function (e) {
            e.preventDefault();
            tour.start();
        });
    };
});
