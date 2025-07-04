@php
    $col = $portal . '_required';
    $requiredChecked = $row->$col ? 'checked' : '';
    $optionalChecked = !$row->$col ? 'checked' : '';
@endphp
<div class="form-check form-check-inline">
    <input class="form-check-input document-type-required-radio"
           type="radio"
           name="{{ $col }}_{{ $row->id }}"
           data-id="{{ $row->id }}"
           data-portal="{{ $portal }}"
           value="1"
           {{ $requiredChecked }}>
    <label class="form-check-label text-danger" style="font-weight:bold;">إجباري</label>
</div>
<div class="form-check form-check-inline">
    <input class="form-check-input document-type-required-radio"
           type="radio"
           name="{{ $col }}_{{ $row->id }}"
           data-id="{{ $row->id }}"
           data-portal="{{ $portal }}"
           value="0"
           {{ $optionalChecked }}>
    <label class="form-check-label text-success" style="font-weight:bold;">اختياري</label>
</div>
