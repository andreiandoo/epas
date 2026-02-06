/**
 * TICS.ro - Accessibility Widget
 *
 * Provides accessibility features:
 * - Font size adjustment
 * - Underline links/headings
 * - Disable animations
 * - Color adjustments (grayscale, high contrast, invert)
 * - Cursor customization (size, color)
 *
 * All settings are persisted in localStorage
 */

const TicsAccessibility = {
    // Default settings
    defaults: {
        fontSize: 100,
        underlineLinks: false,
        underlineHeadings: false,
        disableAnimations: false,
        colorMode: 'normal',
        cursorSize: 'normal',
        cursorColor: 'default'
    },

    // Current settings
    settings: {},

    // Storage key
    STORAGE_KEY: 'tics_accessibility',

    /**
     * Initialize accessibility widget
     */
    init() {
        this.loadSettings();
        this.applySettings();
        this.bindEvents();
        this.updateUI();
    },

    /**
     * Load settings from localStorage
     */
    loadSettings() {
        try {
            const stored = localStorage.getItem(this.STORAGE_KEY);
            this.settings = stored ? { ...this.defaults, ...JSON.parse(stored) } : { ...this.defaults };
        } catch (e) {
            console.warn('Could not load accessibility settings:', e);
            this.settings = { ...this.defaults };
        }
    },

    /**
     * Save settings to localStorage
     */
    saveSettings() {
        try {
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(this.settings));
        } catch (e) {
            console.warn('Could not save accessibility settings:', e);
        }
    },

    /**
     * Apply all settings to the page
     */
    applySettings() {
        const html = document.documentElement;

        // Font size
        html.style.fontSize = this.settings.fontSize + '%';

        // Underline links
        if (this.settings.underlineLinks) {
            html.classList.add('a11y-underline-links');
        } else {
            html.classList.remove('a11y-underline-links');
        }

        // Underline headings
        if (this.settings.underlineHeadings) {
            html.classList.add('a11y-underline-headings');
        } else {
            html.classList.remove('a11y-underline-headings');
        }

        // Disable animations
        if (this.settings.disableAnimations) {
            html.classList.add('a11y-no-animations');
        } else {
            html.classList.remove('a11y-no-animations');
        }

        // Color mode
        html.classList.remove('a11y-grayscale', 'a11y-high-contrast', 'a11y-invert');
        if (this.settings.colorMode !== 'normal') {
            html.classList.add('a11y-' + this.settings.colorMode);
        }

        // Cursor size
        html.classList.remove('a11y-cursor-large', 'a11y-cursor-xlarge');
        if (this.settings.cursorSize !== 'normal') {
            html.classList.add('a11y-cursor-' + this.settings.cursorSize);
        }

        // Cursor color
        html.classList.remove('a11y-cursor-black', 'a11y-cursor-white', 'a11y-cursor-yellow', 'a11y-cursor-blue', 'a11y-cursor-red');
        if (this.settings.cursorColor !== 'default') {
            html.classList.add('a11y-cursor-' + this.settings.cursorColor);
        }
    },

    /**
     * Update UI to reflect current settings
     */
    updateUI() {
        // Font size
        const fontSizeLabel = document.getElementById('fontSizeLabel');
        const fontSizeRange = document.getElementById('fontSizeRange');
        if (fontSizeLabel) fontSizeLabel.textContent = this.settings.fontSize + '%';
        if (fontSizeRange) fontSizeRange.value = this.settings.fontSize;

        // Checkboxes
        const underlineLinks = document.getElementById('underlineLinks');
        const underlineHeadings = document.getElementById('underlineHeadings');
        const disableAnimations = document.getElementById('disableAnimations');

        if (underlineLinks) underlineLinks.checked = this.settings.underlineLinks;
        if (underlineHeadings) underlineHeadings.checked = this.settings.underlineHeadings;
        if (disableAnimations) disableAnimations.checked = this.settings.disableAnimations;

        // Color mode buttons
        document.querySelectorAll('.color-mode-btn').forEach(btn => {
            const mode = btn.dataset.colorMode;
            if (mode === this.settings.colorMode) {
                btn.classList.add('border-gray-900');
                btn.classList.remove('border-gray-200');
            } else {
                btn.classList.remove('border-gray-900');
                btn.classList.add('border-gray-200');
            }
        });

        // Cursor size buttons
        document.querySelectorAll('.cursor-size-btn').forEach(btn => {
            const size = btn.dataset.cursorSize;
            if (size === this.settings.cursorSize) {
                btn.classList.add('bg-gray-900', 'text-white');
                btn.classList.remove('bg-white', 'text-gray-700');
            } else {
                btn.classList.remove('bg-gray-900', 'text-white');
                btn.classList.add('bg-white', 'text-gray-700');
            }
        });

        // Cursor size label
        const cursorSizeLabel = document.getElementById('cursorSizeLabel');
        if (cursorSizeLabel) {
            const labels = { normal: 'Normal', large: 'Mare', xlarge: 'Extra mare' };
            cursorSizeLabel.textContent = labels[this.settings.cursorSize] || 'Normal';
        }

        // Cursor color buttons
        document.querySelectorAll('.cursor-color-btn').forEach(btn => {
            const color = btn.dataset.cursorColor;
            if (color === this.settings.cursorColor) {
                btn.classList.add('border-gray-900');
                btn.classList.remove('border-gray-200');
            } else {
                btn.classList.remove('border-gray-900');
                btn.classList.add('border-gray-200');
            }
        });
    },

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Toggle button
        const toggle = document.getElementById('accessibilityToggle');
        if (toggle) {
            toggle.addEventListener('click', () => this.openModal());
        }

        // Close button
        const close = document.getElementById('accessibilityClose');
        if (close) {
            close.addEventListener('click', () => this.closeModal());
        }

        // Overlay click
        const overlay = document.getElementById('accessibilityOverlay');
        if (overlay) {
            overlay.addEventListener('click', () => this.closeModal());
        }

        // Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeModal();
            }
        });

        // Font size controls
        const fontDecrease = document.getElementById('fontDecrease');
        const fontIncrease = document.getElementById('fontIncrease');
        const fontSizeRange = document.getElementById('fontSizeRange');

        if (fontDecrease) {
            fontDecrease.addEventListener('click', () => this.adjustFontSize(-10));
        }
        if (fontIncrease) {
            fontIncrease.addEventListener('click', () => this.adjustFontSize(10));
        }
        if (fontSizeRange) {
            fontSizeRange.addEventListener('input', (e) => {
                this.settings.fontSize = parseInt(e.target.value);
                this.applySettings();
                this.updateUI();
                this.saveSettings();
            });
        }

        // Checkboxes
        const underlineLinks = document.getElementById('underlineLinks');
        const underlineHeadings = document.getElementById('underlineHeadings');
        const disableAnimations = document.getElementById('disableAnimations');

        if (underlineLinks) {
            underlineLinks.addEventListener('change', (e) => {
                this.settings.underlineLinks = e.target.checked;
                this.applySettings();
                this.saveSettings();
            });
        }
        if (underlineHeadings) {
            underlineHeadings.addEventListener('change', (e) => {
                this.settings.underlineHeadings = e.target.checked;
                this.applySettings();
                this.saveSettings();
            });
        }
        if (disableAnimations) {
            disableAnimations.addEventListener('change', (e) => {
                this.settings.disableAnimations = e.target.checked;
                this.applySettings();
                this.saveSettings();
            });
        }

        // Color mode buttons
        document.querySelectorAll('.color-mode-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.settings.colorMode = btn.dataset.colorMode;
                this.applySettings();
                this.updateUI();
                this.saveSettings();
            });
        });

        // Cursor size buttons
        document.querySelectorAll('.cursor-size-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.settings.cursorSize = btn.dataset.cursorSize;
                this.applySettings();
                this.updateUI();
                this.saveSettings();
            });
        });

        // Cursor color buttons
        document.querySelectorAll('.cursor-color-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.settings.cursorColor = btn.dataset.cursorColor;
                this.applySettings();
                this.updateUI();
                this.saveSettings();
            });
        });

        // Reset button
        const reset = document.getElementById('accessibilityReset');
        if (reset) {
            reset.addEventListener('click', () => this.resetSettings());
        }
    },

    /**
     * Open modal
     */
    openModal() {
        const modal = document.getElementById('accessibilityModal');
        const panel = document.getElementById('accessibilityPanel');

        if (modal && panel) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            // Trigger animation
            requestAnimationFrame(() => {
                panel.classList.remove('translate-x-full');
            });

            // Focus first interactive element
            setTimeout(() => {
                const firstInput = panel.querySelector('button, input');
                if (firstInput) firstInput.focus();
            }, 300);
        }
    },

    /**
     * Close modal
     */
    closeModal() {
        const modal = document.getElementById('accessibilityModal');
        const panel = document.getElementById('accessibilityPanel');

        if (modal && panel) {
            panel.classList.add('translate-x-full');

            setTimeout(() => {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }, 300);
        }
    },

    /**
     * Adjust font size
     */
    adjustFontSize(delta) {
        const newSize = Math.max(80, Math.min(150, this.settings.fontSize + delta));
        if (newSize !== this.settings.fontSize) {
            this.settings.fontSize = newSize;
            this.applySettings();
            this.updateUI();
            this.saveSettings();
        }
    },

    /**
     * Reset all settings to defaults
     */
    resetSettings() {
        this.settings = { ...this.defaults };
        this.applySettings();
        this.updateUI();
        this.saveSettings();

        // Show confirmation
        const reset = document.getElementById('accessibilityReset');
        if (reset) {
            const originalText = reset.innerHTML;
            reset.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Resetat!';
            reset.classList.add('bg-green-100', 'text-green-700');
            reset.classList.remove('bg-gray-100', 'text-gray-700');

            setTimeout(() => {
                reset.innerHTML = originalText;
                reset.classList.remove('bg-green-100', 'text-green-700');
                reset.classList.add('bg-gray-100', 'text-gray-700');
            }, 2000);
        }
    }
};

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => TicsAccessibility.init());
} else {
    TicsAccessibility.init();
}

// Make available globally
window.TicsAccessibility = TicsAccessibility;
