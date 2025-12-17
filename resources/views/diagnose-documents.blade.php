<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>تشخيص مشكلة الوثائق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="p-5">
    <div class="container">
        <h1 class="mb-4">تشخيص مشكلة عرض الوثائق</h1>

        <!-- Test 1: Database Check -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">1. التحقق من قاعدة البيانات</h5>
            </div>
            <div class="card-body">
                @php
                    $documentTypes = \App\Models\DocumentType::all();
                    $count = $documentTypes->count();
                @endphp
                <div class="alert alert-{{ $count > 0 ? 'success' : 'danger' }}">
                    <i class="fas fa-{{ $count > 0 ? 'check' : 'times' }}-circle me-2"></i>
                    عدد أنواع الوثائق في قاعدة البيانات: <strong>{{ $count }}</strong>
                </div>
                @if($count > 0)
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>الوصف</th>
                                <th>البادئة</th>
                                <th>أساسي</th>
                                <th>عائلة</th>
                                <th>متوفى</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documentTypes->take(5) as $doc)
                                <tr>
                                    <td>{{ $doc->id }}</td>
                                    <td>{{ $doc->description }}</td>
                                    <td>{{ $doc->pref }}</td>
                                    <td>{{ $doc->basic_enabled ? '✓' : '✗' }}</td>
                                    <td>{{ $doc->family_enabled ? '✓' : '✗' }}</td>
                                    <td>{{ $doc->deceased_enabled ? '✓' : '✗' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if($count > 5)
                        <p class="text-muted">... وهناك {{ $count - 5 }} وثيقة أخرى</p>
                    @endif
                @endif
            </div>
        </div>

        <!-- Test 2: Blade Rendering -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">2. اختبار عرض Blade</h5>
            </div>
            <div class="card-body">
                <p>عدد البطاقات المعروضة في DOM:</p>
                <div id="blade-test" class="row g-3">
                    @foreach($documentTypes->take(3) as $docType)
                        <div class="col-md-4">
                            <div class="card border document-card" data-doc-id="{{ $docType->id }}">
                                <div class="card-body">
                                    <h6>{{ $docType->description }}</h6>
                                    <span class="badge bg-primary">{{ $docType->pref }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="alert alert-info mt-3" id="blade-count"></div>
            </div>
        </div>

        <!-- Test 3: Modal Structure -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">3. اختبار هيكل المودال</h5>
            </div>
            <div class="card-body">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#testModal">
                    فتح مودال الاختبار
                </button>
                <div class="alert alert-info mt-3">
                    اضغط الزر أعلاه لفتح المودال واختبار التبويبات
                </div>
            </div>
        </div>

        <!-- Test 4: JavaScript Check -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">4. اختبار JavaScript</h5>
            </div>
            <div class="card-body">
                <button class="btn btn-success" onclick="testJavaScript()">تشغيل اختبار JS</button>
                <pre id="js-result" class="bg-light p-3 mt-3 rounded"></pre>
            </div>
        </div>

        <!-- Test 5: API Check -->
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">5. اختبار API</h5>
            </div>
            <div class="card-body">
                <div class="input-group mb-3">
                    <input type="number" id="testSponsorId" class="form-control" placeholder="رقم الجمعية" value="1">
                    <button class="btn btn-primary" onclick="testAPI()">اختبار API</button>
                </div>
                <pre id="api-result" class="bg-light p-3 rounded"></pre>
            </div>
        </div>
    </div>

    <!-- Test Modal -->
    <div class="modal fade" id="testModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">اختبار المودال</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#tab1">التبويب 1</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab2">تبويب الوثائق</a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tab1">
                            <p>محتوى التبويب الأول</p>
                        </div>
                        <div class="tab-pane fade" id="tab2">
                            <div class="alert alert-success">
                                إذا ظهرت هذه الرسالة، فالتبويبات تعمل بشكل صحيح
                            </div>
                            <div class="row g-3">
                                @foreach($documentTypes->take(6) as $docType)
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6>{{ $docType->description }}</h6>
                                                <span class="badge bg-primary">{{ $docType->pref }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Test Blade rendering
            const bladeCards = $('#blade-test .document-card').length;
            $('#blade-count').html(`<i class="fas fa-info-circle me-2"></i>تم العثور على <strong>${bladeCards}</strong> بطاقة في DOM`);
        });

        function testJavaScript() {
            const result = {
                jQuery: typeof jQuery !== 'undefined',
                Bootstrap: typeof bootstrap !== 'undefined',
                documentCards: $('.document-card').length,
                documentToggles: $('.document-enabled-toggle').length,
                containers: $('#documents_container').length
            };

            $('#js-result').text(JSON.stringify(result, null, 2));
        }

        function testAPI() {
            const sponsorId = $('#testSponsorId').val();
            $('#api-result').text('جاري التحميل...');

            $.ajax({
                url: `/admin/sponsors/${sponsorId}/documents`,
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#api-result').text(JSON.stringify(response, null, 2));
                },
                error: function(xhr) {
                    $('#api-result').text('خطأ: ' + JSON.stringify(xhr.responseJSON || xhr.responseText, null, 2));
                }
            });
        }
    </script>
</body>
</html>
