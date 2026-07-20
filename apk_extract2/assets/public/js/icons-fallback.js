/**
 * Icons Fallback - Automatically converts Material Icons text to data attributes
 * This enables CSS-based SVG fallback icons for Android WebView compatibility
 */
(function() {
    'use strict';

    function processIcons() {
        // Find all material-icons elements
        const icons = document.querySelectorAll('.material-icons, .material-icons-round');

        icons.forEach(icon => {
            const iconName = icon.textContent.trim();
            if (iconName && !icon.hasAttribute('data-icon')) {
                icon.setAttribute('data-icon', iconName);
                // Keep original text for accessibility but hide it via CSS
                icon.setAttribute('aria-label', iconName);
            }
        });
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', processIcons);
    } else {
        processIcons();
    }

    // Also observe for dynamically added icons
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) { // Element node
                    if (node.classList && (node.classList.contains('material-icons') || node.classList.contains('material-icons-round'))) {
                        const iconName = node.textContent.trim();
                        if (iconName && !node.hasAttribute('data-icon')) {
                            node.setAttribute('data-icon', iconName);
                            node.setAttribute('aria-label', iconName);
                        }
                    }
                    // Check descendants
                    const descendantIcons = node.querySelectorAll ? node.querySelectorAll('.material-icons, .material-icons-round') : [];
                    descendantIcons.forEach(function(icon) {
                        const iconName = icon.textContent.trim();
                        if (iconName && !icon.hasAttribute('data-icon')) {
                            icon.setAttribute('data-icon', iconName);
                            icon.setAttribute('aria-label', iconName);
                        }
                    });
                }
            });
        });
    });

    observer.observe(document.body || document.documentElement, {
        childList: true,
        subtree: true
    });

    // Expose globally for manual calls
    window.processIcons = processIcons;
})();
