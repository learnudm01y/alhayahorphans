@push('scriptsCodeUserRegistration')
    <script>
        // حساب العمر تلقائياً عند تغيير تاريخ الميلاد في نماذج أفراد الأسرة فقط
        document.addEventListener('input', function(e) {
            if (e.target.name && e.target.name.match(/^family_members\[\d+\]\[person_birth_date\]$/)) {
                window.calculateAge(e.target);
            }
        });

        // دالة حساب العمر خاصة بأفراد الأسرة
        window.calculateAge = function(inputElement) {
            const birthDate = new Date(inputElement.value);
            if (isNaN(birthDate.getTime())) return;
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            // استخراج الفهرس الصحيح من اسم الحقل
            const match = inputElement.name.match(/family_members\[(\d+)\]/);
            if (!match) return;
            const formIndex = match[1];
            // ابحث عن الحقل داخل نفس النموذج فقط
            const form = inputElement.closest('.family-member-form');
            if (!form) return;
            const ageInput = form.querySelector(`input[name="family_members[${formIndex}][person_age]"]`);
            if (ageInput) {
                ageInput.value = age;
            }
        }
    </script>
@endpush
