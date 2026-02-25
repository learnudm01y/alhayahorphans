@php
    // التأكد من جلب بيانات المتوفين
    $dead = $data->deadPepole ?? null;

    // تحديد ما إذا كانت بيانات الأم موجودة لفتح القسم مباشرة
    $showMother = $dead && (
        trim(($dead->mother_first_name ?? '') . ($dead->mother_last_name ?? '') . ($dead->mother_id ?? '') . ($dead->mother_death_date ?? '') . ($dead->mother_death_reason ?? '')) !== ''
    );
@endphp

<div class="row g-3">
    <!-- بيانات الأب المتوفى -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-gradient-primary text-dark py-3">
                <h5 class="card-title mb-0 d-flex align-items-center">
                    <i class="fas fa-male fs-4 me-2"></i>
                    بيانات الأب المتوفى
                </h5>
            </div>
            <div class="card-body bg-light">
                <div class="row g-3">
                    <input type="hidden" name="re_file_id" value="{{ $data->file_id_number ?? $file_id_number ?? '' }}">
                    <div class="col-12">
                        <label class="form-label fw-semibold">رقم الهوية <span class="text-danger">*</span></label>
                        <div class="input-group" style="max-width:320px;">
                            <input type="text" name="father_id" class="form-control" inputmode="numeric" pattern="[0-9]*" maxlength="9"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                                   value="{{ old('father_id', $dead ? $dead->father_id : '') }}"
                                   placeholder="أدخل رقم الهوية للجلب التلقائي">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                        <input type="text" name="father_first_name" class="form-control"
                               value="{{ old('father_first_name', $dead ? $dead->father_first_name : '') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الاسم الثاني</label>
                        <input type="text" name="father_second_name" class="form-control"
                               value="{{ old('father_second_name', $dead ? $dead->father_second_name : '') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الاسم الثالث</label>
                        <input type="text" name="father_third_name" class="form-control"
                               value="{{ old('father_third_name', $dead ? $dead->father_third_name : '') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
                        <input type="text" name="father_last_name" class="form-control"
                               value="{{ old('father_last_name', $dead ? $dead->father_last_name : '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">تاريخ الوفاة <span class="text-danger">*</span></label>
                        <input type="date" name="father_death_date" class="form-control"
                               value="{{ old('father_death_date', $dead ? $dead->father_death_date : '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">سبب الوفاة <span class="text-danger">*</span></label>
                        <select name="father_death_reason" class="form-select">
                            <option value="">اختر سبب الوفاة</option>
                            @foreach ($death_reasons as $deathReason)
                                <option value="{{ $deathReason->id }}"
                                    {{ old('father_death_reason', $dead ? $dead->father_death_reason : '') == $deathReason->id ? 'selected' : '' }}>
                                    {{ $deathReason->description }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- زر فتح قسم بيانات الأم المتوفاة -->
    <div class="col-12 mb-3">
        <button type="button" class="btn btn-primary w-100" id="toggleMotherInfo">
            <i class="fas fa-plus me-2"></i>بيانات الأم المتوفاة
        </button>
    </div>

    <!-- قسم بيانات الأم المتوفاة -->
    <div class="col-12">
        <div id="motherInfoSection" class="collapse @if($showMother) show @endif">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-gradient-primary text-dark py-3">
                    <h5 class="card-title mb-0 d-flex align-items-center">
                        <i class="fas fa-female fs-4 me-2"></i>
                        بيانات الأم المتوفاة
                    </h5>
                </div>
                <div class="card-body bg-light">
                    <div class="row g-3">
                        <input type="hidden" name="re_file_id" value="{{ $data->file_id_number ?? $file_id_number ?? '' }}">
                        <div class="col-12">
                            <label class="form-label fw-semibold">رقم الهوية <span class="text-danger">*</span></label>
                            <div class="input-group" style="max-width:320px;">
                                <input type="text" name="mother_id" class="form-control" inputmode="numeric" pattern="[0-9]*" maxlength="9"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                                       value="{{ old('mother_id', $dead ? $dead->mother_id : '') }}"
                                       placeholder="أدخل رقم الهوية للجلب التلقائي">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                            <input type="text" name="mother_first_name" class="form-control"
                                   value="{{ old('mother_first_name', $dead ? $dead->mother_first_name : '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">الاسم الثاني</label>
                            <input type="text" name="mother_second_name" class="form-control"
                                   value="{{ old('mother_second_name', $dead ? $dead->mother_second_name : '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">الاسم الثالث</label>
                            <input type="text" name="mother_third_name" class="form-control"
                                   value="{{ old('mother_third_name', $dead ? $dead->mother_third_name : '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
                            <input type="text" name="mother_last_name" class="form-control"
                                   value="{{ old('mother_last_name', $dead ? $dead->mother_last_name : '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">تاريخ الوفاة <span class="text-danger">*</span></label>
                            <input type="date" name="mother_death_date" class="form-control"
                                   value="{{ old('mother_death_date', $dead ? $dead->mother_death_date : '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">سبب الوفاة <span class="text-danger">*</span></label>
                            <select name="mother_death_reason" class="form-select">
                                <option value="">اختر سبب الوفاة</option>
                                @foreach ($death_reasons as $deathReason)
                                    <option value="{{ $deathReason->id }}"
                                        {{ old('mother_death_reason', $dead ? $dead->mother_death_reason : '') == $deathReason->id ? 'selected' : '' }}>
                                        {{ $deathReason->description }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         قسم المتوفين الإضافيين (غير الأب والأم)
         ============================================================ --}}
    <div class="col-12 mt-4">
        {{-- حقل مخفي يدل على أن قسم المتوفين الإضافيين قد أُرسل --}}
        <input type="hidden" name="additional_deceased_submitted" value="1">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-gradient-danger text-dark py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 d-flex align-items-center">
                    <i class="fas fa-users-slash fs-4 me-2"></i>
                    متوفون إضافيون
                </h5>
                <button type="button" class="btn btn-success btn-sm" id="addAdditionalDeceasedBtn">
                    <i class="fas fa-plus me-1"></i>إضافة متوفي
                </button>
            </div>
            <div class="card-body bg-light">
                <div id="additionalDeceasedContainer">
                    @if(isset($additionalDeceased) && $additionalDeceased->count() > 0)
                        @foreach($additionalDeceased as $adIndex => $adDeceased)
                            <div class="additional-deceased-form border rounded p-3 mb-3 position-relative"
                                 data-index="{{ $adIndex }}"
                                 style="border: 2px dashed #dc3545 !important; background:#fff8f8;">
                                <input type="hidden" name="additional_deceased[{{ $adIndex }}][existing_id]" value="{{ $adDeceased->id }}">
                                <button type="button"
                                    class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2 remove-additional-deceased"
                                    title="حذف">
                                    <i class="fas fa-times"></i>
                                </button>
                                <h6 class="text-danger fw-bold mb-3">
                                    <i class="fas fa-user-times me-2"></i>متوفي رقم {{ $adIndex + 1 }}
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">رقم الهوية</label>
                                        <div class="input-group">
                                            <input type="text"
                                                   name="additional_deceased[{{ $adIndex }}][id_number]"
                                                   class="form-control additional-deceased-id-input"
                                                   data-index="{{ $adIndex }}"
                                                   inputmode="numeric"
                                                   maxlength="9"
                                                   oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                                                   value="{{ $adDeceased->person_id }}"
                                                   placeholder="رقم الهوية">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">صلة القرابة</label>
                                        <select name="additional_deceased[{{ $adIndex }}][relationship]" class="form-select">
                                            <option value="">اختر</option>
                                            @foreach([
                                                'father'=>'أب','mother'=>'أم','brother'=>'أخ',
                                                'sister'=>'أخت','grandfather'=>'جد','grandmother'=>'جدة',
                                                'uncle'=>'عم','aunt'=>'عمة','other'=>'أخرى'
                                            ] as $val => $label)
                                                <option value="{{ $val }}" {{ $adDeceased->relationship == $val ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">تاريخ الوفاة</label>
                                        <input type="date"
                                               name="additional_deceased[{{ $adIndex }}][death_date]"
                                               class="form-control"
                                               value="{{ $adDeceased->death_date }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">الاسم الأول</label>
                                        <input type="text"
                                               name="additional_deceased[{{ $adIndex }}][first_name]"
                                               class="form-control"
                                               value="{{ $adDeceased->first_name }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">الاسم الثاني</label>
                                        <input type="text"
                                               name="additional_deceased[{{ $adIndex }}][second_name]"
                                               class="form-control"
                                               value="{{ $adDeceased->second_name }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">الاسم الثالث</label>
                                        <input type="text"
                                               name="additional_deceased[{{ $adIndex }}][third_name]"
                                               class="form-control"
                                               value="{{ $adDeceased->third_name }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">اسم العائلة</label>
                                        <input type="text"
                                               name="additional_deceased[{{ $adIndex }}][last_name]"
                                               class="form-control"
                                               value="{{ $adDeceased->last_name }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">سبب الوفاة</label>
                                        <select name="additional_deceased[{{ $adIndex }}][death_reason]" class="form-select">
                                            <option value="">اختر</option>
                                            @foreach ($death_reasons as $dr)
                                                <option value="{{ $dr->id }}" {{ $adDeceased->death_reason == $dr->id ? 'selected' : '' }}>{{ $dr->description }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <p class="text-muted text-center py-3" id="noAdditionalDeceasedMsg">
                            <i class="fas fa-info-circle me-1"></i>لا يوجد متوفون إضافيون. اضغط "إضافة متوفي" لإضافة واحد.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- JavaScript إدارة المتوفين الإضافيين --}}
<script>
(function () {
    var AdditionalDeceasedCount = {{ isset($additionalDeceased) ? $additionalDeceased->count() : 0 }};

    var deathReasonsData = @json($death_reasons->map(fn($dr) => ['id' => $dr->id, 'desc' => $dr->description]));

    function buildDeathReasonsOptions() {
        var opts = '<option value="">اختر</option>';
        deathReasonsData.forEach(function (dr) {
            opts += '<option value="' + dr.id + '">' + dr.desc + '</option>';
        });
        return opts;
    }

    function buildAdditionalDeceasedForm(index) {
        return `
        <div class="additional-deceased-form border rounded p-3 mb-3 position-relative"
             data-index="${index}"
             style="border: 2px dashed #dc3545 !important; background:#fff8f8;">
            <button type="button"
                class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2 remove-additional-deceased"
                title="حذف">
                <i class="fas fa-times"></i>
            </button>
            <h6 class="text-danger fw-bold mb-3">
                <i class="fas fa-user-times me-2"></i>متوفي رقم ${index + 1}
            </h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">رقم الهوية</label>
                    <div class="input-group">
                        <input type="text"
                               name="additional_deceased[${index}][id_number]"
                               class="form-control additional-deceased-id-input"
                               data-index="${index}"
                               inputmode="numeric"
                               maxlength="9"
                               oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                               placeholder="رقم الهوية">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">صلة القرابة</label>
                    <select name="additional_deceased[${index}][relationship]" class="form-select">
                        <option value="">اختر</option>
                        <option value="father">أب</option>
                        <option value="mother">أم</option>
                        <option value="brother">أخ</option>
                        <option value="sister">أخت</option>
                        <option value="grandfather">جد</option>
                        <option value="grandmother">جدة</option>
                        <option value="uncle">عم</option>
                        <option value="aunt">عمة</option>
                        <option value="other">أخرى</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">تاريخ الوفاة</label>
                    <input type="date" name="additional_deceased[${index}][death_date]" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">الاسم الأول</label>
                    <input type="text" name="additional_deceased[${index}][first_name]" class="form-control" placeholder="الاسم الأول">
                </div>
                <div class="col-md-3">
                    <label class="form-label">الاسم الثاني</label>
                    <input type="text" name="additional_deceased[${index}][second_name]" class="form-control" placeholder="الاسم الثاني">
                </div>
                <div class="col-md-3">
                    <label class="form-label">الاسم الثالث</label>
                    <input type="text" name="additional_deceased[${index}][third_name]" class="form-control" placeholder="الاسم الثالث">
                </div>
                <div class="col-md-3">
                    <label class="form-label">اسم العائلة</label>
                    <input type="text" name="additional_deceased[${index}][last_name]" class="form-control" placeholder="اسم العائلة">
                </div>
                <div class="col-md-6">
                    <label class="form-label">سبب الوفاة</label>
                    <select name="additional_deceased[${index}][death_reason]" class="form-select">
                        ${buildDeathReasonsOptions()}
                    </select>
                </div>
            </div>
        </div>`;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var addBtn = document.getElementById('addAdditionalDeceasedBtn');
        var container = document.getElementById('additionalDeceasedContainer');
        var noMsg = document.getElementById('noAdditionalDeceasedMsg');

        if (addBtn && container) {
            addBtn.addEventListener('click', function () {
                if (noMsg) noMsg.style.display = 'none';
                var html = buildAdditionalDeceasedForm(AdditionalDeceasedCount);
                container.insertAdjacentHTML('beforeend', html);
                var newForm = container.lastElementChild;
                var idInput = newForm.querySelector('.additional-deceased-id-input');
                if (idInput && window.setupAdditionalDeceasedLookup) {
                    window.setupAdditionalDeceasedLookup(idInput, AdditionalDeceasedCount);
                }
                AdditionalDeceasedCount++;
            });
        }

        // حذف متوفي إضافي
        if (container) {
            container.addEventListener('click', function (e) {
                var btn = e.target.closest('.remove-additional-deceased');
                if (btn) {
                    btn.closest('.additional-deceased-form').remove();
                    if (container.querySelectorAll('.additional-deceased-form').length === 0 && noMsg) {
                        noMsg.style.display = 'block';
                    }
                }
            });
        }

        // ربط البحث بالهوية للمتوفين الموجودين مسبقاً
        document.querySelectorAll('.additional-deceased-id-input').forEach(function (inp) {
            var idx = inp.dataset.index;
            if (idx !== undefined && window.setupAdditionalDeceasedLookup) {
                window.setupAdditionalDeceasedLookup(inp, parseInt(idx));
            }
        });
    });
})();
</script>
