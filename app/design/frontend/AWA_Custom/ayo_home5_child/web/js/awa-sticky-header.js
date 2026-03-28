!function () {
	'use strict';

	var mobileMaxWidth = 991;

	function initStickyHeader() {
		var header = document.getElementById('header') ||
			document.querySelector('.awa-site-header__shell') ||
			document.querySelector('.header-container') ||
			document.querySelector('header[role="banner"]');

		if (!header) {
			return;
		}

		var topHeader = header.querySelector('.top-header');
		var stickyClass = 'awa-header-sticky';
		var hiddenClass = 'awa-header--hidden';
		var threshold = 80;
		var hideThreshold = 200;
		var scrollDelta = 8;
		var isSticky = false;
		var isHidden = false;
		var lastScrollY = 0;
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
				header.classList.remove(hiddenClass);
				document.body.classList.remove(hiddenClass);
			}

			if (document.body.style.paddingTop !== '') {
				document.body.style.paddingTop = '';
			}

			isSticky = false;
			isHidden = false;
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
			var diff = scrollTop - lastScrollY;

			/* --- Sticky toggle --- */
			if (scrollTop > threshold && !isSticky) {
				cachedHeight = header.offsetHeight;
				header.classList.add(stickyClass);
				document.body.style.paddingTop = cachedHeight + 'px';
				isSticky = true;
			} else if (scrollTop <= threshold && isSticky) {
				clearStickyState();
				lastScrollY = scrollTop;
				return;
			}

			/* --- Hide / Show based on scroll direction --- */
			if (isSticky) {
				if (diff > scrollDelta && scrollTop > hideThreshold && !isHidden) {
					/* Scrolling DOWN past threshold — hide */
					header.classList.add(hiddenClass);
					document.body.classList.add(hiddenClass);
					isHidden = true;
				} else if (diff < -scrollDelta && isHidden) {
					/* Scrolling UP — show */
					header.classList.remove(hiddenClass);
					document.body.classList.remove(hiddenClass);
					isHidden = false;
				}
			}

			lastScrollY = scrollTop;
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
