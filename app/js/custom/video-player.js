/**
 * =============================================================================
 * Video Player Module - Modern, No jQuery
 * =============================================================================
 * Unified video handling for embedded and self-hosted videos.
 * 
 * FEATURES:
 * - YouTube/Vimeo embed support
 * - Self-hosted MP4 support
 * - Autoplay with accessibility safeguards
 * - Respects prefers-reduced-motion (pauses autoplay)
 * - Lazy loading for performance
 * 
 * USAGE:
 * <div class="pf-video" data-video-src="https://youtube.com/embed/..." data-autoplay="true">
 *   <div class="pf-video__player"></div>
 * </div>
 * 
 * Or for self-hosted:
 * <div class="pf-video pf-video--native" data-video-src="/path/to/video.mp4">
 *   <video class="pf-video__player"></video>
 * </div>
 * 
 * API:
 *   window.VideoPlayer.play(containerId)
 *   window.VideoPlayer.pause(containerId)
 *   window.VideoPlayer.changeSource(containerId, newSrc)
 * =============================================================================
 */

(function() {
    'use strict';

    // Prevent double initialization
    if (window.VideoPlayerInitialized) return;
    window.VideoPlayerInitialized = true;

    class VideoPlayer {
        constructor() {
            this.prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            this.players = new Map();
        }

        init() {
            // Find all video containers
            const videoContainers = document.querySelectorAll('.pf-video, .video-wrap');
            
            videoContainers.forEach(container => {
                this._initializePlayer(container);
            });

            // Expose API
            window.VideoPlayer = {
                play: (id) => this.play(id),
                pause: (id) => this.pause(id),
                changeSource: (id, src) => this.changeSource(id, src)
            };
        }

        _initializePlayer(container) {
            const id = container.id || `video-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
            container.id = id;

            const src = container.dataset.videoSrc;
            const autoplay = container.dataset.autoplay === 'true';
            const isNative = container.classList.contains('pf-video--native');

            // Store player reference
            this.players.set(id, {
                container,
                src,
                autoplay,
                isNative,
                loaded: false
            });

            // If autoplay requested but user prefers reduced motion, don't autoplay
            if (autoplay && !this.prefersReducedMotion) {
                this._loadPlayer(id);
            }

            // Setup lazy loading via IntersectionObserver
            if (!autoplay) {
                this._setupLazyLoad(id);
            }
        }

        _setupLazyLoad(id) {
            const player = this.players.get(id);
            if (!player) return;

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !player.loaded) {
                        this._loadPlayer(id);
                        observer.disconnect();
                    }
                });
            }, { rootMargin: '100px' });

            observer.observe(player.container);
        }

        _loadPlayer(id) {
            const player = this.players.get(id);
            if (!player || player.loaded) return;

            const playerEl = player.container.querySelector('.pf-video__player, iframe, video');
            
            if (player.isNative) {
                this._loadNativeVideo(player, playerEl);
            } else {
                this._loadEmbedVideo(player, playerEl);
            }

            player.loaded = true;
        }

        _loadNativeVideo(player, videoEl) {
            if (!videoEl) {
                videoEl = document.createElement('video');
                videoEl.className = 'pf-video__player';
                player.container.appendChild(videoEl);
            }

            videoEl.src = player.src;
            videoEl.controls = true;
            videoEl.playsInline = true;
            
            if (player.autoplay && !this.prefersReducedMotion) {
                videoEl.autoplay = true;
                videoEl.muted = true; // Required for autoplay
            }
        }

        _loadEmbedVideo(player, iframeEl) {
            if (!iframeEl || iframeEl.tagName !== 'IFRAME') {
                iframeEl = document.createElement('iframe');
                iframeEl.className = 'pf-video__player';
                iframeEl.setAttribute('allowfullscreen', '');
                iframeEl.setAttribute('frameborder', '0');
                player.container.appendChild(iframeEl);
            }

            let src = player.src;
            
            // Add autoplay parameter if needed
            if (player.autoplay && !this.prefersReducedMotion) {
                const separator = src.includes('?') ? '&' : '?';
                if (src.includes('youtube') || src.includes('youtu.be')) {
                    src += `${separator}autoplay=1&mute=1`;
                } else if (src.includes('vimeo')) {
                    src += `${separator}autoplay=1&muted=1`;
                }
            }

            iframeEl.src = src;
        }

        play(id) {
            const player = this.players.get(id);
            if (!player) return;

            const videoEl = player.container.querySelector('video');
            if (videoEl) {
                videoEl.play();
            }
        }

        pause(id) {
            const player = this.players.get(id);
            if (!player) return;

            const videoEl = player.container.querySelector('video');
            if (videoEl) {
                videoEl.pause();
            }
        }

        changeSource(id, newSrc) {
            const player = this.players.get(id);
            if (!player) return;

            player.src = newSrc;
            player.loaded = false;
            this._loadPlayer(id);
        }
    }

    // Legacy compatibility: expose playSessionVideo for existing code
    window.playSessionVideo = function(url, title) {
        const iframe = document.getElementById('video-player');
        if (iframe) {
            iframe.src = url;
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new VideoPlayer().init());
    } else {
        new VideoPlayer().init();
    }
})();
