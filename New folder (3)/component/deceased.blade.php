<div class="tab-pane fade" id="deceased" role="tabpanel" aria-labelledby="deceased-tab">
      <div class="row g-3">
          <!-- Father Information -->
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
                          <div class="col-md-4 mt-3">
                              <!-- ملاحظة توضيحية لرفع الملفات -->
                              <div class="alert alert-primary py-2 mb-2" style="font-size: 0.97rem;">
                                  يرجى اختيار نوع الوثيقة أولاً، وسوف يتم تحويلك لرفع الصورة
                                  المطلوبة.
                              </div>
                              <!-- منطقة رفع الملفات للأب المتوفى (معرفات فريدة) -->
                              <div data-upload-zone="deceased_father" data-person-id="{{ $father_id ?? '' }}">
                                  <label class="form-label fw-bold"> رفع الملفات <span class="text-danger">*</span></label>
                                  <select class="form-select mainDocumentTypeSelect" id="mainDocumentTypeSelect_father">
                                      <option value="">اختر نوع الوثيقة</option>
                                      @foreach ($documentTypes->where('deceased_enabled', 1) as $documentType)
                                          <option value="{{ $documentType->pref }}">
                                              {{ $documentType->description }}</option>
                                      @endforeach
                                  </select>
                                  <input type="file" class="mainDocumentFileInput" id="mainDocumentFileInput_father" accept="image/*,.pdf"
                                      style="display:none;">
                                  <div class="mainDocumentPreview" id="mainDocumentPreview_father"></div>
                                  <div class="mainDocumentNames" id="mainDocumentNames_father"></div>
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
                      <h5 class="card-title mb-0 d-flex align-items-center">
                          <i class="fas fa-female fs-4 me-2"></i>
                          بيانات الأم المتوفية
                      </h5>
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
                          <div class="col-md-4 mt-3">
                              <!-- ملاحظة توضيحية لرفع الملفات -->
                              <div class="alert alert-primary py-2 mb-2" style="font-size: 0.97rem;">
                                  يرجى اختيار نوع الوثيقة أولاً، وسوف يتم تحويلك لرفع الصورة
                                  المطلوبة.
                              </div>
                              <!-- منطقة رفع الملفات للأم المتوفية (معرفات فريدة) -->
                              <div data-upload-zone="deceased_mother" data-person-id="{{ $mother_id ?? '' }}">
                                  <label class="form-label fw-bold"> رفع الملفات <span class="text-danger">*</span></label>
                                  <select class="form-select mainDocumentTypeSelect" id="mainDocumentTypeSelect_mother">
                                      <option value="">اختر نوع الوثيقة</option>
                                      @foreach ($documentTypes->where('deceased_enabled', 1) as $documentType)
                                          <option value="{{ $documentType->pref }}">
                                              {{ $documentType->description }}</option>
                                      @endforeach
                                  </select>
                                  <input type="file" class="mainDocumentFileInput" id="mainDocumentFileInput_mother" accept="image/*,.pdf"
                                      style="display:none;">
                                  <div class="mainDocumentPreview" id="mainDocumentPreview_mother"></div>
                                  <div class="mainDocumentNames" id="mainDocumentNames_mother"></div>
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
          document.addEventListener('DOMContentLoaded', function() {
              const nextBtn = document.getElementById('goToFamilyTabBtn');
              if (nextBtn) {
                  nextBtn.addEventListener('click', function(e) {
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
                      // ...existing code for tab navigation...
                      const familyTab = document.getElementById('family-members-tab');
                      if (familyTab) familyTab.click();
                  });
              }
          });
      </script>
  </div>
