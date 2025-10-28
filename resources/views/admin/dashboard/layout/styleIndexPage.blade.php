<style>
    /* ========== CLEAN RESPONSIVE STYLES FOR CARDS ========== */

    /* Mobile screens: Single card carousel */
    @media (max-width: 767.98px) {
        .category-stats-responsive {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }

        .category-cards-row-responsive,
        .admin-cards-row-responsive {
            flex-wrap: nowrap;
        }

        .category-carousel-card-responsive,
        .admin-carousel-card-responsive {
            flex: 0 0 100%;
            max-width: 100%;
        }

        .admin-cards-carousel-wrapper-responsive {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
    }

    /* Tablet screens: 2 cards per row */
    @media (min-width: 768px) and (max-width: 1199.98px) {
        .category-cards-row-responsive,
        .admin-cards-row-responsive {
            flex-wrap: nowrap;
        }

        .category-carousel-card-responsive,
        .admin-carousel-card-responsive {
            flex: 0 0 50%;
            max-width: 50%;
        }
    }

    /* Desktop screens: 4 cards per row with proper centering */
    @media (min-width: 1200px) {
        .category-cards-row-responsive,
        .admin-cards-row-responsive {
            flex-wrap: nowrap;
            justify-content: center;
            align-items: stretch;
        }

        .category-carousel-card-responsive,
        .admin-carousel-card-responsive {
            flex: 0 0 25%;
            max-width: 25%;
        }
    }

    /* Chart responsive wrapper for better visualization */
    .chart-responsive-wrapper {
        width: 100%;
        min-height: 280px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem 0;
    }

    .chart-container-enhanced {
        min-height: 380px;
    }

    @media (max-width: 991.98px) {
        .chart-container-enhanced {
            min-height: 300px;
        }
        .chart-responsive-wrapper {
            min-height: 220px;
        }
    }

    /* Shine effect for animated counters */
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
        background: linear-gradient(120deg,
            rgba(255,255,255,0.0) 0%,
            rgba(255,255,255,0.8) 50%,
            rgba(255,255,255,0.0) 100%);
        animation: shine-move 0.8s cubic-bezier(0.4, 0.0, 0.2, 1);
        pointer-events: none;
    }
    @keyframes shine-move {
        0% { right: -60%; }
        100% { right: 110%; }
    }
</style>
