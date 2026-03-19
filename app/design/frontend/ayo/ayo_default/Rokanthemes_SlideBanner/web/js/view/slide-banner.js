/**
 * AWA Motos - Slide Banner UI Component
 *
 * Refatora o script inline do slider.phtml para um componente Magento UI.
 * Melhora a manutenibilidade, separação de responsabilidades e alinha com as
 * melhores práticas do Magento 2.
 */
define([
    'jquery',
    'rokanthemes/owl',
    'mage/mage'
], function ($) {
    'use strict';

    $.widget('awa.slideBanner', {
        options: {
            sliderConfig: {},
            totalSlides: 0
        },

        /**
         * @private
         */
        _create: function () {
            this.isPaused = false;
            this.progressInterval = null;
            this.isInitialized = false;

            // Validação: Se não há slides, não inicializa
            if (this.element.find('.banner_item').length === 0) {
                console.warn('[SlideBanner] No slides found. Skipping initialization.');
                this.element.find('.slide-controls').hide();
                return;
            }

            this._initSlider();
            this._enhanceA11y();
            this._initSuperLazyLoad();
            this._initControls();
            this.isInitialized = true;
        },

        /**
         * Inicializa o Owl Carousel com as configurações fornecidas.
         * @private
         */
        _initSlider: function () {
            var $owl = this.element.find('.owl');
            var sliderConfig = this.options.sliderConfig;

            // Validação: Verifica se owl-carousel está disponível
            if (!$owl.length || typeof $owl.owlCarousel !== 'function') {
                console.error('[SlideBanner] Owl Carousel library not loaded or .owl element not found.');
                return;
            }

            // Respeitar preferências do usuário (reduce motion)
            try {
                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    if (Object.prototype.hasOwnProperty.call(sliderConfig, 'autoPlay')) {
                        sliderConfig.autoPlay = false;
                    }
                    if (Object.prototype.hasOwnProperty.call(sliderConfig, 'autoplay')) {
                        sliderConfig.autoplay = false;
                    }
                }
            } catch (e) {
                console.error('[SlideBanner] Could not check for prefers-reduced-motion: ', e);
            }

            try {
                $owl.owlCarousel(sliderConfig);
            } catch (error) {
                console.error('[SlideBanner] Error initializing Owl Carousel:', error);
            }
        },

        /**
         * Inicializa os controles de Play/Pause e a barra de progresso.
         * @private
         */
        _initControls: function () {
            var self = this;
            var $owl = this.element.find('.owl');
            var $playPauseBtn = this.element.find('.play-pause-button');
            var $progressBar = this.element.find('.progress-bar');
            var autoPlay = this.options.sliderConfig.autoPlay || this.options.sliderConfig.autoplay;
            var autoPlayTimeout = this.options.sliderConfig.autoPlayTimeout || this.options.sliderConfig.autoplayTimeout || 5000;

            if (!autoPlay) {
                this.element.find('.slide-controls').hide();
                return;
            }

            function pauseSlider() {
                $owl.trigger('stop.owl.autoplay');
                self.isPaused = true;
                $playPauseBtn.attr('aria-label', $.mage.__('Iniciar slides'));
                $playPauseBtn.find('.icon-pause').hide();
                $playPauseBtn.find('.icon-play').show();
                clearInterval(self.progressInterval);
            }

            function playSlider() {
                $owl.trigger('play.owl.autoplay', [autoPlayTimeout]);
                self.isPaused = false;
                $playPauseBtn.attr('aria-label', $.mage.__('Pausar slides'));
                $playPauseBtn.find('.icon-play').hide();
                $playPauseBtn.find('.icon-pause').show();
                startProgress();
            }

            function startProgress() {
                clearInterval(self.progressInterval);
                $progressBar.css('width', '0%');
                var startTime = Date.now();

                self.progressInterval = setInterval(function () {
                    var elapsedTime = Date.now() - startTime;
                    var percentage = (elapsedTime / autoPlayTimeout) * 100;
                    $progressBar.css('width', percentage + '%');
                    if (percentage >= 100) {
                        clearInterval(self.progressInterval);
                    }
                }, 30);
            }

            $playPauseBtn.on('click', function () {
                if (self.isPaused) {
                    playSlider();
                } else {
                    pauseSlider();
                }
            });

            // Pausa ao focar nos controles
            this.element.on('focusin', 'a, button', pauseSlider);

            // Reinicia ao sair o foco (opcional, pode ser irritante)
            // this.element.on('focusout', 'a, button', playSlider);

            // Reinicia a barra de progresso na mudança de slide
            $owl.on('changed.owl.carousel', function(event) {
                if (!self.isPaused) {
                    startProgress();
                }
            });

            // Inicia a barra de progresso no carregamento
            startProgress();
        },

        /**
         * Implementa o "Super Lazy Load", carregando imagens apenas na interação.
         * @private
         */
        _initSuperLazyLoad: function () {
            var $owl = this.element.find('.owl');
            var self = this;

            // Aplica skeleton loading inicial em todas as imagens lazy
            this.element.find('img[data-src]').addClass('loading-skeleton lqip-blur');

            var lazyLoadImages = function (event) {
                // O evento pode ser de 'change' ou 'translate' dependendo da versão/config do Owl
                var $currentItem = $(event.target).find('.owl-item').eq(event.item.index);
                // Carrega atual + próximo + anterior + próximo do próximo (mais agressivo)
                var $itemsToLoad = $currentItem
                    .add($currentItem.next())
                    .add($currentItem.next().next())
                    .add($currentItem.prev());

                $itemsToLoad.each(function() {
                    var $item = $(this);
                    if ($item.data('lazy-loaded')) {
                        return;
                    }

                    var $picture = $item.find('picture');
                    var $sources = $picture.find('source[data-srcset]');
                    var $img = $picture.find('img[data-src]');

                    if ($sources.length > 0) {
                        $sources.each(function() {
                            var $source = $(this);
                            $source.attr('srcset', $source.data('srcset')).removeAttr('data-srcset');
                        });
                    }

                    if ($img.length > 0) {
                        var originalSrc = $img.data('src');
                        $img.attr('src', originalSrc).removeAttr('data-src');

                        $img.on('load', function() {
                            $(this).removeClass('lqip-blur loading-skeleton');
                        });

                        // Fallback: remove skeleton mesmo se load falhar
                        $img.on('error', function() {
                            $(this).removeClass('loading-skeleton');
                            console.warn('[SlideBanner] Failed to load image:', originalSrc);
                        });
                    }

                    $item.data('lazy-loaded', true);
                });
            };

            // Carrega o próximo e o anterior na mudança de slide
            $owl.on('changed.owl.carousel', lazyLoadImages);

            // Garante que o primeiro slide (que já é visível) não fique embaçado se tiver LQIP
            var $firstImg = $owl.find('.owl-item.active img.lqip-blur');
            if ($firstImg.length && !$firstImg.attr('data-src')) {
                 $firstImg.removeClass('lqip-blur loading-skeleton');
            }

            // Se a primeira imagem ainda está carregando, aguarda o load
            var $firstImgLazy = $owl.find('.owl-item.active img[data-src]');
            if ($firstImgLazy.length) {
                var firstSrc = $firstImgLazy.data('src');
                $firstImgLazy.attr('src', firstSrc).removeAttr('data-src');
                $firstImgLazy.on('load', function() {
                    $(this).removeClass('lqip-blur loading-skeleton');
                });
            }
        },

        /**
         * Melhora a acessibilidade (A11y) do carrossel.
         * @private
         */
        _enhanceA11y: function () {
            var $owl = this.element.find('.owl');
            var self = this;

            if (!$owl.length || typeof $owl.owlCarousel !== 'function') {
                return;
            }

            function applyState() {
                var $items = $owl.find('.owl-item');
                if (!$items.length) {
                    return;
                }

                // Atributos para itens visíveis/ocultos
                $items.each(function () {
                    var $item = $(this);
                    var isActive = $item.hasClass('active');
                    $item.attr('aria-hidden', isActive ? 'false' : 'true');

                    var $links = $item.find('a, button');
                    if ($links.length) {
                        $links.attr('tabindex', isActive ? '0' : '-1');
                    }
                });

                // Labels e roles para navegação
                var $prev = self.element.find('.owl-prev');
                var $next = self.element.find('.owl-next');
                $prev.attr({'aria-label': $.mage.__('Slide anterior'), 'role': 'button', 'tabindex': '0'});
                $next.attr({'aria-label': $.mage.__('Próximo slide'), 'role': 'button', 'tabindex': '0'});

                // Roles para paginação (dots) - Owl Carousel 1.x usa .owl-page, 2.x usa .owl-dot
                var $pagination = self.element.find('.owl-pagination, .owl-dots');
                if ($pagination.length) {
                    $pagination.attr({'role': 'tablist', 'aria-label': $.mage.__('Indicadores de slides')});
                }

                // Suporte para Owl Carousel 1.x (.owl-page)
                var $pages = self.element.find('.owl-page');
                $pages.each(function (index) {
                    var $page = $(this);
                    var isSelected = $page.hasClass('active');
                    $page.attr({
                        'role': 'tab',
                        'aria-label': $.mage.__('Ir para slide %1').replace('%1', index + 1),
                        'aria-selected': isSelected ? 'true' : 'false',
                        'tabindex': isSelected ? '0' : '-1'
                    });
                });

                // Suporte para Owl Carousel 2.x (.owl-dot)
                var $dots = self.element.find('.owl-dot');
                $dots.each(function (index) {
                    var $dot = $(this);
                    var isSelected = $dot.hasClass('active');
                    $dot.attr({
                        'role': 'tab',
                        'aria-label': $.mage.__('Ir para slide %1').replace('%1', index + 1),
                        'aria-selected': isSelected ? 'true' : 'false',
                        'tabindex': isSelected ? '0' : '-1'
                    });
                });
            }

            // Aplica os atributos de acessibilidade na inicialização e em cada mudança
            $owl.on('initialized.owl.carousel refreshed.owl.carousel changed.owl.carousel', function () {
                applyState();
            });

            // Permite navegação via teclado (Enter/Espaço)
            this.element.on('keydown', '.owl-prev, .owl-next, .owl-page, .owl-dot', function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    ev.preventDefault();
                    $(this).trigger('click');
                }
            });

            // Garante que o estado inicial seja aplicado
            applyState();
        },

        /**
         * Cleanup ao destruir o widget
         * @private
         */
        _destroy: function () {
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
                this.progressInterval = null;
            }

            var $owl = this.element.find('.owl');
            if ($owl.length && typeof $owl.trigger === 'function') {
                $owl.trigger('destroy.owl.carousel');
            }

            this.element.off();
        }
    });

    return $.awa.slideBanner;
});
