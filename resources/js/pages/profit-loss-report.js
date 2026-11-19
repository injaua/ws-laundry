// Reports: revenue vs. expenses, and expenses per category.
import { chart } from '../lib/charts';
import { pageData } from '../lib/dom';

const { labels, revenue, expenses, categories, texts } = pageData('profit-loss-report');

chart('profitChart', {
    type: 'bar',
    data: {
        labels,
        datasets: [
            { label: texts.revenue, data: revenue, backgroundColor: 'rgba(40,167,69,0.7)', borderColor: '#28a745', borderWidth: 1 },
            { label: texts.expenses, data: expenses, backgroundColor: 'rgba(220,53,69,0.7)', borderColor: '#dc3545', borderWidth: 1 },
        ],
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } },
});

chart('expensePieChart', {
    type: 'pie',
    data: {
        labels: categories.labels,
        datasets: [{ data: categories.values, backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#6f42c1', '#0dcaf0'] }],
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } },
});
