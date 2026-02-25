@php
    use App\Models\GeneralCategory;
    use App\Models\Data;
    $categories = GeneralCategory::all();
    $today = now()->format('Y-m-d');
    $month = now()->format('Y-m');
    $year = now()->format('Y');
@endphp

<div class="container-fluid my-4 px-3" style="max-width: 1400px;">
    <h4 class="mb-3">إحصائيات التصنيفات العامة</h4>

    {{-- Desktop: عرض عادي بتسطير تلقائي --}}
    <div class="d-none d-md-flex flex-wrap" id="category-cards-desktop" style="gap: 1rem;">
        @forelse($categories as $category)
            @php
                $countAll = Data::where('data_section_id', $category->id)->count();
                $countToday = Data::where('data_section_id', $category->id)
                    ->whereDate('created_at', $today)->count();
                $countMonth = Data::where('data_section_id', $category->id)
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)->count();
                $countYear = Data::where('data_section_id', $category->id)
                    ->whereYear('created_at', now()->year)->count();
            @endphp
            <div class="category-desktop-card" style="flex: 1 1 220px; min-width: 200px; max-width: 280px;">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fa fa-layer-group fa-2x text-info me-2"></i>
                            <h5 class="card-title mb-0 text-truncate">{{ $category->description }}</h5>
                        </div>
                        <ul class="list-group list-group-flush mb-0">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>إجمالي السجلات</span>
                                <span class="badge bg-primary animated-counter-en" data-target="{{ $countAll }}">0</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>اليوم</span>
                                <span class="badge bg-success animated-counter-en" data-target="{{ $countToday }}">0</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>هذا الشهر</span>
                                <span class="badge bg-info animated-counter-en" data-target="{{ $countMonth }}">0</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>هذه السنة</span>
                                <span class="badge bg-warning text-dark animated-counter-en" data-target="{{ $countYear }}">0</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info">لا يوجد تصنيفات عامة.</div>
            </div>
        @endforelse
    </div>

    {{-- Mobile: Carousel بكرت واحد --}}
    <div class="d-md-none" id="category-cards-carousel-wrapper" style="position:relative; overflow:hidden;">
        <div id="category-cards-row" style="display:flex; transition:transform 0.4s cubic-bezier(.4,0,.2,1); will-change:transform;">
            @foreach($categories as $category)
                @php
                    $countAll = Data::where('data_section_id', $category->id)->count();
                    $countToday = Data::where('data_section_id', $category->id)
                        ->whereDate('created_at', $today)->count();
                    $countMonth = Data::where('data_section_id', $category->id)
                        ->whereYear('created_at', now()->year)
                        ->whereMonth('created_at', now()->month)->count();
                    $countYear = Data::where('data_section_id', $category->id)
                        ->whereYear('created_at', now()->year)->count();
                @endphp
                <div class="category-carousel-card" style="min-width:100%; box-sizing:border-box; padding: 0 0.5rem;">
                    <div class="card shadow-sm">
                        <div class="card-body d-flex flex-column justify-content-between">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fa fa-layer-group fa-2x text-info me-2"></i>
                                <h5 class="card-title mb-0 text-truncate">{{ $category->description }}</h5>
                            </div>
                            <ul class="list-group list-group-flush mb-0">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>إجمالي السجلات</span>
                                    <span class="badge bg-primary animated-counter-en" data-target="{{ $countAll }}">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>اليوم</span>
                                    <span class="badge bg-success animated-counter-en" data-target="{{ $countToday }}">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>هذا الشهر</span>
                                    <span class="badge bg-info animated-counter-en" data-target="{{ $countMonth }}">0</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>هذه السنة</span>
                                    <span class="badge bg-warning text-dark animated-counter-en" data-target="{{ $countYear }}">0</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        {{-- أزرار التنقل للجوال --}}
        <div class="d-flex justify-content-center align-items-center gap-3 mt-3">
            <button id="carousel-prev" class="btn btn-sm btn-outline-secondary" style="border-radius:50%; width:36px; height:36px; padding:0;">
                <i class="fas fa-chevron-right"></i>
            </button>
            <span id="carousel-indicator" class="text-muted small"></span>
            <button id="carousel-next" class="btn btn-sm btn-outline-secondary" style="border-radius:50%; width:36px; height:36px; padding:0;">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
    </div>
</div>

<style>
.animated-counter-en {
    display: inline-block;
}
.animated-counter-en.shine {
    animation: shine-pulse 0.9s ease;
}
@keyframes shine-pulse {
    0%   { box-shadow: 0 0 0 0 rgba(255,224,0,0.6); }
    50%  { box-shadow: 0 0 8px 4px rgba(255,224,0,0.3); }
    100% { box-shadow: 0 0 0 0 rgba(255,224,0,0); }
}
.category-desktop-card .card {
    border-radius: 12px;
    transition: box-shadow 0.2s;
}
.category-desktop-card .card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.12) !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Animated counter ────────────────────────────────────────────────
    function animateCounterEn(el, target, duration) {
        duration = duration || 1200;
        let startTime = null;
        target = parseInt(el.getAttribute('data-target'));
        if (isNaN(target)) return;
        function step(ts) {
            if (!startTime) startTime = ts;
            let progress = Math.min((ts - startTime) / duration, 1);
            el.textContent = Math.floor(progress * target).toLocaleString('en-US');
            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = target.toLocaleString('en-US');
                el.classList.add('shine');
                setTimeout(function () { el.classList.remove('shine'); }, 900);
            }
        }
        requestAnimationFrame(step);
    }
    document.querySelectorAll('.animated-counter-en').forEach(function (el) {
        animateCounterEn(el);
    });

    // ── Mobile carousel ─────────────────────────────────────────────────
    var row       = document.getElementById('category-cards-row');
    var prevBtn   = document.getElementById('carousel-prev');
    var nextBtn   = document.getElementById('carousel-next');
    var indicator = document.getElementById('carousel-indicator');

    if (!row) return;

    var cards      = row.querySelectorAll('.category-carousel-card');
    var total      = cards.length;
    var currentIdx = 0;

    function goTo(idx) {
        currentIdx = Math.max(0, Math.min(idx, total - 1));
        row.style.transform = 'translateX(' + (currentIdx * 100) + '%)';
        if (indicator) indicator.textContent = (currentIdx + 1) + ' / ' + total;
        if (prevBtn)   prevBtn.disabled  = (currentIdx === 0);
        if (nextBtn)   nextBtn.disabled  = (currentIdx === total - 1);
    }

    if (prevBtn) prevBtn.addEventListener('click', function () { goTo(currentIdx - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { goTo(currentIdx + 1); });

    goTo(0);

    // Swipe support
    var touchStartX = 0;
    row.addEventListener('touchstart', function (e) { touchStartX = e.touches[0].clientX; }, { passive: true });
    row.addEventListener('touchend', function (e) {
        var diff = touchStartX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 40) { goTo(diff > 0 ? currentIdx + 1 : currentIdx - 1); }
    }, { passive: true });
});
</script>
