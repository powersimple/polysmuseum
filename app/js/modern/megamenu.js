// Modern megamenu implementation using browser-native features
class MegaMenu {
    constructor(containerId = 'main-menu') {
        this.container = document.getElementById(containerId);
        this.menuData = window.menus?.megamenu?.menu_levels || [];
        this.isInitialized = false;
        
        // Use IntersectionObserver for animations
        this.observer = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            },
            { threshold: 0.1 }
        );
    }

    init() {
        if (this.isInitialized || !this.container) return;
        
        // Create the menu structure
        const menuHTML = this.buildMenu(this.menuData);
        this.container.innerHTML = menuHTML;
        
        // Add event listeners
        this.addEventListeners();
        
        // Observe menu items for animations
        this.container.querySelectorAll('.animated').forEach(item => {
            this.observer.observe(item);
        });
        
        this.isInitialized = true;
    }

    buildMenu(items, parentClasses = '') {
        if (!items || !items.length) return '';

        return items.map(item => {
            const classes = item.classes || '';
            const isMega = classes.includes('mega-drop-down');
            const isDropdown = classes.includes('drop-down');
            
            let menuItem = '';
            const outerTag = item.parent_classes === 'mega-drop-down' ? 'div' : 'li';
            
            // Build the link
            const link = this.buildLink(item);
            
            // Build the menu item
            menuItem += `<${outerTag} class="${classes}">`;
            menuItem += link;
            
            // Add children if they exist
            if (item.children && item.children.length) {
                const childClasses = isMega ? 'mega-menu animated fadeIn' : 
                                   isDropdown ? 'animated fadeIn' : 'animated fadeIn';
                
                menuItem += `<ul class="${childClasses}">`;
                menuItem += this.buildMenu(item.children, classes);
                menuItem += '</ul>';
            }
            
            menuItem += `</${outerTag}>`;
            return menuItem;
        }).join('');
    }

    buildLink(item) {
        let url = item.url || '#';
        if (item.xfn) {
            url += `#${item.xfn}`;
        }
        
        const target = item.target ? `target="${item.target}"` : '';
        return `<a href="${url}" ${target}>${item.title}</a>`;
    }

    addEventListeners() {
        // Use event delegation for better performance
        this.container.addEventListener('click', (e) => {
            const dropdown = e.target.closest('.drop-down, .mega-drop-down');
            if (dropdown) {
                const link = dropdown.querySelector('a');
                if (link && link.getAttribute('href') === '#') {
                    e.preventDefault();
                    dropdown.classList.toggle('active');
                }
            }
        });

        // Add mobile menu toggle
        const toggle = this.container.querySelector('.toggle-menu');
        if (toggle) {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                this.container.classList.toggle('mobile-active');
            });
        }

        // Add keyboard navigation
        this.container.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                const dropdown = e.target.closest('.drop-down, .mega-drop-down');
                if (dropdown) {
                    e.preventDefault();
                    dropdown.classList.toggle('active');
                }
            }
        });
    }

    // Clean up when no longer needed
    destroy() {
        if (this.observer) {
            this.observer.disconnect();
        }
        this.container = null;
        this.isInitialized = false;
    }
}

// Export for use in other modules
export default MegaMenu; 