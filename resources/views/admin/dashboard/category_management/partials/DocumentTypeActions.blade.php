<div class="btn-group" role="group">
    <button type="button" class="btn btn-warning btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#editDocumentTypeModal"
        data-id="{{ $row->id }}"
        data-description="{{ $row->description }}"
        data-action="{{ route('admin.DocumentType_name.update', $row->id) }}">
        <i class="fas fa-edit"></i>
    </button>
    <button type="button" class="btn btn-danger btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#deleteDocumentTypeModal"
        data-id="{{ $row->id }}"
        data-description="{{ $row->description }}"
        data-action="{{ route('admin.DocumentType_name.destroy', $row->id) }}">
        <i class="fas fa-trash"></i>
    </button>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // تحديث حالة on/off لأي بوابة
    document.querySelectorAll('.document-type-switch').forEach(function(switchEl) {
        switchEl.addEventListener('change', function() {
            const id = this.dataset.id;
            const portal = this.dataset.portal;
            const enabled = this.checked ? 1 : 0;
            fetch("{{ route('admin.DocumentType_name.update', 0) }}".replace('/0', '/' + id), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    portal: portal,
                    enabled: enabled,
                    _method: 'PATCH'
                })
            })
            .then(res => res.json())
            .then(data => {
                console.log('DocumentTypeSwitch response:', data);
                if (data.success) {
                    alert('تم تحديث حالة الوثيقة بنجاح');
                    // إعادة تحميل الجدول بعد التحديث
                    if (window.LaravelDataTables && window.LaravelDataTables['documenttype-table']) {
                        window.LaravelDataTables['documenttype-table'].ajax.reload(null, false);
                    }
                } else {
                    alert('فشل التحديث: ' + (data.error || 'خطأ غير معروف'));
                }
            })
            .catch((err) => {
                alert('حدث خطأ أثناء الاتصال بالخادم');
                console.error(err);
            });
        });
    });
});
</script>
