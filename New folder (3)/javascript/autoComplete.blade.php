
@push('scriptsCodeUserRegistration')
    <script>
        // تحديث أفراد الأسرة تلقائياً حسب اختيار القسم
        document.addEventListener('DOMContentLoaded', function() {
            const sectionSelect = document.querySelector('select[name="data_section_id"]');
            const addFamilyMemberBtn = document.getElementById('addFamilyMember');

            function fillFamilyMemberFields(template) {
                const sectionValue = sectionSelect.value;
                if (sectionValue === '1') {
                    // قسم الأيتام: خذ من الأب المتوفي
                    const fatherFirst = document.querySelector('input[name="father_first_name"]')?.value || '';
                    const fatherSecond = document.querySelector('input[name="father_second_name"]')?.value || '';
                    const fatherLast = document.querySelector('input[name="father_last_name"]')?.value || '';
                    template.querySelector('input[name*="[second_name]"]').value = fatherFirst;
                    template.querySelector('input[name*="[third_name]"]').value = fatherSecond;
                    template.querySelector('input[name*="[last_name]"]').value = fatherLast;
                } else {
                    // غير الأيتام: خذ من البيانات الأساسية
                    const dataFirst = document.querySelector('input[name="data_first_name"]')?.value || '';
                    const dataFather = document.querySelector('input[name="data_father_name"]')?.value || '';
                    const dataFamily = document.querySelector('input[name="data_family_name"]')?.value || '';
                    template.querySelector('input[name*="[second_name]"]').value = dataFirst;
                    template.querySelector('input[name*="[third_name]"]').value = dataFather;
                    template.querySelector('input[name*="[last_name]"]').value = dataFamily;
                }
            }

            // عند إضافة فرد جديد
            addFamilyMemberBtn.addEventListener('click', function() {
                setTimeout(() => {
                    const templates = document.querySelectorAll('.family-member-form');
                    const lastTemplate = templates[templates.length - 1];
                    fillFamilyMemberFields(lastTemplate);
                }, 50);
            });

            // عند تغيير القسم، حدث أفراد الأسرة الحاليين
            sectionSelect.addEventListener('change', function() {
                document.querySelectorAll('.family-member-form').forEach(template => {
                    fillFamilyMemberFields(template);
                });
            });
        });
    </script>
@endpush
