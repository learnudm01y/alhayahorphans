@php
    $enabledCol = $portal . '_enabled';
    $requiredCol = $portal . '_required';
    $checked = $row->$enabledCol ? 'checked' : '';
    $requiredChecked = $row->$requiredCol ? 'checked' : '';
    $optionalChecked = !$row->$requiredCol ? 'checked' : '';
    $dropdownId = 'dropdown_' . $portal . '_' . $row->id;
@endphp
<div class="dropdown">
    <button class="btn btn-outline-primary btn-sm dropdown-toggle w-100"
            type="button"
            id="{{ $dropdownId }}"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            onclick="toggleDropdown('{{ $dropdownId }}')">
        خيارات
    </button>
    <ul class="dropdown-menu text-center" style="min-width: 180px;" aria-labelledby="{{ $dropdownId }}">
        <li>
            <div class="form-check form-switch d-flex justify-content-center align-items-center mb-2">
                <input type="checkbox"
                    class="form-check-input document-type-switch"
                    data-id="{{ $row->id }}"
                    data-portal="{{ $portal }}"
                    {{ $checked }}>
                <label class="form-check-label ms-2">{{ $row->$enabledCol ? 'مفعل' : 'معطل' }}</label>
            </div>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input document-type-required-radio"
                        type="radio"
                        name="{{ $requiredCol }}_{{ $row->id }}"
                        data-id="{{ $row->id }}"
                        data-portal="{{ $portal }}"
                        value="1"
                        {{ $requiredChecked }}>
                    <label class="form-check-label text-danger" style="font-weight:bold;">إجباري</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input document-type-required-radio"
                        type="radio"
                        name="{{ $requiredCol }}_{{ $row->id }}"
                        data-id="{{ $row->id }}"
                        data-portal="{{ $portal }}"
                        value="0"
                        {{ $optionalChecked }}>
                    <label class="form-check-label text-success" style="font-weight:bold;">اختياري</label>
                </div>
            </div>
        </li>
    </ul>
</div>

<script>
function toggleDropdown(dropdownId) {
    const dropdownElement = document.getElementById(dropdownId);
    const dropdownMenu = dropdownElement.nextElementSibling;

    // إغلاق جميع القوائم المنسدلة الأخرى
    document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
        if (menu !== dropdownMenu) {
            menu.classList.remove('show');
            menu.previousElementSibling.setAttribute('aria-expanded', 'false');
        }
    });

    // تبديل حالة القائمة الحالية
    if (dropdownMenu.classList.contains('show')) {
        dropdownMenu.classList.remove('show');
        dropdownElement.setAttribute('aria-expanded', 'false');
    } else {
        dropdownMenu.classList.add('show');
        dropdownElement.setAttribute('aria-expanded', 'true');
    }
}

// إغلاق القائمة عند النقر خارجها
document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
            menu.classList.remove('show');
            menu.previousElementSibling.setAttribute('aria-expanded', 'false');
        });
    }
});
</script>
