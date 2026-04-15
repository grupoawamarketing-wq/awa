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
            /* Side vertical menu — submenu fly-out positioning.
             *
             * IMPORTANT: Chrome/Blink compositor clips overflow content of
             * position:fixed elements to their own GPU layer bounds, even when
             * overflow:visible is set. A position:absolute or position:fixed child
             * inside a position:fixed togge-menu therefore does NOT paint outside the
             * togge-menu bounds.
             *
             * Solution: "portal" — move the submenu element into a dedicated div
             * appended directly to <body> before showing it, then restore it when
             * hiding. From body context, position:fixed is truly viewport-relative
             * and paints at the correct position above all page content.
             */
            var $awaSidePortal = null;

            function getSidePortal() {
                if (!$awaSidePortal || !document.body.contains($awaSidePortal[0])) {
                    $awaSidePortal = $('<div class="awa-side-submenu-portal" style="position:absolute;top:0;left:0;width:0;height:0;overflow:visible;" aria-hidden="true"></div>').appendTo('body');
                }

                return $awaSidePortal;
            }

            $nav.find("li.level0.parent")
                .on("mouseenter", function () {
                    var $item  = $(this);
                    /* Look for cached popup first (may have been moved to portal) */
                    var popup = $.data(this, 'awaSidePopup');

                    if (!popup) {
                        popup = $item.children(".submenu")[0];

                        if (!popup) { return; }

                        /* Cache metadata for restore on mouseleave */
                        $.data(this, 'awaSidePopup', popup);
                        $.data(popup, 'awaSideOwner', this);
                        $.data(popup, 'awaSideOrigParent', popup.parentNode);
                        $.data(popup, 'awaSideOrigAnchor', popup.nextSibling);
                    }

                    var $popup = $(popup);

                    /* Cancel any pending close/restore timer */
                    var pending = $.data(this, 'awaSideCloseTimer');

                    if (pending) {
                        clearTimeout(pending);
                        $.data(this, 'awaSideCloseTimer', null);
                    }

                    var itemRect = this.getBoundingClientRect();
                    var wWidth   = $(window).innerWidth();
                    var wHeight  = $(window).innerHeight();
                    var cWidth   = Math.max($popup.outerWidth() || 0, 550);
                    var cHeight  = $popup.outerHeight() || 400;

                    var left = itemRect.right;
                    var top  = itemRect.top;
                    var radius;

                    if (left + cWidth > wWidth) {
                        left   = Math.max(0, itemRect.left - cWidth);
                        radius = "6px 0 0 6px";
                    } else {
                        radius = "0 6px 6px 6px";
                    }

                    if (top + cHeight > wHeight) {
                        top = Math.max(0, wHeight - cHeight - 10);
                    }

                    /* Move to portal (body context) so position:fixed is viewport-relative */
                    getSidePortal()[0].appendChild(popup);

                    $popup.css({
                        "position":      "fixed",
                        "left":          left + "px",
                        "top":           top  + "px",
                        "right":         "auto",
                        "z-index":       "99998",
                        "border-radius": radius,
                        "visibility":    "visible",
                        "opacity":       "1",
                        "transform":     "translateX(0)"
                    });
                })
                .on("mouseleave", function () {
                    var popup = $.data(this, 'awaSidePopup');

                    if (!popup) {
                        popup = $(this).children(".submenu")[0];
                    }

                    if (!popup) { return; }

                    var $popup = $(popup);
                    var li     = this;

                    /* Reset CSS — CSS transition (opacity/visibility) plays out */
                    $popup.css({
                        "position":      "",
                        "left":          "",
                        "top":           "",
                        "right":         "",
                        "z-index":       "",
                        "border-radius": "",
                        "visibility":    "",
                        "opacity":       "",
                        "transform":     ""
                    });

                    /* Restore to original DOM position after transition ends (~160ms) */
                    var closeTimer = setTimeout(function () {
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
                    }, 250);

                    $.data(li, 'awaSideCloseTimer', closeTimer);
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