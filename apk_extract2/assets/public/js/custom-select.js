/**
 * Custom Select with Search - Offline Version
 * A lightweight, dependency-free custom select component
 */

class CustomSelect {
    constructor(element, options = {}) {
        this.originalSelect = element;
        this.options = {
            placeholder: options.placeholder || 'اختر...',
            searchPlaceholder: options.searchPlaceholder || 'بحث...',
            noResults: options.noResults || 'لا توجد نتائج',
            ...options
        };

        this.isOpen = false;
        this.selectedValue = '';
        this.selectedText = '';
        this.filteredOptions = [];

        this.init();
    }

    init() {
        // Hide original select
        this.originalSelect.style.display = 'none';

        // Create custom select structure
        this.createCustomSelect();

        // Bind events
        this.bindEvents();

        // Set initial value if exists
        if (this.originalSelect.value) {
            this.setValue(this.originalSelect.value);
        }
    }

    createCustomSelect() {
        // Main wrapper
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'cs-wrapper';

        // Selected display
        this.selectedDisplay = document.createElement('div');
        this.selectedDisplay.className = 'cs-selected';
        this.selectedDisplay.innerHTML = `
            <span class="cs-selected-text">${this.options.placeholder}</span>
            <span class="cs-arrow"></span>
        `;

        // Dropdown
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'cs-dropdown';

        // Search input
        this.searchWrapper = document.createElement('div');
        this.searchWrapper.className = 'cs-search-wrapper';
        this.searchInput = document.createElement('input');
        this.searchInput.type = 'text';
        this.searchInput.className = 'cs-search';
        this.searchInput.placeholder = this.options.searchPlaceholder;
        this.searchWrapper.appendChild(this.searchInput);

        // Options list
        this.optionsList = document.createElement('div');
        this.optionsList.className = 'cs-options';

        // Build options from original select
        this.buildOptions();

        // Assemble dropdown
        this.dropdown.appendChild(this.searchWrapper);
        this.dropdown.appendChild(this.optionsList);

        // Assemble wrapper
        this.wrapper.appendChild(this.selectedDisplay);
        this.wrapper.appendChild(this.dropdown);

        // Insert after original select
        this.originalSelect.parentNode.insertBefore(this.wrapper, this.originalSelect.nextSibling);
    }

    buildOptions(filter = '') {
        this.optionsList.innerHTML = '';
        const options = this.originalSelect.querySelectorAll('option');
        let hasResults = false;

        options.forEach(option => {
            if (option.disabled && option.value === '') return; // Skip placeholder option

            const text = option.textContent;
            const value = option.value;

            // Filter check
            if (filter && !text.toLowerCase().includes(filter.toLowerCase())) {
                return;
            }

            hasResults = true;

            const optionEl = document.createElement('div');
            optionEl.className = 'cs-option';
            optionEl.dataset.value = value;
            optionEl.textContent = text;

            if (value === this.selectedValue) {
                optionEl.classList.add('cs-option-selected');
            }

            optionEl.addEventListener('click', () => {
                this.selectOption(value, text);
            });

            this.optionsList.appendChild(optionEl);
        });

        // No results message
        if (!hasResults) {
            const noResults = document.createElement('div');
            noResults.className = 'cs-no-results';
            noResults.textContent = this.options.noResults;
            this.optionsList.appendChild(noResults);
        }
    }

    bindEvents() {
        // Toggle dropdown
        this.selectedDisplay.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggle();
        });

        // Search input
        this.searchInput.addEventListener('input', (e) => {
            this.buildOptions(e.target.value);
        });

        // Prevent dropdown close when clicking inside
        this.dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Close on outside click
        document.addEventListener('click', () => {
            this.close();
        });

        // Keyboard navigation
        this.searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.close();
            }
        });
    }

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        // Close other open selects
        document.querySelectorAll('.cs-wrapper.cs-open').forEach(el => {
            if (el !== this.wrapper) {
                el.classList.remove('cs-open');
            }
        });

        this.isOpen = true;
        this.wrapper.classList.add('cs-open');
        this.searchInput.value = '';
        this.buildOptions();

        // Focus search after small delay for animation
        setTimeout(() => {
            this.searchInput.focus();
        }, 100);
    }

    close() {
        this.isOpen = false;
        this.wrapper.classList.remove('cs-open');
    }

    selectOption(value, text) {
        this.selectedValue = value;
        this.selectedText = text;

        // Update display
        this.selectedDisplay.querySelector('.cs-selected-text').textContent = text;
        this.selectedDisplay.classList.add('cs-has-value');

        // Update original select
        this.originalSelect.value = value;

        // Trigger change event on original select
        const event = new Event('change', { bubbles: true });
        this.originalSelect.dispatchEvent(event);

        // Close dropdown
        this.close();

        // Rebuild options to show selected state
        this.buildOptions();
    }

    setValue(value) {
        const option = this.originalSelect.querySelector(`option[value="${value}"]`);
        if (option) {
            this.selectOption(value, option.textContent);
        }
    }

    getValue() {
        return this.selectedValue;
    }

    getText() {
        return this.selectedText;
    }

    reset() {
        this.selectedValue = '';
        this.selectedText = '';
        this.selectedDisplay.querySelector('.cs-selected-text').textContent = this.options.placeholder;
        this.selectedDisplay.classList.remove('cs-has-value');
        this.originalSelect.value = '';
        this.buildOptions();
    }

    destroy() {
        this.wrapper.remove();
        this.originalSelect.style.display = '';
    }

    // Static method to initialize all selects with a class
    static initAll(selector = '.custom-select', options = {}) {
        const selects = document.querySelectorAll(selector);
        const instances = [];
        selects.forEach(select => {
            instances.push(new CustomSelect(select, options));
        });
        return instances;
    }
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CustomSelect;
}
