!function () {
	'use strict';

	let mobileMaxWidth = 991;

	function initStickyHeader() {
		let header = document.getElementById('header') ||
			document.querySelector('.awa-site-header__shell') ||
			document.querySelector('.header-container') ||
			document.querySelector('header[role="banner"]');

		if (!header) {
			return;
		}

		let topHeader = header.querySelector('.top-header');
		let stickyClass = 'awa-header-sticky';
		let hiddenClass = 'awa-header--hidden';
		let condensedClass = 'awa-header-condensed';
		let threshold = 80;
		let hideThreshold = 200;
		let scrollDelta = 8;
		let isSticky = false;
		let isHidden = false;
		let lastScrollY = 0;
		let cachedHeight = 0;
		let ticking = false;
		let passiveSupported = false;

		try {
			let passiveProbe = Object.defineProperty({}, 'passive', {
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
				document.body.classList.remove(condensedClass);
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

			let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
			let diff = scrollTop - lastScrollY;

			/* --- Sticky toggle --- */
			if (scrollTop > threshold && !isSticky) {
				cachedHeight = header.offsetHeight;
				header.classList.add(stickyClass);
				document.body.classList.add(condensedClass);
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
