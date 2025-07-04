@php
    $enabledCol = $portal . '_enabled';
    $requiredCol = $portal . '_required';
    $checked = $row->$enabledCol ? 'checked' : '';
    $requiredChecked = $row->$requiredCol ? 'checked' : '';
    $optionalChecked = !$row->$requiredCol ? 'checked' : '';
@endphp
<div class="dropdown">
    <button class="btn btn-outline-primary btn-sm dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        خيارات
    </button>
    <ul class="dropdown-menu text-center" style="min-width: 180px;">
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
