/**
 * =============================================================================
 * Header Offset Utility - Modern, No jQuery
 * =============================================================================
 * Dynamically measures the fixed header height (including sectionbar) and 
 * updates CSS variables so content doesn't render underneath the header.
 * 
 * Also accounts for WP admin bar when logged in.
 * 
 * USAGE:
 * Automatically initializes on DOMContentLoaded.
 * Updates on: resize, orientationchange, menu open/close, ResizeObserver
 * 
 * CSS Variables Set:
 *   --site-header-h: Header element height in px (megamenu only)
 *   --sectionbar-h: Sectionbar height in px (0 if not present)
 *   --admin-bar-h: WP admin bar height in px
 *   --total-header-offset: Header + sectionbar + admin bar combined
 * =============================================================================
 */

(function() {
    'use strict';

    // Prevent double initialization
    if (window.HeaderOffsetInitialized) return;
    window.HeaderOffsetInitialized = true;

    class HeaderOffset {
        constructor() {
            this.header = null;
            this.sectionbar = null;
            this.adminBar = null;
            this.resizeObserver = null;
            this.debounceTimer = null;
        }

        init() {
            // Find header element - try multiple selectors for robustness
            this.header = document.getElementById('header') 
                       || document.querySelector('.megamenu')
                       || document.querySelector('header');
            
            // Find sectionbar (L2 menu bar) if present
            this.sectionbar = document.querySelector('.sectionbar');
            
            // Find WP admin bar if present
            this.adminBar = document.getElementById('wpadminbar');

            if (!this.header) {
                console.warn('HeaderOffset: No header element found');
                return;
            }

            // Initial measurement
            this._updateOffset();

            // Setup observers and listeners
            this._setupResizeObserver();
            this._bindEvents();
        }

        _updateOffset() {
            if (!this.header) return;

            // Measure header height (includes sectionbar if it's inside #header)
            const headerRect = this.header.getBoundingClientRect();
            const headerHeight = Math.ceil(headerRect.height);

            // Measure sectionbar separately for the CSS variable (informational only)
            let sectionbarHeight = 0;
            if (this.sectionbar) {
                const sectionbarRect = this.sectionbar.getBoundingClientRect();
                if (sectionbarRect.height > 0 && getComputedStyle(this.sectionbar).display !== 'none') {
                    sectionbarHeight = Math.ceil(sectionbarRect.height);
                }
            }

            // Measure admin bar if present
            let adminBarHeight = 0;
            if (this.adminBar && document.body.classList.contains('admin-bar')) {
                const adminBarRect = this.adminBar.getBoundingClientRect();
                adminBarHeight = Math.ceil(adminBarRect.height);
            }

            // Total offset: header already includes sectionbar (it's nested inside #header)
            // Only add admin bar separately since it's outside #header
            const totalOffset = headerHeight + adminBarHeight;

            // Set CSS variables on document root
            const root = document.documentElement;
            root.style.setProperty('--site-header-h', headerHeight + 'px');
            root.style.setProperty('--sectionbar-h', sectionbarHeight + 'px');
            root.style.setProperty('--admin-bar-h', adminBarHeight + 'px');
            root.style.setProperty('--total-header-offset', totalOffset + 'px');
        }

        _setupResizeObserver() {
            // Use ResizeObserver if available (preferred - auto-updates on any size change)
            if ('ResizeObserver' in window) {
                this.resizeObserver = new ResizeObserver(() => {
                    this._debouncedUpdate();
                });

                this.resizeObserver.observe(this.header);

                // Also observe sectionbar if present
                if (this.sectionbar) {
                    this.resizeObserver.observe(this.sectionbar);
                }

                // Also observe admin bar if present
                if (this.adminBar) {
                    this.resizeObserver.observe(this.adminBar);
                }
            }
        }

        _bindEvents() {
            // Fallback/additional listeners for resize and orientation change
            window.addEventListener('resize', () => this._debouncedUpdate(), { passive: true });
            window.addEventListener('orientationchange', () => this._debouncedUpdate(), { passive: true });

            // Listen for megamenu state changes (custom event from megamenu-controller.js)
            document.addEventListener('megamenu:opened', () => this._debouncedUpdate());
            document.addEventListener('megamenu:closed', () => this._debouncedUpdate());

            // Also listen for class changes on body that might indicate menu state
            // This catches the .megamenu-open class toggle
            if ('MutationObserver' in window) {
                const bodyObserver = new MutationObserver((mutations) => {
                    for (const mutation of mutations) {
                        if (mutation.attributeName === 'class') {
                            this._debouncedUpdate();
                            break;
                        }
                    }
                });

                bodyObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
            }
        }

        _debouncedUpdate() {
            // Debounce rapid updates
            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }
            this.debounceTimer = setTimeout(() => {
                this._updateOffset();
            }, 50);
        }

        // Public method to force update (can be called externally if needed)
        update() {
            this._updateOffset();
        }
    }

    // Create global instance for external access
    const headerOffset = new HeaderOffset();

    // Expose update method globally
    window.updateHeaderOffset = () => headerOffset.update();

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => headerOffset.init());
    } else {
        headerOffset.init();
    }
})();
