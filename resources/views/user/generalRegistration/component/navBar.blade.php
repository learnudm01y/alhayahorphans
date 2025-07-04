<ul class="nav nav-tabs nav-fill mb-4 mobile-bottom-tabs" id="formTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active py-3" id="instructions-tab" data-bs-toggle="tab" data-bs-target="#instructions"
            type="button" role="tab" aria-controls="instructions" aria-selected="true">
            <div class="d-flex flex-column align-items-center">
                <i class="fas fa-home tab-icon mb-2"></i>
                <span class="fs-4 fw-bold tab-label"> البوابة الرئيسية </span>
            </div>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link py-3" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button"
            role="tab" aria-controls="basic" aria-selected="false">
            <div class="d-flex flex-column align-items-center">
                <i class="fas fa-user tab-icon mb-2"></i>
                <span class="fs-4 fw-bold tab-label">البيانات الأساسية</span>
            </div>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link py-3" id="family-members-tab" data-bs-toggle="tab" data-bs-target="#family-members"
            type="button" role="tab" aria-controls="family-members" aria-selected="false">
            <div class="d-flex flex-column align-items-center">
                <i class="fas fa-users tab-icon mb-2"></i>
                <span class="fs-4 fw-bold tab-label">أفراد الأسرة</span>
            </div>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link py-3" id="deceased-tab" data-bs-toggle="tab" data-bs-target="#deceased" type="button"
            role="tab" aria-controls="deceased" aria-selected="false">
            <div class="d-flex flex-column align-items-center">
                <i class="fas fa-user-times tab-icon mb-2"></i>
                <span class="fs-4 fw-bold tab-label">الأفراد المتوفين</span>
            </div>
        </button>
    </li>
</ul>
{{-- <script>
    document.addEventListener('DOMContentLoaded', function() {
        // بيانات الوثائق من الباكند (يجب تمريرها من الكنترولر)
        const documentTypes = @json($documentTypes);

        // دوال التحقق لكل بوابة
        function validateBasicTab() {
            // تحقق من الحقول المطلوبة
            const requiredFields = [
                'data_section_id', 'data_id_number', 'user_password', 'user_password_confirmation',
                'data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name',
                'data_relationship', 'data_birth_date', 'data_gender', 'data_phone_number',
                'data_marital_status', 'data_displacement_status', 'data_current_address',
                'data_city', 'data_province', 'data_health_status', 'data_employment_status_breadwinner',
                'data_housing_status', 'data_current_housing_type'
            ];
            for (const name of requiredFields) {
                const el = document.querySelector(`[name="${name}"]`);
                if (el && !el.value) return false;
            }
            // تحقق من رفع جميع الوثائق الإجبارية
            let valid = true;
            documentTypes.filter(dt => dt.basic_enabled && dt.basic_required).forEach(dt => {
                const input = document.querySelector(`input[type="file"][data-pref="${dt.pref}"]`);
                if (!input || !(input.files && input.files.length > 0)) valid = false;
            });
            return valid;
        }

        function validateDeceasedTab() {
            // أضف هنا التحقق من الحقول المطلوبة في بوابة المتوفين حسب نموذجك
            // مثال: تحقق من وجود رقم هوية الأب/الأم ورفع الوثائق الإجبارية
            let valid = true;
            documentTypes.filter(dt => dt.deceased_enabled && dt.deceased_required).forEach(dt => {
                const input = document.querySelector(`input[type="file"][data-pref="${dt.pref}"]`);
                if (!input || !(input.files && input.files.length > 0)) valid = false;
            });
            // أضف تحقق الحقول الأخرى إذا لزم
            return valid;
        }

        function validateFamilyTab() {
            // تحقق من كل فرد من أفراد الأسرة
            let valid = true;
            document.querySelectorAll('.family-member-form:not(.d-none)').forEach(function(form) {
                const requiredFields = [
                    'first_name', 'last_name', 'person_id', 'person_birth_date', 'person_gender'
                ];
                for (const field of requiredFields) {
                    const el = form.querySelector(`[name*="[${field}]"]`);
                    if (el && !el.value) valid = false;
                }
                // تحقق من رفع جميع الوثائق الإجبارية لكل فرد
                documentTypes.filter(dt => dt.family_enabled && dt.family_required).forEach(dt => {
                    const input = form.querySelector(
                        `input[type="file"][data-pref="${dt.pref}"]`);
                    if (!input || !(input.files && input.files.length > 0)) valid = false;
                });
            });
            return valid;
        }

        // منع التنقل بين التبويبات إلا بعد تحقق الشروط
        document.querySelectorAll('#formTabs .nav-link').forEach(function(tabBtn) {
            tabBtn.addEventListener('click', function(e) {
                const target = this.getAttribute('data-bs-target');
                if (target === '#instructions') return; // استثناء البوابة الرئيسية

                // تحقق من البوابة السابقة حسب الترتيب
                const tabOrder = ['#instructions', '#basic', '#deceased', '#family-members'];
                const idx = tabOrder.indexOf(target);
                if (idx > 0) {
                    let prevValid = true;
                    if (tabOrder[idx - 1] === '#basic') prevValid = validateBasicTab();
                    if (tabOrder[idx - 1] === '#deceased') prevValid = validateDeceasedTab();
                    if (tabOrder[idx - 1] === '#family-members') prevValid =
                validateFamilyTab();
                    if (!prevValid) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى إكمال جميع البيانات والوثائق الإجبارية في البوابة السابقة قبل المتابعة.',
                            confirmButtonText: 'حسنًا'
                        });
                        return false;
                    }
                }
            });
        });
    });
</script> --}}
