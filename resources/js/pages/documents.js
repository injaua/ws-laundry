// Document archive: upload drop zones and file lists, folder / category modals (new or rename),
// favourite toggle without reload, tag suggestions, sending to colleagues (new files, a document
// of the archive, reply to the sender), reopening a modal after a validation error.
import { Modal } from 'bootstrap';
import { appConfig, csrfToken, esc } from '../lib/dom';
import { on } from '../lib/page';

const ACCEPTED = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
const ICONS = {
    pdf: 'bi-file-earmark-pdf text-danger',
    doc: 'bi-file-earmark-word text-primary',
    docx: 'bi-file-earmark-word text-primary',
    xls: 'bi-file-earmark-excel text-success',
    xlsx: 'bi-file-earmark-excel text-success',
    ppt: 'bi-file-earmark-ppt text-warning',
    pptx: 'bi-file-earmark-ppt text-warning',
};
const formatSize = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.ceil(bytes / 1024))} KB`);
const extOf = (name) => (name.includes('.') ? name.split('.').pop().toLowerCase() : '');

// ---- Upload / send: drop zone + list of the chosen files
function dropZone(input, drop, list) {
    if (!input || !drop || !list) return;
    const renderFiles = () => {
        const files = [...(input.files ?? [])];
        list.hidden = files.length === 0;
        const max = appConfig().maxUploadBytes;
        list.innerHTML = files
            .map((file) => {
                const ext = extOf(file.name);
                const bad = !ACCEPTED.includes(ext) || (max && file.size > max);
                return `<li class="${bad ? 'invalid' : ''}">
                <i class="bi ${ICONS[ext] ?? 'bi-file-earmark-x'}"></i>
                <span class="flex-grow-1 text-truncate">${esc(file.name)}</span>
                <span class="text-muted small">${formatSize(file.size)}</span>
            </li>`;
            })
            .join('');
    };
    input.addEventListener('change', renderFiles);
    ['dragenter', 'dragover'].forEach((type) =>
        drop.addEventListener(type, (e) => {
            e.preventDefault();
            drop.classList.add('dragover');
        }),
    );
    ['dragleave', 'drop'].forEach((type) => drop.addEventListener(type, () => drop.classList.remove('dragover')));
    drop.addEventListener('drop', (e) => {
        e.preventDefault();
        if (!e.dataTransfer?.files?.length) return;
        input.files = e.dataTransfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
}
const byId = (id) => document.getElementById(id);
dropZone(byId('docFiles'), byId('docDrop'), byId('docFileList'));
dropZone(byId('exFiles'), byId('exDrop'), byId('exFileList'));

// ---- Tag suggestions: add the tag to the field
on(document, 'click', (e) => {
    const chip = e.target.closest('[data-doc-add-tag]');
    if (!chip) return;
    const field = document.getElementById('upTags');
    if (!field) return;
    const tags = field.value
        .split(',')
        .map((t) => t.trim())
        .filter(Boolean);
    if (!tags.some((t) => t.toLowerCase() === chip.dataset.docAddTag.toLowerCase())) tags.push(chip.dataset.docAddTag);
    field.value = tags.join(', ');
});

// ---- Folder / category modal: new, or rename when opened from [data-doc-edit]
on(document, 'click', (e) => {
    const trigger = e.target.closest('[data-bs-target="#folderModal"], [data-bs-target="#categoryModal"]');
    if (!trigger) return;
    const modal = document.querySelector(trigger.dataset.bsTarget);
    const form = modal?.querySelector('form');
    if (!form) return;

    const editUrl = trigger.dataset.docEdit;
    form.action = editUrl || form.dataset.storeUrl;
    const method = form.querySelector('[name=_method]');
    const editField = form.querySelector('[name=_edit_url]');
    method.disabled = !editUrl;
    editField.disabled = !editUrl;
    editField.value = editUrl ?? '';
    form.querySelector('.js-title').textContent = editUrl ? form.dataset.editTitle : form.dataset.newTitle;

    const name = form.querySelector('[name=name]');
    name.value = editUrl ? (trigger.dataset.name ?? '') : '';
    name.classList.remove('is-invalid');
    const color = trigger.dataset.color;
    const radios = [...form.querySelectorAll('[name=color]')];
    (radios.find((r) => r.value === color) ?? radios[0]).checked = true;
});
['folderModal', 'categoryModal'].forEach((id) =>
    document.getElementById(id)?.addEventListener('shown.bs.modal', (e) => e.target.querySelector('[name=name]')?.focus()),
);

// ---- Favourite without reloading the page
on(document, 'submit', async (e) => {
    const form = e.target.closest('.js-doc-favorite');
    if (!form) return;
    e.preventDefault();
    const button = form.querySelector('button');
    button.disabled = true;
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(String(response.status));
        const { favorite } = await response.json();
        button.classList.toggle('on', favorite);
        button.setAttribute('aria-pressed', String(favorite));
        button.querySelector('i').className = `bi ${favorite ? 'bi-star-fill' : 'bi-star'}`;
    } catch {
        form.submit();
    } finally {
        button.disabled = false;
    }
});

// ---- Send to colleagues: a document of the archive (#forwardModal) or a reply (#exchangeModal)
on(document, 'click', (e) => {
    const forward = e.target.closest('[data-doc-forward]');
    if (forward) {
        const form = byId('forwardModal')?.querySelector('form');
        if (!form) return;
        form.action = forward.dataset.docForward;
        form.querySelector('[name=_forward_url]').value = forward.dataset.docForward;
        form.querySelector('[name=_forward_title]').value = forward.dataset.title ?? '';
        form.querySelector('.js-forward-title').textContent = forward.dataset.title ?? '';
        form.querySelectorAll('[name="recipients[]"]').forEach((box) => (box.checked = false));
        form.querySelector('[name=message]').value = '';
        return;
    }
    const trigger = e.target.closest('[data-bs-target="#exchangeModal"]');
    if (!trigger) return;
    // A reply ticks the sender only; the header button keeps what was ticked before.
    if (trigger.dataset.docReply) {
        byId('exchangeModal')
            ?.querySelectorAll('[name="recipients[]"]')
            .forEach((box) => (box.checked = box.value === trigger.dataset.docReply));
    }
});

// ---- After a validation error, show the modal the form came from
const reopen = document.querySelector('[data-doc-reopen]')?.dataset.docReopen;
if (reopen) {
    const modal = document.querySelector(reopen);
    if (modal) Modal.getOrCreateInstance(modal).show();
}
