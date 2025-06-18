/**
 * Sidebar Responsive Manager
 * Maneja el comportamiento del sidebar en diferentes tamaños de pantalla
 */

class SidebarManager {
    constructor() {
        this.init();
    }
    
    init() {
        this.createMobileElements();
        this.bindEvents();
        this.handleResize();
    }
    
    createMobileElements() {
        // Crear botón hamburguesa si no existe
        if (!document.getElementById('sidebarToggle')) {
            const toggleBtn = document.createElement('button');
            toggleBtn.id = 'sidebarToggle';
            toggleBtn.className = 'sidebar-toggle';
            toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
            document.body.appendChild(toggleBtn);
        }
        
        // Crear overlay si no existe
        if (!document.getElementById('sidebarOverlay')) {
            const overlay = document.createElement('div');
            overlay.id = 'sidebarOverlay';
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
        }
    }
    
    bindEvents() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        if (sidebarToggle && sidebar && sidebarOverlay) {
            // Toggle sidebar on mobile
            sidebarToggle.addEventListener('click', () => {
                this.toggleSidebar();
            });
            
            // Close sidebar when clicking overlay
            sidebarOverlay.addEventListener('click', () => {
                this.closeSidebar();
            });
            
            // Close sidebar on escape key
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    this.closeSidebar();
                }
            });
        }
        
        // Handle window resize
        window.addEventListener('resize', () => {
            this.handleResize();
        });
    }
    
    toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        if (sidebar && sidebarOverlay) {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
            
            // Prevent body scroll when sidebar is open on mobile
            if (sidebar.classList.contains('show')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }
    }
    
    closeSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        if (sidebar && sidebarOverlay) {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
    
    handleResize() {
        const width = window.innerWidth;
        
        // Auto-close sidebar on desktop
        if (width > 768) {
            this.closeSidebar();
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    new SidebarManager();
});

// Export for use in other scripts if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SidebarManager;
}
