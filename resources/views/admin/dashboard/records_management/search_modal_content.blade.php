<!-- محتوى البحث للـ Modal -->
<!-- تأكد من وجود CSRF token -->
@if(!isset($csrfToken))
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endif

<div style="position: relative;">

<style>
    /* تحسين مظهر الاقتراحات في الـ Modal */
    #searchSuggestions {
        position: absol<!-- شاشة التحميل للـ Modal -->
<div id="loadingOverlay" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
     background: rgba(255,255,255,0.8); z-index: 1000; border-radius: 0.375rem;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
         background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">جاري البحث...</span>
        </div>
        <p class="mt-2 mb-0">جاري البحث في السجلات...</p>
    </div>
</div>   top: 100%;
        left: 0;
        z-index: 1050;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        background: white;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    #searchSuggestions .dropdown-item {
        padding: 0.5rem 1rem;
        border: none;
        color: #212529;
        text-decoration: none;
        display: block;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    #searchSuggestions .dropdown-item:hover,
    #searchSuggestions .dropdown-item.active {
        background-color: #f8f9fa;
        color: #495057;
    }

    /* تحسين مظهر النتائج في الـ Modal */
    .search-result-card {
        border: 1px solid #e3e6f0;
        border-radius: 0.35rem;
        transition: all 0.3s;
        margin-bottom: 1rem;
    }

    .search-result-card:hover {
        border-color: #5a5c69;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }

    /* تحسين شاشة التحميل في الـ Modal */
    #loadingOverlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1060;
        backdrop-filter: blur(2px);
    }

    /* تحسين الأزرار */
    .btn-search {
        background: linear-gradient(45deg, #4e73df, #224abe);
        border: none;
        color: white;
        transition: all 0.3s;
    }

    .btn-search:hover {
        transform: translateY(-1px);
        box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
        color: white;
    }

    /* تحسين الجداول في الـ Modal */
    .table-responsive {
        border-radius: 0.35rem;
        overflow: hidden;
        max-height: 400px;
        overflow-y: auto;
    }

    .table th {
        background-color: #f8f9fc;
        border-color: #e3e6f0;
        font-weight: 600;
        color: #5a5c69;
        font-size: 0.85rem;
        padding: 0.75rem;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .table td {
        border-color: #e3e6f0;
        padding: 0.5rem;
        vertical-align: middle;
        font-size: 0.875rem;
    }

    /* تحسين المرشحات المتقدمة */
    .collapse {
        border-top: 1px solid #e3e6f0;
        margin-top: 1rem;
        padding-top: 1rem;
    }

    /* تحسين responsive للـ Modal */
    @media (max-width: 768px) {
        .table-responsive {
            font-size: 0.75rem;
            max-height: 300px;
        }

        .table th,
        .table td {
            padding: 0.5rem 0.25rem;
        }
    }
</style>

<!-- نموذج البحث -->
<form id="searchForm" class="mb-4">
    @csrf
    <div class="row">
        <!-- نوع البحث -->
        <div class="col-md-4 mb-3">
            <label for="search_type" class="form-label">نوع البحث</label>
            <select class="form-select" id="search_type" name="search_type" required>
                <option value="all">جميع الجداول</option>
                <option value="main_records">السجلات الرئيسية</option>
                <option value="family_members">أفراد الأسرة</option>
                <option value="deceased">المتوفين</option>
            </select>
        </div>

        <!-- البحث النصي العام -->
        <div class="col-md-6 mb-3">
            <label for="search_text" class="form-label">البحث العام</label>
            <div class="position-relative">
                <input type="text" class="form-control" id="search_text" name="search_text"
                       placeholder="ابحث بالاسم، رقم الهوية، رقم الجوال، رقم الملف..."
                       autocomplete="off">
                <div id="searchSuggestions" class="dropdown-menu w-100" style="display: none; max-height: 200px; overflow-y: auto;"></div>
            </div>
        </div>

        <!-- عدد النتائج في الصفحة -->
        <div class="col-md-2 mb-3">
            <label for="per_page" class="form-label">عدد النتائج</label>
            <select class="form-select" id="per_page" name="per_page">
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

    <!-- مرشحات متقدمة -->
    <div class="row">
        <div class="col-12">
            <button type="button" class="btn btn-outline-secondary btn-sm mb-3"
                    data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                <i class="fas fa-filter"></i> مرشحات متقدمة
            </button>
        </div>
    </div>

    <div class="collapse" id="advancedFilters">
        <div class="row">
            <!-- رقم الملف -->
            <div class="col-md-3 mb-3">
                <label for="file_id" class="form-label">رقم الملف</label>
                <input type="text" class="form-control" id="file_id" name="file_id">
            </div>

            <!-- رقم الهوية -->
            <div class="col-md-3 mb-3">
                <label for="identity_number" class="form-label">رقم الهوية</label>
                <input type="text" class="form-control" id="identity_number" name="identity_number">
            </div>

            <!-- رقم الجوال -->
            <div class="col-md-3 mb-3">
                <label for="phone_number" class="form-label">رقم الجوال</label>
                <input type="text" class="form-control" id="phone_number" name="phone_number">
            </div>

            <!-- الجنس -->
            <div class="col-md-3 mb-3">
                <label for="gender" class="form-label">الجنس</label>
                <select class="form-select" id="gender" name="gender">
                    <option value="">اختر الجنس</option>
                    <option value="male">ذكر</option>
                    <option value="female">أنثى</option>
                </select>
            </div>

            <!-- القسم -->
            <div class="col-md-3 mb-3">
                <label for="section_id" class="form-label">القسم</label>
                <select class="form-select" id="section_id" name="section_id">
                    <option value="">اختر القسم</option>
                    @if(isset($sections))
                        @foreach($sections as $section)
                            <option value="{{ $section->id }}">{{ $section->description }}</option>
                        @endforeach
                    @endif
                </select>
            </div>

            <!-- المدينة -->
            <div class="col-md-3 mb-3">
                <label for="city_id" class="form-label">المدينة</label>
                <select class="form-select" id="city_id" name="city_id">
                    <option value="">اختر المدينة</option>
                    @if(isset($cities))
                        @foreach($cities as $city)
                            <option value="{{ $city->id }}">{{ $city->city }}</option>
                        @endforeach
                    @endif
                </select>
            </div>

            <!-- تاريخ الميلاد من -->
            <div class="col-md-3 mb-3">
                <label for="birth_date_from" class="form-label">تاريخ الميلاد من</label>
                <input type="date" class="form-control" id="birth_date_from" name="birth_date_from">
            </div>

            <!-- تاريخ الميلاد إلى -->
            <div class="col-md-3 mb-3">
                <label for="birth_date_to" class="form-label">تاريخ الميلاد إلى</label>
                <input type="date" class="form-control" id="birth_date_to" name="birth_date_to">
            </div>
        </div>
    </div>

    <!-- أزرار التحكم -->
    <div class="row mt-4">
        <div class="col-12 text-center">
            <button type="submit" class="btn btn-search btn-primary">
                <i class="fas fa-search"></i> بحث
            </button>
            <button type="button" class="btn btn-secondary ms-2" id="clearForm">
                <i class="fas fa-eraser"></i> مسح
            </button>
            <button type="button" class="btn btn-info ms-2" id="testConnection">
                <i class="fas fa-wifi"></i> اختبار الاتصال
            </button>
        </div>
    </div>
</form>

<script>
// اختبار سريع للاتصال
$(document).ready(function() {
    $('#testConnection').on('click', function() {
        console.log('Testing connection...');

        $.ajax({
            url: '{{ route("search.records") }}',
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                search_type: 'all',
                search_text: 'test'
            },
            success: function(response) {
                console.log('Connection test successful:', response);
                alert('الاتصال يعمل بشكل صحيح!');
            },
            error: function(xhr) {
                console.error('Connection test failed:', xhr);
                alert('فشل في الاتصال: ' + (xhr.responseJSON?.message || xhr.statusText));
            }
        });
    });
});
</script>

<!-- نتائج البحث -->
<div id="searchResults" style="display: none;">
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">نتائج البحث</h5>
        </div>
        <div class="card-body">
            <!-- سيتم عرض النتائج هنا -->
        </div>
    </div>
</div>

<!-- شاشة التحميل للـ Modal -->
<div id="loadingOverlay" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
     background: rgba(255,255,255,0.8); z-index: 1000; border-radius: 0.375rem;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
         background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">جاري البحث...</span>
        </div>
        <p class="mt-2 mb-0">جاري البحث في السجلات...</p>
    </div>
</div>

</div> <!-- إغلاق wrapper -->
