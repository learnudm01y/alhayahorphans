<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                {{-- حقل مخفي لرقم الملف العام من بوابة البيانات الأساسية --}}
                <input type="hidden" id="main_file_id_number" value="{{ $data->file_id_number ?? $file_id_number ?? '' }}">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">بيانات أفراد الأسرة</h5>
                    <button type="button" class="btn btn-primary" id="addFamilyMember">
                        <i class="fas fa-plus me-2"></i>إضافة فرد
                    </button>
                </div>
                <div id="familyMembersContainer">
                    @if(isset($data) && $edit && $data->rePeople && count($data->rePeople))
                        @foreach($data->rePeople as $index => $member)
                            <div class="family-member-form border rounded mb-3 position-relative">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center" style="background: #f8f9fa;">
                                        <span class="fw-bold">فرد رقم {{ $index + 1 }}</span>
                                        <button type="button"
                                            class="btn btn-danger btn-sm delete-family-member"
                                            data-index="{{ $index }}"
                                            data-member-id="{{ $member->id }}">
                                            حذف الفرد
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <input type="hidden" name="family_members[{{ $index }}][file_id]" value="{{ $member->file_id ?? $data->file_id_number ?? '' }}">
                                            <input type="hidden" name="family_members[{{ $index }}][id]" value="{{ $member->id }}">
                                            <div class="col-md-6">
                                                <label class="form-label">رقم التسجيل <span class="text-danger">*</span></label>
                                                <input type="text" name="family_members[{{ $index }}][registration_id]" class="form-control bg-secondary bg-opacity-10 registration-id-input" readonly value="">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">حالة الكفالة</label>
                                                <select name="family_members[{{ $index }}][sponsorship_status]" class="form-select">
                                                    <option value="">اختر الحالة</option>
                                                    @foreach ($sponsorship_status as $status)
                                                        <option value="{{ $status->id }}" {{ $member->sponsorship_status == $status->id ? 'selected' : '' }}>{{ $status->description }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                                                <input type="text" name="family_members[{{ $index }}][first_name]" class="form-control" value="{{ $member->first_name }}">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">الاسم الثاني</label>
                                                <input type="text" name="family_members[{{ $index }}][second_name]" class="form-control" value="{{ $member->second_name }}">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">الاسم الثالث</label>
                                                <input type="text" name="family_members[{{ $index }}][third_name]" class="form-control" value="{{ $member->third_name }}">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
                                                <input type="text" name="family_members[{{ $index }}][last_name]" class="form-control" value="{{ $member->last_name }}">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">رقم هوية اليتيم</label>
                                                <input type="text" name="family_members[{{ $index }}][person_id]" class="form-control" inputmode="numeric" pattern="[0-9]*" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '');" value="{{ $member->person_id }}">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">تاريخ الميلاد <span class="text-danger">*</span></label>
                                                <input type="date" name="family_members[{{ $index }}][person_birth_date]" class="form-control" value="{{ $member->person_birth_date }}">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">العمر</label>
                                                <input type="number" name="family_members[{{ $index }}][person_age]" class="form-control" readonly value="{{ $member->person_age }}">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">الجنس <span class="text-danger">*</span></label>
                                                <select name="family_members[{{ $index }}][person_gender]" class="form-select" required>
                                                    <option value="">اختر الجنس</option>
                                                    <option value="1" {{ $member->person_gender == 1 ? 'selected' : '' }}>ذكر</option>
                                                    <option value="2" {{ $member->person_gender == 2 ? 'selected' : '' }}>أنثى</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">الحالة الصحية</label>
                                                <select name="family_members[{{ $index }}][person_health_status]" class="form-select">
                                                    <option value="">اختر الحالة</option>
                                                    @foreach ($health_status as $status)
                                                        <option value="{{ $status->id }}" {{ $member->person_health_status == $status->id ? 'selected' : '' }}>{{ $status->description }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">نوع الكفالة</label>
                                                <select name="family_members[{{ $index }}][person_type_of_guarantee]" class="form-select">
                                                    <option value="">اختر النوع</option>
                                                    @foreach ($guarantee_types as $type)
                                                        <option value="{{ $type->id }}" {{ $member->person_type_of_guarantee == $type->id ? 'selected' : '' }}>{{ $type->description }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <div class="documents-flex-container mb-2">
                                                    @foreach($data->attachments->where('person_identity_number', $member->person_id) as $attachment)
                                                        <div class="document-card card">
                                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                                <h6>{{ $attachment->file_type }}</h6>
                                                                <div>
                                                                    <a href="{{ route('admin.file.show', ['filename' => $attachment->stored_file_name ?: basename($attachment->file_path)]) }}" target="_blank" class="btn btn-sm btn-primary">عرض</a>
                                                                    <button type="button" class="btn btn-sm btn-danger delete-attachment" data-id="{{ $attachment->id }}">حذف</button>
                                                                </div>
                                                            </div>
                                                            <div class="card-body text-center">
                                                                @if(Str::endsWith($attachment->file_path, ['jpg','jpeg','png']))
                                                                    <img src="{{ route('admin.file.show', ['filename' => $attachment->stored_file_name ?: basename($attachment->file_path)]) }}" class="img-fluid" style="max-height:120px;">
                                                                @else
                                                                    <span class="text-muted">{{ $attachment->stored_file_name }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                     @endif
                        {{-- <div class="family-member-form border rounded p-3 mb-3 position-relative">
                            <!-- هيدر خاص للكارد الفارغ مع زر الحذف x -->
                            <div class="card-header d-flex justify-content-end align-items-center" style="background: #f8f9fa; border-bottom: 1px solid #eee; min-height: 48px;">
                                <button type="button" class="btn btn-light btn-sm delete-family-member-x custom-x-btn" title="حذف">
                                    <span aria-hidden="true" style="font-size:1.2rem;">&times;</span>
                                </button>
                            </div>
                            <div class="row g-3">
                                <input type="hidden" name="family_members[0][file_id]" value="{{ $file_id_number ?? '' }}">
                                <div class="col-md-6">
                                    <label class="form-label">رقم التسجيل <span class="text-danger">*</span></label>
                                    <input type="text" name="family_members[0][registration_id]" class="form-control bg-secondary bg-opacity-10 registration-id-input" readonly value="">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">حالة الكفالة</label>
                                    <select name="family_members[0][sponsorship_status]" class="form-select">
                                        <option value="">اختر الحالة</option>
                                        @foreach ($sponsorship_status as $status)
                                            <option value="{{ $status->id }}">{{ $status->description }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                                    <input type="text" name="family_members[0][first_name]" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">الاسم الثاني</label>
                                    <input type="text" name="family_members[0][second_name]" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">الاسم الثالث</label>
                                    <input type="text" name="family_members[0][third_name]" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
                                    <input type="text" name="family_members[0][last_name]" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">رقم هوية اليتيم</label>
                                    <input type="text" name="family_members[0][person_id]" class="form-control" inputmode="numeric" pattern="[0-9]*" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">تاريخ الميلاد <span class="text-danger">*</span></label>
                                    <input type="date" name="family_members[0][person_birth_date]" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">العمر</label>
                                    <input type="number" name="family_members[0][person_age]" class="form-control" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">الجنس <span class="text-danger">*</span></label>
                                    <select name="family_members[0][person_gender]" class="form-select" required>
                                        <option value="">اختر الجنس</option>
                                        <option value="1">ذكر</option>
                                        <option value="2">أنثى</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">الحالة الصحية</label>
                                    <select name="family_members[0][person_health_status]" class="form-select">
                                        <option value="">اختر الحالة</option>
                                        @foreach ($health_status as $status)
                                            <option value="{{ $status->id }}">{{ $status->description }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">نوع الكفالة</label>
                                    <select name="family_members[0][person_type_of_guarantee]" class="form-select">
                                        <option value="">اختر النوع</option>
                                        @foreach ($guarantee_types as $type)
                                            <option value="{{ $type->id }}">{{ $type->description }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div> --}}

                </div>
            </div>
        </div>
    </div>
</div>

@push('scriptsCode')
<script>
function fadeOutAndRemove(element, duration = 400) {
    element.style.transition = `opacity ${duration}ms`;
    element.style.opacity = 0;
    setTimeout(() => {
        element.remove();
    }, duration);
}
document.addEventListener('DOMContentLoaded', function() {
    // اجلب رقم الملف العام من الحقل المخفي
    var mainFileId = document.getElementById('main_file_id_number')?.value || '';

    // عيّن رقم التسجيل لكل حقل عند تحميل الصفحة أو عند إضافة فرد جديد
    function setRegistrationIdForFamilyMembers() {
        document.querySelectorAll('.registration-id-input').forEach(function(input) {
            if (!input.value && mainFileId) {
                input.value = mainFileId;
            }
        });
    }

    setRegistrationIdForFamilyMembers();

    // عند الضغط على زر إضافة فرد (بعد توليد الكارد الجديد)
    var addBtn = document.getElementById('addFamilyMember');
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            setTimeout(function() {
                // تحقق أن العنصر موجود قبل استخدامه لتفادي الخطأ
                if (typeof setRegistrationIdForFamilyMembers === 'function') {
                    setRegistrationIdForFamilyMembers();
                }
            }, 200);
        });
    }

    // SweetAlert لجميع أزرار الحذف (حذف الفرد + حذف المرفق)
    document.body.addEventListener('click', function(e) {
        // حذف فرد الأسرة (زر حذف الفرد)
        if (e.target.closest('.delete-family-member')) {
            e.preventDefault();
            var btn = e.target.closest('.delete-family-member');
            var memberId = btn.getAttribute('data-member-id');
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: "سيتم حذف بيانات هذا الفرد!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    var card = btn.closest('.family-member-form');
                    if (memberId) {
                        // حذف من السيرفر عبر AJAX
                        fetch("{{ url('admin/records-management/delete-family-member') }}/" + memberId, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (card) fadeOutAndRemove(card, 500);
                                Swal.fire('تم الحذف!', 'تم حذف فرد الأسرة بنجاح.', 'success');
                            } else {
                                Swal.fire('خطأ!', data.message || 'حدث خطأ أثناء الحذف.', 'error');
                            }
                        })
                        .catch(() => {
                            Swal.fire('خطأ!', 'حدث خطأ أثناء الحذف.', 'error');
                        });
                    } else {
                        // حذف الكارد من الواجهة فقط (لم تتم إضافته بعد)
                        if (card) fadeOutAndRemove(card, 500);
                    }
                }
            });
        }

        // حذف فرد الأسرة (زر x للبطاقات الفارغة)
        if (e.target.closest('.delete-family-member-x')) {
            e.preventDefault();
            var btn = e.target.closest('.delete-family-member-x');
            var card = btn.closest('.family-member-form');
            // تأثير تلاشي بدون تأكيد
            if (card) fadeOutAndRemove(card, 400);
        }

        // حذف المرفق
        if (e.target.closest('.delete-attachment')) {
            e.preventDefault();
            var btn = e.target.closest('.delete-attachment');
            var attId = btn.getAttribute('data-id');
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: "سيتم حذف هذا المرفق!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    // حذف من قاعدة البيانات عبر AJAX
                    fetch("{{ url('admin/records-management/delete-attachment') }}/" + attId, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            var card = btn.closest('.document-card');
                            if (card) fadeOutAndRemove(card, 400);
                            Swal.fire('تم الحذف!', 'تم حذف المرفق بنجاح.', 'success');
                        } else {
                            Swal.fire('خطأ!', data.message || 'حدث خطأ أثناء الحذف من قاعدة البيانات.', 'error');
                        }
                    })
                    .catch(() => {
                        Swal.fire('خطأ!', 'حدث خطأ أثناء الحذف من قاعدة البيانات.', 'error');
                    });
                }
            });
        }
    });
});
</script>
{{-- ...existing code... --}}
@endpush

<style>
.delete-family-member {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 10;
    transition: opacity 0.4s;
}
.delete-family-member-x {
    color: #dc3545;
    background: #fff;
    border: 1px solid #eee;
    transition: background 0.2s, color 0.2s;
}
.delete-family-member-x:hover {
    background: #dc3545;
    color: #fff;
}
.delete-family-member-x.custom-x-btn {
    color: #fff;
    background: #ffc107;
    border: 1px solid #ffc107;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    box-shadow: 0 2px 6px #0001;
    transition: background 0.2s, color 0.2s, border 0.2s;
}
.delete-family-member-x.custom-x-btn:hover,
.delete-family-member-x.custom-x-btn:focus {
    background: #dc3545 !important;
    color: #fff !important;
    border: 1px solid #dc3545 !important;
}
</style>

