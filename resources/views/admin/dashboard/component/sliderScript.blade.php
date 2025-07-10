<script>
// سلايدر عملي للبطاقات (تصنيفات عامة وموظفين)
document.addEventListener('DOMContentLoaded', function() {
    // Category slider
    const catRow = document.getElementById('category-cards-row');
    const catCards = catRow ? catRow.querySelectorAll('.category-carousel-card') : [];
    const catPrevBtn = document.getElementById('category-slider-prev-btn');
    const catNextBtn = document.getElementById('category-slider-next-btn');
    // ابدأ من آخر كرت (أقصى اليمين)
    let catCurrent = catCards.length > 0 ? catCards.length - catVisibleCount() : 0;
    function catVisibleCount() {
        if (window.innerWidth >= 1200) return 4;
        if (window.innerWidth >= 768) return 2;
        return 1;
    }
    function updateCatSlider() {
        const visible = catVisibleCount();
        if (!catRow) return;
        const maxIndex = catCards.length - visible;
        // عرض البطاقات من اليمين لليسار فعلياً
        catRow.style.transform = `translateX(-${catCurrent * (100/visible)}%)`;
        // تمييز الكرت النشط
        catCards.forEach((card, idx) => {
            if (idx === catCurrent) card.classList.add('active');
            else card.classList.remove('active');
        });
        catPrevBtn.style.display = (catCurrent < catCards.length - visible) ? 'flex' : 'none';
        catNextBtn.style.display = (catCurrent > 0) ? 'flex' : 'none';
        // Update dots
        const dots = document.getElementById('category-slider-dots');
        if (dots) {
            dots.innerHTML = '';
            for (let i = 0; i <= maxIndex; i++) {
                const dot = document.createElement('span');
                dot.className = 'slider-dot' + (catCurrent === i ? ' active' : '');
                dot.addEventListener('click', () => { catCurrent = i; updateCatSlider(); });
                dots.appendChild(dot);
            }
        }
    }
    // Touch/Swipe support
    let catStartX = null, catMoveX = null;
    if (catCards.length > 0) {
        catCards.forEach(card => card.classList.add('slider-card'));
        // ابدأ من آخر كرت (أقصى اليمين)
        catCurrent = Math.max(0, catCards.length - catVisibleCount());
        updateCatSlider();
        window.addEventListener('resize', function() {
            // عند تغيير حجم الشاشة، تأكد من بقاء البداية من اليمين
            const newVisible = catVisibleCount();
            catCurrent = Math.max(0, catCards.length - newVisible);
            updateCatSlider();
        });
        // زر السابق: ينتقل للكرت السابق (index أصغر)
        // زر السابق: ينتقل للكرت التالي (index أكبر)
        catPrevBtn.addEventListener('click', function() {
            if (catCurrent < catCards.length - catVisibleCount()) {
                catCurrent++;
                updateCatSlider();
            }
        });
        // زر التالي: ينتقل للكرت السابق (index أصغر)
        catNextBtn.addEventListener('click', function() {
            if (catCurrent > 0) {
                catCurrent--;
                updateCatSlider();
            }
        });
        // Touch events (start/move/end)
        catRow.addEventListener('touchstart', function(e) {
            catStartX = e.touches[0].clientX;
            catMoveX = null;
        });
        catRow.addEventListener('touchmove', function(e) {
            if (catStartX !== null) {
                catMoveX = e.touches[0].clientX;
            }
        });
        catRow.addEventListener('touchend', function(e) {
            if (catStartX === null) return;
            let endX = catMoveX !== null ? catMoveX : e.changedTouches[0].clientX;
            let dx = endX - catStartX;
            if (Math.abs(dx) > 40) {
                // إذا سحبنا لليسار (dx < 0) ننتقل للكرت التالي (index أكبر)
                if (dx < 0 && catCurrent < catCards.length - catVisibleCount()) { catCurrent++; updateCatSlider(); }
                // إذا سحبنا لليمين (dx > 0) ننتقل للكرت السابق (index أصغر)
                else if (dx > 0 && catCurrent > 0) { catCurrent--; updateCatSlider(); }
            }
            catStartX = null;
            catMoveX = null;
        });
    }

    // Admin slider
    const adminRow = document.getElementById('admin-cards-row');
    const adminCards = adminRow ? adminRow.querySelectorAll('.admin-carousel-card') : [];
    const adminPrevBtn = document.getElementById('admin-slider-prev-btn');
    const adminNextBtn = document.getElementById('admin-slider-next-btn');
    let adminCurrent = 0;
    adminCurrent = adminCards.length > 0 ? adminCards.length - adminVisibleCount() : 0;
    function adminVisibleCount() {
        if (window.innerWidth >= 1200) return 4;
        if (window.innerWidth >= 768) return 2;
        return 1;
    }
    function updateAdminSlider() {
        const visible = adminVisibleCount();
        if (!adminRow) return;
        const maxIndex = adminCards.length - visible;
        // عرض البطاقات من اليمين لليسار فعلياً
        adminRow.style.transform = `translateX(-${adminCurrent * (100/visible)}%)`;
        // تمييز الكرت النشط
        adminCards.forEach((card, idx) => {
            if (idx === adminCurrent) card.classList.add('active');
            else card.classList.remove('active');
        });
        adminPrevBtn.style.display = (adminCurrent < adminCards.length - visible) ? 'flex' : 'none';
        adminNextBtn.style.display = (adminCurrent > 0) ? 'flex' : 'none';
        // Update dots
        const dots = document.getElementById('admin-slider-dots');
        if (dots) {
            dots.innerHTML = '';
            for (let i = 0; i <= maxIndex; i++) {
                const dot = document.createElement('span');
                dot.className = 'slider-dot' + (adminCurrent === i ? ' active' : '');
                dot.addEventListener('click', () => { adminCurrent = i; updateAdminSlider(); });
                dots.appendChild(dot);
            }
        }
    }
    // Touch/Swipe support
    let adminStartX = null, adminMoveX = null;
    if (adminCards.length > 0) {
        adminCards.forEach(card => card.classList.add('slider-card'));
        adminCurrent = Math.max(0, adminCards.length - adminVisibleCount());
        updateAdminSlider();
        window.addEventListener('resize', function() {
            const newVisible = adminVisibleCount();
            adminCurrent = Math.max(0, adminCards.length - newVisible);
            updateAdminSlider();
        });
        // زر السابق: ينتقل للكرت السابق (index أصغر)
        // زر السابق: ينتقل للكرت التالي (index أكبر)
        adminPrevBtn.addEventListener('click', function() {
            if (adminCurrent < adminCards.length - adminVisibleCount()) {
                adminCurrent++;
                updateAdminSlider();
            }
        });
        // زر التالي: ينتقل للكرت السابق (index أصغر)
        adminNextBtn.addEventListener('click', function() {
            if (adminCurrent > 0) {
                adminCurrent--;
                updateAdminSlider();
            }
        });
        // Touch events (start/move/end)
        adminRow.addEventListener('touchstart', function(e) {
            adminStartX = e.touches[0].clientX;
            adminMoveX = null;
        });
        adminRow.addEventListener('touchmove', function(e) {
            if (adminStartX !== null) {
                adminMoveX = e.touches[0].clientX;
            }
        });
        adminRow.addEventListener('touchend', function(e) {
            if (adminStartX === null) return;
            let endX = adminMoveX !== null ? adminMoveX : e.changedTouches[0].clientX;
            let dx = endX - adminStartX;
            if (Math.abs(dx) > 40) {
                // إذا سحبنا لليسار (dx < 0) ننتقل للكرت التالي (index أكبر)
                if (dx < 0 && adminCurrent < adminCards.length - adminVisibleCount()) { adminCurrent++; updateAdminSlider(); }
                // إذا سحبنا لليمين (dx > 0) ننتقل للكرت السابق (index أصغر)
                else if (dx > 0 && adminCurrent > 0) { adminCurrent--; updateAdminSlider(); }
            }
            adminStartX = null;
            adminMoveX = null;
        });
    }
});
</script>
