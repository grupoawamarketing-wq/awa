;(function($, window, document, undefined) {
    $.fn.VerticalMenu = function() {
        var $nav = $(this);
        var isSideMenu = $nav.hasClass('side-verticalmenu');

        /* --------------------------------------------------------
         *  Classic child‑submenu positioning (nested levels)
         * ------------------------------------------------------ */
        $nav.find("li.classic .subchildmenu > li.parent").on("mouseenter", function(){
            var $popup = $(this).children("ul.subchildmenu");
            var wWidth = $(window).innerWidth();
            if ($popup.length) {
                var pos = $(this).offset();
                var cWidth = $popup.outerWidth();
                if (wWidth <= pos.left + $(this).outerWidth() + cWidth) {
                    $popup.css({"left": "auto", "right": "100%", "border-radius": "6px 0 6px 6px"});
                } else {
                    $popup.css({"left": "100%", "right": "auto", "border-radius": "0 6px 6px 6px"});
                }
            }
        });

        /* --------------------------------------------------------
         *  Static / classic parent submenu — non‑side menus only
         * ------------------------------------------------------ */
        if (!isSideMenu) {
            $nav.find("li.staticwidth.parent, li.classic.parent").on("mouseenter", function(){
                var $popup = $(this).children(".submenu");
                var wWidth = $(window).innerWidth();
                var wHeight = $(window).innerHeight();
                if ($popup.length) {
                    var pos = $(this).offset();
                    var cWidth = $popup.outerWidth();
                    var cHeight = $popup.outerHeight();
                    if (wWidth <= pos.left + $(this).outerWidth() + cWidth) {
                        $popup.css({"left": "auto", "right": "0", "border-radius": "6px 0 6px 6px"});
                    } else {
                        $popup.css({"left": "0", "right": "auto", "border-radius": "0 6px 6px 6px"});
                    }
                    var scrollTop = $(window).scrollTop();
                    var topRelat = pos.top - scrollTop;
                    if (topRelat + cHeight > wHeight) {
                        var maxTop = Math.max(0, wHeight - cHeight - 10);
                        $popup.css({"top": (maxTop - topRelat + scrollTop) + "px"});
                    } else {
                        $popup.css({"top": ""});
                    }
                }
            });
        } else {
            /* --------------------------------------------------------
             *  Side vertical menu — portal pattern v2 para fly-outs
             *
             *  Chrome/Blink recorta overflow dos nós position:fixed na
             *  sua própria camada GPU. Solução: mover cada submenu para
             *  um portal div diretamente em <body> ao abrir, restaurar
             *  ao fechar.
             *
             *  Correções vs v1:
             *  - relatedTarget: cursor pode entrar no popup sem fechar
             *  - Exclusão mútua: só um submenu no portal por vez
             *  - Reposicionamento no scroll enquanto portal ativo
             *  - Limpeza no resize para evitar orphans no portal
             *  - z-index no div wrapper do portal
             *  - querySelector global evita portais duplicados
             * ------------------------------------------------------ */

            function getSidePortal() {
                var existing = document.querySelector('.awa-side-submenu-portal');

                if (existing) { return $(existing); }

                return $('<div class="awa-side-submenu-portal"' +
                    ' style="position:absolute;top:0;left:0;width:0;height:0;overflow:visible;z-index:99997;"' +
                    ' aria-hidden="true"></div>').appendTo('body');
            }

            /* ID estável por nav para namespaces de scroll únicos */
            var sideNavSid = ($nav.attr('id') || 'vmside').replace(/[^a-zA-Z0-9]/g, '');

            /* Item ativo no momento — exclusão mútua */
            var $awaSideCurrentLi = null;

            /* Atribui índice único a cada item para namespace de scroll */
            var sideItemCount = 0;
            $nav.find('li.level0.parent').each(function () {
                $(this).data('awaSideIdx', sideNavSid + '-' + (sideItemCount++));
            });

            function calcSideCoords(li, $popup) {
                var r      = li.getBoundingClientRect();
                var wW     = $(window).innerWidth();
                var wH     = $(window).innerHeight();
                var cW     = Math.max($popup.outerWidth() || 0, 550);
                var cH     = $popup.outerHeight() || 400;
                var left   = r.right;
                var top    = r.top;
                var radius;

                if (left + cW > wW) {
                    left   = Math.max(0, r.left - cW);
                    radius = '6px 0 0 6px';
                } else {
                    radius = '0 6px 6px 6px';
                }

                if (top + cH > wH) { top = Math.max(0, wH - cH - 10); }

                return { left: left, top: top, radius: radius };
            }

            function applySideCoords($popup, coords) {
                $popup.css({
                    'position':      'fixed',
                    'left':          coords.left + 'px',
                    'top':           coords.top  + 'px',
                    'right':         'auto',
                    'z-index':       '99998',
                    'border-radius': coords.radius,
                    'visibility':    'visible',
                    'opacity':       '1',
                    'transform':     'translateX(0)'
                });
            }

            function resetSideStyle($popup) {
                $popup.css({
                    'position': '', 'left': '', 'top': '', 'right': '',
                    'z-index': '', 'border-radius': '', 'visibility': '',
                    'opacity': '', 'transform': ''
                });
            }

            function restoreToDOM(li, popup) {
                $.data(li, 'awaSideCloseTimer', null);

                var origParent = $.data(popup, 'awaSideOrigParent');
                var origAnchor = $.data(popup, 'awaSideOrigAnchor');

                if (origParent && popup.parentNode !== origParent) {
                    if (origAnchor && origAnchor.parentNode === origParent) {
                        origParent.insertBefore(popup, origAnchor);
                    } else {
                        origParent.appendChild(popup);
                    }
                }
            }

            function beginSideClose(li, popup) {
                var idx = $(li).data('awaSideIdx');

                $(window).off('scroll.awaSideRepos-' + idx);
                $(popup).off('mouseenter.awaSide mouseleave.awaSide');

                var pending = $.data(li, 'awaSideCloseTimer');

                if (pending) { clearTimeout(pending); }

                /* Esconder explicitamente: no contexto do portal a CSS base do
                 * .togge-menu não se aplica, então visibility:'' não oculta. */
                $(popup).css({ 'visibility': 'hidden', 'opacity': '0' });

                var t = setTimeout(function () {
                    resetSideStyle($(popup));
                    restoreToDOM(li, popup);

                    if ($awaSideCurrentLi === li) { $awaSideCurrentLi = null; }
                }, 250);

                $.data(li, 'awaSideCloseTimer', t);
            }

            $nav.find('li.level0.parent')
                .on('mouseenter', function (e) {
                    var li    = this;
                    var $li   = $(this);
                    var idx   = $li.data('awaSideIdx');

                    /* Exclusão mútua: fechar o item irmão eventualmente aberto */
                    if ($awaSideCurrentLi && $awaSideCurrentLi !== li) {
                        var prevPopup = $.data($awaSideCurrentLi, 'awaSidePopup');

                        if (prevPopup) { beginSideClose($awaSideCurrentLi, prevPopup); }
                    }

                    $awaSideCurrentLi = li;

                    /* Obter / cachear popup */
                    var popup = $.data(li, 'awaSidePopup');

                    if (!popup) {
                        popup = $li.children('.submenu')[0];

                        if (!popup) { return; }

                        $.data(li, 'awaSidePopup',        popup);
                        $.data(popup, 'awaSideOwner',     li);
                        $.data(popup, 'awaSideOrigParent', popup.parentNode);
                        $.data(popup, 'awaSideOrigAnchor', popup.nextSibling);
                    }

                    var $popup = $(popup);

                    /* Cancelar timer de fechar pendente */
                    var pending = $.data(li, 'awaSideCloseTimer');

                    if (pending) { clearTimeout(pending); $.data(li, 'awaSideCloseTimer', null); }

                    /* Mover para portal e posicionar */
                    getSidePortal()[0].appendChild(popup);
                    applySideCoords($popup, calcSideCoords(li, $popup));

                    /* Hover no popup: cancelar fechar ao entrar, fechar ao sair */
                    $popup.off('mouseenter.awaSide mouseleave.awaSide')
                        .on('mouseenter.awaSide', function () {
                            var t = $.data(li, 'awaSideCloseTimer');

                            if (t) { clearTimeout(t); $.data(li, 'awaSideCloseTimer', null); }
                        })
                        .on('mouseleave.awaSide', function (ev) {
                            /* Não fechar se cursor voltou para o li dono */
                            if (ev.relatedTarget &&
                                    (li === ev.relatedTarget || li.contains(ev.relatedTarget))) {
                                return;
                            }

                            beginSideClose(li, popup);
                        });

                    /* Reposicionar no scroll enquanto popup estiver no portal */
                    $(window).off('scroll.awaSideRepos-' + idx)
                        .on('scroll.awaSideRepos-' + idx, function () {
                            if (popup.parentNode && popup.parentNode.classList &&
                                    popup.parentNode.classList.contains('awa-side-submenu-portal')) {
                                applySideCoords($popup, calcSideCoords(li, $popup));
                            } else {
                                $(window).off('scroll.awaSideRepos-' + idx);
                            }
                        });
                })
                .on('mouseleave', function (e) {
                    var li    = this;
                    var popup = $.data(li, 'awaSidePopup');

                    if (!popup) { return; }

                    /* Não fechar se cursor entrou no popup do portal */
                    if (e.relatedTarget &&
                            (popup === e.relatedTarget || popup.contains(e.relatedTarget))) {
                        return;
                    }

                    beginSideClose(li, popup);
                });

            /* Limpeza no resize: restaurar todos os orphans no portal */
            $(window).on('resize.awaSideCleanup', function () {
                var orphans = document.querySelectorAll('.awa-side-submenu-portal > *');

                Array.prototype.forEach.call(orphans, function (popup) {
                    var li = $.data(popup, 'awaSideOwner');

                    if (!li) { return; }

                    var t = $.data(li, 'awaSideCloseTimer');

                    if (t) { clearTimeout(t); $.data(li, 'awaSideCloseTimer', null); }

                    $(popup).off('mouseenter.awaSide mouseleave.awaSide');
                    resetSideStyle($(popup));
                    restoreToDOM(li, popup);
                });

                $awaSideCurrentLi = null;
            });
        }

        /* --------------------------------------------------------
         *  Reset on resize
         * ------------------------------------------------------ */
        var resizeTimer;
        $(window).on("resize", function(){
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function(){
                if (!isSideMenu) {
                    $nav.find("li.classic .submenu, li.staticwidth .submenu, li.classic .subchildmenu .subchildmenu").each(function(){
                        $(this).css({"left": "", "right": "", "top": ""});
                    });
                }
            }, 150);
        });

        /* --------------------------------------------------------
         *  Mobile: open‑children‑toggle for level 0
         * ------------------------------------------------------ */
        $nav.find("li.ui-menu-item > .open-children-toggle").off("click").on("click", function(e){
            e.preventDefault();
            e.stopPropagation();
            var $parent = $(this).parent();
            var $submenu = $parent.children(".submenu");
            var $link = $parent.children("a");
            var isOpen = $submenu.hasClass("opened");

            $parent.siblings().children(".submenu").removeClass("opened");
            $parent.siblings().children("a").removeClass("ui-state-active");

            if (!isOpen) {
                $submenu.addClass("opened");
                $link.addClass("ui-state-active");
            } else {
                $submenu.removeClass("opened");
                $link.removeClass("ui-state-active");
            }
        });

        /* --------------------------------------------------------
         *  Mobile: subchild submenu toggle
         * ------------------------------------------------------ */
        $nav.find(".submenu .subchildmenu li.ui-menu-item > .open-children-toggle").off("click").on("click", function(e){
            e.preventDefault();
            e.stopPropagation();
            var $parent = $(this).parent();
            var $sub = $parent.children(".subchildmenu");
            var $link = $parent.children("a");
            if (!$sub.hasClass("opened")) {
                $sub.addClass("opened").slideDown(200);
                $link.addClass("ui-state-active");
            } else {
                $sub.removeClass("opened").slideUp(200);
                $link.removeClass("ui-state-active");
            }
        });
    };
})(window.Zepto || window.jQuery, window, document); 