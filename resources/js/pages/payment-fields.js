// Payment forms (payments/_mobile-ref): reference / bank fields per method, change to give back.
import { pageData } from '../lib/dom';
import { fmtMoney } from '../lib/money';
import { on } from '../lib/page';

const { mobile: MOBILE, texts: T } = pageData('payment-fields');
const fmt = (n) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(n);
const sync = (form) => {
    const select = form.querySelector('select[name="method"]');
    if (!select) return;
    const method = select.value;
    const amountInput = form.querySelector('[name="amount"]');
    const amount = amountInput ? parseFloat(amountInput.value || 0) : 0;
    const nothingCollected = amountInput && amount <= 0;
    form.querySelectorAll('.js-pay-extra .js-for').forEach((el) => {
        const on = el.dataset.methods.split(',').includes(method) && !nothingCollected;
        // Bootstrap's d-none is !important: an inline style cannot override it, toggle the class.
        el.classList.toggle('d-none', !on);
        el.querySelectorAll('input').forEach((i) => {
            i.disabled = !on;
        });
    });
    const ref = form.querySelector('.js-pay-extra [name="transaction_ref"]');
    if (ref) {
        const labels = JSON.parse(ref.dataset.labels);
        const label = labels[MOBILE.includes(method) ? 'mobile' : method] || labels.mobile;
        ref.required = ['pos', 'transfer', 'cheque', ...MOBILE].includes(method) && !nothingCollected;
        const holder = form.querySelector('.js-ref-label');
        if (holder) holder.textContent = label;
        ref.placeholder = label;
    }
    const bank = form.querySelector('.js-pay-extra [name="cheque_bank"]');
    if (bank) bank.required = method === 'cheque' && !nothingCollected;
    const tendered = form.querySelector('.js-tendered');
    const change = form.querySelector('.js-change');
    if (tendered && change) {
        const given = parseFloat(tendered.value || 0);
        change.textContent = given > 0 ? (given >= amount ? fmtMoney(given - amount) : T.tenderedShort) : '—';
        change.classList.toggle('text-danger', given > 0 && given < amount);
        change.classList.toggle('text-success', !(given > 0 && given < amount));
    }
};
const handler = (e) => {
    if (e.target.matches('select[name="method"], [name="amount"], .js-tendered') && e.target.form) sync(e.target.form);
};
on(document, 'change', handler);
on(document, 'input', handler);
const init = () => document.querySelectorAll('.js-pay-extra').forEach((box) => box.closest('form') && sync(box.closest('form')));
document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
