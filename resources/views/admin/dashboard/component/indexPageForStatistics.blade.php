@extends('admin.dashboard.toolbars.index')
@section('content')
@include('admin.dashboard.layout.styleIndexPage')
    <div class="row g-4" style="padding-right:2rem; padding-left:2rem;">
        <!-- كارد الأفراد -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card text-center shadow h-100">
                <div class="card-body">
                    <div class="mb-2">
                        <i class="bi bi-people-fill fs-1 text-primary"></i>
                    </div>
                    <h5 class="card-title">عدد الأفراد</h5>
                    <p class="card-text fs-3 fw-bold"><span class="animated-counter-en"
                            data-target="{{ \App\Models\RePeople::count() }}">0</span></p>
                </div>
            </div>
        </div>
        <!-- كارد البيانات -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card text-center shadow h-100">
                <div class="card-body">
                    <div class="mb-2">
                        <i class="bi bi-person-badge-fill fs-1 text-success"></i>
                    </div>
                    <h5 class="card-title">عدد اولياء الأمور </h5>
                    <p class="card-text fs-3 fw-bold"><span class="animated-counter-en"
                            data-target="{{ \App\Models\Data::count() }}">0</span></p>
                </div>
            </div>
        </div>
        <!-- كارد المتوفين -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card text-center shadow h-100">
                <div class="card-body">
                    <div class="mb-2">
                        <i class="bi bi-person-x-fill fs-1 text-danger"></i>
                    </div>
                    <h5 class="card-title">عدد المتوفين</h5>
                    <p class="card-text fs-3 fw-bold"><span class="animated-counter-en"
                            data-target="{{ \App\Models\DeadPepole::count() }}">0</span></p>
                </div>
            </div>
        </div>
        <!-- كارد المرفقات -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card text-center shadow h-100">
                <div class="card-body">
                    <div class="mb-2">
                        <i class="bi bi-paperclip fs-1 text-warning"></i>
                    </div>
                    <h5 class="card-title">عدد المرفقات</h5>
                    <p class="card-text fs-3 fw-bold"><span class="animated-counter-en"
                            data-target="{{ \App\Models\Attachment::count() }}">0</span></p>
                </div>
            </div>
        </div>

        @php
        use App\Models\GeneralCategory;
        use App\Models\Data;
        $categories = GeneralCategory::all();
        $today = now()->format('Y-m-d');
        $year  = now()->format('Y');
    @endphp

    {{-- ===== إحصائيات التصنيفات العامة ===== --}}
    <div class="col-12 px-2 mt-2">
        <h4 class="mb-3 px-2">إحصائيات التصنيفات العامة</h4>
        <div class="cat-grid">
            @forelse($categories as $category)
                @php
                    $cAll   = Data::where('data_section_id', $category->id)->count();
                    $cToday = Data::where('data_section_id', $category->id)->whereDate('created_at', $today)->count();
                    $cMonth = Data::where('data_section_id', $category->id)->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();
                    $cYear  = Data::where('data_section_id', $category->id)->whereYear('created_at', now()->year)->count();
                @endphp
                <div class="cat-grid-item">
                    <div class="card shadow-sm h-100 cat-card">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fa fa-layer-group fa-xl text-info me-2 flex-shrink-0"></i>
                                <h6 class="card-title mb-0 fw-bold text-truncate">{{ $category->description }}</h6>
                            </div>
                            <ul class="list-group list-group-flush flex-grow-1">
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span class="text-muted small">إجمالي السجلات</span>
                                    <span class="badge bg-primary rounded-pill cat-counter" data-target="{{ $cAll }}">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span class="text-muted small">اليوم</span>
                                    <span class="badge bg-success rounded-pill cat-counter" data-target="{{ $cToday }}">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span class="text-muted small">هذا الشهر</span>
                                    <span class="badge bg-info rounded-pill cat-counter" data-target="{{ $cMonth }}">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span class="text-muted small">هذه السنة</span>
                                    <span class="badge bg-warning text-dark rounded-pill cat-counter" data-target="{{ $cYear }}">0</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="alert alert-info">لا يوجد تصنيفات عامة.</div></div>
            @endforelse
        </div>
    </div>

    <style>
    /* ===== CSS Grid للتصنيفات - يتكيف تلقائياً بدون slider ===== */
    .cat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
        gap: 1rem;
        padding: 0 0.5rem;
        width: 100%;
    }
    .cat-grid-item {
        min-width: 0;
    }
    .cat-card {
        border-radius: 12px;
        transition: box-shadow 0.2s, transform 0.2s;
        border: 1px solid rgba(0,0,0,.07);
    }
    .cat-card:hover {
        box-shadow: 0 6px 24px rgba(0,0,0,.10) !important;
        transform: translateY(-2px);
    }
    .cat-card .list-group-item {
        border-left: none;
        border-right: none;
    }
    .cat-card .list-group-item:first-child { border-top: none; }
    .cat-card .list-group-item:last-child  { border-bottom: none; }
    @media (max-width: 575.98px) {
        .cat-grid { grid-template-columns: 1fr 1fr; gap: 0.6rem; }
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        function animateCatCounter(el) {
            var target = parseInt(el.getAttribute('data-target'), 10);
            if (isNaN(target) || target === 0) { el.textContent = '0'; return; }
            var duration = 1000, start = null;
            function step(ts) {
                if (!start) start = ts;
                var p = Math.min((ts - start) / duration, 1);
                el.textContent = Math.floor(p * target).toLocaleString('en-US');
                if (p < 1) { requestAnimationFrame(step); }
                else { el.textContent = target.toLocaleString('en-US'); }
            }
            requestAnimationFrame(step);
        }
        document.querySelectorAll('.cat-counter').forEach(animateCatCounter);
    });
    </script>
    @php
    $admins = \App\Models\User::where('role', 'admin')->latest()->take(10)->get(); // جلب آخر 10 موظفين فقط
    $today = now()->format('Y-m-d');
    $month = now()->format('Y-m');
    $year = now()->format('Y');
    @endphp
     <h4 class="mb-3">تقرير نشاط الموظفين (Admins)</h4>
    <div id="admin-cards-carousel-wrapper" class="admin-cards-carousel-wrapper-responsive">
        <div id="admin-cards-slider-wrapper" class="slider-wrapper">
            <button id="admin-slider-prev-btn" class="slider-nav-btn" style="left:0.5rem;" aria-label="السابق">&#8592;</button>
            <button id="admin-slider-next-btn" class="slider-nav-btn" style="right:0.5rem;" aria-label="التالي">&#8594;</button>
            <div class="admin-cards-row-responsive slider-row" id="admin-cards-row">
            @forelse($admins as $admin)
                @php
                    // ✅ استخدام اسم المستخدم لأن data_user_insert_data يحتوي على الاسم الآن
                    $userName = $admin->name;
                    $countToday = \App\Models\Data::where('data_user_insert_data', $userName)
                        ->whereDate('created_at', $today)->count();
                    $countMonth = \App\Models\Data::where('data_user_insert_data', $userName)
                        ->whereYear('created_at', now()->year)
                        ->whereMonth('created_at', now()->month)->count();
                    $countYear = \App\Models\Data::where('data_user_insert_data', $userName)
                        ->whereYear('created_at', now()->year)->count();
                @endphp
                <div class="slider-card admin-carousel-card admin-carousel-card-responsive">
                    <div class="card shadow-sm h-100" style="min-width: 0;">
                        <div class="card-body d-flex flex-column justify-content-between" style="min-width:0;">
                            <div class="d-flex align-items-center mb-2 flex-wrap flex-md-nowrap text-center text-md-start">
                                <i class="fa fa-user-shield fa-2x text-primary me-2 mb-2 mb-md-0"></i>
                                <div class="flex-fill" style="min-width:0;">
                                    <h5 class="card-title mb-0 text-truncate">{{ $admin->name }}</h5>
                                    <small class="text-muted text-truncate d-block">{{ $admin->email }}</small>
                                </div>
                            </div>
                            <ul class="list-group list-group-flush mb-3">
                                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <span>عدد السجلات اليوم</span>
                                    <span class="badge bg-success animated-counter-admin" data-target="{{ $countToday }}">0</span>
                                </li>
                                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <span>عدد السجلات هذا الشهر</span>
                                    <span class="badge bg-info animated-counter-admin" data-target="{{ $countMonth }}">0</span>
                                </li>
                                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <span>عدد السجلات هذه السنة</span>
                                    <span class="badge bg-warning animated-counter-admin" data-target="{{ $countYear }}">0</span>
                                </li>
                            </ul>
                            <div class="mt-2 d-flex flex-wrap align-items-center gap-2 justify-content-between">
                                <span class="badge bg-secondary">الرتبة: {{ $admin->role }}</span>
                                <span class="badge bg-light text-dark">ID: {{ $admin->id }}</span>
                                <button type="button" class="btn btn-outline-primary btn-sm admin-records-btn mt-2 mt-md-0" data-admin-id="{{ $admin->id }}" data-admin-name="{{ $admin->name }}" data-bs-toggle="modal" data-bs-target="#adminRecordsModal" >
                                    <i class="bi bi-list-ul"></i> عرض السجلات
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-info">لا يوجد موظفون من نوع admin.</div>
                </div>
            @endforelse
            </div>
            <div class="slider-dots" id="admin-slider-dots"></div>
        </div>
    </div>

    <style>
    /* ========== CLEAN SLIDER STYLES - FIXED VERSION ========== */

    /* Slider dots pagination */
    .slider-dots {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin: 1rem 0;
        padding: 0.5rem 0;
    }
    .slider-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #d0d0d0;
        transition: all 0.3s ease;
        cursor: pointer;
        border: 2px solid transparent;
    }
    .slider-dot:hover {
        background: #a0a0a0;
        transform: scale(1.1);
    }
    .slider-dot.active {
        background: #0d6efd;
        border-color: #0a58ca;
        transform: scale(1.2);
    }

    /* Slider wrapper - with overflow hidden for carousel effect */
    .slider-wrapper {
        position: relative;
        width: 100%;
        padding: 2rem 0;
        margin: 0 auto;
        overflow: hidden;
        min-height: 300px;
    }

    @media (min-width: 768px) {
        .slider-wrapper {
            min-height: 350px;
        }
    }

    @media (min-width: 1200px) {
        .slider-wrapper {
            min-height: 380px;
            padding: 2.5rem 0;
        }
    }

    /* Slider row container - CLEAN FLEX */
    .slider-row {
        display: flex;
        flex-direction: row;
        transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        will-change: transform;
        touch-action: pan-y;
        align-items: stretch;
    }

    /* Base slider card - REMOVED PROBLEMATIC SCALE */
    .slider-card {
        flex-shrink: 0;
        box-sizing: border-box;
        transition: opacity 0.3s ease;
    }

    /* REMOVED: Active card transform scale that was causing clipping */
    .slider-card.active {
        opacity: 1;
    }

    /* Mobile: 1 card full width */
    .slider-card {
        flex: 0 0 100%;
        max-width: 100%;
        padding: 0.5rem 0.75rem;
    }

    /* Tablet: 2 cards per view */
    @media (min-width: 768px) {
        .slider-card {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0.75rem 1rem;
        }
    }

    /* Desktop: 4 cards per view with proper spacing */
    @media (min-width: 1200px) {
        .slider-card {
            flex: 0 0 25%;
            max-width: 25%;
            padding: 1rem 1.25rem;
        }
    }

    /* Slider navigation buttons */
    .slider-nav-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        z-index: 15;
        background: #ffffff;
        border: 2px solid #dee2e6;
        border-radius: 50%;
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        color: #495057;
        box-shadow: 0 3px 12px rgba(0,0,0,0.15);
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .slider-nav-btn:hover {
        background: #0d6efd;
        color: #ffffff;
        border-color: #0d6efd;
        transform: translateY(-50%) scale(1.1);
    }
    .slider-nav-btn:active {
        transform: translateY(-50%) scale(0.95);
    }

    @media (max-width: 767.98px) {
        .slider-nav-btn { display: none !important; }
        .slider-dots { margin: 0.5rem 0 1rem; }
    }

    /* Category stats container - wider on large screens */
    @media (min-width: 992px) {
        .category-stats-responsive.container {
            max-width: 1800px !important;
        }
    }

    /* Admin cards specific adjustments */
    .admin-cards-row-responsive.slider-row {
        margin-top: 0;
        margin-bottom: 0;
        padding-top: 0;
        padding-bottom: 0;
    }

    /* Ensure cards display properly with proper centering */
    .category-carousel-card, .admin-carousel-card {
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .category-carousel-card .card,
    .admin-carousel-card .card {
        height: 100%;
        border-radius: 0.5rem;
        overflow: hidden;
        transition: transform 0.3s ease;
    }

    .category-carousel-card .card:hover,
    .admin-carousel-card .card:hover {
        transform: translateY(-2px);
    }
    </style>
        </div>
        <!-- أزرار التنقل (تظهر فقط على الجوال) -->
        <button id="carousel-prev-btn" class="carousel-nav-btn d-md-none d-lg-none" style="display:none;" aria-label="السابق">&#8592;</button>
        <button id="carousel-next-btn" class="carousel-nav-btn d-md-none d-lg-none" style="display:none;" aria-label="التالي">&#8594;</button>
        <!-- Pagination للكمبيوتر فقط (تم التعطيل لأننا نعرض كل الكروت دفعة واحدة) -->
        <!-- <div class="d-none d-md-block mt-3">
        </div> -->
    </div>


    </div>



    <!-- كروت نشاط الموظفين (Admins) -->
    <!-- مودال عرض السجلات (ثابت في الصفحة) -->
    <div class="modal fade" id="adminRecordsModal" tabindex="-1" aria-labelledby="adminRecordsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminRecordsModalLabel">سجلات الموظف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div id="admin-records-table-area" class="w-100 text-center py-4">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">جاري
                                التحميل...</span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animated counter logic (English, with shine effect on the number itself)
            function animateCounterEn(el, target, duration = 1200) {
                let start = 0;
                let startTime = null;
                target = parseInt(target);
                if (isNaN(target)) return;

                function step(timestamp) {
                    if (!startTime) startTime = timestamp;
                    let progress = Math.min((timestamp - startTime) / duration, 1);
                    let value = Math.floor(progress * (target - start) + start);
                    el.textContent = value.toLocaleString('en-US');
                    if (progress < 1) {
                        requestAnimationFrame(step);
                    } else {
                        el.textContent = target.toLocaleString('en-US');
                        el.classList.add('shine');
                        setTimeout(() => {
                            el.classList.remove('shine');
                        }, 900);
                    }
                }
                requestAnimationFrame(step);
            }
            document.querySelectorAll('.animated-counter-en').forEach(function(el) {
                let target = el.getAttribute('data-target');
                animateCounterEn(el, target);
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animated counter logic (English, with shine effect on the number itself)
            function animateCounterEn(el, target, duration = 1200) {
                let start = 0;
                let startTime = null;
                target = parseInt(target);
                if (isNaN(target)) return;

                function step(timestamp) {
                    if (!startTime) startTime = timestamp;
                    let progress = Math.min((timestamp - startTime) / duration, 1);
                    let value = Math.floor(progress * (target - start) + start);
                    el.textContent = value.toLocaleString('en-US');
                    if (progress < 1) {
                        requestAnimationFrame(step);
                    } else {
                        el.textContent = target.toLocaleString('en-US');
                        el.classList.add('shine');
                        setTimeout(() => {
                            el.classList.remove('shine');
                        }, 900);
                    }
                }
                requestAnimationFrame(step);
            }
            // Main statistics cards (English + shine)
            document.querySelectorAll('.animated-counter-en').forEach(function(el) {
                let target = el.getAttribute('data-target');
                animateCounterEn(el, target);
            });
            // Admin activity cards (English + shine)
            document.querySelectorAll('.animated-counter-admin').forEach(function(el) {
                let target = el.getAttribute('data-target');
                animateCounterEn(el, target);
            });
            // جلب سجلات الموظف عند الضغط على زر "عرض السجلات"
            document.querySelectorAll('.admin-records-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    var adminId = btn.getAttribute('data-admin-id');
                    var area = document.getElementById('admin-records-table-area');
                    area.innerHTML =
                        '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">جاري التحميل...</span></div>';
                    fetch('/admin/ajax/admin-records/' + adminId)
                        .then(function(response) {
                            return response.json();
                        })
                        .then(function(data) {
                            if (data.success) {
                                area.innerHTML = data.html;
                                bindAdminRecordsPagination(adminId);
                            } else {
                                area.innerHTML = '<div class="alert alert-warning">' + (data
                                    .message || 'لا توجد سجلات.') + '</div>';
                            }
                        })
                        .catch(function() {
                            area.innerHTML =
                                '<div class="alert alert-danger">حدث خطأ أثناء جلب البيانات.</div>';
                        });
                });
            });

            // دعم التنقل بين صفحات الجدول داخل المودال
            function bindAdminRecordsPagination(adminId) {
                var area = document.getElementById('admin-records-table-area');
                var pagers = area.querySelectorAll('.admin-records-pagination a.page-link[data-page]');
                pagers.forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        var page = link.getAttribute('data-page');
                        area.innerHTML =
                            '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">جاري التحميل...</span></div>';
                        fetch('/admin/ajax/admin-records/' + adminId + '?page=' + page)
                            .then(function(response) {
                                return response.json();
                            })
                            .then(function(data) {
                                if (data.success) {
                                    area.innerHTML = data.html;
                                    bindAdminRecordsPagination(adminId);
                                } else {
                                    area.innerHTML = '<div class="alert alert-warning">' + (data
                                        .message || 'لا توجد سجلات.') + '</div>';
                                }
                            })
                            .catch(function() {
                                area.innerHTML =
                                    '<div class="alert alert-danger">حدث خطأ أثناء جلب البيانات.</div>';
                            });
                    });
                });
            }
        });
    </script>

    <!-- رسم بياني لمعدل التسجيل الشهري -->
    @php
        use Illuminate\Support\Facades\DB;
        $months = collect(range(0, 11))
            ->map(function ($i) {
                return now()->subMonths($i)->format('Y-m');
            })
            ->reverse()
            ->values();

        if (!function_exists('getMonthlyCounts')) {
            function getMonthlyCounts($table, $dateCol)
            {
                return DB::table($table)
                    ->selectRaw("DATE_FORMAT($dateCol, '%Y-%m') as month, COUNT(*) as count")
                    ->where($dateCol, '>=', now()->subMonths(11)->startOfMonth())
                    ->groupBy('month')
                    ->pluck('count', 'month');
            }
        }

        $rePeopleCounts = getMonthlyCounts('re_people', 'created_at');
        $dataCounts = getMonthlyCounts('data', 'created_at');
        $deadPeopleCounts = getMonthlyCounts('dead_people', 'created_at');
        $attachmentsCounts = getMonthlyCounts('attachments', 'created_at');
    @endphp



    <!-- حاوية واحدة للشارتات مع margin خارجي -->
    <div class="statistics-charts-wrapper my-4" style="margin-right:2rem; margin-left:2rem;">
        <div class="container-fluid px-0" style="padding-right:3.5rem; padding-left:3.5rem;">
            <div class="row justify-content-center align-items-stretch gx-4 gy-4">
                <!-- شارت اليوم الواحد (دائري) -->
                <div class="col-12 col-lg-5 d-flex align-items-stretch">
                    <div class="card flex-fill h-100 w-100 p-4">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <h5 class="card-title text-center mb-4">إحصائيات التسجيل اليومي ({{ now()->format('Y-m-d') }})
                            </h5>
                            <canvas id="todayPieChart" height="180"></canvas>
                        </div>
                    </div>
                </div>
                <!-- شارت معدل التسجيل الشهري -->
                <div class="col-12 col-lg-7 d-flex align-items-stretch">
                    <div id="chartContainer" class="card flex-fill h-100 w-100 p-4 chart-container-enhanced"
                        style="max-width: 100%; transition: all 0.3s; position: relative; z-index: 1;">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">معدل التسجيل الشهري (آخر 12 شهرًا)</h5>
                                <button id="expandChartBtn" class="btn btn-outline-primary btn-sm" title="تكبير/تصغير"
                                    style="min-width: 40px;">
                                    <i class="bi bi-arrows-fullscreen"></i>
                                </button>
                            </div>
                            <div class="chart-responsive-wrapper">
                                <canvas id="monthlyChart" height="260"
                                    style="max-width:100%; image-rendering:pixelated;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
@include('admin.dashboard.component.sliderScript')
@include('admin.dashboard.javascript.chartsComponent')
@endsection
