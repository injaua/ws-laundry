/*
 * Declarative behaviours shared by every layout, so views need no inline JavaScript:
 *
 *   <form data-confirm="Delete?">                ask before submitting (also on a <button> / <a>)
 *   <tr data-href="url">                         the whole row opens url
 *   <input data-uppercase>                       typed in capitals
 *   <select data-auto-submit>                    submit the form on change
 *   <button data-print>                          window.print()
 *   <body data-auto-print>                       print once the page has loaded
 *   <div data-open-on-load="url">                open url in a new tab once loaded
 *   <button data-toggle-password="inputId">      show / hide a password
 *   <a data-remember="key=value">                remember a choice in localStorage
 *   <div data-show-if="type=business">           visible only while the condition holds
 *   <input data-required-if="direction=in">      required only while the condition holds
 *   <input type=checkbox data-check-all=".row">  tick / untick every matching checkbox
 *   <input type=file data-preview="#img">        preview the chosen image
 *   <button data-bs-toggle="modal" data-modal-action="url" data-order="…">
 *                                                set the modal's form action and its [data-fill="order"] text
 *   <div data-poll-reload="url">                 reload when the status endpoint stops being "open"
 *
 * Conditions: "name" (checked / not empty), "name=a|b", "name!=a", joined with "&".
 */
import { reloadWhenClosed } from './lib/poll';
import { appConfig, ready, storage } from './lib/dom';

// ---- Confirmation: capture phase, so it runs before any other submit handler.
document.addEventListener(
    'submit',
    (e) => {
        const form = e.target.closest('form[data-confirm]');
        if (form && !window.confirm(form.dataset.confirm)) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    },
    true,
);
// …and on a single button or link of a form that has several.
document.addEventListener(
    'click',
    (e) => {
        const el = e.target.closest('button[data-confirm], a[data-confirm]');
        if (el && !window.confirm(el.dataset.confirm)) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    },
    true,
);

// ---- No double submission: the button is disabled once the form really leaves.
document.addEventListener('submit', (e) => {
    // Forms handled in JavaScript (e.g. the scan station) stay on the page.
    if (e.defaultPrevented || e.target.hasAttribute('data-no-lock')) return;
    const btn = e.target.querySelector('button[type=submit]');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span> ${appConfig().texts?.loading ?? '…'}`;
    }
});

document.addEventListener('change', (e) => {
    const el = e.target;
    if (el.matches('[data-auto-submit]') && el.form) el.form.submit();

    if (el.matches('[data-check-all]')) {
        document.querySelectorAll(el.dataset.checkAll).forEach((box) => {
            box.checked = el.checked;
        });
    }

    if (el.matches('input[type=file][data-preview]') && el.files?.[0]) {
        const img = document.querySelector(el.dataset.preview);
        if (img) {
            img.src = URL.createObjectURL(el.files[0]);
            img.closest('.d-none')?.classList.remove('d-none');
        }
        if (el.dataset.previewUncheck) {
            const box = document.querySelector(el.dataset.previewUncheck);
            if (box) box.checked = false;
        }
    }
});

document.addEventListener('input', (e) => {
    if (e.target.matches('input[data-uppercase]')) e.target.value = e.target.value.toUpperCase();
});

document.addEventListener('click', (e) => {
    // Clickable table rows; links and buttons inside the row keep their own action.
    const row = e.target.closest('tr[data-href]');
    if (row && !e.target.closest('a, button, input, select, label, form')) location.href = row.dataset.href;

    if (e.target.closest('[data-print]')) {
        e.preventDefault();
        window.print();
    }

    const remember = e.target.closest('[data-remember]');
    if (remember) {
        const [key, value] = remember.dataset.remember.split('=');
        storage.set(key, value);
    }

    const pw = e.target.closest('[data-toggle-password]');
    if (pw) {
        const input = document.getElementById(pw.dataset.togglePassword);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        const icon = pw.querySelector('i');
        if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        pw.setAttribute('aria-pressed', String(show));
    }
});

// ---- Bootstrap modals opened from a list: one modal, many rows.
document.addEventListener('show.bs.modal', (e) => {
    const trigger = e.relatedTarget;
    if (!trigger) return;
    const modal = e.target;
    const form = modal.querySelector('form');
    if (form && trigger.dataset.modalAction) form.action = trigger.dataset.modalAction;
    modal.querySelectorAll('[data-fill]').forEach((el) => {
        const value = trigger.dataset[el.dataset.fill];
        if (value !== undefined) el.textContent = value;
    });
});

// ---- Conditional fields
function fieldValue(scope, name) {
    const fields = [...scope.querySelectorAll(`[name="${name}"]`)].filter((f) => f.type !== 'hidden');
    if (!fields.length) return scope === document ? '' : fieldValue(document, name);
    const f = fields[0];
    if (f.type === 'radio') return fields.find((r) => r.checked)?.value ?? '';
    if (f.type === 'checkbox') return f.checked ? f.value || 'on' : '';

    return f.value;
}

function holds(el, expression) {
    const scope = el.closest('form') ?? document;

    return expression.split('&').every((part) => {
        const m = part.trim().match(/^([^!=]+)(!?=)?(.*)$/);
        if (!m) return true;
        const value = fieldValue(scope, m[1].trim());
        if (!m[2]) return value !== '';
        const match = m[3].split('|').includes(value);

        return m[2] === '=' ? match : !match;
    });
}

export function refreshConditions(root = document) {
    root.querySelectorAll('[data-show-if]').forEach((el) => el.classList.toggle('d-none', !holds(el, el.dataset.showIf)));
    root.querySelectorAll('[data-required-if]').forEach((el) => {
        el.required = holds(el, el.dataset.requiredIf);
    });
}

document.addEventListener('change', () => refreshConditions());
document.addEventListener('input', (e) => {
    if (e.target.matches('input[type=text], input[type=number], textarea')) refreshConditions();
});

// ---- Broken images (missing logo, deleted upload) disappear instead of showing an icon.
const hideBroken = (img) => img.classList.add('d-none');
document.addEventListener(
    'error',
    (e) => {
        if (e.target instanceof HTMLImageElement && e.target.hasAttribute('data-hide-broken')) hideBroken(e.target);
    },
    true,
);

ready(() => {
    // images that failed before this script ran
    document.querySelectorAll('img[data-hide-broken]').forEach((img) => {
        if (img.complete && img.naturalWidth === 0) hideBroken(img);
    });
    refreshConditions();
    document
        .querySelectorAll('[data-poll-reload]')
        .forEach((el) => reloadWhenClosed(el.dataset.pollReload, { max: Number(el.dataset.pollMax || 120) }));
});

window.addEventListener('load', () => {
    if (document.querySelector('[data-auto-print]')) setTimeout(() => window.print(), 250);
    document.querySelectorAll('[data-open-on-load]').forEach((el) => window.open(el.dataset.openOnLoad, '_blank'));
});
