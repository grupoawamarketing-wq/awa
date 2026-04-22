!function(e,t){"use strict";if(!e.__awaQaAccessibilitySafeInit){e.__awaQaAccessibilitySafeInit=!0;var a=0,r=!1,n=null,o=null,i=!1;"loading"!==t.readyState?E():t.addEventListener("DOMContentLoaded",E,{once:!0})}function u(e){return String(e||"").replace(/\s+/g," ").trim()}function s(e,t){return e?(e.id||(a+=1,e.id=(t||"awa-node")+"-"+a),e.id):""}function c(e,t){var a=u(t);e&&a&&(e.getAttribute("aria-label")||e.setAttribute("aria-label",a),!e.getAttribute("title")&&e.matches("a, button, input, select, textarea")&&e.setAttribute("title",a))}function l(e,t){var a,r;if(!e)return null;for(a=e.children||[],r=0;r<a.length;r+=1)if(a[r].matches&&a[r].matches(t))return a[r];return null}function q(e){return e&&e.labels&&e.labels.length?u(Array.prototype.map.call(e.labels,function(e){return e.textContent||""}).join(" ")):""}function C(e){var a,r,n;if(!e)return"";if((a=e.closest("label"))&&u(a.textContent))return u(a.textContent);if((r=e.previousElementSibling)&&r.matches("label")&&u(r.textContent))return u(r.textContent);if((n=e.nextElementSibling)&&n.matches("label")&&u(n.textContent))return u(n.textContent);return""}function L(e){var a,r,n,o;if(!e||e.getAttribute("aria-label")||e.getAttribute("aria-labelledby")||!p(e))return;a=q(e),a||(r=e.id,r&&(n=t.querySelector('label[for="'+r.replace(/"/g,'\\"')+'"]'),a=n?u(n.textContent):"")),a||(a=C(e)),a||(o=e.getAttribute("placeholder")||e.getAttribute("title")||"select"===e.tagName.toLowerCase()&&e.name?e.name:""),c(e,a)}function x(){Array.prototype.slice.call(t.querySelectorAll("#mst_categorySearch")).forEach(function(e){c(e,e.getAttribute("placeholder")||"Buscar produtos nesta categoria")}),Array.prototype.slice.call(t.querySelectorAll("#sorter, .sorter-options")).forEach(function(e){c(e,"Ordenar produtos por")}),Array.prototype.slice.call(t.querySelectorAll("#limiter, .limiter-options")).forEach(function(e){c(e,"Produtos por página")}),Array.prototype.slice.call(t.querySelectorAll('#choose_category, select[name="cat"]')).forEach(function(e){c(e,"Categoria")}),Array.prototype.slice.call(t.querySelectorAll('input:not([type="hidden"]):not([aria-label]):not([aria-labelledby]), select:not([aria-label]):not([aria-labelledby]), textarea:not([aria-label]):not([aria-labelledby])')).forEach(L)}function d(){i||(i=!0,e.requestAnimationFrame(function(){var e;i=!1,Array.prototype.slice.call(t.querySelectorAll('.block-search, [data-awa-component="search-autocomplete"]')).forEach(y),x(),e=t.querySelectorAll(".navigation.custommenu.main-nav, .navigation.verticalmenu.side-verticalmenu"),Array.prototype.slice.call(e).forEach(function(e){var t=e.querySelector('[data-role="awa-vertical-menu-trigger"]');t&&t.setAttribute("aria-haspopup","true"),Array.prototype.slice.call(e.querySelectorAll("li")).forEach(function(e){var t,a=l(e,"a"),r=l(e,".open-children-toggle"),n=l(e,".submenu, .groupmenu, .subchildmenu, ul.level0"),o=u(a?a.textContent:e.textContent)||"Submenu";n?(t=e.classList.contains("active")||e.classList.contains("_active")||n.classList.contains("opened")||n.classList.contains("active")||n.classList.contains("is-open")||p(n),s(n,"awa-menu-panel"),n.setAttribute("aria-hidden",t?"false":"true"),a&&(a.setAttribute("aria-haspopup","true"),c(a,o)),r&&(r.setAttribute("role","button"),r.setAttribute("tabindex",r.getAttribute("tabindex")||"0"),r.setAttribute("aria-controls",n.id),r.setAttribute("aria-expanded",t?"true":"false"),c(r,(t?"Recolher ":"Expandir ")+o))):a&&c(a,o)})})}))}function p(e){return!(!e||e.hidden||"true"===e.getAttribute("aria-hidden"))&&null!==e.offsetParent}function f(e){return e?Array.prototype.slice.call(e.querySelectorAll(["a[href]","button:not([disabled])","textarea:not([disabled])",'input:not([disabled]):not([type="hidden"])',"select:not([disabled])",'[tabindex]:not([tabindex="-1"])'].join(","))).filter(p):[]}function b(){var e=t.getElementById("maincontent");return e&&!e.hasAttribute("tabindex")&&e.setAttribute("tabindex","-1"),e}function h(e){var a,r,n,o=e.querySelector('[data-awa-search-input="true"], #search, input[name="q"]'),i=e.querySelector('[data-awa-search-help], #awa-search-help');return o?(i&&i.setAttribute("data-awa-search-help","true"),i||((i=t.createElement("span")).className="awa-sr-only",i.setAttribute("data-awa-search-help","true"),i.textContent="Digite ao menos 2 caracteres para ver sugestões e use as setas para navegar.",e.appendChild(i)),s(i,"awa-search-help"),a=o,r=i.id,a&&r&&-1===(n=(a.getAttribute("aria-describedby")||"").split(/\s+/).filter(Boolean)).indexOf(r)&&(n.push(r),a.setAttribute("aria-describedby",n.join(" "))),i):null}function y(e){var t,a,r,n,o;e&&(t=e.querySelector('[data-awa-search-input="true"], #search, input[name="q"]'),a=e.querySelector('[data-awa-search-category-select="true"], #choose_category'),r=e.querySelector('#search_autocomplete, [data-awa-search-panel="true"]'),n=e.querySelector('#searchsuite-autocomplete, [data-awa-search-results-root="true"], .searchsuite-autocomplete'),h(e),t&&c(t,t.getAttribute("placeholder")||"Buscar produtos"),a&&c(a,"Categoria"),r&&(s(r,"awa-search-panel"),r.setAttribute("role",r.getAttribute("role")||"listbox"),r.setAttribute("aria-label",r.getAttribute("aria-label")||"Sugestões de busca"),t&&!t.getAttribute("aria-controls")&&t.setAttribute("aria-controls",r.id)),n&&(n.setAttribute("aria-label",n.getAttribute("aria-label")||"Resultados da busca em tempo real"),Array.prototype.slice.call(n.querySelectorAll("a[href], button")).forEach(function(e){var t=e.getAttribute("data-awa-option-label")||e.getAttribute("aria-label")||u(e.textContent);c(e,t)})),o=e.querySelectorAll('.search-autocomplete li, .searchsuite-autocomplete li, [role="option"]'),Array.prototype.slice.call(o).forEach(function(e){e.setAttribute("role","option"),e.setAttribute("aria-selected",e.getAttribute("aria-selected")||"false"),e.setAttribute("tabindex",e.getAttribute("tabindex")||"-1"),s(e,"awa-search-option")}))}function v(){return t.getElementById("awa-quote-modal")}function A(e){Array.prototype.slice.call(t.querySelectorAll("[data-awa-quote-open]")).forEach(function(t){t.setAttribute("aria-expanded",e?"true":"false")})}function m(){var e=v();e&&(e.classList.remove("is-open"),e.setAttribute("aria-hidden","true"),t.body.classList.remove("awa-modal-open"),A(!1),r&&(t.removeEventListener("keydown",g,!0),r=!1),n&&"function"==typeof n.focus&&n.focus())}function g(e){var a=v();if(a&&"true"!==a.getAttribute("aria-hidden"))return"Escape"===e.key?(e.preventDefault(),void m()):void("Tab"===e.key&&function(e,a){var r,n,o=f(a);o.length?(r=o[0],n=o[o.length-1],e.shiftKey&&t.activeElement===r?(e.preventDefault(),n.focus()):e.shiftKey||t.activeElement!==n||(e.preventDefault(),r.focus())):(e.preventDefault(),a.focus())}(e,a))}function w(){var a=v();Array.prototype.slice.call(t.querySelectorAll("[data-awa-quote-open]")).forEach(function(a){"true"!==a.dataset.awaQuoteOpenBound&&(a.addEventListener("click",function(){!function(a){var o,i,u=v();u&&(n=a||t.activeElement,u.classList.add("is-open"),u.setAttribute("aria-hidden","false"),t.body.classList.add("awa-modal-open"),A(!0),o=u.querySelector("[data-awa-quote-autofocus]"),i=f(u),e.setTimeout(function(){o&&"function"==typeof o.focus?o.focus():i.length&&"function"==typeof i[0].focus?i[0].focus():u.focus()},0),r||(t.addEventListener("keydown",g,!0),r=!0))}(a)}),a.dataset.awaQuoteOpenBound="true")}),a&&"true"!==a.dataset.awaQuoteModalBound&&(a.addEventListener("click",function(e){var t=e.target;t&&t.closest("[data-awa-quote-close]")&&m()}),a.dataset.awaQuoteModalBound="true")}function E(){var a;b(),Array.prototype.slice.call(t.querySelectorAll(".awa-skip-link, .vmm-skip-nav")).forEach(function(t){"true"!==t.dataset.awaSkipBound&&(t.addEventListener("click",function(){var t=b();t&&e.setTimeout(function(){t.focus({preventScroll:!1})},0)}),t.dataset.awaSkipBound="true")}),function(){var a=t.getElementById("awa-back-to-top");function r(){var r=(e.pageYOffset||t.documentElement.scrollTop||0)>600;a.hidden=!r,a.classList.toggle("is-visible",r),a.setAttribute("aria-hidden",r?"false":"true")}a&&"true"!==a.dataset.awaBackToTopBound&&(a.addEventListener("click",function(){e.scrollTo({top:0,behavior:"function"==typeof e.matchMedia&&e.matchMedia("(prefers-reduced-motion: reduce)").matches?"auto":"smooth"})}),e.addEventListener("scroll",r,{passive:!0}),r(),a.dataset.awaBackToTopBound="true")}(),w(),a=t.querySelectorAll('.block-search, [data-awa-component="search-autocomplete"]'),Array.prototype.slice.call(a).forEach(y),x(),o||"undefined"==typeof MutationObserver||(o=new MutationObserver(function(){d()})).observe(t.body,{childList:!0,subtree:!0}),d(),t.addEventListener("click",function(t){t.target&&t.target.closest(".navigation, .block-search, .search-autocomplete, .searchsuite-autocomplete, .toolbar, .mst_categorySearch, #newsletter_pop_up, .modal-popup")&&e.setTimeout(d,0)},!0),e.addEventListener("resize",d)}}(window,document);

/* AWA WCAG fixes — appended to awa-qa-accessibility-safe.js */
!function(w, d) {
    'use strict';
    if (w.__awaWcagExtFixesInit) { return; }
    w.__awaWcagExtFixesInit = true;

    function applyFixes() {
        /* 1. WCAG 2.5.3: Remove mismatched aria-labels from footer contact links.
           e.g. aria-label="Abrir WhatsApp comercial" vs visible text "WhatsApp Comercial Resposta rápida..."
           Removing the mismatched label lets the accessible name fall back to visible text. */
        d.querySelectorAll('.awa-footer-business-contact__action[aria-label]').forEach(function (link) {
            var ariaLabel = (link.getAttribute('aria-label') || '').toLowerCase();
            var visibleText = (link.innerText || link.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
            if (visibleText && !ariaLabel.includes(visibleText)) {
                link.removeAttribute('aria-label');
            }
        });

        /* 2. WCAG 2.5.3: Nav toggle — accessible name must contain visible text "Alternar Nav".
           Prepend visible text so aria-label starts with what sighted users see. */
        var navToggle = d.querySelector('.action.nav-toggle[aria-label][data-action="toggle-nav"]');
        if (navToggle) {
            var visText = (navToggle.innerText || navToggle.textContent || '').replace(/\s+/g, ' ').trim();
            var curLabel = navToggle.getAttribute('aria-label') || '';
            if (visText && !curLabel.toLowerCase().includes(visText.toLowerCase())) {
                navToggle.setAttribute('aria-label', visText + ': ' + curLabel);
            }
        }

        /* 3. WCAG 2.5.3: Category mega-menu trigger — visible text " Categorias" not in aria-label.
           Prepend visible text. */
        var catTrigger = d.querySelector('.title-category-dropdown[aria-label]');
        if (catTrigger) {
            var catVisText = (catTrigger.innerText || catTrigger.textContent || '').replace(/\s+/g, ' ').trim();
            var catLabel = catTrigger.getAttribute('aria-label') || '';
            if (catVisText && !catLabel.toLowerCase().includes(catVisText.toLowerCase())) {
                catTrigger.setAttribute('aria-label', catVisText + ': ' + catLabel);
            }
        }

        /* 4. WCAG 1.3.1: Page Builder injects <div> nodes as direct children of <ul.main-nav-list>,
           which makes them invalid list children. role="presentation" makes AT ignore the divs
           structurally, so the nested <li> elements are treated as direct list children. */
        d.querySelectorAll('ul.main-nav-list > div').forEach(function (div) {
            div.setAttribute('role', 'presentation');
        });

        /* 5. WCAG 1.3.6 / landmark-one-main: On the homepage themes5.css sets
           .page-main { display:none }, leaving no visible main landmark.
           Add role="main" to the actual content container. */
        var mainEl = d.querySelector('main#maincontent');
        if (mainEl && w.getComputedStyle(mainEl).display === 'none') {
            var contentTop = d.querySelector('.content-top-home');
            if (contentTop && !contentTop.getAttribute('role')) {
                contentTop.setAttribute('role', 'main');
                contentTop.setAttribute('aria-label', 'Conteúdo principal');
            }
        }

        /* 6. WCAG 1.1.1: Auth modal logo img has no alt (Knockout only sets src). */
        var authLogo = d.querySelector('.block-authentication .wave-top img.logo');
        if (authLogo && !authLogo.getAttribute('alt')) {
            authLogo.setAttribute('alt', 'AWA Motos');
        }
    }

    if (d.readyState !== 'loading') {
        applyFixes();
    } else {
        d.addEventListener('DOMContentLoaded', applyFixes, { once: true });
    }
}(window, document);
