@extends('user.generalRegistration.index')
@section('contentGeneralRegistration')
    <div class="container-fluid py-4" style="max-width:100vw;">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card shadow-lg border-0">
                    <div
                        class="card-header bg-gradient-primary text-dark fw-bold fs-4 text-center rounded-top p-4 d-flex align-items-center justify-content-between flex-wrap">
                        <span style="margin-top: 1.5rem; display: inline-block;">
                            <i class="fas fa-user-plus me-2"></i>
                            إضافة سجل جديد
                        </span>
                        <div class="d-flex align-items-center" style="margin-top: 1.5rem;">
                            <label class="form-label mb-0 me-2 fs-5" style="color: #222;">رقم الملف:</label>
                            <input type="text"
                                class="form-control bg-secondary bg-opacity-25 border-0 text-center fs-3 fw-bold"
                                style="width: 180px; height: 55px; box-shadow: none;" value="{{ $file_id_number ?? '' }}"
                                readonly>
                        </div>
                    </div>

                    <!-- Add Tab Navigation (mobile-bottom-tabs style) -->
                    @include('user.generalRegistration.component.navBar')

                    <div class="card-body bg-light">

                        <!-- تأكد من وجود هذه الحقول المخفية -->
                        <div class="tab-content" id="formTabsContent">
                            <!-- Instructions Tab: بوابة تعليمات الإدخال معزولة بالكامل -->
                            <div class="tab-pane fade show active" id="instructions" role="tabpanel"
                                aria-labelledby="instructions-tab">

                                <!-- بوابة البحث الجديدة -->
                                <div class="card shadow-sm border-0 mb-4" dir="rtl">
                                    <div class="card-header bg-gradient-primary text-dark fw-bold">
                                        <i class="fas fa-search me-2"></i>البحث عن سجل موجود
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label for="searchInput" class="form-label">ابحث برقم الهوية أو الاسم</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="searchInput"
                                                        placeholder="أدخل رقم هوية المعيل، اليتيم، المتوفى أو الاسم">
                                                    <button class="btn btn-primary" type="button" id="searchButton">
                                                        <i class="fas fa-search"></i> بحث
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- نتائج البحث -->
                                        <div id="searchResults" class="mt-3" style="display: none;">
                                            <!-- سيتم ملء النتائج هنا -->
                                        </div>

                                        <!-- رسالة عدم وجود نتائج -->
                                        <div id="noResultsMessage" class="alert alert-warning mt-3" style="display: none;">
                                            <i class="fas fa-info-circle"></i> لم يتم العثور على سجل مطابق. يمكنك المتابعة لإنشاء سجل جديد.
                                        </div>
                                    </div>
                                </div>

                                <!-- بداية القسم المعلق - سيتم استخدامه لاحقاً -->
                                <!--
                                <div class="alert alert-info mt-4" dir="rtl">
                                    <div class="mb-3" style="text-align: right;">
                                        <span class="text-black" style="font-size: 1.1rem;">
                                            <i class="fas fa-info-circle"></i>ملاحظة/ إذا كنت قد سجلت مسبقا لدينا بإمكانك
                                            تسجيل الدخول
                                        </span>
                                        <button type="button" class="btn btn-primary ms-3" id="showLoginFormBtn">
                                            <i class="fas fa-sign-in-alt fa-flip-horizontal me-1"></i>تسجيل الدخول
                                        </button>
                                    </div>

                                    <div id="loginFormContainer" class="card p-4 my-3 shadow-sm border border-primary"
                                        style="max-width: 400px; margin: 0 auto; display: none;">
                                        <form id="loginForm" method="POST" action="{{ route('user.login') }}"
                                            autocomplete="off">
                                            @csrf
                                            <div class="mb-3">
                                                <label for="login_email" class="form-label">رقم الهوية</label>
                                                <input type="text" class="form-control" id="login_email"
                                                    name="login_email" maxlength="20" required pattern="[0-9]+">
                                            </div>
                                            <div class="mb-3">
                                                <label for="login_password" class="form-label">كلمة المرور (4 أرقام)</label>
                                                <input type="password" class="form-control" id="login_password"
                                                    name="login_password" maxlength="4" minlength="4" required
                                                    pattern="\d{4}">
                                            </div>
                                            <button type="submit" class="btn btn-success w-100">دخول</button>
                                        </form>
                                    </div>
                                    <script>
                                        document.addEventListener('DOMContentLoaded', function() {
                                            const showLoginFormBtn = document.getElementById('showLoginFormBtn');
                                            const loginFormContainer = document.getElementById('loginFormContainer');
                                            if (showLoginFormBtn && loginFormContainer) {
                                                showLoginFormBtn.addEventListener('click', function() {
                                                    loginFormContainer.style.display = loginFormContainer.style.display === 'none' ?
                                                        'block' : 'none';
                                                });
                                            }
                                        });
                                    </script>
                                    -->

                                    <form action="{{ route('store.generalRegistration') }}" method="POST"
                                        enctype="multipart/form-data" autocomplete="off" id="main_form">
                                        @csrf
                                        <input type="hidden" name="file_id_number" value="{{ $file_id_number ?? '' }}">

                                        <!--
                                        <h4 class="mb-3"><i class="fas fa-info-circle"></i> تعليمات إدخال البيانات
                                        </h4>
                                        <ul class="fs-5">
                                            <li>يرجى تعبئة جميع الحقول الإلزامية بعناية لضمان قبول الطلب.</li>
                                            <li>لرفع الملفات (مثل الصور أو المستندات)، استخدم زر <b>إضافة مرفق</b> في
                                                بوابة المرفقات فقط، وتأكد من أن حجم الملف وصيغته مطابقة للتعليمات.
                                            </li>
                                            <li>لحساب العمر تلقائيًا، أدخل تاريخ الميلاد بشكل صحيح وسيتم حساب العمر
                                                تلقائيًا في الحقل المخصص.</li>
                                            <li>في حال ظهور رسالة خطأ باللون الأحمر، يرجى مراجعة البيانات المدخلة
                                                وتصحيحها.</li>
                                            <li>استخدم خاصية الإكمال التلقائي عند إدخال بيانات مثل اسم المدينة أو
                                                المحافظة لتسهيل وتسريع الإدخال.</li>
                                            <li>يمكنك مراجعة البيانات المدخلة في بوابة "عرض المعلومات المدخلة" قبل
                                                الحفظ النهائي.</li>
                                            <li>لحفظ كلمة المرور في متصفحك، استخدم زر الحفظ بجانب حقل كلمة المرور
                                                (في حال توفره).</li>
                                        </ul>
                                        <div class="mt-3 text-muted">
                                            <i class="fas fa-exclamation-triangle"></i> جميع المعلومات ستُعامل بسرية
                                            تامة.
                                        </div>
                                -->
                                <!--</div>-->

                                <div class="mt-4 text-end">
                                    <button type="button" class="btn btn-success px-5 py-2 fs-5" id="goToBasicTabBtn">
                                        التالي <i class="fas fa-arrow-left ms-2"></i>
                                    </button>
                                </div>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const goToBasicTabBtn = document.getElementById('goToBasicTabBtn');
                                        if (goToBasicTabBtn) {
                                            goToBasicTabBtn.addEventListener('click', function() {
                                                const basicTab = document.getElementById('basic-tab');
                                                if (basicTab) {
                                                    basicTab.click();
                                                }
                                            });
                                        }

                                        // وظيفة البحث
                                        const searchButton = document.getElementById('searchButton');
                                        const searchInput = document.getElementById('searchInput');
                                        const searchResults = document.getElementById('searchResults');
                                        const noResultsMessage = document.getElementById('noResultsMessage');
                                        let searchAttempts = 0;

                                        if (searchButton && searchInput) {
                                            searchButton.addEventListener('click', performSearch);
                                            searchInput.addEventListener('keypress', function(e) {
                                                if (e.key === 'Enter') {
                                                    performSearch();
                                                }
                                            });
                                        }

                                        function performSearch() {
                                            const searchTerm = searchInput.value.trim();

                                            if (!searchTerm) {
                                                alert('الرجاء إدخال رقم الهوية أو الاسم للبحث');
                                                return;
                                            }

                                            // إظهار loader
                                            searchButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري البحث...';
                                            searchButton.disabled = true;

                                            // إرسال طلب AJAX
                                            fetch('{{ route("search.all.tables") }}', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                                                },
                                                body: JSON.stringify({
                                                    search_term: searchTerm
                                                })
                                            })
                                            .then(response => response.json())
                                            .then(data => {
                                                searchButton.innerHTML = '<i class="fas fa-search"></i> بحث';
                                                searchButton.disabled = false;

                                                if (data.found) {
                                                    searchAttempts++;
                                                    displaySearchResults(data);
                                                    noResultsMessage.style.display = 'none';
                                                } else {
                                                    searchAttempts++;
                                                    searchResults.style.display = 'none';

                                                    if (searchAttempts >= 2) {
                                                        // بعد المحاولة الثانية، السماح بالمتابعة
                                                        noResultsMessage.innerHTML = `
                                                            <i class="fas fa-info-circle"></i>
                                                            ${data.message}
                                                            <br><br>
                                                            <button type="button" class="btn btn-success" id="continueToForm">
                                                                المتابعة لإنشاء سجل جديد <i class="fas fa-arrow-left"></i>
                                                            </button>
                                                        `;
                                                        noResultsMessage.style.display = 'block';

                                                        // إضافة حدث للزر
                                                        setTimeout(() => {
                                                            document.getElementById('continueToForm')?.addEventListener('click', function() {
                                                                // الانتقال للبوابة التالية
                                                                document.getElementById('basic-tab')?.click();
                                                            });
                                                        }, 100);
                                                    } else {
                                                        noResultsMessage.innerHTML = `
                                                            <i class="fas fa-exclamation-triangle"></i>
                                                            ${data.message}
                                                            <br>
                                                            يرجى المحاولة مرة أخرى للتأكد.
                                                        `;
                                                        noResultsMessage.style.display = 'block';
                                                    }
                                                }
                                            })
                                            .catch(error => {
                                                console.error('خطأ في البحث:', error);
                                                searchButton.innerHTML = '<i class="fas fa-search"></i> بحث';
                                                searchButton.disabled = false;
                                                alert('حدث خطأ أثناء البحث. يرجى المحاولة مرة أخرى.');
                                            });
                                        }

                                        function displaySearchResults(data) {
                                            searchResults.style.display = 'block';

                                            if (data.has_account) {
                                                // المستخدم موجود مسبقاً - عرض نموذج تسجيل الدخول
                                                searchResults.innerHTML = `
                                                    <div class="alert alert-success">
                                                        <h5><i class="fas fa-check-circle"></i> ${data.message}</h5>
                                                        <div class="mt-3">
                                                            <strong>التفاصيل:</strong><br>
                                                            <p>الاسم: <strong>${data.data.full_name}</strong></p>
                                                            <p>رقم الهوية: <strong>${data.data.id_number}</strong></p>
                                                            <p>نوع السجل: <strong>${data.data.type}</strong></p>
                                                            ${data.data.file_id_number ? '<p>رقم الملف: <strong>' + data.data.file_id_number + '</strong></p>' : ''}
                                                        </div>
                                                    </div>
                                                `;
                                            } else if (data.source === 'civil_registry') {
                                                // موجود في قاعدة البيانات المركزية فقط - عرض البيانات وملء الحقول
                                                // معالجة تاريخ الميلاد بشكل صحيح
                                                let birthDateDisplay = 'غير متوفر';
                                                if (data.data.birth_date_display) {
                                                    birthDateDisplay = data.data.birth_date_display;
                                                } else if (data.data.birth_date) {
                                                    try {
                                                        // تحويل التاريخ إلى صيغة Y/m/d
                                                        const dateObj = new Date(data.data.birth_date);
                                                        if (!isNaN(dateObj.getTime())) {
                                                            const year = dateObj.getFullYear();
                                                            const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                                                            const day = String(dateObj.getDate()).padStart(2, '0');
                                                            birthDateDisplay = `${year}/${month}/${day}`;
                                                        }
                                                    } catch (e) {
                                                        console.error('خطأ في معالجة تاريخ الميلاد:', e);
                                                    }
                                                }

                                                searchResults.innerHTML = `
                                                    <div class="alert alert-info">
                                                        <h5><i class="fas fa-info-circle"></i> ${data.message}</h5>
                                                        <div class="mt-3">
                                                            <strong>البيانات المتوفرة:</strong><br>
                                                            <p><strong>الاسم:</strong> ${data.data.full_name}</p>
                                                            <p><strong>رقم الهوية:</strong> ${data.data.id_number}</p>
                                                            <p><strong>تاريخ الميلاد:</strong> ${birthDateDisplay}</p>
                                                            <p><strong>الجنس:</strong> ${data.data.gender == 1 ? 'ذكر' : (data.data.gender == 2 ? 'أنثى' : 'غير محدد')}</p>
                                                            ${data.data.marital_status_name ? '<p><strong>الحالة الاجتماعية:</strong> ' + data.data.marital_status_name + '</p>' : ''}
                                                            ${data.data.city_name ? '<p><strong>المدينة:</strong> ' + data.data.city_name + '</p>' : ''}
                                                        </div>
                                                        <button type="button" class="btn btn-primary mt-3" id="fillFieldsBtn">
                                                            <i class="fas fa-magic"></i> ملء الحقول تلقائياً والمتابعة
                                                        </button>
                                                    </div>
                                                `;

                                                // إضافة حدث لزر ملء الحقول
                                                setTimeout(() => {
                                                    document.getElementById('fillFieldsBtn')?.addEventListener('click', function() {
                                                        fillFieldsFromCivilRegistry(data.data);
                                                    });
                                                }, 100);
                                            }
                                        }

                                        // دالة لتحويل رمز الحالة الاجتماعية إلى نص
                                        function getMaritalStatusText(code) {
                                            const statuses = {
                                                1: 'أعزب',
                                                2: 'متزوج',
                                                3: 'مطلق',
                                                4: 'أرمل'
                                            };
                                            return statuses[code] || 'غير محدد';
                                        }

                                        function fillFieldsFromCivilRegistry(personData) {
                                            // الانتقال للبوابة الأساسية
                                            document.getElementById('basic-tab')?.click();

                                            // ملء الحقول بعد تحميل البوابة
                                            setTimeout(() => {
                                                // ملء البيانات الأساسية
                                                const fields = {
                                                    'data_id_number': personData.id_number,
                                                    'data_first_name': personData.first_name,
                                                    'data_father_name': personData.father_name,
                                                    'data_grand_father_name': personData.grand_father_name,
                                                    'data_family_name': personData.family_name,
                                                    'data_birth_date': personData.birth_date,
                                                    'data_gender': personData.gender,
                                                    'data_marital_status': personData.marital_status,
                                                    'data_city': personData.city,
                                                    'data_current_address': personData.street
                                                };

                                                console.log('ملء الحقول من قاعدة البيانات المركزية:', personData);

                                                Object.keys(fields).forEach(fieldName => {
                                                    const field = document.querySelector('[name="' + fieldName + '"]');
                                                    if (field && fields[fieldName]) {
                                                        // معالجة خاصة لتاريخ الميلاد
                                                        if (fieldName === 'data_birth_date') {
                                                            try {
                                                                // تحويل التاريخ إلى صيغة YYYY-MM-DD
                                                                let dateValue = fields[fieldName];
                                                                if (dateValue) {
                                                                    const dateObj = new Date(dateValue);
                                                                    if (!isNaN(dateObj.getTime())) {
                                                                        const year = dateObj.getFullYear();
                                                                        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                                                                        const day = String(dateObj.getDate()).padStart(2, '0');
                                                                        field.value = `${year}-${month}-${day}`;
                                                                    } else {
                                                                        field.value = dateValue;
                                                                    }
                                                                }
                                                            } catch (e) {
                                                                console.error('خطأ في معالجة تاريخ الميلاد:', e);
                                                            }
                                                        } else {
                                                            field.value = fields[fieldName];
                                                        }

                                                        // إطلاق حدث change لتحديث الحقول المرتبطة
                                                        field.dispatchEvent(new Event('change', { bubbles: true }));
                                                        field.dispatchEvent(new Event('input', { bubbles: true }));
                                                    }
                                                });

                                                // عرض رسالة نجاح باستخدام SweetAlert
                                                Swal.fire({
                                                    icon: 'success',
                                                    title: 'تم ملء الحقول بنجاح',
                                                    text: 'تم جلب البيانات من قاعدة البيانات المركزية. يرجى مراجعة البيانات وإكمال الحقول الناقصة.',
                                                    confirmButtonText: 'حسناً',
                                                    confirmButtonColor: '#28a745'
                                                });

                                                // التمرير للأعلى
                                                window.scrollTo({ top: 0, behavior: 'smooth' });
                                            }, 500);
                                        }
                                    });
                                </script>
                                <div id="didding" style="padding-bottom: 80px;"></div>
                            </div>
                            <!-- نهاية بوابة تعليمات الإدخال -->

                            <!-- Basic Info Tab -->
                            <div class="tab-pane fade" id="basic" role="tabpanel" aria-labelledby="basic-tab">
                                @include('user.generalRegistration.component.baseTap')
                            </div>
                            <!-- Family Members Tab -->
                            @include('user.generalRegistration.component.familyMember')


                            <!-- Deceased Tab -->

                            @include('user.generalRegistration.component.deceased')
                            <!-- بوابة عرض المعلومات المدخلة ستُضاف ديناميكياً من جافاسكريبت -->
                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('user.generalRegistration.style')
    @include('user.generalRegistration.javascript')

@endsection
