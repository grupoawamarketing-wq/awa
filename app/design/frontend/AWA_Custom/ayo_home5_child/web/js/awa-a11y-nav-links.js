(function (w, d) {
    'use strict';

    var cfgNode = d.getElementById('awa-a11y-nav-config');
    var cfg = {};
    if (cfgNode && cfgNode.textContent) {
        try {
            cfg = JSON.parse(cfgNode.textContent);
        } catch (e) {
            cfg = {};
        }
    }
    var isHome = !!cfg.isHome;

    var observer = null;
    var booted = false;

    function slugToLabel(href) {
        var slug = (href || '').replace(/^.*\//, '').replace(/\.html$/i, '');
        return slug ? slug.replace(/-/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }) : null;
    }

    function fixNavImageLinks(root) {
        var scope = root && root.querySelectorAll ? root : d;
        var links = scope.querySelectorAll('a.navigation__inner-link');
        links.forEach(function (link) {
            var txt = (link.textContent || '').trim();
            if (txt || link.getAttribute('aria-label') || link.getAttribute('aria-labelledby')) {
                return;
            }
            var img = link.querySelector('img');
            if (!img) {
                return;
            }

            var catName = null;
            var panel = link.closest('[class*="navigation__inner"], .awa-vmf-portal, .level0.submenu');
            if (panel) {
                var titleEl = panel.querySelector('[class*="subcategory-title"] span, [class*="subcategory-title"] a, [class*="nav-title"]');
                if (titleEl) {
                    catName = (titleEl.textContent || '').trim() || null;
                }
            }
            if (!catName) {
                catName = slugToLabel(link.getAttribute('href'));
            }

            if (catName) {
                link.setAttribute('aria-label', catName);
                if (!img.getAttribute('alt')) {
                    img.setAttribute('alt', catName);
                }
            }
        });
    }

    function fixNavLinks(root) {
        var scope = root && root.querySelectorAll ? root : d;
        var links = scope.querySelectorAll('a.navigation__inner-link');

        links.forEach(function (link) {
            var txt = (link.textContent || '').trim().toLowerCase();
            if (txt !== 'ver tudo' && txt !== 'ver todos') {
                return;
            }
            if (link.getAttribute('aria-label') || link.getAttribute('aria-labelledby')) {
                return;
            }

            var catName = null;
            var panel = link.closest(
                '.navigation__inner-item--all,' +
                '.navigation__inner-list--level1,' +
                '.awa-vmf-portal,' +
                '.level0.submenu,' +
                '[class*="navigation__inner"]'
            );

            if (panel) {
                var imgEl = panel.querySelector('a.navigation__inner-link img[alt]');
                if (imgEl && imgEl.alt) {
                    catName = imgEl.alt.trim();
                }
            }

            if (!catName && panel) {
                var titleEl = panel.querySelector('[class*="subcategory-title"] span,[class*="subcategory-title"] a');
                if (titleEl && titleEl.textContent) {
                    catName = titleEl.textContent.trim();
                }
            }

            if (!catName) {
                catName = slugToLabel(link.getAttribute('href'));
            }

            if (catName) {
                link.setAttribute('aria-label', 'Ver todos: ' + catName);
            }
        });
    }

    function runAll(root) {
        var scope = root || d;
        fixNavImageLinks(scope);
        fixNavLinks(scope);
    }

    function watchPortals() {
        if (!w.MutationObserver || observer) {
            return;
        }

        observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (!node || node.nodeType !== 1) {
                        return;
                    }
                    if (
                        (node.classList && (node.classList.contains('awa-vmf-portal') || String(node.className).includes('navigation__inner'))) ||
                        (node.querySelector && node.querySelector('a.navigation__inner-link'))
                    ) {
                        runAll(node);
                    }
                });
            });
        });

        observer.observe(d.body || d.documentElement, { childList: true, subtree: true });
        w.setTimeout(function () {
            if (observer) {
                observer.disconnect();
                observer = null;
            }
        }, 10000);
    }

    function bootNavA11y() {
        if (booted) {
            return;
        }
        booted = true;
        runAll(d);
        w.setTimeout(function () { runAll(d); }, 400);
        watchPortals();
    }

    if (isHome) {
        var homeNavIntent = ['pointerdown', 'keydown', 'touchstart'];

        function onHomeNavIntent(ev) {
            var target = ev && ev.target;
            if (target && target.closest && target.closest('.block-vertical-nav, .block-vmenu, .navigation, .nav-sections, .header-control, .awa-nav-bar')) {
                homeNavIntent.forEach(function (name) {
                    w.removeEventListener(name, onHomeNavIntent, true);
                });
                bootNavA11y();
            }
        }

        homeNavIntent.forEach(function (name) {
            d.addEventListener(name, onHomeNavIntent, { capture: true, passive: name !== 'keydown' });
        });
        return;
    }

    function runDefault() {
        runAll(d);
        w.setTimeout(function () { runAll(d); }, 200);
        w.setTimeout(function () { runAll(d); }, 800);
        watchPortals();
    }

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', runDefault, { once: true });
    } else {
        runDefault();
    }
}(window, document));
