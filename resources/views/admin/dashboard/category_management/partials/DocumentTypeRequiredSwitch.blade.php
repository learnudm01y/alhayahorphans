@php
    $requiredChecked = $row->is_required ? 'checked' : '';
    $optionalChecked = !$row->is_required ? 'checked' : '';
@endphp
<div class="form-check form-check-inline">
    <input class="form-check-input document-type-required-radio"
           type="radio"
           name="is_required_{{ $row->id }}"
           data-id="{{ $row->id }}"
           value="1"
           {{ $requiredChecked }}>
    <label class="form-check-label text-danger" style="font-weight:bold;">إجباري</label>
</div>
<div class="form-check form-check-inline">
    <input class="form-check-input document-type-required-radio"
           type="radio"
           name="is_required_{{ $row->id }}"
           data-id="{{ $row->id }}"
           value="0"
           {{ $optionalChecked }}>
    <label class="form-check-label text-success" style="font-weight:bold;">اختياري</label>
</div>
