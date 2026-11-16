@php($searchId = 'records-search-' . \Illuminate\Support\Str::random(8))
<div class="records-search" data-record-search
     data-search-url="{{ route('search.records') }}"
     data-suggestions-url="{{ route('search.suggestions') }}"
     data-stats-url="{{ route('search.stats') }}"
     data-clear-url="{{ route('search.clear.cache') }}" dir="rtl">
    <style>
        .records-search [hidden] { display: none !important; }
        .records-search .search-suggestions { position: absolute; top: 100%; right: 0; left: 0; z-index: 1070; max-height: 230px; overflow-y: auto; }
        .records-search .search-table { min-width: 1050px; }
        .records-search .search-table th { white-space: nowrap; }
        .records-search .search-table td { vertical-align: middle; }
        .records-search .search-address { max-width: 180px; overflow-wrap: anywhere; }
        .records-search .search-details { background: #f8f9fa; }
        .records-search .search-details dl { display: grid; grid-template-columns: minmax(120px, auto) 1fr; gap: .4rem 1rem; margin: 0; }
        .records-search .search-details dd { margin: 0; overflow-wrap: anywhere; }
    </style>
    <form data-role="form" novalidate>
        @csrf
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label w-100">نوع البحث
                    <select name="search_type" class="form-select">
                        <option value="all">جميع الجداول</option>
                        <option value="main_records">السجلات الرئيسية</option>
                        <option value="family_members">أفراد الأسرة</option>
                        <option value="deceased">المتوفون</option>
                    </select>
                </label>
            </div>
            <div class="col-md-6">
                <label class="form-label w-100">البحث العام
                    <span class="position-relative d-block">
                        <input name="search_text" type="search" class="form-control" maxlength="255" autocomplete="off" value="{{ $initialSearchText ?? '' }}"
                               placeholder="الاسم، الهوية، الجوال الأساسي أو البديل، رقم الملف"
                               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $searchId }}-suggestions">
                        <span id="{{ $searchId }}-suggestions" data-role="suggestions" class="search-suggestions list-group shadow" role="listbox" hidden></span>
                    </span>
                </label>
            </div>
            <div class="col-md-3">
                <label class="form-label w-100">عدد النتائج في الصفحة
                    <select name="per_page" class="form-select">
                        <option value="25">25</option><option value="50">50</option><option value="100">100</option>
                    </select>
                </label>
            </div>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm mb-3" data-role="filters-toggle" aria-expanded="false" aria-controls="{{ $searchId }}-filters">
            <i class="fas fa-filter" aria-hidden="true"></i> مرشحات متقدمة
        </button>
        <div id="{{ $searchId }}-filters" data-role="filters" hidden>
            <div class="row g-3 mb-3">
                <div class="col-md-3"><label class="form-label w-100">رقم الملف<input name="file_id" class="form-control" maxlength="50"></label></div>
                <div class="col-md-3"><label class="form-label w-100">رقم الهوية<input name="identity_number" class="form-control" inputmode="numeric" maxlength="50"></label></div>
                <div class="col-md-3"><label class="form-label w-100">رقم الجوال<input name="phone_number" type="tel" class="form-control" maxlength="50" placeholder="الأساسي أو البديل — 4 أرقام على الأقل"></label></div>
                <div class="col-md-3">
                    <label class="form-label w-100">الجنس<select name="gender" class="form-select"><option value="">الكل</option><option value="male">ذكر</option><option value="female">أنثى</option></select></label>
                </div>
                @foreach([
                    ['section_id', 'القسم', $sections, 'description', 'all'],
                    ['city_id', 'المدينة', $cities, 'city', 'all'],
                    ['request_status_id', 'حالة الطلب', $requestStatuses, 'description', 'all'],
                    ['health_status_id', 'الحالة الصحية', $healthStatuses, 'description', 'main_records family_members'],
                    ['relationship_id', 'صلة القرابة', $relationships, 'attribute', 'main_records'],
                    ['marital_status_id', 'الحالة الاجتماعية', $maritalStatuses, 'CI_PERSONAL_CD', 'main_records'],
                    ['academic_qualification_id', 'المؤهل العلمي', $academicQualifications, 'description', 'main_records'],
                    ['employment_status_id', 'حالة العمل', $employmentStatuses, 'description', 'main_records'],
                ] as [$field, $label, $options, $textField, $supported])
                    <div class="col-md-3" data-supported="{{ $supported }}">
                        <label class="form-label w-100">{{ $label }}
                            <select name="{{ $field }}" class="form-select">
                                <option value="">{{ $field === 'request_status_id' ? 'كل الحالات' : 'الكل' }}</option>
                                @foreach($options as $option)
                                    <option value="{{ $option->id }}" @selected($field === 'request_status_id' && $option->id == $defaultRequestStatusId)>{{ $option->{$textField} }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                @endforeach
                @foreach(['birth_date' => ['تاريخ الميلاد', 'main_records family_members'], 'death_date' => ['تاريخ الوفاة', 'deceased']] as $field => [$label, $supported])
                    <div class="col-md-3" data-supported="{{ $supported }}"><label class="form-label w-100">{{ $label }} من<input name="{{ $field }}_from" type="date" class="form-control"></label></div>
                    <div class="col-md-3" data-supported="{{ $supported }}"><label class="form-label w-100">{{ $label }} إلى<input name="{{ $field }}_to" type="date" class="form-control"></label></div>
                @endforeach
            </div>
        </div>
        <p class="small text-muted mb-2" data-role="scope-note" aria-live="polite"></p>
        <p class="small text-muted">الجوال والقسم والمدينة وحالة الطلب لأفراد الأسرة والمتوفين تُؤخذ من ملف المعيل. النتائج تُعد حسب السجل والشخص؛ قد يظهر الشخص بأكثر من صفة.</p>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <button type="submit" class="btn btn-primary" data-role="submit"><i class="fas fa-search" aria-hidden="true"></i> بحث</button>
            <button type="reset" class="btn btn-secondary">مسح المرشحات</button>
            <button type="button" class="btn btn-outline-info" data-role="stats">إحصائيات السجلات</button>
            <button type="button" class="btn btn-outline-warning" data-role="clear-cache">تحديث ذاكرة البحث</button>
        </div>
    </form>
    <div data-role="message" class="alert" role="status" aria-live="polite" hidden></div>
    <div data-role="stats-panel" class="alert alert-info" role="status" hidden></div>
    <div data-role="loading" class="text-center py-3" role="status" hidden><span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span> جارٍ البحث…</div>
    <div data-role="results" class="card mt-3" hidden>
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 text-white">نتائج البحث</h5><span data-role="count" class="badge bg-light text-dark"></span>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 search-table">
                <thead><tr><th>الاسم الكامل</th><th>رقم الهوية</th><th>رقم الملف</th><th>الجوال</th><th>العمر</th><th>الجنس</th><th>المدينة</th><th>العنوان</th><th>حالة الطلب</th><th>نوع السجل</th><th>الإجراءات</th></tr></thead>
                <tbody data-role="rows"></tbody>
            </table>
        </div>
        <nav data-role="pagination" class="p-3 border-top" aria-label="صفحات نتائج البحث"></nav>
    </div>
</div>
