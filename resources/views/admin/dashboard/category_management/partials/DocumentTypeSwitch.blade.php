@php
    $col = $portal . '_enabled';
    $checked = $row->$col ? 'checked' : '';
@endphp
<div class="form-check form-switch d-flex justify-content-center">
    <input type="checkbox"
        class="form-check-input document-type-switch"
        data-id="{{ $row->id }}"
        data-portal="{{ $portal }}"
        {{ $checked }}>
</div>
