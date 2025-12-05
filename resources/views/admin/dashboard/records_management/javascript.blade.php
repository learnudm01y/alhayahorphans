@include('admin.dashboard.records_management.editSectionJavascript.formTabs')
@include('admin.dashboard.records_management.editSectionJavascript.refreshNameOfMembers')
@include('admin.dashboard.records_management.editSectionJavascript.ageCalculating')
@include('admin.dashboard.records_management.editSectionJavascript.manageDeadTap')
@include('admin.dashboard.records_management.editSectionJavascript.errorTracker')
@include('admin.dashboard.records_management.editSectionJavascript.autoComplete')
@include('admin.dashboard.records_management.editSectionJavascript.viewFinalInformation')
@include('admin.dashboard.records_management.editSectionJavascript.uploadDocument')

{{-- 🏦 JavaScript للحسابات البنكية في صفحة التعديل --}}
@if(isset($edit) && $edit)
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
});
</script>
@endif




