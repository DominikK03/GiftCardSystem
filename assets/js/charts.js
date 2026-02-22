import ApexCharts from 'apexcharts';

// Theme colors matching admin
const colors = {
    ACTIVE: '#10b981',
    INACTIVE: '#94a3b8',
    EXPIRED: '#eab308',
    DEPLETED: '#ef4444',
    CANCELLED: '#dc2626',
    SUSPENDED: '#f97316',
    primary: '#23c1cd',
    secondary: '#3b82f6',
};

const fontFamily = '"Public Sans", "Segoe UI", system-ui, sans-serif';

// Common chart options
const baseOptions = {
    chart: { fontFamily, toolbar: { show: false } },
    dataLabels: { enabled: false },
    grid: { borderColor: '#f1f5f9' },
};

document.addEventListener('DOMContentLoaded', () => {
    initDonutChart();
    initAreaChart();
    initBarChart();
});

function initDonutChart() {
    const el = document.getElementById('chart-status-donut');
    if (!el) return;

    const raw = JSON.parse(el.dataset.chartValues || '{}');
    const colorMap = JSON.parse(el.dataset.chartColors || '{}');
    const labels = Object.keys(raw).filter(k => raw[k] > 0);
    const series = labels.map(k => raw[k]);

    if (series.length === 0) {
        el.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-pie-chart" style="font-size:2rem;"></i><p class="mt-2" style="font-size:.85rem;">No data</p></div>';
        return;
    }

    new ApexCharts(el, {
        ...baseOptions,
        chart: { ...baseOptions.chart, type: 'donut', height: 350 },
        series,
        labels,
        colors: labels.map(l => colorMap[l] || colors[l] || '#6b7280'),
        legend: { position: 'bottom', fontSize: '12px', fontFamily },
        plotOptions: {
            pie: {
                donut: {
                    size: '60%',
                    labels: {
                        show: true,
                        total: { show: true, label: 'Total', fontSize: '14px', fontWeight: 700, fontFamily },
                    },
                },
            },
        },
    }).render();
}

function initAreaChart() {
    const el = document.getElementById('chart-activity-area');
    if (!el) return;

    const raw = JSON.parse(el.dataset.chartValues || '[]');

    if (raw.length === 0) {
        el.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-graph-up" style="font-size:2rem;"></i><p class="mt-2" style="font-size:.85rem;">No activity data</p></div>';
        return;
    }

    // Group activity by date
    const grouped = {};
    raw.forEach(item => {
        const date = item.updatedAt.split(' ')[0]; // "dd.mm.YYYY" format
        grouped[date] = (grouped[date] || 0) + 1;
    });

    const categories = Object.keys(grouped);
    const series = Object.values(grouped);

    new ApexCharts(el, {
        ...baseOptions,
        chart: { ...baseOptions.chart, type: 'area', height: 280, sparkline: { enabled: false } },
        series: [{ name: 'Events', data: series }],
        xaxis: { categories, labels: { style: { fontSize: '11px', fontFamily } } },
        yaxis: { labels: { style: { fontSize: '11px', fontFamily } } },
        colors: [colors.primary],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: 2 },
        tooltip: { y: { formatter: (val) => val + ' events' } },
    }).render();
}

function initBarChart() {
    const el = document.getElementById('chart-tenants-bar');
    if (!el) return;

    const raw = JSON.parse(el.dataset.chartValues || '[]');

    if (raw.length === 0) {
        el.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-bar-chart" style="font-size:2rem;"></i><p class="mt-2" style="font-size:.85rem;">No tenant data</p></div>';
        return;
    }

    new ApexCharts(el, {
        ...baseOptions,
        chart: { ...baseOptions.chart, type: 'bar', height: 280 },
        series: [{ name: 'Active Cards', data: raw.map(t => t.cardCount) }],
        xaxis: {
            categories: raw.map(t => t.name.length > 12 ? t.name.substring(0, 12) + '...' : t.name),
            labels: { style: { fontSize: '11px', fontFamily } },
        },
        yaxis: { labels: { style: { fontSize: '11px', fontFamily } } },
        colors: [colors.secondary],
        plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
        tooltip: {
            custom: ({ series, seriesIndex, dataPointIndex }) => {
                const tenant = raw[dataPointIndex];
                return `<div style="padding:8px 12px;font-size:.85rem;">
                    <strong>${tenant.name}</strong><br>
                    Cards: ${tenant.cardCount}<br>
                    Balance: ${(tenant.totalBalance / 100).toFixed(2)} ${tenant.currency}
                </div>`;
            }
        },
    }).render();
}
