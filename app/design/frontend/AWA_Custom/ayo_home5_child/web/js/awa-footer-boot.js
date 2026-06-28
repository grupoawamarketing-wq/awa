(function (w, d) {
    'use strict';

    function syncFooterToggleAria() {
        var mobile = w.matchMedia('(max-width: 767px)').matches;
        d.querySelectorAll('button.awa-footer-section__toggle').forEach(function (btn) {
            btn.setAttribute('aria-expanded', mobile ? 'false' : 'true');
        });
    }

    syncFooterToggleAria();
    if (typeof w.matchMedia === 'function') {
        var mq = w.matchMedia('(max-width: 767px)');
        if (typeof mq.addEventListener === 'function') {
            mq.addEventListener('change', syncFooterToggleAria);
        } else if (typeof mq.addListener === 'function') {
            mq.addListener(syncFooterToggleAria);
        }
    }

    var bootStarted = false;

    function bootFooterInteractions() {
        if (bootStarted || w.__awaFooterInteractionsHomeInit) {
            return;
        }

        var footer = d.querySelector('.page_footer, .page-footer');
        var cfgNode = d.getElementById('awa-footer-interactions-config-json');
        if (!footer || !cfgNode || !cfgNode.textContent) {
            return;
        }

        bootStarted = true;

        var run = function () {
            if (footer.getAttribute('data-awa-footer-interactions-boot') === '1') {
                return;
            }

            w.require(['awaFooterInteractions'], function (footerInteractions) {
                var config;
                try {
                    config = JSON.parse(cfgNode.textContent);
                } catch (e) {
                    return;
                }

                footer.setAttribute('data-awa-footer-interactions-boot', '1');
                footerInteractions(config, footer);
            });
        };

        if (typeof w.awaRunWhenRequire === 'function') {
            w.awaRunWhenRequire(run, { key: 'footer-interactions-critical' });
        } else if (typeof w.require === 'function' && !w.require._awaStub) {
            run();
        } else {
            var pollAttempts = 0;
            var pollTimer = w.setInterval(function () {
                pollAttempts += 1;
                if (typeof w.require === 'function' && !w.require._awaStub) {
                    w.clearInterval(pollTimer);
                    run();
                    return;
                }
                if (pollAttempts >= 80) {
                    w.clearInterval(pollTimer);
                }
            }, 250);
        }
    }

    function scheduleFooterBoot() {
        var footer = d.querySelector('.page_footer, .page-footer');
        if (!footer) {
            return;
        }

        footer.addEventListener('click', function (evt) {
            if (!evt.target || !evt.target.closest) {
                return;
            }
            if (evt.target.closest('.awa-footer-section__toggle, .awa-footer-categories-expand__toggle')) {
                bootFooterInteractions();
            }
        }, { capture: true, passive: true });

        if ('IntersectionObserver' in w) {
            var observer = new w.IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        return;
                    }
                    observer.disconnect();
                    bootFooterInteractions();
                });
            }, { rootMargin: '320px 0px 0px 0px' });
            observer.observe(footer);
            return;
        }

        bootFooterInteractions();
    }

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', scheduleFooterBoot, { once: true });
    } else {
        scheduleFooterBoot();
    }
}(window, document));
