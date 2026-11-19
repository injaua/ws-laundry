// Expense form: fixed categories prefill the amount, the salary category imports the month's payroll.
import { pageData } from '../lib/dom';

var select = document.getElementById('expenseCategory');
var amount = document.getElementById('expenseAmount');
var title = document.getElementById('expenseTitle');
var fixedBox = document.getElementById('fixedPeriodBox');
var periodBox = document.getElementById('salaryPeriodBox');
var month = document.getElementById('salaryMonth');
var year = document.getElementById('salaryYear');
var importBtn = document.getElementById('salaryImportBtn');
var { totalUrl, isEdit } = pageData('expense-form');
var amountTouched = isEdit;
var autoTitle = '';

amount.addEventListener('input', function () {
    amountTouched = true;
});

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
            var generated = currentData().name + ' ' + pad(month.value) + '/' + year.value;
            if (!title.value || title.value === autoTitle) {
                title.value = generated;
                autoTitle = generated;
            }
        });
}

select.addEventListener('change', function () {
    var d = currentData();
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
});

importBtn.addEventListener('click', fetchTotal);

var initial = currentData();
if (initial.fixed === '1') {
    fixedBox.classList.remove('d-none');
}
if (initial.salary === '1') {
    periodBox.classList.remove('d-none');
}
