// Reports: revenue over the selected period.
import { chart, isDarkTheme, moneyTicks } from '../lib/charts';
import { pageData } from '../lib/dom';

const { labels, values, texts } = pageData('revenue-report');
const dark = isDarkTheme();

chart('revenueChart', {
    type: 'line',
    data: {
        labels,
        datasets: [
            {
                label: texts.revenue,
                data: values,
                borderColor: dark ? '#7986cb' : '#1a237e',
                backgroundColor: dark ? 'rgba(121, 134, 203, 0.15)' : 'rgba(26, 35, 126, 0.1)',
                fill: true,
                tension: 0.4,
            },
        ],
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: moneyTicks } },
    },
});
