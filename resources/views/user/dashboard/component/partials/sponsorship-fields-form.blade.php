{{-- نموذج الحقول الديناميكية للكفالة --}}
@if(isset($groupedFields) && count($groupedFields) > 0)
    @foreach($groupedFields as $categoryId => $categoryData)
        <div class="card card-custom p-4 mb-4">
            <h4 class="field-group-title">
                <i class="bi bi-folder2-open me-2"></i>
                {{ $categoryData['name'] }}
            </h4>

            <div class="row">
                @foreach($categoryData['fields'] as $field)
                    @php
                        // استخراج القيمة من مصفوفة القيم المحضرة
                        $value = $fieldValues[$field['db_column']] ?? '';
                        $fieldKey = str_replace('field_', '', $field['db_column']);
                    @endphp

                    <div class="col-md-6 col-12 mb-3">
                        <label class="form-label">
                            {{ $field['display_name'] }}
                            @if($field['required'])
                                <span class="text-danger">*</span>
                            @endif
                        </label>

                        @if(in_array($fieldKey, ['description', 'notes', 'description_needs']))
                            {{-- حقول نصية طويلة --}}
                            <textarea
                                name="fields[{{ $field['db_column'] }}]"
                                class="form-control"
                                rows="3"
                                {{ $field['required'] ? 'required' : '' }}
                            >{{ old('fields.' . $field['db_column'], $value) }}</textarea>
                        @elseif(Str::contains($fieldKey, ['date', 'birth']))
                            {{-- حقول التاريخ --}}
                            <input
                                type="date"
                                name="fields[{{ $field['db_column'] }}]"
                                class="form-control"
                                value="{{ old('fields.' . $field['db_column'], $value) }}"
                                {{ $field['required'] ? 'required' : '' }}
                            >
                        @elseif(in_array($fieldKey, ['phone', 'guardian_phone', 'alt_phone_number']))
                            {{-- حقول الهاتف --}}
                            <input
                                type="tel"
                                name="fields[{{ $field['db_column'] }}]"
                                class="form-control"
                                value="{{ old('fields.' . $field['db_column'], $value) }}"
                                pattern="[0-9]*"
                                {{ $field['required'] ? 'required' : '' }}
                            >
                        @elseif($fieldKey === 'email')
                            {{-- حقل البريد الإلكتروني --}}
                            <input
                                type="email"
                                name="fields[{{ $field['db_column'] }}]"
                                class="form-control"
                                value="{{ old('fields.' . $field['db_column'], $value) }}"
                                {{ $field['required'] ? 'required' : '' }}
                            >
                        @else
                            {{-- حقول نصية عادية --}}
                            <input
                                type="text"
                                name="fields[{{ $field['db_column'] }}]"
                                class="form-control"
                                value="{{ old('fields.' . $field['db_column'], $value) }}"
                                {{ $field['required'] ? 'required' : '' }}
                            >
                        @endif

                        @error('fields.' . $field['db_column'])
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@else
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        لا توجد حقول مفعلة لهذه الجمعية. يرجى التواصل مع الإدارة.
    </div>
@endif

{{-- قسم أفراد الأسرة (قابل للتعديل) --}}
<div class="card card-custom p-4 mb-4">
    <h4 class="field-group-title">
        <i class="bi bi-people-fill me-2"></i>
        أفراد الأسرة
    </h4>

    <div id="family-members-container">
        @if(isset($sponsorship->relationData->rePeople) && count($sponsorship->relationData->rePeople) > 0)
            @foreach($sponsorship->relationData->rePeople as $index => $member)
                <div class="card mb-3 family-member-item" data-member-index="{{ $index }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">فرد رقم {{ $index + 1 }}</h5>
                            <button type="button" class="btn btn-sm btn-danger remove-family-member">
                                <i class="bi bi-trash"></i> حذف
                            </button>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">اسم الفرد <span class="text-danger">*</span></label>
                                <input type="text" name="family_members[{{ $index }}][person_name]"
                                       class="form-control" value="{{ $member->person_name }}" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">صلة القرابة <span class="text-danger">*</span></label>
                                <input type="text" name="family_members[{{ $index }}][person_relationship]"
                                       class="form-control" value="{{ $member->person_relationship }}" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاريخ الميلاد</label>
                                <input type="date" name="family_members[{{ $index }}][person_birth_date]"
                                       class="form-control" value="{{ $member->person_birth_date }}">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">الحالة الصحية</label>
                                <input type="text" name="family_members[{{ $index }}][person_health_status]"
                                       class="form-control" value="{{ $member->person_health_status }}">
                            </div>

                            <input type="hidden" name="family_members[{{ $index }}][id]" value="{{ $member->id }}">
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <button type="button" class="btn btn-success" id="add-family-member">
        <i class="bi bi-plus-circle me-2"></i>
        إضافة فرد جديد
    </button>
</div>

{{-- قسم المعلومات البنكية (عرض فقط) --}}
@if(isset($approvedBankAccount) && $approvedBankAccount)
    <div class="card card-custom mb-4">
        <div class="card-body bank-info-card">
            <h4 class="mb-4">
                <i class="bi bi-bank2 me-2"></i>
                المعلومات البنكية المعتمدة
            </h4>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="info-label">اسم البنك</div>
                    <div class="info-value">
                        @php
                            $bankName = $approvedBankAccount->bank_name;
                            if(is_numeric($bankName)) {
                                $bankModel = \App\Models\BankName::find($bankName);
                                $bankName = $bankModel ? $bankModel->description : $approvedBankAccount->bank_name;
                            }
                        @endphp
                        {{ $bankName }}
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="info-label">اسم صاحب الحساب</div>
                    <div class="info-value">{{ $approvedBankAccount->re_guardian_name }}</div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="info-label">رقم الآيبان (شيكل)</div>
                    <div class="info-value">{{ $approvedBankAccount->iban_shekel }}</div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="info-label">رقم الآيبان (دولار)</div>
                    <div class="info-value">{{ $approvedBankAccount->iban_usd }}</div>
                </div>
            </div>

            <div class="alert alert-light mt-3">
                <i class="bi bi-info-circle me-2"></i>
                لا يمكن تعديل المعلومات البنكية. للتحديث، يرجى التواصل مع الإدارة.
            </div>
        </div>
    </div>
@endif

{{-- قسم المرفقات --}}
@if(isset($enabledFields) && isset($enabledFields['field_attachments']))
    <div class="card card-custom p-4 mb-4">
        <h4 class="field-group-title">
            <i class="bi bi-paperclip me-2"></i>
            المرفقات
        </h4>

        <div class="mb-3">
            <label class="form-label">رفع مرفقات جديدة (PDF, JPG, PNG)</label>
            <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png">
            <small class="text-muted">يمكنك رفع عدة ملفات. الحجم الأقصى للملف: 10MB</small>
        </div>

        @if(isset($sponsorship->relationData->attachments) && count($sponsorship->relationData->attachments) > 0)
            <div class="existing-attachments">
                <h6 class="mb-3">المرفقات الحالية:</h6>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($sponsorship->relationData->attachments as $att)
                        @php
                            $isImage = Str::endsWith(strtolower($att->stored_file_name), ['jpg','jpeg','png','gif']);
                        @endphp
                        <div class="attachment-item position-relative">
                            @if($isImage)
                                <img src="{{ route('admin.file.show', ['filename' => $att->stored_file_name]) }}"
                                     class="attachment-thumb" alt="مرفق" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px;">
                            @else
                                <div class="attachment-thumb d-flex align-items-center justify-content-center bg-light"
                                     style="width: 100px; height: 100px; border-radius: 8px;">
                                    <i class="bi bi-file-earmark-pdf" style="font-size: 2rem; color: #d9534f;"></i>
                                </div>
                            @endif
                            <a href="{{ route('admin.file.show', ['filename' => $att->stored_file_name]) }}"
                               target="_blank" class="btn btn-sm btn-primary position-absolute bottom-0 start-0 m-1">
                                <i class="bi bi-eye"></i>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endif

<style>
    .family-member-item {
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        transition: all 0.3s ease;
    }
    .family-member-item:hover {
        border-color: #3b82f6;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
    }
    .attachment-item {
        display: inline-block;
        margin: 5px;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let memberIndex = {{ isset($sponsorship->relationData->rePeople) ? count($sponsorship->relationData->rePeople) : 0 }};

    // إضافة فرد جديد
    document.getElementById('add-family-member').addEventListener('click', function() {
        const container = document.getElementById('family-members-container');
        const memberHtml = `
            <div class="card mb-3 family-member-item" data-member-index="${memberIndex}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">فرد رقم ${memberIndex + 1}</h5>
                        <button type="button" class="btn btn-sm btn-danger remove-family-member">
                            <i class="bi bi-trash"></i> حذف
                        </button>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">اسم الفرد <span class="text-danger">*</span></label>
                            <input type="text" name="family_members[${memberIndex}][person_name]"
                                   class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">صلة القرابة <span class="text-danger">*</span></label>
                            <input type="text" name="family_members[${memberIndex}][person_relationship]"
                                   class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">تاريخ الميلاد</label>
                            <input type="date" name="family_members[${memberIndex}][person_birth_date]"
                                   class="form-control">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">الحالة الصحية</label>
                            <input type="text" name="family_members[${memberIndex}][person_health_status]"
                                   class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', memberHtml);
        memberIndex++;
    });

    // حذف فرد
    document.addEventListener('click', function(e) {
        if(e.target.closest('.remove-family-member')) {
            if(confirm('هل أنت متأكد من حذف هذا الفرد؟')) {
                e.target.closest('.family-member-item').remove();
            }
        }
    });
});
</script>
