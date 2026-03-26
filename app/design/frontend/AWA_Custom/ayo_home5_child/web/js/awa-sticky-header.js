!function () {
	'use strict';

	var mobileMaxWidth = 767;

	function initStickyHeader() {
		var header = document.getElementById('header') ||
			document.querySelector('.header-container') ||
			document.querySelector('header[role="banner"]');

		if (!header) {
			return;
		}

		var topHeader = header.querySelector('.top-header');
		var stickyClass = 'awa-header-sticky';
		var threshold = 80;
		var isSticky = false;
		var cachedHeight = 0;
		var ticking = false;
		var passiveSupported = false;

		try {
			var passiveProbe = Object.defineProperty({}, 'passive', {
				get: function () {
					passiveSupported = true;
					return true;
				}
			});
			window.addEventListener('awaPassiveTest', null, passiveProbe);
		} catch (error) {
			passiveSupported = false;
		}

		function isMobileViewport() {
			return window.matchMedia('(max-width: ' + mobileMaxWidth + 'px)').matches;
		}

		function clearStickyState() {
			if (isSticky || header.classList.contains(stickyClass)) {
				header.classList.remove(stickyClass);
			}

			if (document.body.style.paddingTop !== '') {
				document.body.style.paddingTop = '';
			}

			isSticky = false;
		}

		function recalcThreshold() {
			threshold = topHeader ? topHeader.offsetHeight + 20 : 80;

			if (isSticky) {
				cachedHeight = header.offsetHeight;
				document.body.style.paddingTop = cachedHeight + 'px';
			}
		}

		function updateStickyState() {
			if (isMobileViewport()) {
				clearStickyState();
				return;
			}

			var scrollTop = window.pageYOffset || document.documentElement.scrollTop;

			if (scrollTop > threshold && !isSticky) {
				cachedHeight = header.offsetHeight;
				header.classList.add(stickyClass);
				document.body.style.paddingTop = cachedHeight + 'px';
				isSticky = true;
				return;
			}

			if (scrollTop <= threshold && isSticky) {
				clearStickyState();
			}
		}

		recalcThreshold();

		window.addEventListener('scroll', function () {
			if (ticking) {
				return;
			}

			ticking = true;
			window.requestAnimationFrame(function () {
				updateStickyState();
				ticking = false;
			});
		}, passiveSupported ? { passive: true } : false);

		window.addEventListener('resize', function () {
			recalcThreshold();
			updateStickyState();
		}, passiveSupported ? { passive: true } : false);

		updateStickyState();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initStickyHeader);
		return;
	}

	initStickyHeader();
}();
