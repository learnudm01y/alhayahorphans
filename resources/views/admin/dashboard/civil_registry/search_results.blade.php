@extends('admin.dashboard.toolbars.index')

@section('content')
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header p-4" style="background: #28a745; border-radius: .5rem .5rem 0 0;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span style="color: #fff; font-weight: bold; font-size: 1.5rem; letter-spacing: 1px;">
                                <i class="bi bi-search me-2" style="color: #fff;"></i>
                                نتائج البحث المتقدم
                            </span>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light btn-sm" onclick="openAdvancedSearch()">
                                    <i class="bi bi-search me-1"></i>
                                    بحث جديد
                                </button>
                                <a href="{{ route('admin.persons.index') }}" class="btn btn-outline-light btn-sm">
                                    <i class="bi bi-arrow-left me-1"></i>
                                    العودة
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- عرض معايير البحث -->
                        @if(!empty($searchTerm) || !empty(array_filter($filters ?? [])))
                            <div class="alert alert-info mb-4">
                                <h6 class="alert-heading">
                                    <i class="bi bi-info-circle me-1"></i>
                                    معايير البحث المستخدمة:
                                </h6>
                                <div class="row g-2">
                                    @if(!empty($searchTerm))
                                        <div class="col-auto">
                                            <span class="badge bg-primary">البحث الشامل: {{ $searchTerm }}</span>
                                        </div>
                                    @endif

                                    @foreach($filters ?? [] as $key => $value)
                                        @if(!empty($value))
                                            <div class="col-auto">
                                                <span class="badge bg-secondary">
                                                    {{ $this->getFilterLabel($key) }}: {{ $value }}
                                                </span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- الإحصائيات -->
                        @if(isset($statistics))
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body text-center">
                                            <h4 class="card-title">{{ $statistics['total'] ?? 0 }}</h4>
                                            <p class="card-text">إجمالي النتائج</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info text-white">
                                        <div class="card-body text-center">
                                            <h4 class="card-title">{{ $statistics['male_count'] ?? 0 }}</h4>
                                            <p class="card-text">ذكور</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning text-white">
                                        <div class="card-body text-center">
                                            <h4 class="card-title">{{ $statistics['female_count'] ?? 0 }}</h4>
                                            <p class="card-text">إناث</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white">
                                        <div class="card-body text-center">
                                            <h4 class="card-title">{{ $statistics['cities_count'] ?? 0 }}</h4>
                                            <p class="card-text">مدن مختلفة</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- أزرار التصدير -->
                        <div class="d-flex justify-content-end mb-3">
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-success dropdown-toggle"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-download me-1"></i>
                                    تصدير النتائج
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item export-results-btn" href="#" data-format="excel">
                                        <i class="bi bi-file-earmark-excel me-1"></i> Excel
                                    </a></li>
                                    <li><a class="dropdown-item export-results-btn" href="#" data-format="csv">
                                        <i class="bi bi-file-earmark-text me-1"></i> CSV
                                    </a></li>
                                    <li><a class="dropdown-item export-results-btn" href="#" data-format="pdf">
                                        <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                                    </a></li>
                                </ul>
                            </div>
                        </div>

                        <!-- جدول النتائج -->
                        @if($persons->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped text-center align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>رقم الهوية</th>
                                            <th>الاسم الكامل</th>
                                            <th>تاريخ الميلاد</th>
                                            <th>الجنس</th>
                                            <th>المدينة</th>
                                            <th>الحالة الاجتماعية</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($persons as $person)
                                            <tr>
                                                <td>{{ $person->CI_ID_NUM ?? '-' }}</td>
                                                <td>{{ $this->getFullName($person) }}</td>
                                                <td>{{ $person->CI_BIRTH_DT ?? '-' }}</td>
                                                <td>
                                                    @if($person->CI_SEX_CD == 1)
                                                        <span class="badge bg-primary">ذكر</span>
                                                    @elseif($person->CI_SEX_CD == 2)
                                                        <span class="badge bg-info">أنثى</span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>{{ $person->city->name ?? 'غير محدد' }}</td>
                                                <td>{{ $person->socialStatus->name ?? 'غير محدد' }}</td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="{{ route('admin.persons.edit', $person->id) }}"
                                                           class="btn btn-sm btn-outline-primary" title="تعديل" target="_blank">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <form method="POST" action="{{ route('admin.persons.destroy', $person->id) }}"
                                                              style="display: inline;">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="button" class="btn btn-sm btn-outline-danger delete-btn"
                                                                    title="حذف">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                        <a href="{{ route('admin.persons.edit', $person->id) }}"
                                                           class="btn btn-sm btn-outline-info" title="عرض وتعديل" target="_blank">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- التنقل بين الصفحات -->
                            <div class="d-flex justify-content-center mt-4">
                                {{ $persons->appends(request()->query())->links() }}
                            </div>
                        @else
                            <div class="text-center py-5">
                                <i class="bi bi-search display-1 text-muted"></i>
                                <h4 class="mt-3 text-muted">لا توجد نتائج</h4>
                                <p class="text-muted">لم يتم العثور على أي نتائج تطابق معايير البحث المحددة</p>
                                <button type="button" class="btn btn-primary" onclick="openAdvancedSearch()">
                                    <i class="bi bi-search me-1"></i>
                                    جرب بحثاً جديداً
                                </button>
                            </div>
                        @endif
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
@endsection

@push('scriptsCode')
    <!-- ملفات JavaScript للبحث المتقدم -->
    <script src="{{ asset('js/person-search.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // أحداث حذف الشخص
            document.addEventListener('click', function(e) {
                if (e.target.closest('.delete-btn')) {
                    e.preventDefault();
                    const form = e.target.closest('form');

                    Swal.fire({
                        title: 'هل أنت متأكد؟',
                        text: 'لن تتمكن من التراجع عن هذا الإجراء!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'نعم، احذف!',
                        cancelButtonText: 'إلغاء'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                }
            });

            // أحداث تصدير النتائج
            document.addEventListener('click', function(e) {
                if (e.target.closest('.export-results-btn')) {
                    e.preventDefault();
                    const format = e.target.closest('.export-results-btn').dataset.format;

                    // إنشاء نموذج لإرسال البيانات
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route("admin.persons.search.export") }}';

                    // إضافة CSRF token
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = '{{ csrf_token() }}';
                    form.appendChild(csrfInput);

                    // إضافة معايير البحث
                    @if(!empty($searchTerm))
                        const searchTermInput = document.createElement('input');
                        searchTermInput.type = 'hidden';
                        searchTermInput.name = 'search_term';
                        searchTermInput.value = '{{ $searchTerm }}';
                        form.appendChild(searchTermInput);
                    @endif

                    @foreach($filters ?? [] as $key => $value)
                        @if(!empty($value))
                            const filterInput = document.createElement('input');
                            filterInput.type = 'hidden';
                            filterInput.name = '{{ $key }}';
                            filterInput.value = '{{ $value }}';
                            form.appendChild(filterInput);
                        @endif
                    @endforeach

                    // إضافة نوع التصدير
                    const formatInput = document.createElement('input');
                    formatInput.type = 'hidden';
                    formatInput.name = 'format';
                    formatInput.value = format;
                    form.appendChild(formatInput);

                    // إرسال النموذج
                    document.body.appendChild(form);
                    form.submit();
                    document.body.removeChild(form);
                }
            });
        });

        // دالة الحصول على الاسم الكامل
        function getFullName(person) {
            const parts = [
                person.CI_FIRST_ARB,
                person.CI_FATHER_ARB,
                person.CI_GRAND_FATHER_ARB,
                person.CI_FAMILY_ARB
            ].filter(part => part && part.trim());

            return parts.join(' ') || '-';
        }

        // دالة الحصول على تسمية الفلتر
        function getFilterLabel(key) {
            const labels = {
                'ci_id_num': 'رقم الهوية',
                'first_name': 'الاسم الأول',
                'father_name': 'اسم الأب',
                'grandfather_name': 'اسم الجد',
                'family_name': 'اسم العائلة',
                'mother_name': 'اسم الأم',
                'gender': 'الجنس',
                'city': 'المدينة',
                'birth_date_from': 'تاريخ الميلاد من',
                'birth_date_to': 'تاريخ الميلاد إلى',
                'social_status': 'الحالة الاجتماعية'
            };

            return labels[key] || key;
        }
    </script>
@endpush

@php
    // دوال PHP مساعدة
    function getFullName($person) {
        $nameParts = array_filter([
            $person->CI_FIRST_ARB,
            $person->CI_FATHER_ARB,
            $person->CI_GRAND_FATHER_ARB,
            $person->CI_FAMILY_ARB
        ]);

        return implode(' ', $nameParts) ?: '-';
    }

    function getFilterLabel($key) {
        $labels = [
            'ci_id_num' => 'رقم الهوية',
            'first_name' => 'الاسم الأول',
            'father_name' => 'اسم الأب',
            'grandfather_name' => 'اسم الجد',
            'family_name' => 'اسم العائلة',
            'mother_name' => 'اسم الأم',
            'gender' => 'الجنس',
            'city' => 'المدينة',
            'birth_date_from' => 'تاريخ الميلاد من',
            'birth_date_to' => 'تاريخ الميلاد إلى',
            'social_status' => 'الحالة الاجتماعية'
        ];

        return $labels[$key] ?? $key;
    }
@endphp
