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
                                  <div class="alert alert-info py-2 mb-2" style="font-size: 0.95rem;">
                                      <i class="fas fa-info-circle me-1"></i>
                                      يرجى إدخال رقم هوية الأب بشكل صحيح (9 أرقام) أولاً قبل اختيار نوع الوثيقة.
                                  </div>
                                  <label class="form-label fw-bold">رفع وثائق الأب المتوفى</label>
                                  <select class="form-select mainDocumentTypeSelect" id="mainDocumentTypeSelect_father" disabled style="opacity: 0.5; cursor: not-allowed;">
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
                                  <div class="alert alert-info py-2 mb-2" style="font-size: 0.95rem;">
                                      <i class="fas fa-info-circle me-1"></i>
                                      يرجى إدخال رقم هوية الأم بشكل صحيح (9 أرقام) أولاً قبل اختيار نوع الوثيقة.
                                  </div>
                                  <label class="form-label fw-bold">رفع وثائق الأم المتوفية</label>
                                  <select class="form-select mainDocumentTypeSelect" id="mainDocumentTypeSelect_mother" disabled style="opacity: 0.5; cursor: not-allowed;">
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

              // 🆕 تعريف المتغيرات في البداية لاستخدامها في كل الدوال
              const fatherIdInput = document.querySelector('[name="father_id"]');
              const motherIdInput = document.querySelector('[name="mother_id"]');
              const motherSection = document.getElementById('motherInfoSection');

              const nextBtn = document.getElementById('goToFamilyTabBtn');
              if (nextBtn) {
                  nextBtn.addEventListener('click', async function(e) {
                      // 🆕 التحقق من صحة أرقام الهوية أولاً قبل التحقق من الحقول الأخرى

                      // التحقق من رقم هوية الأب (إذا كان مدخلاً)
                      if (fatherIdInput && fatherIdInput.value.trim()) {
                          const fatherIdValue = fatherIdInput.value.trim();
                          if (!/^\d{9}$/.test(fatherIdValue)) {
                              e.preventDefault();
                              let message = 'رقم هوية الأب يجب أن يكون 9 أرقام بالضبط';

                              if (fatherIdValue.length < 9) {
                                  message = `رقم هوية الأب يجب أن يكون 9 أرقام (تم إدخال ${fatherIdValue.length} فقط)`;
                              } else if (fatherIdValue.length > 9) {
                                  message = `رقم هوية الأب يجب أن يكون 9 أرقام (تم إدخال ${fatherIdValue.length})`;
                              }

                              // تمييز الحقل بالأحمر
                              fatherIdInput.style.border = '2px solid red';
                              fatherIdInput.focus();

                              Swal.fire({
                                  icon: 'error',
                                  title: 'خطأ في رقم هوية الأب',
                                  text: message,
                                  confirmButtonText: 'حسناً'
                              });
                              return;
                          } else {
                              // إزالة التمييز الأحمر إذا كان صحيحاً
                              fatherIdInput.style.border = '';
                          }
                      }

                      // التحقق من رقم هوية الأم (إذا كان مدخلاً)
                      if (motherIdInput && motherIdInput.value.trim() && motherSection && motherSection.style.display !== 'none') {
                          const motherIdValue = motherIdInput.value.trim();
                          if (!/^\d{9}$/.test(motherIdValue)) {
                              e.preventDefault();
                              let message = 'رقم هوية الأم يجب أن يكون 9 أرقام بالضبط';

                              if (motherIdValue.length < 9) {
                                  message = `رقم هوية الأم يجب أن يكون 9 أرقام (تم إدخال ${motherIdValue.length} فقط)`;
                              } else if (motherIdValue.length > 9) {
                                  message = `رقم هوية الأم يجب أن يكون 9 أرقام (تم إدخال ${motherIdValue.length})`;
                              }

                              // تمييز الحقل بالأحمر
                              motherIdInput.style.border = '2px solid red';
                              motherIdInput.focus();

                              Swal.fire({
                                  icon: 'error',
                                  title: 'خطأ في رقم هوية الأم',
                                  text: message,
                                  confirmButtonText: 'حسناً'
                              });
                              return;
                          } else {
                              // إزالة التمييز الأحمر إذا كان صحيحاً
                              motherIdInput.style.border = '';
                          }
                      }

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

              // 🆕 إضافة مراقبة مباشرة لحقول أرقام الهوية
              // دالة التحقق من رقم الهوية
              function validateIdField(input, label) {
                  if (!input) return;

                  input.addEventListener('input', function() {
                      const value = this.value.trim();

                      // إذا كان الحقل فارغاً، لا تعرض خطأ
                      if (!value) {
                          this.style.border = '';
                          return;
                      }

                      // التحقق من الطول
                      if (value.length !== 9) {
                          this.style.border = '2px solid red';
                      } else if (/^\d{9}$/.test(value)) {
                          this.style.border = '2px solid green';
                      } else {
                          this.style.border = '2px solid red';
                      }
                  });

                  // التحقق عند فقدان التركيز
                  input.addEventListener('blur', function() {
                      const value = this.value.trim();

                      if (value && !/^\d{9}$/.test(value)) {
                          let message = `رقم ${label} يجب أن يكون 9 أرقام بالضبط`;

                          if (value.length < 9) {
                              message = `رقم ${label} يجب أن يكون 9 أرقام (تم إدخال ${value.length} فقط)`;
                          } else if (value.length > 9) {
                              message = `رقم ${label} يجب أن يكون 9 أرقام (تم إدخال ${value.length})`;
                          }

                          this.style.border = '2px solid red';

                          Swal.fire({
                              icon: 'warning',
                              title: 'تنبيه',
                              text: message,
                              confirmButtonText: 'حسناً',
                              timer: 3000
                          });
                      }
                  });
              }

              // تطبيق المراقبة على حقول أرقام الهوية
              validateIdField(fatherIdInput, 'هوية الأب');
              validateIdField(motherIdInput, 'هوية الأم');

              // 🆕 دالة لتعطيل/تفعيل قائمة نوع الوثيقة بناءً على رقم الهوية (للأب والأم)
              function setupDocumentSelectValidation(idInput, docTypeSelect, label) {
                  if (!idInput || !docTypeSelect) return;

                  // دالة التحقق
                  function checkAndToggleDocSelect() {
                      const idValue = idInput.value.trim();
                      const isValid = /^\d{9}$/.test(idValue); // بالضبط 9 أرقام

                      if (isValid) {
                          // تفعيل القائمة
                          docTypeSelect.disabled = false;
                          docTypeSelect.classList.remove('disabled');
                          docTypeSelect.style.opacity = '1';
                          docTypeSelect.style.cursor = 'pointer';
                      } else {
                          // تعطيل القائمة
                          docTypeSelect.disabled = true;
                          docTypeSelect.classList.add('disabled');
                          docTypeSelect.style.opacity = '0.5';
                          docTypeSelect.style.cursor = 'not-allowed';
                          docTypeSelect.selectedIndex = 0; // إعادة تعيين الاختيار
                      }
                  }

                  // التحقق الأولي
                  checkAndToggleDocSelect();

                  // مراقبة التغييرات في حقل رقم الهوية
                  idInput.addEventListener('input', checkAndToggleDocSelect);
                  idInput.addEventListener('change', checkAndToggleDocSelect);

                  // منع فتح القائمة إذا كانت معطلة
                  docTypeSelect.addEventListener('mousedown', function(e) {
                      if (this.disabled) {
                          e.preventDefault();
                          const idValue = idInput.value.trim();
                          let message = `يجب إدخال رقم ${label} صحيح (9 أرقام) قبل اختيار نوع الوثيقة`;

                          if (!idValue) {
                              message = `يجب إدخال رقم ${label} أولاً`;
                          } else if (idValue.length < 9) {
                              message = `رقم ${label} يجب أن يكون 9 أرقام (تم إدخال ${idValue.length} فقط)`;
                          } else if (idValue.length > 9) {
                              message = `رقم ${label} يجب أن يكون 9 أرقام (تم إدخال ${idValue.length})`;
                          } else if (!/^\d+$/.test(idValue)) {
                              message = `رقم ${label} يجب أن يحتوي على أرقام فقط`;
                          }

                          Swal.fire({
                              icon: 'warning',
                              title: 'تنبيه',
                              text: message,
                              confirmButtonText: 'حسناً'
                          });
                      }
                  });
              }

              // تطبيق المراقبة على قوائم الوثائق
              const fatherDocSelect = document.querySelector('#mainDocumentTypeSelect_father');
              const motherDocSelect = document.querySelector('#mainDocumentTypeSelect_mother');

              setupDocumentSelectValidation(fatherIdInput, fatherDocSelect, 'هوية الأب');
              setupDocumentSelectValidation(motherIdInput, motherDocSelect, 'هوية الأم');
          });
      </script>
  </div>
      </script>
  </div>
