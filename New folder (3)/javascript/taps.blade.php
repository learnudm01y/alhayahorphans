@push('scriptsCodeUserRegistration')
    <script>
        (function() {
            // Enable Bootstrap tabs inside a local scope to avoid redeclaration errors
            const triggerTabList = Array.prototype.slice.call(document.querySelectorAll('#formTabs button'));
            triggerTabList.forEach(function(triggerEl) {
                new bootstrap.Tab(triggerEl);
            });
        })();
    </script>
@endpush
