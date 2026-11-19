// Reports: share of each payment method.
import { chart } from '../lib/charts';
import { pageData } from '../lib/dom';

const { labels, values } = pageData('payment-methods-report');

chart('methodPieChart', {
    type: 'doughnut',
    data: { labels, datasets: [{ data: values, backgroundColor: ['#20c997', '#6f42c1', '#0d6efd', '#fd7e14', '#198754'] }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } },
});
