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
                              <input type="text" name="father_first_name" class="form-control">
                          </div>
                          <div class="col-md-3">
                              <label class="form-label">الاسم الثاني</label>
                              <input type="text" name="father_second_name" class="form-control">
                          </div>
                          <div class="col-md-3">
                              <label class="form-label">الاسم الثالث</label>
                              <input type="text" name="father_third_name" class="form-control">
                          </div>
                          <div class="col-md-3">
                              <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
                              <input type="text" name="father_last_name" class="form-control">
                          </div>
                          <div class="col-md-4">
                              <label class="form-label">رقم الهوية <span class="text-danger">*</span></label>
                              <input type="text" name="father_id" class="form-control" inputmode="numeric"
                                  pattern="[0-9]*" maxlength="10"
                                  oninput="this.value = this.value.replace(/[^0-9]/g, '');">
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
                                  المطلوبة. يمكنك رفع صورة فقط.
                              </div>
                              <label class="form-label fw-bold"> رفع الملفات <span class="text-danger">*</span></label>
                              <select class="form-select" id="mainDocumentTypeSelect">
                                  <option value="">اختر نوع الوثيقة</option>
                                  @foreach ($documentTypes as $documentType)
                                      <option value="{{ $documentType->pref }}">
                                          {{ $documentType->description }}</option>
                                  @endforeach
                              </select>
                              <input type="file" id="mainDocumentFileInput" accept="image/*,.pdf"
                                  style="display:none;">
                              <div id="mainDocumentPreview" class="mt-2"></div>
                              <div id="mainDocumentNames" class="mt-2"></div>
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
                              <input type="text" name="mother_first_name" class="form-control">
                          </div>
                          <div class="col-md-3">
                              <label class="form-label">الاسم الثاني</label>
                              <input type="text" name="mother_second_name" class="form-control">
                          </div>
                          <div class="col-md-3">
                              <label class="form-label">الاسم الثالث</label>
                              <input type="text" name="mother_third_name" class="form-control">
                          </div>
                          <div class="col-md-3">
                              <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
                              <input type="text" name="mother_last_name" class="form-control">
                          </div>
                          <div class="col-md-4">
                              <label class="form-label">رقم الهوية <span class="text-danger">*</span></label>
                              <input type="text" name="mother_id" class="form-control" inputmode="numeric"
                                  pattern="[0-9]*" maxlength="10"
                                  oninput="this.value = this.value.replace(/[^0-9]/g, '');">
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
                                  المطلوبة. يمكنك رفع صورة فقط.
                              </div>
                              <label class="form-label fw-bold"> رفع الملفات <span
                                      class="text-danger">*</span></label>
                              <select class="form-select" id="mainDocumentTypeSelect">
                                  <option value="">اختر نوع الوثيقة</option>
                                  @foreach ($documentTypes as $documentType)
                                      <option value="{{ $documentType->pref }}">
                                          {{ $documentType->description }}</option>
                                  @endforeach
                              </select>
                              <input type="file" id="mainDocumentFileInput" accept="image/*,.pdf"
                                  style="display:none;">
                              <div id="mainDocumentPreview" class="mt-2"></div>
                              <div id="mainDocumentNames" class="mt-2"></div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </div>
  </div>
