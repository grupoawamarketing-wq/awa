/**
 * AWA PDP B2B Pro — Interactive features
 * Image zoom, tabs, sticky sidebar, smooth scroll
 * 2026-03-28
 */
(function () {
    'use strict';

    if (window.__awaPdpB2bProInit) return;
    window.__awaPdpB2bProInit = true;

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    /* ========================================
       1. IMAGE ZOOM OVERLAY
       ======================================== */
    function initImageZoom() {
        var mediaCol = document.querySelector('.catalog-product-view .product.col.media') ||
                       document.querySelector('.catalog-product-view .product.media');
        if (!mediaCol) return;

        // Add zoom trigger button
        var stage = mediaCol.querySelector('.fotorama__stage') ||
                    mediaCol.querySelector('.gallery-placeholder');
        if (!stage) return;

        // Create zoom trigger
        var trigger = document.createElement('button');
        trigger.className = 'awa-pdp-zoom-trigger';
        trigger.setAttribute('type', 'button');
        trigger.setAttribute('aria-label', 'Ampliar imagem');
        trigger.setAttribute('title', 'Ampliar imagem');
        trigger.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6"/><path d="M8 11h6"/></svg>';
        stage.style.position = 'relative';
        stage.appendChild(trigger);

        // Create overlay
        var overlay = document.createElement('div');
        overlay.className = 'awa-pdp-zoom-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-label', 'Galeria de imagens ampliada');
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML =
            '<button class="awa-pdp-zoom-close" aria-label="Fechar" title="Fechar">&times;</button>' +
            '<button class="awa-pdp-zoom-nav awa-pdp-zoom-nav--prev" aria-label="Imagem anterior" title="Anterior">&#8249;</button>' +
            '<img src="" alt="Imagem ampliada do produto" />' +
            '<button class="awa-pdp-zoom-nav awa-pdp-zoom-nav--next" aria-label="Próxima imagem" title="Próxima">&#8250;</button>';
        document.body.appendChild(overlay);

        var zoomImg = overlay.querySelector('img');
        var images = [];
        var currentIdx = 0;

        function getImages() {
            var imgs = [];
            var fotoramaStage = mediaCol.querySelector('.fotorama');
            if (fotoramaStage && typeof jQuery !== 'undefined') {
                try {
                    var fotorama = jQuery(fotoramaStage).data('fotorama');
                    if (fotorama && fotorama.data) {
                        fotorama.data.forEach(function (item) {
                            if (item.full) imgs.push(item.full);
                            else if (item.img) imgs.push(item.img);
                        });
                    }
                } catch (e) { /* fallback below */ }
            }
            if (!imgs.length) {
                mediaCol.querySelectorAll('.fotorama__stage__frame img').forEach(function (img) {
                    var src = img.getAttribute('src') || img.getAttribute('data-src');
                    if (src && src.indexOf('placeholder') === -1) imgs.push(src);
                });
            }
            if (!imgs.length) {
                var mainImg = mediaCol.querySelector('img');
                if (mainImg) imgs.push(mainImg.src);
            }
            return imgs;
        }

        function showZoom(idx) {
            images = getImages();
            if (!images.length) return;
            currentIdx = Math.max(0, Math.min(idx || 0, images.length - 1));
            zoomImg.src = images[currentIdx];
            zoomImg.alt = 'Imagem ' + (currentIdx + 1) + ' de ' + images.length;
            overlay.classList.add('active');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function hideZoom() {
            overlay.classList.remove('active');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            trigger.focus();
        }

        function nextImage() {
            if (images.length > 1) {
                currentIdx = (currentIdx + 1) % images.length;
                zoomImg.src = images[currentIdx];
                zoomImg.alt = 'Imagem ' + (currentIdx + 1) + ' de ' + images.length;
            }
        }

        function prevImage() {
            if (images.length > 1) {
                currentIdx = (currentIdx - 1 + images.length) % images.length;
                zoomImg.src = images[currentIdx];
                zoomImg.alt = 'Imagem ' + (currentIdx + 1) + ' de ' + images.length;
            }
        }

        trigger.addEventListener('click', function () { showZoom(0); });
        overlay.querySelector('.awa-pdp-zoom-close').addEventListener('click', hideZoom);
        overlay.querySelector('.awa-pdp-zoom-nav--prev').addEventListener('click', prevImage);
        overlay.querySelector('.awa-pdp-zoom-nav--next').addEventListener('click', nextImage);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) hideZoom();
        });

        document.addEventListener('keydown', function (e) {
            if (!overlay.classList.contains('active')) return;
            if (e.key === 'Escape') hideZoom();
            if (e.key === 'ArrowLeft') prevImage();
            if (e.key === 'ArrowRight') nextImage();
        });
    }

    /* ========================================
       2. TABS SYSTEM
       ======================================== */
    function initTabs() {
        var tabContainer = document.querySelector('.awa-pdp-tabs');
        if (!tabContainer) return;

        var tabBtns = tabContainer.querySelectorAll('.awa-pdp-tabs__nav-btn');
        var tabPanels = tabContainer.querySelectorAll('.awa-pdp-tabs__panel');

        function activateTab(idx) {
            tabBtns.forEach(function (btn, i) {
                var isActive = i === idx;
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                btn.setAttribute('tabindex', isActive ? '0' : '-1');
            });
            tabPanels.forEach(function (panel, i) {
                var isActive = i === idx;
                panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                panel.classList.toggle('active', isActive);
            });
        }

        tabBtns.forEach(function (btn, i) {
            btn.addEventListener('click', function () { activateTab(i); });
            btn.addEventListener('keydown', function (e) {
                var target = -1;
                if (e.key === 'ArrowRight') target = (i + 1) % tabBtns.length;
                if (e.key === 'ArrowLeft') target = (i - 1 + tabBtns.length) % tabBtns.length;
                if (e.key === 'Home') target = 0;
                if (e.key === 'End') target = tabBtns.length - 1;
                if (target >= 0) {
                    e.preventDefault();
                    tabBtns[target].focus();
                    activateTab(target);
                }
            });
        });

        // Auto-activate first tab
        activateTab(0);
    }

    /* ========================================
       3. SHARE FUNCTIONALITY
       ======================================== */
    function initShare() {
        var shareLinks = document.querySelectorAll('[data-awa-share]');
        shareLinks.forEach(function (el) {
            el.addEventListener('click', function (e) {
                var action = el.getAttribute('data-awa-share');
                var url = encodeURIComponent(window.location.href);
                var title = encodeURIComponent(document.title);

                if (action === 'whatsapp') {
                    window.open('https://wa.me/?text=' + title + '%20' + url, '_blank', 'noopener');
                } else if (action === 'email') {
                    window.location.href = 'mailto:?subject=' + title + '&body=' + url;
                } else if (action === 'copy') {
                    e.preventDefault();
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(window.location.href).then(function () {
                            var original = el.textContent;
                            el.textContent = 'Copiado!';
                            setTimeout(function () { el.textContent = original; }, 2000);
                        });
                    }
                }
            });
        });
    }

    /* ========================================
       4. LAZY LOADING
       ======================================== */
    function initLazyLoad() {
        if ('IntersectionObserver' in window) {
            var lazyImages = document.querySelectorAll('.catalog-product-view img[data-src]');
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        img.src = img.getAttribute('data-src');
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                });
            }, { rootMargin: '200px' });

            lazyImages.forEach(function (img) { observer.observe(img); });
        }
    }

    /* ========================================
       5. SMOOTH SCROLL ON TAB ANCHORS
       ======================================== */
    function initSmoothScroll() {
        document.querySelectorAll('.catalog-product-view a[href^="#"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                var target = document.querySelector(a.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
                    target.focus({ preventScroll: true });
                }
            });
        });
    }

    /* ========================================
       INIT ALL
       ======================================== */
    ready(function () {
        if (!document.body.classList.contains('catalog-product-view')) return;

        // Wait for Fotorama to initialize before adding zoom
        var fotoramaCheck = setInterval(function () {
            var fotorama = document.querySelector('.fotorama__stage');
            if (fotorama) {
                clearInterval(fotoramaCheck);
                initImageZoom();
            }
        }, 300);
        // Timeout after 10s
        setTimeout(function () { clearInterval(fotoramaCheck); initImageZoom(); }, 10000);

        initTabs();
        initShare();
        initLazyLoad();
        initSmoothScroll();
    });
})();
