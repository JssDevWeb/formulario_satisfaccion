/**
 * Script para manejar el toggle de modo oscuro en el admin
 */

// Función para inicializar el tema
function initTheme() {
    const savedTheme = localStorage.getItem('admin-theme') || 'light';
    setTheme(savedTheme);
}

// Función para establecer el tema
function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('admin-theme', theme);
    
    // Actualizar el toggle si existe
    const toggle = document.querySelector('.theme-toggle');
    if (toggle) {
        if (theme === 'dark') {
            toggle.classList.add('dark');
        } else {
            toggle.classList.remove('dark');
        }
    }
}

// Función para alternar el tema
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    setTheme(newTheme);
}

// Crear el toggle button
function createThemeToggle() {
    return `
        <div class="theme-toggle" onclick="toggleTheme()" title="Cambiar tema">
            <div class="toggle-slider"></div>
            <i class="bi bi-sun-fill toggle-icon sun-icon"></i>
            <i class="bi bi-moon-fill toggle-icon moon-icon"></i>
        </div>
    `;
}

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    initTheme();
    
    // Agregar el toggle al header si existe un contenedor para él
    const themeContainer = document.querySelector('.theme-toggle-container');
    if (themeContainer) {
        themeContainer.innerHTML = createThemeToggle();
    }
});

// Detectar cambios en las preferencias del sistema
if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        // Solo cambiar automáticamente si no hay preferencia guardada
        if (!localStorage.getItem('admin-theme')) {
            setTheme(e.matches ? 'dark' : 'light');
        }
    });
}
