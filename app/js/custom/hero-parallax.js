/**
 * =============================================================================
 * Hero Parallax Module - Modern, No jQuery
 * =============================================================================
 * Handles parallax scroll effects for hero sections.
 * 
 * FEATURES:
 * - Respects prefers-reduced-motion (WCAG 2.1 AA)
 * - Uses requestAnimationFrame for performance
 * - Supports multiple hero variants via data attributes
 * - Mobile-safe with automatic disable on touch devices
 * 
 * USAGE:
 * Add to any hero element:
 *   <section class="pf-hero pf-hero--parallax" data-parallax-scale="0.0005" data-parallax-fade="0.0005">
 * 
 * CSS classes:
 *   .pf-hero              - Base hero wrapper
 *   .pf-hero--parallax    - Enables parallax effect
 *   .pf-hero--static      - Static image (no effect)
 *   .pf-hero--slideshow   - Slideshow hero
 *   .pf-hero--video       - Video hero
 * =============================================================================
 */

(function() {
    'use strict';

    // Prevent double initialization
    if (window.HeroParallaxInitialized) return;
    window.HeroParallaxInitialized = true;

    class HeroParallax {
        constructor() {
            // Check for reduced motion preference - WCAG 2.1 AA
            this.prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            
            // Disable on touch devices for better mobile performance
            this.isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
            
            this.heroes = [];
            this.ticking = false;
        }

        init() {
            // Skip if user prefers reduced motion
            if (this.prefersReducedMotion) {
                this._applyReducedMotionFallback();
                return;
            }

            // Find all parallax heroes (support both old and new class names)
            // Exclude #dynamic-hero and hero-cover-* elements - transform breaks background scaling
            const parallaxElements = document.querySelectorAll('.pf-hero--parallax, .parallax:not(#dynamic-hero):not(.hero-cover-10):not(.hero-cover-20):not(.hero-cover-25):not(.hero-cover-50)');
            
            if (parallaxElements.length === 0) return;

            parallaxElements.forEach(el => {
                this.heroes.push({
                    element: el,
                    scaleRate: parseFloat(el.dataset.parallaxScale) || 0.0005,
                    fadeRate: parseFloat(el.dataset.parallaxFade) || 0.0005
                });
            });

            this._bindEvents();
        }

        _bindEvents() {
            window.addEventListener('scroll', () => {
                if (!this.ticking) {
                    requestAnimationFrame(() => this._updateParallax());
                    this.ticking = true;
                }
            }, { passive: true });
        }

        _updateParallax() {
            const scroll = window.pageYOffset;

            this.heroes.forEach(hero => {
                const scaleValue = 1 + scroll * hero.scaleRate;
                const opacityValue = Math.max(0, 1 - scroll * hero.fadeRate);

                hero.element.style.transform = `scale(${scaleValue})`;
                hero.element.style.opacity = opacityValue;
            });

            this.ticking = false;
        }

        _applyReducedMotionFallback() {
            // For users who prefer reduced motion, ensure heroes are visible but static
            document.querySelectorAll('.pf-hero--parallax, .parallax').forEach(el => {
                el.style.transform = 'none';
                el.style.opacity = '1';
            });
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new HeroParallax().init());
    } else {
        new HeroParallax().init();
    }
})();
