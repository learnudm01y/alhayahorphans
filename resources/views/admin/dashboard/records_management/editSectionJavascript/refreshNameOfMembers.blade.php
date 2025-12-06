<script>
    document.addEventListener('DOMContentLoaded', function () {
        // تحديث قائمة الأشخاص مع رقم الهوية
        function updatePersonsList() {
            const mainPerson = document.querySelector('option[value="main"]');
            const firstName = document.querySelector('input[name="data_first_name"]').value;
            const familyName = document.querySelector('input[name="data_family_name"]').value;
            const idNumber = document.querySelector('input[name="data_id_number"]').value;

            if (mainPerson) {
                mainPerson.textContent = (firstName && familyName) ? `${firstName} ${familyName}` : 'صاحب الملف';
                if (idNumber) {
                    mainPerson.setAttribute('data-id-number', idNumber);
                }
            }

            // 🆕 تحديث معلومات الأب والأم المتوفين
            const fileIdNumber = document.getElementById('main_file_id_number')?.value ||
                                document.querySelector('input[name="file_id_number"]')?.value || '';

            // تحديث الأب المتوفى
            const fatherOption = document.querySelector('option[value="deceased_father"]');
            if (fatherOption) {
                const fatherId = document.querySelector('input[name="father_id"]')?.value || '';
                const fatherFirstName = document.querySelector('input[name="father_first_name"]')?.value || '';
                const fatherLastName = document.querySelector('input[name="father_last_name"]')?.value || '';

                fatherOption.setAttribute('data-id', fileIdNumber);
                if (fatherId) {
                    fatherOption.setAttribute('data-id-number', fatherId);
                }
                if (fatherFirstName || fatherLastName) {
                    fatherOption.textContent = `الأب المتوفى${fatherFirstName ? ' - ' + fatherFirstName + ' ' + fatherLastName : ''}`;
                }
            }

            // تحديث الأم المتوفية
            const motherOption = document.querySelector('option[value="deceased_mother"]');
            if (motherOption) {
                const motherId = document.querySelector('input[name="mother_id"]')?.value || '';
                const motherFirstName = document.querySelector('input[name="mother_first_name"]')?.value || '';
                const motherLastName = document.querySelector('input[name="mother_last_name"]')?.value || '';

                motherOption.setAttribute('data-id', fileIdNumber);
                if (motherId) {
                    motherOption.setAttribute('data-id-number', motherId);
                }
                if (motherFirstName || motherLastName) {
                    motherOption.textContent = `الأم المتوفية${motherFirstName ? ' - ' + motherFirstName + ' ' + motherLastName : ''}`;
                }
            }

            const familyMembersOptions = document.getElementById('family_members_options');
            familyMembersOptions.innerHTML = '';

            document.querySelectorAll('.family-member-form').forEach((form, index) => {
                const firstName = form.querySelector('input[name$="[first_name]"]')?.value || '';
                const lastName = form.querySelector('input[name$="[last_name]"]')?.value || '';
                const idNumber = form.querySelector('input[name$="[person_id]"]')?.value || '';
                // رقم الملف الخاص بالفرد من input[name$="[file_id]"]
                let fileId = form.querySelector('input[name$="[file_id]"]')?.value || '';
                // إذا لم يوجد رقم ملف للفرد، استخدم رقم الملف العام من الحقل المخفي الرئيسي
                if (!fileId) {
                    const mainFileId = document.getElementById('main_file_id_number')?.value || '';
                    fileId = mainFileId;
                }

                if (firstName && lastName) {
                    const option = document.createElement('option');
                    option.value = `family_${index}`;
                    option.textContent = `${firstName} ${lastName}`;
                    if (idNumber) option.setAttribute('data-id-number', idNumber);
                    if (fileId) {
                        option.setAttribute('data-file-id', fileId);
                        option.setAttribute('data-id', fileId); // للتوافق مع الكود السابق
                    }
                    familyMembersOptions.appendChild(option);
                }
            });

            // عند تغيير الشخص المختار، حدّث رقم الملف في حقل document_id بوراثة رقم الملف من بوابة أفراد الأسرة أو من العام
            const personSelector = document.getElementById('person_selector');
            if (personSelector) {
                const selectedOption = personSelector.options[personSelector.selectedIndex];
                let fileId = selectedOption ? (selectedOption.getAttribute('data-file-id') || '') : '';
                // إذا لم يوجد رقم ملف في الخيار، استخدم رقم الملف العام من الحقل المخفي الرئيسي
                if (!fileId) {
                    const mainFileId = document.getElementById('main_file_id_number')?.value || '';
                    fileId = mainFileId;
                }
                const documentIdInput = document.getElementById('document_id');
                if (documentIdInput) documentIdInput.value = fileId;
            }
        }

        // تنسيق رقم الملف
        function padFileIdNumber(fileId, length = 6) {
            fileId = String(fileId || '');
            return fileId.padStart(length, '0');
        }

        // تحديث رقم الهوية أو الاسم
        document.addEventListener('input', function (e) {
            if (e.target.name && e.target.name.match(/^family_members\[\d+\]\[person_id\]$/)) {
                const match = e.target.name.match(/^family_members\[(\d+)\]\[person_id\]$/);
                if (match) {
                    const index = match[1];
                    const newId = e.target.value;
                    const option = document.querySelector(`#person_selector option[value="family_${index}"]`);
                    if (option) {
                        option.setAttribute('data-id-number', newId);
                    }
                }
            }

            // 🆕 تحديث معلومات المتوفين عند تغيير حقولهم
            if (
                e.target.name === 'father_id' ||
                e.target.name === 'father_first_name' ||
                e.target.name === 'father_last_name' ||
                e.target.name === 'mother_id' ||
                e.target.name === 'mother_first_name' ||
                e.target.name === 'mother_last_name'
            ) {
                updatePersonsList();
            }

            // تحديث الأسماء عند التعديل
            if (
                e.target.name === 'data_first_name' ||
                e.target.name === 'data_family_name' ||
                e.target.name.includes('[first_name]') ||
                e.target.name.includes('[last_name]')
            ) {
                updatePersonsList();
            }
        });

        // تغيير الشخص في المرفقات
        const personSelector = document.getElementById('person_selector');
        if (personSelector) {
            personSelector.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];
                const idNumber = selectedOption.getAttribute('data-id-number') || '';
                const fileId = selectedOption.getAttribute('data-file-id') || '';
                const hiddenInput = document.getElementById('identity_number');
                if (hiddenInput) hiddenInput.value = idNumber;
                // تحديث رقم الملف في الحقل الخاص به
                const documentIdInput = document.getElementById('document_id');
                if (documentIdInput) documentIdInput.value = fileId;
            });
        }

        // إعادة بناء القائمة بعد إضافة فرد أو حذف فرد
        function safeUpdatePersonsList() {
            try {
                updatePersonsList();
            } catch (e) {
                // منع توقف الصفحة عند عدم وجود أي card
                console.warn('[updatePersonsList error]', e);
            }
        }

        if (!window.addFamilyMemberBtnInitialized) {
            const addFamilyMemberBtn = document.getElementById('addFamilyMember');
            if (addFamilyMemberBtn) {
                addFamilyMemberBtn.onclick = null;
                addFamilyMemberBtn.removeAttribute('onclick');
                addFamilyMemberBtn.addEventListener('click', function () {
                    setTimeout(safeUpdatePersonsList, 100);
                });
                window.addFamilyMemberBtnInitialized = true;
            }
        }

        // مراقبة حذف الكارد (زر x أو حذف فرد) لتحديث القائمة بأمان
        document.body.addEventListener('click', function(e) {
            if (e.target.closest('.delete-family-member-x') || e.target.closest('.delete-family-member')) {
                setTimeout(safeUpdatePersonsList, 150);
            }
        });

        // تحديث القائمة عند تحميل الصفحة
        safeUpdatePersonsList();
    });
</script>
