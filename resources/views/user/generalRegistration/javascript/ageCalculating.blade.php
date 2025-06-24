@push('scriptsCodeUserRegistration')
    {{-- حساب العمر تلقائيا --}}
    <script>
        // حساب العمر تلقائياً عند تغيير تاريخ الميلاد
        document.addEventListener('input', function(e) {
            if (e.target.name.includes('[person_birth_date]')) {
                calculateAge(e.target);
            }
        });

        // دالة حساب العمر
        function calculateAge(inputElement) {
            const birthDate = new Date(inputElement.value);
            if (isNaN(birthDate.getTime())) return; // تحقق من صحة التاريخ

            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();

            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }

            const formIndex = inputElement.name.match(/\[(\d+)\]/)[1];
            const ageInput = document.querySelector(`input[name="family_members[${formIndex}][person_age]"]`);
            if (ageInput) {
                ageInput.value = age;
            }
        }
    </script>
@endpush
