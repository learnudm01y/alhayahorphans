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
                          <div class="col-md-4">
                              <label class="form-label">رقم الهوية <span class="text-danger">*</span></label>
                              <div class="input-group">
                                  <input type="text" name="father_id" class="form-control deceased-id-input" data-target="father" inputmode="numeric" minlength="9" maxlength="10" pattern="[0-9]{9,10}" oninput="this.value = this.value.replace(/[^0-9]/g, '');" placeholder="أدخل رقم الهوية لجلب البيانات">
                                  <span class="input-group-text deceased-search-status" data-target="father" style="display:none;">
                                      <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                                  </span>
                              </div>
                              <small class="text-muted">سيتم جلب الاسم تلقائياً من قاعدة البيانات المركزية</small>
                          </div>
                          <div class="col-md-2">
                              <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                              <input type="text" name="father_first_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-2">
                              <label class="form-label">الاسم الثاني <span class="text-primary"
                                      style="color:#6c757d !important;">(اختياري)</span></label>
                              <input type="text" name="father_second_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-2">
                              <label class="form-label">الاسم الثالث <span class="text-primary"
                                      style="color:#6c757d !important;">(اختياري)</span></label>
                              <input type="text" name="father_third_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-2">
                              <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
                              <input type="text" name="father_last_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-4">
                              <label class="form-label">تاريخ الوفاة <span class="text-danger">*</span></label>
                              <input type="date" name="father_death_date" class="form-control">
                          </div>
                          <div class="col-md-4">
                              <label class="form-label">سبب الوفاة <span class="text-danger">*</span></label>
                              <select name="father_death_reason" class="form-select">
                                  <option value="">اختر سبب الوفاة</option>
                                  @foreach ($death_reasons->where('description', '!=', 'Unknown') as $deathReason)
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
                                  <div class="alert alert-warning py-2 mb-2" style="font-size: 0.9rem;">
                                      <i class="fas fa-star text-danger me-1"></i>
                                      <strong>الوثائق المميزة بعلامة <span class="text-danger">★</span> إجبارية ويجب إدخالها</strong>
                                  </div>
                                  <label class="form-label fw-bold">رفع وثائق الأب المتوفى</label>
                                  <select class="form-select mainDocumentTypeSelect" id="mainDocumentTypeSelect_father" disabled style="opacity: 0.5; cursor: not-allowed;">
                                      <option value="">اختر نوع الوثيقة</option>
                                      @foreach ($documentTypes->where('deceased_enabled', 1)->where('description', '!=', 'Unknown') as $documentType)
                                          <option value="{{ $documentType->pref }}" {{ $documentType->deceased_required ? 'data-required=true' : '' }}>
                                              {{ $documentType->deceased_required ? '★ ' : '' }}{{ $documentType->description }}{{ $documentType->deceased_required ? ' (إجباري)' : '' }}</option>
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
                          <div class="col-md-4">
                              <label class="form-label">رقم الهوية <span class="text-danger">*</span></label>
                              <div class="input-group">
                                  <input type="text" name="mother_id" class="form-control deceased-id-input" data-target="mother" inputmode="numeric" minlength="9" maxlength="10" pattern="[0-9]{9,10}" oninput="this.value = this.value.replace(/[^0-9]/g, '');" placeholder="أدخل رقم الهوية لجلب البيانات">
                                  <span class="input-group-text deceased-search-status" data-target="mother" style="display:none;">
                                      <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                                  </span>
                              </div>
                              <small class="text-muted">سيتم جلب الاسم تلقائياً من قاعدة البيانات المركزية</small>
                          </div>
                          <div class="col-md-2">
                              <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                              <input type="text" name="mother_first_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-2">
                              <label class="form-label">الاسم الثاني <span class="text-primary"
                                      style="color:#6c757d !important;">(اختياري)</span></label>
                              <input type="text" name="mother_second_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-2">
                              <label class="form-label">الاسم الثالث <span class="text-primary"
                                      style="color:#6c757d !important;">(اختياري)</span></label>
                              <input type="text" name="mother_third_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-2">
                              <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
                              <input type="text" name="mother_last_name" class="form-control" maxlength="30">
                          </div>
                          <div class="col-md-4">
                              <label class="form-label">تاريخ الوفاة <span class="text-danger">*</span></label>
                              <input type="date" name="mother_death_date" class="form-control">
                          </div>
                          <div class="col-md-4">
                              <label class="form-label">سبب الوفاة <span class="text-danger">*</span></label>
                              <select name="mother_death_reason" class="form-select">
                                  <option value="">اختر سبب الوفاة</option>
                                  @foreach ($death_reasons->where('description', '!=', 'Unknown') as $deathReason)
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
                                  <div class="alert alert-warning py-2 mb-2" style="font-size: 0.9rem;">
                                      <i class="fas fa-star text-danger me-1"></i>
                                      <strong>الوثائق المميزة بعلامة <span class="text-danger">★</span> إجبارية ويجب إدخالها</strong>
                                  </div>
                                  <label class="form-label fw-bold">رفع وثائق الأم المتوفية</label>
                                  <select class="form-select mainDocumentTypeSelect" id="mainDocumentTypeSelect_mother" disabled style="opacity: 0.5; cursor: not-allowed;">
                                      <option value="">اختر نوع الوثيقة</option>
                                      @foreach ($documentTypes->where('deceased_enabled', 1)->where('description', '!=', 'Unknown') as $documentType)
                                          <option value="{{ $documentType->pref }}" {{ $documentType->deceased_required ? 'data-required=true' : '' }}>
                                              {{ $documentType->deceased_required ? '★ ' : '' }}{{ $documentType->description }}{{ $documentType->deceased_required ? ' (إجباري)' : '' }}</option>
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

          <!-- Container for additional deceased persons -->
          <div id="additionalDeceasedContainer" class="col-12">
              <!-- سيتم إضافة المتوفين الإضافيين هنا -->
          </div>

          <!-- Add Another Deceased Button -->
          <div class="col-12 mb-3">
              <button type="button" class="btn btn-secondary w-100" id="addAnotherDeceasedBtn">
                  <i class="fas fa-plus me-2"></i>إضافة متوفي آخر مرتبط بنفس الملف
              </button>
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

              // 🆕 متغيرات المتوفين الإضافيين
              let additionalDeceasedCount = 0;
              const maxAdditionalDeceased = 10;
              const additionalDeceasedContainer = document.getElementById('additionalDeceasedContainer');
              const addAnotherDeceasedBtn = document.getElementById('addAnotherDeceasedBtn');
              const deathReasonsData = @json($death_reasons->where('description', '!=', 'Unknown')->values());
              const documentTypesData = @json($documentTypes->where('deceased_enabled', 1)->values());
              const fileIdNumber = '{{ $file_id_number ?? '' }}';

              // 🆕 دالة جلب بيانات الشخص من قاعدة البيانات المركزية
              function fetchDeceasedPersonData(idNumber, targetPrefix, formContainer = null) {
                  let firstNameInput, secondNameInput, thirdNameInput, lastNameInput, statusSpan;

                  // التحقق مما إذا كان نموذج إضافي أم نموذج رئيسي (أب/أم)
                  if (targetPrefix.startsWith('additional_deceased_')) {
                      // نموذج متوفي إضافي
                      const index = targetPrefix.replace('additional_deceased_', '');
                      firstNameInput = formContainer ? formContainer.querySelector(`[name="additional_deceased[${index}][first_name]"]`) : document.querySelector(`[name="additional_deceased[${index}][first_name]"]`);
                      secondNameInput = formContainer ? formContainer.querySelector(`[name="additional_deceased[${index}][second_name]"]`) : document.querySelector(`[name="additional_deceased[${index}][second_name]"]`);
                      thirdNameInput = formContainer ? formContainer.querySelector(`[name="additional_deceased[${index}][third_name]"]`) : document.querySelector(`[name="additional_deceased[${index}][third_name]"]`);
                      lastNameInput = formContainer ? formContainer.querySelector(`[name="additional_deceased[${index}][last_name]"]`) : document.querySelector(`[name="additional_deceased[${index}][last_name]"]`);
                  } else {
                      // نموذج الأب أو الأم
                      firstNameInput = formContainer ? formContainer.querySelector(`[name="${targetPrefix}_first_name"]`) : document.querySelector(`[name="${targetPrefix}_first_name"]`);
                      secondNameInput = formContainer ? formContainer.querySelector(`[name="${targetPrefix}_second_name"]`) : document.querySelector(`[name="${targetPrefix}_second_name"]`);
                      thirdNameInput = formContainer ? formContainer.querySelector(`[name="${targetPrefix}_third_name"]`) : document.querySelector(`[name="${targetPrefix}_third_name"]`);
                      lastNameInput = formContainer ? formContainer.querySelector(`[name="${targetPrefix}_last_name"]`) : document.querySelector(`[name="${targetPrefix}_last_name"]`);
                  }

                  statusSpan = formContainer ? formContainer.querySelector(`.deceased-search-status[data-target="${targetPrefix}"]`) : document.querySelector(`.deceased-search-status[data-target="${targetPrefix}"]`);

                  if (!idNumber || idNumber.length < 9) {
                      return;
                  }

                  // إظهار مؤشر التحميل
                  if (statusSpan) statusSpan.style.display = 'flex';

                  fetch(`/api/civil-registry/search-by-id?search_text=${encodeURIComponent(idNumber)}`)
                      .then(response => response.json())
                      .then(data => {
                          if (statusSpan) statusSpan.style.display = 'none';

                          if (data.success && data.data && data.data.length > 0) {
                              const person = data.data[0];

                              // توزيع الاسم على الحقول الأربعة
                              if (firstNameInput && person.CI_FIRST_ARB) {
                                  firstNameInput.value = person.CI_FIRST_ARB;
                                  firstNameInput.classList.add('is-valid');
                              }
                              if (secondNameInput && person.CI_FATHER_ARB) {
                                  secondNameInput.value = person.CI_FATHER_ARB;
                                  secondNameInput.classList.add('is-valid');
                              }
                              if (thirdNameInput && person.CI_GRAND_FATHER_ARB) {
                                  thirdNameInput.value = person.CI_GRAND_FATHER_ARB;
                                  thirdNameInput.classList.add('is-valid');
                              }
                              if (lastNameInput && person.CI_FAMILY_ARB) {
                                  lastNameInput.value = person.CI_FAMILY_ARB;
                                  lastNameInput.classList.add('is-valid');
                              }
                          } else {
                              // لم يتم العثور على الشخص
                              if (firstNameInput) firstNameInput.placeholder = 'لم يتم العثور - أدخل يدوياً';
                              if (lastNameInput) lastNameInput.placeholder = 'لم يتم العثور - أدخل يدوياً';
                          }
                      })
                      .catch(error => {
                          if (statusSpan) statusSpan.style.display = 'none';
                          console.error('خطأ في جلب بيانات المتوفي:', error);
                      });
              }

              // 🆕 ربط أحداث الجلب التلقائي بحقول رقم الهوية الموجودة
              function attachDeceasedIdListeners() {
                  document.querySelectorAll('.deceased-id-input').forEach(input => {
                      if (!input.dataset.listenerAttached) {
                          input.dataset.listenerAttached = 'true';
                          let debounceTimer;

                          input.addEventListener('input', function() {
                              clearTimeout(debounceTimer);
                              const idNumber = this.value.trim();
                              const targetPrefix = this.dataset.target;
                              const formContainer = this.closest('.card') || this.closest('.additional-deceased-form');

                              debounceTimer = setTimeout(() => {
                                  if (idNumber.length >= 9) {
                                      fetchDeceasedPersonData(idNumber, targetPrefix, formContainer);
                                  }
                              }, 500);
                          });

                          input.addEventListener('blur', function() {
                              const idNumber = this.value.trim();
                              const targetPrefix = this.dataset.target;
                              const formContainer = this.closest('.card') || this.closest('.additional-deceased-form');
                              if (idNumber.length >= 9) {
                                  fetchDeceasedPersonData(idNumber, targetPrefix, formContainer);
                              }
                          });
                      }
                  });
              }

              // 🆕 إنشاء نموذج متوفي إضافي
              function createAdditionalDeceasedForm(index) {
                  const deathReasonsOptions = deathReasonsData.map(reason =>
                      `<option value="${reason.id}">${reason.description}</option>`
                  ).join('');

                  const documentTypesOptions = documentTypesData.filter(docType => docType.description !== 'Unknown').map(docType =>
                      `<option value="${docType.pref}" ${docType.deceased_required ? 'data-required="true"' : ''}>${docType.deceased_required ? '★ ' : ''}${docType.description}${docType.deceased_required ? ' (إجباري)' : ''}</option>`
                  ).join('');

                  return `
                  <div class="additional-deceased-form card border-0 shadow-sm mb-3" data-index="${index}">
                      <div class="card-header bg-gradient-warning text-dark py-3 d-flex justify-content-between align-items-center">
                          <h5 class="mb-0">بيانات متوفي إضافي #${index + 1}</h5>
                          <button type="button" class="btn btn-danger btn-sm remove-additional-deceased-btn" title="حذف">
                              <i class="fas fa-times"></i> حذف
                          </button>
                      </div>
                      <div class="card-body bg-light">
                          <div class="row g-3">
                              <input type="hidden" name="additional_deceased[${index}][re_file_id]" value="${fileIdNumber}">
                              <div class="col-md-4">
                                  <label class="form-label">رقم الهوية <span class="text-danger">*</span></label>
                                  <div class="input-group">
                                      <input type="text" name="additional_deceased[${index}][id_number]" class="form-control deceased-id-input" data-target="additional_deceased_${index}" inputmode="numeric" minlength="9" maxlength="10" pattern="[0-9]{9,10}" oninput="this.value = this.value.replace(/[^0-9]/g, '');" placeholder="أدخل رقم الهوية لجلب البيانات">
                                      <span class="input-group-text deceased-search-status" data-target="additional_deceased_${index}" style="display:none;">
                                          <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                                      </span>
                                  </div>
                                  <small class="text-muted">سيتم جلب الاسم تلقائياً من قاعدة البيانات المركزية</small>
                              </div>
                              <div class="col-md-2">
                                  <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                                  <input type="text" name="additional_deceased[${index}][first_name]" class="form-control" maxlength="30">
                              </div>
                              <div class="col-md-2">
                                  <label class="form-label">الاسم الثاني <span class="text-primary" style="color:#6c757d !important;">(اختياري)</span></label>
                                  <input type="text" name="additional_deceased[${index}][second_name]" class="form-control" maxlength="30">
                              </div>
                              <div class="col-md-2">
                                  <label class="form-label">الاسم الثالث <span class="text-primary" style="color:#6c757d !important;">(اختياري)</span></label>
                                  <input type="text" name="additional_deceased[${index}][third_name]" class="form-control" maxlength="30">
                              </div>
                              <div class="col-md-2">
                                  <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
                                  <input type="text" name="additional_deceased[${index}][last_name]" class="form-control" maxlength="30">
                              </div>
                              <div class="col-md-4">
                                  <label class="form-label">صلة القرابة <span class="text-danger">*</span></label>
                                  <select name="additional_deceased[${index}][relationship]" class="form-select">
                                      <option value="">اختر صلة القرابة</option>
                                      <option value="father">أب</option>
                                      <option value="mother">أم</option>
                                      <option value="brother">أخ</option>
                                      <option value="sister">أخت</option>
                                      <option value="grandfather">جد</option>
                                      <option value="grandmother">جدة</option>
                                      <option value="uncle">عم/خال</option>
                                      <option value="aunt">عمة/خالة</option>
                                      <option value="other">أخرى</option>
                                  </select>
                              </div>
                              <div class="col-md-4">
                                  <label class="form-label">تاريخ الوفاة <span class="text-danger">*</span></label>
                                  <input type="date" name="additional_deceased[${index}][death_date]" class="form-control">
                              </div>
                              <div class="col-md-4">
                                  <label class="form-label">سبب الوفاة <span class="text-danger">*</span></label>
                                  <select name="additional_deceased[${index}][death_reason]" class="form-select">
                                      <option value="">اختر سبب الوفاة</option>
                                      ${deathReasonsOptions}
                                  </select>
                              </div>
                              <div class="col-md-12 mt-3">
                                  <div data-upload-zone="additional_deceased_${index}" class="upload-zone">
                                      <div class="alert alert-info py-2 mb-2" style="font-size: 0.95rem;">
                                          <i class="fas fa-info-circle me-1"></i>
                                          يرجى إدخال رقم هوية المتوفي بشكل صحيح (9 أرقام) أولاً قبل اختيار نوع الوثيقة.
                                      </div>
                                      <div class="alert alert-warning py-2 mb-2" style="font-size: 0.9rem;">
                                          <i class="fas fa-star text-danger me-1"></i>
                                          <strong>الوثائق المميزة بعلامة <span class="text-danger">★</span> إجبارية ويجب إدخالها</strong>
                                      </div>
                                      <label class="form-label fw-bold">رفع وثائق المتوفي</label>
                                      <select class="form-select mainDocumentTypeSelect additional-deceased-doc-select" id="mainDocumentTypeSelect_additional_${index}" data-deceased-index="${index}" disabled style="opacity: 0.5; cursor: not-allowed;">
                                          <option value="">اختر نوع الوثيقة</option>
                                          ${documentTypesOptions}
                                      </select>
                                      <input type="file" class="mainDocumentFileInput" id="mainDocumentFileInput_additional_${index}" style="display:none;">
                                      <div class="mainDocumentPreview mt-2" id="mainDocumentPreview_additional_${index}" style="display: block; visibility: visible !important; opacity: 1 !important; width: 100% !important; position: relative !important; margin: 10px 0px !important; padding: 10px !important; background: rgb(248, 249, 250) !important; border: 1px solid rgb(40, 167, 69) !important; border-radius: 8px !important;"></div>
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>
                  `;
              }

              // 🆕 دالة تحديث أزرار الحذف
              function updateAdditionalDeceasedRemoveButtons() {
                  document.querySelectorAll('.remove-additional-deceased-btn').forEach(btn => {
                      btn.onclick = function(e) {
                          e.preventDefault();
                          Swal.fire({
                              title: 'تأكيد الحذف',
                              text: 'هل أنت متأكد أنك تريد حذف بيانات هذا المتوفي؟',
                              icon: 'warning',
                              showCancelButton: true,
                              confirmButtonText: 'نعم، احذف',
                              cancelButtonText: 'إلغاء'
                          }).then((result) => {
                              if (result.isConfirmed) {
                                  btn.closest('.additional-deceased-form').remove();
                                  additionalDeceasedCount--;
                                  if (additionalDeceasedCount < maxAdditionalDeceased) {
                                      addAnotherDeceasedBtn.disabled = false;
                                  }
                              }
                          });
                      }
                  });
              }

              // 🆕 زر إضافة متوفي آخر
              if (addAnotherDeceasedBtn) {
                  addAnotherDeceasedBtn.addEventListener('click', function() {
                      if (additionalDeceasedCount < maxAdditionalDeceased) {
                          additionalDeceasedContainer.insertAdjacentHTML('beforeend', createAdditionalDeceasedForm(additionalDeceasedCount));
                          additionalDeceasedCount++;
                          updateAdditionalDeceasedRemoveButtons();
                          attachDeceasedIdListeners();
                          // تفعيل مراقبة رقم الهوية للنماذج المولدة
                          setupAdditionalDeceasedValidation();
                          // 🆕 ربط event listeners لرفع الملفات
                          setupAdditionalDeceasedFileUpload(additionalDeceasedCount - 1);
                          if (additionalDeceasedCount >= maxAdditionalDeceased) {
                              addAnotherDeceasedBtn.disabled = true;
                          }
                      }
                  });
              }

              // 🆕 دالة ربط رفع الملفات للنماذج المولدة ديناميكياً
              function setupAdditionalDeceasedFileUpload(index) {
                  const docSelect = document.getElementById(`mainDocumentTypeSelect_additional_${index}`);
                  const fileInput = document.getElementById(`mainDocumentFileInput_additional_${index}`);
                  const uploadZone = document.querySelector(`[data-upload-zone="additional_deceased_${index}"]`);

                  if (docSelect && fileInput && uploadZone) {
                      console.log(`🔧 [setupAdditionalDeceasedFileUpload] ربط event listeners للمتوفي الإضافي #${index}`);

                      // عند اختيار نوع وثيقة، افتح متصفح الملفات
                      docSelect.addEventListener('change', function() {
                          if (this.value && !this.disabled) {
                              console.log(`📂 [setupAdditionalDeceasedFileUpload] فتح متصفح الملفات للمتوفي #${index}, نوع الوثيقة:`, this.value);
                              fileInput.click();
                          }
                      });

                      // عند اختيار ملف، قم برفعه
                      fileInput.addEventListener('change', function(e) {
                          if (e.target.files && e.target.files.length > 0) {
                              const file = e.target.files[0];
                              const personKey = `additional_deceased_${index}`;
                              const docTypeValue = docSelect.value;
                              const idInput = document.querySelector(`[name="additional_deceased[${index}][id_number]"]`);
                              const personId = idInput ? idInput.value.trim() : '';

                              // الحصول على اسم الوثيقة من النص المعروض
                              const docTypeName = docSelect.options[docSelect.selectedIndex]?.text || docTypeValue;

                              console.log(`📤 [setupAdditionalDeceasedFileUpload] رفع ملف:`, {
                                  file: file.name,
                                  personKey: personKey,
                                  docType: docTypeValue,
                                  docTypeName: docTypeName,
                                  personId: personId
                              });

                              if (typeof window.addAttachmentTask === 'function') {
                                  window.addAttachmentTask(personKey, file, docTypeValue, personId, fileIdNumber, docTypeName);
                              } else {
                                  console.error('❌ window.addAttachmentTask غير متوفرة');
                              }

                              // إعادة تعيين القائمة المنسدلة
                              docSelect.selectedIndex = 0;
                              // إعادة تعيين input الملف
                              fileInput.value = '';
                          }
                      });

                      console.log(`✅ [setupAdditionalDeceasedFileUpload] تم ربط event listeners بنجاح للمتوفي #${index}`);
                  } else {
                      console.error(`❌ [setupAdditionalDeceasedFileUpload] لم يتم العثور على العناصر للمتوفي #${index}:`, {
                          hasDocSelect: !!docSelect,
                          hasFileInput: !!fileInput,
                          hasUploadZone: !!uploadZone
                      });
                  }
              }

              // 🆕 دالة تفعيل/تعطيل قائمة الوثائق بناءً على رقم الهوية للنماذج المولدة
              function setupAdditionalDeceasedValidation() {
                  document.querySelectorAll('.additional-deceased-form').forEach(form => {
                      const idInput = form.querySelector('.deceased-id-input');
                      const docSelect = form.querySelector('.additional-deceased-doc-select');

                      if (idInput && docSelect) {
                          function checkAndToggleDocSelect() {
                              const idValue = idInput.value.trim();
                              const isValid = /^\d{9}$/.test(idValue);

                              if (isValid) {
                                  docSelect.disabled = false;
                                  docSelect.classList.remove('disabled');
                                  docSelect.style.opacity = '1';
                                  docSelect.style.cursor = 'pointer';
                              } else {
                                  docSelect.disabled = true;
                                  docSelect.classList.add('disabled');
                                  docSelect.style.opacity = '0.5';
                                  docSelect.style.cursor = 'not-allowed';
                                  docSelect.selectedIndex = 0;
                              }
                          }

                          checkAndToggleDocSelect();
                          idInput.addEventListener('input', checkAndToggleDocSelect);
                          idInput.addEventListener('change', checkAndToggleDocSelect);

                          docSelect.addEventListener('mousedown', function(e) {
                              if (this.disabled) {
                                  e.preventDefault();
                                  const idValue = idInput.value.trim();
                                  let message = 'يجب إدخال رقم هوية المتوفي صحيح (9 أرقام) قبل اختيار نوع الوثيقة';

                                  if (!idValue) {
                                      message = 'يجب إدخال رقم هوية المتوفي أولاً';
                                  } else if (idValue.length < 9) {
                                      message = `رقم هوية المتوفي يجب أن يكون 9 أرقام (تم إدخال ${idValue.length} فقط)`;
                                  } else if (idValue.length > 9) {
                                      message = `رقم هوية المتوفي يجب أن يكون 9 أرقام (تم إدخال ${idValue.length})`;
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
                  });
              }

              // ربط المستمعات للحقول الموجودة مسبقاً (الأب والأم)
              attachDeceasedIdListeners();

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
