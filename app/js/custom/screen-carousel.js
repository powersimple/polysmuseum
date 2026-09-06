/**
 * Screen Image Carousel — zero-dependency progressive enhancement.
 * -----------------------------------------------------------------------------
 * Markup is emitted server-side by polys_render_screen_carousel()
 * (functions/functions-screen-carousel.php). This script finds every
 * `.screen-carousel`, enhances it into an accessible carousel with autoplay,
 * prev/next, dots, keyboard, and touch-swipe, and honours reduced-motion.
 *
 * No jQuery. No external libraries. Compiled into main[.min].js via the
 * app/js/custom concatenation step (see vite.config.mjs).
 * -----------------------------------------------------------------------------
 */
/* screen-carousel module */
(function () {
	'use strict';

	var prefersReduced =
		window.matchMedia &&
		window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function initCarousel(root) {
		if (root.__scInit) {
			return;
		}
		root.__scInit = true;

		var slides = Array.prototype.slice.call(
			root.querySelectorAll('.screen-carousel__slide')
		);
		if (!slides.length) {
			return;
		}

		root.classList.add('is-enhanced');

		var index = 0;
		for (var s = 0; s < slides.length; s++) {
			if (slides[s].classList.contains('is-active')) {
				index = s;
				break;
			}
		}

		var prevBtn = root.querySelector('.screen-carousel__nav--prev');
		var nextBtn = root.querySelector('.screen-carousel__nav--next');
		var dotsWrap = root.querySelector('.screen-carousel__dots');
		var dots = [];

		// Single slide: show it, nothing else to wire up.
		if (slides.length < 2) {
			slides[0].classList.add('is-active');
			slides[0].setAttribute('aria-hidden', 'false');
			return;
		}

		// Build dots.
		if (dotsWrap) {
			for (var d = 0; d < slides.length; d++) {
				(function (i) {
					var b = document.createElement('button');
					b.type = 'button';
					b.className = 'screen-carousel__dot';
					b.setAttribute('aria-label', 'Go to image ' + (i + 1));
					b.addEventListener('click', function () {
						go(i);
						restart();
					});
					dotsWrap.appendChild(b);
					dots.push(b);
				})(d);
			}
		}

		function render() {
			for (var i = 0; i < slides.length; i++) {
				var active = i === index;
				slides[i].classList.toggle('is-active', active);
				slides[i].setAttribute('aria-hidden', active ? 'false' : 'true');
			}
			for (var j = 0; j < dots.length; j++) {
				var cur = j === index;
				dots[j].classList.toggle('is-current', cur);
				dots[j].setAttribute('aria-current', cur ? 'true' : 'false');
			}
		}

		function go(n) {
			var len = slides.length;
			var next = ((n % len) + len) % len;
			// Direction drives the 3D-eased entrance (see SCSS [data-dir]).
			root.setAttribute('data-dir', next >= index || (index === len - 1 && next === 0) ? 'next' : 'prev');
			if (index === 0 && next === len - 1) {
				root.setAttribute('data-dir', 'prev');
			}
			index = next;
			render();
		}
		function next() { go(index + 1); }
		function prev() { go(index - 1); }

		if (nextBtn) {
			nextBtn.addEventListener('click', function () { next(); restart(); });
		}
		if (prevBtn) {
			prevBtn.addEventListener('click', function () { prev(); restart(); });
		}

		// Keyboard (when focus is within the carousel).
		root.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight') { next(); restart(); e.preventDefault(); }
			else if (e.key === 'ArrowLeft') { prev(); restart(); e.preventDefault(); }
		});
		if (!root.hasAttribute('tabindex')) {
			root.setAttribute('tabindex', '0');
		}

		// Touch swipe.
		var startX = null;
		root.addEventListener('touchstart', function (e) {
			startX = e.touches[0].clientX;
		}, { passive: true });
		root.addEventListener('touchend', function (e) {
			if (startX === null) { return; }
			var dx = e.changedTouches[0].clientX - startX;
			if (Math.abs(dx) > 40) {
				if (dx < 0) { next(); } else { prev(); }
				restart();
			}
			startX = null;
		}, { passive: true });

		// Autoplay.
		var interval = parseInt(root.getAttribute('data-autoplay'), 10) || 0;
		var timer = null;
		function start() {
			if (prefersReduced || interval <= 0) { return; }
			stop();
			timer = window.setInterval(next, interval);
		}
		function stop() {
			if (timer) { window.clearInterval(timer); timer = null; }
		}
		function restart() { stop(); start(); }

		root.addEventListener('mouseenter', stop);
		root.addEventListener('mouseleave', start);
		root.addEventListener('focusin', stop);
		root.addEventListener('focusout', start);
		document.addEventListener('visibilitychange', function () {
			if (document.hidden) { stop(); } else { start(); }
		});

		render();
		start();
	}

	function initAll() {
		var nodes = document.querySelectorAll('.screen-carousel');
		Array.prototype.forEach.call(nodes, initCarousel);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
})();
