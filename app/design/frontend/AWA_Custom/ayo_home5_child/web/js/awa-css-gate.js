/**
 * AWA — CSS Interaction Gate
 *
 * Aplica links CSS com data-awa-gate="1" (media="print" → "all")
 * e injeta fila #awa-css-gate-queue de forma progressiva.
 *
 * Home: cosmético via requestIdleCallback pós-load (sem reflow no 1º clique).
 * Interação acelera a fila; clique em área vazia não dispara o gate.
 * Outras rotas: fallback pós-load curto.
 */
(function () {
    'use strict';

    var CSS_GATE_ATTR = 'data-awa-gate';
    var applied = false;
    var GATE_EVENTS = ['pointerdown', 'keydown', 'touchstart'];
    var QUEUE_WATCHDOG_MS = 30000;
    var MOBILE_FALLBACK_DELAY_MS = 1200;
    var DESKTOP_FALLBACK_DELAY_MS = 1200;
    var HOME_INTERACTION_FALLBACK_MS = 12000; /* opt18: gate só após idle longo — evita freeze aos ~5s */
    var HOME_STYLES_M_DELAY_MS = 4500; /* opt18: 2.8s→4.5s — styles-m 3.7MB entra mais tarde */
    var HOME_LCP_GATE_FALLBACK_MS = 5200;
    var QUEUE_STAGGER_MS = 50;
    var QUEUE_HEAVY_GAP_MS = 120; /* opt17: gap entre bundles LOAD_LAST (evita parse paralelo) */
    var QUEUE_STYLES_M_FRAGMENT = 'styles-m.css';
    var QUEUE_HEADER_FIRST_FRAGMENTS = [
        'awa-third-party-bundle',
        /* awa-carousel-bundle + awa-shelf-carousel: async imediato na home — vitrine é conteúdo primário */
        /* header-stack + header-refine-terminal: migrados para styles-l (_extend.less 43.01/43.02) */
        'awa-commerce-impeccable-refine',
        'awa-home-gate-visual-bundle',   /* §1-§25 layout 27KB — logo após carousel */
        'awa-home-terminal-bundle',
        'awa-home-hover-lock'
    ];
    /* Home: bundles pesados só após CSS de vitrine/header (opt16+17) */
    var QUEUE_HOME_LOAD_LAST_FRAGMENTS = [
        'awa-super-global',
        'awa-defer-global-bundle',
        'awa-layout-bundle',
        'awa-super-home',
        'awa-home-body-end-bundle',
        'awa-ui-simplify-terminal',
        'awa-structural-fix',
        'awa-home-gate-polish-cards',    /* card/shelf bugfixes ~134KB */
        'awa-home-gate-polish-type'       /* typography + passes — opt19: audit/bundle legado fora da fila */
    ];

    function isMobileViewport() {
        return window.matchMedia('(max-width: 767px)').matches;
    }

    function isMeaningfulIntent(event) {
        var target;

        if (!event) {
            return false;
        }

        if (event.type === 'keydown') {
            return event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar';
        }

        target = event.target;

        return !!(target && target.closest && target.closest(
            'a, button, input, select, textarea, label, summary, [role="button"], [role="link"], ' +
            '.minicart-wrapper, .awa-header-account-prompt, #search_mini_form, .awa-hero-swiper__nav, ' +
            '.swiper-pagination-bullet, .awa-category-carousel__item, .product-item, .item-product'
        ));
    }

    function isHomePage() {
        return !!(document.body && (
            document.body.classList.contains('cms-index-index') ||
            document.body.classList.contains('cms-home') ||
            document.body.classList.contains('cms-homepage_ayo_home5')
        ));
    }

    function onGateInteraction(event) {
        if (isHomePage() && !isMeaningfulIntent(event)) {
            return;
        }

        applyGatedCSS();
    }

    function isHeavyQueueFragment(url) {
        var j;
        var frag;

        if (!url) {
            return false;
        }

        for (j = 0; j < QUEUE_HOME_LOAD_LAST_FRAGMENTS.length; j += 1) {
            frag = QUEUE_HOME_LOAD_LAST_FRAGMENTS[j];
            if (url.indexOf(frag) !== -1) {
                return true;
            }
        }

        return url.indexOf(QUEUE_STYLES_M_FRAGMENT) !== -1;
    }

    function injectQueuedStylesheets() {
        return new Promise(function (resolve) {
            var node = document.getElementById('awa-css-gate-queue');
            var urls;
            var i;
            var link;
            var pending = 0;
            var urlsToLoad = [];
            var resolved = false;
            var watchdogId;

            function finishQueue() {
                if (resolved) {
                    return;
                }
                resolved = true;
                if (watchdogId) {
                    window.clearTimeout(watchdogId);
                }
                resolve();
            }

            if (!node || !node.textContent) {
                finishQueue();
                return;
            }

            try {
                urls = JSON.parse(node.textContent);
            } catch (e) {
                finishQueue();
                return;
            }

            if (!Array.isArray(urls)) {
                finishQueue();
                return;
            }

            urls.sort(function (a, b) {
                var aPri = 0;
                var bPri = 0;
                var aLast = 0;
                var bLast = 0;
                var j;
                var frag;

                for (j = 0; j < QUEUE_HEADER_FIRST_FRAGMENTS.length; j += 1) {
                    frag = QUEUE_HEADER_FIRST_FRAGMENTS[j];
                    if (a.indexOf(frag) !== -1) {
                        aPri = j + 1;
                    }
                    if (b.indexOf(frag) !== -1) {
                        bPri = j + 1;
                    }
                }

                if (aPri && !bPri) {
                    return -1;
                }
                if (!aPri && bPri) {
                    return 1;
                }
                if (aPri && bPri) {
                    return aPri - bPri;
                }

                for (j = 0; j < QUEUE_HOME_LOAD_LAST_FRAGMENTS.length; j += 1) {
                    frag = QUEUE_HOME_LOAD_LAST_FRAGMENTS[j];
                    if (a.indexOf(frag) !== -1) {
                        aLast = j + 1;
                    }
                    if (b.indexOf(frag) !== -1) {
                        bLast = j + 1;
                    }
                }

                if (aLast && !bLast) {
                    return 1;
                }
                if (!aLast && bLast) {
                    return -1;
                }
                if (aLast && bLast) {
                    return aLast - bLast;
                }

                return 0;
            });

            for (i = 0; i < urls.length; i += 1) {
                if (!urls[i] || document.querySelector('link[href="' + urls[i] + '"]')) {
                    continue;
                }
                urlsToLoad.push(urls[i]);
            }

            /* Home: styles-m (~5MB) por último — reduz TBT/Speed Index no lab sem atrasar LCP */
            if (document.body && document.body.classList.contains('cms-index-index')) {
                var stylesMUrls = [];
                var otherUrls = [];
                for (i = 0; i < urlsToLoad.length; i += 1) {
                    if (urlsToLoad[i].indexOf(QUEUE_STYLES_M_FRAGMENT) !== -1) {
                        stylesMUrls.push(urlsToLoad[i]);
                    } else {
                        otherUrls.push(urlsToLoad[i]);
                    }
                }
                urlsToLoad = otherUrls.concat(stylesMUrls);
            }

            if (urlsToLoad.length === 0) {
                finishQueue();
                return;
            }

            pending = urlsToLoad.length;
            watchdogId = window.setTimeout(finishQueue, QUEUE_WATCHDOG_MS);

            function onQueueSheetDone() {
                pending -= 1;
                if (pending <= 0) {
                    finishQueue();
                }
            }

            function appendQueuedSheet(url, done) {
                link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = url;
                link.fetchPriority = 'low';
                link.onload = done;
                link.onerror = done;
                document.head.appendChild(link);
            }

            var isHomeGate = document.body && document.body.classList.contains('cms-index-index');
            var lightUrls = [];
            var heavyUrls = [];

            for (i = 0; i < urlsToLoad.length; i += 1) {
                if (isHeavyQueueFragment(urlsToLoad[i])) {
                    heavyUrls.push(urlsToLoad[i]);
                } else {
                    lightUrls.push(urlsToLoad[i]);
                }
            }

            for (i = 0; i < lightUrls.length; i += 1) {
                window.setTimeout(
                    appendQueuedSheet.bind(null, lightUrls[i], onQueueSheetDone),
                    i * QUEUE_STAGGER_MS
                );
            }

            /* opt17: bundles pesados em série — evita travamento do main thread */
            (function loadHeavySequential(index) {
                var url;
                var stylesMDelay;
                var run;

                if (index >= heavyUrls.length) {
                    return;
                }

                url = heavyUrls[index];
                stylesMDelay = (isHomeGate && url.indexOf(QUEUE_STYLES_M_FRAGMENT) !== -1)
                    ? HOME_STYLES_M_DELAY_MS
                    : 0;

                run = function () {
                    appendQueuedSheet(url, function () {
                        window.setTimeout(function () {
                            loadHeavySequential(index + 1);
                        }, QUEUE_HEAVY_GAP_MS);
                        onQueueSheetDone();
                    });
                };

                if (stylesMDelay > 0) {
                    window.setTimeout(run, stylesMDelay);
                } else {
                    run();
                }
            }(0));
        });
    }

    function normalizeTerminalHref(href) {
        return href || '';
    }

    /** Header refine terminal — migrado para styles-l via _extend.less (import 43.02). */
    function injectHeaderRefineTerminal() {
        return Promise.resolve();
    }

    /** Impeccable refine — reinjeta após fila gate (vence polish-type / layout-bundle). */
    function injectImpeccableRefineTerminal() {
        return new Promise(function (resolve) {
            var probe = document.querySelector('link[data-awa-impeccable-terminal="refine"]');
            var href;
            var link;

            if (document.querySelector('link[data-awa-impeccable-refine-terminal="1"]')) {
                resolve();
                return;
            }

            href = '';
            if (probe && probe.href) {
                href = probe.href;
            } else {
                probe = document.querySelector('link[href*="awa-commerce-impeccable-refine"]');
                if (probe && probe.href) {
                    href = probe.href;
                }
            }

            if (!href) {
                resolve();
                return;
            }

            link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.media = 'all';
            link.setAttribute('data-awa-impeccable-refine-terminal', '1');
            link.onload = resolve;
            link.onerror = resolve;
            document.body.appendChild(link);
        });
    }

    /** Impeccable audit — no fim do body (vence fila reinjetada no head). */
    function injectImpeccableAuditTerminal() {
        return new Promise(function (resolve) {
            var href;
            var link;
            var probe = document.querySelector(
                'link[data-awa-impeccable-terminal="1"],' +
                'link[data-awa-impeccable-terminal="final"]'
            );

            if (probe) {
                resolve();
                return;
            }

            href = '';
            probe = document.querySelector('link[href*="awa-impeccable-audit-2026-05-28"]');
            if (probe && probe.href) {
                href = probe.href;
            } else {
                probe = document.querySelector('link[href*="awa-header-refine-terminal"]');
                if (probe && probe.href) {
                    href = probe.href.replace(/[^/]+$/, 'awa-impeccable-audit-2026-05-28.css');
                }
            }

            if (!href) {
                resolve();
                return;
            }

            link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.media = 'all';
            link.setAttribute('data-awa-impeccable-terminal', '1');
            link.onload = resolve;
            link.onerror = resolve;
            document.body.appendChild(link);
        });
    }

    /** Post-gate final-wins: CSS injetado no fim do body vence links do gate no head. */
    function getHomeStabilityCss() {
        return 'body#html-body.cms-index-index:not(.nav-open) .navigation.verticalmenu.side-verticalmenu>ul.togge-menu.list-category-dropdown:not(.vmm-open):not(.menu-open),body#html-body.cms-home:not(.nav-open) .navigation.verticalmenu.side-verticalmenu>ul.togge-menu.list-category-dropdown:not(.vmm-open):not(.menu-open),body#html-body.cms-homepage_ayo_home5:not(.nav-open) .navigation.verticalmenu.side-verticalmenu>ul.togge-menu.list-category-dropdown:not(.vmm-open):not(.menu-open){display:none!important;visibility:hidden!important;height:0!important;max-height:0!important;min-height:0!important;overflow:visible!important;margin:0!important;padding:0!important;border:0!important;pointer-events:none!important}body#html-body.cms-index-index .content-top-home a.awa-section-header__link,body#html-body.cms-index-index .content-top-home a.awa-shelf__view-all,body#html-body.cms-index-index .content-top-home a.awa-category-carousel__cta-link,body#html-body.cms-home .content-top-home a.awa-section-header__link,body#html-body.cms-home .content-top-home a.awa-shelf__view-all,body#html-body.cms-home .content-top-home a.awa-category-carousel__cta-link,body#html-body.cms-homepage_ayo_home5 .content-top-home a.awa-section-header__link,body#html-body.cms-homepage_ayo_home5 .content-top-home a.awa-shelf__view-all,body#html-body.cms-homepage_ayo_home5 .content-top-home a.awa-category-carousel__cta-link{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:6px!important;min-height:44px!important;padding:8px 12px!important;border-radius:999px!important;box-sizing:border-box!important;text-decoration:none!important;white-space:nowrap!important;color:var(--awa-primary,#b73337)!important}body#html-body.cms-index-index .content-top-home .awa-shelf--carousel :is(img.product-image-photo[loading="lazy"],.product-thumb img[loading="lazy"]),body#html-body.cms-home .content-top-home .awa-shelf--carousel :is(img.product-image-photo[loading="lazy"],.product-thumb img[loading="lazy"]),body#html-body.cms-homepage_ayo_home5 .content-top-home .awa-shelf--carousel :is(img.product-image-photo[loading="lazy"],.product-thumb img[loading="lazy"]){opacity:1!important;visibility:visible!important}';
    }

    function injectHomeStabilityFix(id) {
        var style;
        var isHome;

        isHome = document.body.classList.contains('cms-index-index') ||
            document.body.classList.contains('cms-home') ||
            document.body.classList.contains('cms-homepage_ayo_home5');

        if (!isHome) {
            return;
        }

        style = document.getElementById(id);
        if (!style) {
            style = document.createElement('style');
            style.id = id;
            document.body.appendChild(style);
        }

        style.textContent = getHomeStabilityCss();
    }

    function injectPostGateHeaderFix() {
        var style;
        var isHome;
        var css;

        if (document.getElementById('awa-header-impeccable-cascade-lock-v17')
            || document.getElementById('awa-header-impeccable-cascade-lock-v16')
            || document.getElementById('awa-header-impeccable-cascade-lock-v15')
            || document.getElementById('awa-header-impeccable-cascade-lock-v14')) {
            return;
        }

        isHome = isHomePage();

        css = '@media (min-width:992px){html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-header-categories .awa-nav-categories,html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-header-categories .sections.nav-sections.category-dropdown,html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-header-categories .section-items.nav-sections.category-dropdown-items,html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-header-categories .section-item-content.nav-sections.category-dropdown-item-content,html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-header-categories .navigation.verticalmenu.side-verticalmenu{background:transparent!important;height:48px!important;max-height:48px!important;min-height:0!important;overflow:visible!important;border:0!important;box-shadow:none!important;padding:0!important;margin:0!important}html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-header-categories button.our_categories.title-category-dropdown[data-role="awa-vertical-menu-trigger"],html body#html-body .page-wrapper .header-control.awa-nav-bar .menu_left_home1 button.our_categories.title-category-dropdown[data-role="awa-vertical-menu-trigger"]{border-radius:0!important;border:0!important;box-shadow:none!important;background:var(--awa-primary,#b73337)!important;color:#fff!important}html body#html-body .page-wrapper .header-control.awa-nav-bar .title-category-dropdown .vm-icon,html body#html-body .page-wrapper .header-control.awa-nav-bar .title-category-dropdown .icon-menu{background:transparent!important;border-radius:0!important;box-shadow:none!important}html body#html-body .page-wrapper .awa-site-header .awa-header-account-prompt .awa-header-account-prompt__line2{display:inline-flex!important;flex-wrap:nowrap!important;align-items:center!important;gap:4px!important;white-space:nowrap!important;min-height:0!important;height:auto!important}html body#html-body .page-wrapper .awa-site-header a.awa-header-account-prompt__link.awa-header-account-prompt__link--register{color:#fff!important;background:#b73337!important;background-color:#b73337!important;border:none!important;border-radius:9999px!important;padding:3px 10px!important;font-size:max(12px,0.75rem)!important;font-weight:700!important;white-space:nowrap!important;display:inline-flex!important;align-items:center!important;line-height:1.2!important;min-height:0!important;height:auto!important}html body#html-body .page-wrapper .awa-site-header .awa-header-account-prompt .awa-header-account-prompt__line1{font-size:max(12px,0.75rem)!important}html body#html-body .page-wrapper .awa-site-header form#search_mini_form.minisearch{border:1.5px solid #e2e8f0!important;border-radius:9999px!important;box-shadow:0 1px 2px rgba(15,23,42,.04)!important;overflow:visible!important}html body#html-body .page-wrapper .awa-site-header a.action.showcart.header-mini-cart,html body#html-body .page-wrapper .awa-site-header .minicart-wrapper .action.showcart{overflow:visible!important;position:relative!important}}';

        css += '@media (prefers-reduced-motion:no-preference){#html-body .page-wrapper .header-wrapper-sticky,#html-body .page-wrapper .header-wrapper-sticky.is-sticky,#html-body .page-wrapper .awa-site-header .header-wrapper-sticky{transition:box-shadow .2s ease,opacity .2s ease,transform .2s ease!important}#html-body .page-wrapper .logo,#html-body .page-wrapper .awa-site-header .logo,#html-body .page-wrapper .logo img{transition:opacity .2s ease,transform .2s ease!important}}@media (prefers-reduced-motion:reduce){#html-body .page-wrapper .header-wrapper-sticky,#html-body .page-wrapper .logo,#html-body .page-wrapper .logo img{transition:none!important}}#html-body .page-wrapper .awa-header-account-prompt__guest .awa-header-account-prompt__line1,#html-body .page-wrapper .awa-benefit-desc{font-size:max(12px,.8125rem)!important;line-height:1.45!important}#html-body .page-wrapper .awa-footer-trust-bar .awa-footer-trust-copy strong{color:#333!important}#html-body .page-wrapper .awa-footer-trust-bar .awa-footer-trust-copy span{color:#666!important}#html-body .page-wrapper .awa-footer-business-contact__action,#html-body .page-wrapper .awa-footer-business-contact__action--primary{background:#fff!important;border:1px solid #e5e7eb!important;color:#111827!important}#html-body .page-wrapper .awa-footer-business-contact__action-copy strong,#html-body .page-wrapper .awa-footer-business-contact__action-copy small{color:#111827!important}html body#html-body .page-wrapper .page_footer .awa-footer-atendimento p.awa-footer-atendimento__label,html body#html-body .page-wrapper .page_footer .awa-footer-atendimento p.awa-footer-atendimento__label--social,html body#html-body .page-wrapper .page_footer p.awa-footer-atendimento__label,html body#html-body .page-wrapper .page_footer p.awa-footer-atendimento__label--social{color:oklch(45% .02 20)!important}html body#html-body .page-wrapper .page_footer .awa-footer-atendimento .awa-footer-atendimento__store{background:oklch(99% .002 20)!important;border:1px solid oklch(92% .01 20)!important;padding:12px!important;border-radius:8px!important}html body#html-body .page-wrapper .page_footer .awa-footer-atendimento .awa-footer-atendimento__store p.awa-footer-atendimento__store-name,html body#html-body .page-wrapper .page_footer .awa-footer-atendimento .awa-footer-atendimento__store p.awa-footer-atendimento__store-address{color:oklch(22% .01 20)!important}#html-body .page-wrapper .page_footer h3.awa-newsletter-title{color:#fff!important}html body#html-body .page-wrapper :is(.navigation.verticalmenu div[id^="submenu-menu-"],.navigation.verticalmenu .submenu.navigation__submenu){border:0!important;box-shadow:0 4px 12px rgb(15 23 42/10%)!important}html body#html-body .page-wrapper :is(#search_autocomplete,.mst-searchautocomplete__autocomplete){border:0!important;box-shadow:0 4px 16px rgb(15 23 42/12%)!important;overflow:visible!important}html body#html-body .page-wrapper :is(#awa-b2b-promo-bar,.awa-b2b-promo-bar) .awa-b2b-promo-close{color:#fff!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-section-header__eyebrow{display:none!important;visibility:hidden!important;height:0!important;width:0!important;overflow:hidden!important;margin:0!important;padding:0!important;border:0!important}html body#html-body :is(nav.fixed-bottom.hidden-sm,nav.fixed-bottom,.fixed-bottom,.awa-mobile-bottom-nav){border:0!important;border-top:0!important;box-shadow:0 -2px 8px rgb(15 23 42/8%)!important}html body#html-body .page-wrapper :is(#header .header-content,.header-container .header-content,.header-content){border:0!important;box-shadow:none!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-category-carousel__item .awa-category-carousel__icon{transition:transform .24s cubic-bezier(.22,1,.36,1)!important}html body#html-body .page-wrapper .awa-footer-trust-bar .awa-footer-trust-item .awa-footer-trust-copy strong{color:#333!important}html body#html-body .page-wrapper .awa-footer-trust-bar .awa-footer-trust-item .awa-footer-trust-copy span{color:#666!important}html body#html-body.b2b-register-index .page-wrapper #b2b-register-shell{background:oklch(98.5% .004 20)!important;border:0!important;box-shadow:none!important;padding:clamp(20px,4vw,34px)!important;border-radius:16px!important}html body#html-body.b2b-register-index .page-wrapper #b2b-register-shell :is(.b2b-register-container,.b2b-register-page){border:0!important;box-shadow:none!important;background:transparent!important;padding:0!important}html body#html-body.b2b-register-index .page-wrapper #b2b-register-shell .b2b-register-progress{border:0!important;background:transparent!important;box-shadow:none!important}html body#html-body.b2b-register-index .page-wrapper #b2b-register-shell .form-section{border:0!important;background:transparent!important;box-shadow:none!important}html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper{overflow-x:clip!important;overflow-y:visible!important}html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper .awa-skip-link:not(:focus):not(:focus-visible){left:0!important;width:1px!important;height:1px!important;clip-path:inset(50%)!important;overflow:hidden!important;margin:-1px!important}html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper :is(#awa-search-label,#awa-search-panel-a11y,.mst-searchautocomplete__autocomplete){overflow:visible!important}html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper li.ui-menu-item.navigation__item--parent{overflow:visible!important}html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper :is(.level0.submenu.navigation__submenu,.subchildmenu.navigation__inner-list){overflow:visible!important;padding-right:12px!important}html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper .navigation.custommenu.main-nav>li.ui-menu-item.navigation__item>a{padding-block:12px!important}html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper form#search_mini_form.minisearch .actions{padding-inline-start:8px!important}html body#html-body.b2b-account-dashboard .page-wrapper .page-main>.columns{display:grid!important;grid-template-columns:1fr!important;gap:8px!important;align-items:start!important}@media (min-width:768px){html body#html-body.b2b-account-dashboard .page-wrapper .page-main>.columns{grid-template-columns:200px minmax(0,1fr)!important;gap:16px!important}html body#html-body.b2b-account-dashboard .page-wrapper .page-main>.columns>.column.main{grid-column:2!important}}@media (min-width:1024px){html body#html-body.b2b-account-dashboard .page-wrapper .page-main>.columns{grid-template-columns:240px minmax(0,1fr)!important;gap:24px!important}}html body#html-body.b2b-account-dashboard .page-wrapper .summary-card{box-shadow:none!important}html body#html-body.b2b-account-dashboard .page-wrapper .b2b-dashboard-lazy-panel[data-lazy-loaded="error"],html body#html-body.b2b-account-dashboard .page-wrapper .b2b-dashboard-lazy-panel.b2b-dashboard-lazy-panel--error{border:0!important;background:transparent!important;padding:8px 0!important}';

        if (isHome) {
            css += 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl:not(.owl-carousel):not(.owl-loaded){display:flex!important;flex-flow:row nowrap!important;overflow:hidden!important;width:100%!important;min-height:300px!important;max-height:300px!important;margin:0!important;padding:0!important;list-style:none!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl:not(.owl-carousel):not(.owl-loaded)>li.item{flex:0 0 50%!important;max-width:50%!important;box-sizing:border-box!important;list-style:none!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl:not(.owl-carousel):not(.owl-loaded)>li.item:nth-child(n+3){display:none!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-nav-bar .velaFooterLinks,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-site-header .velaFooterLinks:not(.page-footer .velaFooterLinks),html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .header-control .velaFooterLinks{display:none!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-site-header .awa-account-dropdown__trigger:not([aria-expanded="true"])+.awa-account-dropdown__menu{display:none!important;opacity:0!important;visibility:hidden!important;pointer-events:none!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .item-product .product-image-photo,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .item-product .product-thumb img{min-height:120px!important;object-fit:contain!important;opacity:1!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-shelf--carousel :is(img.product-image-photo[loading="lazy"],.product-thumb img[loading="lazy"]){opacity:1!important;visibility:visible!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-shelf--carousel.awa-carousel-pending .awa-carousel__viewport{animation-iteration-count:3!important}@media(min-width:768px){html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl:not(.owl-carousel):not(.owl-loaded)>li.item{flex:0 0 33.333%!important;max-width:33.333%!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl:not(.owl-carousel):not(.owl-loaded)>li.item:nth-child(n+3){display:flex!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl:not(.owl-carousel):not(.owl-loaded)>li.item:nth-child(n+4){display:none!important}}@media(min-width:1024px){html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl:not(.owl-carousel):not(.owl-loaded)>li.item{flex:0 0 25%!important;max-width:25%!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl:not(.owl-carousel):not(.owl-loaded)>li.item:nth-child(n+4){display:flex!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl:not(.owl-carousel):not(.owl-loaded)>li.item:nth-child(n+5){display:none!important}}';
            css += getHomeStabilityCss();
            css += '@media(max-width:767px){html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-shelf--carousel>.awa-owl-nav,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-shelf--carousel .awa-carousel>.awa-owl-nav{display:flex!important;position:relative!important;transform:none!important;min-height:44px!important;height:auto!important;justify-content:flex-end!important;margin-block-start:8px!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-shelf--carousel .awa-owl-nav__btn,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-shelf--carousel .awa-owl-nav__btn.swiper-button-prev,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-shelf--carousel .awa-owl-nav__btn.swiper-button-next{position:relative!important;inset:auto!important;top:auto!important;left:auto!important;right:auto!important;inline-size:44px!important;block-size:44px!important;min-inline-size:44px!important;min-block-size:44px!important;transform:none!important;margin:0!important}}';
            css += '@media (min-width:992px){html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .menu_left_home1 .verticalmenu.side-verticalmenu>ul.togge-menu.list-category-dropdown:is(.menu-open,.vmm-open,[aria-hidden="false"],[data-awa-menu-state="open"]){overflow:visible!important;overflow-x:visible!important;contain:layout style!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .menu_left_home1 .verticalmenu.side-verticalmenu>ul.togge-menu.list-category-dropdown:is(.menu-open,.vmm-open)>li.ui-menu-item.level0{overflow:visible!important}}';
        }

        /* Cascade-lock v12 no body: nav/mobile only — promo + impeccable surfaces live in PHP cascade-lock */
        if (!document.getElementById('awa-header-impeccable-cascade-lock-v12')) {
            css += '@media (max-width:767px){html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky,html body#html-body .page-wrapper #header .header-wrapper-sticky{height:88px!important;min-height:88px!important;max-height:88px!important;overflow:hidden!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){display:grid!important;grid-template-areas:"toggle brand cart" "search search search"!important;grid-template-columns:44px minmax(0,1fr) 44px!important;grid-template-rows:minmax(0,44px) minmax(0,44px)!important;gap:8px 10px!important;padding:0 12px!important;max-height:88px!important;height:88px!important;overflow:hidden!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky :is(.awa-header-brand-cell,.col-md-2.awa-header-brand){grid-area:brand!important;align-self:center!important;max-height:56px!important}}@media (min-width:768px) and (max-width:991px){html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){display:grid!important;grid-template-areas:"brand search account cart"!important;grid-template-columns:minmax(72px,auto) minmax(0,1fr) auto 44px!important;grid-template-rows:44px!important;gap:8px 12px!important;padding:0 16px!important;max-height:52px!important;height:auto!important;overflow:visible!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky :is(.awa-header-mobile-toggle,.action.nav-toggle,[data-action="toggle-nav"]){display:none!important;visibility:hidden!important;pointer-events:none!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky .awa-header-right-col .awa-header-account-prompt{grid-area:account!important;display:flex!important;visibility:visible!important;pointer-events:auto!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky .awa-header-minicart{grid-area:cart!important;justify-self:end!important}}@media (min-width:992px){html body#html-body .page-wrapper .awa-site-header .awa-main-header__inner.wp-header,html body#html-body .page-wrapper .awa-site-header .awa-main-header__inner[data-awa-header-row]{display:grid!important;grid-template-columns:clamp(140px,16%,200px) minmax(320px,1fr) auto!important;align-items:center!important;gap:clamp(16px,2vw,32px)!important;overflow:visible!important}html body#html-body .page-wrapper .awa-site-header .awa-main-header__inner.wp-header,html body#html-body .page-wrapper .awa-site-header .awa-main-header__inner[data-awa-header-row],html body#html-body .page-wrapper .awa-site-header .header.awa-main-header{min-height:68px!important;height:68px!important;max-height:68px!important;padding-block:0!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-site-header .awa-main-header__inner.wp-header,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-site-header .awa-main-header__inner[data-awa-header-row]{display:grid!important;grid-template-columns:minmax(132px,176px) minmax(360px,1fr) minmax(300px,auto)!important;grid-template-areas:"brand search actions"!important;column-gap:clamp(16px,2vw,28px)!important}html body#html-body .page-wrapper .awa-site-header .awa-header-primary-row{display:contents!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-site-header .awa-header-brand-cell{grid-area:brand!important;grid-column:auto!important;max-width:176px!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-site-header .awa-header-search-col{grid-area:search!important;grid-column:auto!important;min-width:0!important;max-width:none!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-site-header .awa-header-right-col{grid-area:actions!important;grid-column:auto!important;width:auto!important;max-width:360px!important;justify-self:end!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky :is(.awa-header-brand-cell,.col-md-2.awa-header-brand){align-self:center!important;height:auto!important;min-height:0!important;max-height:56px!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky,html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky.is-sticky,html body#html-body .page-wrapper #header .header-wrapper-sticky{height:auto!important;min-height:68px!important;max-height:68px!important;overflow:visible!important}html body#html-body .page-wrapper .header-control.header-nav.awa-nav-bar,html body#html-body .page-wrapper .header-control.awa-nav-bar,html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-nav-bar__inner,html body#html-body .page-wrapper .header-control.awa-nav-bar > .container{min-height:var(--awa-nav-bar-h,48px)!important;max-height:var(--awa-nav-bar-h,48px)!important;height:var(--awa-nav-bar-h,48px)!important;box-sizing:border-box!important}html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header .header-control.header-nav.awa-nav-bar,html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header .header-control.header-nav.awa-nav-bar > .container,html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header .header-control.header-nav.awa-nav-bar .awa-nav-bar__inner{min-height:var(--awa-nav-bar-h,48px)!important;max-height:var(--awa-nav-bar-h,48px)!important;height:var(--awa-nav-bar-h,48px)!important;box-sizing:border-box!important;padding-block:0!important;overflow:visible!important}html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-nav-bar__inner,html body#html-body .page-wrapper .header-control.awa-nav-bar > .container > .row{display:flex!important;align-items:center!important;min-height:48px!important;height:48px!important}}';
        }

        style = document.getElementById('awa-header-post-gate-fix');
        if (!style) {
            style = document.createElement('style');
            style.id = 'awa-header-post-gate-fix';
            document.body.appendChild(style);
        }

        css += '@media (max-width:767px){html body#html-body .page-wrapper :is(.awa-site-header .header-wrapper-sticky,#header .header-wrapper-sticky){height:auto!important;min-height:88px!important;max-height:none!important;overflow:visible!important;contain:none!important;padding-block:0!important}html body#html-body .page-wrapper .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){overflow:visible!important}html body#html-body .page-wrapper .awa-site-header :is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap,.awa-header-search-col,.awa-header-minicart,.minicart-wrapper){overflow:visible!important}}@media (min-width:768px) and (max-width:991px){html body#html-body .page-wrapper :is(.awa-site-header .header-wrapper-sticky,#header .header-wrapper-sticky){height:auto!important;min-height:52px!important;max-height:none!important;overflow:visible!important;contain:none!important;padding-block:0!important}}@media (min-width:992px){html body#html-body .page-wrapper :is(.awa-site-header .header-wrapper-sticky,#header .header-wrapper-sticky){height:auto!important;min-height:68px!important;max-height:68px!important;overflow:visible!important;contain:none!important;padding-block:0!important}}';

        style.textContent = css;
    }

    function logHeaderDebugMetrics() {
        /* debug telemetry removed */
    }

    function applyGatedCSS() {
        var links;
        var i;

        if (applied) {
            return;
        }

        applied = true;

        injectHomeStabilityFix('awa-home-stability-gate-fix');

        if (document.documentElement) {
            document.documentElement.classList.add('awa-css-gate-applied');
            document.documentElement.classList.remove('awa-css-gate-pending');
        }

        links = document.querySelectorAll('link[' + CSS_GATE_ATTR + ']');

        for (i = 0; i < links.length; i += 1) {
            links[i].media = 'all';
        }

        injectQueuedStylesheets().then(function () {
            return injectHeaderRefineTerminal();
        }).then(function () {
            return injectImpeccableAuditTerminal();
        }).then(function () {
            return injectImpeccableRefineTerminal();
        }).then(function () {
            injectPostGateHeaderFix();
            logHeaderDebugMetrics('post-gate', 'H2-H4');
            try {
                document.dispatchEvent(new CustomEvent('awa:css-gate-applied', { bubbles: true }));
            } catch (e) { /* noop */ }
        });

        for (i = 0; i < GATE_EVENTS.length; i += 1) {
            window.removeEventListener(GATE_EVENTS[i], onGateInteraction, true);
        }
    }

    var i;

    window.__awaApplyGatedCSS = applyGatedCSS;

    if (document.documentElement && isHomePage()) {
        document.documentElement.classList.add('awa-css-gate-pending');
    }

    for (i = 0; i < GATE_EVENTS.length; i += 1) {
        window.addEventListener(GATE_EVENTS[i], onGateInteraction, {
            capture: true,
            passive: true
        });
    }

    function scheduleFallbackGate() {
        var run = function () {
            if (!applied) {
                applyGatedCSS();
            }
        };

        if (isHomePage()) {
            if ('requestIdleCallback' in window) {
                window.requestIdleCallback(run, { timeout: HOME_LCP_GATE_FALLBACK_MS });
            } else {
                window.setTimeout(run, HOME_LCP_GATE_FALLBACK_MS);
            }
            return;
        }

        if (isMobileViewport()) {
            window.setTimeout(run, MOBILE_FALLBACK_DELAY_MS);
            return;
        }

        window.setTimeout(run, DESKTOP_FALLBACK_DELAY_MS);
    }

    window.addEventListener('load', function () {
        window.setTimeout(function () {
            logHeaderDebugMetrics('pre-gate', 'H1');
        }, 500);

        scheduleFallbackGate();
    }, { once: true });

    if (window.__awaCssGateApplyImmediately) {
        window.setTimeout(applyGatedCSS, 0);
    }
}());

/**
 * Busca — botão limpar (sync; awa-ux-enhancements deferido na home via AMD bootstrap).
 */
(function () {
    'use strict';

    function setSearchClearVisible(clearBtn, visible) {
        clearBtn.hidden = !visible;
        clearBtn.style.display = visible ? 'inline-flex' : 'none';
        clearBtn.setAttribute('aria-hidden', visible ? 'false' : 'true');
    }

    function initSearchClear() {
        var searchInput = document.querySelector(
            '#search, .header-search input[type="text"], .block-search input.input-text'
        );
        if (!searchInput || document.getElementById('awa-search-clear')) {
            return;
        }

        var clearBtn = document.createElement('button');
        clearBtn.id = 'awa-search-clear';
        clearBtn.type = 'button';
        clearBtn.className = 'awa-search-clear-btn';
        clearBtn.setAttribute('aria-label', 'Limpar busca');
        clearBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

        var control = searchInput.closest(
            '.control[data-awa-search-control], .field.search .control, .control'
        ) || searchInput.parentElement;

        if (control) {
            if (window.getComputedStyle(control).position === 'static') {
                control.style.position = 'relative';
            }
            control.appendChild(clearBtn);
        }

        setSearchClearVisible(clearBtn, false);
        searchInput.classList.add('awa-search-input--clearable');

        searchInput.addEventListener('input', function () {
            setSearchClearVisible(clearBtn, !!this.value);
        });
        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            setSearchClearVisible(clearBtn, false);
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            searchInput.focus();
        });
    }

    function bootSearchClear() {
        initSearchClear();
    }

    if (document.readyState !== 'loading') {
        bootSearchClear();
    } else {
        document.addEventListener('DOMContentLoaded', bootSearchClear, { once: true });
    }

    window.setTimeout(bootSearchClear, 500);
    window.setTimeout(bootSearchClear, 2000);
}());
