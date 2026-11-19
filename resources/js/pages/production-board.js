// Production board: remembered operator, reschedule / rework modals, auto refresh.
import { storage } from '../lib/dom';

const employee = document.getElementById('board-employee');
const saved = storage.get('prod-employee');
if (saved !== null && !employee.value) employee.value = saved;
employee.addEventListener('change', () => storage.set('prod-employee', employee.value));
document.querySelectorAll('.js-with-employee').forEach((form) =>
    form.addEventListener('submit', () => {
        form.querySelector('[name=employee_id]').value = employee.value;
    }),
);

document.querySelectorAll('[data-bs-target="#rescheduleModal"], [data-bs-target="#reworkModal"]').forEach((btn) =>
    btn.addEventListener('click', () => {
        const modal = document.querySelector(btn.dataset.bsTarget);
        const form = modal.querySelector('form');
        form.action = btn.dataset.action;
        modal.querySelector('.js-order').textContent = btn.dataset.order;
        const date = form.querySelector('[name=delivery_date]');
        if (date) date.value = '';
        const result = form.querySelector('[name=result]');
        if (result) result.disabled = !btn.dataset.qc;
    }),
);

// Keep the board current; skip while a modal or menu is open.
setInterval(() => {
    if (!document.querySelector('.modal.show, .dropdown-menu.show') && document.visibilityState === 'visible') location.reload();
}, 90000);
