define([
    'jquery',
    'Magento_Theme/js/model/breadcrumb-list'
], function ($, breadcrumbList) {
    'use strict';

    return function (widget) {
        $.widget('mage.breadcrumbs', widget, {
            options: {
                categoryUrlSuffix: '',
                useCategoryPathInUrl: false,
                product: '',
                categoryItemSelector: '.navigation__item',
                menuContainer: 'nav.navigation',
                mobileBreakpoint: 640,
                compactProductLabelLength: 58
            },

            /**
             * Bind a lightweight resize listener once so the breadcrumb can
             * switch between full and compact mobile states without rerendering.
             *
             * @private
             */
            _create: function () {
                var self = this;

                this._super();

                if (this._awaResizeNamespace) {
                    return;
                }

                this._awaResizeNamespace = '.awaPdpBreadcrumbs' + this.uuid;

                $(window).on(
                    'resize' + this._awaResizeNamespace + ' orientationchange' + this._awaResizeNamespace,
                    function () {
                        self._applyResponsiveCompaction();
                    }
                );
            },

            /** @inheritdoc */
            _destroy: function () {
                if (this._awaResizeNamespace) {
                    $(window).off(this._awaResizeNamespace);
                }

                this._super();
            },

            /** @inheritdoc */
            _render: function () {
                this._appendCatalogCrumbs();
                this._super();
                this._applyResponsiveCompaction();
            },

            /**
             * Append category and product crumbs.
             *
             * @private
             */
            _appendCatalogCrumbs: function () {
                var categoryCrumbs = this._resolveCategoryCrumbs();

                categoryCrumbs.forEach(function (crumbInfo) {
                    breadcrumbList.push(crumbInfo);
                });

                if (this.options.product) {
                    breadcrumbList.push(this._getProductCrumb());
                }
            },

            /**
             * Resolve categories crumbs.
             *
             * @return {Array}
             * @private
             */
            _resolveCategoryCrumbs: function () {
                var menuItem = this._resolveCategoryMenuItem(),
                    categoryCrumbs = [];

                if (menuItem !== null && menuItem.length) {
                    categoryCrumbs.unshift(this._getCategoryCrumb(menuItem));

                    while ((menuItem = this._getParentMenuItem(menuItem)) !== null) {
                        categoryCrumbs.unshift(this._getCategoryCrumb(menuItem));
                    }
                }

                return categoryCrumbs;
            },

            /**
             * Returns crumb data.
             *
             * @param {Object} menuItem
             * @return {Object}
             * @private
             */
            _getCategoryCrumb: function (menuItem) {
                return {
                    name: 'category',
                    label: menuItem.find(".navigation__label").text() || menuItem.clone().children().remove().end().text().trim(),
                    link: menuItem.attr('href'),
                    title: ''
                };
            },

            /**
             * Returns product crumb.
             *
             * @return {Object}
             * @private
             */
            _getProductCrumb: function () {
                return {
                    name: 'product',
                    label: this.options.product,
                    link: '',
                    title: ''
                };
            },

            /**
             * Find parent menu item for current.
             *
             * @param {Object} menuItem
             * @return {Object|null}
             * @private
             */
            _getParentMenuItem: function (menuItem) {
                var classes,
                    classNav,
                    parentClass,
                    parentMenuItem = null;

                if (!menuItem) {
                    return null;
                }

                classes = menuItem.parent().attr('class');
                classNav = classes.match(/(nav\-)[0-9]+(\-[0-9]+)+/gi);

                if (classNav) {
                    classNav = classNav[0];
                    parentClass = classNav.substr(0, classNav.lastIndexOf('-'));

                    if (parentClass.lastIndexOf('-') !== -1) {
                        parentMenuItem = $(this.options.menuContainer).find('.' + parentClass + ' > a');
                        parentMenuItem = parentMenuItem.length ? parentMenuItem : null;
                    }
                }

                return parentMenuItem;
            },

            /**
             * Returns category menu item.
             *
             * Tries to resolve category from url or from referrer as fallback and
             * find menu item from navigation menu by category url.
             *
             * @return {Object|null}
             * @private
             */
            _resolveCategoryMenuItem: function () {
                var categoryUrl = this._resolveCategoryUrl(),
                    menu = $(this.options.menuContainer),
                    categoryMenuItem = null;

                if (categoryUrl && menu.length) {
                    categoryMenuItem = menu.find(
                        this.options.categoryItemSelector +
                        ' > a[href="' + categoryUrl + '"]'
                    );
                }

                return categoryMenuItem;
            },

            /**
             * Returns category url.
             *
             * @return {String}
             * @private
             */
            _resolveCategoryUrl: function () {
                var categoryUrl;

                if (this.options.useCategoryPathInUrl) {
                    categoryUrl = window.location.href.split('?')[0];
                    categoryUrl = categoryUrl.substring(0, categoryUrl.lastIndexOf('/')) +
                        this.options.categoryUrlSuffix;
                } else {
                    categoryUrl = document.referrer;

                    if (categoryUrl.indexOf('?') > 0) {
                        categoryUrl = categoryUrl.substr(0, categoryUrl.indexOf('?'));
                    }
                }

                return categoryUrl;
            },

            /**
             * Collapse middle crumbs on small screens and shorten only the
             * visible product label, while keeping the full text in attributes.
             *
             * @private
             */
            _applyResponsiveCompaction: function () {
                var $items = this.element.find('.items'),
                    $crumbs,
                    $visibleCrumbs;

                if (!$('body').hasClass('catalog-product-view') || !$items.length) {
                    return;
                }

                this._restoreResponsiveState($items);
                $crumbs = $items.children('.item');

                if (!this._isCompactViewport() || !$crumbs.length) {
                    this.element.removeClass('awa-breadcrumbs-compact');
                    return;
                }

                this.element.addClass('awa-breadcrumbs-compact');

                if ($crumbs.length > 3) {
                    $crumbs
                        .slice(1, $crumbs.length - 2)
                        .addClass('awa-breadcrumbs-mobile-hidden');

                    $('<li/>', {
                        'class': 'item awa-breadcrumbs-ellipsis',
                        'aria-hidden': 'true',
                        'text': '...'
                    }).insertAfter($crumbs.first());
                }

                $visibleCrumbs = $items.children('.item').not('.awa-breadcrumbs-mobile-hidden');
                this._truncateVisibleCrumb($visibleCrumbs.last());
            },

            /**
             * Restore the full breadcrumb before applying mobile compaction again.
             *
             * @param {jQuery} $items
             * @private
             */
            _restoreResponsiveState: function ($items) {
                var $lastLabel = $items.children('.item').last().find('strong, a, [itemprop="name"]').first(),
                    fullLabel = '';

                $items.children('.awa-breadcrumbs-ellipsis').remove();
                $items.children('.item').removeClass('awa-breadcrumbs-mobile-hidden');

                if ($lastLabel.length) {
                    fullLabel = $lastLabel.attr('data-awa-full-label') || '';

                    if (fullLabel) {
                        $lastLabel.text(fullLabel);
                    }
                }
            },

            /**
             * Keep the full label for accessibility/tooltips while shortening the
             * visual label on compact mobile layouts.
             *
             * @param {jQuery} $crumb
             * @private
             */
            _truncateVisibleCrumb: function ($crumb) {
                var $label = $crumb.find('strong, a, [itemprop="name"]').first(),
                    fullLabel,
                    maxLength,
                    shortLabel;

                if (!$label.length) {
                    return;
                }

                fullLabel = $.trim($label.attr('data-awa-full-label') || $label.text());

                if (!fullLabel) {
                    return;
                }

                $label.attr('data-awa-full-label', fullLabel);
                $label.attr('title', fullLabel);
                $label.attr('aria-label', fullLabel);

                maxLength = parseInt(this.options.compactProductLabelLength, 10) || 58;
                shortLabel = fullLabel;

                if (fullLabel.length > maxLength) {
                    shortLabel = $.trim(fullLabel.slice(0, maxLength - 1))
                        .replace(/[\s\-\/]+$/, '') + '...';
                }

                $label.text(shortLabel);
            },

            /**
             * Check whether the viewport should use the compact breadcrumb state.
             *
             * @return {Boolean}
             * @private
             */
            _isCompactViewport: function () {
                return window.innerWidth <= this.options.mobileBreakpoint;
            }
        });

        return $.mage.breadcrumbs;
    };
});
