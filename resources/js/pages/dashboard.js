// Dashboard: revenue of the last 7 days.
import { chart, moneyTicks } from '../lib/charts';
import { pageData } from '../lib/dom';

const { labels, values, texts } = pageData('dashboard');

chart('weeklyChart', {
    type: 'bar',
    data: {
        labels,
        datasets: [
            {
                label: texts.revenue,
                data: values,
                backgroundColor: ['#c5cae9', '#9fa8da', '#7986cb', '#5c6bc0', '#3f51b5', '#3949ab', '#1a237e'],
                borderRadius: 4,
            },
        ],
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: moneyTicks } },
    },
});
