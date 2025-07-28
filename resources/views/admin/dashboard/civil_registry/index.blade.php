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
                            {{-- <button type="button" class="btn btn-success btn-sm d-flex align-items-center justify-content-center"
                                    onclick="openAdvancedSearch()">
                                <i class="bi bi-search me-1"></i>
                                البحث المتقدم
                            </button> --}}
                            {{-- <button type="button" class="btn btn-primary btn-sm d-flex align-items-center justify-content-center"
                                    onclick="openScoutSearchModal()">
                                <i class="fas fa-rocket me-1"></i>
                                البحث السريع Scout
                            </button> --}}
                            <button type="button" class="btn btn-warning btn-sm d-flex align-items-center justify-content-center"
                                    data-bs-toggle="modal" data-bs-target="#civilRegistrySearchModal">
                                <i class="fas fa-search-plus me-1"></i>
                                البحث في السجل المدني الجديد
                            </button>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="mb-3">
                            <div class="row g-2">
                                <div class="col-md-2">
                                    <input type="text" id="search-ci-id-num" class="form-control" placeholder="رقم الهوية">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" id="search-ci-first-arb" class="form-control" placeholder="الاسم الأول">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" id="search-ci-father-arb" class="form-control" placeholder="اسم الأب">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" id="search-ci-grand-father-arb" class="form-control" placeholder="اسم الجد">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" id="search-ci-family-arb" class="form-control" placeholder="اسم العائلة">
                                </div>
                            </div>
                        </div>
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
                                    </select>
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
