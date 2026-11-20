// Bank deposit: total of the cash and the cheques ticked.
import { fmtMoney } from '../lib/money';
import { on } from '../lib/page';

const fmt = (n) => fmtMoney(n);
const total = () => {
    let sum = parseFloat(document.querySelector('.js-dep-cash').value || 0);
    document.querySelectorAll('.js-dep-cheque:checked').forEach((c) => (sum += parseFloat(c.dataset.amount)));
    document.querySelector('.js-dep-total').textContent = fmt(sum);
};
on(document, 'input', (e) => {
    if (e.target.matches('.js-dep-cash, .js-dep-cheque')) total();
});
on(document, 'change', (e) => {
    if (e.target.matches('.js-dep-cheque')) total();
});
total();
