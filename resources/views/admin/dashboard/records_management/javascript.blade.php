@include('admin.dashboard.records_management.editSectionJavascript.formTabs')
@include('admin.dashboard.records_management.editSectionJavascript.refreshNameOfMembers')
@include('admin.dashboard.records_management.editSectionJavascript.ageCalculating')
@include('admin.dashboard.records_management.editSectionJavascript.manageDeadTap')
@include('admin.dashboard.records_management.editSectionJavascript.errorTracker')
@include('admin.dashboard.records_management.editSectionJavascript.autoComplete')
@include('admin.dashboard.records_management.editSectionJavascript.viewFinalInformation')
@include('admin.dashboard.records_management.editSectionJavascript.uploadDocument')

{{-- 🏦 JavaScript للحسابات البنكية والـ Validation --}}
<script>
$(document).ready(function() {
    let editBankAccountCount = 0;
    const maxEditBankAccounts = 10;
    const editBankNames = @json($bank_name ?? []);
    const existingAccounts = @json($bankAccounts ?? []);

    function createEditBankAccountForm(index, bankData = {}) {
        return `
        <div class="bank-account-form border rounded p-4 mb-4 position-relative"
             data-index="${index}"
             style="border: 2px dashed #009ef7 !important; background-color: #d8d8d8;">
            <button type="button" class="btn-close position-absolute top-0 end-0 m-3 remove-edit-bank-btn"
                    title="حذف الحساب" style="z-index: 10;"></button>
            <h6 class="mb-4 text-primary fw-bold">
                <i class="fas fa-university me-2"></i>حساب بنكي رقم ${index + 1}
            </h6>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">اسم البنك</label>
                    <select name="bank_accounts[${index}][bank_name]" class="form-select form-select-solid">
                        <option value="">اختر البنك</option>
                        ${editBankNames.map(bank => `
                            <option value="${bank.id}" ${bankData.bank_name == bank.id ? 'selected' : ''}>
                                ${bank.description}
                            </option>
                        `).join('')}
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">اسم صاحب الحساب</label>
                    <input type="text" name="bank_accounts[${index}][re_guardian_name]"
                           class="form-control form-control-solid" maxlength="100"
                           placeholder="أدخل اسم صاحب الحساب"
                           value="${bankData.re_guardian_name || ''}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">رقم هوية صاحب الحساب</label>
                    <input type="text" name="bank_accounts[${index}][person_owner_identity_number]"
                           class="form-control form-control-solid" maxlength="20"
                           placeholder="أدخل رقم الهوية"
                           value="${bankData.person_owner_identity_number || ''}"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">رقم هاتف صاحب الحساب</label>
                    <input type="text" name="bank_accounts[${index}][re_phone_number]"
                           class="form-control form-control-solid" maxlength="20"
                           placeholder="أدخل رقم الهاتف"
                           value="${bankData.re_phone_number || ''}"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">رقم IBAN بالدولار</label>
                    <input type="text" name="bank_accounts[${index}][iban_usd]"
                           class="form-control form-control-solid" maxlength="34"
                           placeholder="مثال: PS00XXXX0000000000000000000"
                           value="${bankData.iban_usd || ''}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">رقم IBAN بالشيكل</label>
                    <input type="text" name="bank_accounts[${index}][iban_shekel]"
                           class="form-control form-control-solid" maxlength="34"
                           placeholder="مثال: PS00XXXX0000000000000000000"
                           value="${bankData.iban_shekel || ''}">
                </div>
                ${bankData.id ? `
                <div class="col-12">
                    <div class="alert ${bankData.check_account == 1 ? 'alert-success' : 'alert-warning'} d-flex align-items-center justify-content-between">
                        <span>
                            ${bankData.check_account == 1 ?
                                '<i class="fas fa-check-circle me-2"></i>تم اعتماد هذا الحساب' :
                                '<i class="fas fa-exclamation-triangle me-2"></i>هذا الحساب في انتظار الاعتماد'}
                        </span>
                        ${bankData.check_account != 1 ? `
                        <button type="button" class="btn btn-sm btn-success approve-bank-account"
                                data-account-id="${bankData.id}">
                            <i class="fas fa-check me-1"></i>اعتماد الحساب
                        </button>
                        ` : ''}
                    </div>
                </div>
                ` : ''}
                <input type="hidden" name="bank_accounts[${index}][id]" value="${bankData.id || ''}">
            </div>
        </div>
        `;
    }

    function updateRemoveEditBankButtons() {
        $('.remove-edit-bank-btn').off('click').on('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'تأكيد الحذف',
                text: 'هل أنت متأكد من حذف هذا الحساب البنكي؟',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    $(this).closest('.bank-account-form').remove();
                    editBankAccountCount--;

                    // إعادة ترقيم
                    $('.bank-account-form').each(function(idx) {
                        $(this).attr('data-index', idx);
                        $(this).find('h6').html(`<i class="fas fa-university me-2"></i>حساب بنكي رقم ${idx + 1}`);
                    });

                    if (editBankAccountCount === 0) {
                        $('#editBankAccountsContainer').addClass('d-none');
                    }

                    $('#addEditBankAccountBtn').prop('disabled', false);

                    Swal.fire({
                        icon: 'success',
                        title: 'تم الحذف',
                        text: 'تم حذف الحساب البنكي بنجاح',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        });
    }

    // تحميل الحسابات الموجودة
    if (existingAccounts && existingAccounts.length > 0) {
        $('#editBankAccountsContainer').removeClass('d-none');
        existingAccounts.forEach(function(account, index) {
            $('#editBankAccountsContainer').append(createEditBankAccountForm(index, account));
            editBankAccountCount++;
        });
        updateRemoveEditBankButtons();

        if (editBankAccountCount >= maxEditBankAccounts) {
            $('#addEditBankAccountBtn').prop('disabled', true);
        }
    }

    // إضافة حساب بنكي جديد
    $('#addEditBankAccountBtn').on('click', function() {
        if (editBankAccountCount < maxEditBankAccounts) {
            $('#editBankAccountsContainer').removeClass('d-none');
            $('#editBankAccountsContainer').append(createEditBankAccountForm(editBankAccountCount));
            editBankAccountCount++;
            updateRemoveEditBankButtons();

            // التمرير للحساب الجديد
            $('html, body').animate({
                scrollTop: $('.bank-account-form:last').offset().top - 100
            }, 500);

            if (editBankAccountCount >= maxEditBankAccounts) {
                $(this).prop('disabled', true);
            }
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'تنبيه',
                text: 'لا يمكن إضافة أكثر من 10 حسابات بنكية'
            });
        }
    });

    // ============================================
    // نظام Validation الشامل
    // ============================================

    // دالة لجمع وعرض جميع أخطاء الـ Validation
    function collectAndDisplayValidationErrors() {
        const errors = [];
        const errorsList = $('#validation-errors-list');
        const errorsContainer = $('#validation-errors-container');

        errorsList.empty();

        // التحقق من الحقول المطلوبة في البوابة الأولى
        const requiredFields = [
            { name: 'data_section_id', label: 'القسم', selector: 'select[name="data_section_id"]' },
            { name: 'data_id_number', label: 'رقم الهوية', selector: 'input[name="data_id_number"]' },
            { name: 'file_id_number', label: 'رقم الملف', selector: 'input[name="file_id_number"]' },
            { name: 'data_first_name', label: 'الاسم الأول', selector: 'input[name="data_first_name"]' },
            { name: 'data_father_name', label: 'اسم الأب', selector: 'input[name="data_father_name"]' },
            { name: 'data_grand_father_name', label: 'اسم الجد', selector: 'input[name="data_grand_father_name"]' },
            { name: 'data_family_name', label: 'اسم العائلة', selector: 'input[name="data_family_name"]' },
            { name: 'data_relationship', label: 'صلة القرابة', selector: 'select[name="data_relationship"]' },
            { name: 'data_birth_date', label: 'تاريخ الميلاد', selector: 'input[name="data_birth_date"]' },
            { name: 'data_gender', label: 'الجنس', selector: 'select[name="data_gender"]' },
            { name: 'data_phone_number', label: 'رقم الهاتف', selector: 'input[name="data_phone_number"]' },
            { name: 'data_marital_status', label: 'الحالة الاجتماعية', selector: 'select[name="data_marital_status"]' },
            { name: 'data_displacement_status', label: 'حالة النزوح', selector: 'select[name="data_displacement_status"]' },
            { name: 'data_current_address', label: 'العنوان الحالي', selector: 'input[name="data_current_address"]' },
            { name: 'data_city', label: 'المدينة', selector: 'select[name="data_city"]' },
            { name: 'data_province', label: 'المحافظة', selector: 'select[name="data_province"]' },
            { name: 'data_health_status', label: 'الحالة الصحية', selector: 'select[name="data_health_status"]' },
            { name: 'data_employment_status_breadwinner', label: 'الحالة الوظيفية المعيل', selector: 'select[name="data_employment_status_breadwinner"]' },
            { name: 'data_housing_status', label: 'حالة السكن', selector: 'select[name="data_housing_status"]' },
            { name: 'data_current_housing_type', label: 'نوع السكن الحالي', selector: 'select[name="data_current_housing_type"]' }
        ];

        requiredFields.forEach(function(field) {
            const element = $(field.selector);
            if (element.length > 0) {
                const value = element.val();
                if (!value || value.trim() === '') {
                    errors.push(field.label + ' مطلوب');
                    element.addClass('is-invalid');
                } else {
                    element.removeClass('is-invalid');
                }
            }
        });

        // التحقق من صحة رقم الهوية (10 أرقام)
        const idNumber = $('input[name="data_id_number"]').val();
        if (idNumber && idNumber.length !== 9) {
            errors.push('رقم الهوية يجب أن يتكون من 9 أرقام');
            $('input[name="data_id_number"]').addClass('is-invalid');
        }

        // التحقق من صحة رقم الملف (6 أرقام)
        const fileId = $('input[name="file_id_number"]').val();
        if (fileId && fileId.length > 6) {
            errors.push('رقم الملف يجب ألا يتجاوز 6 أرقام');
            $('input[name="file_id_number"]').addClass('is-invalid');
        }

        // عرض الأخطاء إذا وجدت
        if (errors.length > 0) {
            errors.forEach(function(error) {
                errorsList.append('<li>' + error + '</li>');
            });
            errorsContainer.removeClass('d-none');

            // التمرير إلى منطقة الأخطاء
            $('html, body').animate({
                scrollTop: errorsContainer.offset().top - 100
            }, 500);

            return false;
        } else {
            errorsContainer.addClass('d-none');
            return true;
        }
    }

    // التحقق عند تقديم النموذج
    $('#main_form').on('submit', function(e) {
        if (!collectAndDisplayValidationErrors()) {
            e.preventDefault();

            Swal.fire({
                icon: 'error',
                title: 'خطأ في البيانات',
                html: 'يرجى تصحيح الأخطاء المعروضة في النموذج قبل الحفظ.<br><strong>عدد الأخطاء: ' + $('#validation-errors-list li').length + '</strong>',
                confirmButtonText: 'حسناً',
                confirmButtonColor: '#d33'
            });

            return false;
        }
    });

    // إزالة علامة الخطأ عند تعديل الحقل
    $('input[required], select[required], input.is-invalid, select.is-invalid').on('change input', function() {
        if ($(this).val() && $(this).val().trim() !== '') {
            $(this).removeClass('is-invalid');
        }
    });

    // التحقق الفوري عند تبديل البوابات
    $('.nav-link[data-bs-toggle="tab"]').on('click', function() {
        // إخفاء رسائل الأخطاء عند التنقل
        $('#validation-errors-container').addClass('d-none');
    });

    // 🏦 اعتماد الحساب البنكي
    $(document).on('click', '.approve-bank-account', function(e) {
        e.preventDefault();
        const accountId = $(this).data('account-id');
        const button = $(this);

        Swal.fire({
            title: 'تأكيد الاعتماد',
            text: 'هل أنت متأكد من اعتماد هذا الحساب البنكي؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، اعتمد',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("admin.records.management.approveBankAccount") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        account_id: accountId
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الاعتماد',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            });

                            // تحديث الواجهة
                            button.closest('.alert')
                                .removeClass('alert-warning')
                                .addClass('alert-success')
                                .html('<span><i class="fas fa-check-circle me-2"></i>تم اعتماد هذا الحساب</span>');
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: xhr.responseJSON?.message || 'حدث خطأ أثناء اعتماد الحساب'
                        });
                    }
                });
            }
        });
    });
});
</script>




