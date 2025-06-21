{{-- في تبويب المرفقات --}}
<div class="row g-3">
    <!-- اختيار الشخص -->
    <div class="col-md-4">
        <label class="form-label fw-bold">اختر الشخص <span class="text-danger">*</span></label>
        <select id="person_selector" class="form-select">
            <option value="">اختر الشخص</option>
            <optgroup label="صاحب الملف">
                <option value="main"
                        data-id="{{ $data->file_id_number ?? $file_id_number ?? '' }}"
                        data-id-number="{{ $data->data_id_number ?? $data_id_number ?? '' }}">
                    {{ ($data->data_first_name ?? '') . ' ' .($data->data_family_name ?? '') }}
                </option>
            </optgroup>
            <optgroup label="أفراد الأسرة" id="family_members_options">
                @if(isset($data) && $edit && $data->rePeople && count($data->rePeople))
                    @foreach ($data->rePeople as $index => $member)
                        <option value="family_{{ $index }}"
                                data-id="{{ $data->file_id_number ?? $file_id_number ?? '' }}"
                                data-id-number="{{ $member->person_id ?? '' }}">
                            {{ $member->first_name }} {{ $member->last_name }}
                        </option>
                    @endforeach
                @elseif(isset($family_members) && count($family_members))
                    @foreach ($family_members as $index => $member)
                        <option value="family_{{ $index }}"
                                data-id="{{ $file_id_number ?? '' }}"
                                data-id-number="{{ $member['person_id'] ?? '' }}">
                            {{ $member['name'] ?? '' }}
                        </option>
                    @endforeach
                @endif
            </optgroup>
            <optgroup label="الأفراد المتوفين">
                <option value="deceased_father"
                        data-id="{{ $data->file_id_number ?? $file_id_number ?? '' }}"
                        data-id-number="{{ $data->deadPepole->father_id ?? $father_id_number ?? '' }}">
                    الأب المتوفى
                </option>
                <option value="deceased_mother"
                        data-id="{{ $data->file_id_number ?? $file_id_number ?? '' }}"
                        data-id-number="{{ $data->deadPepole->mother_id ?? $mother_id_number ?? '' }}">
                    الأم المتوفية
                </option>
            </optgroup>
        </select>
    </div>

    <!-- نوع الوثيقة -->
    <div class="col-md-4">
        <label class="form-label fw-bold">نوع الوثيقة <span class="text-danger">*</span></label>
        <select id="document_type" class="form-select">
            <option value="">اختر نوع الوثيقة</option>
            @foreach ($documentTypes as $documentType)
                <option value="{{ $documentType->pref }}">{{ $documentType->description }}</option>
            @endforeach
        </select>
    </div>

    <!-- رقم الملف العام (readonly) -->
    <div class="col-md-4">
        <label class="form-label fw-bold">رقم الملف</label>
        <input type="text" id="document_id" class="form-control bg-secondary bg-opacity-25" readonly
               value="{{ $data->file_id_number ?? $file_id_number ?? '' }}">
    </div>

    <!-- رفع ملفات متعددة -->
    <div class="col-12 mt-3">
        <div class="file-upload-wrapper">
            <input type="file"
                   id="document_file"
                   class="form-control"
                   accept="image/*,application/pdf"
                   multiple
            >
            <div id="preview" class="mt-3 d-none">
                <div id="previewList" class="d-flex flex-wrap gap-2"></div>
                <div class="mt-2 text-center">
                    <button type="button" id="confirmUpload" class="btn btn-success d-none">
                        <i class="fas fa-check me-2"></i>إضافة المرفقات
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- عرض المرفقات الموجودة سابقاً -->
<div class="card mt-4">
    <div class="card-body">
        <h5 class="card-title mb-3">الوثائق المرفقة</h5>
        <div id="documents_container">
            <!-- كما في السابق: بطاقات المرفقات لمختلف الأقسام -->
            <!-- قسم صاحب الملف -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title border-bottom pb-2">وثائق صاحب الملف</h5>
                    <div id="main_person_docs" class="d-flex flex-nowrap overflow-auto gap-3 py-2">
                        @if(isset($data) && $data->attachments)
                            @foreach($data->attachments->where('person_identity_number', $data->data_id_number) as $attachment)
                                <div class="document-card card">
                                    <div class="card-header d-flex justify-content-center align-items-center gap-2 p-2" style="min-height: 70px;">
                                        <h6 class="mb-1 small">{{ $attachment->file_type }}</h6>
                                        <div class="d-flex gap-2">
                                            <a
                                                href="{{ asset($attachment->file_path) }}"
                                                @if(Str::endsWith($attachment->file_path, ['jpg','jpeg','png']))
                                                    class="btn btn-sm btn-primary btn-preview-image"
                                                    data-img-src="{{ asset($attachment->file_path) }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#imagePreviewModal"
                                                    onclick="event.preventDefault(); document.getElementById('imagePreviewModalImg').src=this.getAttribute('data-img-src');"
                                                @else
                                                    class="btn btn-sm btn-primary"
                                                    target="_blank"
                                                @endif
                                            >
                                                عرض
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger delete-attachment" data-id="{{ $attachment->id }}">
                                                حذف
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body text-center">
                                        @if(Str::endsWith($attachment->file_path, ['jpg','jpeg','png']))
                                            <img src="{{ asset($attachment->file_path) }}" class="img-fluid" style="max-height:120px;">
                                        @else
                                            <span class="text-muted">{{ $attachment->stored_file_name }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
            <!-- قسم أفراد الأسرة -->
            <div class="card mb-5">
                <div class="card-body">
                    <h5 class="card-title border-bottom pb-2">وثائق أفراد الأسرة</h5>
                    <div id="family_members_docs">
                        @if(isset($data) && $data->rePeople && count($data->rePeople))
                            @foreach($data->rePeople as $index => $member)
                                @php
                                    $member_attachments = \App\Models\Attachment::where('person_identity_number', $member->person_id)->get();
                                @endphp
                                @if($member_attachments->count())
                                    <div class="mb-4">
                                        <div class="bg-primary bg-opacity-10 border border-primary rounded-top px-3 py-2 mb-2 text-primary fw-bold text-center" style="font-size: 1.1rem;">
                                            {{ $member->first_name }} {{ $member->last_name }}
                                        </div>
                                        <div class="documents-flex-container d-flex flex-nowrap gap-3 py-2" id="docs_container_family_{{ $index }}">
                                            @foreach($member_attachments as $attachment)
                                                <div class="document-card card">
                                                    <div class="card-header d-flex justify-content-center align-items-center gap-2 p-2" style="min-height: 70px;">
                                                        <h6 class="mb-1 small">{{ $attachment->file_type }}</h6>
                                                        <div class="d-flex gap-2">
                                                            <a
                                                                href="{{ asset($attachment->file_path) }}"
                                                                @if(Str::endsWith($attachment->file_path, ['jpg','jpeg','png']))
                                                                    class="btn btn-sm btn-primary btn-preview-image"
                                                                    data-img-src="{{ asset($attachment->file_path) }}"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#imagePreviewModal"
                                                                    onclick="event.preventDefault(); document.getElementById('imagePreviewModalImg').src=this.getAttribute('data-img-src');"
                                                                @else
                                                                    class="btn btn-sm btn-primary"
                                                                    target="_blank"
                                                                @endif
                                                            >
                                                                عرض
                                                            </a>
                                                            <button type="button" class="btn btn-sm btn-danger delete-attachment" data-id="{{ $attachment->id }}">حذف</button>
                                                        </div>
                                                    </div>
                                                    <div class="card-body text-center">
                                                        @if(Str::endsWith($attachment->file_path, ['jpg','jpeg','png']))
                                                            <img src="{{ asset($attachment->file_path) }}" class="img-fluid" style="max-height:120px;">
                                                        @else
                                                            <span class="text-muted">{{ $attachment->stored_file_name }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <span class="text-muted">لا توجد مرفقات لأفراد الأسرة.</span>
                        @endif
                    </div>
                </div>
            </div>
            <!-- قسم المتوفين -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title border-bottom pb-2 mb-4">
                        <i class="fas fa-user-times me-2"></i>وثائق الأفراد المتوفين
                    </h5>
                    <div class="row g-4">
                        @php
                            $deceased = isset($data) && $data->deadPepole ? $data->deadPepole : null;
                        @endphp
                        @if($deceased && ($deceased->father_id || $deceased->mother_id))
                            <div class="col-md-6">
                                <div class="deceased-docs-section">
                                    <div class="bg-primary bg-opacity-10 border border-primary rounded-top px-3 py-2 mb-2 text-primary fw-bold text-center">
                                        الأب المتوفى
                                    </div>
                                    <div id="father_docs" class="documents-flex-container d-flex flex-nowrap gap-3 py-2">
                                        @php
                                            $father_attachments = \App\Models\Attachment::where('person_identity_number', $deceased->father_id)->get();
                                        @endphp
                                        @forelse($father_attachments as $attachment)
                                            <div class="document-card card">
                                                <div class="card-header d-flex justify-content-center align-items-center gap-2 p-2" style="min-height: 70px;">
                                                    <h6 class="mb-1 small">{{ $attachment->file_type }}</h6>
                                                    <div class="d-flex gap-2">
                                                        <a
                                                            href="{{ asset($attachment->file_path) }}"
                                                            @if(Str::endsWith($attachment->file_path, ['jpg','jpeg','png']))
                                                                class="btn btn-sm btn-primary btn-preview-image"
                                                                data-img-src="{{ asset($attachment->file_path) }}"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#imagePreviewModal"
                                                                onclick="event.preventDefault(); document.getElementById('imagePreviewModalImg').src=this.getAttribute('data-img-src');"
                                                            @else
                                                                class="btn btn-sm btn-primary"
                                                                target="_blank"
                                                            @endif
                                                        >
                                                            عرض
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-danger delete-attachment" data-id="{{ $attachment->id }}">حذف</button>
                                                    </div>
                                                </div>
                                                <div class="card-body text-center">
                                                    @if(Str::endsWith($attachment->file_path, ['jpg','jpeg','png']))
                                                        <img src="{{ asset($attachment->file_path) }}" class="img-fluid" style="max-height:120px;">
                                                    @else
                                                        <span class="text-muted">{{ $attachment->stored_file_name }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <span class="text-muted">لا توجد مرفقات للأب المتوفى.</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="deceased-docs-section">
                                    <div class="bg-primary bg-opacity-10 border border-primary rounded-top px-3 py-2 mb-2 text-primary fw-bold text-center">
                                        الأم المتوفية
                                    </div>
                                    <div id="mother_docs" class="documents-flex-container d-flex flex-nowrap gap-3 py-2">
                                        @php
                                            $mother_attachments = \App\Models\Attachment::where('person_identity_number', $deceased->mother_id)->get();
                                        @endphp
                                        @forelse($mother_attachments as $attachment)
                                            <div class="document-card card">
                                                <div class="card-header d-flex justify-content-center align-items-center gap-2 p-2" style="min-height: 70px;">
                                                    <h6 class="mb-1 small">{{ $attachment->file_type }}</h6>
                                                    <div class="d-flex gap-2">
                                                        <a
                                                            href="{{ asset($attachment->file_path) }}"
                                                            @if(Str::endsWith($attachment->file_path, ['jpg','jpeg','png']))
                                                                class="btn btn-sm btn-primary btn-preview-image"
                                                                data-img-src="{{ asset($attachment->file_path) }}"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#imagePreviewModal"
                                                                onclick="event.preventDefault(); document.getElementById('imagePreviewModalImg').src=this.getAttribute('data-img-src');"
                                                            @else
                                                                class="btn btn-sm btn-primary"
                                                                target="_blank"
                                                            @endif
                                                        >
                                                            عرض
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-danger delete-attachment" data-id="{{ $attachment->id }}">حذف</button>
                                                    </div>
                                                </div>
                                                <div class="card-body text-center">
                                                    @if(Str::endsWith($attachment->file_path, ['jpg','jpeg','png']))
                                                        <img src="{{ asset($attachment->file_path) }}" class="img-fluid" style="max-height:120px;">
                                                    @else
                                                        <span class="text-muted">{{ $attachment->stored_file_name }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <span class="text-muted">لا توجد مرفقات للأم المتوفية.</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        @else
                            <span class="text-muted text-center">لا توجد بيانات متوفين أو مرفقات متوفين.</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- حاوية عرض الصورة (Modal) -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imagePreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="imagePreviewModalLabel">معاينة الصورة</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>
      <div class="modal-body text-center">
        <img id="imagePreviewModalImg" src="" alt="معاينة" class="img-fluid" style="max-height:70vh;">
      </div>
    </div>
  </div>
</div>

<!-- أضف هذا السكريبت أسفل الصفحة أو بعد المودال مباشرة -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-preview-image').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var imgSrc = this.getAttribute('data-img-src');
            var img = document.getElementById('imagePreviewModalImg');
            if(img && imgSrc) {
                img.src = imgSrc;
            }
        });
    });
    // عند إغلاق المودال، امسح الصورة
    var modal = document.getElementById('imagePreviewModal');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', function () {
            var img = document.getElementById('imagePreviewModalImg');
            if(img) img.src = '';
        });
    }
});
</script>
