@extends('layouts.app')

@section('title', 'لوحة تحكم المستخدم')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">مرحباً {{ $user->name }}</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-user"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">اسم المستخدم</span>
                                    <span class="info-box-number">{{ $user->name }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-success"><i class="fas fa-envelope"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">البريد الإلكتروني</span>
                                    <span class="info-box-number">{{ $user->email }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <h5><i class="icon fas fa-info"></i> مرحباً بك!</h5>
                                هذه هي لوحة تحكم المستخدم. يمكنك من هنا الوصول إلى جميع الخدمات المتاحة لك.
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">الخدمات</h3>
                                </div>
                                <div class="card-body">
                                    <a href="{{ route('user.generalRegistration.index') }}" class="btn btn-primary btn-block">
                                        <i class="fas fa-plus"></i> التسجيل العام
                                    </a>
                                </div>
                            </div>
                        </div>

                     

                        <div class="col-md-4">
                            <div class="card card-warning">
                                <div class="card-header">
                                    <h3 class="card-title">الإعدادات</h3>
                                </div>
                                <div class="card-body">
                                    <a href="{{ route('user.settings') }}" class="btn btn-warning btn-block">
                                        <i class="fas fa-cog"></i> الإعدادات
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
