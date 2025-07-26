<!-- Modal للبحث السريع بتقنية Scout -->
<div class="modal fade" id="scoutSearchModal" tabindex="-1" aria-labelledby="scoutSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scoutSearchModalLabel">
                    <i class="fas fa-rocket me-2"></i>
                    البحث السريع بتقنية Scout
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- رسائل النظام -->
                <div id="scoutMessage" class="scout-message" style="display: none;"></div>

                <!-- حاوي البحث -->
                <div class="scout-search-container">
                    <input type="text"
                           id="scoutSearchInput"
                           class="form-control"
                           placeholder="ابحث بالاسم الكامل أو رقم الهوية..."
                           autocomplete="off">
                </div>

                <!-- أزرار البحث -->
                <div class="scout-buttons">
                    <button class="btn btn-scout-primary" type="button" id="scoutSearchBtn">
                        <i class="fas fa-search me-1"></i>
                        بحث سريع
                    </button>
                    <button class="btn btn-scout-success" type="button" id="scoutAdvancedSearchBtn">
                        <i class="fas fa-filter me-1"></i>
                        بحث متقدم
                    </button>
                    <button class="btn btn-scout-secondary" type="button" id="scoutToggleAdvanced">
                        <i class="fas fa-chevron-down"></i>
                        إظهار الفلاتر
                    </button>
                    <button class="btn btn-scout-secondary" type="button" id="scoutClearBtn">
                        <i class="fas fa-times me-1"></i>
                        مسح
                    </button>
                </div>

                <!-- فلاتر البحث المتقدم -->
                <div id="scoutAdvancedFilters" class="scout-filters" style="display: none;">
                    <h6>
                        <i class="fas fa-filter me-2"></i>
                        فلاتر البحث المتقدم
                    </h6>
                    <div class="row">
                        <div class="col-md-3">
                            <label for="scoutGenderFilter" class="form-label">الجنس</label>
                            <select id="scoutGenderFilter" class="form-select">
                                <option value="">الكل</option>
                                <option value="M">ذكر</option>
                                <option value="F">أنثى</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="scoutBirthYearFilter" class="form-label">سنة الميلاد</label>
                            <input type="number" id="scoutBirthYearFilter" class="form-control"
                                   placeholder="مثال: 1990" min="1900" max="{{ date('Y') }}">
                        </div>
                        <div class="col-md-3">
                            <label for="scoutCityFilter" class="form-label">المدينة</label>
                            <input type="text" id="scoutCityFilter" class="form-control" placeholder="المدينة">
                        </div>
                        <div class="col-md-3">
                            <label for="scoutLimitFilter" class="form-label">عدد النتائج</label>
                            <select id="scoutLimitFilter" class="form-select">
                                <option value="20">20</option>
                                <option value="50" selected>50</option>
                                <option value="100">100</option>
                                <option value="200">200</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- إحصائيات البحث -->
                <div class="scout-stats">
                    <h6>
                        <i class="fas fa-chart-line me-2"></i>
                        إحصائيات البحث
                    </h6>
                    <div id="scoutSearchStats"></div>
                </div>

                <!-- مؤشر التحميل -->
                <div id="scoutLoadingIndicator" class="scout-loading" style="display: none;">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">جاري البحث...</span>
                    </div>
                    <p class="mt-2">جاري البحث السريع...</p>
                </div>

                <!-- النتائج -->
                <div id="scoutSearchResults" class="scout-results" style="display: none;">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th width="80">الإجراءات</th>
                                <th>رقم الهوية</th>
                                <th>الاسم الكامل</th>
                                <th>تاريخ الميلاد</th>
                                <th>الجنس</th>
                                <th>المدينة</th>
                            </tr>
                        </thead>
                        <tbody id="scoutSearchResultsBody">
                            <!-- النتائج هنا -->
                        </tbody>
                    </table>
                </div>

                <!-- رسالة عدم وجود نتائج -->
                <div id="scoutNoResults" class="scout-no-results" style="display: none;">
                    <i class="fas fa-search"></i>
                    <h5>لا توجد نتائج</h5>
                    <p>جرب البحث بكلمات مختلفة أو استخدم فلاتر أقل تقييداً</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal لإحصائيات النظام -->
<div class="modal fade" id="scoutStatsModal" tabindex="-1" aria-labelledby="scoutStatsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="scoutStatsModalLabel">
                    <i class="fas fa-chart-bar me-2"></i>
                    إحصائيات نظام البحث
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="scoutSystemStats">
                    <!-- الإحصائيات ستظهر هنا -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                <button type="button" class="btn btn-primary" id="scoutIndexDataBtn">
                    <i class="fas fa-sync-alt me-1"></i>
                    فهرسة البيانات
                </button>
            </div>
        </div>
    </div>
</div>
