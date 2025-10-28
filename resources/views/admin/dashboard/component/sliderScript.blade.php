<script>
/**
 * CLEAN SLIDER SCRIPT - OPTIMIZED FOR SMOOTH TRANSITIONS
 * Handles both category and admin cards sliders with RTL support
 */
document.addEventListener('DOMContentLoaded', function() {

    // ========== CATEGORY SLIDER ==========
    const catRow = document.getElementById('category-cards-row');
    const catCards = catRow ? catRow.querySelectorAll('.category-carousel-card') : [];
    const catPrevBtn = document.getElementById('category-slider-prev-btn');
    const catNextBtn = document.getElementById('category-slider-next-btn');
    const catDotsContainer = document.getElementById('category-slider-dots');
    let catCurrent = 0;

    function catVisibleCount() {
        if (window.innerWidth >= 1200) return 4;
        if (window.innerWidth >= 768) return 2;
        return 1;
    }

    function updateCatSlider() {
        if (!catRow || catCards.length === 0) return;

        const visible = catVisibleCount();
        const maxIndex = Math.max(0, catCards.length - visible);

        // Clamp current index to valid range
        catCurrent = Math.max(0, Math.min(catCurrent, maxIndex));

        // Calculate precise transform (each card is 100/visible % wide)
        const translatePercent = catCurrent * (100 / visible);
        catRow.style.transform = `translateX(-${translatePercent}%)`;

        // Update active state (visual feedback)
        catCards.forEach((card, idx) => {
            card.classList.toggle('active', idx === catCurrent);
        });

        // Update navigation buttons visibility
        if (catPrevBtn && catNextBtn) {
            catPrevBtn.style.display = (catCurrent < maxIndex) ? 'flex' : 'none';
            catNextBtn.style.display = (catCurrent > 0) ? 'flex' : 'none';
        }

        // Update pagination dots
        if (catDotsContainer) {
            catDotsContainer.innerHTML = '';
            for (let i = 0; i <= maxIndex; i++) {
                const dot = document.createElement('span');
                dot.className = 'slider-dot' + (catCurrent === i ? ' active' : '');
                dot.addEventListener('click', () => {
                    catCurrent = i;
                    updateCatSlider();
                });
                catDotsContainer.appendChild(dot);
            }
        }
    }

    // Initialize category slider
    if (catCards.length > 0) {
        catCards.forEach(card => card.classList.add('slider-card'));

        // Start from the rightmost position (RTL)
        catCurrent = Math.max(0, catCards.length - catVisibleCount());
        updateCatSlider();

        // Navigation buttons
        if (catPrevBtn) {
            catPrevBtn.addEventListener('click', function() {
                const visible = catVisibleCount();
                const maxIndex = Math.max(0, catCards.length - visible);
                if (catCurrent < maxIndex) {
                    catCurrent++;
                    updateCatSlider();
                }
            });
        }

        if (catNextBtn) {
            catNextBtn.addEventListener('click', function() {
                if (catCurrent > 0) {
                    catCurrent--;
                    updateCatSlider();
                }
            });
        }

        // Touch/swipe support
        let catTouchStart = null;
        catRow.addEventListener('touchstart', function(e) {
            catTouchStart = e.touches[0].clientX;
        }, { passive: true });

        catRow.addEventListener('touchend', function(e) {
            if (catTouchStart === null) return;

            const catTouchEnd = e.changedTouches[0].clientX;
            const delta = catTouchEnd - catTouchStart;
            const threshold = 50; // Minimum swipe distance

            if (Math.abs(delta) > threshold) {
                const visible = catVisibleCount();
                const maxIndex = Math.max(0, catCards.length - visible);

                if (delta < 0 && catCurrent < maxIndex) {
                    // Swipe left → next (RTL: move forward)
                    catCurrent++;
                    updateCatSlider();
                } else if (delta > 0 && catCurrent > 0) {
                    // Swipe right → previous (RTL: move backward)
                    catCurrent--;
                    updateCatSlider();
                }
            }

            catTouchStart = null;
        }, { passive: true });

        // Responsive resize handler
        let catResizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(catResizeTimeout);
            catResizeTimeout = setTimeout(function() {
                const newVisible = catVisibleCount();
                const newMaxIndex = Math.max(0, catCards.length - newVisible);
                catCurrent = Math.min(catCurrent, newMaxIndex);
                updateCatSlider();
            }, 150);
        });
    }

    // ========== ADMIN SLIDER ==========
    const adminRow = document.getElementById('admin-cards-row');
    const adminCards = adminRow ? adminRow.querySelectorAll('.admin-carousel-card') : [];
    const adminPrevBtn = document.getElementById('admin-slider-prev-btn');
    const adminNextBtn = document.getElementById('admin-slider-next-btn');
    const adminDotsContainer = document.getElementById('admin-slider-dots');
    let adminCurrent = 0;

    function adminVisibleCount() {
        if (window.innerWidth >= 1200) return 4;
        if (window.innerWidth >= 768) return 2;
        return 1;
    }

    function updateAdminSlider() {
        if (!adminRow || adminCards.length === 0) return;

        const visible = adminVisibleCount();
        const maxIndex = Math.max(0, adminCards.length - visible);

        // Clamp current index to valid range
        adminCurrent = Math.max(0, Math.min(adminCurrent, maxIndex));

        // Calculate precise transform
        const translatePercent = adminCurrent * (100 / visible);
        adminRow.style.transform = `translateX(-${translatePercent}%)`;

        // Update active state
        adminCards.forEach((card, idx) => {
            card.classList.toggle('active', idx === adminCurrent);
        });

        // Update navigation buttons visibility
        if (adminPrevBtn && adminNextBtn) {
            adminPrevBtn.style.display = (adminCurrent < maxIndex) ? 'flex' : 'none';
            adminNextBtn.style.display = (adminCurrent > 0) ? 'flex' : 'none';
        }

        // Update pagination dots
        if (adminDotsContainer) {
            adminDotsContainer.innerHTML = '';
            for (let i = 0; i <= maxIndex; i++) {
                const dot = document.createElement('span');
                dot.className = 'slider-dot' + (adminCurrent === i ? ' active' : '');
                dot.addEventListener('click', () => {
                    adminCurrent = i;
                    updateAdminSlider();
                });
                adminDotsContainer.appendChild(dot);
            }
        }
    }

    // Initialize admin slider
    if (adminCards.length > 0) {
        adminCards.forEach(card => card.classList.add('slider-card'));

        // Start from the rightmost position (RTL)
        adminCurrent = Math.max(0, adminCards.length - adminVisibleCount());
        updateAdminSlider();

        // Navigation buttons
        if (adminPrevBtn) {
            adminPrevBtn.addEventListener('click', function() {
                const visible = adminVisibleCount();
                const maxIndex = Math.max(0, adminCards.length - visible);
                if (adminCurrent < maxIndex) {
                    adminCurrent++;
                    updateAdminSlider();
                }
            });
        }

        if (adminNextBtn) {
            adminNextBtn.addEventListener('click', function() {
                if (adminCurrent > 0) {
                    adminCurrent--;
                    updateAdminSlider();
                }
            });
        }

        // Touch/swipe support
        let adminTouchStart = null;
        adminRow.addEventListener('touchstart', function(e) {
            adminTouchStart = e.touches[0].clientX;
        }, { passive: true });

        adminRow.addEventListener('touchend', function(e) {
            if (adminTouchStart === null) return;

            const adminTouchEnd = e.changedTouches[0].clientX;
            const delta = adminTouchEnd - adminTouchStart;
            const threshold = 50;

            if (Math.abs(delta) > threshold) {
                const visible = adminVisibleCount();
                const maxIndex = Math.max(0, adminCards.length - visible);

                if (delta < 0 && adminCurrent < maxIndex) {
                    adminCurrent++;
                    updateAdminSlider();
                } else if (delta > 0 && adminCurrent > 0) {
                    adminCurrent--;
                    updateAdminSlider();
                }
            }

            adminTouchStart = null;
        }, { passive: true });

        // Responsive resize handler
        let adminResizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(adminResizeTimeout);
            adminResizeTimeout = setTimeout(function() {
                const newVisible = adminVisibleCount();
                const newMaxIndex = Math.max(0, adminCards.length - newVisible);
                adminCurrent = Math.min(adminCurrent, newMaxIndex);
                updateAdminSlider();
            }, 150);
        });
    }
});
</script>
