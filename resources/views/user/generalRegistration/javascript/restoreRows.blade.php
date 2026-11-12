@php
    // طبقة تطبيق مشتركة: تستعملها مسودة ما قبل الإرسال (restoreOldInput) بعد الرفض،
    // ومسودة المتصفح (draftRestore) عند انقطاع الإنترنت أو إعادة الفتح.
    // إعادة البناء تستخدم دوال الصفحة الأصلية حتى تبقى الفهارس ومناطق الرفع والمستمعات صحيحة.
@endphp
<script>
    window.RegRows = (function () {
        // تعبئة حقل واحد وتفعيل الأحداث المرتبطة به (المحافظة، الأعداد، بوابة الوثيقة، العمر)
        function fill(scope, selector, value) {
            if (value === null || value === undefined || typeof value === 'object' || String(value) === '') {
                return;
            }
            const field = scope.querySelector(selector);
            if (!field || field.type === 'file') {
                return;
            }
            field.value = String(value);
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function fillFamilyCard(card, index, member) {
            const prefix = 'family_members[' + index + ']';
            fill(card, 'input[name="' + prefix + '[person_id]"]', member.person_id);
            fill(card, 'input[name="' + prefix + '[first_name]"]', member.first_name);
            fill(card, 'input[name="' + prefix + '[second_name]"]', member.second_name);
            fill(card, 'input[name="' + prefix + '[third_name]"]', member.third_name);
            fill(card, 'input[name="' + prefix + '[last_name]"]', member.last_name);
            fill(card, 'input[name="' + prefix + '[person_birth_date]"]', member.person_birth_date);
            fill(card, 'select[name="' + prefix + '[person_gender]"]', member.person_gender);
            fill(card, 'select[name="' + prefix + '[person_health_status]"]', member.person_health_status);
            fill(card, 'textarea[name="' + prefix + '[person_note]"]', member.person_note);
        }

        // المفاتيح الأصلية -> مفاتيح البطاقات الفعلية، وnull إذا تعذر إنشاء البطاقة.
        // يجب أن يستعمل draftRestore هذه الخريطة للوثائق دون تعديل مفاتيح المسودة الأصلية.
        function applyFamilyMembers(list) {
            const selector = '#familyMembersContainer .family-member-form:not([data-member-index="template"])';
            const cards = Array.prototype.slice.call(document.querySelectorAll(selector));
            const used = new Set();
            const mapping = {};
            // نحجز البطاقات الموجودة أولاً، فلا تُستعمل بطاقة أُنشئت للتو لفرد آخر.
            const rows = (list || []).map(function (member, index) {
                const sourceIndex = /^\d+$/.test(member.rowIndex) ? String(member.rowIndex) : String(index);
                const card = cards.find(function (item) {
                    return !used.has(item) && item.getAttribute('data-restored-row-index') === sourceIndex;
                }) || cards.find(function (item) {
                    return !used.has(item) && !item.hasAttribute('data-restored-row-index')
                        && item.getAttribute('data-member-index') === sourceIndex;
                });
                if (card) used.add(card);
                mapping['family_' + sourceIndex] = null;

                return { person: member, sourceIndex: sourceIndex, card: card };
            });
            rows.forEach(function (row) {
                if (!row.card && typeof window.addFamilyMember === 'function') {
                    const before = Array.prototype.slice.call(document.querySelectorAll(selector));
                    window.addFamilyMember();
                    row.card = Array.prototype.find.call(document.querySelectorAll(selector), function (card) {
                        return before.indexOf(card) === -1;
                    });
                }
                if (!row.card) return;
                row.card.setAttribute('data-restored-row-index', row.sourceIndex);
                fillFamilyCard(row.card, row.card.getAttribute('data-member-index'), row.person);
            });
            // إضافة فرد قد تعيد ترقيم جميع البطاقات، لذا نحسب الخريطة بعد اكتمال الإضافات.
            rows.forEach(function (row) {
                const duplicateSource = rows.some(function (other) {
                    return other !== row && other.sourceIndex === row.sourceIndex;
                });
                if (row.card && !duplicateSource) {
                    mapping['family_' + row.sourceIndex] = 'family_' + row.card.getAttribute('data-member-index');
                }
            });

            return mapping;
        }

        // العداد الخاص بالمكوّن لا يساوي دائماً فهرس المصفوفة بعد الحذف؛ نقرأ البطاقة الجديدة نفسها.
        function applyAdditionalDeceased(list) {
            const addDeceasedButton = document.getElementById('addAnotherDeceasedBtn');
            const selector = '.additional-deceased-form';
            const cards = Array.prototype.slice.call(document.querySelectorAll(selector));
            const used = new Set();
            const mapping = {};
            const rows = (list || []).map(function (person, index) {
                const sourceIndex = /^\d+$/.test(person.rowIndex) ? String(person.rowIndex) : String(index);
                const card = cards.find(function (item) {
                    return !used.has(item) && item.getAttribute('data-restored-row-index') === sourceIndex;
                }) || cards.find(function (item) {
                    return !used.has(item) && !item.hasAttribute('data-restored-row-index')
                        && item.getAttribute('data-index') === sourceIndex;
                });
                if (card) used.add(card);
                mapping['additional_deceased_' + sourceIndex] = null;

                return { person: person, sourceIndex: sourceIndex, card: card };
            });
            rows.forEach(function (row) {
                if (!row.card && addDeceasedButton) {
                    const before = Array.prototype.slice.call(document.querySelectorAll(selector));
                    addDeceasedButton.click();
                    row.card = Array.prototype.find.call(document.querySelectorAll(selector), function (card) {
                        return before.indexOf(card) === -1;
                    });
                }
                if (!row.card) return;
                row.card.setAttribute('data-restored-row-index', row.sourceIndex);
                const person = row.person;
                fill(row.card, 'input[name$="[id_number]"]', person.id_number);
                fill(row.card, 'input[name$="[first_name]"]', person.first_name);
                fill(row.card, 'input[name$="[second_name]"]', person.second_name);
                fill(row.card, 'input[name$="[third_name]"]', person.third_name);
                fill(row.card, 'input[name$="[last_name]"]', person.last_name);
                fill(row.card, 'select[name$="[relationship]"]', person.relationship);
                fill(row.card, 'input[name$="[death_date]"]', person.death_date);
                fill(row.card, 'select[name$="[death_reason]"]', person.death_reason);
            });
            rows.forEach(function (row) {
                if (!row.card) return;
                const actualIndex = row.card.getAttribute('data-index');
                // قد يعيد المكوّن استعمال فهرس موجود بعد الحذف؛ لا نربط الوثائق بمنطقة ملتبسة.
                const matchingCards = Array.prototype.filter.call(document.querySelectorAll(selector), function (card) {
                    return card.getAttribute('data-index') === actualIndex;
                });
                const duplicateSource = rows.some(function (other) {
                    return other !== row && other.sourceIndex === row.sourceIndex;
                });
                if (matchingCards.length === 1 && !duplicateSource) {
                    mapping['additional_deceased_' + row.sourceIndex] = 'additional_deceased_' + actualIndex;
                }
            });

            return mapping;
        }

        function showDeceasedMother() {
            const toggleMotherButton = document.getElementById('toggleMotherInfo');
            const motherInfoSection = document.getElementById('motherInfoSection');
            if (toggleMotherButton) {
                toggleMotherButton.click();
            }
            // الزر قد يكون مربوطاً بعد اكتمال الكومة، فالإظهار المباشر ضمانة أخيرة
            if (motherInfoSection && motherInfoSection.style.display === 'none') {
                motherInfoSection.style.display = 'block';
            }
        }

        return {
            fill: fill,
            fillFamilyCard: fillFamilyCard,
            applyFamilyMembers: applyFamilyMembers,
            applyAdditionalDeceased: applyAdditionalDeceased,
            showDeceasedMother: showDeceasedMother
        };
    })();
</script>
