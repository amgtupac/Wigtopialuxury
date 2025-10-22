/**
 * Dark Mode Toggle
 * Manages dark/light theme switching with localStorage persistence
 */

(function() {
    'use strict';

    const DarkMode = {
        // Configuration
        storageKey: 'wigtopia_dark_mode',
        toggleClass: 'dark-mode',
        
        /**
         * Initialize dark mode
         */
        init: function() {
            // Check saved preference or system preference
            const savedMode = this.getSavedMode();
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            // Apply dark mode if saved or system prefers it
            if (savedMode === 'dark' || (savedMode === null && systemPrefersDark)) {
                this.enable();
            }
            
            // Create toggle button
            this.createToggleButton();
            
            // Listen for system theme changes
            this.listenToSystemChanges();
            
            // Keyboard shortcut (Ctrl/Cmd + Shift + D)
            this.setupKeyboardShortcut();
        },

        /**
         * Enable dark mode
         */
        enable: function() {
            document.documentElement.classList.add(this.toggleClass);
            this.saveMode('dark');
            this.updateToggleButton();
            this.dispatchEvent('darkModeEnabled');
        },

        /**
         * Disable dark mode
         */
        disable: function() {
            document.documentElement.classList.remove(this.toggleClass);
            this.saveMode('light');
            this.updateToggleButton();
            this.dispatchEvent('darkModeDisabled');
        },

        /**
         * Toggle dark mode
         */
        toggle: function() {
            if (this.isEnabled()) {
                this.disable();
            } else {
                this.enable();
            }
        },

        /**
         * Check if dark mode is enabled
         */
        isEnabled: function() {
            return document.documentElement.classList.contains(this.toggleClass);
        },

        /**
         * Save mode to localStorage
         */
        saveMode: function(mode) {
            try {
                localStorage.setItem(this.storageKey, mode);
            } catch (e) {
                console.warn('Failed to save dark mode preference:', e);
            }
        },

        /**
         * Get saved mode from localStorage
         */
        getSavedMode: function() {
            try {
                return localStorage.getItem(this.storageKey);
            } catch (e) {
                console.warn('Failed to retrieve dark mode preference:', e);
                return null;
            }
        },

        /**
         * Create toggle button
         */
        createToggleButton: function() {
            // Check if button already exists
            if (document.getElementById('darkModeToggle')) {
                return;
            }

            const button = document.createElement('button');
            button.id = 'darkModeToggle';
            button.className = 'dark-mode-toggle';
            button.setAttribute('aria-label', 'Toggle dark mode');
            button.setAttribute('title', 'Toggle dark mode (Ctrl+Shift+D)');
            
            this.updateToggleButton(button);
            
            button.addEventListener('click', () => {
                this.toggle();
                
                // Add click animation
                button.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    button.style.transform = '';
                }, 150);
            });
            
            document.body.appendChild(button);
        },

        /**
         * Update toggle button icon
         */
        updateToggleButton: function(button) {
            button = button || document.getElementById('darkModeToggle');
            
            if (!button) return;
            
            const isDark = this.isEnabled();
            button.innerHTML = isDark 
                ? '<i class="fas fa-sun"></i>' 
                : '<i class="fas fa-moon"></i>';
            
            button.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
        },

        /**
         * Listen to system theme changes
         */
        listenToSystemChanges: function() {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            
            // Only auto-switch if user hasn't set a preference
            mediaQuery.addEventListener('change', (e) => {
                if (this.getSavedMode() === null) {
                    if (e.matches) {
                        this.enable();
                    } else {
                        this.disable();
                    }
                }
            });
        },

        /**
         * Setup keyboard shortcut
         */
        setupKeyboardShortcut: function() {
            document.addEventListener('keydown', (e) => {
                // Ctrl+Shift+D or Cmd+Shift+D
                if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'D') {
                    e.preventDefault();
                    this.toggle();
                }
            });
        },

        /**
         * Dispatch custom event
         */
        dispatchEvent: function(eventName) {
            const event = new CustomEvent(eventName, {
                detail: { isDark: this.isEnabled() }
            });
            window.dispatchEvent(event);
        },

        /**
         * Get current theme
         */
        getCurrentTheme: function() {
            return this.isEnabled() ? 'dark' : 'light';
        },

        /**
         * Set theme programmatically
         */
        setTheme: function(theme) {
            if (theme === 'dark') {
                this.enable();
            } else if (theme === 'light') {
                this.disable();
            } else if (theme === 'auto') {
                // Remove saved preference and use system
                try {
                    localStorage.removeItem(this.storageKey);
                } catch (e) {
                    console.warn('Failed to remove dark mode preference:', e);
                }
                
                // Apply system preference
                const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (systemPrefersDark) {
                    this.enable();
                } else {
                    this.disable();
                }
            }
        },

        /**
         * Add custom styles for specific elements
         */
        addCustomStyles: function(selector, darkStyles) {
            const styleId = 'dark-mode-custom-' + selector.replace(/[^a-z0-9]/gi, '-');
            
            // Remove existing style if present
            const existingStyle = document.getElementById(styleId);
            if (existingStyle) {
                existingStyle.remove();
            }
            
            // Create new style element
            const style = document.createElement('style');
            style.id = styleId;
            
            let css = `.dark-mode ${selector} {`;
            for (const [property, value] of Object.entries(darkStyles)) {
                css += `${property}: ${value};`;
            }
            css += '}';
            
            style.textContent = css;
            document.head.appendChild(style);
        }
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            DarkMode.init();
        });
    } else {
        DarkMode.init();
    }

    // Expose to global scope
    window.DarkMode = DarkMode;

    // Example event listeners for other scripts
    window.addEventListener('darkModeEnabled', (e) => {
        console.log('Dark mode enabled');
    });

    window.addEventListener('darkModeDisabled', (e) => {
        console.log('Dark mode disabled');
    });

})();
