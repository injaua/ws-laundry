// User form (create / edit): checks the fields before sending, shows the password rules as
// they are met, and tells the user what is wrong instead of failing silently.
// The server validates again (UserRequest); its messages are rendered in the same places.
import { pageData } from '../lib/dom';
import { on } from '../lib/page';

const form = document.getElementById('userForm');

if (form) {
    const { isEdit, messages } = pageData('user-form');
    const field = (name) => form.elements.namedItem(name);
    const name = field('name');
    const email = field('email');
    const role = field('role');
    const password = field('password');
    const confirm = field('password_confirmation');
    const rulesList = document.getElementById('passwordRules');
    const summary = document.getElementById('userFormClientErrors');
    const serverSummary = document.getElementById('userFormErrors');
    const submit = form.querySelector('[data-submit]');
    const submitHtml = submit.innerHTML;

    const RULES = {
        length: (v) => v.length >= 10,
        case: (v) => /[a-z]/.test(v) && /[A-Z]/.test(v),
        number: (v) => /\d/.test(v),
    };

    const feedbackFor = (input) => form.querySelector(`[data-feedback="${input.name}"]`);

    function setError(input, message) {
        input.classList.toggle('is-invalid', Boolean(message));
        const box = feedbackFor(input);
        if (box) box.textContent = message || '';
    }

    function passwordRulesMet(value) {
        return Object.values(RULES).every((test) => test(value));
    }

    // Password checklist: grey circle → green tick while typing.
    function refreshRules() {
        const value = password.value;
        rulesList.classList.toggle('d-none', isEdit && value === '');
        rulesList.querySelectorAll('[data-rule]').forEach((item) => {
            const ok = RULES[item.dataset.rule](value);
            item.classList.toggle('text-success', ok);
            item.classList.toggle('text-muted', !ok);
            item.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
        });
    }

    // Green when both passwords match; red only once the confirmation is as long as the password.
    function refreshConfirm() {
        const filled = confirm.value !== '';
        const matches = filled && confirm.value === password.value;
        confirm.classList.toggle('is-valid', matches);
        const finished = confirm.value.length >= password.value.length;
        setError(confirm, filled && !matches && finished ? messages.mismatch : '');
    }

    // Returns the list of problems (and marks each field).
    function check() {
        const problems = [];
        const flag = (input, message) => {
            setError(input, message);
            if (message) problems.push({ input, message });
        };

        flag(name, name.value.trim() === '' ? messages.required : name.value.trim().length < 2 ? messages.name : '');

        if (document.activeElement !== email) email.value = email.value.trim().toLowerCase();
        flag(email, email.value === '' ? messages.required : email.validity.valid ? '' : messages.email);

        if (role && !role.disabled) flag(role, role.value === '' ? messages.role : '');

        const pw = password.value;
        if (!isEdit || pw !== '') {
            flag(password, pw === '' ? messages.required : passwordRulesMet(pw) ? '' : messages.password);
            flag(confirm, confirm.value === '' ? messages.required : confirm.value !== pw ? messages.mismatch : '');
        } else {
            flag(password, '');
            flag(confirm, confirm.value !== '' ? messages.mismatch : '');
        }

        return problems;
    }

    function showSummary(problems) {
        const list = summary.querySelector('ul');
        list.replaceChildren(
            ...problems.map(({ input, message }) => {
                const li = document.createElement('li');
                const label = form.querySelector(`label[for="${input.id}"]`);
                li.textContent = `${label ? label.textContent.replace('*', '').trim() + ': ' : ''}${message}`;
                return li;
            }),
        );
        summary.classList.toggle('d-none', problems.length === 0);
        if (serverSummary) serverSummary.classList.add('d-none');
    }

    form.addEventListener('submit', (e) => {
        const problems = check();
        if (problems.length) {
            e.preventDefault();
            showSummary(problems);
            problems[0].input.focus();
            summary.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        // Valid: clear the old list and lock the button so a slow request is not sent twice.
        showSummary([]);
        submit.disabled = true;
        submit.innerHTML = `<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> ${messages.saving}`;
    });

    // Fix-as-you-type: once a field was flagged, re-check it on every change.
    form.addEventListener('input', (e) => {
        if (e.target === password) {
            refreshRules();
            if (confirm.value) refreshConfirm();
        }
        if (e.target === confirm) refreshConfirm();
        if (!summary.classList.contains('d-none')) {
            showSummary(check()); // the list shrinks as problems are fixed
        } else if (e.target.classList.contains('is-invalid') && e.target !== confirm && e.target !== password) {
            setError(e.target, ''); // server error on this field: hide it once the user edits
        }
    });

    email.addEventListener('blur', () => {
        email.value = email.value.trim().toLowerCase();
    });

    // Back from the server with errors: start at the first problem.
    on(window, 'pageshow', () => {
        // The browser "back" button may restore the locked button.
        submit.disabled = false;
        submit.innerHTML = submitHtml;
    });
    refreshRules();
    const firstInvalid = form.querySelector('.is-invalid');
    if (firstInvalid) {
        firstInvalid.focus();
        serverSummary?.scrollIntoView({ block: 'center' });
    }
}
