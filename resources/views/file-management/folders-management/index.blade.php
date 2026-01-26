@extends('admin.dashboard.toolbars.index')

@push('styles')
<link href="{{ asset('css/folder-management-enhanced.css') }}" rel="stylesheet" type="text/css" />
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    /* عزل كامل لرؤوس الجداول - تجاوز جميع الأنماط الخارجية */
    #kt_file_manager_list thead,
    #kt_excel_manager_list thead,
    .table thead {
        background-color: #ffffff !important;
        background-image: none !important;
        background: #ffffff !important;
    }

    #kt_file_manager_list thead tr,
    #kt_excel_manager_list thead tr,
    .table thead tr {
        background-color: #ffffff !important;
        background-image: none !important;
        background: #ffffff !important;
        color: #333333 !important;
    }

    #kt_file_manager_list thead th,
    #kt_excel_manager_list thead th,
    .table thead th {
        background-color: #ffffff !important;
        background-image: none !important;
        background: #ffffff !important;
        color: #333333 !important;
        border-color: #e4e6ea !important;
        border-top: none !important;
        position: relative;
    }

    /* منع أي gradients أو patterns */
    #kt_file_manager_list thead *,
    #kt_excel_manager_list thead *,
    .table thead * {
        background-color: #ffffff !important;
        background-image: none !important;
        background: #ffffff !important;
    }

    /* تجاوز أي أنماط bootstrap */
    .table-light,
    .table-primary,
    .table-secondary,
    .table-success,
    .table-danger,
    .table-warning,
    .table-info,
    .table-dark {
        background-color: #ffffff !important;
    }

    /* تجاوز أي أنماط من frameworks أخرى */
    thead {
        background: white !important;
    }

    th {
        background: white !important;
    }
</style>
@endpush

@section('content')
<div class="folder-management-container">
    <!--begin::Card-->
        <div class="card card-flush table-enhanced">
            <!--begin::Card header-->
            <div class="card-header pt-8">
                <div class="card-title">
                    <!--begin::Search-->
                    <div class="search-container d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-1 search-icon">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <input type="text" id="kt_filemanager_search" class="form-control form-control-solid search-input w-300px ps-15" placeholder="🔍 البحث في الملفات والمجلدات..." />
                    </div>
                    <!--end::Search-->
                </div>
                <!--begin::Card toolbar-->
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    <div class="d-flex justify-content-end" data-kt-filemanager-table-toolbar="base">
                        <!--begin::Type Toggle-->
                        <div class="btn-group me-3" role="group">
                            <button type="button" class="btn btn-enhanced btn-{{ request('type', 'images') === 'images' ? 'primary' : 'light' }}"
                                    onclick="window.location.href='{{ route('admin.manage.folders.index', ['type' => 'images']) }}'">
                                <i class="ki-duotone ki-picture fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i> مجلدات الصور
                            </button>
                            <button type="button" class="btn btn-enhanced btn-{{ request('type') === 'excel' ? 'primary' : 'light' }}"
                                    onclick="window.location.href='{{ route('admin.manage.folders.index', ['type' => 'excel']) }}'">
                                <i class="ki-duotone ki-file fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i> ملفات Excel
                            </button>
                        </div>
                        <!--end::Type Toggle-->
                        <!--begin::Export-->
                        {{-- <button type="button" class="btn btn-flex btn-enhanced btn-light-primary me-3 pulse-animation" id="kt_file_manager_new_folder">
                        <i class="ki-duotone ki-add-folder fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>➕ مجلد جديد</button>
                        <!--end::Export-->
                        <!--begin::Add customer-->
                        <button type="button" class="btn btn-flex btn-enhanced btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_upload">
                        <i class="ki-duotone ki-folder-up fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>⬆️ رفع ملفات</button>
                        <!--end::Add customer--> --}}
                    </div>
                    <!--end::Toolbar-->
                    <!--begin::Group actions-->
                    <div class="d-flex justify-content-end align-items-center d-none" data-kt-filemanager-table-toolbar="selected">
                        <div class="fw-bold me-5">
                        <span class="me-2" data-kt-filemanager-table-select="selected_count"></span>محدد</div>
                        <button type="button" class="btn btn-danger" data-kt-filemanager-table-select="delete_selected">حذف المحدد</button>
                    </div>
                    <!--end::Group actions-->
                </div>
                <!--end::Card toolbar-->
            </div>
            <!--end::Card header-->
            <!--begin::Card body-->
            <div class="card-body">
                <!--begin::Table header-->
                <div class="folder-breadcrumb d-flex flex-stack">
                    <!--begin::Folder path-->
                    <div class="badge badge-enhanced badge-lg badge-light-primary">
                        <div class="d-flex align-items-center flex-wrap">
                        <i class="ki-duotone ki-abstract-32 fs-2 text-primary me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <span>
                            {{ request('type', 'images') === 'images' ? ' مجلدات الصور والوثائق' : ' ملفات Excel' }}
                            @if(isset($scanned_from_disk) && $scanned_from_disk)
                               
                            @endif
                        </span>
                        </div>
                    </div>
                    <!--end::Folder path-->
                    <!--begin::Folder Stats-->
                    <div class="stats-card badge-enhanced badge-lg badge-primary">
                        <span id="kt_file_manager_items_counter">
                            @if(request('type', 'images') === 'images')
                                <span class="stats-number">{{ (isset($folders) && method_exists($folders, 'total')) ? $folders->total() : (isset($total_folders_found) ? $total_folders_found : 0) }}</span>
                                <span class="stats-label">مجلد</span>
                                @if(isset($total_folders_found))
                                    <small class="d-block text-light">({{ $total_folders_found }} مجلد إجمالي)</small>
                                @endif
                            @else
                                <span class="stats-number">{{ isset($totalExcelFiles) ? $totalExcelFiles : (isset($folders) && method_exists($folders, 'total') ? $folders->total() : (isset($folders) ? $folders->count() : 0)) }}</span>
                                <span class="stats-label">ملف</span>
                                @if(isset($folders) && method_exists($folders, 'total'))
                                    <small class="d-block text-light">({{ $folders->total() }} مجلد)</small>
                                @endif
                            @endif
                        </span>
                    </div>
                    <!--end::Folder Stats-->
                </div>
                <!--end::Table header-->

                @if(isset($error))
                    <div class="alert alert-warning d-flex align-items-center p-5 mb-5">
                        <i class="ki-duotone ki-shield-tick fs-2hx text-warning me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h4 class="mb-1 text-dark">تحذير</h4>
                            <span>{{ $error }}</span>
                        </div>
                    </div>
                @endif


                @if(isset($debug_info))
                    <div class="alert alert-light d-flex align-items-center p-5 mb-5">
                        <i class="ki-duotone ki-information fs-2hx text-primary me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h4 class="mb-1 text-dark">معلومات تشخيصية</h4>
                            <div class="text-muted fs-7">
                                <div>📁 مجلدات من جدول attachments: {{ $debug_info['attachment_folders_found'] ?? 0 }}</div>
                                <div>📂 مجلدات من جدول enhanced_attachments: {{ $debug_info['enhanced_folders_found'] ?? 0 }}</div>
                                <div>📋 إجمالي المجلدات المعالجة: {{ $debug_info['total_processed_folders'] ?? 0 }}</div>
                                <div>🗂️ مسار storage: {{ $debug_info['storage_path'] ?? 'غير محدد' }}</div>
                                <div>🔗 مسار public: {{ $debug_info['public_path'] ?? 'غير محدد' }}</div>
                            </div>
                        </div>
                    </div>
                @endif

                <!--begin::Table-->
                <div id="files_table_container">
                    @if(request('type', 'images') === 'images')
                        @include('file-management.folders-management.partials.folders-table')
                    @else
                        @include('file-management.folders-management.partials.excel-table')
                    @endif
                </div>
                <!--end::Table-->
            </div>
            <!--end::Card body-->
        </div>
    <!--end::Card-->

    <!-- Modal محتويات المجلد -->
    <div class="modal fade modal-enhanced" id="folderContentsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">📁 محتويات المجلد: <span id="modal-folder-name" class="text-warning"></span></h2>
                    <div class="btn-close" data-bs-dismiss="modal" aria-label="Close"></div>
                </div>
                <div class="modal-body">
                    <div id="folder-contents" class="row">
                        <!-- سيتم تحميل المحتوى هنا عبر JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-enhanced btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>إغلاق
                    </button>
                    <button type="button" class="btn btn-enhanced btn-success" id="downloadFolderBtn"
                            title="تحميل جميع ملفات المجلد في ملف ZIP مضغوط">
                        <i class="fas fa-file-archive me-2"></i>� تحميل كـ ZIP
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal عرض الصور -->
    <div class="modal fade modal-enhanced" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel"> عرض الصورة</h5>
                    <div class="btn-close" data-bs-dismiss="modal" aria-label="Close"></div>
                </div>
                <div class="modal-body text-center">
                    <div class="image-preview-container">
                        <img id="modalImage" src="" class="img-fluid" alt="صورة">
                        <div class="image-overlay">
                            <i class="ki-duotone ki-eye fs-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-enhanced btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-enhanced btn-primary" id="downloadImage">
                        <i class="ki-duotone ki-down fs-2 me-2"></i>📥 تحميل
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay d-none">
        <div class="text-center">
            <div class="loading-spinner"></div>
            <h4 class="mt-3">جاري التحميل...</h4>
            <p class="text-muted">يرجى الانتظار</p>
        </div>
    </div>
</div>

    @if(request('type') === 'excel' || (isset($excelMode) && $excelMode))
        {{-- JavaScript خاص ببوابة Excel --}}
        @include('file-management.folders-management.excelJavascript')
    @else
        {{-- JavaScript خاص ببوابة الصور --}}
        @include('file-management.folders-management.javascript')
    @endif

    <!-- Additional JavaScript for Image Modal Support -->
    <script>
    // Fallback function if showImageModal is not loaded
    if (typeof showImageModal === 'undefined') {
        console.log('🔧 Creating fallback showImageModal function');

        window.showImageModal = function(imageSrc, imageTitle = 'صورة') {
            console.log('📸 Fallback showImageModal called:', imageSrc);

            // Try to find and use existing modal
            const modalImage = document.getElementById('modalImage');
            const imageModal = document.getElementById('imageModal');

            if (modalImage && imageModal) {
                // تحديث المسار ليستخدم العرض الآمن
                if (imageSrc && !imageSrc.includes('admin/file/show/')) {
                    const filename = imageSrc.split('/').pop();
                    imageSrc = `{{ route('admin.file.show', '') }}/${filename}`;
                }

                modalImage.src = imageSrc;
                modalImage.alt = imageTitle;

                // Use Bootstrap modal if available
                if (typeof bootstrap !== 'undefined') {
                    const modal = new bootstrap.Modal(imageModal);
                    modal.show();
                } else {
                    // Fallback to simple display
                    imageModal.style.display = 'block';
                }
            } else {
                // Ultimate fallback - open in new window
                console.log('🚨 No modal found, opening in new window');
                window.open(imageSrc, '_blank');
            }
        };
    }

    // Test function
    window.testImageModal = function() {
        console.log('🧪 Testing image modal...');
        showImageModal('{{ route("admin.file.show", "000010_sample.jpg") }}', 'Test Image');
    };

    console.log('✅ Image modal support initialized');
    </script>
@endsection
