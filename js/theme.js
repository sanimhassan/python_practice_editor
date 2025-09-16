// Handles light/dark mode toggling
const theme = {
    init: function() {
        console.log('Theme module initializing...');
        
        // Set initial theme
        this.body = document.documentElement; // Use html element for better compatibility
        this.currentTheme = localStorage.getItem('theme') || 'light';
        
        // Apply theme immediately
        this.applyTheme();
        
        // Set up toggle button if it exists
        this.setupToggleButton();
        
        // Log current state
        console.log('Current theme:', this.currentTheme);
    },
    
    setupToggleButton: function() {
        this.themeToggleBtn = document.getElementById('theme-toggle-btn');
        console.log('Theme toggle button:', this.themeToggleBtn);
        
        if (this.themeToggleBtn) {
            console.log('Adding click event listener to theme toggle button');
            this.themeToggleBtn.addEventListener('click', () => this.toggleTheme());
            
            // Set initial icon
            this.updateIcon();
        } else {
            console.error('Theme toggle button not found in the DOM');
        }
    },
    
    applyTheme: function() {
        // Set the data-theme attribute on html element
        if (this.currentTheme === 'dark') {
            this.body.setAttribute('data-theme', 'dark');
        } else {
            this.body.removeAttribute('data-theme');
        }
        
        // Update icon
        this.updateIcon();
        
        // Save to localStorage
        localStorage.setItem('theme', this.currentTheme);
        
        console.log('Theme applied:', this.currentTheme);
    },
    
    updateIcon: function() {
        if (!this.themeToggleBtn) return;
        
        const icon = this.themeToggleBtn.querySelector('i');
        if (icon) {
            icon.className = this.currentTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            console.log('Icon updated:', icon.className);
        }
    },
    
    toggleTheme: function() {
        console.log('Toggling theme from', this.currentTheme);
        this.currentTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.applyTheme();
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM fully loaded, initializing theme...');
    theme.init();
});

// Expose to window
window.theme = theme;

// Debug log
console.log('Theme.js loaded - version 5');

// Force theme application after a short delay to ensure DOM is fully loaded
setTimeout(() => {
    if (window.theme) {
        console.log('Applying theme after delay');
        window.theme.applyTheme();
    } else {
        console.error('Theme object not available after delay');
    }
}, 1000);
