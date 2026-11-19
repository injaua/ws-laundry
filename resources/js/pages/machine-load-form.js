// New machine load: kilos picked vs. machine capacity.

const machine = document.getElementById('machine');
const update = () => {
    let total = 0;
    document.querySelectorAll('.js-kg').forEach((i) => (total += parseFloat(i.value) || 0));
    total = Math.round(total * 100) / 100;
    document.getElementById('total-kg').textContent = total;
    const cap = parseFloat(machine.selectedOptions[0]?.dataset.capacity || 0);
    const bar = document.getElementById('cap-bar');
    const text = document.getElementById('cap-text');
    if (cap > 0) {
        const pct = Math.round((total / cap) * 100);
        bar.style.width = Math.min(pct, 100) + '%';
        bar.className = 'progress-bar ' + (pct > 100 ? 'bg-danger' : pct < 60 ? 'bg-warning' : 'bg-success');
        text.textContent = pct + '% · ' + cap + ' kg';
        document.getElementById('overload').classList.toggle('d-none', pct <= 100);
    } else {
        bar.style.width = '0%';
        text.textContent = '';
    }
};
document.querySelectorAll('.js-pick').forEach((cb) =>
    cb.addEventListener('change', () => {
        const input = document.querySelector('.js-kg[data-id="' + cb.dataset.id + '"]');
        input.value = cb.checked ? input.dataset.default : '';
        update();
    }),
);
document.querySelectorAll('.js-kg').forEach((i) =>
    i.addEventListener('input', () => {
        document.querySelector('.js-pick[data-id="' + i.dataset.id + '"]').checked = (parseFloat(i.value) || 0) > 0;
        update();
    }),
);
machine.addEventListener('change', update);
update();
