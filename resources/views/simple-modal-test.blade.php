<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار بسيط للوثائق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="p-5">
    <div class="container">
        <h1 class="mb-4">اختبار بسيط - الوثائق في المودال</h1>

        <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#testModal">
            افتح المودال
        </button>

        <div class="mt-4">
            <h5>معلومات:</h5>
            <ul>
                <li>عدد الوثائق في قاعدة البيانات: <strong>{{ \App\Models\DocumentType::count() }}</strong></li>
                <li>البورت: <strong>{{ Request::getHost() }}:{{ Request::getPort() }}</strong></li>
            </ul>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="testModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">مودال الاختبار</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="home-tab" data-bs-toggle="tab" data-bs-target="#home" type="button">
                                <i class="fas fa-home me-2"></i>
                                الرئيسية
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button">
                                <i class="fas fa-file-alt me-2"></i>
                                الوثائق ({{ \App\Models\DocumentType::count() }})
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="home" role="tabpanel">
                            <div class="alert alert-info">
                                هذا هو التبويب الأول
                            </div>
                        </div>
                        <div class="tab-pane fade" id="documents" role="tabpanel">
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                إذا رأيت هذه الرسالة، فالتبويب يعمل!
                            </div>

                            <div class="row g-3">
                                @php
                                    $documents = \App\Models\DocumentType::all();
                                @endphp

                                @forelse($documents as $doc)
                                    <div class="col-md-4">
                                        <div class="card border border-primary h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="fw-bold mb-0">
                                                        <i class="fas fa-file-pdf text-danger me-1"></i>
                                                        {{ $doc->description }}
                                                    </h6>
                                                    <span class="badge bg-primary">{{ $doc->pref }}</span>
                                                </div>
                                                <small class="text-muted">ID: {{ $doc->id }}</small>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12">
                                        <div class="alert alert-warning">
                                            لا توجد وثائق!
                                        </div>
                                    </div>
                                @endforelse
                            </div>

                            <div class="alert alert-info mt-3">
                                <strong>المجموع:</strong> {{ $documents->count() }} وثيقة
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            console.log('Page loaded');
            console.log('Modal element:', $('#testModal').length);
            console.log('Document cards:', $('.card').length);

            // عند فتح المودال
            $('#testModal').on('show.bs.modal', function() {
                console.log('Modal opening...');
            });

            $('#testModal').on('shown.bs.modal', function() {
                console.log('Modal opened!');
                console.log('Cards visible:', $('.card:visible').length);
            });

            // عند تغيير التبويب
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
                console.log('Tab switched to:', e.target.id);
                const target = $(e.target).data('bs-target');
                console.log('Tab content:', target);
                console.log('Cards in active tab:', $(target + ' .card').length);
            });
        });
    </script>
</body>
</html>
