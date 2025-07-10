@php
    use App\Models\GeneralCategory;
    use App\Models\Data;
    $categories = GeneralCategory::all();
    $today = now()->format('Y-m-d');
    $month = now()->format('Y-m');
    $year = now()->format('Y');
@endphp

<div class="container my-4" style="max-width: 1200px;">
    <h4 class="mb-3">إحصائيات التصنيفات العامة</h4>
    <div id="category-cards-carousel-wrapper">
        <div class="row flex-nowrap" id="category-cards-row" style="min-width: 900px;">
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
                <div class="col-12 col-sm-10 col-md-6 col-lg-4 mb-4 category-carousel-card">
                    <div class="card shadow-sm h-100" style="min-width: 0;">
                        <div class="card-body d-flex flex-column justify-content-between" style="min-width:0;">
                            <div class="d-flex align-items-center mb-2 flex-wrap flex-md-nowrap text-center text-md-start">
                                <i class="fa fa-layer-group fa-2x text-info me-2 mb-2 mb-md-0"></i>
                                <div class="flex-fill" style="min-width:0;">
                                    <h5 class="card-title mb-0 text-truncate">{{ $category->description }}</h5>
                                </div>
                            </div>
                            <ul class="list-group list-group-flush mb-3">
                                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <span>إجمالي السجلات</span>
                                    <span class="badge bg-primary animated-counter-en" data-target="{{ $countAll }}">0</span>
                                </li>
                                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <span>اليوم</span>
                                    <span class="badge bg-success animated-counter-en" data-target="{{ $countToday }}">0</span>
                                </li>
                                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <span>هذا الشهر</span>
                                    <span class="badge bg-info animated-counter-en" data-target="{{ $countMonth }}">0</span>
                                </li>
                                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
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
    </div>
</div>


<style>
@media (max-width: 767.98px) {
  #category-cards-carousel-wrapper {
    position: relative;
    overflow: hidden;
    width: 100vw;
    margin-right: -2rem;
    margin-left: -2rem;
    padding: 0;
  }
  #category-cards-row {
    display: flex;
    flex-direction: row;
    transition: transform 0.4s cubic-bezier(.4,0,.2,1);
    will-change: transform;
    min-width: 100vw !important;
    width: 100vw;
    margin: 0;
  }
  .category-carousel-card {
    min-width: 95vw !important;
    max-width: 100vw;
    flex: 0 0 98vw;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
    display: none;
    justify-content: center;
    align-items: center;
  }
  .category-carousel-card.active {
    display: flex;
  }
  .card-body, .card {
    padding: 0.7rem !important;
  }
  .list-group-item {
    font-size: 0.98rem;
    padding: 0.5rem 0.7rem;
  }
  .card-title, .card .badge, .btn {
    font-size: 1rem !important;
  }
}
.animated-counter-en {
    position: relative;
    overflow: visible;
    display: inline-block;
    background: none;
    color: #222;
}
.animated-counter-en.shine {
    background: linear-gradient(90deg, #fffbe6 0%, #ffe066 40%, #fffbe6 100%);
    background-size: 200% 100%;
    background-repeat: no-repeat;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-fill-color: transparent;
    animation: shine-move 1.2s linear;
}
@keyframes shine-move {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Carousel logic للجوال فقط
    function isMobile() {
        return window.innerWidth <= 767.98;
    }
    const cards = document.querySelectorAll('.category-carousel-card');
    let currentIdx = 0;
    function updateCarousel() {
        if (!isMobile()) {
            cards.forEach(card => card.classList.add('active'));
            return;
        }
        cards.forEach((card, idx) => {
            card.classList.toggle('active', idx === currentIdx);
        });
    }
    if (cards.length > 0) {
        updateCarousel();
        window.addEventListener('resize', updateCarousel);
    }

    // Animated counter logic (English, with shine effect on the number itself)
    function animateCounterEn(el, target, duration = 1200) {
        let start = 0;
        let startTime = null;
        target = parseInt(target);
        if (isNaN(target)) return;
        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            let progress = Math.min((timestamp - startTime) / duration, 1);
            let value = Math.floor(progress * (target - start) + start);
            el.textContent = value.toLocaleString('en-US');
            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = target.toLocaleString('en-US');
                el.classList.add('shine');
                setTimeout(() => { el.classList.remove('shine'); }, 900);
            }
        }
        requestAnimationFrame(step);
    }
    document.querySelectorAll('.animated-counter-en').forEach(function(el) {
        let target = el.getAttribute('data-target');
        animateCounterEn(el, target);
    });
});
</script>
