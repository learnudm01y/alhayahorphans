<!-- Modal البحث المتقدم -->
<div class="modal fade" id="advancedSearchModal" tabindex="-1" aria-labelledby="advancedSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="advancedSearchModalLabel">
                    <i class="bi bi-search me-2"></i>
                    البحث المتقدم في قاعدة بيانات المواطنين
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <!-- نموذج البحث -->
                <form id="advancedSearchForm">
                    <div class="row g-3 mb-4">
                        <!-- البحث الشامل -->
                        <div class="col-12">
                            <label for="search_term" class="form-label fw-bold">
                                <i class="bi bi-search me-1"></i>
                                البحث الشامل
                            </label>
                            <div class="input-group search-input-group">
                                <input type="text" class="form-control" id="search_term" name="search_term"
                                       placeholder="ابحث بالاسم الكامل أو جزء منه، رقم الهوية، اسم الأم، العنوان..."
                                       autocomplete="off">
                                <button class="btn btn-outline-primary search-btn" type="button" id="quickSearchBtn">
                                    <i class="bi bi-lightning-fill"></i>
                                    بحث سريع
                                </button>
                            </div>
                            <div class="form-text">
                                يمكنك البحث بالاسم الكامل أو أجزاء منه (مثل: أحمد محمد، أحمد العلي، محمد أحمد، إلخ)
                            </div>
                        </div>
                    </div>

                    <!-- الفلاتر المحددة -->
                    <div class="border-top pt-3">
                        <h6 class="fw-bold mb-3">
                            <i class="bi bi-funnel me-1"></i>
                            فلاتر البحث المحددة
                        </h6>
                        <div class="row g-3 search-filters">
                            <!-- رقم الهوية -->
                            <div class="col-md-3">
                                <label for="ci_id_num" class="form-label">رقم الهوية</label>
                                <input type="text" class="form-control" id="ci_id_num" name="ci_id_num"
                                       placeholder="رقم الهوية">
                            </div>

                            <!-- الاسم الأول -->
                            <div class="col-md-3">
                                <label for="first_name" class="form-label">الاسم الأول</label>
                                <input type="text" class="form-control" id="first_name" name="first_name"
                                       placeholder="الاسم الأول">
                            </div>

                            <!-- اسم الأب -->
                            <div class="col-md-3">
                                <label for="father_name" class="form-label">اسم الأب</label>
                                <input type="text" class="form-control" id="father_name" name="father_name"
                                       placeholder="اسم الأب">
                            </div>

                            <!-- اسم الجد -->
                            <div class="col-md-3">
                                <label for="grandfather_name" class="form-label">اسم الجد</label>
                                <input type="text" class="form-control" id="grandfather_name" name="grandfather_name"
                                       placeholder="اسم الجد">
                            </div>

                            <!-- اسم العائلة -->
                            <div class="col-md-3">
                                <label for="family_name" class="form-label">اسم العائلة</label>
                                <input type="text" class="form-control" id="family_name" name="family_name"
                                       placeholder="اسم العائلة">
                            </div>

                            <!-- اسم الأم -->
                            <div class="col-md-3">
                                <label for="mother_name" class="form-label">اسم الأم</label>
                                <input type="text" class="form-control" id="mother_name" name="mother_name"
                                       placeholder="اسم الأم">
                            </div>

                            <!-- الجنس -->
                            <div class="col-md-3">
                                <label for="gender" class="form-label">الجنس</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">جميع الأجناس</option>
                                    <option value="1">ذكر</option>
                                    <option value="2">أنثى</option>
                                </select>
                            </div>

                            <!-- المدينة -->
                            <div class="col-md-3">
                                <label for="city" class="form-label">المدينة</label>
                                <select class="form-select" id="city" name="city">
                                    <option value="">جميع المدن</option>
                                    @foreach($cities ?? [] as $city)
                                        <option value="{{ $city->id }}">{{ $city->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- تاريخ الميلاد من -->
                            <div class="col-md-3">
                                <label for="birth_date_from" class="form-label">تاريخ الميلاد من</label>
                                <input type="date" class="form-control" id="birth_date_from" name="birth_date_from">
                            </div>

                            <!-- تاريخ الميلاد إلى -->
                            <div class="col-md-3">
                                <label for="birth_date_to" class="form-label">تاريخ الميلاد إلى</label>
                                <input type="date" class="form-control" id="birth_date_to" name="birth_date_to">
                            </div>

                            <!-- الحالة الاجتماعية -->
                            <div class="col-md-3">
                                <label for="social_status" class="form-label">الحالة الاجتماعية</label>
                                <select class="form-select" id="social_status" name="social_status">
                                    <option value="">جميع الحالات</option>
                                    @foreach($socialStatuses ?? [] as $status)
                                        <option value="{{ $status->id }}">{{ $status->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- أزرار الإجراء -->
                    <div class="border-top pt-3 mt-4">
                        <div class="row g-2">
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-1"></i>
                                    بحث
                                </button>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-outline-secondary" id="clearSearchBtn">
                                    <i class="bi bi-arrow-clockwise me-1"></i>
                                    مسح
                                </button>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-outline-info" id="getStatisticsBtn">
                                    <i class="bi bi-graph-up me-1"></i>
                                    إحصائيات
                                </button>
                            </div>
                            <div class="col-auto ms-auto">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-success dropdown-toggle"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-download me-1"></i>
                                        تصدير
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item export-btn" href="#" data-format="excel">
                                            <i class="bi bi-file-earmark-excel me-1"></i> Excel
                                        </a></li>
                                        <li><a class="dropdown-item export-btn" href="#" data-format="csv">
                                            <i class="bi bi-file-earmark-text me-1"></i> CSV
                                        </a></li>
                                        <li><a class="dropdown-item export-btn" href="#" data-format="pdf">
                                            <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- منطقة عرض الإحصائيات -->
                <div id="statisticsSection" class="border-top pt-3 mt-3" style="display: none;">
                    <h6 class="fw-bold mb-3">
                        <i class="bi bi-graph-up me-1"></i>
                        إحصائيات البحث
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h4 class="card-title" id="totalCount">0</h4>
                                    <p class="card-text">إجمالي النتائج</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h4 class="card-title" id="maleCount">0</h4>
                                    <p class="card-text">ذكور</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h4 class="card-title" id="femaleCount">0</h4>
                                    <p class="card-text">إناث</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h4 class="card-title" id="citiesCount">0</h4>
                                    <p class="card-text">مدن مختلفة</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- منطقة عرض النتائج -->
                <div id="searchResults" class="border-top pt-3 mt-3" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-list-check me-1"></i>
                            نتائج البحث
                        </h6>
                        <small class="text-muted" id="resultsCount"></small>
                    </div>

                    <!-- Loading indicator -->
                    <div id="searchLoading" class="text-center py-4" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري البحث...</span>
                        </div>
                        <p class="mt-2 text-muted">جاري البحث...</p>
                    </div>

                    <!-- جدول النتائج -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="searchResultsTable">
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
                            <tbody id="searchResultsBody">
                                <!-- النتائج ستظهر هنا -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav aria-label="تنقل نتائج البحث" id="searchPagination" style="display: none;">
                        <ul class="pagination justify-content-center">
                            <!-- روابط التنقل ستظهر هنا -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
