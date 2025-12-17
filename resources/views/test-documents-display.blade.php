<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار عرض الوثائق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .document-card {
            transition: all 0.3s ease;
        }
        .document-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body class="p-5">
    <div class="container">
        <h1 class="mb-4">اختبار عرض الوثائق من قاعدة البيانات</h1>

        <div class="row g-5" id="documents_container">
            @php
                $documentTypes = \App\Models\DocumentType::all();
            @endphp

            @if($documentTypes->count() > 0)
                <div class="col-12">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        تم العثور على {{ $documentTypes->count() }} نوع وثيقة
                    </div>
                </div>

                @foreach($documentTypes as $docType)
                    <div class="col-md-6 col-lg-4">
                        <div class="card border border-gray-300 document-card h-100" data-doc-id="{{ $docType->id }}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h4 class="fw-bold text-gray-800 mb-2">
                                            <i class="fas fa-file-pdf text-danger me-2"></i>
                                            {{ $docType->description }}
                                        </h4>
                                        <span class="badge bg-primary">البادئة: {{ $docType->pref }}</span>
                                    </div>
                                    <div class="form-check form-switch form-check-custom">
                                        <input class="form-check-input document-enabled-toggle"
                                               type="checkbox"
                                               id="doc_{{ $docType->id }}"
                                               data-doc-id="{{ $docType->id }}">
                                        <label class="form-check-label fw-bold" for="doc_{{ $docType->id }}">
                                            مفعل
                                        </label>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label fw-bold">تفعيل لـ:</label>
                                    <div class="d-flex gap-3 flex-wrap">
                                        <div class="form-check">
                                            <input class="form-check-input document-toggle"
                                                   type="checkbox"
                                                   id="doc_{{ $docType->id }}_basic"
                                                   data-toggle-type="basic">
                                            <label class="form-check-label" for="doc_{{ $docType->id }}_basic">
                                                البيانات الأساسية
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input document-toggle"
                                                   type="checkbox"
                                                   id="doc_{{ $docType->id }}_family"
                                                   data-toggle-type="family">
                                            <label class="form-check-label" for="doc_{{ $docType->id }}_family">
                                                أفراد الأسرة
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input document-toggle"
                                                   type="checkbox"
                                                   id="doc_{{ $docType->id }}_deceased"
                                                   data-toggle-type="deceased">
                                            <label class="form-check-label" for="doc_{{ $docType->id }}_deceased">
                                                المتوفين
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="col-12">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        لا توجد أنواع وثائق في قاعدة البيانات
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
