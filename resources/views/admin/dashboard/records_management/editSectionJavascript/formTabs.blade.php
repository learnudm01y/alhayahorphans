<script>
    // Enable Bootstrap tabs
    // تعريف المتغير مرة واحدة فقط في أعلى الملف
    if (typeof window.triggerTabList === 'undefined') {
        window.triggerTabList = [].slice.call(document.querySelectorAll('#formTabs button'));
        window.triggerTabList.forEach(function(triggerEl) {
            new bootstrap.Tab(triggerEl);
        });
    }
</script>
