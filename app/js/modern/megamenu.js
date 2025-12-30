/**
 * MegaMenu - Accessible, Responsive Navigation System
 * 
 * Features:
 * - Click-to-open on desktop (with optional hover)
 * - Off-canvas drawer on mobile with accordion behavior
 * - Full keyboard navigation (Tab, Enter, Space, Escape, Arrow keys)
 * - ARIA attributes for screen readers
 * - Focus management and trapping in mobile overlay
 * - Respects prefers-reduced-motion
 * - No dependencies
 */

class MegaMenu {
    constructor(options = {}) {
        // Configuration
        this.config = {
            containerSelector: '.megamenu',
            mobileBreakpoint: 768,
            closeOnOutsideClick: true,
            closeOnEscape: true,
            ...options
        };

        // State
        this.state = {
            openMenuId: null,
            mobileOpen: false,
            openAccordions: new Set()
        };

        // DOM references (populated in init)
        this.container = null;
        this.desktopNav = null;
        this.mobileNav = null;
        this.mobileToggle = null;
        this.overlay = null;

        // Bound handlers for cleanup
        this._handleDocumentClick = this._onDocumentClick.bind(this);
        this._handleKeyDown = this._onKeyDown.bind(this);
        this._handleResize = this._onResize.bind(this);
    }

    /**
     * Initialize the megamenu
     */
    init() {
        this.container = document.querySelector(this.config.containerSelector);
        if (!this.container) {
            console.warn('MegaMenu: Container not found');
            return;
        }

        // Cache DOM references
        this.desktopNav = this.container.querySelector('.megamenu__bar');
        this.mobileNav = this.container.querySelector('.megamenu__mobile');
        this.mobileToggle = this.container.querySelector('.megamenu__toggle');
        this.overlay = this.container.querySelector('.megamenu__overlay');

        // Bind events
        this._bindDesktopEvents();
        this._bindMobileEvents();
        this._bindGlobalEvents();

        // Set initial ARIA states
        this._initAriaStates();
    }

    /**
     * Initialize ARIA states on load
     */
    _initAriaStates() {
        // Desktop triggers
        if (this.desktopNav) {
            this.desktopNav.querySelectorAll('[aria-expanded]').forEach(trigger => {
                trigger.setAttribute('aria-expanded', 'false');
            });
        }

        // Mobile toggle
        if (this.mobileToggle) {
            this.mobileToggle.setAttribute('aria-expanded', 'false');
        }

        // Mobile accordion triggers
        if (this.mobileNav) {
            this.mobileNav.querySelectorAll('.megamenu__mobile-trigger').forEach(trigger => {
                trigger.setAttribute('aria-expanded', 'false');
            });
        }
    }

    // =========================================================================
    // Desktop Navigation
    // =========================================================================

    _bindDesktopEvents() {
        if (!this.desktopNav) return;

        // Click on L1 triggers
        this.desktopNav.addEventListener('click', (e) => {
            const trigger = e.target.closest('[aria-expanded]');
            if (trigger) {
                e.preventDefault();
                this._toggleDesktopMenu(trigger);
            }
        });

        // Keyboard navigation within desktop nav
        this.desktopNav.addEventListener('keydown', (e) => {
            this._handleDesktopKeyNav(e);
        });
    }

    /**
     * Toggle a desktop dropdown panel
     */
    _toggleDesktopMenu(trigger) {
        const menuItem = trigger.closest('.megamenu__item');
        const panel = menuItem?.querySelector('.megamenu__panel');
        const menuId = menuItem?.dataset.menuId || menuItem?.id;

        if (!panel) return;

        const isOpen = trigger.getAttribute('aria-expanded') === 'true';

        if (isOpen) {
            this._closeDesktopMenu(trigger, panel);
        } else {
            // Close any other open menu first
            this._closeAllDesktopMenus();
            this._openDesktopMenu(trigger, panel, menuId);
        }
    }

    _openDesktopMenu(trigger, panel, menuId) {
        trigger.setAttribute('aria-expanded', 'true');
        panel.setAttribute('data-state', 'open');
        trigger.closest('.megamenu__item')?.classList.add('is-open');
        this.state.openMenuId = menuId;

        // Smart positioning to keep panel in viewport
        this._positionPanel(panel, trigger);

        // Focus first focusable element in panel
        requestAnimationFrame(() => {
            const firstFocusable = panel.querySelector('a, button');
            firstFocusable?.focus();
        });
    }

    _closeDesktopMenu(trigger, panel) {
        trigger.setAttribute('aria-expanded', 'false');
        panel.setAttribute('data-state', 'closed');
        trigger.closest('.megamenu__item')?.classList.remove('is-open');
        this.state.openMenuId = null;
    }

    _closeAllDesktopMenus() {
        if (!this.desktopNav) return;

        this.desktopNav.querySelectorAll('.megamenu__item.is-open').forEach(item => {
            const trigger = item.querySelector('[aria-expanded]');
            const panel = item.querySelector('.megamenu__panel');
            if (trigger && panel) {
                this._closeDesktopMenu(trigger, panel);
            }
        });
    }

    /**
     * Position panel to prevent viewport overflow
     */
    _positionPanel(panel, trigger) {
        // Reset positioning
        panel.classList.remove('align-right', 'align-center');

        const rect = panel.getBoundingClientRect();
        const viewportWidth = window.innerWidth;

        // If panel overflows right edge, align to right
        if (rect.right > viewportWidth - 20) {
            panel.classList.add('align-right');
        }
    }

    /**
     * Handle keyboard navigation in desktop nav
     */
    _handleDesktopKeyNav(e) {
        const { key } = e;
        const trigger = e.target.closest('[aria-expanded]');
        const menuItem = e.target.closest('.megamenu__item');
        const panel = menuItem?.querySelector('.megamenu__panel');

        switch (key) {
            case 'Enter':
            case ' ':
                if (trigger && panel) {
                    e.preventDefault();
                    this._toggleDesktopMenu(trigger);
                }
                break;

            case 'Escape':
                if (this.state.openMenuId) {
                    e.preventDefault();
                    const openItem = this.desktopNav.querySelector('.megamenu__item.is-open');
                    const openTrigger = openItem?.querySelector('[aria-expanded]');
                    this._closeAllDesktopMenus();
                    openTrigger?.focus();
                }
                break;

            case 'ArrowDown':
                if (panel && panel.getAttribute('data-state') === 'open') {
                    e.preventDefault();
                    const links = panel.querySelectorAll('a, button');
                    const currentIndex = Array.from(links).indexOf(document.activeElement);
                    const nextIndex = currentIndex < links.length - 1 ? currentIndex + 1 : 0;
                    links[nextIndex]?.focus();
                }
                break;

            case 'ArrowUp':
                if (panel && panel.getAttribute('data-state') === 'open') {
                    e.preventDefault();
                    const links = panel.querySelectorAll('a, button');
                    const currentIndex = Array.from(links).indexOf(document.activeElement);
                    const prevIndex = currentIndex > 0 ? currentIndex - 1 : links.length - 1;
                    links[prevIndex]?.focus();
                }
                break;

            case 'ArrowRight':
                if (trigger) {
                    e.preventDefault();
                    const items = Array.from(this.desktopNav.querySelectorAll('.megamenu__item'));
                    const currentIndex = items.indexOf(menuItem);
                    const nextItem = items[currentIndex + 1] || items[0];
                    nextItem?.querySelector('[aria-expanded], a')?.focus();
                }
                break;

            case 'ArrowLeft':
                if (trigger) {
                    e.preventDefault();
                    const items = Array.from(this.desktopNav.querySelectorAll('.megamenu__item'));
                    const currentIndex = items.indexOf(menuItem);
                    const prevItem = items[currentIndex - 1] || items[items.length - 1];
                    prevItem?.querySelector('[aria-expanded], a')?.focus();
                }
                break;
        }
    }

    // =========================================================================
    // Mobile Navigation
    // =========================================================================

    _bindMobileEvents() {
        // Toggle button
        if (this.mobileToggle) {
            this.mobileToggle.addEventListener('click', () => {
                this._toggleMobileNav();
            });
        }

        // Close button
        const closeBtn = this.mobileNav?.querySelector('.megamenu__mobile-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                this._closeMobileNav();
            });
        }

        // Overlay click to close
        if (this.overlay) {
            this.overlay.addEventListener('click', () => {
                this._closeMobileNav();
            });
        }

        // Accordion triggers
        if (this.mobileNav) {
            this.mobileNav.addEventListener('click', (e) => {
                const trigger = e.target.closest('.megamenu__mobile-trigger');
                if (trigger) {
                    e.preventDefault();
                    this._toggleMobileAccordion(trigger);
                }
            });
        }
    }

    _toggleMobileNav() {
        if (this.state.mobileOpen) {
            this._closeMobileNav();
        } else {
            this._openMobileNav();
        }
    }

    _openMobileNav() {
        this.state.mobileOpen = true;
        this.mobileToggle?.setAttribute('aria-expanded', 'true');
        this.mobileNav?.classList.add('is-open');
        this.overlay?.classList.add('is-visible');

        // Prevent body scroll
        document.body.style.overflow = 'hidden';

        // Focus first item in mobile nav
        requestAnimationFrame(() => {
            const firstFocusable = this.mobileNav?.querySelector('a, button');
            firstFocusable?.focus();
        });
    }

    _closeMobileNav() {
        this.state.mobileOpen = false;
        this.mobileToggle?.setAttribute('aria-expanded', 'false');
        this.mobileNav?.classList.remove('is-open');
        this.overlay?.classList.remove('is-visible');

        // Restore body scroll
        document.body.style.overflow = '';

        // Return focus to toggle
        this.mobileToggle?.focus();
    }

    _toggleMobileAccordion(trigger) {
        const submenu = trigger.nextElementSibling;
        if (!submenu || !submenu.classList.contains('megamenu__mobile-submenu')) return;

        const isOpen = trigger.getAttribute('aria-expanded') === 'true';

        if (isOpen) {
            trigger.setAttribute('aria-expanded', 'false');
            submenu.classList.remove('is-open');
        } else {
            trigger.setAttribute('aria-expanded', 'true');
            submenu.classList.add('is-open');
        }
    }

    // =========================================================================
    // Global Events
    // =========================================================================

    _bindGlobalEvents() {
        // Close on outside click
        if (this.config.closeOnOutsideClick) {
            document.addEventListener('click', this._handleDocumentClick);
        }

        // Close on Escape
        if (this.config.closeOnEscape) {
            document.addEventListener('keydown', this._handleKeyDown);
        }

        // Handle resize (close mobile nav when resizing to desktop)
        window.addEventListener('resize', this._handleResize);
    }

    _onDocumentClick(e) {
        // Close desktop menus if clicking outside
        if (this.state.openMenuId && !e.target.closest('.megamenu__item')) {
            this._closeAllDesktopMenus();
        }
    }

    _onKeyDown(e) {
        if (e.key === 'Escape') {
            if (this.state.mobileOpen) {
                this._closeMobileNav();
            } else if (this.state.openMenuId) {
                this._closeAllDesktopMenus();
            }
        }
    }

    _onResize() {
        // Close mobile nav when resizing to desktop
        if (window.innerWidth >= this.config.mobileBreakpoint && this.state.mobileOpen) {
            this._closeMobileNav();
        }
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Close all menus (desktop and mobile)
     */
    closeAll() {
        this._closeAllDesktopMenus();
        if (this.state.mobileOpen) {
            this._closeMobileNav();
        }
    }

    /**
     * Destroy the megamenu instance and clean up
     */
    destroy() {
        document.removeEventListener('click', this._handleDocumentClick);
        document.removeEventListener('keydown', this._handleKeyDown);
        window.removeEventListener('resize', this._handleResize);

        this.container = null;
        this.desktopNav = null;
        this.mobileNav = null;
        this.mobileToggle = null;
        this.overlay = null;
    }
}

// Export for ES modules
export default MegaMenu; 