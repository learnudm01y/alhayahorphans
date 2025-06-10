@extends('admin.dashboard.toolbars.index')
@section('content')
<div class="card">
    <!--begin::Card header-->
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h2>عرض بيانات المستخدم</h2>
        </div>
        <div class="card-toolbar">
            <div class="d-flex justify-content-end">
                <a href="{{route('admin.role.management101') }}" class="btn btn-light-primary">
                    <i class="ki-duotone ki-arrow-left fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <span class="ms-2">رجوع</span>
                </a>
            </div>
        </div>
    </div>
    <!--end::Card header-->

    <!--begin::Card body-->
    <div class="card-body py-4">
        <div class="row g-9">
            <!--begin::Col-->
            <div class="col-md-6 border-end">
                <div class="py-3">
                    <div class="fs-5 text-muted fw-semibold mb-2">الاسم</div>
                    <div class="fs-4 fw-bold">{{ $user->name }}</div>
                </div>
                <div class="py-3">
                    <div class="fs-5 text-muted fw-semibold mb-2">البريد الإلكتروني</div>
                    <div class="fs-4 fw-bold">{{ $user->email }}</div>
                </div>
            </div>
            <!--end::Col-->

            <!--begin::Col-->
            <div class="col-md-6">
                <div class="py-3">
                    <div class="fs-5 text-muted fw-semibold mb-2">الأدوار</div>
                    <div class="d-flex flex-wrap gap-2">
                        @if(!empty($user->getRoleNames()))
                            @foreach($user->getRoleNames() as $role)
                                <span class="badge badge-light-primary fs-7 fw-bold">{{ $role }}</span>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
            <!--end::Col-->
        </div>
    </div>
    <!--end::Card body-->
</div>
@endsection

@push('styles')
<style>
    .badge {
        padding: 0.5rem 1rem;
    }
</style>
@endpush
