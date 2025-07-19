{{-- Custom Simple Pagination View --}}
@if ($paginator->hasPages())
    <nav class="simple-pagination" aria-label="Pagination Navigation">
        <div class="simple-pagination-container">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <span class="pagination-btn disabled" aria-disabled="true">
                    <i class="ki-duotone ki-left fs-5"></i>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pagination-btn" rel="prev">
                    <i class="ki-duotone ki-left fs-5"></i>
                </a>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <span class="pagination-dots">{{ $element }}</span>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination-btn active" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="pagination-btn">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pagination-btn" rel="next">
                    <i class="ki-duotone ki-right fs-5"></i>
                </a>
            @else
                <span class="pagination-btn disabled" aria-disabled="true">
                    <i class="ki-duotone ki-right fs-5"></i>
                </span>
            @endif
        </div>
    </nav>
@endif

<style>
/* Simple Pagination Styles */
.simple-pagination {
    margin: 2rem 0;
}

.simple-pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pagination-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0.5rem;
    background: white;
    border: 1px solid #e4e6ea;
    border-radius: 8px;
    color: #5e6278;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s ease;
    cursor: pointer;
}

.pagination-btn:hover:not(.disabled):not(.active) {
    background: #f8f9fa;
    border-color: #d1d3e0;
    color: #3f4254;
    transform: translateY(-1px);
}

.pagination-btn.active {
    background: #1b84ff;
    border-color: #1b84ff;
    color: white;
    font-weight: 600;
}

.pagination-btn.disabled {
    background: #f8f9fa;
    border-color: #e4e6ea;
    color: #b5b5c3;
    cursor: not-allowed;
}

.pagination-dots {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    color: #b5b5c3;
    font-weight: 500;
}

/* Responsive Design */
@media (max-width: 576px) {
    .simple-pagination-container {
        gap: 0.25rem;
    }

    .pagination-btn {
        min-width: 36px;
        height: 36px;
        font-size: 0.8rem;
    }

    .pagination-dots {
        min-width: 36px;
        height: 36px;
    }
}

/* تحسينات للوضع الداكن */
@media (prefers-color-scheme: dark) {
    .pagination-btn {
        background: #1e1e2d;
        border-color: #363649;
        color: #a1a5b7;
    }

    .pagination-btn:hover:not(.disabled):not(.active) {
        background: #2b2b40;
        border-color: #464660;
        color: #ffffff;
    }

    .pagination-btn.disabled {
        background: #1e1e2d;
        border-color: #363649;
        color: #5e6278;
    }
}
</style>
