{{-- VARIABLES: personKey, personType, personIdentity, personName, recordId, fileId, sponsors, additionalId --}}
@php
    $personIdentity = trim((string) ($personIdentity ?? ''));
    $sponsors = $sponsors ?? collect();
    $additionalId = $additionalId ?? null;

    $exportQuery = ['person_type' => $personType];
    if ($additionalId) {
        $exportQuery['additional_id'] = $additionalId;
    }
    $exportUrl = route('admin.records.management.export-family-report', array_merge(['id' => $recordId], $exportQuery));
@endphp
@if($personIdentity !== '')
<div class="mt-3 w-100 d-flex flex-column gap-2">
    @if($sponsors->count() > 0)
        <div class="form-group mb-2">
            <label for="sponsor_select_{{ $personKey }}" class="form-label text-end d-block" style="font-size: 0.85rem;">
                <i class="bi bi-building"></i> اختر الجمعية لتصدير التقرير
            </label>
            <select id="sponsor_select_{{ $personKey }}" class="form-select form-select-sm sponsor-selector" style="font-size: 0.85rem;">
                <option value="">-- اختر الجمعية --</option>
                @foreach($sponsors as $sponsor)
                    <option value="{{ $sponsor->id }}">{{ $sponsor->sponsor_name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <button type="button" class="btn btn-sm btn-info view-more-btn"
                data-type="{{ $personType }}"
                data-person-id="{{ $personIdentity }}"
                data-file-id="{{ $fileId }}"
                data-person-name="{{ $personName }}">
            <i class="bi bi-eye"></i> مشاهدة المزيد
        </button>
        <a href="{{ $exportUrl }}"
           class="btn btn-sm btn-success export-report-btn"
           data-export-key="{{ $personKey }}"
           data-original-url="{{ $exportUrl }}"
           target="_blank">
            <i class="bi bi-file-earmark-pdf"></i> تصدير التقرير
        </a>
    </div>
</div>
@endif