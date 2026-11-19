// Management indicators: monthly revenue / expenses / result and operations trend.
import { chart } from '../lib/charts';
import { pageData } from '../lib/dom';
import { fmtMoney } from '../lib/money';

const { trend, texts } = pageData('management');

// A preset period applies at once; "custom" shows the date range first.
const period = document.getElementById('period');
period?.addEventListener('change', () => {
    document.getElementById('custom-range')?.classList.toggle('d-none', period.value !== 'custom');
    if (period.value !== 'custom') period.form.submit();
});
const labels = trend.map((t) => t.label);

chart('trendChart', {
    data: {
        labels,
        datasets: [
            { type: 'bar', label: texts.revenue, data: trend.map((t) => t.revenue), backgroundColor: 'rgba(13,110,253,0.6)' },
            { type: 'bar', label: texts.expenses, data: trend.map((t) => t.expenses), backgroundColor: 'rgba(220,53,69,0.5)' },
            {
                type: 'line',
                label: texts.result,
                data: trend.map((t) => t.result),
                borderColor: '#198754',
                backgroundColor: '#198754',
                tension: 0.3,
            },
        ],
    },
    options: {
        plugins: { tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${fmtMoney(c.parsed.y)}` } } },
        scales: { y: { ticks: { callback: (v) => `${Math.round(v / 1000)}k` } } },
    },
});

chart('opsChart', {
    type: 'line',
    data: {
        labels,
        datasets: [
            { label: texts.customers, data: trend.map((t) => t.customers), borderColor: '#0d6efd', yAxisID: 'y', tension: 0.3 },
            {
                label: `${texts.onTime} (%)`,
                data: trend.map((t) => t.on_time),
                borderColor: '#fd7e14',
                yAxisID: 'y1',
                tension: 0.3,
                spanGaps: true,
            },
        ],
    },
    options: { scales: { y: { beginAtZero: true }, y1: { position: 'right', min: 0, max: 100, grid: { drawOnChartArea: false } } } },
});
