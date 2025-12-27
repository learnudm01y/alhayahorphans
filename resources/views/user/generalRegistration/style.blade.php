@push('UserStylesGeneralRegistration')

    <style>
        .nav-tabs .nav-link {
            border: none;
            color: #666;
            transition: all 0.3s;
            position: relative;
        }

        .nav-tabs .nav-link:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .nav-tabs .nav-link.active {
            color: #0d6efd;
            background-color: #fff;
            border-bottom: 3px solid #0d6efd;
        }

        .nav-tabs .nav-link i {
            transition: all 0.3s;
        }

        .nav-tabs .nav-link:hover i,
        .nav-tabs .nav-link.active i {
            transform: scale(1.2);
        }

        /* أنماط التبويب المعطل */
        .nav-tabs .nav-link.disabled,
        .nav-tabs .nav-link:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: auto;
        }

        .nav-tabs .nav-link.disabled:hover,
        .nav-tabs .nav-link:disabled:hover {
            background-color: transparent;
            transform: none;
        }

        .nav-tabs .nav-link.disabled i,
        .nav-tabs .nav-link:disabled i {
            transform: none !important;
        }

        .preview-item .card {
            height: 100%;
        }

        .preview-item .card-img-top {
            height: 200px;
            object-fit: cover;
        }

        #loading {
            padding: 2rem;
        }

        .uploaded-document {
            transition: all 0.3s ease;
        }

        .uploaded-document:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .family-member-form {
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }

        .family-member-form:hover {
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            color: #0d6efd;
            font-weight: bold;
        }

        .person-documents {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
        }

        .person-documents:not(:last-child) {
            border-bottom: 1px solid #dee2e6;
        }

        .family-member-documents .card {
            height: 100%;
            min-height: 300px;
        }

        .family-member-documents .card-body {
            display: flex;
            flex-direction: column;
        }

        .family-member-documents .card img {
            max-height: 150px;
            object-fit: contain;
            margin-bottom: 1rem;
        }

        #family_members_docs .d-flex {
            display: flex !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            gap: 1rem !important;
            padding: 0.5rem 0 !important;
        }

        #family_members_docs .card {
            min-width: 250px !important;
            flex: 0 0 auto !important;
        }

        /* تنسيق شريط التمرير */
        #family_members_docs .d-flex::-webkit-scrollbar {
            height: 8px;
        }

        #family_members_docs .d-flex::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        #family_members_docs .d-flex::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        #family_members_docs .d-flex::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* تنسيق حاويات الوثائق */
        .documents-flex-container {
            display: flex !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            gap: 1rem !important;
            padding: 0.5rem 0 !important;
            scroll-behavior: smooth;
        }

        .document-card {
            min-width: 250px !important;
            flex: 0 0 auto !important;
            margin-bottom: 0 !important;
        }

        /* تحسينات على بطاقات الوثائق */
        .document-card {
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            background: #fff;
        }

        .document-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.2);
        }

        .document-card .card-header {
            background: rgba(13, 110, 253, 0.1);
            border-bottom: 1px solid rgba(13, 110, 253, 0.2);
            padding: 0.75rem 1.25rem;
        }

        .document-card .card-header h6 {
            font-size: 1rem;
            color: #0d6efd;
            margin: 0;
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .document-card .btn-danger {
            margin-right: 0.5rem;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
        }

        .document-card .card-header .btn-danger:hover {
            background-color: #dc3545;
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        }

        /* تنظيم المحتوى داخل البطاقة */
        .document-card .card-body {
            padding: 1.25rem;
        }

        .document-card .card-body img {
            padding: 0.5rem;
            border: 1px solid rgba(13, 110, 253, 0.1);
            border-radius: 8px;
            background: white;
        }

        .document-card .text-center {
            margin-top: 1rem;
        }

        .document-card .text-muted {
            color: #6c757d !important;
            margin-bottom: 0.5rem;
        }

        /* تحسين المظهر العام للبطاقة */
        .document-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            overflow: hidden;
            margin: 0.5rem;
        }

        /* تنسيق زر اختيار الملف */
        #document_file {
            border: 2px dashed #0d6efd;
            border-radius: 10px;
            padding: 2rem;
            background: rgba(13, 110, 253, 0.05);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        #document_file:hover {
            background: rgba(13, 110, 253, 0.1);
            border-color: #0a58ca;
        }

        .upload-btn {
            border: 2px solid #0d6efd;
            font-weight: bold;
            padding: 1rem;
            transition: all 0.3s ease;
        }

        .upload-btn:hover {
            background: #0d6efd;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.2);
        }

        .upload-btn i {
            font-size: 1.2rem;
        }

        .file-upload-wrapper {
            position: relative;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }

        #preview {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        #preview img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
        }

        #confirmUpload {
            padding: 0.5rem 2rem;
            font-weight: bold;
        }

        /* تنسيقات خاصة بقسم المتوفين */
        #deceased .card-title {
            position: relative;
            padding-bottom: 0.5rem;
        }

        #deceased .card-title i {
            font-size: 1.25rem;
        }

        #deceased .border {
            border-color: rgba(13, 110, 253, 0.2) !important;
            transition: all 0.3s ease;
        }

        #deceased .border:hover {
            border-color: rgba(13, 110, 253, 0.4) !important;
            box-shadow: 0 0 15px rgba(13, 110, 253, 0.1);
        }

        /* تنسيقات قسم وثائق المتوفين */
        .deceased-docs-section {
            background: rgba(13, 110, 253, 0.03);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0 15px rgba(13, 110, 253, 0.05);
        }

        .deceased-docs-section h6 {
            border-bottom: 2px solid rgba(13, 110, 253, 0.2);
            padding-bottom: 0.75rem;
            margin-bottom: 1.5rem;
        }

        #father_docs,
        #mother_docs {
            min-height: 120px;
            border: 1px dashed rgba(13, 110, 253, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        /* تحسينات على بطاقات المعلومات */
        .deceased-person-card {
            border: none;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .deceased-person-card:hover {
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
            transform: translateY(-5px);
        }

        .deceased-person-card .card-header {
            border-bottom: 2px solid rgba(13, 110, 253, 0.1);
        }

        .deceased-person-card .card-title {
            color: #2c3e50;
            font-size: 1.25rem;
        }

        .deceased-person-card .card-title i {
            color: #0d6efd;
        }

        .deceased-person-card hr {
            opacity: 0.1;
        }

        .deceased-person-card .form-control {
            border-color: rgba(0, 0, 0, 0.1);
        }

        .deceased-person-card .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }

        .deceased-person-card textarea {
            resize: none;
        }

        .family-member-form {
            transition: all 0.3s ease;
        }

        .family-member-form .card-header {
            position: relative;
        }

        .family-member-form .delete-member {
            padding: 0.25rem 0.5rem;
            transition: all 0.3s ease;
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .family-member-form .delete-member:hover {
            background-color: #bb2d3b;
            border-color: #b02a37;
            transform: scale(1.1);
        }

        .family-member-form .card-body {
            padding: 1.5rem;
        }

        .family-member-form .form-control:focus,
        .family-member-form .form-select:focus {
            border-color: rgba(13, 110, 253, 0.4);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }

        .save-record-btn {
            padding: 1rem 2rem;
            font-size: 1.25rem;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(13, 110, 253, 0.2);
        }

        .save-record-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(13, 110, 253, 0.3);
        }

        .save-record-btn i {
            font-size: 1.5rem;
        }

        .preview-item .card,
        .family-member-documents .card,
        .document-card,
        .deceased-person-card,
        .family-member-form {
            width: 100%;
            max-width: 100%;
            margin: 0;
        }

        .card,
        .document-card,
        .deceased-person-card,
        .family-member-form {
            padding: 0.5rem !important;
        }

        .card-body,
        .document-card .card-body,
        .deceased-person-card .card-body,
        .family-member-form .card-body {
            padding: 0.5rem !important;
        }

        .file-upload-wrapper,
        #preview,
        .deceased-docs-section {
            padding: 0.5rem !important;
        }

        .documents-flex-container,
        #family_members_docs .d-flex {
            padding: 0.25rem 0 !important;
            gap: 0.5rem !important;
        }

        .document-card,
        .family-member-documents .card {
            min-width: 150px !important;
        }

        /* Responsive: تقليل الحشوات أكثر للجوال */
        @media (max-width: 576px) {

            .card,
            .document-card,
            .deceased-person-card,
            .family-member-form {
                padding: 0.2rem !important;
            }

            .card-body,
            .document-card .card-body,
            .deceased-person-card .card-body,
            .family-member-form .card-body {
                padding: 0.2rem !important;
            }

            .file-upload-wrapper,
            #preview,
            .deceased-docs-section {
                padding: 0.2rem !important;
            }

            .documents-flex-container,
            #family_members_docs .d-flex {
                padding: 0.1rem 0 !important;
                gap: 0.2rem !important;
            }

            .document-card,
            .family-member-documents .card {
                min-width: 120px !important;
            }
        }
    </style>
    <style>
        /* مثال:
                .document-card { width: 150px; }
                .documents-flex-container { overflow-x: auto; }
                حسب التصميم العام. */
        .document-card {
            width: 150px;
        }

        .documents-flex-container {
            overflow-x: auto;
        }
    </style>
@endpush
@push('UserStylesGeneralRegistration')
    <style>
        .tab-icon {
            font-size: 2rem;
            color: #0d6efd;
            background: none !important;
            border-radius: 0 !important;
            padding: 0 !important;
            margin-bottom: 0.2rem;
            border: none !important;
            transition: none !important;
            box-shadow: none !important;
        }

        .nav-tabs .nav-link.active .tab-icon,
        .nav-tabs .nav-link:focus .tab-icon,
        .nav-tabs .nav-link:hover .tab-icon {
            background: none !important;
            color: #0d6efd !important;
            border: none !important;
            transform: none !important;
            box-shadow: none !important;
        }

        @media (max-width: 576px) {
            .mobile-bottom-tabs {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 1050;
                background: rgba(245, 245, 245, 0.95);
                box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.08);
                margin-bottom: 0 !important;
                border-top: 1.5px solid #e5e7eb;
                border-radius: 22px 22px 0 0;
                padding: 0.2rem 0.5rem 0.3rem 0.5rem;
                display: flex !important;
                justify-content: space-between;
                gap: 0 !important;
            }

            .mobile-bottom-tabs .nav-item {
                flex: 1 1 0;
                display: flex;
                justify-content: center;
                align-items: stretch;
                position: relative;
            }

            .mobile-bottom-tabs .nav-link {
                padding: 0.4rem 0 !important;
                background: transparent !important;
                border: none !important;
                box-shadow: none !important;
                display: flex;
                flex-direction: column;
                align-items: center;
                border-radius: 18px !important;
                position: relative;
                height: 100%;
                min-width: 0;
            }

            .mobile-bottom-tabs .tab-label {
                display: none !important;
            }

            .tab-icon {
                font-size: 2.1rem !important;
                color: #232323 !important;
                background: rgba(200, 200, 200, 0.18) !important;
                border-radius: 16px !important;
                padding: 0.55rem !important;
                margin-bottom: 0 !important;
                border: none !important;
                box-shadow: 0 1px 6px rgba(0, 0, 0, 0.04) !important;
                transition: background 0.2s, color 0.2s, box-shadow 0.2s !important;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            /* Styling for disabled navigation items */
            .mobile-bottom-tabs .nav-link.disabled .tab-icon,
            .mobile-bottom-tabs .nav-link[disabled] .tab-icon,
            .mobile-bottom-tabs .nav-item .disabled .tab-icon {
                color: #9e9e9e !important; /* Gray color for disabled icons */
                background: rgba(200, 200, 200, 0.1) !important;
                box-shadow: none !important;
                opacity: 0.7 !important;
            }

            .nav-tabs .nav-link.active .tab-icon,
            .nav-tabs .nav-link:focus .tab-icon,
            .nav-tabs .nav-link:hover .tab-icon {
                color: #232323 !important;
                background: rgba(44, 44, 44, 0.13) !important;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.10) !important;
            }

            /* Make sure active items override disabled styles */
            .nav-tabs .nav-link.disabled.active .tab-icon,
            .nav-tabs .nav-link[disabled].active .tab-icon {
                color: #232323 !important;
                opacity: 1 !important;
            }

            .mobile-bottom-tabs .nav-item:not(:last-child)::after {
                content: "";
                position: absolute;
                top: 18%;
                right: 0;
                width: 1.5px;
                height: 64%;
                background: #e5e7eb;
                border-radius: 2px;
                opacity: 0.85;
                z-index: 2;
            }

            body {
                padding-bottom: 80px !important;
            }
        }
    </style>
@endpush
