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
