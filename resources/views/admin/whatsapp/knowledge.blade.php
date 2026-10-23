@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container-fluid" dir="rtl">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">قاعدة معرفة الواتساب (الذكاء الاصطناعي)</h4>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.whatsapp.index') }}">طلبات الواتساب</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header">النص العام (اسم الجمعية، الدوام، الشروط، الوثائق...)</div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.whatsapp.knowledge.general') }}">
                @csrf
                <textarea name="content" rows="12" class="form-control mb-2" dir="rtl">{{ old('content', $content) }}</textarea>
                <button type="submit" class="btn btn-primary btn-sm">حفظ النص العام</button>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            {{ $editing ? 'تعديل سؤال' : 'إضافة سؤال وجواب' }}
            @if($editing)
                <a class="float-start btn btn-link btn-sm" href="{{ route('admin.whatsapp.knowledge') }}">إلغاء التعديل</a>
            @endif
        </div>
        <div class="card-body">
            <form method="post"
                  action="{{ $editing ? route('admin.whatsapp.knowledge.update', $editing) : route('admin.whatsapp.knowledge.store') }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label">السؤال</label>
                        <input type="text" name="question" maxlength="255" required class="form-control"
                               value="{{ old('question', $editing?->question) }}" placeholder="مثال: ما هي الوثائق المطلوبة؟">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">الجواب</label>
                        <input type="text" name="answer" maxlength="3000" required class="form-control"
                               value="{{ old('answer', $editing?->answer) }}" placeholder="الجواب المختصر الذي يرسله البوت">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">الترتيب</label>
                        <input type="number" name="sort_order" min="0" max="9999" class="form-control"
                               value="{{ old('sort_order', $editing?->sort_order ?? 0) }}">
                    </div>
                </div>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="faq-active"
                           @checked(old('is_active', $editing?->is_active ?? true))>
                    <label class="form-check-label" for="faq-active">مفعَّل (يُستخدم في إجابات البوت)</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm mt-2">{{ $editing ? 'حفظ التعديل' : 'إضافة' }}</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>السؤال</th>
                        <th>الجواب</th>
                        <th>الترتيب</th>
                        <th>الحالة</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($faqs as $i => $faq)
                        <tr>
                            <td>{{ $faqs->firstItem() + $i }}</td>
                            <td>{{ $faq->question }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($faq->answer, 60) }}</td>
                            <td>{{ $faq->sort_order }}</td>
                            <td>
                                @if($faq->is_active)
                                    <span class="badge bg-success">مفعَّل</span>
                                @else
                                    <span class="badge bg-secondary">معطَّل</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <a class="btn btn-outline-primary btn-sm"
                                   href="{{ route('admin.whatsapp.knowledge', ['faq' => $faq->id]) }}">تعديل</a>

                                <form method="post" action="{{ route('admin.whatsapp.knowledge.update', $faq) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="question" value="{{ $faq->question }}">
                                    <input type="hidden" name="answer" value="{{ $faq->answer }}">
                                    <input type="hidden" name="sort_order" value="{{ $faq->sort_order }}">
                                    <input type="hidden" name="is_active" value="{{ $faq->is_active ? 0 : 1 }}">
                                    <button type="submit" class="btn btn-outline-warning btn-sm">
                                        {{ $faq->is_active ? 'تعطيل' : 'تفعيل' }}
                                    </button>
                                </form>

                                <form method="post" action="{{ route('admin.whatsapp.knowledge.destroy', $faq) }}"
                                      class="d-inline" onsubmit="return confirm('حذف هذا السؤال نهائياً؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">لا توجد أسئلة بعد — أضف من النموذج أعلاه</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="d-flex justify-content-center mt-3">
                {{ $faqs->links('components.simple-pagination') }}
            </div>
        </div>
    </div>
</div>
@endsection
