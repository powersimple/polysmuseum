/**
 * Events Profile Index — interaction layer (server-rendered markup).
 * -----------------------------------------------------------------------------
 * The directory (#profile-index) and the sidebar (#appearances) are rendered
 * server-side from data/profile-index.json (see functions-events.php), so they
 * work with JS off and are fully cacheable. This tiny script only wires the
 * clicks: clicking an appearance plays its video and — when clicked in the
 * directory — mirrors that person's appearances into the sidebar.
 *
 * Leading ";" guards against ASI issues from the concatenated main.js build.
 * -----------------------------------------------------------------------------
 */
;(function () {
	'use strict';

	function play(url, title) {
		if (!url) { return; }
		// The URL already carries autoplay=1&rel=0; setting the iframe src right
		// after a user click autoplays. Prefer the events-page player directly,
		// fall back to the theme's playSessionVideo where that exists.
		var player = document.getElementById('video-player');
		if (player) {
			player.src = url;
			var vp = player.closest ? player.closest('.video-position') : null;
			if (vp && vp.style && vp.style.visibility === 'hidden') { vp.style.visibility = 'visible'; }
			return;
		}
		if (typeof window.playSessionVideo === 'function') {
			window.playSessionVideo(url, title || '', '');
		}
	}

	function onClick(e) {
		var t = e.target;
		var a = (t && t.closest) ? t.closest('.appearance-link') : null;
		if (!a) {
			return;
		}
		e.preventDefault();
		play(a.getAttribute('data-video'), a.getAttribute('data-title'));

		// Clicked inside the directory → mirror this person into the sidebar.
		var card    = a.closest('.index-profile');
		var sidebar = document.getElementById('appearances');
		if (card && sidebar) {
			var name = (card.getAttribute('data-name') || '').replace(/[<>&"]/g, '');
			var list = card.querySelector('.appearance-list');
			sidebar.innerHTML =
				'<h4 class="appearances-heading">Appearances by ' + name + '</h4>' +
				(list ? list.outerHTML : '');
		}
	}

	document.addEventListener('click', onClick);
})();

