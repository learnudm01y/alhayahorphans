<div class="tab-pane fade" id="deceased" role="tabpanel" aria-labelledby="deceased-tab">
      <div class="row g-3">
          <!-- Father Information -->
          <div class="col-12">
              <div class="card border-0 shadow-sm">
                  <div class="card-header bg-gradient-primary text-dark py-3">
                      <h5 class="mb-0">بيانات الأب المتوفى</h5>
                  </div>
                  <div class="card-body bg-light">
                      <div class="row g-3">
                          <input type="hidden" name="re_file_id" value="{{ $file_id_number ?? '' }}">
                          <div class="col-md-3">
                              <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                              <input type="text" name="father_first_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-3">
                              <label class="form-label">الاسم الثاني <span class="text-primary"
                                      style="color:#6c757d !important;">(اختياري)</span></label>
                              <input type="text" name="father_second_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-3">
                              <label class="form-label">الاسم الثالث <span class="text-primary"
                                      style="color:#6c757d !important;">(اختياري)</span></label>
                              <input type="text" name="father_third_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-3">
                              <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
                              <input type="text" name="father_last_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-4">
                              <label class="form-label">رقم الهوية <span class="text-danger">*</span></label>
                              {{-- 4000000// مليون // 999999--}}
                              <input type="text" name="father_id" class="form-control" inputmode="numeric" minlength="9" maxlength="10" pattern="[0-9]{9,10}" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                          </div>
                          <div class="col-md-4">
                              <label class="form-label">تاريخ الوفاة <span class="text-danger">*</span></label>
                              <input type="date" name="father_death_date" class="form-control">
                          </div>
                          <div class="col-md-4">
                              <label class="form-label">سبب الوفاة <span class="text-danger">*</span></label>
                              <select name="father_death_reason" class="form-select">
                                  <option value="">اختر سبب الوفاة</option>
                                  @foreach ($death_reasons as $deathReason)
                                      <option value="{{ $deathReason->id }}">
                                          {{ $deathReason->description }}</option>
                                  @endforeach
                              </select>
                          </div>
                          <div class="col-md-12 mt-3">
                              <!-- منطقة رفع الملفات للأب المتوفى -->
                              <div data-upload-zone="deceased_father" class="upload-zone">
                                  <label class="form-label fw-bold">رفع وثائق الأب المتوفى</label>
                                  <select class="form-select mainDocumentTypeSelect" id="mainDocumentTypeSelect_father">
                                      <option value="">اختر نوع الوثيقة</option>
                                      @foreach ($documentTypes->where('deceased_enabled', 1) as $documentType)
                                          <option value="{{ $documentType->pref }}">
                                              {{ $documentType->description }}</option>
                                      @endforeach
                                  </select>
                                  <input type="file" class="mainDocumentFileInput" id="mainDocumentFileInput_father" style="display:none;">
                                  <div class="mainDocumentPreview" id="mainDocumentPreview_father"></div>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>

          <!-- Add Toggle Button -->
          <div class="col-12 mb-3">
              <button type="button" class="btn btn-primary w-100" id="toggleMotherInfo">
                  <i class="fas fa-plus me-2"></i>إضافة بيانات الأم المتوفية
              </button>
          </div>

          <!-- Mother Information (Hidden by default) -->
          <div class="col-12" id="motherInfoSection" style="display: none;">
              <div class="card border-0 shadow-sm">
                  <div class="card-header bg-gradient-primary text-dark py-3">
                      <h5 class="mb-0">بيانات الأم المتوفية</h5>
                  </div>
                  <div class="card-body bg-light">
                      <div class="row g-3">
                          <input type="hidden" name="re_file_id" value="{{ $file_id_number ?? '' }}">
                          <div class="col-md-3">
                              <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                              <input type="text" name="mother_first_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-3">
                              <label class="form-label">الاسم الثاني <span class="text-primary"
                                      style="color:#6c757d !important;">(اختياري)</span></label>
                              <input type="text" name="mother_second_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-3">
                              <label class="form-label">الاسم الثالث <span class="text-primary"
                                      style="color:#6c757d !important;">(اختياري)</span></label>
                              <input type="text" name="mother_third_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-3">
                              <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
                              <input type="text" name="mother_last_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-4">
                              <label class="form-label">رقم الهوية <span class="text-danger">*</span></label>
                              <input type="text" name="mother_id" class="form-control" inputmode="numeric" minlength="9" maxlength="10" pattern="[0-9]{9,10}" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                          </div>
                          <div class="col-md-4">
                              <label class="form-label">تاريخ الوفاة <span class="text-danger">*</span></label>
                              <input type="date" name="mother_death_date" class="form-control">
                          </div>
                          <div class="col-md-4">
                              <label class="form-label">سبب الوفاة <span class="text-danger">*</span></label>
                              <select name="mother_death_reason" class="form-select">
                                  <option value="">اختر سبب الوفاة</option>
                                  @foreach ($death_reasons as $deathReason)
                                      <option value="{{ $deathReason->id }}">
                                          {{ $deathReason->description }}</option>
                                  @endforeach
                              </select>
                          </div>
                          <div class="col-md-12 mt-3">
                              <!-- منطقة رفع الملفات للأم المتوفية -->
                              <div data-upload-zone="deceased_mother" class="upload-zone">
                                  <label class="form-label fw-bold">رفع وثائق الأم المتوفية</label>
                                  <select class="form-select mainDocumentTypeSelect" id="mainDocumentTypeSelect_mother">
                                      <option value="">اختر نوع الوثيقة</option>
                                      @foreach ($documentTypes->where('deceased_enabled', 1) as $documentType)
                                          <option value="{{ $documentType->pref }}">
                                              {{ $documentType->description }}</option>
                                      @endforeach
                                  </select>
                                  <input type="file" class="mainDocumentFileInput" id="mainDocumentFileInput_mother" style="display:none;">
                                  <div class="mainDocumentPreview" id="mainDocumentPreview_mother"></div>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </div>

      <!-- Next Button -->
      <div class="mt-4 text-end">
          <button type="button" class="btn btn-success px-5 py-2 fs-5" id="goToFamilyTabBtn">
              التالي <i class="fas fa-arrow-left ms-2"></i>
          </button>
      </div>
      <div id="didding" style="padding-bottom: 80px;"></div>
      <!-- SweetAlert2 CDN -->
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script>
          document.addEventListener('DOMContentLoaded', async function(e) {
             e.preventDefault();
              const nextBtn = document.getElementById('goToFamilyTabBtn');
              if (nextBtn) {
                  nextBtn.addEventListener('click', async function(e) {
                      // حقول الأب المطلوبة
                      const fatherRequired = [
                          { name: 'father_first_name', label: 'الاسم الأول للأب' },
                          { name: 'father_last_name', label: 'اسم العائلة للأب' },
                          { name: 'father_id', label: 'رقم هوية الأب' },
                          { name: 'father_death_date', label: 'تاريخ وفاة الأب' },
                          { name: 'father_death_reason', label: 'سبب وفاة الأب' },
                      ];
                      // حقل رفع الملفات للأب
                      const fatherDocType = document.querySelector('#deceased .card-body select#mainDocumentTypeSelect');
                      const fatherFileInput = document.querySelector('#deceased .card-body input[type="file"]');
                      let firstInvalid = null;
                      for (const field of fatherRequired) {
                          const el = document.querySelector(`[name="${field.name}"]`);
                          if (el && !el.value) {
                              firstInvalid = field.label;
                              break;
                          }
                      }
                      // تحقق من رفع الملف أو اختيار نوع الوثيقة
                      if (!firstInvalid && fatherDocType && fatherFileInput) {
                          const hasFile = fatherFileInput.files && fatherFileInput.files.length > 0;
                          if (!fatherDocType.value && !hasFile) {
                              firstInvalid = 'نوع الوثيقة أو رفع الملف للأب';
                          }
                      }
                      // إذا ظهرت بيانات الأم، تحقق من حقولها أيضًا
                      const motherSection = document.getElementById('motherInfoSection');
                      if (!firstInvalid && motherSection && motherSection.style.display !== 'none') {
                          const motherRequired = [
                              { name: 'mother_first_name', label: 'الاسم الأول للأم' },
                              { name: 'mother_last_name', label: 'اسم العائلة للأم' },
                              { name: 'mother_id', label: 'رقم هوية الأم' },
                              { name: 'mother_death_date', label: 'تاريخ وفاة الأم' },
                              { name: 'mother_death_reason', label: 'سبب وفاة الأم' },
                          ];
                          const motherDocType = motherSection.querySelector('select#mainDocumentTypeSelect');
                          const motherFileInput = motherSection.querySelector('input[type="file"]');
                          for (const field of motherRequired) {
                              const el = motherSection.querySelector(`[name="${field.name}"]`);
                              if (el && !el.value) {
                                  firstInvalid = field.label;
                                  break;
                              }
                          }
                          if (!firstInvalid && motherDocType && motherFileInput) {
                              const hasFile = motherFileInput.files && motherFileInput.files.length > 0;
                              if (!motherDocType.value && !hasFile) {
                                  firstInvalid = 'نوع الوثيقة أو رفع الملف للأم';
                              }
                          }
                      }
                      if (firstInvalid) {
                          e.preventDefault();
                          Swal.fire({
                              icon: 'warning',
                              title: 'تنبيه',
                              text: `يرجى إدخال ${firstInvalid} قبل المتابعة!`,
                              confirmButtonText: 'حسنًا'
                          });
                          return;
                      }
                      const valid = await validateAllIds(e);
                      if (!valid) return; // إذا كان هناك خطأ لا تنتقل
                      // ...existing code for tab navigation...
                      const familyTab = document.getElementById('family-members-tab');
                      if (familyTab) familyTab.click();
                  });
              }
          });
      </script>
  </div>
      </script>
  </div>
