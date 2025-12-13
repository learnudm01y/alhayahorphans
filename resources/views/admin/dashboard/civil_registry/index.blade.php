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

    <!-- Modal عرض تفاصيل الشخص مع الحسابات البنكية -->
    <div class="modal fade" id="personDetailsModal" tabindex="-1" aria-labelledby="personDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="personDetailsModalLabel">
                        <i class="fas fa-user-circle"></i> تفاصيل الشخص
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="personDetailsContent">
                    <!-- Loading Spinner -->
                    <div class="text-center py-5" id="personDetailsLoading">
                        <div class="spinner-border text-info" style="width: 3rem; height: 3rem;" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                        <p class="mt-3 text-muted">جاري تحميل البيانات...</p>
                    </div>
                    <!-- محتوى التفاصيل -->
                    <div id="personDetailsData" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> إغلاق
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal إضافة حساب بنكي -->
    <div class="modal fade" id="addBankAccountModal" tabindex="-1" aria-labelledby="addBankAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addBankAccountModalLabel">
                        <i class="fas fa-university"></i> إضافة حساب بنكي جديد
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addBankAccountForm">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>معلومات الشخص:</strong>
                            <div id="personInfoDisplay" class="mt-2"></div>
                        </div>

                        <input type="hidden" id="bank_file_id_number" name="file_id_number">
                        <input type="hidden" id="bank_re_id_number" name="re_id_number">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم البنك <span class="text-danger">*</span></label>
                                <select name="bank_name" class="form-select" required>
                                    <option value="">اختر البنك</option>
                                    @foreach($bank_name ?? [] as $bank)
                                        <option value="{{ $bank->id }}">{{ $bank->bank_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">اسم صاحب الحساب <span class="text-danger">*</span></label>
                                <input type="text" name="re_guardian_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">رقم هوية صاحب الحساب</label>
                                <input type="text" name="person_owner_identity_number" class="form-control"
                                       inputmode="numeric" pattern="[0-9]*" maxlength="9"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">رقم الهاتف</label>
                                <input type="text" name="re_phone_number" class="form-control"
                                       inputmode="numeric" pattern="[0-9]*" maxlength="10"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">IBAN (دولار)</label>
                                <input type="text" name="iban_usd" class="form-control" maxlength="34"
                                       placeholder="PS00XXXX0000000000000000000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">IBAN (شيكل)</label>
                                <input type="text" name="iban_shekel" class="form-control" maxlength="34"
                                       placeholder="PS00XXXX0000000000000000000">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> إلغاء
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> حفظ الحساب البنكي
                        </button>
                    </div>
                </form>
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
                            <td><strong>${person.id_number || person.CI_ID_NUM || 'غير محدد'}</strong></td>
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
        // دالة عرض تفاصيل الشخص مع الحسابات البنكية
        window.showPersonDetails = function(personId) {
            // إظهار الـ Modal
            const modal = new bootstrap.Modal(document.getElementById('personDetailsModal'));
            modal.show();

            // إظهار الـ Loading وإخفاء المحتوى
            $('#personDetailsLoading').show();
            $('#personDetailsData').hide();

            // جلب البيانات من الـ API
            fetch(`/api/civil-registry/person-details/${personId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayPersonDetails(data.data);
                    } else {
                        $('#personDetailsData').html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                ${data.message}
                            </div>
                        `);
                    }
                    $('#personDetailsLoading').hide();
                    $('#personDetailsData').show();
                })
                .catch(error => {
                    console.error('خطأ في جلب البيانات:', error);
                    $('#personDetailsData').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            حدث خطأ في جلب البيانات. يرجى المحاولة مرة أخرى.
                        </div>
                    `);
                    $('#personDetailsLoading').hide();
                    $('#personDetailsData').show();
                });
        };

        // دالة عرض تفاصيل الشخص
        function displayPersonDetails(data) {
            const person = data.person;
            const bankAccounts = data.bank_accounts;
            const hasBankAccounts = data.has_bank_accounts;

            // بناء HTML للبيانات الأساسية
            let html = `
                <div class="container-fluid">
                    <!-- معلومات الشخص الأساسية -->
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="fas fa-id-card me-2"></i>البيانات الشخصية</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <strong><i class="fas fa-user text-primary me-2"></i>الاسم الكامل:</strong>
                                    <p class="mb-0">${person.full_name}</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong><i class="fas fa-id-badge text-info me-2"></i>رقم الهوية:</strong>
                                    <p class="mb-0">${person.id_number || 'غير محدد'}</p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <strong><i class="fas fa-venus-mars text-secondary me-2"></i>الجنس:</strong>
                                    <p class="mb-0">${person.sex}</p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <strong><i class="fas fa-calendar text-warning me-2"></i>تاريخ الميلاد:</strong>
                                    <p class="mb-0">${person.birth_date ? new Date(person.birth_date).toLocaleDateString('ar-EG') : 'غير محدد'}</p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <strong><i class="fas fa-heartbeat text-danger me-2"></i>الحالة:</strong>
                                    <p class="mb-0">
                                        ${person.is_alive
                                            ? '<span class="badge bg-success">على قيد الحياة</span>'
                                            : '<span class="badge bg-danger">متوفى</span>'}
                                    </p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong><i class="fas fa-female text-pink me-2"></i>اسم الأم:</strong>
                                    <p class="mb-0">${person.mother_name || 'غير محدد'}</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong><i class="fas fa-map-marker-alt text-success me-2"></i>المدينة:</strong>
                                    <p class="mb-0">${person.city || 'غير محدد'}</p>
                                </div>
                                ${person.street ? `
                                <div class="col-md-6 mb-3">
                                    <strong><i class="fas fa-road text-muted me-2"></i>الشارع:</strong>
                                    <p class="mb-0">${person.street}</p>
                                </div>
                                ` : ''}
                                ${person.house_no ? `
                                <div class="col-md-6 mb-3">
                                    <strong><i class="fas fa-home text-muted me-2"></i>رقم المنزل:</strong>
                                    <p class="mb-0">${person.house_no}</p>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>

                    <!-- الحسابات البنكية -->
                    <div class="card">
                        <div class="card-header ${hasBankAccounts ? 'bg-success' : 'bg-warning'} text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-university me-2"></i>
                                الحسابات البنكية
                                ${hasBankAccounts
                                    ? `<span class="badge bg-light text-success ms-2">${bankAccounts.length} حساب</span>`
                                    : '<span class="badge bg-light text-warning ms-2">لا توجد حسابات</span>'}
                            </h6>
                            ${data.data_record ? `
                            <button type="button" class="btn btn-light btn-sm" onclick="showAddBankAccountForm(${data.data_record.file_id_number}, '${data.person.id_number}', '${data.person.full_name}')">
                                <i class="fas fa-plus me-1"></i>إضافة حساب بنكي
                            </button>
                            ` : ''}
                        </div>
                        <div class="card-body">
            `;

            if (hasBankAccounts) {
                bankAccounts.forEach((account, index) => {
                    const isApproved = account.check_account == 1;
                    html += `
                        <div class="bank-account-form border rounded p-4 mb-4 position-relative"
                             style="border: 2px dashed ${isApproved ? '#50cd89' : '#009ef7'} !important; background-color: ${isApproved ? '#e8fff3' : '#f1faff'};">
                            <h6 class="mb-4 fw-bold" style="color: ${isApproved ? '#50cd89' : '#009ef7'};">
                                <i class="fas fa-university me-2"></i>حساب بنكي رقم ${index + 1}
                            </h6>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-muted">اسم البنك</label>
                                    <div class="form-control form-control-solid bg-light" style="background-color: #f5f8fa;">
                                        ${account.bank_name_text || 'غير محدد'}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-muted">اسم صاحب الحساب</label>
                                    <div class="form-control form-control-solid bg-light" style="background-color: #f5f8fa;">
                                        ${account.re_guardian_name || '-'}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-muted">رقم هوية صاحب الحساب</label>
                                    <div class="form-control form-control-solid bg-light" style="background-color: #f5f8fa;">
                                        ${account.person_owner_identity_number || '-'}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-muted">رقم هاتف صاحب الحساب</label>
                                    <div class="form-control form-control-solid bg-light" style="background-color: #f5f8fa;">
                                        ${account.re_phone_number || '-'}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-muted">رقم IBAN بالدولار</label>
                                    <div class="form-control form-control-solid bg-light" style="background-color: #f5f8fa; font-family: monospace;">
                                        ${account.iban_usd || '-'}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-muted">رقم IBAN بالشيكل</label>
                                    <div class="form-control form-control-solid bg-light" style="background-color: #f5f8fa; font-family: monospace;">
                                        ${account.iban_shekel || '-'}
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="alert ${isApproved ? 'alert-success' : 'alert-warning'} d-flex align-items-center justify-content-between mb-0">
                                        <span>
                                            ${isApproved
                                                ? '<i class="fas fa-check-circle me-2"></i>تم اعتماد هذا الحساب'
                                                : '<i class="fas fa-exclamation-triangle me-2"></i>هذا الحساب في انتظار الاعتماد'}
                                        </span>
                                        ${!isApproved ? `
                                        <button type="button" class="btn btn-sm btn-success approve-civil-bank-account"
                                                data-account-id="${account.id}">
                                            <i class="fas fa-check me-1"></i>اعتماد الحساب
                                        </button>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                if (data.data_record) {
                    html += `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>ملاحظة:</strong> هذا الشخص مسجل في النظام (رقم ملف: ${data.data_record.file_id_number})
                            ولكن لم يتم إضافة أي حسابات بنكية له بعد.
                        </div>
                    `;
                } else {
                    html += `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>تنبيه:</strong> هذا الشخص موجود في السجل المدني فقط ولم يتم تسجيله في نظام الحياة للأيتام بعد.
                            لإضافة حسابات بنكية، يجب أولاً تسجيل الشخص في النظام.
                        </div>
                    `;
                }
            }

            html += `
                        </div>
                    </div>
                </div>
            `;

            $('#personDetailsData').html(html);
        }

        // دالة لإظهار نموذج إضافة حساب بنكي
        function showAddBankAccountForm(fileIdNumber, idNumber, fullName) {
            // تعبئة الحقول المخفية
            $('#bank_file_id_number').val(fileIdNumber);
            $('#bank_re_id_number').val(idNumber);

            // عرض معلومات الشخص
            $('#personInfoDisplay').html(`
                <strong>الاسم:</strong> ${fullName}<br>
                <strong>رقم الهوية:</strong> ${idNumber}<br>
                <strong>رقم الملف:</strong> ${fileIdNumber}
            `);

            // مسح النموذج
            $('#addBankAccountForm')[0].reset();
            $('#bank_file_id_number').val(fileIdNumber);
            $('#bank_re_id_number').val(idNumber);

            // فتح المودال
            $('#addBankAccountModal').modal('show');
        }

        // معالجة إرسال النموذج
        $('#addBankAccountForm').on('submit', function(e) {
            e.preventDefault();

            const submitButton = $(this).find('button[type="submit"]');
            const originalText = submitButton.html();

            // تعطيل الزر أثناء الإرسال
            submitButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...');

            // جمع البيانات
            const formData = {
                file_id_number: $('#bank_file_id_number').val(),
                re_id_number: $('#bank_re_id_number').val(),
                bank_name: $('select[name="bank_name"]').val(),
                re_guardian_name: $('input[name="re_guardian_name"]').val(),
                person_owner_identity_number: $('input[name="person_owner_identity_number"]').val(),
                re_phone_number: $('input[name="re_phone_number"]').val(),
                iban_usd: $('input[name="iban_usd"]').val(),
                iban_shekel: $('input[name="iban_shekel"]').val()
            };

            $.ajax({
                url: '/api/civil-registry/save-bank-account',
                method: 'POST',
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    // إغلاق المودال
                    $('#addBankAccountModal').modal('hide');

                    // عرض رسالة نجاح
                    Swal.fire({
                        icon: 'success',
                        title: 'تم الحفظ بنجاح',
                        text: 'تم إضافة الحساب البنكي بنجاح',
                        confirmButtonText: 'موافق'
                    });

                    // إعادة تحميل تفاصيل الشخص لعرض الحساب الجديد
                    const personId = $('#bank_re_id_number').val();
                    $.ajax({
                        url: `/api/civil-registry/person-details/${personId}`,
                        method: 'GET',
                        success: function(data) {
                            displayPersonDetails(data);
                        }
                    });
                },
                error: function(xhr) {
                    let errorMessage = 'حدث خطأ أثناء حفظ البيانات';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: errorMessage,
                        confirmButtonText: 'موافق'
                    });
                },
                complete: function() {
                    // إعادة تفعيل الزر
                    submitButton.prop('disabled', false).html(originalText);
                }
            });
        });

        // معالجة اعتماد الحساب البنكي
        $(document).on('click', '.approve-civil-bank-account', function() {
            const accountId = $(this).data('account-id');
            const button = $(this);

            Swal.fire({
                title: 'تأكيد الاعتماد',
                text: 'هل تريد اعتماد هذا الحساب البنكي؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#50cd89',
                cancelButtonColor: '#f1416c',
                confirmButtonText: 'نعم، اعتماد',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    // تعطيل الزر أثناء المعالجة
                    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> جاري الاعتماد...');

                    $.ajax({
                        url: '/admin/records-management/approve-bank-account',
                        method: 'POST',
                        data: {
                            account_id: accountId,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الاعتماد',
                                text: 'تم اعتماد الحساب البنكي بنجاح',
                                confirmButtonText: 'موافق'
                            });

                            // إعادة تحميل التفاصيل
                            const personId = $('#bank_re_id_number').val();
                            if (personId) {
                                $.ajax({
                                    url: `/api/civil-registry/person-details/${personId}`,
                                    method: 'GET',
                                    success: function(data) {
                                        displayPersonDetails(data);
                                    }
                                });
                            }
                        },
                        error: function(xhr) {
                            button.prop('disabled', false).html('<i class="fas fa-check me-1"></i>اعتماد الحساب');

                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ',
                                text: xhr.responseJSON?.message || 'حدث خطأ أثناء اعتماد الحساب',
                                confirmButtonText: 'موافق'
                            });
                        }
                    });
                }
            });
        });
    });

    </script>
@endpush
