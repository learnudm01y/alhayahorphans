@extends('admin.dashboard.toolbars.index')

@section('content')
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header p-4" style="background: #0d6efd; border-radius: .5rem .5rem 0 0;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span style="color: #fff; font-weight: bold; font-size: 1.5rem; letter-spacing: 1px;">
                                <i class="bi bi-people-fill me-2" style="color: #fff;"></i>
                                إدارة المواطنين
                            </span>
                        </div>
                        <div class="d-flex justify-content-start gap-2">
                            <a href="{{ route('civil-registry.create') }}" class="btn btn-light btn-sm d-flex align-items-center justify-content-center">
                                <i class="bi bi-plus-circle me-1"></i>
                                إضافة مواطن
                            </a>
                            <button type="button" class="btn btn-warning btn-sm d-flex align-items-center justify-content-center"
                                    data-bs-toggle="modal" data-bs-target="#civilRegistrySearchModal">
                                <i class="fas fa-search-plus me-1"></i>
                                البحث في السجل المدني الجديد
                            </button>
                        </div>
                    </div>

                    <div class="card-body">
                        {!! $dataTable->table(['class' => 'table table-bordered table-striped text-center align-middle w-100'], true) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- تضمين Modal البحث المتقدم -->
    @include('admin.dashboard.civil_registry.search_modal', [
        'cities' => \App\Models\City::all(),
        'socialStatuses' => \App\Models\CI_PERSONAL_CD::all()
    ])

    <!-- Modal البحث في السجل المدني الجديد -->
    <div class="modal fade" id="civilRegistrySearchModal" tabindex="-1" aria-labelledby="civilRegistrySearchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="civilRegistrySearchModalLabel">
                        <i class="fas fa-search-plus"></i> البحث في السجل المدني الجديد
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <!-- شاشة التحميل -->
                    <div id="civilRegistryLoadingOverlay" class="civil-loading-overlay position-absolute w-100 h-100 align-items-center justify-content-center d-none" style="background: rgba(255,255,255,0.9); z-index: 9999; display: none !important;">
                        <div class="text-center">
                            <div class="spinner-border text-warning" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">جاري البحث...</span>
                            </div>
                            <p class="mt-3 text-muted fw-bold">جاري البحث في السجل المدني الجديد...</p>
                        </div>
                    </div>

                    <!-- محتوى النموذج -->
                    <div class="container-fluid p-4">
                        <!-- إحصائيات قاعدة البيانات -->
                        <div id="civilRegistryStats" class="row mb-4">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    جاري تحميل إحصائيات قاعدة البيانات الجديدة...
                                </div>
                            </div>
                        </div>

                        <!-- نموذج البحث -->
                        <form id="civilRegistrySearchForm">
                            <div class="row">
                                <!-- البحث النصي -->
                                <div class="col-md-6 mb-3">
                                    <label for="civil_search_text" class="form-label">نص البحث</label>
                                    <input type="text" class="form-control" id="civil_search_text" name="search_text" placeholder="ادخل الاسم أو رقم الهوية...">
                                    <small class="form-text text-muted">يمكنك البحث بالاسم الكامل أو جزء منه أو رقم الهوية</small>
                                </div>

                                <!-- نوع البحث -->
                                <div class="col-md-6 mb-3">
                                    <label for="civil_search_type" class="form-label">نوع البحث</label>
                                    <select class="form-select" id="civil_search_type" name="search_type">
                                        <option value="quick">البحث السريع</option>
                                        <option value="advanced">البحث المتقدم</option>
                                        <option value="comprehensive">البحث الشامل</option>
                                        <option value="id_only">البحث برقم الهوية فقط</option>
                                        <option value="name_only">البحث بالاسم فقط</option>
                                        <option value="family_relations" style="background-color: #d4edda; font-weight: bold;">🌳 البحث العائلي (عرض العائلة كاملة)</option>
                                    </select>
                                    <small class="form-text text-success" id="familySearchHint" style="display: none;">
                                        <i class="fas fa-info-circle"></i> البحث العائلي: سيتم عرض جميع أفراد العائلة المرتبطين بالشخص
                                    </small>
                                </div>

                                <!-- الجنس -->
                                <div class="col-md-4 mb-3">
                                    <label for="civil_gender" class="form-label">الجنس</label>
                                    <select class="form-select" id="civil_gender" name="gender">
                                        <option value="">الكل</option>
                                        <option value="1">ذكر</option>
                                        <option value="2">أنثى</option>
                                    </select>
                                </div>

                                <!-- سنة الميلاد -->
                                <div class="col-md-4 mb-3">
                                    <label for="civil_birth_year" class="form-label">سنة الميلاد</label>
                                    <input type="number" class="form-control" id="civil_birth_year" name="birth_year" min="1900" max="2025" placeholder="مثال: 1990">
                                </div>

                                <!-- حالة الحياة -->
                                <div class="col-md-4 mb-3">
                                    <label for="civil_is_alive" class="form-label">حالة الحياة</label>
                                    <select class="form-select" id="civil_is_alive" name="is_alive">
                                        <option value="">الكل</option>
                                        <option value="1">حي</option>
                                        <option value="0">متوفي</option>
                                    </select>
                                </div>

                                <!-- عدد النتائج -->
                                <div class="col-md-6 mb-3">
                                    <label for="civil_limit" class="form-label">عدد النتائج</label>
                                    <select class="form-select" id="civil_limit" name="limit">
                                        <option value="20">20 نتيجة</option>
                                        <option value="50" selected>50 نتيجة</option>
                                        <option value="100">100 نتيجة</option>
                                        <option value="200">200 نتيجة</option>
                                    </select>
                                </div>

                                <!-- أزرار التحكم -->
                                <div class="col-md-6 mb-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-warning me-2">
                                        <i class="fas fa-search"></i> بحث
                                    </button>
                                    <button type="button" class="btn btn-secondary me-2" id="civilClearForm">
                                        <i class="fas fa-eraser"></i> مسح
                                    </button>
                                    <button type="button" class="btn btn-info" id="civilTestConnection">
                                        <i class="fas fa-plug"></i> اختبار الاتصال
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- النتائج -->
                        <div id="civilRegistryResults" class="mt-4" style="display: none;">
                            <hr>
                            <h5><i class="fas fa-list"></i> نتائج البحث في السجل المدني الجديد</h5>
                            <div id="civilRegistryResultsContent"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="row w-100">
                        <div class="col-md-6 text-start">
                            <small class="text-muted" id="civilSearchInfo">جاهز للبحث في السجل المدني الجديد</small>
                        </div>
                        <div class="col-md-6 text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> إغلاق
                            </button>
                            <button type="button" class="btn btn-danger" id="civilClearCache">
                                <i class="fas fa-trash"></i> مسح الكاش
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scriptsCode')
    {!! $dataTable->scripts() !!}

    <!-- ملفات CSS و JavaScript للبحث المتقدم -->
    <link href="{{ asset('css/person-search.css') }}" rel="stylesheet">
    <script src="{{ asset('js/person-search.js') }}"></script>

    <script>
        // لا تغييرات هنا لأن التسريع يجب أن يتم في الاستعلامات وقاعدة البيانات وليس في الواجهة
        document.addEventListener('DOMContentLoaded', function () {
            $(document).on('preInit.dt', '#persons-table', function () {
                var table = $('#persons-table').DataTable();
                $('#search-ci-id-num').on('keyup change', function () {
                    table.column(0).search(this.value).draw();
                });
                $('#search-ci-first-arb').on('keyup change', function () {
                    table.column(1).search(this.value).draw();
                });
                $('#search-ci-father-arb').on('keyup change', function () {
                    table.column(2).search(this.value).draw();
                });
                $('#search-ci-grand-father-arb').on('keyup change', function () {
                    table.column(3).search(this.value).draw();
                });
                $('#search-ci-family-arb').on('keyup change', function () {
                    table.column(4).search(this.value).draw();
                });
            });
            $('#persons-table').on('click', '.delete-btn', function (event) {
                event.preventDefault();
                var form = $(this).closest('form');
                var deleteUrl = form.attr('action');

                Swal.fire({
                    title: 'هل أنت متأكد؟',
                    text: 'لن تتمكن من التراجع عن هذا الإجراء!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'نعم، احذف!',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.off('submit').submit();
                    }
                });
            });
        });
    </script>

    <!-- Scout Search Modal -->
    @include('admin.dashboard.civil_registry.scout_search_modal')

    <!-- Scout Search CSS المعزول -->
    <link rel="stylesheet" href="{{ asset('css/scout-search-isolated.css') }}">

    <!-- Scout Search JavaScript المحسن -->
    <script src="{{ asset('js/scout-search-fixed.js') }}"></script>

    <!-- JavaScript للتحكم في البحث في السجل المدني -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // إظهار/إخفاء تلميح البحث العائلي
        $('#civil_search_type').on('change', function() {
            if ($(this).val() === 'family_relations') {
                $('#familySearchHint').show();
            } else {
                $('#familySearchHint').hide();
            }
        });

        // تحميل إحصائيات قاعدة البيانات عند فتح المودال
        $('#civilRegistrySearchModal').on('shown.bs.modal', function () {
            // التأكد من إخفاء الـ spinner عند فتح المودال
            showCivilRegistryLoading(false);
            loadCivilRegistryStats();
        });

        // إخفاء الـ spinner عند إغلاق المودال أيضاً
        $('#civilRegistrySearchModal').on('hidden.bs.modal', function () {
            showCivilRegistryLoading(false);
        });

        // تحميل إحصائيات قاعدة البيانات
        function loadCivilRegistryStats() {
            fetch('/api/civil-registry/database-stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const stats = data.data;
                        $('#civilRegistryStats').html(`
                            <div class="col-12">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="card bg-info text-white">
                                            <div class="card-body text-center">
                                                <h4>${stats.total_records.toLocaleString()}</h4>
                                                <small>إجمالي السجلات</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-success text-white">
                                            <div class="card-body text-center">
                                                <h4>${stats.alive_records.toLocaleString()}</h4>
                                                <small>الأحياء</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-warning text-dark">
                                            <div class="card-body text-center">
                                                <h4>${stats.male_records.toLocaleString()}</h4>
                                                <small>الذكور</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-primary text-white">
                                            <div class="card-body text-center">
                                                <h4>${stats.female_records.toLocaleString()}</h4>
                                                <small>الإناث</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `);
                    }
                })
                .catch(error => {
                    console.error('خطأ في تحميل الإحصائيات:', error);
                    $('#civilRegistryStats').html(`
                        <div class="col-12">
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                خطأ في تحميل إحصائيات قاعدة البيانات
                            </div>
                        </div>
                    `);
                });
        }

        // نموذج البحث
        $('#civilRegistrySearchForm').on('submit', function(e) {
            e.preventDefault();

            const searchText = $('#civil_search_text').val().trim();
            const searchType = $('#civil_search_type').val();

            if (!searchText) {
                alert('يرجى إدخال نص للبحث');
                return;
            }

            performCivilRegistrySearch();
        });

        // تنفيذ البحث
        function performCivilRegistrySearch() {
            showCivilRegistryLoading(true);

            const formData = new FormData($('#civilRegistrySearchForm')[0]);
            const searchParams = new URLSearchParams();

            for (let [key, value] of formData.entries()) {
                if (value) {
                    searchParams.append(key, value);
                }
            }

            const searchType = $('#civil_search_type').val();
            let endpoint = '/api/civil-registry/search';

            // التحقق من نوع البحث العائلي
            if (searchType === 'family_relations') {
                performFamilyRelationsSearchInModal();
                return;
            }

            switch(searchType) {
                case 'quick':
                    endpoint = '/api/civil-registry/quick-search';
                    break;
                case 'advanced':
                    endpoint = '/api/civil-registry/advanced-search';
                    break;
                case 'comprehensive':
                    endpoint = '/api/civil-registry/comprehensive-search';
                    break;
                case 'id_only':
                    endpoint = '/api/civil-registry/search-by-id';
                    break;
                case 'name_only':
                    endpoint = '/api/civil-registry/search-by-name';
                    break;
            }

            const startTime = Date.now();

            fetch(`${endpoint}?${searchParams.toString()}`)
                .then(response => response.json())
                .then(data => {
                    const endTime = Date.now();
                    const searchDuration = ((endTime - startTime) / 1000).toFixed(2);

                    // إخفاء شاشة التحميل فوراً عند استلام النتائج
                    showCivilRegistryLoading(false);

                    if (data.success) {
                        displayCivilRegistryResults(data.data, searchDuration);
                        updateSearchInfo(`تم العثور على ${data.data.length} نتيجة في ${searchDuration} ثانية`);
                    } else {
                        $('#civilRegistryResults').hide();
                        updateSearchInfo(`خطأ في البحث: ${data.message}`);
                        alert(`خطأ في البحث: ${data.message}`);
                    }
                })
                .catch(error => {
                    // إخفاء شاشة التحميل في حالة الخطأ أيضاً
                    showCivilRegistryLoading(false);
                    console.error('خطأ في البحث:', error);
                    $('#civilRegistryResults').hide();
                    updateSearchInfo('خطأ في الاتصال بالخادم');
                    alert('حدث خطأ في البحث. يرجى المحاولة مرة أخرى.');
                });
        }

        // عرض النتائج
        function displayCivilRegistryResults(results, searchDuration) {
            // إخفاء شاشة التحميل أولاً
            showCivilRegistryLoading(false);

            if (results.length === 0) {
                $('#civilRegistryResultsContent').html(`
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-search"></i>
                        <h5>لم يتم العثور على نتائج</h5>
                        <p>لم يتم العثور على أي سجلات تطابق معايير البحث.</p>
                    </div>
                `);
            } else {
                let html = `
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6>عدد النتائج: ${results.length}</h6>
                        <small class="text-muted">وقت البحث: ${searchDuration} ثانية</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-warning">
                                <tr>
                                    <th>رقم الهوية</th>
                                    <th>الاسم الكامل</th>
                                    <th>الجنس</th>
                                    <th>تاريخ الميلاد</th>
                                    <th>العمر</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                results.forEach(person => {
                    // بناء الاسم الكامل من الأعمدة المنفصلة
                    const fullName = [
                        person.CI_FIRST_ARB || '',
                        person.CI_FATHER_ARB || '',
                        person.CI_GRAND_FATHER_ARB || '',
                        person.CI_FAMILY_ARB || ''
                    ].filter(name => name.trim()).join(' ');

                    const age = person.CI_BIRTH_DT ? calculateAge(person.CI_BIRTH_DT) : 'غير محدد';
                    const gender = person.CI_SEX_CD == 1 ? 'ذكر' : (person.CI_SEX_CD == 2 ? 'أنثى' : 'غير محدد');
                    const isAlive = !person.CI_DEAD_DT;
                    const status = isAlive ? '<span class="badge bg-success">حي</span>' : '<span class="badge bg-danger">متوفي</span>';

                    html += `
                        <tr>
                            <td><strong>${person.CI_ID_NUM || 'غير محدد'}</strong></td>
                            <td>${fullName || 'غير محدد'}</td>
                            <td>${gender}</td>
                            <td>${person.CI_BIRTH_DT ? new Date(person.CI_BIRTH_DT).toLocaleDateString('ar-EG') : 'غير محدد'}</td>
                            <td>${age}</td>
                            <td>${status}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" onclick="showPersonDetails('${person.ID}')">
                                    <i class="fas fa-eye"></i> عرض
                                </button>
                            </td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;

                $('#civilRegistryResultsContent').html(html);
            }

            $('#civilRegistryResults').show();
        }

        // حساب العمر
        function calculateAge(birthDate) {
            if (!birthDate) return 'غير محدد';
            const today = new Date();
            const birth = new Date(birthDate);
            let age = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                age--;
            }
            return age + ' سنة';
        }

        // ==================== دالة البحث العائلي داخل Modal السجل المدني ====================
        function performFamilyRelationsSearchInModal() {
            const searchText = $('#civil_search_text').val().trim();
            
            if (!searchText) {
                alert('يرجى إدخال رقم الهوية أو الاسم للبحث العائلي');
                showCivilRegistryLoading(false);
                return;
            }

            updateSearchInfo('جاري البحث عن العلاقات العائلية...');
            
            const startTime = Date.now();

            // تحديد نوع البحث: رقم أم اسم
            const isIdNumber = /^\d+$/.test(searchText);
            const endpoint = isIdNumber 
                ? '/admin/family-relations/search' 
                : '/admin/family-relations/search-by-name';
            const requestBody = isIdNumber 
                ? { id_number: searchText } 
                : { name: searchText };

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(requestBody)
            })
            .then(response => response.json())
            .then(data => {
                const endTime = Date.now();
                const searchDuration = ((endTime - startTime) / 1000).toFixed(2);

                showCivilRegistryLoading(false);

                if (data.success) {
                    // التحقق من وجود نتائج متعددة
                    if (data.data.multiple_results) {
                        displayMultiplePersonsSelection(data.data.persons, searchDuration);
                    } else {
                        displayFamilyRelationsInModal(data.data, searchDuration);
                        updateSearchInfo(`تم العثور على ${data.data.family_members.length} فرد من العائلة في ${searchDuration} ثانية`);
                    }
                } else {
                    $('#civilRegistryResults').hide();
                    updateSearchInfo(`خطأ: ${data.message}`);
                    alert(`خطأ: ${data.message}`);
                }
            })
            .catch(error => {
                showCivilRegistryLoading(false);
                console.error('خطأ في البحث العائلي:', error);
                $('#civilRegistryResults').hide();
                updateSearchInfo('خطأ في الاتصال بالخادم');
                alert('حدث خطأ في البحث العائلي. يرجى المحاولة مرة أخرى.');
            });
        }

        // عرض قائمة الأشخاص المتعددين للاختيار
        function displayMultiplePersonsSelection(persons, searchDuration) {
            let html = `
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle"></i> تم العثور على ${persons.length} شخص مطابق!</h5>
                    <p>يرجى اختيار الشخص المناسب من القائمة أدناه:</p>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-bordered">
                        <thead class="table-warning">
                            <tr>
                                <th>اختيار</th>
                                <th>رقم الهوية</th>
                                <th>الاسم الكامل</th>
                                <th>الجنس</th>
                                <th>العمر</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            persons.forEach(person => {
                const statusBadge = person.is_alive 
                    ? '<span class="badge bg-success">حي</span>' 
                    : '<span class="badge bg-danger">متوفي</span>';
                
                html += `
                    <tr style="cursor: pointer;" onclick="selectPersonAndSearch('${person.id_number}')">
                        <td class="text-center">
                            <button class="btn btn-sm btn-primary" onclick="selectPersonAndSearch('${person.id_number}'); event.stopPropagation();">
                                <i class="fas fa-hand-pointer"></i> اختيار
                            </button>
                        </td>
                        <td><strong>${person.id_number}</strong></td>
                        <td>${person.full_name}</td>
                        <td>${person.gender}</td>
                        <td>${person.age}</td>
                        <td>${statusBadge}</td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mt-3">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        <strong>ملاحظة:</strong> اضغط على أي صف أو زر "اختيار" لعرض العائلة الكاملة للشخص المختار.
                    </small>
                </div>
            `;

            $('#civilRegistryResultsContent').html(html);
            $('#civilRegistryResults').show();
            updateSearchInfo(`تم العثور على ${persons.length} شخص مطابق في ${searchDuration} ثانية`);
        }

        // اختيار شخص من القائمة والبحث عن عائلته
        function selectPersonAndSearch(idNumber) {
            showCivilRegistryLoading(true);
            updateSearchInfo('جاري تحميل بيانات العائلة...');
            
            const startTime = Date.now();

            fetch('/admin/family-relations/search', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ id_number: idNumber })
            })
            .then(response => response.json())
            .then(data => {
                const endTime = Date.now();
                const searchDuration = ((endTime - startTime) / 1000).toFixed(2);

                showCivilRegistryLoading(false);

                if (data.success) {
                    displayFamilyRelationsInModal(data.data, searchDuration);
                    updateSearchInfo(`تم العثور على ${data.data.family_members.length} فرد من العائلة في ${searchDuration} ثانية`);
                } else {
                    alert(`خطأ: ${data.message}`);
                }
            })
            .catch(error => {
                showCivilRegistryLoading(false);
                console.error('خطأ:', error);
                alert('حدث خطأ في تحميل بيانات العائلة');
            });
        }

        // عرض نتائج البحث العائلي في Modal السجل المدني
        function displayFamilyRelationsInModal(data, searchDuration) {
            showCivilRegistryLoading(false);

            const person = data.person;
            const familyMembers = data.family_members;
            const stats = data.statistics;

            let html = `
                <div class="alert alert-success">
                    <h5><i class="fas fa-user-circle"></i> معلومات الشخص المبحوث عنه:</h5>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p><strong>رقم الهوية:</strong> <span class="badge bg-primary fs-6">${person.id_number}</span></p>
                            <p><strong>الاسم الكامل:</strong> ${person.full_name}</p>
                            <p><strong>تاريخ الميلاد:</strong> ${person.birth_date ? new Date(person.birth_date).toLocaleDateString('ar-EG') : 'غير محدد'}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>العمر:</strong> ${person.age}</p>
                            <p><strong>الجنس:</strong> ${person.gender}</p>
                            <p><strong>الحالة:</strong> <span class="badge ${person.is_alive ? 'bg-success' : 'bg-danger'}">${person.status}</span></p>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center py-2">
                                <h4 class="mb-0">${stats.total_relations}</h4>
                                <small>إجمالي العلاقات</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center py-2">
                                <h4 class="mb-0">${Object.keys(stats.relation_types).length}</h4>
                                <small>أنواع العلاقات</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center py-2">
                                <h4 class="mb-0">${searchDuration} ثانية</h4>
                                <small>وقت البحث</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            if (familyMembers.length === 0) {
                html += `
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-info-circle"></i>
                        <h5>لا توجد علاقات عائلية</h5>
                        <p>لم يتم العثور على أي أفراد عائلة مرتبطين بهذا الشخص.</p>
                    </div>
                `;
            } else {
                // جمع كل الأفراد في جدول واحد مع إزالة التكرار
                let allMembers = [];
                let addedIds = new Set(); // لتتبع الأرقام المضافة وتجنب التكرار
                
                // إضافة الأشخاص الرئيسيين (الأب، الأم، إلخ)
                familyMembers.forEach(member => {
                    if (!addedIds.has(member.id_number)) {
                        allMembers.push({
                            id_number: member.id_number,
                            full_name: member.full_name,
                            relation_type: member.relation_type,
                            gender: member.gender,
                            age: member.age,
                            is_alive: member.is_alive,
                            status: member.status,
                            level: 0 // المستوى الأول (الأب، الأم)
                        });
                        addedIds.add(member.id_number);
                    }
                    
                    // إضافة الأبناء
                    if (member.children && member.children.length > 0) {
                        member.children.forEach(child => {
                            // تجنب إضافة نفس الشخص الرئيسي كابن
                            if (!addedIds.has(child.id_number)) {
                                allMembers.push({
                                    id_number: child.id_number,
                                    full_name: child.full_name,
                                    relation_type: child.relation_type,
                                    gender: child.gender,
                                    age: child.age,
                                    is_alive: child.is_alive,
                                    status: child.status,
                                    level: 1, // المستوى الثاني (الأبناء)
                                    parent_name: member.full_name // اسم الوالد
                                });
                                addedIds.add(child.id_number);
                            }
                        });
                    }
                });

                html += `
                    <h5 class="mb-3">
                        <i class="fas fa-users"></i> الشجرة العائلية (${allMembers.length} فرد):
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-striped">
                            <thead class="table-primary">
                                <tr>
                                    <th>#</th>
                                    <th>رقم الهوية</th>
                                    <th>الاسم الكامل</th>
                                    <th>العلاقة</th>
                                    <th>الجنس</th>
                                    <th>العمر</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                allMembers.forEach((member, index) => {
                    const statusBadge = member.is_alive 
                        ? '<span class="badge bg-success">حي</span>' 
                        : '<span class="badge bg-danger">متوفي</span>';
                    
                    // تنسيق خاص للمستوى الأول (الأب، الأم)
                    const rowClass = member.level === 0 ? 'table-info fw-bold' : '';
                    const namePrefix = member.level === 1 ? '&nbsp;&nbsp;&nbsp;↳ ' : '';
                    
                    html += `
                        <tr class="${rowClass}">
                            <td>${index + 1}</td>
                            <td><strong>${member.id_number}</strong></td>
                            <td>${namePrefix}${member.full_name}</td>
                            <td><span class="badge ${member.level === 0 ? 'bg-primary' : 'bg-secondary'}">${member.relation_type}</span></td>
                            <td>${member.gender}</td>
                            <td>${member.age}</td>
                            <td>${statusBadge}</td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info mt-3">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            <strong>ملاحظة:</strong> 
                            الصفوف الملونة بالأزرق تمثل الأشخاص الرئيسيين (الأب، الأم)، 
                            والصفوف التي تحتوي على (↳) تمثل الأبناء والمرتبطين.
                        </small>
                    </div>
                `;
            }

            $('#civilRegistryResultsContent').html(html);
            $('#civilRegistryResults').show();
        }

        // إظهار/إخفاء شاشة التحميل
        function showCivilRegistryLoading(show) {
            const overlay = $('#civilRegistryLoadingOverlay');
            console.log('showCivilRegistryLoading called with:', show); // للتتبع

            if (show) {
                // إظهار الـ loading
                overlay.removeClass('d-none').addClass('d-flex').css({
                    'display': 'flex !important',
                    'visibility': 'visible',
                    'opacity': '1'
                }).show();
                console.log('Loading overlay shown'); // للتتبع
            } else {
                // إخفاء الـ loading بقوة
                overlay.removeClass('d-flex').addClass('d-none').css({
                    'display': 'none !important',
                    'visibility': 'hidden',
                    'opacity': '0'
                }).hide();
                console.log('Loading overlay hidden'); // للتتبع
            }
        }

        // التأكد من إخفاء الـ spinner عند تحميل الصفحة
        $(document).ready(function() {
            showCivilRegistryLoading(false);
        });

        // تحديث معلومات البحث
        function updateSearchInfo(message) {
            $('#civilSearchInfo').text(message);
        }

        // مسح النموذج
        $('#civilClearForm').on('click', function() {
            $('#civilRegistrySearchForm')[0].reset();
            $('#civilRegistryResults').hide();
            showCivilRegistryLoading(false); // إخفاء الـ spinner عند مسح النموذج
            updateSearchInfo('جاهز للبحث في السجل المدني الجديد');
        });

        // اختبار الاتصال
        $('#civilTestConnection').on('click', function() {
            showCivilRegistryLoading(true);
            updateSearchInfo('جاري اختبار الاتصال...');

            fetch('/api/civil-registry/test-connection')
                .then(response => response.json())
                .then(data => {
                    showCivilRegistryLoading(false);
                    if (data.success) {
                        updateSearchInfo(`✅ الاتصال ناجح - ${data.message}`);
                        alert(`✅ نجح الاتصال بقاعدة البيانات\n${data.message}`);
                    } else {
                        updateSearchInfo(`❌ فشل الاتصال - ${data.message}`);
                        alert(`❌ فشل الاتصال بقاعدة البيانات\n${data.message}`);
                    }
                })
                .catch(error => {
                    showCivilRegistryLoading(false);
                    console.error('خطأ في اختبار الاتصال:', error);
                    updateSearchInfo('❌ خطأ في اختبار الاتصال');
                    alert('حدث خطأ في اختبار الاتصال');
                });
        });

        // مسح الكاش
        $('#civilClearCache').on('click', function() {
            if (confirm('هل أنت متأكد من مسح جميع بيانات الكاش؟')) {
                showCivilRegistryLoading(true);
                updateSearchInfo('جاري مسح الكاش...');

                fetch('/api/civil-registry/clear-cache', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        showCivilRegistryLoading(false);
                        if (data.success) {
                            updateSearchInfo('✅ تم مسح الكاش بنجاح');
                            alert('✅ تم مسح الكاش بنجاح');
                        } else {
                            updateSearchInfo(`❌ فشل مسح الكاش - ${data.message}`);
                            alert(`❌ فشل مسح الكاش: ${data.message}`);
                        }
                    })
                    .catch(error => {
                        showCivilRegistryLoading(false);
                        console.error('خطأ في مسح الكاش:', error);
                        updateSearchInfo('❌ خطأ في مسح الكاش');
                        alert('حدث خطأ في مسح الكاش');
                    });
            }
        });

        // دالة عرض تفاصيل الشخص (يمكن تطويرها لاحقاً)
        window.showPersonDetails = function(personId) {
            alert(`عرض تفاصيل الشخص رقم: ${personId}\n(هذه الميزة قيد التطوير)`);
        };
    });

    </script>
@endpush
