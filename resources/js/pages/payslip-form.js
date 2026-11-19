// Payslip form: net salary, suggested absence deduction for the period.
import { pageData } from '../lib/dom';
import { fmtMoney } from '../lib/money';

var base = document.getElementById('baseSalary');
var bonus = document.getElementById('bonusField');
var deduction = document.getElementById('deductionField');
var absence = document.getElementById('absenceField');
var absenceHint = document.getElementById('absenceHint');
var net = document.getElementById('netSalary');
var employee = document.getElementById('payslipEmployee');
var month = document.getElementById('periodMonth');
var year = document.getElementById('periodYear');
var { suggestionUrl, hintText } = pageData('payslip-form');

function refreshNet() {
    var value = (Number(base.value) || 0) + (Number(bonus.value) || 0) - (Number(deduction.value) || 0) - (Number(absence.value) || 0);
    net.value = value.toLocaleString('pt', { maximumFractionDigits: 0 });
}

function refreshAbsence() {
    if (!employee.value || !year.value || !month.value) {
        return;
    }
    var url =
        suggestionUrl +
        '?employee_id=' +
        encodeURIComponent(employee.value) +
        '&period_year=' +
        encodeURIComponent(year.value) +
        '&period_month=' +
        encodeURIComponent(month.value);
    fetch(url, { headers: { Accept: 'application/json' } })
        .then(function (r) {
            return r.json();
        })
        .then(function (data) {
            absence.value = data.amount;
            absenceHint.textContent = hintText.replace(':days', data.days).replace(':rate', fmtMoney(data.daily_rate));
            refreshNet();
        });
}

[base, bonus, deduction, absence].forEach(function (el) {
    el.addEventListener('input', refreshNet);
});

employee.addEventListener('change', function () {
    var selected = employee.options[employee.selectedIndex];
    if (selected && selected.dataset.base !== undefined) {
        base.value = selected.dataset.base;
        refreshNet();
    }
    refreshAbsence();
});
month.addEventListener('change', refreshAbsence);
year.addEventListener('change', refreshAbsence);

refreshNet();
refreshAbsence();
