/**
 * AWA Motos — Slide Banner Widget (rokanthemes/slideBanner)
 *
 * Inicializado via data-mage-init pelo template Rokanthemes_SlideBanner/templates/slider.phtml.
 * Gerencia: Owl Carousel, barra de progresso animada, botão play/pause e atributos ARIA.
 *
 * Parâmetros recebidos (options):
 *   sliderConfig {Object} — opções do Owl Carousel 1.x (autoPlay, singleItem, etc.)
 *   totalSlides  {number} — total de slides para atualizar ARIA labels
 */
define([
    'jquery',
    'rokanthemes/owl'
], function ($) {
    'use strict';

    $.widget('rokanthemes.slideBanner', {
        options: {
            sliderConfig: {},
            totalSlides: 0
        },

        _rafId: null,
        _progressStart: 0,
        _progressDuration: 5000,
        _isPlaying: false,
        _$owl: null,

        _create: function () {
            var self    = this;
            var config  = $.extend({}, this.options.sliderConfig);
            var reduced = window.matchMedia &&
                          window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (reduced) {
                config.autoPlay = false;
            }

            this._isPlaying        = !!config.autoPlay;
            this._progressDuration = typeof config.autoPlayTimeout === 'number'
                ? config.autoPlayTimeout
                : 5000;

            var $owl = this.element.find('.owl');
            this._$owl = $owl;

            $owl.owlCarousel($.extend({}, config, {
                afterInit: function (elem) {
                    self._applyA11y(elem);
                    if (self._isPlaying) {
                        self._startProgress();
                    }
                },
                afterMove: function (elem) {
                    self._applyA11y(elem);
                    if (self._isPlaying) {
                        self._resetProgress();
                    }
                }
            }));

            this.element.find('.play-pause-button')
                .on('click.slideBanner', function () {
                    self._togglePlayPause();
                });
        },

        _applyA11y: function (elem) {
            elem.find('.owl-page, .owl-dot').each(function (index) {
                var $dot       = $(this);
                var isSelected = $dot.hasClass('active');

                $dot.attr({
                    'role':          'tab',
                    'aria-label':    $.mage.__('Ir para slide') + ' ' + (index + 1),
                    'aria-selected': isSelected ? 'true' : 'false',
                    'tabindex':      isSelected ? '0' : '-1'
                });
            });
        },

        _startProgress: function () {
            var self = this;
            var $bar = this.element.find('.progress-bar');

            this._progressStart = performance.now();

            var tick = function (now) {
                var elapsed = now - self._progressStart;
                var pct     = Math.min(elapsed / self._progressDuration * 100, 100);

                $bar.css('width', pct + '%');

                if (pct < 100) {
                    self._rafId = requestAnimationFrame(tick);
                }
            };

            this._rafId = requestAnimationFrame(tick);
        },

        _resetProgress: function () {
            if (this._rafId) {
                cancelAnimationFrame(this._rafId);
                this._rafId = null;
            }
            this.element.find('.progress-bar').css('width', '0%');
            this._startProgress();
        },

        _stopProgress: function () {
            if (this._rafId) {
                cancelAnimationFrame(this._rafId);
                this._rafId = null;
            }
            this.element.find('.progress-bar').css('width', '0%');
        },

        _togglePlayPause: function () {
            var $btn       = this.element.find('.play-pause-button');
            var $iconPause = $btn.find('.icon-pause');
            var $iconPlay  = $btn.find('.icon-play');
            var owl        = this._$owl.data('owlCarousel');

            if (this._isPlaying) {
                if (owl) {
                    owl.stop();
                }
                this._stopProgress();
                this._isPlaying = false;

                $iconPause.hide();
                $iconPlay.removeClass('icon-play--hidden').show();
                $btn.attr('aria-label', $.mage.__('Reproduzir slides'));
                this.element.attr('aria-live', 'polite');
            } else {
                if (owl) {
                    owl.play();
                }
                this._startProgress();
                this._isPlaying = true;

                $iconPause.show();
                $iconPlay.addClass('icon-play--hidden').hide();
                $btn.attr('aria-label', $.mage.__('Pausar slides'));
                this.element.attr('aria-live', 'off');
            }
        },

        _destroy: function () {
            this._stopProgress();
            this.element.find('.play-pause-button').off('.slideBanner');

            if (this._$owl) {
                var owl = this._$owl.data('owlCarousel');
                if (owl && typeof owl.destroy === 'function') {
                    owl.destroy();
                }
            }
        }
    });

    return $.rokanthemes.slideBanner;
});
