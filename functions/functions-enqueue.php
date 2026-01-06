<?php

    function enqueue_style() {

        //because without this, there is no site, at least not a coherent one.

        // Using style.css for development (megamenu styles)
        wp_enqueue_style( 'powersimple',get_stylesheet_directory_uri() . '/style.css');

       /*

          wp_register_style('bootstrap', '//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.0/css/bootstrap.min.css', null,'1.1', true); 

        wp_enqueue_style('bootstrap');

       */

   

    }

    add_action( 'wp_enqueue_scripts', 'enqueue_style' );



    function theme_scripts() {

        wp_register_script('jqueryui', '//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js',array('jquery')); 

        wp_enqueue_script('jqueryui');

        

       /*

        wp_register_script('scroll-magic', '//cdnjs.cloudflare.com/ajax/libs/ScrollMagic/2.0.5/ScrollMagic.min.js', null,'1.1', true); 

        wp_enqueue_script('scroll-magic');

        wp_register_script('scroll-magic-debug', '//cdnjs.cloudflare.com/ajax/libs/ScrollMagic/2.0.5/plugins/debug.addIndicators.min.js', null,'1.1', true); 

        wp_enqueue_script('scroll-magic-debug');

       

       wp_enqueue_script( 'jquery' );





     



        wp_register_script('slick', '//cdn.jsdelivr.net/jquery.slick/1.5.9/slick.min.js',array('jquery')); 

        wp_enqueue_script('slick');

       

        // Avoid conflicting with A-Frame (which bundles its own Three.js).
        // Only enqueue standalone Three.js on pages NOT using A-Frame.
        global $post;
        $use_aframe = 0;
        if (isset($post) && is_object($post)) {
            $use_aframe = intval(get_post_meta($post->ID, 'use_aframe', true));
        }

        if ($use_aframe !== 1) {
            wp_register_script('three', '//cdnjs.cloudflare.com/ajax/libs/three.js/r75/three.min.js'); 
            wp_enqueue_script('three');
        } else {
            // If registered/enqueued earlier by plugins or other hooks, remove to prevent duplicate Three.
            wp_dequeue_script('three');
            wp_deregister_script('three');
        }



    wp_register_script('d3', '//cdnjs.cloudflare.com/ajax/libs/d3/4.2.2/d3.min.js'); 

        wp_enqueue_script('d3');

        wp_register_script('d3-geo', '//d3js.org/d3-geo.v1.min.js'); 

        wp_enqueue_script('d3-geo');

        

        wp_register_script('topojson', '//d3js.org/topojson.v2.min.js'); 

        wp_enqueue_script('topojson');



        wp_register_script('tweenmax', '//cdnjs.cloudflare.com/ajax/libs/gsap/1.18.4/TweenMax.min.js'); 

        wp_enqueue_script('tweenmax');

           //vendor is the stylesheet rendered 

     */

       //vendor is the stylesheet rendered 
      
$path= parse_url(get_stylesheet_directory_uri())['path'];

       wp_register_script('vendor',$path . '/vendor.min.js', array('jquery'),rand(100000,999999), false); 
       wp_enqueue_script('vendor');

       /*
           wp_register_script('xrapp',get_stylesheet_directory_uri() . '/xr-app.js', array('jquery'),rand(100000,999999), true); 

           wp_enqueue_script('xrapp');
        */


        

     
     
   

    
       
    //    wp_register_script('main',$path. '/main.js', array('jquery'),rand(100000,999999), true); 

      

        wp_enqueue_script('main');

        // Megamenu - inline script (no ES module import issues)
        // Use a static flag to prevent duplicate registration
        static $megamenu_script_added = false;
        if (!$megamenu_script_added) {
            $megamenu_script_added = true;
            add_action('wp_footer', function() {
                ?>
                <!-- MEGAMENU SCRIPT START -->
                <script>
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
                        this.state = { openMenuId: null, mobileOpen: false };
                        this.container = null;
                        this.desktopNav = null;
                        this.mobileNav = null;
                        this.mobileToggle = null;
                        this.overlay = null;
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

                        // Hover to open (desktop only)
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

                    _toggleDesktopMenu(trigger) {
                        const menuItem = trigger.closest('.megamenu__item');
                        const panel = menuItem?.querySelector('.megamenu__panel');
                        if (!panel) return;

                        const isOpen = trigger.getAttribute('aria-expanded') === 'true';

                        if (isOpen) {
                            this._closeDesktopMenu(trigger, panel, menuItem);
                        } else {
                            this._closeAllDesktopMenus();
                            this._openDesktopMenu(trigger, panel, menuItem);
                        }
                    }

                    _openDesktopMenu(trigger, panel, menuItem) {
                        trigger.setAttribute('aria-expanded', 'true');
                        panel.setAttribute('data-state', 'open');
                        menuItem.classList.add('is-open');
                        this.state.openMenuId = menuItem.id;
                    }

                    _closeDesktopMenu(trigger, panel, menuItem) {
                        trigger.setAttribute('aria-expanded', 'false');
                        panel.setAttribute('data-state', 'closed');
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
                        }

                        const closeBtn = this.mobileNav?.querySelector('.megamenu__mobile-close');
                        if (closeBtn) {
                            closeBtn.addEventListener('click', () => this._closeMobileNav());
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
                        }
                    }

                    _toggleMobileNav() {
                        this.state.mobileOpen ? this._closeMobileNav() : this._openMobileNav();
                    }

                    _openMobileNav() {
                        this.state.mobileOpen = true;
                        this.mobileToggle?.setAttribute('aria-expanded', 'true');
                        this.mobileNav?.classList.add('is-open');
                        this.overlay?.classList.add('is-visible');
                        document.body.style.overflow = 'hidden';
                    }

                    _closeMobileNav() {
                        this.state.mobileOpen = false;
                        this.mobileToggle?.setAttribute('aria-expanded', 'false');
                        this.mobileNav?.classList.remove('is-open');
                        this.overlay?.classList.remove('is-visible');
                        document.body.style.overflow = '';
                    }

                    _toggleMobileAccordion(trigger) {
                        const submenu = trigger.nextElementSibling;
                        if (!submenu?.classList.contains('megamenu__mobile-submenu')) return;

                        const isOpen = trigger.getAttribute('aria-expanded') === 'true';
                        trigger.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                        submenu.classList.toggle('is-open', !isOpen);
                    }

                    _bindGlobalEvents() {
                        document.addEventListener('click', (e) => {
                            if (this.state.openMenuId && !e.target.closest('.megamenu__item')) {
                                this._closeAllDesktopMenus();
                            }
                        });

                        document.addEventListener('keydown', (e) => {
                            if (e.key === 'Escape') {
                                if (this.state.mobileOpen) this._closeMobileNav();
                                else if (this.state.openMenuId) this._closeAllDesktopMenus();
                            }
                        });

                        window.addEventListener('resize', () => {
                            if (window.innerWidth >= this.config.mobileBreakpoint && this.state.mobileOpen) {
                                this._closeMobileNav();
                            }
                        });
                    }
                }

                // Initialize
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', () => new MegaMenu().init());
                } else {
                    new MegaMenu().init();
                }
            })();
            </script>
            <!-- MEGAMENU SCRIPT END -->
            <?php
            }, 100);
        } // end if !$megamenu_script_added



    }



    

    add_action( 'wp_enqueue_scripts', 'theme_scripts' );  



function style_loader_src_make_relative ( $src, $handle ) {
    // Remove the domain part. 
    // site_url() = http://localhost:8080
    $src = str_replace(url_root(), "", $src); 
    
    return $src;  
  }
  add_filter('style_loader_src', 'style_loader_src_make_relative', 10, 2 );
//phpinfo(); die();

// (Removed global corner brand injection; now scoped to group exhibits only)


?>