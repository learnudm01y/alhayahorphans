<script>
    document.addEventListener('DOMContentLoaded', () => {
      const modalEl     = document.getElementById('cropperModal');
      const imageEl     = document.getElementById('cropperImage');
      const saveBtn     = document.getElementById('cropperSaveBtn');
      let   cropper     = null;
      const cropperModal = new bootstrap.Modal(modalEl);

      if (!modalEl || !imageEl || !saveBtn) {
        console.warn('❗ Cropper modal elements missing:', { modalEl, imageEl, saveBtn });
        return;
      }

      // 1️⃣ رصد تغيير أي حقل input[name="avatar"]
      document.body.addEventListener('change', e => {
        if (!e.target.matches('input[name="avatar"]')) return;
        const file = e.target.files[0];
        if (!file) return;

        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/heic', 'image/heif'];
        const allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic', 'heif'];
        const ext = file.name.split('.').pop().toLowerCase();

        if (!allowedTypes.includes(file.type) && !allowedExts.includes(ext)) {
          Swal.fire({
            icon: 'warning',
            title: 'نوع ملف غير مدعوم',
            text: 'الرجاء اختيار صورة (JPG, PNG, GIF, WebP, BMP, HEIC)'
          });
          e.target.value = '';
          return;
        }

        if (file.size > 10 * 1024 * 1024) {
          Swal.fire({
            icon: 'warning',
            title: 'الملف كبير جداً',
            text: 'الحد الأقصى لحجم الصورة 10 ميجابايت'
          });
          e.target.value = '';
          return;
        }

        const reader = new FileReader();
        reader.onload = ev => {
          imageEl.src = ev.target.result;
          cropperModal.show();
        };
        reader.readAsDataURL(file);
      });

      // 2️⃣ إنشاء Cropper بعد ظهور المودال
      modalEl.addEventListener('shown.bs.modal', () => {
        if (cropper) cropper.destroy();
        cropper = new Cropper(imageEl, {
          aspectRatio:    1,
          viewMode:       1,
          autoCropArea:   1,
          dragMode:       'move',
          background:     false,
          responsive:     true
        });
      });

      // 3️⃣ حفظ الصورة المقطوعة ورفعها للسيرفر
      saveBtn.addEventListener('click', () => {
        if (!cropper) return console.warn('⚠️ Cropper not ready');
        const canvas = cropper.getCroppedCanvas({
          width:       300,
          height:      300,
          fillColor:   'transparent'
        });

        canvas.toBlob(blob => {
          if (!blob) return console.error('❌ Failed to create Blob');
          const fd = new FormData();
          fd.append('avatar', blob, 'avatar.png');

          fetch('{{ route("admin.updateAvatar") }}', {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body:    fd
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              const newUrl = `${data.avatar}?v=${Date.now()}`;
              // حدّث صور .user-avatar
              document.querySelectorAll('img.user-avatar').forEach(img => {
                img.src = newUrl;
              });
              // حدّث معاينة الـ input
              document.querySelectorAll('.image-input-wrapper').forEach(w => {
                w.style.backgroundImage = `url(${newUrl})`;
              });
              Swal.fire({
                icon:               'success',
                title:              'تم تحديث الصورة!',
                timer:              1500,
                showConfirmButton:  false
              });
            }
            cropperModal.hide();
          })
          .catch(err => {
            console.error(err);
            Swal.fire({
              icon:  'error',
              title: 'فشل رفع الصورة',
              text:  'حاول مرة أخرى'
            });
          });
        }, 'image/png');
      });

      // 4️⃣ تنظيف عند إغلاق المودال
      modalEl.addEventListener('hidden.bs.modal', () => {
        if (cropper) {
          cropper.destroy();
          cropper = null;
        }
        imageEl.src = '';
      });
    });
    </script>



