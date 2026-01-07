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

// function checkDuplicateIdsInForm(input) {
//     const idInputs = [
//         ...document.querySelectorAll('input[name="data_id_number"]'),
//         ...document.querySelectorAll('input[name="father_id"]'),
//         ...document.querySelectorAll('input[name="mother_id"]'),
//         ...document.querySelectorAll('input[name^="family_members"][name$="[person_id]"]')
//     ];

//     const ids = {};
//     let duplicate = null;
//     let isCurrentDuplicate = false;

//     idInputs.forEach(inp => {
//         const val = inp.value.trim();
//         if (!val) return;
//         if (ids[val]) {
//             if (input && inp === input) isCurrentDuplicate = true;
//             inp.classList.add('is-invalid');
//             inp.classList.remove('is-valid');
//             duplicate = val;
//         } else {
//             ids[val] = true;
//             inp.classList.remove('is-invalid');
//             inp.classList.remove('is-valid');
//         }
//     });

//     if (input && isCurrentDuplicate) {
//         input.classList.add('is-invalid');
//         input.classList.remove('is-valid');
//         Swal.fire({
//             icon: 'error',
//             title: 'تنبيه',
//             text: `رقم الهوية ${input.value.trim()} مكرر في النموذج!`
//         });
//         return false;
//     }

//     if (duplicate) {
//         Swal.fire({
//             icon: 'error',
//             title: 'تنبيه',
//             text: `رقم الهوية ${duplicate} مكرر في النموذج!`
//         });
//         return false;
//     }
//     return true;
// }

// // استدعِ الدالة عند كل إدخال
// document.addEventListener('input', function(e) {
//     if (
//         e.target.matches('input[name="data_id_number"], input[name="father_id"], input[name="mother_id"], input[name^="family_members"][name$="[person_id]"]')
//     ) {
//         checkDuplicateIdsInForm(e.target);
//     }
// });

// document.getElementById('main_form')?.addEventListener('submit', function(e) {
//     if (!checkDuplicateIdsInForm()) {
//         e.preventDefault();
//     }
// });

// // التحقق على مستوى قاعدة البيانات فقط إذا لم يكن مكرر في النموذج
// function checkIdNumberInDatabase(id, input) {
//     // تحقق أولاً من التكرار في النموذج
//     if (!id) return;
//     if (!checkDuplicateIdsInForm(input)) {
//         // إذا كان مكرر في النموذج، لا تفحص في قاعدة البيانات
//         return;
//     }
//     fetch('/check-id-number', {
//         method: 'POST',
//         headers: {
//             'Content-Type': 'application/json',
//             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
//         },
//         body: JSON.stringify({ id_number: id })
//     })
//     .then(res => res.json())
//     .then(data => {
//         if (data.exists) {
//             input.classList.add('is-invalid');
//             input.classList.remove('is-valid');
//             Swal.fire({
//                 icon: 'error',
//                 title: 'تنبيه',
//                 text: `رقم الهوية ${id} موجود بالفعل في قاعدة البيانات!`
//             });
//         } else {
//             input.classList.remove('is-invalid');
//             input.classList.add('is-valid'); // إطار أخضر فقط إذا لم يكن مكرر
//         }
//     });
// }

// // اربطها مع جميع حقول الهوية
// document.addEventListener('blur', function(e) {
//     if (
//         e.target.matches('input[name="data_id_number"], input[name="father_id"], input[name="mother_id"], input[name^="family_members"][name$="[person_id]"]')
//     ) {
//         checkIdNumberInDatabase(e.target.value.trim(), e.target);
//     }
// }, true);
// new code
function checkDuplicateIdsInForm(input) {
    // جمع جميع حقول رقم الهوية من جميع البوابات
    const idInputs = [
        ...document.querySelectorAll('input[name="data_id_number"]'), // البيانات الأساسية
        ...document.querySelectorAll('input[name="father_id"]'), // الأب المتوفى
        ...document.querySelectorAll('input[name="mother_id"]'), // الأم المتوفاة
        ...document.querySelectorAll('input[name^="additional_deceased"][name$="[id_number]"]'), // المتوفين الإضافيين
        ...document.querySelectorAll('input[name^="family_members"][name$="[person_id]"]') // أفراد الأسرة
    ];

    const ids = {};
    let duplicate = null;
    let isCurrentDuplicate = false;
    let duplicateInputs = []; // لتتبع جميع الحقول المكررة

    idInputs.forEach(inp => {
        const val = inp.value.trim();
        if (!val || val.length < 9) return; // تجاهل القيم الفارغة أو القصيرة

        if (ids[val]) {
            // وجدنا تكرار
            if (input && inp === input) isCurrentDuplicate = true;
            inp.classList.add('is-invalid');
            inp.classList.remove('is-valid');
            duplicate = val;
            duplicateInputs.push(inp);

            // أضف الحقل الأول المكرر أيضاً
            if (ids[val].input) {
                ids[val].input.classList.add('is-invalid');
                ids[val].input.classList.remove('is-valid');
                if (!duplicateInputs.includes(ids[val].input)) {
                    duplicateInputs.push(ids[val].input);
                }
            }
        } else {
            ids[val] = { input: inp };
            // فقط أزل is-invalid، لا تضف is-valid حتى يتم التحقق من قاعدة البيانات
            inp.classList.remove('is-invalid');
        }
    });

    if (input && isCurrentDuplicate) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        Swal.fire({
            icon: 'error',
            title: 'رقم هوية مكرر',
            text: `رقم الهوية ${input.value.trim()} مكرر في النموذج! يجب أن يكون كل رقم هوية فريداً.`,
            confirmButtonText: 'حسناً'
        });
        return false;
    }

    if (duplicate) {
        Swal.fire({
            icon: 'error',
            title: 'رقم هوية مكرر',
            text: `رقم الهوية ${duplicate} مكرر في النموذج! يجب أن يكون كل رقم هوية فريداً.`,
            confirmButtonText: 'حسناً',
            didClose: () => {
                // ركز على أول حقل مكرر
                if (duplicateInputs.length > 0) {
                    duplicateInputs[0].focus();
                }
            }
        });
        return false;
    }
    return true;
}

// التحقق على مستوى قاعدة البيانات فقط إذا لم يكن مكرر في النموذج
async function checkIdNumbersInDatabase() {
    // جمع جميع حقول رقم الهوية من جميع البوابات
    const idInputs = [
        ...document.querySelectorAll('input[name="data_id_number"]'), // البيانات الأساسية
        ...document.querySelectorAll('input[name="father_id"]'), // الأب المتوفى
        ...document.querySelectorAll('input[name="mother_id"]'), // الأم المتوفاة
        ...document.querySelectorAll('input[name^="additional_deceased"][name$="[id_number]"]'), // المتوفين الإضافيين
        ...document.querySelectorAll('input[name^="family_members"][name$="[person_id]"]') // أفراد الأسرة
    ];

    for (const input of idInputs) {
        const id = input.value.trim();
        if (!id || id.length < 9) continue; // تجاهل القيم الفارغة أو القصيرة

        // تحقق من التكرار في النموذج أولاً
        if (!checkDuplicateIdsInForm(input)) {
            return false;
        }

        // تحقق من قاعدة البيانات
        try {
            const res = await fetch('/check-id-number', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ id_number: id })
            });
            const data = await res.json();

            if (data.exists) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                Swal.fire({
                    icon: 'error',
                    title: 'رقم هوية موجود مسبقاً',
                    text: `رقم الهوية ${id} موجود بالفعل في قاعدة البيانات!`,
                    confirmButtonText: 'حسناً',
                    didClose: () => {
                        input.focus();
                    }
                });
                return false;
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
            }
        } catch (error) {
            console.error('خطأ في التحقق من رقم الهوية:', error);
        }
    }
    return true;
}

// استدعِ الدالة عند كل إدخال
document.addEventListener('input', function(e) {
    if (
        e.target.matches('input[name="data_id_number"], input[name="father_id"], input[name="mother_id"], input[name^="additional_deceased"][name$="[id_number]"], input[name^="family_members"][name$="[person_id]"]')
    ) {
        checkDuplicateIdsInForm(e.target);
    }
});

// اربطها مع جميع حقول الهوية عند الخروج من الحقل
document.addEventListener('blur', function(e) {
    if (
        e.target.matches('input[name="data_id_number"], input[name="father_id"], input[name="mother_id"], input[name^="additional_deceased"][name$="[id_number]"], input[name^="family_members"][name$="[person_id]"]')
    ) {
        // تحقق من التكرار في النموذج أولاً
        if (!checkDuplicateIdsInForm(e.target)) return;
        // ثم تحقق من قاعدة البيانات
        checkIdNumbersInDatabase();
    }
}, true);

// === ضع الكود الجديد هنا ===
async function validateAllIds(e) {
    // تحقق من التكرار في النموذج
    const isUnique = checkDuplicateIdsInForm();
    if (!isUnique) {
        if (e) e.preventDefault();
        return false;
    }
    // تحقق من قاعدة البيانات
    const dbOk = await checkIdNumbersInDatabase();
    if (!dbOk) {
        if (e) e.preventDefault();
        return false;
    }
    return true;
}

// تحديث رقم الهوية المرتبط بالصور او المرفقات بشكل عام
// ...existing code...

// تحديث رقم الهوية في جميع المرفقات عند تغيير رقم الهوية لأي شخص
document.addEventListener('input', function(e) {
    // تحديث رقم الهوية للبيانات الأساسية
    if (e.target.name === 'data_id_number') {
        const newId = e.target.value.trim();
        if (window.allDocs && window.allDocs.has('main')) {
            window.allDocs.get('main').forEach(doc => {
                doc.personId = newId;
            });
        }
    }
    // تحديث رقم هوية الأب المتوفى
    if (e.target.name === 'father_id') {
        const newId = e.target.value.trim();
        if (window.allDocs && window.allDocs.has('deceased_father')) {
            window.allDocs.get('deceased_father').forEach(doc => {
                doc.personId = newId;
            });
        }
    }
    // تحديث رقم هوية الأم المتوفاة
    if (e.target.name === 'mother_id') {
        const newId = e.target.value.trim();
        if (window.allDocs && window.allDocs.has('deceased_mother')) {
            window.allDocs.get('deceased_mother').forEach(doc => {
                doc.personId = newId;
            });
        }
    }
    // تحديث رقم هوية أفراد الأسرة
    if (
        e.target.name &&
        e.target.name.startsWith('family_members[') &&
        e.target.name.endsWith('[person_id]')
    ) {
        const match = e.target.name.match(/family_members\[(\d+)\]\[person_id\]/);
        if (match) {
            const idx = match[1];
            const newId = e.target.value.trim();
            const personKey = `family_${idx}`;
            if (window.allDocs && window.allDocs.has(personKey)) {
                window.allDocs.get(personKey).forEach(doc => {
                    doc.personId = newId;
                });
            }
        }
    }
});
    </script>
