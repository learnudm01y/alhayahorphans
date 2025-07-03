<input type="hidden" name="family_members[{{ $idx }}][file_id]" value="{{ $file_id_number }}">
<div class="col-md-6">
    <label class="form-label">رقم التسجيل <span class="text-danger">*</span></label>
    <input type="text" name="family_members[{{ $idx }}][registration_id]" class="form-control bg-secondary bg-opacity-10" readonly value="{{ $file_id_number }}" maxlength="30">
</div>
<div class="col-md-4">
    <label class="form-label">رقم هوية اليتيم</label>
    <input type="text" name="family_members[{{ $idx }}][person_id]" class="form-control" inputmode="numeric" minlength="9" maxlength="10" pattern="[0-9]{9,10}" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
</div>
<div class="col-md-3">
    <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
    <input type="text" name="family_members[{{ $idx }}][first_name]" class="form-control" maxlength="30">
</div>
<div class="col-md-3">
    <label class="form-label">الاسم الثاني <span class="text-primary" style="color:#6c757d !important;">(اختياري)</span></label>
    <input type="text" name="family_members[{{ $idx }}][second_name]" class="form-control" maxlength="30">
</div>
<div class="col-md-3">
    <label class="form-label">الاسم الثالث <span class="text-primary" style="color:#6c757d !important;">(اختياري)</span></label>
    <input type="text" name="family_members[{{ $idx }}][third_name]" class="form-control" maxlength="30">
</div>
<div class="col-md-3">
    <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
    <input type="text" name="family_members[{{ $idx }}][last_name]" class="form-control" maxlength="30">
</div>
<div class="col-md-4">
    <label class="form-label">تاريخ الميلاد <span class="text-danger">*</span></label>
    <input type="date" name="family_members[{{ $idx }}][person_birth_date]" class="form-control">
</div>
<div class="col-md-4">
    <label class="form-label">العمر</label>
    <input type="number" name="family_members[{{ $idx }}][person_age]" class="form-control" readonly>
</div>
<div class="col-md-4">
    <label class="form-label">الجنس <span class="text-danger">*</span></label>
    <select name="family_members[{{ $idx }}][person_gender]" class="form-select">
        <option value="">اختر الجنس</option>
        <option value="1">ذكر</option>
        <option value="2">أنثى</option>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label">الحالة الصحية</label>
    <select name="family_members[{{ $idx }}][person_health_status]" class="form-select">
        <option value="">اختر الحالة</option>
        @foreach ($health_status as $status)
            <option value="{{ $status->id }}">{{ $status->description }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-4">
    <label class="form-label fw-bold text-primary">ملاحظة <span class="text-primary" style="color:#6c757d !important;">(اختياري)</span></label>
    <textarea name="family_members[{{ $idx }}][person_note]" cols="30" rows="4" class="form-control rounded shadow-sm border-primary bg-light" placeholder="أدخل ملاحظتك هنا..." style="resize: vertical; min-height: 80px;"></textarea>
</div>
<div class="col-md-4 mt-3">
    <!-- ملاحظة توضيحية لرفع الملفات -->
    <div class="alert alert-primary py-2 mb-2" style="font-size: 0.97rem;">يرجى اختيار نوع الوثيقة أولاً، وسوف يتم تحويلك لرفع الصورة المطلوبة.</div>
    <label class="form-label fw-bold"> رفع الملفات <span class="text-danger">*</span></label>
    <div class="upload-zone" data-upload-zone="family_{{ $idx }}">
        <select class="form-select mainDocumentTypeSelect" id="mainDocumentTypeSelect_{{ $idx }}">
            <option value="">اختر نوع الوثيقة</option>
            @foreach ($documentTypes->where('family_enabled', 1) as $documentType)
                <option value="{{ $documentType->pref }}">{{ $documentType->description }}</option>
            @endforeach
        </select>
        <input type="file" class="mainDocumentFileInput" id="mainDocumentFileInput_{{ $idx }}" accept="image/*,.pdf" style="display:none !important; visibility:hidden !important; width:0; height:0; pointer-events:none; opacity:0; position:absolute; left:-9999px;">
        <div class="mainDocumentPreview mt-2" id="mainDocumentPreview_{{ $idx }}"></div>
        <div class="mainDocumentNames mt-2" id="mainDocumentNames_{{ $idx }}"></div>
    </div>
</div>
