{{-- هذا الملف يحتوي على عرض البيانات القديمة للأشخاص الذين ليس لهم كفالة --}}
<div class="card card-custom p-3 mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2 main-header-row" style="background: rgba(180,180,180,0.18); border-radius: 16px 16px 0 0; padding: 14px 24px; font-weight: bold; font-size: 1.3rem; font-family: 'Cairo', Arial, Tahoma, sans-serif; width: 100%;">
        <div class="d-flex align-items-center flex-wrap" style="gap: 16px;">
            <span>رقم الملف: {{ $data->file_id_number }}</span>
            <span style="border-right: 2px solid #bbb; height: 22px; margin: 0 12px;"></span>
            <span>القسم: {{ optional($data->section)->description }}</span>
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
                    <span class="info-value" style="display:inline-block; font-size:1.25rem;">{{ $data->data_first_name }} {{ $data->data_father_name }} {{ $data->data_grand_father_name }} {{ $data->data_family_name }}</span>
                </div>
                <hr style="border-top: 3px solid #ffc107; width: 220px; margin: 8px 0 0 0; border-radius: 2px;">
            </div>
        </div>
        <div class="col-md-4 col-6 mb-2"><div class="info-label">تاريخ الميلاد</div><div class="info-value">{{ $data->data_birth_date }}</div></div>
        <div class="col-md-4 col-6 mb-2"><div class="info-label">حالة الطلب</div><div class="info-value">{{ optional($data->requestStatus)->description }}</div></div>
        <div class="col-md-4 col-6 mb-2"><div class="info-label">صلة القرابة</div><div class="info-value">{{ optional($data->categoryOfRelation)->attribute }}</div></div>
        <div class="col-md-4 col-6 mb-2"><div class="info-label">الحالة الصحية</div><div class="info-value">{{ optional($data->healthStatus)->description }}</div></div>
        <div class="col-md-4 col-6 mb-2"><div class="info-label">رقم الجوال</div><div class="info-value">{{ $data->data_phone_number }}</div></div>
        <div class="col-md-4 col-6 mb-2"><div class="info-label">رقم جوال إضافي</div><div class="info-value">{{ $data->data_alt_phone_number }}</div></div>
    </div>
</div>
