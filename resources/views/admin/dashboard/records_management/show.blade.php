@extends('admin.dashboard.toolbars.index')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700&display=swap" rel="stylesheet">
<!-- Modal Styles -->
<style>
    .profile-img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 50%;
        border: 2px solid #eee;
        background: #fafafa;
    }
    .card-custom {
        border-radius: 18px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.07);
        margin-bottom: 1.5rem;
        background: #fff;
    }
    .info-label {
        color: #888;
        font-size: 1rem;
        margin-bottom: 2px;
    }
    .info-value {
        font-size: 1.1rem;
        font-weight: bold;
        color: #222;
        margin-bottom: 8px;
    }
    .attachment-thumb {
        width: 48px;
        height: 48px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #ddd;
        margin-left: 8px;
        margin-bottom: 4px;
    }
    .main-header-row .d-flex span,
    .main-header-row .d-flex {
        font-size: 1.3rem;
    }
    .modal-backdrop.show { opacity: 0.3; }
    .modal-img-preview {
        display: block;
        margin: 0 auto;
        border-radius: 10px;
        box-sizing: border-box;
        object-fit: contain;
        width: 100%;
        height: auto;
        max-width: 100%;
        max-height: 60vh;
    }

    @media (max-width: 1200px) {
        .modal-img-preview {
            max-width: 100vw;
            max-height: 50vh;
        }
    }
    @media (max-width: 992px) {
        .modal-img-preview {
            max-width: 100vw;
            max-height: 40vh;
        }
    }
    @media (max-width: 767px) {
        .profile-img { width: 56px; height: 56px; }
        .card-custom { padding: 0.5rem; }
        .info-label, .info-value { font-size: 1.15rem !important; }
        .main-header-row {
            font-size: 1rem !important;
            padding: 10px 8px !important;
        }
        .main-header-row .d-flex span,
        .main-header-row .d-flex {
            font-size: 0.95rem !important;
        }
        .main-header-row .d-flex {
            gap: 4px !important;
        }
        .main-header-row .d-flex.gap-2 {
            gap: 4px !important;
            flex-wrap: wrap;
        }
        .main-header-row .btn {
            font-size: 0.85rem !important;
            padding: 4px 8px !important;
            min-width: 70px !important;
        }
        .modal-img-preview {
            max-width: 98vw !important;
            max-height: 32vh !important;
            width: 100% !important;
            height: auto !important;
        }
    }
    @media (max-width: 767px) {
        .card-custom > .main-header-row {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 8px !important;
        }
        .card-custom > .main-header-row > .d-flex:first-child {
            justify-content: center !important;
            margin-bottom: 0 !important;
        }
        .card-custom > .main-header-row > .d-flex.gap-2 {
            justify-content: center !important;
            align-self: auto !important;
            margin-bottom: 0 !important;
            margin-top: 0 !important;
        }
    }
    @media (max-width: 992px) {
        .family-scroll-row {
            flex-wrap: nowrap !important;
            overflow-x: auto;
            display: flex !important;
            gap: 12px;
            padding-bottom: 8px;
            margin-left: 0;
            margin-right: 0;
        }
        .family-scroll-row .family-member-card {
            min-width: 320px;
            max-width: 90vw;
            flex: 0 0 auto;
            margin-bottom: 0 !important;
        }
    }
</style>
<!-- Modal HTML -->
<div class="modal fade" id="attachmentsModal" tabindex="-1" aria-labelledby="attachmentsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="direction: rtl;">
      <div class="modal-header">
        <h5 class="modal-title" id="attachmentsModalLabel">عرض المرفقات</h5>
        <button type="button" class="btn-close ms-0" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>
      <div class="modal-body">
        <div id="attachmentsModalContent" class="d-flex flex-wrap justify-content-center align-items-center" style="gap: 16px;"></div>
        <div id="attachmentPreview" class="mt-4 text-center"></div>
      </div>
    </div>
  </div>
</div>
<div class="container">

    <h2 class="mb-4 text-center" style="font-family: 'Cairo', Arial, Tahoma, sans-serif;">عرض تفاصيل السجل</h2>

    {{-- بيانات الملف --}}
    <div class="card card-custom p-3 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 main-header-row"
             style="background: rgba(180,180,180,0.18); border-radius: 16px 16px 0 0; padding: 14px 24px; font-weight: bold; font-size: 1.3rem; font-family: 'Cairo', Arial, Tahoma, sans-serif; width: 100%;">
            <div class="d-flex align-items-center flex-wrap" style="gap: 16px;">
                <span>رقم الملف: {{ $data->file_id_number }}</span>
                <span style="border-right: 2px solid #bbb; height: 22px; margin: 0 12px;"></span>
                <span>القسم: {{ optional($data->section)->description }}</span>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.records.management.edit', $data->id) }}" class="btn btn-primary btn-sm" style="white-space: nowrap; min-width: 80px;">
                    <i class="bi bi-pencil-square"></i> تعديل
                </a>
                <form action="{{ route('admin.records.management.delete', $data->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف السجل؟');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm" style="white-space: nowrap; min-width: 80px;">
                        <i class="bi bi-trash"></i> حذف
                    </button>
                </form>
                <button onclick="window.history.back()" class="btn btn-warning btn-sm main-back-btn" style="white-space: nowrap; min-width: 80px;">
                    <i class="bi bi-arrow-left"></i> رجوع
                </button>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12 mb-3">
                <div class="d-flex flex-column align-items-start" style="direction: rtl;">
                    <div>
                        <span class="info-label" style="font-size:1.1rem;"> رقم الهوية :</span>
                        <span class="info-value" style="display:inline-block; font-size:1.25rem;">{{ $data->data_id_number }}</span>
                    </div>
                    <div>
                        <span class="info-label" style="font-size:1.1rem;">الاسم الكامل :</span>
                        <span class="info-value" style="display:inline-block; font-size:1.25rem;">
                            {{ $data->data_first_name }} {{ $data->data_father_name }} {{ $data->data_grand_father_name }} {{ $data->data_family_name }}
                        </span>
                    </div>
                    <hr style="border-top: 3px solid #ffc107; width: 220px; margin: 8px 0 0 0; border-radius: 2px;">
                </div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">تاريخ الميلاد</div>
                <div class="info-value">{{ $data->data_birth_date }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">حالة الطلب</div>
                <div class="info-value">{{ optional($data->requestStatus)->description }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">صلة القرابة</div>
                <div class="info-value">{{ optional($data->categoryOfRelation)->attribute }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">الحالة الصحية</div>
                <div class="info-value">{{ optional($data->healthStatus)->description }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">رقم الجوال</div>
                <div class="info-value">{{ $data->data_phone_number }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">رقم جوال إضافي</div>
                <div class="info-value">{{ $data->data_alt_phone_number }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">الحالة الاجتماعية</div>
                <div class="info-value">{{ optional($data->maritalStatus)->description }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">المؤهل العلمي</div>
                <div class="info-value">{{ optional($data->academicQualification)->description }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">المدينة</div>
                <div class="info-value">{{ optional($data->city)->city }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">المحافظة</div>
                <div class="info-value">{{ optional($data->province)->description }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">حالة عمل العائل</div>
                <div class="info-value">{{ optional($data->employmentStatusBreadwinner)->description }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">الحالة السكنية</div>
                <div class="info-value">{{ optional($data->housingStatus)->description }}</div>
            </div>
            <div class="col-md-4 col-6 mb-2">
                <div class="info-label">نوع السكن الحالي</div>
                <div class="info-value">{{ optional($data->currentHousingType)->description }}</div>
            </div>
            <div class="col-12 mb-2">
                <div class="info-label">وصف الاحتياج</div>
                <div class="info-value">{{ $data->data_description_needs }}</div>
            </div>
        </div>
        <div class="mt-3 w-100">
                <div class="info-label mb-1">المعلومات البنكية:</div>
                @php
                    // جلب جميع الحسابات البنكية المرتبطة بنفس رقم السجل العام (file_id_number)
                    $bankAccounts = \App\Models\GuardianBankAccount::where('guardian_registration', $data->file_id_number)->get();
                @endphp
                @if($bankAccounts->count())
                    <div class="row mb-2">
                        <div class="col-12">
                            <table class="table table-bordered table-sm" style="background:#fff;">
                                <thead>
                                    <tr>
                                        <th>اسم البنك</th>
                                        <th>رقم الآيبان (شيكل)</th>
                                        <th>رقم الآيبان (دولار)</th>
                                        <th>اسم صاحب الحساب</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bankAccounts as $account)
                                        <tr>
                                            <td>
                                                @php
                                                    // إذا كان bank_name رقم (id) فاعرض الوصف من جدول bank_names
                                                    $bankName = $account->bank_name;
                                                    if(is_numeric($bankName)) {
                                                        $bankModel = \App\Models\BankName::find($bankName);
                                                        $bankName = $bankModel ? $bankModel->description : $account->bank_name;
                                                    }
                                                @endphp
                                                {{ $bankName }}
                                            </td>
                                            <td>{{ $account->iban_shekel }}</td>
                                            <td>{{ $account->iban_usd }}</td>
                                            <td>{{ $account->re_guardian_name }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <span class="text-muted">لا توجد بيانات بنكية مسجلة.</span>
                @endif
            </div>
        {{-- المرفقات الخاصة بالبيانات الأساسية --}}
        <div class="mt-3 w-100">
            <div class="info-label mb-1">المرفقات:</div>
            @if($data->attachments && count($data->attachments))
                <div class="d-flex flex-wrap">
                    @foreach($data->attachments as $att)
                        @php
                            $isImage = Str::endsWith(strtolower($att->stored_file_name), ['jpg','jpeg','png','gif']);
                        @endphp
                        <a href="javascript:void(0);" class="open-attachments-modal"
                            data-attachments='@json($data->attachments->map(function($a){return [
                                "file_path" => route("admin.file.show", ["filename" => $a->stored_file_name ?: basename($a->file_path)]),
                                "stored_file_name" => $a->stored_file_name
                            ];}))'
                            data-index="{{ $loop->index }}">
                            @if($isImage)
                                <img src="{{ route('admin.file.show', ['filename' => $att->stored_file_name ?: basename($att->file_path)]) }}"
                                     class="attachment-thumb"
                                     alt="مرفق"
                                     oncontextmenu="return false;"
                                     ondragstart="return false;"
                                     style="user-select: none; -webkit-user-select: none;">
                            @else
                                <span class="attachment-thumb d-flex align-items-center justify-content-center bg-light">
                                    <i class="bi bi-file-earmark-pdf" style="font-size: 1.5rem; color: #d9534f;"></i>
                                </span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @else
                <span class="text-muted">لا يوجد</span>
            @endif
        </div>
    </div>


    {{-- أفراد الأسرة --}}
    <div class="card card-custom p-3 mb-4">
        <h5 class="mb-3">أفراد الأسرة</h5>
        @if($data->rePeople && count($data->rePeople))
            <div class="row family-scroll-row">
                @foreach($data->rePeople as $member)
                    <div class="col-md-6 col-lg-4 col-12 mb-4 family-member-card">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body d-flex flex-column align-items-start" style="direction: rtl;">
                                {{-- تم حذف صورة الشخص --}}
                                <div class="info-value mb-1">{{ $member->first_name ?? '' }} {{ $member->second_name ?? '' }} {{ $member->third_name ?? '' }} {{ $member->last_name ?? '' }}</div>
                                <div class="info-label mb-1">رقم الهوية: <span class="info-value">{{ $member->person_id ?? '-' }}</span></div>
                                <div class="info-label mb-1">العمر: <span class="info-value">{{ $member->person_age ?? '-' }}</span></div>
                                <div class="info-label mb-1">الجنس: <span class="info-value">
                                    @if($member->person_gender == 1) ذكر @elseif($member->person_gender == 2) أنثى @else - @endif
                                </span></div>
                                <div class="info-label mb-1">الحالة الصحية: <span class="info-value">{{ optional($member->healthStatus)->description ?? '-' }}</span></div>
                                <div class="info-label mb-1">نوع الكفالة: <span class="info-value">{{ optional($member->guaranteeType)->description ?? '-' }}</span></div>
                                <div class="info-label mb-1">حالة الكفالة: <span class="info-value">{{ optional($member->sponsorshipStatus)->description ?? '-' }}</span></div>
                                <div class="info-label mb-1">تاريخ الميلاد: <span class="info-value">{{ $member->person_birth_date ?? '-' }}</span></div>
                                {{-- مرفقات الشخص --}}
                                <div class="mt-2 w-100">
                                    <div class="info-label mb-1">المرفقات:</div>
                                    @if($member->attachments && count($member->attachments))
                                        <div class="d-flex flex-wrap">
                                            @foreach($member->attachments as $att)
                                                @php
                                                    $isImage = Str::endsWith(strtolower($att->stored_file_name), ['jpg','jpeg','png','gif']);
                                                @endphp
                                                <a href="javascript:void(0);" class="open-attachments-modal"
                                                    data-attachments='@json($member->attachments->map(function($a){return [
                                                        "file_path" => route("admin.file.show", ["filename" => $a->stored_file_name ?: basename($a->file_path)]),
                                                        "stored_file_name" => $a->stored_file_name
                                                    ];}))'
                                                    data-index="{{ $loop->index }}">
                                                    @if($isImage)
                                                        <img src="{{ route('admin.file.show', ['filename' => $att->stored_file_name ?: basename($att->file_path)]) }}"
                                                             class="attachment-thumb"
                                                             alt="مرفق"
                                                             oncontextmenu="return false;"
                                                             ondragstart="return false;"
                                                             style="user-select: none; -webkit-user-select: none;">
                                                    @else
                                                        <span class="attachment-thumb d-flex align-items-center justify-content-center bg-light">
                                                            <i class="bi bi-file-earmark-pdf" style="font-size: 1.5rem; color: #d9534f;"></i>
                                                        </span>
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-muted">لا يوجد</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-muted">لا يوجد أفراد أسرة.</p>
        @endif
    </div>

    {{-- بيانات المتوفين --}}
    <div class="card card-custom p-3 mb-4">
        <h5 class="mb-3">بيانات المتوفين</h5>
        @if($data->deadPepole)
            <div class="row">
                {{-- الأب --}}
                @if($data->deadPepole->father_first_name || $data->deadPepole->father_last_name || $data->deadPepole->father_id)
                    @php
                        $fatherAttachments = $data->deadPepole->fatherAttachments ?? collect();
                    @endphp
                    <div class="col-md-6 col-12 mb-3">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body d-flex flex-column align-items-start" style="direction: rtl;">
                                {{-- تم حذف صورة الأب --}}
                                <div class="info-value mb-1">الأب: {{ $data->deadPepole->father_first_name ?? '' }} {{ $data->deadPepole->father_second_name ?? '' }} {{ $data->deadPepole->father_third_name ?? '' }} {{ $data->deadPepole->father_last_name ?? '' }}</div>
                                <div class="info-label mb-1">رقم الهوية: <span class="info-value">{{ $data->deadPepole->father_id ?? '-' }}</span></div>
                                <div class="info-label mb-1">تاريخ الوفاة: <span class="info-value">{{ $data->deadPepole->father_death_date ?? '-' }}</span></div>
                                <div class="info-label mb-1">سبب الوفاة: <span class="info-value">{{ optional($data->deadPepole->fatherDeathReason)->description ?? '-' }}</span></div>
                                {{-- مرفقات الأب --}}
                                <div class="mt-2 w-100">
                                    <div class="info-label mb-1">المرفقات:</div>
                                    @if($fatherAttachments && count($fatherAttachments))
                                        <div class="d-flex flex-wrap">
                                            @foreach($fatherAttachments as $att)
                                                @php
                                                    $isImage = Str::endsWith(strtolower($att->stored_file_name), ['jpg','jpeg','png','gif']);
                                                @endphp
                                                <a href="javascript:void(0);" class="open-attachments-modal"
                                                    data-attachments='@json($fatherAttachments->map(function($a){return [
                                                        "file_path" => route("admin.file.show", ["filename" => $a->stored_file_name ?: basename($a->file_path)]),
                                                        "stored_file_name" => $a->stored_file_name
                                                    ];}))'
                                                    data-index="{{ $loop->index }}">
                                                    @if($isImage)
                                                        <img src="{{ route('admin.file.show', ['filename' => $att->stored_file_name ?: basename($att->file_path)]) }}"
                                                             class="attachment-thumb"
                                                             alt="مرفق"
                                                             oncontextmenu="return false;"
                                                             ondragstart="return false;"
                                                             style="user-select: none; -webkit-user-select: none;">
                                                    @else
                                                        <span class="attachment-thumb d-flex align-items-center justify-content-center bg-light">
                                                            <i class="bi bi-file-earmark-pdf" style="font-size: 1.5rem; color: #d9534f;"></i>
                                                        </span>
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-muted">لا يوجد</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                {{-- الأم --}}
                @if($data->deadPepole->mother_first_name || $data->deadPepole->mother_last_name || $data->deadPepole->mother_id)
                    @php
                        $motherAttachments = $data->deadPepole->motherAttachments ?? collect();
                    @endphp
                    <div class="col-md-6 col-12 mb-3">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body d-flex flex-column align-items-start" style="direction: rtl;">
                                {{-- تم حذف صورة الأم --}}
                                <div class="info-value mb-1">الأم: {{ $data->deadPepole->mother_first_name ?? '' }} {{ $data->deadPepole->mother_second_name ?? '' }} {{ $data->deadPepole->mother_third_name ?? '' }} {{ $data->deadPepole->mother_last_name ?? '' }}</div>
                                <div class="info-label mb-1">رقم الهوية: <span class="info-value">{{ $data->deadPepole->mother_id ?? '-' }}</span></div>
                                <div class="info-label mb-1">تاريخ الوفاة: <span class="info-value">{{ $data->deadPepole->mother_death_date ?? '-' }}</span></div>
                                <div class="info-label mb-1">سبب الوفاة: <span class="info-value">{{ optional($data->deadPepole->motherDeathReason)->description ?? '-' }}</span></div>
                                {{-- مرفقات الأم --}}
                                <div class="mt-2 w-100">
                                    <div class="info-label mb-1">المرفقات:</div>
                                    @if($motherAttachments && count($motherAttachments))
                                        <div class="d-flex flex-wrap">
                                            @foreach($motherAttachments as $att)
                                                @php
                                                    $isImage = Str::endsWith(strtolower($att->stored_file_name), ['jpg','jpeg','png','gif']);
                                                @endphp
                                                <a href="javascript:void(0);" class="open-attachments-modal"
                                                    data-attachments='@json($motherAttachments->map(function($a){return [
                                                        "file_path" => route("admin.file.show", ["filename" => $a->stored_file_name ?: basename($a->file_path)]),
                                                        "stored_file_name" => $a->stored_file_name
                                                    ];}))'
                                                    data-index="{{ $loop->index }}">
                                                    @if($isImage)
                                                        <img src="{{ route('admin.file.show', ['filename' => $att->stored_file_name ?: basename($att->file_path)]) }}"
                                                             class="attachment-thumb"
                                                             alt="مرفق"
                                                             oncontextmenu="return false;"
                                                             ondragstart="return false;"
                                                             style="user-select: none; -webkit-user-select: none;">
                                                    @else
                                                        <span class="attachment-thumb d-flex align-items-center justify-content-center bg-light">
                                                            <i class="bi bi-file-earmark-pdf" style="font-size: 1.5rem; color: #d9534f;"></i>
                                                        </span>
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-muted">لا يوجد</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @else
            <p class="text-muted">لا توجد بيانات متوفين.</p>
        @endif
    </div>
</div>
@push('scriptsCode')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // دعم جميع مناطق المرفقات (الرئيسية، أفراد الأسرة، المتوفين)
    document.querySelectorAll('.open-attachments-modal').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            let attachments = [];
            try {
                attachments = JSON.parse(this.getAttribute('data-attachments'));
            } catch {}
            let index = parseInt(this.getAttribute('data-index')) || 0;
            showAttachmentsModal(attachments, index);
        });
    });

    function showAttachmentsModal(attachments, activeIndex) {
        const modalContent = document.getElementById('attachmentsModalContent');
        const preview = document.getElementById('attachmentPreview');
        modalContent.innerHTML = '';
        preview.innerHTML = '';

        attachments.forEach(function(att, idx) {
            let isImage = /\.(jpg|jpeg|png|gif)$/i.test(att.stored_file_name);
            let thumb = '';
            if(isImage) {
                thumb = `<img src="${att.file_path}" class="attachment-thumb" style="cursor:pointer;" data-preview-index="${idx}" alt="مرفق" oncontextmenu="return false;" ondragstart="return false;">`;
            } else {
                thumb = `<span class="attachment-thumb d-flex align-items-center justify-content-center bg-light" style="cursor:pointer;" data-preview-index="${idx}">
                    <i class="bi bi-file-earmark-pdf" style="font-size: 1.5rem; color: #d9534f;"></i>
                </span>`;
            }
            modalContent.innerHTML += `<a href="javascript:void(0);" data-preview-index="${idx}" class="me-2">${thumb}</a>`;
        });

        function renderPreview(idx) {
            let att = attachments[idx];
            let isImage = /\.(jpg|jpeg|png|gif)$/i.test(att.stored_file_name);

            if(isImage) {
                preview.innerHTML = `<img src="${att.file_path}" class="modal-img-preview" alt="مرفق" oncontextmenu="return false;" ondragstart="return false;">`;
            } else {
                preview.innerHTML = `
                    <div class="text-center">
                        <div class="mb-3">
                            <i class="bi bi-file-earmark-pdf" style="font-size: 4rem; color: #d9534f;"></i>
                        </div>
                        <h5>${att.stored_file_name}</h5>
                        <a href="${att.file_path}" target="_blank" class="btn btn-primary">
                            <i class="bi bi-eye"></i> عرض الملف
                        </a>
                    </div>
                `;
            }
        }

        renderPreview(activeIndex);

        modalContent.querySelectorAll('[data-preview-index]').forEach(function(el) {
            el.addEventListener('click', function() {
                renderPreview(parseInt(this.getAttribute('data-preview-index')));
            });
        });

        var modal = new bootstrap.Modal(document.getElementById('attachmentsModal'));
        modal.show();
    }
});
</script>
@endpush
@endsection
