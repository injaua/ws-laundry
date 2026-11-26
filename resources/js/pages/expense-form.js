// Expense form: fixed categories prefill the amount, the salary category imports the month's payroll,
// the category suggests the VAT rate and the VAT/net split of the gross amount is previewed.
import { pageData } from '../lib/dom';

var select = document.getElementById('expenseCategory');
var amount = document.getElementById('expenseAmount');
var title = document.getElementById('expenseTitle');
var fixedBox = document.getElementById('fixedPeriodBox');
var periodBox = document.getElementById('salaryPeriodBox');
var month = document.getElementById('salaryMonth');
var year = document.getElementById('salaryYear');
var importBtn = document.getElementById('salaryImportBtn');
var vatRate = document.getElementById('expenseVatRate');
var vatPreview = document.getElementById('expenseVatPreview');
var { totalUrl, isEdit, vatLabel, currency } = pageData('expense-form');
var vatTouched = isEdit;
var amountTouched = isEdit;
var autoTitle = '';

amount.addEventListener('input', function () {
    amountTouched = true;
    updateVatPreview();
});

vatRate.addEventListener('input', function () {
    vatTouched = true;
    updateVatPreview();
});

// The amount is gross: VAT = gross x rate / (100 + rate).
function updateVatPreview() {
    var gross = parseFloat(amount.value) || 0;
    var rate = parseFloat(vatRate.value) || 0;
    var vat = rate > 0 ? Math.round((gross * rate) / (100 + rate) * 100) / 100 : 0;
    var net = Math.round((gross - vat) * 100) / 100;
    vatPreview.textContent = vatLabel.replace(':vat', vat.toFixed(2) + ' ' + currency).replace(':net', net.toFixed(2) + ' ' + currency);
}

function currentData() {
    return select.options[select.selectedIndex] ? select.options[select.selectedIndex].dataset : {};
}

function pad(n) {
    return (n < 10 ? '0' : '') + n;
}

function fetchTotal() {
    var url = totalUrl + '?month=' + encodeURIComponent(month.value) + '&year=' + encodeURIComponent(year.value);
    fetch(url, { headers: { Accept: 'application/json' } })
        .then(function (r) {
            return r.json();
        })
        .then(function (data) {
            amount.value = data.amount;
            amountTouched = true;
            updateVatPreview();
            var generated = currentData().name + ' ' + pad(month.value) + '/' + year.value;
            if (!title.value || title.value === autoTitle) {
                title.value = generated;
                autoTitle = generated;
            }
        });
}

select.addEventListener('change', function () {
    var d = currentData();
    if (!vatTouched && d.vat !== undefined) {
        vatRate.value = d.vat;
    }
    fixedBox.classList.toggle('d-none', d.fixed !== '1');
    if (d.salary === '1') {
        periodBox.classList.remove('d-none');
        fetchTotal();
    } else {
        periodBox.classList.add('d-none');
        if (d.fixed === '1' && !amountTouched) {
            amount.value = d.default;
        }
    }
    updateVatPreview();
});

importBtn.addEventListener('click', fetchTotal);

var initial = currentData();
if (initial.fixed === '1') {
    fixedBox.classList.remove('d-none');
}
if (initial.salary === '1') {
    periodBox.classList.remove('d-none');
}
// A failed validation re-renders with old() values: keep them, otherwise suggest the category's rate.
if (vatRate.value !== '') {
    vatTouched = true;
} else if (initial.vat !== undefined) {
    vatRate.value = initial.vat;
}
updateVatPreview();

// Purchase order raised from the expense (create only): lines name a resource by kind;
// an existing name reuses the stock item, any other name creates it (equipment is always new).
var poSwitch = document.getElementById('createPurchaseOrder');
if (poSwitch) {
    var data = pageData('expense-form');
    var resources = data.resources || {};
    var poBox = document.getElementById('purchaseOrderBox');
    var poLines = document.getElementById('poLines');
    var poTotal = document.getElementById('poTotal');

    var findResource = function (kind, name) {
        var wanted = name.trim().toLowerCase();
        return (resources[kind] || []).find(function (r) {
            return r.name.toLowerCase() === wanted;
        });
    };

    var togglePo = function () {
        poBox.classList.toggle('d-none', !poSwitch.checked);
        poBox.querySelectorAll('[data-po-required]').forEach(function (el) {
            el.required = poSwitch.checked;
        });
        refreshTotal();
    };

    var refreshLine = function (row) {
        var kind = row.querySelector('.po-kind').value;
        var name = row.querySelector('.po-name');
        var unit = row.querySelector('.po-unit');
        var qty = row.querySelector('.po-qty');
        var status = row.querySelector('.po-status');
        var isEquipment = kind === 'equipment';

        name.setAttribute('list', 'poNames-' + kind);
        unit.disabled = isEquipment;
        qty.step = isEquipment ? '1' : '0.01';
        qty.min = isEquipment ? '1' : '0.01';

        var found = !isEquipment && name.value.trim() !== '' ? findResource(kind, name.value) : null;
        if (found) {
            unit.value = found.unit;
            unit.disabled = true;
        }
        if (name.value.trim() === '') {
            status.textContent = '';
        } else if (isEquipment) {
            status.textContent = data.newEquipmentLabel;
            status.className = 'small mt-1 po-status text-info';
        } else {
            status.textContent = found ? data.existingLabel : data.newLabel;
            status.className = 'small mt-1 po-status ' + (found ? 'text-success' : 'text-primary');
        }
    };

    var refreshTotal = function () {
        var total = 0;
        poLines.querySelectorAll('.po-line').forEach(function (row) {
            total += (parseFloat(row.querySelector('.po-qty').value) || 0) * (parseFloat(row.querySelector('.po-cost').value) || 0);
        });
        total = Math.round(total * 100) / 100;
        poTotal.textContent = total.toFixed(2);
        // The lines suggest the expense amount until it is typed by hand.
        if (poSwitch.checked && !amountTouched && total > 0) {
            amount.value = total.toFixed(2);
            updateVatPreview();
        }
    };

    var renumber = function () {
        poLines.querySelectorAll('.po-line').forEach(function (row, i) {
            row.querySelectorAll('[name^="po_lines["]').forEach(function (el) {
                el.name = el.name.replace(/^po_lines\[\d+\]/, 'po_lines[' + i + ']');
            });
        });
    };

    poLines.addEventListener('input', function (e) {
        var row = e.target.closest('.po-line');
        if (row) {
            refreshLine(row);
        }
        refreshTotal();
    });
    poLines.addEventListener('change', function (e) {
        var row = e.target.closest('.po-line');
        if (row) {
            refreshLine(row);
        }
    });
    poLines.addEventListener('click', function (e) {
        var btn = e.target.closest('.po-remove');
        if (!btn || poLines.querySelectorAll('.po-line').length <= 1) {
            return;
        }
        btn.closest('.po-line').remove();
        renumber();
        refreshTotal();
    });

    document.getElementById('poAddLine').addEventListener('click', function () {
        var rows = poLines.querySelectorAll('.po-line');
        var clone = rows[rows.length - 1].cloneNode(true);
        clone.querySelectorAll('.text-danger').forEach(function (el) {
            el.remove();
        });
        clone.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        clone.querySelector('.po-name').value = '';
        clone.querySelector('.po-qty').value = 1;
        clone.querySelector('.po-cost').value = '';
        poLines.appendChild(clone);
        renumber();
        refreshLine(clone);
        refreshTotal();
        clone.querySelector('.po-name').focus();
    });

    // A disabled unit is not posted: re-enable before submitting (the server ignores it
    // for existing items and equipment).
    poSwitch.form.addEventListener('submit', function () {
        poLines.querySelectorAll('.po-unit').forEach(function (el) {
            el.disabled = false;
        });
    });

    poSwitch.addEventListener('change', togglePo);
    poLines.querySelectorAll('.po-line').forEach(refreshLine);
    togglePo();
}
