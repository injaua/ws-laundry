// Purchase order lines: add / remove lines, line subtotals.
import { fmtMoney, roundMoney } from '../lib/money';

var poLineIndex = Number(document.getElementById('po-lines-body').dataset.nextIndex || 0);

function refreshPoLineNames() {
    document.querySelectorAll('#po-lines-body tr').forEach(function (row, idx) {
        row.querySelectorAll('[name]').forEach(function (input) {
            input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + idx + ']');
        });
        recalcPoSubtotal(row);
    });
}

function recalcPoSubtotal(row) {
    var qty = parseFloat(row.querySelector('.line-qty').value) || 0;
    var cost = parseFloat(row.querySelector('.line-cost').value) || 0;
    row.querySelector('.line-subtotal').textContent = qty * cost > 0 ? fmtMoney(roundMoney(qty * cost)) : '—';
}

function addPoLine() {
    var tpl = document.getElementById('po-line-template').content.cloneNode(true);
    tpl.querySelectorAll('[name]').forEach(function (input) {
        input.name = input.name.replace('__NAME__', 'lines[' + poLineIndex + ']');
    });
    poLineIndex++;
    document.getElementById('po-lines-body').appendChild(tpl);
    refreshPoLineNames();
}

function removePoLine(btn) {
    var body = document.getElementById('po-lines-body');
    if (body.querySelectorAll('tr').length <= 1) {
        return;
    }
    btn.closest('tr').remove();
    refreshPoLineNames();
}

document.getElementById('po-lines').addEventListener('input', function (e) {
    if (e.target.classList.contains('line-qty') || e.target.classList.contains('line-cost')) {
        recalcPoSubtotal(e.target.closest('tr'));
    }
});
document.querySelectorAll('#po-lines-body tr').forEach(recalcPoSubtotal);

document.addEventListener('click', function (e) {
    if (e.target.closest('[data-po-add]')) addPoLine();
    const remove = e.target.closest('[data-po-remove]');
    if (remove) removePoLine(remove);
});
