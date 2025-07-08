@include('user.generalRegistration.javascript.taps')
@include('user.generalRegistration.javascript.manageDaedTap')
{{-- تحميل أداة القص مبكراً قبل documentUpload --}}
@include('user.generalRegistration.javascript.documentUpload')
@include('user.generalRegistration.javascript.cropper')
@include('user.generalRegistration.javascript.ageCalculating')
@include('user.generalRegistration.javascript.errorTracker')
@include('user.generalRegistration.javascript.autoComplete')
@include('user.generalRegistration.javascript.showInsertedData')
@include('user.generalRegistration.javascript.manageForm')

<script>
    // ...existing code...

document.addEventListener('DOMContentLoaded', function() {
    // --- بوابة البيانات الأساسية ---
    const mainDocType = document.getElementById('mainDocumentTypeSelect_main');
    const mainFileInput = document.getElementById('mainDocumentFileInput_main');
    if (mainDocType && mainFileInput) {
        mainDocType.addEventListener('mousedown', function(e) {
            const idVal = document.querySelector('[name="data_id_number"]')?.value || '';
            if (!idVal) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'يرجى إدخال رقم الهوية أولاً قبل رفع أي وثيقة.'
                });
                return false;
            }
        });
        mainDocType.addEventListener('change', function(e) {
            const idVal = document.querySelector('[name="data_id_number"]')?.value || '';
            if (!idVal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'يرجى إدخال رقم الهوية أولاً قبل رفع أي وثيقة.'
                });
                mainDocType.value = '';
                return false;
            }
        });
    }

    // --- بوابة الأفراد المتوفين (الأب) ---
    const fatherDocType = document.getElementById('mainDocumentTypeSelect_father');
    const fatherFileInput = document.getElementById('mainDocumentFileInput_father');
    if (fatherDocType && fatherFileInput) {
        fatherDocType.addEventListener('mousedown', function(e) {
            const idVal = document.querySelector('[name="father_id"]')?.value || '';
            if (!idVal) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'يرجى إدخال رقم هوية الأب أولاً قبل رفع أي وثيقة.'
                });
                return false;
            }
        });
        fatherDocType.addEventListener('change', function(e) {
            const idVal = document.querySelector('[name="father_id"]')?.value || '';
            if (!idVal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'يرجى إدخال رقم هوية الأب أولاً قبل رفع أي وثيقة.'
                });
                fatherDocType.value = '';
                return false;
            }
        });
    }

    // --- بوابة الأفراد المتوفين (الأم) ---
    const motherDocType = document.getElementById('mainDocumentTypeSelect_mother');
    const motherFileInput = document.getElementById('mainDocumentFileInput_mother');
    if (motherDocType && motherFileInput) {
        motherDocType.addEventListener('mousedown', function(e) {
            const idVal = document.querySelector('[name="mother_id"]')?.value || '';
            if (!idVal) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'يرجى إدخال رقم هوية الأم أولاً قبل رفع أي وثيقة.'
                });
                return false;
            }
        });
        motherDocType.addEventListener('change', function(e) {
            const idVal = document.querySelector('[name="mother_id"]')?.value || '';
            if (!idVal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'يرجى إدخال رقم هوية الأم أولاً قبل رفع أي وثيقة.'
                });
                motherDocType.value = '';
                return false;
            }
        });
    }
});
// ...existing code...
    </script>
