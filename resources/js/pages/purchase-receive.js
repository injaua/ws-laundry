// Purchase order (resources/purchases/show): receiving posts to the picked line, and
// picking a line fills its pending quantity, ordered unit cost and a suggested lot number.
// Equipment lines arrive as one unit with no lot or expiry.
const line = document.getElementById('receive-line');
const form = document.getElementById('receive-form');

const apply = (fill) => {
    const option = line.options[line.selectedIndex];
    if (!option) {
        return;
    }
    const d = option.dataset;
    const isEquipment = d.equipment === '1';
    const quantity = document.getElementById('receive-quantity');

    form.action = option.value;
    document.getElementById('receive-line-id').value = d.id;
    quantity.readOnly = isEquipment;
    form.querySelectorAll('.receive-stock-only').forEach((el) => {
        el.classList.toggle('d-none', isEquipment);
        el.querySelectorAll('input').forEach((input) => {
            input.disabled = isEquipment;
        });
    });

    if (fill) {
        quantity.value = d.qty;
        document.getElementById('receive-unit-cost').value = d.cost;
        document.getElementById('receive-lot').value = d.lot;
    }
};

line?.addEventListener('change', () => apply(true));
if (line) {
    apply(false);
}
