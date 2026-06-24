<!-- حقل مخفي لرقم الملف -->
<input type="hidden" name="family_members[{{ $idx }}][file_id]" value="{{ $file_id_number }}">

<!-- رقم التسجيل -->
<div class="col-md-6">
    <label class="form-label">رقم التسجيل <span class="text-danger">*</span></label>
    <input type="text" name="family_members[{{ $idx }}][registration_id]" class="form-control bg-secondary bg-opacity-10" readonly value="{{ $file_id_number }}" maxlength="30">
</div>

<!-- رقم هوية اليتيم -->
<div class="col-md-6">
    <label class="form-label">رقم هوية اليتيم</label>
    <div class="input-group">
        <input type="text" name="family_members[{{ $idx }}][person_id]" class="form-control family-member-id-input" data-member-index="{{ $idx }}" inputmode="numeric" minlength="9" maxlength="10" pattern="[0-9]{9,10}" oninput="this.value = this.value.replace(/[^0-9]/g, '');" placeholder="أدخل رقم الهوية لجلب البيانات">
        <span class="input-group-text family-member-search-status" data-member-index="{{ $idx }}" style="display:none;">
            <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
        </span>
    </div>
    <small class="text-muted">سيتم جلب الاسم وتاريخ الميلاد تلقائياً من قاعدة البيانات المركزية إن توفر</small>
</div>

<!-- الاسم الأول -->
<div class="col-md-3">
    <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
    <input type="text" name="family_members[{{ $idx }}][first_name]" class="form-control" maxlength="30">
</div>

<!-- الاسم الثاني -->
<div class="col-md-3">
    <label class="form-label">الاسم الثاني <span class="text-primary" style="color:#6c757d !important;">(اختياري)</span></label>
    <input type="text" name="family_members[{{ $idx }}][second_name]" class="form-control" maxlength="30">
</div>

<!-- الاسم الثالث -->
<div class="col-md-3">
    <label class="form-label">الاسم الثالث <span class="text-primary" style="color:#6c757d !important;">(اختياري)</span></label>
    <input type="text" name="family_members[{{ $idx }}][third_name]" class="form-control" maxlength="30">
</div>

<!-- اسم العائلة -->
<div class="col-md-3">
    <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
    <input type="text" name="family_members[{{ $idx }}][last_name]" class="form-control" maxlength="30">
</div>

<!-- تاريخ الميلاد -->
<div class="col-md-4">
    <label class="form-label">تاريخ الميلاد <span class="text-danger">*</span></label>
    <input type="date" name="family_members[{{ $idx }}][person_birth_date]" class="form-control">
</div>

<!-- العمر -->
<div class="col-md-4">
    <label class="form-label">العمر</label>
    <input type="number" name="family_members[{{ $idx }}][person_age]" class="form-control" readonly>
</div>

<!-- الجنس -->
<div class="col-md-4">
    <label class="form-label">الجنس <span class="text-danger">*</span></label>
    <select name="family_members[{{ $idx }}][person_gender]" class="form-select">
        <option value="">اختر الجنس</option>
        <option value="1">ذكر</option>
        <option value="2">أنثى</option>
    </select>
</div>

<!-- الحالة الصحية -->
<div class="col-md-4">
    <label class="form-label">الحالة الصحية</label>
    <select name="family_members[{{ $idx }}][person_health_status]" class="form-select">
        <option value="">اختر الحالة</option>
        @foreach ($health_status->where('description', '!=', 'Unknown') as $status)
            <option value="{{ $status->id }}">{{ $status->description }}</option>
        @endforeach
    </select>
</div>

<!-- ملاحظة -->
<div class="col-md-4">
    <label class="form-label fw-bold text-primary">ملاحظة <span class="text-primary" style="color:#6c757d !important;">(اختياري)</span></label>
    <textarea name="family_members[{{ $idx }}][person_note]" cols="30" rows="4" class="form-control rounded shadow-sm border-primary bg-light" placeholder="أدخل ملاحظتك هنا..." style="resize: vertical; min-height: 80px;"></textarea>
</div>

<!-- رفع الملفات -->
<div class="col-md-4 mt-3">
    <!-- ملاحظة توضيحية لرفع الملفات -->
    <div class="alert alert-primary py-2 mb-2" style="font-size: 0.97rem;">
        يرجى اختيار نوع الوثيقة أولاً، وسوف يتم تحويلك لرفع الصورة المطلوبة.
    </div>
    <div class="alert alert-warning py-2 mb-2" style="font-size: 0.9rem;">
        <i class="fas fa-star text-danger me-1"></i>
        <strong>الوثائق المميزة بعلامة <span class="text-danger">★</span> إجبارية ويجب إدخالها</strong>
    </div>
    <label class="form-label fw-bold">رفع الملفات <span class="text-danger">*</span></label>
    <div class="upload-zone" data-upload-zone="family_{{ $idx }}">
        <select class="form-select mainDocumentTypeSelect document-type-select" id="mainDocumentTypeSelect_{{ $idx }}">
            <option value="">اختر نوع الوثيقة</option>
            @foreach ($documentTypes->where('family_enabled', 1)->where('description', '!=', 'Unknown') as $documentType)
                <option value="{{ $documentType->pref }}" {{ $documentType->family_required ? 'data-required=true' : '' }}>
                    {{ $documentType->family_required ? '★ ' : '' }}{{ $documentType->description }}{{ $documentType->family_required ? ' (إجباري)' : '' }}
                </option>
            @endforeach
        </select>
        <input type="file"
               class="mainDocumentFileInput document-file-input"
               id="mainDocumentFileInput_{{ $idx }}"
               style="display:none !important; visibility:hidden !important; width:0; height:0; pointer-events:none; opacity:0; position:absolute; left:-9999px;">
        <div class="mainDocumentPreview mt-2" id="mainDocumentPreview_{{ $idx }}"></div>
        <div class="mainDocumentNames mt-2" id="mainDocumentNames_{{ $idx }}"></div>
    </div>
</div>

<!-- إضافة CSS لتحسين تجربة الجوال -->
<style>
    /* تحسينات عامة لحقل رفع الملفات */
    .mainDocumentFileInput {
        transition: all 0.3s ease;
    }

    /* تحسين مظهر زر اختيار الوثيقة */
    .mainDocumentTypeSelect {
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .mainDocumentTypeSelect:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        outline: 0;
    }

    /* تحسين مظهر منطقة الرفع */
    .upload-zone {
        position: relative;
        transition: opacity 0.3s ease;
    }

    /* مؤشر التحميل */
    .upload-zone.loading {
        pointer-events: none;
        opacity: 0.7;
    }

    .upload-zone.loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 20px;
        height: 20px;
        margin: -10px 0 0 -10px;
        border: 2px solid #f3f3f3;
        border-radius: 50%;
        border-top: 2px solid #0d6efd;
        animation: spin 1s linear infinite;
        z-index: 10;
    }

    /* أنيميشن الدوران */
    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }
        100% {
            transform: rotate(360deg);
        }
    }

    /* تحسينات للجوال */
    @media (max-width: 768px) {
        .upload-zone {
            margin-bottom: 1rem;
        }

        .mainDocumentTypeSelect {
            font-size: 16px; /* منع التكبير التلقائي في iOS */
        }

        .alert {
            font-size: 0.9rem;
        }
    }

    /* تحسينات للشاشات الكبيرة */
    @media (min-width: 769px) {
        .upload-zone {
            min-height: 120px;
        }
    }

    /* تحسينات إضافية لمعاينة الصور */
    .mainDocumentPreview img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
    }

    .mainDocumentPreview img:hover {
        transform: scale(1.05);
    }

    /* تحسين مظهر أسماء الملفات */
    .mainDocumentNames {
        padding: 0.5rem;
        background-color: #f8f9fa;
        border-radius: 6px;
        border: 1px solid #dee2e6;
    }

    .mainDocumentNames .file-name {
        color: #495057;
        font-weight: 500;
        word-break: break-word;
    }
</style>
