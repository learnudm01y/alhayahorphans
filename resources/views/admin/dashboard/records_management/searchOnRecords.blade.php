@extends('admin.dashboard.toolbars.index')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">البحث الشامل في السجلات</h3></div>
            <div class="card-body">
                @include('admin.dashboard.records_management.search_modal_content')
            </div>
        </div>
    </div>
@endsection

@push('scriptsCode')
    <script src="{{ asset('js/search-results.js') }}?v={{ filemtime(public_path('js/search-results.js')) }}"></script>
    <script>
        $(function () { RecordSearch.mount(document.querySelector('[data-record-search]')); });
    </script>
@endpush
