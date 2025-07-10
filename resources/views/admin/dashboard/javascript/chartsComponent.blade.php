    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // تكبير/تصغير الحاوية
            const chartContainer = document.getElementById('chartContainer');
            const expandBtn = document.getElementById('expandChartBtn');
            let expanded = false;
            expandBtn.addEventListener('click', function() {
                expanded = !expanded;
                const chartResponsiveWrapper = chartContainer.querySelector('.chart-responsive-wrapper');
                const monthlyChart = document.getElementById('monthlyChart');
                if (expanded) {
                    // طبقة تغطية سوداء للخلفية
                    let overlay = document.createElement('div');
                    overlay.id = 'chart-fullscreen-overlay';
                    overlay.style.position = 'fixed';
                    overlay.style.top = 0;
                    overlay.style.left = 0;
                    overlay.style.width = '100vw';
                    overlay.style.height = '100vh';
                    overlay.style.background = 'rgba(0,0,0,0.45)';
                    overlay.style.zIndex = '1049';
                    overlay.style.transition = 'opacity 0.3s';
                    document.body.appendChild(overlay);

                    chartContainer.style.position = 'fixed';
                    chartContainer.style.top = '50%';
                    chartContainer.style.left = '50%';
                    chartContainer.style.transform = 'translate(-50%, -50%)';
                    chartContainer.style.width = '95vw';
                    chartContainer.style.maxWidth = 'none';
                    chartContainer.style.height = '90vh';
                    chartContainer.style.zIndex = '1050';
                    chartContainer.style.background = '#fff';
                    chartContainer.classList.add('shadow-lg', 'border-primary');
                    chartContainer.style.borderRadius = '1rem';
                    chartContainer.style.boxShadow = '0 8px 32px 0 rgba(13,110,253,0.18), 0 1.5px 8px 0 rgba(0,0,0,0.08)';
                    chartContainer.style.overflow = 'auto';
                    document.body.style.overflow = 'hidden';
                    // تكبير الشارت نفسه مع رفع الجودة
                    if (chartResponsiveWrapper && monthlyChart) {
                        chartResponsiveWrapper.style.transition = 'transform 0.4s cubic-bezier(.77,0,.18,1.01)';
                        chartResponsiveWrapper.style.transform = 'scale(1)';
                        setTimeout(function() {
                            let width = Math.max(window.innerWidth * 0.85, 600);
                            let height = Math.max(window.innerHeight * 0.7, 400);
                            const dpr = window.devicePixelRatio || 1;
                            monthlyChart.width = width * dpr;
                            monthlyChart.height = height * dpr;
                            monthlyChart.style.width = width + 'px';
                            monthlyChart.style.height = height + 'px';
                            const ctx = monthlyChart.getContext('2d');
                            ctx.setTransform(1, 0, 0, 1, 0, 0);
                            ctx.scale(dpr, dpr);
                            if (window.chartInstance) {
                                window.chartInstance.resize();
                            }
                        }, 350);
                    }
                    expandBtn.innerHTML = '<i class="bi bi-fullscreen-exit"></i>';
                    expandBtn.style.pointerEvents = 'auto';
                } else {
                    // إزالة طبقة التغطية
                    let overlay = document.getElementById('chart-fullscreen-overlay');
                    if (overlay) overlay.remove();
                    chartContainer.removeAttribute('style');
                    chartContainer.classList.remove('shadow-lg', 'border-primary');
                    if (chartResponsiveWrapper && monthlyChart) {
                        chartResponsiveWrapper.style.transform = 'scale(1)';
                        setTimeout(function() {
                            let width = Math.min(chartResponsiveWrapper.offsetWidth, 900);
                            let height = window.innerWidth < 992 ? 200 : 260;
                            const dpr = window.devicePixelRatio || 1;
                            monthlyChart.width = width * dpr;
                            monthlyChart.height = height * dpr;
                            monthlyChart.style.width = width + 'px';
                            monthlyChart.style.height = height + 'px';
                            const ctx = monthlyChart.getContext('2d');
                            ctx.setTransform(1, 0, 0, 1, 0, 0);
                            ctx.scale(dpr, dpr);
                            if (window.chartInstance) {
                                window.chartInstance.resize();
                                // إصلاح اختفاء الشارت بعد التصغير
                                setTimeout(function() {
                                    window.chartInstance.update();
                                }, 100);
                            }
                        }, 350);
                    }
                    document.body.style.overflow = '';
                    expandBtn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
                    expandBtn.style.pointerEvents = 'auto';
                }
            });

            const months = @json($months);
            const rePeopleData = months.map(m => Number(@json($rePeopleCounts)[m] ?? 0));
            const dataData = months.map(m => Number(@json($dataCounts)[m] ?? 0));
            const deadPeopleData = months.map(m => Number(@json($deadPeopleCounts)[m] ?? 0));
            const attachmentsData = months.map(m => Number(@json($attachmentsCounts)[m] ?? 0));
            const canvas = document.getElementById('monthlyChart');
            function setHighDpiCanvas(canvas, width, height) {
                const dpr = window.devicePixelRatio || 1;
                canvas.width = width * dpr;
                canvas.height = height * dpr;
                canvas.style.width = width + 'px';
                canvas.style.height = height + 'px';
                const ctx = canvas.getContext('2d');
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.scale(dpr, dpr);
                return ctx;
            }
            function getCanvasSize() {
                const parent = canvas.parentElement;
                const width = Math.min(parent.offsetWidth, 900);
                let height = 260;
                if (window.innerWidth < 992) height = 200;
                return { width, height };
            }
            let { width, height } = getCanvasSize();
            let ctx = setHighDpiCanvas(canvas, width, height);
            let chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [
                        { label: 'الأفراد', data: rePeopleData, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)', fill: true },
                        { label: 'أولياء الأمور', data: dataData, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.1)', fill: true },
                        { label: 'المتوفين', data: deadPeopleData, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.1)', fill: true },
                        { label: 'المرفقات', data: attachmentsData, borderColor: '#ffc107', backgroundColor: 'rgba(255,193,7,0.1)', fill: true },
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top', labels: { font: { family: 'Cairo, Tahoma, Arial' } } },
                        title: { display: false }
                    },
                    scales: {
                        x: { title: { display: true, text: 'الشهر' } },
                        y: { title: { display: true, text: 'عدد السجلات' }, beginAtZero: true }
                    }
                }
            });
            window.chartInstance = chartInstance;
            window.addEventListener('resize', function() {
                ({ width, height } = getCanvasSize());
                ctx = setHighDpiCanvas(canvas, width, height);
                chartInstance.resize();
            });
        });
    </script>

    <script>
        // شارت اليوم الواحد (Pie)
        document.addEventListener('DOMContentLoaded', function() {
            const todayPie = document.getElementById('todayPieChart');
            if (todayPie) {
                const todayPieCtx = todayPie.getContext('2d');
                new Chart(todayPieCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['الأفراد', 'أولياء الأمور', 'المتوفين', 'المرفقات'],
                        datasets: [{
                            data: [
                                {{ DB::table('re_people')->whereDate('created_at', now()->format('Y-m-d'))->count() }},
                                {{ DB::table('data')->whereDate('created_at', now()->format('Y-m-d'))->count() }},
                                {{ DB::table('dead_people')->whereDate('created_at', now()->format('Y-m-d'))->count() }},
                                {{ DB::table('attachments')->whereDate('created_at', now()->format('Y-m-d'))->count() }}
                            ],
                            backgroundColor: [
                                '#0d6efd',
                                '#198754',
                                '#dc3545',
                                '#ffc107'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    font: {
                                        family: 'Cairo, Tahoma, Arial'
                                    }
                                }
                            },
                            title: {
                                display: false
                            }
                        }
                    }
                });
            }

        });
    </script>
