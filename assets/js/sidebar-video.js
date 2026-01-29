/**
 * Sidebar Video Player
 * 
 * Handles click events on video links in the events sidebar.
 * Loads video into player container and scrolls to it with autoplay.
 */
(function() {
    'use strict';

    var PLAYER_CONTAINER_ID = 'events-sidebar-video-player';

    /**
     * Parse video URL and return embed info
     * @param {string} url - Video URL
     * @returns {object} - { type: 'youtube'|'vimeo'|'direct', embedUrl: string }
     */
    function parseVideoUrl(url) {
        if (!url) return null;

        // YouTube patterns
        var youtubeMatch = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
        if (youtubeMatch) {
            return {
                type: 'youtube',
                videoId: youtubeMatch[1],
                embedUrl: 'https://www.youtube.com/embed/' + youtubeMatch[1] + '?autoplay=1&rel=0'
            };
        }

        // Vimeo patterns
        var vimeoMatch = url.match(/(?:vimeo\.com\/(?:video\/)?|player\.vimeo\.com\/video\/)(\d+)/);
        if (vimeoMatch) {
            return {
                type: 'vimeo',
                videoId: vimeoMatch[1],
                embedUrl: 'https://player.vimeo.com/video/' + vimeoMatch[1] + '?autoplay=1'
            };
        }

        // Direct video file (mp4, webm, etc.)
        if (/\.(mp4|webm|ogg|mov)(\?|$)/i.test(url)) {
            return {
                type: 'direct',
                embedUrl: url
            };
        }

        // Unknown - try as iframe
        return {
            type: 'iframe',
            embedUrl: url
        };
    }

    /**
     * Create embed HTML for video
     * @param {object} videoInfo - Parsed video info
     * @returns {string} - HTML string
     */
    function createEmbedHtml(videoInfo) {
        if (!videoInfo) return '';

        if (videoInfo.type === 'direct') {
            return '<video controls autoplay playsinline style="width:100%;max-width:100%;aspect-ratio:16/9;">' +
                   '<source src="' + escapeHtml(videoInfo.embedUrl) + '" type="video/mp4">' +
                   'Your browser does not support the video tag.' +
                   '</video>';
        }

        // iframe embed for YouTube, Vimeo, or unknown
        return '<iframe src="' + escapeHtml(videoInfo.embedUrl) + '" ' +
               'style="width:100%;aspect-ratio:16/9;border:none;" ' +
               'allow="autoplay; fullscreen; picture-in-picture" ' +
               'allowfullscreen></iframe>';
    }

    /**
     * Escape HTML special characters
     * @param {string} str - String to escape
     * @returns {string} - Escaped string
     */
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Handle video link click
     * @param {Event} e - Click event
     */
    function handleVideoClick(e) {
        var link = e.target.closest('[data-video-url]');
        if (!link) return;

        e.preventDefault();

        var videoUrl = link.getAttribute('data-video-url');
        var container = document.getElementById(PLAYER_CONTAINER_ID);

        if (!container) {
            console.warn('Video player container not found: #' + PLAYER_CONTAINER_ID);
            // Fallback: open in new tab
            window.open(videoUrl, '_blank');
            return;
        }

        var videoInfo = parseVideoUrl(videoUrl);
        var embedHtml = createEmbedHtml(videoInfo);

        // Insert embed
        container.innerHTML = embedHtml;
        container.style.display = 'block';

        // Scroll to player
        container.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // Add active class for styling
        container.classList.add('is-active');
    }

    /**
     * Initialize event listeners
     */
    function init() {
        // Use event delegation on document for dynamic content
        document.addEventListener('click', handleVideoClick);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
