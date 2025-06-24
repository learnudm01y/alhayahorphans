 <div class="tab-pane fade" id="attachments" role="tabpanel"
                                    aria-labelledby="attachments-tab">
                                    <div class="row g-3">
                                        <div class="col-12 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">اختر الشخص <span
                                                                    class="text-danger">*</span></label>
                                                            <select id="person_selector" class="form-select">
                                                                <option value="">اختر الشخص</option>
                                                                <optgroup label="صاحب الملف">
                                                                    <option value="main"
                                                                        data-id="{{ $file_id_number }}"
                                                                        data-id-number="{{ isset($data_id_number) ? $data_id_number : '' }}">
                                                                        <span class="main-person"></span>
                                                                    </option>
                                                                </optgroup>
                                                                <optgroup label="أفراد الأسرة"
                                                                    id="family_members_options">
                                                                    @foreach ($family_members ?? [] as $index => $member)
                                                                        <option value="family_{{ $index }}"
                                                                            data-id="{{ $file_id_number }}"
                                                                            data-id-number="{{ $member['person_id'] ?? '' }}">
                                                                            {{ $member['name'] ?? '' }}
                                                                        </option>
                                                                    @endforeach
                                                                </optgroup>
                                                                <optgroup label="الأفراد المتوفين">
                                                                    <option value="deceased_father"
                                                                        data-id-number="{{ isset($father_id_number) ? $father_id_number : '' }}">
                                                                        الأب المتوفى
                                                                    </option>
                                                                    <option value="deceased_mother"
                                                                        data-id-number="{{ isset($mother_id_number) ? $mother_id_number : '' }}">
                                                                        الأم المتوفية
                                                                    </option>
                                                                </optgroup>
                                                            </select>

                                                            <!-- حقل مخفي لإرسال رقم الهوية -->
                                                            <input type="hidden" name="person_identity_number"
                                                                id="identity_number">
                                                        </div>

                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">نوع الوثيقة <span
                                                                    class="text-danger">*</span></label>
                                                            <select name="document_type" id="document_type"
                                                                class="form-select">
                                                                <option value="">اختر نوع الوثيقة</option>
                                                                @foreach ($documentTypes as $documentType)
                                                                    <option value="{{ $documentType->pref }}">
                                                                        {{ $documentType->description }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>

                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">رقم الملف</label>
                                                            <input type="text" id="document_id"
                                                                class="form-control bg-secondary bg-opacity-25"
                                                                value="{{ $file_id_number ?? '' }}" readonly>
                                                        </div>
                                                    </div>

                                                    <div class="text-center mt-3">
                                                        <div class="file-upload-wrapper">
                                                            <input type="file" name="document_file" id="document_file"
                                                                class="form-control" accept="image/*">
                                                            <div id="preview" class="mt-3 d-none">
                                                                <img src="" alt="معاينة" class="img-fluid mb-2"
                                                                    style="max-height: 200px;">
                                                                <div class="mt-2">
                                                                    <button type="button" id="confirmUpload"
                                                                        class="btn btn-success">
                                                                        <i class="fas fa-check me-2"></i>تأكيد الرفع
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title mb-3">الوثائق المرفقة</h5>
                                                    <div id="documents_container">
                                                        <!-- قسم وثائق صاحب الملف -->
                                                        <div class="card mb-4">
                                                            <div class="card-body">
                                                                <h5 class="card-title border-bottom pb-2">وثائق صاحب الملف
                                                                </h5>
                                                                <div id="main_person_docs"
                                                                    class="d-flex flex-nowrap overflow-auto gap-3 py-2">
                                                                    <!-- وثائق صاحب الملف ستظهر هنا -->
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- قسم وثائق أفراد الأسرة -->
                                                        <div class="card mb-5">
                                                            <div class="card-body">
                                                                <h5 class="card-title border-bottom pb-2">وثائق أفراد
                                                                    الأسرة</h5>
                                                                <div id="family_members_docs">
                                                                    <!-- سيتم إضافة أقسام الوثائق ديناميكياً لكل فرد -->
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- قسم وثائق المتوفين -->
                                                        <div class="card mb-4">
                                                            <div class="card-body">
                                                                <h5 class="card-title border-bottom pb-2 mb-4">
                                                                    <i class="fas fa-user-times me-2"></i>وثائق الأفراد
                                                                    المتوفين
                                                                </h5>
                                                                <div class="row g-4">
                                                                    <div class="col-md-6">
                                                                        <div class="deceased-docs-section">
                                                                            <h6 class="text-primary mb-3">وثائق الأب
                                                                                المتوفى</h6>
                                                                            <div id="father_docs"
                                                                                class="documents-flex-container">
                                                                                <!-- وثائق الأب المتوفى ستظهر هنا -->
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="deceased-docs-section">
                                                                            <h6 class="text-primary mb-3">وثائق الأم
                                                                                المتوفية</h6>
                                                                            <div id="mother_docs"
                                                                                class="documents-flex-container">
                                                                                <!-- وثائق الأم المتوفية ستظهر هنا -->
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- تم حذف زر الحفظ من هنا -->
                                </div>
