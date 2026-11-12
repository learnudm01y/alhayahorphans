@php
    // الحقول الثابتة تسترجع قيمها مباشرة عبر old() داخل القوالب، لكن بطاقات أفراد الأسرة
    // وصفوف المتوفين الإضافيين لا توجد في HTML القادم من الخادم — إنها تُولَّد في المتصفح،
    // لذلك تُعاد بناؤها من البيانات المخزّنة في الجلسة باستخدام نفس دوال الإضافة الأصلية.
    $oldFamilyMembers = array_values(array_filter((array) old('family_members', []), 'is_array'));
    $oldAdditionalDeceased = array_values(array_filter((array) old('additional_deceased', []), 'is_array'));
    $oldDeceasedMotherVisible = (string) old('deceased_mother_id', '') !== ''
        || (string) old('deceased_mother_first_name', '') !== ''
        || (string) old('mother_death_date', '') !== ''
        || (string) old('mother_is_alive', '') === '0';
@endphp

@include('user.generalRegistration.javascript.restoreRows')

@if (count($oldFamilyMembers) > 0 || count($oldAdditionalDeceased) > 0 || $oldDeceasedMotherVisible)
    <script>
        // يعمل بعد اكتمال تحميل الصفحة: دالة إضافة الفرد تُعرَّف داخل كود التبويب،
        // وأحداث الأزرار تُربط عند DOMContentLoaded، فالتنفيذ عند load يضمن جهوزيتها.
        window.addEventListener('load', function () {
            window.RegRows.applyFamilyMembers(@json($oldFamilyMembers));
            window.RegRows.applyAdditionalDeceased(@json($oldAdditionalDeceased));
            @if ($oldDeceasedMotherVisible)
                window.RegRows.showDeceasedMother();
            @endif
        });
    </script>
@endif
