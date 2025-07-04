// إبراز الحقل غير الصحيح وتوجيه المستخدم للتبويب المناسب
function highlightInvalidField(input) {
    if (!input) return;
    input.style.border = '2px solid red';
    let foundTab = false;
    let tabBtn = null;
    // ابحث عن أقرب تبويب أو بوابة
    if (input.name === 'data_id_number' || (input.closest && input.closest('#basic'))) {
        tabBtn = document.getElementById('basic-tab');
    } else if (input.name === 'mother_id' || (input.closest && input.closest('#motherInfoSection'))) {
        tabBtn = document.getElementById('deceased-tab');
    } else if (input.name === 'father_id' || (input.closest && input.closest('#deceased'))) {
        tabBtn = document.getElementById('deceased-tab');
    } else if ((input.name && input.name.startsWith('family_members')) || (input.closest && input.closest('.family-member-form'))) {
        tabBtn = document.getElementById('family-members-tab');
    }
    if (tabBtn) {
        // إذا كان التبويب غير نشط، فعّل التبويب ثم انتظر حتى يظهر الحقل ثم ركز عليه
        if (!tabBtn.classList.contains('active')) {
            tabBtn.click();
            foundTab = true;
            setTimeout(function() { if (input && typeof input.focus === 'function') input.focus(); }, 400);
        } else {
            foundTab = true;
            setTimeout(function() { if (input && typeof input.focus === 'function') input.focus(); }, 200);
        }
    } else {
        setTimeout(function() { if (input && typeof input.focus === 'function') input.focus(); }, 200);
    }
    // إذا لم يتم العثور على تبويب مناسب، أظهر رسالة خطأ للمستخدم
    if (!foundTab) {
        // دعم SweetAlert2 أو SweetAlert الكلاسيكي
        if (typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function') {
            try {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ في الحقول',
                    html: 'حدثت مشكلة في أحد الحقول المطلوبة:<br><b>' + (input.name || 'حقل غير معروف') + '</b><br>يرجى التأكد من إظهاره أو تعبئته بشكل صحيح.'
                });
            } catch (e) {
                // fallback للنسخة الكلاسيكية
                if (typeof swal === 'function') {
                    swal('خطأ في الحقول', 'حدثت مشكلة في أحد الحقول المطلوبة: ' + (input.name || 'حقل غير معروف'), 'error');
                } else {
                    alert('حدثت مشكلة في أحد الحقول المطلوبة: ' + (input.name || 'حقل غير معروف'));
                }
            }
        } else if (typeof swal === 'function') {
            swal('خطأ في الحقول', 'حدثت مشكلة في أحد الحقول المطلوبة: ' + (input.name || 'حقل غير معروف'), 'error');
        } else {
            alert('حدثت مشكلة في أحد الحقول المطلوبة: ' + (input.name || 'حقل غير معروف'));
        }
    }
}
