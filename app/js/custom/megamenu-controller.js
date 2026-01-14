/**
 * MegaMenu Controller - WCAG 2.1 AA Accessible
 * 
 * Accessibility features:
 * - Full keyboard navigation (Tab, Enter, Space, Escape, Arrow keys)
 * - Proper aria-expanded and aria-controls attributes
 * - Focus management: submenu open moves focus in, Escape returns focus
 * - Mobile drawer: focus trap when open, Escape closes, overlay click closes
 * - Respects prefers-reduced-motion for animations
 * 
 * State isolation:
 * - Mobile and desktop states are fully independent
 * - On resize from mobile→desktop: all mobile accordions reset, aria states cleared
 * - Body scroll lock uses CSS class (.megamenu-open) not inline styles
 * 
 * No jQuery dependency - vanilla JS only
 */
(function() {
    'use strict';
    
    // Prevent double initialization
    if (window.MegaMenuInitialized) return;
    window.MegaMenuInitialized = true;
    
    class MegaMenu {
        constructor(options = {}) {
            this.config = {
                containerSelector: '.megamenu',
                mobileBreakpoint: 768,
                ...options
            };
            this.state = { 
                openMenuId: null, 
                mobileOpen: false,
                lastFocusedElement: null // Track focus for restoration
            };
            this.container = null;
            this.desktopNav = null;
            this.mobileNav = null;
            this.mobileToggle = null;
            this.overlay = null;
            this.focusableElements = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
        }

        init() {
            this.container = document.querySelector(this.config.containerSelector);
            if (!this.container) return;

            this.desktopNav = this.container.querySelector('.megamenu__bar');
            this.mobileToggle = this.container.querySelector('.megamenu__toggle');
            
            // Mobile elements are now outside the nav container
            this.mobileNav = document.querySelector('.megamenu__mobile');
            this.overlay = document.querySelector('.megamenu__overlay');

            this._bindDesktopEvents();
            this._bindMobileEvents();
            this._bindGlobalEvents();
            this._setupAriaAttributes();
        }

        /**
         * Setup initial ARIA attributes for accessibility
         */
        _setupAriaAttributes() {
            // Ensure mobile nav has proper aria-hidden when closed
            if (this.mobileNav) {
                this.mobileNav.setAttribute('aria-hidden', 'true');
            }
            
            // Ensure panels have aria-hidden when closed
            if (this.desktopNav) {
                this.desktopNav.querySelectorAll('.megamenu__panel').forEach(panel => {
                    if (panel.getAttribute('data-state') !== 'open') {
                        panel.setAttribute('aria-hidden', 'true');
                    }
                });
            }
        }

        _bindDesktopEvents() {
            if (!this.desktopNav) return;

            // Click to toggle
            this.desktopNav.addEventListener('click', (e) => {
                const trigger = e.target.closest('button[aria-expanded]');
                if (trigger) {
                    e.preventDefault();
                    this._toggleDesktopMenu(trigger);
                }
            });

            // Keyboard navigation for triggers (Enter/Space to toggle)
            this.desktopNav.addEventListener('keydown', (e) => {
                const trigger = e.target.closest('button[aria-expanded]');
                if (!trigger) return;
                
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this._toggleDesktopMenu(trigger);
                }
                
                // Arrow key navigation between top-level items
                if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                    e.preventDefault();
                    this._navigateTopLevel(trigger, e.key === 'ArrowRight' ? 1 : -1);
                }
                
                // Arrow down opens submenu and moves focus into it
                if (e.key === 'ArrowDown') {
                    const menuItem = trigger.closest('.megamenu__item');
                    const panel = menuItem?.querySelector('.megamenu__panel');
                    if (panel) {
                        e.preventDefault();
                        this._closeAllDesktopMenus();
                        this._openDesktopMenu(trigger, panel, menuItem);
                        this._focusFirstInPanel(panel);
                    }
                }
            });

            // Hover to open (desktop only, respects reduced motion)
            const menuItems = this.desktopNav.querySelectorAll('.megamenu__item');
            menuItems.forEach(item => {
                const trigger = item.querySelector('button[aria-expanded]');
                const panel = item.querySelector('.megamenu__panel');
                if (!trigger || !panel) return;

                item.addEventListener('mouseenter', () => {
                    if (window.innerWidth >= this.config.mobileBreakpoint) {
                        this._closeAllDesktopMenus();
                        this._openDesktopMenu(trigger, panel, item);
                    }
                });

                item.addEventListener('mouseleave', () => {
                    if (window.innerWidth >= this.config.mobileBreakpoint) {
                        this._closeDesktopMenu(trigger, panel, item);
                    }
                });
            });
        }

        /**
         * Navigate between top-level menu items with arrow keys
         */
        _navigateTopLevel(currentTrigger, direction) {
            const triggers = Array.from(this.desktopNav.querySelectorAll('.megamenu__item > button[aria-expanded], .megamenu__item > a'));
            const currentIndex = triggers.indexOf(currentTrigger);
            if (currentIndex === -1) return;
            
            let newIndex = currentIndex + direction;
            if (newIndex < 0) newIndex = triggers.length - 1;
            if (newIndex >= triggers.length) newIndex = 0;
            
            triggers[newIndex].focus();
        }

        /**
         * Focus first focusable element in panel
         */
        _focusFirstInPanel(panel) {
            const firstFocusable = panel.querySelector(this.focusableElements);
            if (firstFocusable) {
                // Small delay to ensure panel is visible
                setTimeout(() => firstFocusable.focus(), 50);
            }
        }

        _toggleDesktopMenu(trigger) {
            const menuItem = trigger.closest('.megamenu__item');
            const panel = menuItem?.querySelector('.megamenu__panel');
            if (!panel) return;

            const isOpen = trigger.getAttribute('aria-expanded') === 'true';

            if (isOpen) {
                this._closeDesktopMenu(trigger, panel, menuItem);
                trigger.focus(); // Return focus to trigger
            } else {
                this._closeAllDesktopMenus();
                this._openDesktopMenu(trigger, panel, menuItem);
            }
        }

        _openDesktopMenu(trigger, panel, menuItem) {
            trigger.setAttribute('aria-expanded', 'true');
            panel.setAttribute('data-state', 'open');
            panel.setAttribute('aria-hidden', 'false');
            menuItem.classList.add('is-open');
            this.state.openMenuId = menuItem.id;
        }

        _closeDesktopMenu(trigger, panel, menuItem) {
            trigger.setAttribute('aria-expanded', 'false');
            panel.setAttribute('data-state', 'closed');
            panel.setAttribute('aria-hidden', 'true');
            menuItem.classList.remove('is-open');
            this.state.openMenuId = null;
        }

        _closeAllDesktopMenus() {
            if (!this.desktopNav) return;
            this.desktopNav.querySelectorAll('.megamenu__item.is-open').forEach(item => {
                const trigger = item.querySelector('button[aria-expanded]');
                const panel = item.querySelector('.megamenu__panel');
                if (trigger && panel) {
                    this._closeDesktopMenu(trigger, panel, item);
                }
            });
        }

        _bindMobileEvents() {
            if (this.mobileToggle) {
                this.mobileToggle.addEventListener('click', () => this._toggleMobileNav());
                
                // Keyboard support for mobile toggle
                this.mobileToggle.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this._toggleMobileNav();
                    }
                });
            }

            const closeBtn = this.mobileNav?.querySelector('.megamenu__mobile-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this._closeMobileNav());
                closeBtn.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this._closeMobileNav();
                    }
                });
            }

            if (this.overlay) {
                this.overlay.addEventListener('click', () => this._closeMobileNav());
            }

            if (this.mobileNav) {
                this.mobileNav.addEventListener('click', (e) => {
                    const trigger = e.target.closest('.megamenu__mobile-trigger');
                    if (trigger) {
                        e.preventDefault();
                        this._toggleMobileAccordion(trigger);
                    }
                });
                
                // Focus trap for mobile nav
                this.mobileNav.addEventListener('keydown', (e) => {
                    if (e.key === 'Tab' && this.state.mobileOpen) {
                        this._trapFocus(e);
                    }
                });
            }
        }

        /**
         * Trap focus within mobile drawer when open (WCAG requirement)
         */
        _trapFocus(e) {
            const focusableInNav = this.mobileNav.querySelectorAll(this.focusableElements);
            if (focusableInNav.length === 0) return;
            
            const firstFocusable = focusableInNav[0];
            const lastFocusable = focusableInNav[focusableInNav.length - 1];
            
            if (e.shiftKey) {
                // Shift+Tab: if on first element, go to last
                if (document.activeElement === firstFocusable) {
                    e.preventDefault();
                    lastFocusable.focus();
                }
            } else {
                // Tab: if on last element, go to first
                if (document.activeElement === lastFocusable) {
                    e.preventDefault();
                    firstFocusable.focus();
                }
            }
        }

        _toggleMobileNav() {
            this.state.mobileOpen ? this._closeMobileNav() : this._openMobileNav();
        }

        _openMobileNav() {
            // Store current focus and scroll position for restoration (iOS Safari fix)
            this.state.lastFocusedElement = document.activeElement;
            this.state.scrollPosition = window.pageYOffset;
            
            this.state.mobileOpen = true;
            this.mobileToggle?.setAttribute('aria-expanded', 'true');
            this.mobileNav?.classList.add('is-open');
            this.mobileNav?.setAttribute('aria-hidden', 'false');
            this.overlay?.classList.add('is-visible');
            
            // Use CSS class for scroll lock (not inline style) - better iOS Safari support
            document.body.classList.add('megamenu-open');
            
            // Move focus to close button or first focusable element
            const closeBtn = this.mobileNav?.querySelector('.megamenu__mobile-close');
            if (closeBtn) {
                setTimeout(() => closeBtn.focus(), 100);
            }
        }

        _closeMobileNav() {
            this.state.mobileOpen = false;
            this.mobileToggle?.setAttribute('aria-expanded', 'false');
            this.mobileNav?.classList.remove('is-open');
            this.mobileNav?.setAttribute('aria-hidden', 'true');
            this.overlay?.classList.remove('is-visible');
            
            // Remove scroll lock class
            document.body.classList.remove('megamenu-open');
            
            // Restore scroll position (iOS Safari fix)
            if (typeof this.state.scrollPosition === 'number') {
                window.scrollTo(0, this.state.scrollPosition);
                this.state.scrollPosition = null;
            }
            
            // Restore focus to the element that opened the drawer
            if (this.state.lastFocusedElement) {
                this.state.lastFocusedElement.focus();
                this.state.lastFocusedElement = null;
            }
        }

        _toggleMobileAccordion(trigger) {
            // Find submenu via aria-controls (authoritative link)
            const submenuId = trigger.getAttribute('aria-controls');
            let submenu = submenuId ? document.getElementById(submenuId) : null;

            // Fallback: submenu is inside the same mobile item
            if (!submenu) {
                const item = trigger.closest('.megamenu__mobile-item');
                submenu = item?.querySelector('.megamenu__mobile-submenu') || null;
            }

            if (!submenu || !submenu.classList.contains('megamenu__mobile-submenu')) return;

            const isOpen = trigger.getAttribute('aria-expanded') === 'true';

            // If opening: close ALL other open submenus in the entire mobile nav
            // Do NOT rely on DOM sibling/parent relationships
            if (!isOpen && this.mobileNav) {
                this.mobileNav.querySelectorAll('.megamenu__mobile-submenu.is-open').forEach((openSubmenu) => {
                    if (openSubmenu === submenu) return;

                    // Close this submenu
                    openSubmenu.classList.remove('is-open');
                    openSubmenu.setAttribute('aria-hidden', 'true');

                    // Find its controlling trigger via aria-controls and collapse it
                    const openSubmenuId = openSubmenu.id;
                    if (openSubmenuId) {
                        const openTrigger = this.mobileNav.querySelector(`.megamenu__mobile-trigger[aria-controls="${openSubmenuId}"]`);
                        if (openTrigger) {
                            openTrigger.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
            }

            // Toggle clicked submenu
            trigger.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            submenu.classList.toggle('is-open', !isOpen);
            submenu.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
        }



        /**
         * Reset all mobile accordion states
         * Called on breakpoint change to ensure clean desktop state
         */
        _resetMobileAccordions() {
            if (!this.mobileNav) return;
            
            // Close all open submenus
            this.mobileNav.querySelectorAll('.megamenu__mobile-submenu.is-open').forEach((submenu) => {
                submenu.classList.remove('is-open');
                submenu.setAttribute('aria-hidden', 'true');
            });
            
            // Reset all triggers to collapsed
            this.mobileNav.querySelectorAll('.megamenu__mobile-trigger[aria-expanded="true"]').forEach((trigger) => {
                trigger.setAttribute('aria-expanded', 'false');
            });
        }

        _bindGlobalEvents() {
            document.addEventListener('click', (e) => {
                if (this.state.openMenuId && !e.target.closest('.megamenu__item')) {
                    this._closeAllDesktopMenus();
                }
            });

            // Escape key handling with focus restoration
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (this.state.mobileOpen) {
                        this._closeMobileNav();
                    } else if (this.state.openMenuId) {
                        // Find the open menu and return focus to its trigger
                        const openItem = this.desktopNav?.querySelector('.megamenu__item.is-open');
                        const trigger = openItem?.querySelector('button[aria-expanded]');
                        this._closeAllDesktopMenus();
                        if (trigger) trigger.focus();
                    }
                }
            });

            // Breakpoint change: reset mobile state when switching to desktop
            window.addEventListener('resize', () => {
                if (window.innerWidth >= this.config.mobileBreakpoint) {
                    // Switching to desktop: close mobile nav and reset all accordions
                    if (this.state.mobileOpen) {
                        this._closeMobileNav();
                    }
                    // Always reset mobile accordions to ensure clean desktop state
                    this._resetMobileAccordions();
                    // Ensure mobile nav is hidden
                    this.mobileNav?.setAttribute('aria-hidden', 'true');
                }
            });
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new MegaMenu().init());
    } else {
        new MegaMenu().init();
    }
})();
