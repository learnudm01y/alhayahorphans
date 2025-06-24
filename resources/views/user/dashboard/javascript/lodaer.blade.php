
<div id="loader" style="display: none; position: fixed; ...">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">جاري التحميل...</span>
    </div>
</div>

<script>
    document.querySelectorAll('a[data-url]').forEach(link => {
    link.addEventListener('click', function(e) {
        // لا تقوم بتكرار الطلب هنا
        // فقط أظهر الـ loader واترك الباقي للسكريبت الأساسي

        // إظهار اللودر
        const loader = document.getElementById('loader');
        if (loader) loader.style.display = 'flex';
    });
});
    </script>
