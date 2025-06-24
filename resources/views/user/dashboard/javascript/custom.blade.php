<script>
    // تهيئة المكونات
    function initGlobalComponents() {
        // تهيئة tooltips
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el);
        });

        // تهيئة select2
        $('[data-control="select2"]').select2({
            width: '100%',
            templateResult: function(state) {
                if (!state.id) return state.text;
                const flagUrl = $(state.element).data('kt-flag');
                if (flagUrl) {
                    return $(`<span style="display: flex; align-items: center;">
                        <img src="${flagUrl}" class="img-flag" />
                        ${state.text}
                    </span>`);
                }
                return state.text;
            },
            templateSelection: function(state) {
                if (!state.id) return state.text;
                const flagUrl = $(state.element).data('kt-flag');
                if (flagUrl) {
                    return $(`<span style="display: flex; align-items: center;">
                        <img src="${flagUrl}" class="img-flag" />
                        ${state.text}
                    </span>`);
                }
                return state.text;
            },
            escapeMarkup: m => m
        });

        // تهيئة مكون image-input الخاص بـ Metronic
        if (typeof KTImageInput !== 'undefined') {
            document.querySelectorAll('[data-kt-image-input="true"]').forEach(el => {
                new KTImageInput(el);
            });
        }

        // Set csrf at ajax header
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
   // زر تحديث البيانات
   $(document).off('click', '#kt_account_profile_details_submit'); // إزالة أي تكرار قديم
    $(document).on('click', '#kt_account_profile_details_submit', function (e) {
        e.preventDefault();

        var form = document.getElementById('kt_account_profile_details_form');
        var formData = new FormData(form);

        $.ajax({
            url: '{{ route("user.updateProfile") }}',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                Swal.fire({
            text: "تم حفظ البيانات بنجاح!",
            icon: "success",
            confirmButtonText: "تم"
        });
                const timestamp = new Date().getTime();
             document.querySelectorAll('img.user-avatar').forEach(img => {
            const baseUrl = img.src.split('?')[0]; // احذف أي كاش قديم
            img.src = `${baseUrl}?v=${timestamp}`; // أضف متغير يمنع الكاش
        });

    },
    error: function () {
        Swal.fire({
            text: "حدث خطأ أثناء الإرسال!",
            icon: "error",
            confirmButtonText: "حسنًا"
        });
            },
            error: function (xhr, status, error) {
                alert('حدث خطأ أثناء الإرسال. الرجاء المحاولة لاحقاً.');
                console.error(xhr.responseText);
            }
        });
    });
        $(document).on('click', '#kt_signin_email_button button', function () {
        $('#kt_signin_email').addClass('d-none');
        $('#kt_signin_email_edit').removeClass('d-none');
    });
    $(document).on('click', '#kt_signin_cancel', function () {
        $('#kt_signin_email_edit').addClass('d-none');
        $('#kt_signin_email').removeClass('d-none');
    });
    $(document).on('click', '#kt_signin_password_button button', function () {
    $('#kt_signin_password').addClass('d-none');
    $('#kt_signin_password_edit').removeClass('d-none');
});
$(document).on('click', '#kt_password_cancel', function () {
    $('#kt_signin_password_edit').addClass('d-none');
    $('#kt_signin_password').removeClass('d-none');
});
// update the email of profile
$(document).off('click', '#kt_signin_submit');
$(document).on('click', '#kt_signin_submit', function (e) {
    e.preventDefault();
    let form = document.getElementById('kt_signin_change_email');
    let formData = new FormData(form);
    let oldEmail = $('#kt_signin_email .text-gray-600').text(); // التقاط البريد القديم من الـ DOM

    $.ajax({
        url: '{{ route("user.updateEmail") }}',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            if (response.success) {
                let newEmail = formData.get('emailaddress');

                // تحديث النص المعروض للبريد الجديد
                $('#kt_signin_email .text-gray-600').text(newEmail);

                Swal.fire({
                    icon: 'success',
                    title: 'تم تحديث البريد الإلكتروني',
                    html: `
                        <p><strong>البريد السابق:</strong> ${oldEmail}</p>
                        <p><strong>البريد الجديد:</strong> ${newEmail}</p>
                    `,
                    confirmButtonText: 'موافق'
                });

                // إظهار عرض البريد وإخفاء النموذج
                $('#kt_signin_email_edit').addClass('d-none');
                $('#kt_signin_email').removeClass('d-none');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'فشل التحديث',
                    text: response.message || 'حدث خطأ أثناء التحديث'
                });
            }
        },
        error: function () {
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: 'تأكد من إدخال البيانات بشكل صحيح'
            });
        }
    });
});
// update the password of profile
$(document).off('click', '#kt_password_submit');
$(document).on('click', '#kt_password_submit', function (e) {
    e.preventDefault();
    let form = document.getElementById('kt_signin_change_password');
    let formData = new FormData(form);

    $.ajax({
        url: '{{ route("user.updatePassword") }}',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'تم التحديث',
                    text: 'تم تغيير كلمة المرور بنجاح',
                    confirmButtonText: 'حسنًا'
                });
                $('#kt_signin_password_edit').addClass('d-none');
                $('#kt_signin_password').removeClass('d-none');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'فشل',
                    text: response.message || 'فشل تغيير كلمة المرور'
                });
            }
        },
        error: function () {
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: 'تأكد من إدخال البيانات بشكل صحيح'
            });
        }
    });
});

cropperSaveBtn.addEventListener('click', function () {
    if (cropper) {
        const canvas = cropper.getCroppedCanvas({
            width: 300,
            height: 300,
            imageSmoothingQuality: 'high'
        });

        canvas.toBlob(function (blob) {
            // إنشاء FormData لإرسالها
            const formData = new FormData();
            formData.append('avatar', blob, 'avatar.jpg');

            // إرسال الطلب عبر AJAX
            $.ajax({
                url: '{{ route("user.updateAvatar") }}', // غيره حسب الراوت الخاص بك
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        // ✅ تحديث صور الواجهة
                        const timestamp = new Date().getTime();
                        document.querySelectorAll('img.user-avatar').forEach(img => {
                            const src = img.getAttribute('src').split('?')[0];
                            img.setAttribute('src', `${src}?v=${timestamp}`);
                        });

                        Swal.fire({
                            icon: 'success',
                            text: 'تم تحديث الصورة بنجاح'
                        });

                        cropperModal.hide();
                        cropper.destroy();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            text: response.message || 'فشل رفع الصورة'
                        });
                    }
                },
                error: function () {
                    Swal.fire({
                        icon: 'error',
                        text: 'حدث خطأ أثناء رفع الصورة'
                    });
                }
            });
        }, 'image/jpeg');
    }
});

    }

    // تنفيذ التهيئة عند التحميل الأول
    document.addEventListener('DOMContentLoaded', initGlobalComponents);
</script>
