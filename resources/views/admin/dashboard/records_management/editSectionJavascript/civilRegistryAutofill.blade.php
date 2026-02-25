<script>
/**
 * جلب البيانات تلقائياً من السجل المدني في جميع البوابات الإدارية
 * (البيانات الأساسية - المتوفين - أفراد الأسرة)
 */
document.addEventListener('DOMContentLoaded', function () {

    // ======================================================
    // دالة مشتركة لجلب البيانات من السجل المدني
    // ======================================================
    window.fetchFromCivilRegistry = function (idNumber, onSuccess, onNotFound, onError) {
        if (!idNumber || idNumber.length < 9) return;

        fetch(`/api/civil-registry/search-by-id?search_text=${encodeURIComponent(idNumber)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    onSuccess(data.data[0]);
                } else {
                    if (onNotFound) onNotFound();
                }
            })
            .catch(error => {
                console.error('[السجل المدني] خطأ في الجلب:', error);
                if (onError) onError(error);
            });
    };

    // ======================================================
    // دالة تحويل تاريخ الميلاد من صيغة السجل المدني لصيغة HTML
    // ======================================================
    function formatBirthDate(ciDate) {
        if (!ciDate) return '';
        // قد يكون بصيغة YYYY-MM-DD أو YYYY/MM/DD أو timestamp
        if (typeof ciDate === 'number' || /^\d{10,13}$/.test(String(ciDate))) {
            const d = new Date(typeof ciDate === 'number' ? ciDate * 1000 : parseInt(ciDate));
            if (!isNaN(d)) return d.toISOString().split('T')[0];
        }
        const str = String(ciDate).replace(/\//g, '-').trim();
        const match = str.match(/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/);
        if (match) {
            const y = match[1], m = match[2].padStart(2, '0'), d = match[3].padStart(2, '0');
            return `${y}-${m}-${d}`;
        }
        return str.split('T')[0] || '';
    }

    // ======================================================
    // 1. البيانات الأساسية: data_id_number
    // ======================================================
    function setupBasicDataLookup() {
        const idInput = document.querySelector('input[name="data_id_number"]');
        if (!idInput || idInput.dataset.civilLookupAttached) return;
        idInput.dataset.civilLookupAttached = 'true';

        // أضف مؤشر حالة
        let statusSpan = idInput.parentElement.querySelector('.civil-lookup-status');
        if (!statusSpan) {
            statusSpan = document.createElement('span');
            statusSpan.className = 'civil-lookup-status input-group-text';
            statusSpan.style.cssText = 'display:none; cursor:pointer;';
            statusSpan.innerHTML = '<span class="spinner-border spinner-border-sm text-primary" role="status"></span>';

            // ربط input بـ input-group إذا لم يكن
            if (!idInput.parentElement.classList.contains('input-group')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'input-group';
                idInput.parentElement.insertBefore(wrapper, idInput);
                wrapper.appendChild(idInput);
                wrapper.appendChild(statusSpan);
            } else {
                idInput.parentElement.appendChild(statusSpan);
            }
        }

        let debounceTimer;

        function doLookup() {
            const id = idInput.value.trim();
            if (id.length < 9) return;

            statusSpan.style.display = 'flex';

            window.fetchFromCivilRegistry(id,
                function (person) {
                    statusSpan.style.display = 'none';

                    // الاسم الأول
                    const fn = document.querySelector('[name="data_first_name"]');
                    if (fn && person.CI_FIRST_ARB) { fn.value = person.CI_FIRST_ARB; fn.classList.add('is-valid'); }

                    // اسم الأب (الاسم الثاني)
                    const sn = document.querySelector('[name="data_father_name"]');
                    if (sn && person.CI_FATHER_ARB) { sn.value = person.CI_FATHER_ARB; sn.classList.add('is-valid'); }

                    // اسم الجد (الاسم الثالث)
                    const tn = document.querySelector('[name="data_grand_father_name"]');
                    if (tn && person.CI_GRAND_FATHER_ARB) { tn.value = person.CI_GRAND_FATHER_ARB; tn.classList.add('is-valid'); }

                    // اسم العائلة
                    const ln = document.querySelector('[name="data_family_name"]');
                    if (ln && person.CI_FAMILY_ARB) { ln.value = person.CI_FAMILY_ARB; ln.classList.add('is-valid'); }

                    // تاريخ الميلاد
                    const bd = document.querySelector('[name="data_birth_date"]');
                    if (bd && person.CI_BIRTH_DT) { bd.value = formatBirthDate(person.CI_BIRTH_DT); bd.classList.add('is-valid'); }

                    // الجنس
                    const gs = document.querySelector('[name="data_gender"]');
                    if (gs && person.CI_SEX_CD) {
                        gs.value = String(person.CI_SEX_CD);
                        gs.classList.add('is-valid');
                        gs.style.backgroundColor = '#e8f5e9';
                    }

                    idInput.classList.add('is-valid');
                    idInput.classList.remove('is-invalid');
                },
                function () {
                    statusSpan.style.display = 'none';
                    idInput.classList.remove('is-valid');
                },
                function () { statusSpan.style.display = 'none'; }
            );
        }

        idInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(doLookup, 600);
        });

        idInput.addEventListener('blur', function () {
            clearTimeout(debounceTimer);
            doLookup();
        });
    }

    // ======================================================
    // 2. المتوفين: father_id و mother_id
    // ======================================================
    function setupDeceasedLookup(namePrefix, idFieldName) {
        const idInput = document.querySelector(`input[name="${idFieldName}"]`);
        if (!idInput || idInput.dataset.civilLookupAttached) return;
        idInput.dataset.civilLookupAttached = 'true';

        // أضف مؤشر حالة بجانب الحقل
        let statusSpan = null;
        const inputGroup = idInput.closest('.input-group');
        if (inputGroup) {
            statusSpan = inputGroup.querySelector('.civil-lookup-status');
            if (!statusSpan) {
                statusSpan = document.createElement('span');
                statusSpan.className = 'civil-lookup-status input-group-text';
                statusSpan.style.cssText = 'display:none;';
                statusSpan.innerHTML = '<span class="spinner-border spinner-border-sm text-primary" role="status"></span>';
                inputGroup.appendChild(statusSpan);
            }
        }

        let debounceTimer;

        function doLookup() {
            const id = idInput.value.trim();
            if (id.length < 9) return;

            if (statusSpan) statusSpan.style.display = 'flex';

            window.fetchFromCivilRegistry(id,
                function (person) {
                    if (statusSpan) statusSpan.style.display = 'none';

                    const container = idInput.closest('.card') || idInput.closest('.row') || document;

                    const fn = container.querySelector(`[name="${namePrefix}_first_name"]`);
                    if (fn && person.CI_FIRST_ARB) { fn.value = person.CI_FIRST_ARB; fn.classList.add('is-valid'); }

                    const sn = container.querySelector(`[name="${namePrefix}_second_name"]`);
                    if (sn && person.CI_FATHER_ARB) { sn.value = person.CI_FATHER_ARB; sn.classList.add('is-valid'); }

                    const tn = container.querySelector(`[name="${namePrefix}_third_name"]`);
                    if (tn && person.CI_GRAND_FATHER_ARB) { tn.value = person.CI_GRAND_FATHER_ARB; tn.classList.add('is-valid'); }

                    const ln = container.querySelector(`[name="${namePrefix}_last_name"]`);
                    if (ln && person.CI_FAMILY_ARB) { ln.value = person.CI_FAMILY_ARB; ln.classList.add('is-valid'); }

                    idInput.classList.add('is-valid');
                    idInput.classList.remove('is-invalid');
                },
                function () {
                    if (statusSpan) statusSpan.style.display = 'none';
                    idInput.classList.remove('is-valid');
                },
                function () { if (statusSpan) statusSpan.style.display = 'none'; }
            );
        }

        idInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(doLookup, 600);
        });

        idInput.addEventListener('blur', function () {
            clearTimeout(debounceTimer);
            doLookup();
        });
    }

    // ======================================================
    // 3. المتوفين الإضافيين (additional_deceased)
    // ======================================================
    window.setupAdditionalDeceasedLookup = function (idInput, index) {
        if (!idInput || idInput.dataset.civilLookupAttached) return;
        idInput.dataset.civilLookupAttached = 'true';

        const inputGroup = idInput.closest('.input-group');
        let statusSpan = null;
        if (inputGroup) {
            statusSpan = inputGroup.querySelector('.civil-lookup-status');
            if (!statusSpan) {
                statusSpan = document.createElement('span');
                statusSpan.className = 'civil-lookup-status input-group-text';
                statusSpan.style.cssText = 'display:none;';
                statusSpan.innerHTML = '<span class="spinner-border spinner-border-sm text-primary" role="status"></span>';
                inputGroup.appendChild(statusSpan);
            }
        }

        let debounceTimer;

        function doLookup() {
            const id = idInput.value.trim();
            if (id.length < 9) return;

            if (statusSpan) statusSpan.style.display = 'flex';

            window.fetchFromCivilRegistry(id,
                function (person) {
                    if (statusSpan) statusSpan.style.display = 'none';

                    const container = idInput.closest('.additional-deceased-form') || idInput.closest('.card') || document;

                    const fn = container.querySelector(`[name="additional_deceased[${index}][first_name]"]`);
                    if (fn && person.CI_FIRST_ARB) { fn.value = person.CI_FIRST_ARB; fn.classList.add('is-valid'); }

                    const sn = container.querySelector(`[name="additional_deceased[${index}][second_name]"]`);
                    if (sn && person.CI_FATHER_ARB) { sn.value = person.CI_FATHER_ARB; sn.classList.add('is-valid'); }

                    const tn = container.querySelector(`[name="additional_deceased[${index}][third_name]"]`);
                    if (tn && person.CI_GRAND_FATHER_ARB) { tn.value = person.CI_GRAND_FATHER_ARB; tn.classList.add('is-valid'); }

                    const ln = container.querySelector(`[name="additional_deceased[${index}][last_name]"]`);
                    if (ln && person.CI_FAMILY_ARB) { ln.value = person.CI_FAMILY_ARB; ln.classList.add('is-valid'); }

                    idInput.classList.add('is-valid');
                    idInput.classList.remove('is-invalid');
                },
                function () {
                    if (statusSpan) statusSpan.style.display = 'none';
                    idInput.classList.remove('is-valid');
                },
                function () { if (statusSpan) statusSpan.style.display = 'none'; }
            );
        }

        idInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(doLookup, 600);
        });

        idInput.addEventListener('blur', function () {
            clearTimeout(debounceTimer);
            doLookup();
        });
    };

    // ======================================================
    // 4. أفراد الأسرة: person_id
    // ======================================================
    window.setupFamilyMemberLookup = function (idInput) {
        if (!idInput || idInput.dataset.civilLookupAttached) return;
        idInput.dataset.civilLookupAttached = 'true';

        // استخرج index من اسم الحقل family_members[N][person_id]
        const nameMatch = idInput.name.match(/family_members\[(\d+)\]/);
        if (!nameMatch) return;
        const idx = nameMatch[1];

        const inputGroup = idInput.closest('.input-group');
        let statusSpan = null;
        if (inputGroup) {
            statusSpan = inputGroup.querySelector('.civil-lookup-status');
            if (!statusSpan) {
                statusSpan = document.createElement('span');
                statusSpan.className = 'civil-lookup-status input-group-text';
                statusSpan.style.cssText = 'display:none;';
                statusSpan.innerHTML = '<span class="spinner-border spinner-border-sm text-primary" role="status"></span>';
                inputGroup.appendChild(statusSpan);
            }
        }

        let debounceTimer;

        function doLookup() {
            const id = idInput.value.trim();
            if (id.length < 9) return;

            if (statusSpan) statusSpan.style.display = 'flex';

            window.fetchFromCivilRegistry(id,
                function (person) {
                    if (statusSpan) statusSpan.style.display = 'none';

                    const form = idInput.closest('.family-member-form') || document;

                    const fn = form.querySelector(`[name="family_members[${idx}][first_name]"]`);
                    if (fn && person.CI_FIRST_ARB) { fn.value = person.CI_FIRST_ARB; fn.classList.add('is-valid'); }

                    const sn = form.querySelector(`[name="family_members[${idx}][second_name]"]`);
                    if (sn && person.CI_FATHER_ARB) { sn.value = person.CI_FATHER_ARB; sn.classList.add('is-valid'); }

                    const tn = form.querySelector(`[name="family_members[${idx}][third_name]"]`);
                    if (tn && person.CI_GRAND_FATHER_ARB) { tn.value = person.CI_GRAND_FATHER_ARB; tn.classList.add('is-valid'); }

                    const ln = form.querySelector(`[name="family_members[${idx}][last_name]"]`);
                    if (ln && person.CI_FAMILY_ARB) { ln.value = person.CI_FAMILY_ARB; ln.classList.add('is-valid'); }

                    // تاريخ الميلاد
                    const bd = form.querySelector(`[name="family_members[${idx}][person_birth_date]"]`);
                    if (bd && person.CI_BIRTH_DT) {
                        bd.value = formatBirthDate(person.CI_BIRTH_DT);
                        bd.classList.add('is-valid');
                        bd.dispatchEvent(new Event('input', { bubbles: true }));
                        bd.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    // الجنس
                    const gs = form.querySelector(`[name="family_members[${idx}][person_gender]"]`);
                    if (gs && person.CI_SEX_CD) {
                        gs.value = String(person.CI_SEX_CD);
                        gs.classList.add('is-valid');
                    }

                    idInput.classList.add('is-valid');
                    idInput.classList.remove('is-invalid');
                },
                function () {
                    if (statusSpan) statusSpan.style.display = 'none';
                    idInput.classList.remove('is-valid');
                },
                function () { if (statusSpan) statusSpan.style.display = 'none'; }
            );
        }

        idInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(doLookup, 600);
        });

        idInput.addEventListener('blur', function () {
            clearTimeout(debounceTimer);
            doLookup();
        });

        // إذا كان الحقل يحتوي على قيمة مسبقة من قاعدة البيانات، اجلب البيانات فوراً
        if (idInput.value.trim().length >= 9) {
            doLookup();
        }
    };

    // ======================================================
    // تطبيق الربط على جميع الحقول الموجودة عند تحميل الصفحة
    // ======================================================
    function attachAllLookups() {
        // البيانات الأساسية
        setupBasicDataLookup();

        // المتوفين (أب + أم)
        setupDeceasedLookup('father', 'father_id');
        setupDeceasedLookup('mother', 'mother_id');

        // أفراد الأسرة
        document.querySelectorAll('input[name^="family_members"][name$="[person_id]"]').forEach(function (inp) {
            // أضف input-group wrapper إذا لم يكن موجوداً
            if (!inp.closest('.input-group')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'input-group';
                inp.parentElement.insertBefore(wrapper, inp);
                wrapper.appendChild(inp);
            }
            setupFamilyMemberLookup(inp);
        });
    }

    attachAllLookups();

    // ربط الحقول الجديدة عند إضافة أفراد أسرة ديناميكياً
    const addFamilyMemberBtn = document.getElementById('addFamilyMember');
    if (addFamilyMemberBtn) {
        addFamilyMemberBtn.addEventListener('click', function () {
            setTimeout(function () {
                document.querySelectorAll('input[name^="family_members"][name$="[person_id]"]').forEach(function (inp) {
                    if (!inp.dataset.civilLookupAttached) {
                        if (!inp.closest('.input-group')) {
                            const wrapper = document.createElement('div');
                            wrapper.className = 'input-group';
                            inp.parentElement.insertBefore(wrapper, inp);
                            wrapper.appendChild(inp);
                        }
                        setupFamilyMemberLookup(inp);
                    }
                });
            }, 200);
        });
    }

});
</script>
