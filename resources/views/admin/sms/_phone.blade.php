{{-- معاينة الرسالة كما تظهر في الهاتف — @include('admin.sms._phone', ['id' => 'single', 'title' => 'رسالة جديدة']) --}}
<div class="card border">
    <div class="card-header py-2">
        <span class="fw-semibold">معاينة الهاتف</span>
    </div>
    <div class="card-body py-3">
        <div class="sms-phone">
            <div class="sms-phone-bar">
                <span class="sms-phone-contact" id="{{ $id }}-contact">{{ $title ?? 'رسالة جديدة' }}</span>
                <span class="sms-phone-time">الآن</span>
            </div>
            <div class="sms-phone-body">
                <div class="sms-bubble" id="{{ $id }}-bubble"></div>
            </div>
            <div class="sms-phone-input">اكتب رسالة...</div>
        </div>
        <small class="text-muted d-block text-center mt-2">
            معاينة تقريبية — عدد الأجزاء الفعلي يظهر عند "حساب الكلفة".
        </small>
    </div>
</div>
