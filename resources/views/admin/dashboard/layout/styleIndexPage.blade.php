<style>
    /* Responsive styles for category and admin statistics cards */
    @media (max-width: 767.98px) {
        .category-stats-responsive {
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }
        .category-cards-row-responsive {
            min-width: 0 !important;
            flex-wrap: nowrap !important;
        }
        .category-carousel-card-responsive {
            min-width: 90vw !important;
            max-width: 95vw !important;
            flex: 0 0 90vw !important;
        }
        .admin-cards-carousel-wrapper-responsive {
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }
        .admin-cards-row-responsive {
            min-width: 0 !important;
            flex-wrap: nowrap !important;
        }
        .admin-carousel-card-responsive {
            min-width: 90vw !important;
            max-width: 95vw !important;
            flex: 0 0 90vw !important;
        }
    }
    @media (min-width: 1200px) {
        /* Show 4 cards per row on large screens */
        .category-cards-row-responsive, .admin-cards-row-responsive {
            min-width: 0 !important;
            flex-wrap: wrap !important;
        }
        .category-carousel-card-responsive, .admin-carousel-card-responsive {
            min-width: 0 !important;
            max-width: 25% !important;
            flex: 0 0 25% !important;
        }
    }
    @media (min-width: 768px) and (max-width: 1199.98px) {
        /* 2 cards per row on medium screens */
        .category-cards-row-responsive, .admin-cards-row-responsive {
            min-width: 0 !important;
            flex-wrap: wrap !important;
        }
        .category-carousel-card-responsive, .admin-carousel-card-responsive {
            min-width: 0 !important;
            max-width: 50% !important;
            flex: 0 0 50% !important;
        }
    }

    /* تحسين حجم وعرض رسم معدل التسجيل الشهري */
    .chart-responsive-wrapper {
        width: 100%;
        min-height: 260px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .chart-container-enhanced {
        min-height: 350px;
    }
    @media (max-width: 991.98px) {
        .chart-container-enhanced {
            min-height: 280px;
        }
        .chart-responsive-wrapper {
            min-height: 200px;
        }
    }

    /* لمعان متحرك للأرقام عند انتهاء العد */
    .shine {
        position: relative;
        overflow: hidden;
    }
    .shine::after {
        content: '';
        position: absolute;
        top: 0;
        right: -60%;
        width: 60%;
        height: 100%;
        background: linear-gradient(120deg, rgba(255,255,255,0.0) 0%, rgba(255,255,255,0.7) 50%, rgba(255,255,255,0.0) 100%);
        animation: shine-move 0.9s cubic-bezier(0.4,0.0,0.2,1);
        pointer-events: none;
    }
    @keyframes shine-move {
        0% {
            right: -60%;
        }
        100% {
            right: 110%;
        }
    }
</style>
