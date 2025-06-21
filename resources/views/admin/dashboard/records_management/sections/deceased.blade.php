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
                    <div class="col-md-4">
                        <label class="form-label">رقم الهوية <span class="text-danger">*</span></label>
                        <input type="text" name="father_id" class="form-control" inputmode="numeric" pattern="[0-9]*" maxlength="10"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                               value="{{ old('father_id', $dead ? $dead->father_id : '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">تاريخ الوفاة <span class="text-danger">*</span></label>
                        <input type="date" name="father_death_date" class="form-control"
                               value="{{ old('father_death_date', $dead ? $dead->father_death_date : '') }}">
                    </div>
                    <div class="col-md-4">
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
                        <div class="col-md-4">
                            <label class="form-label">رقم الهوية <span class="text-danger">*</span></label>
                            <input type="text" name="mother_id" class="form-control" inputmode="numeric" pattern="[0-9]*" maxlength="10"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                                   value="{{ old('mother_id', $dead ? $dead->mother_id : '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">تاريخ الوفاة <span class="text-danger">*</span></label>
                            <input type="date" name="mother_death_date" class="form-control"
                                   value="{{ old('mother_death_date', $dead ? $dead->mother_death_date : '') }}">
                        </div>
                        <div class="col-md-4">
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
</div>
