// AI settings: choosing a provider suggests its address and prices (only into empty fields, or
// fields still holding the previous provider's suggestion) and the models it offers. The list
// holds the known suggestions; "Load models" asks the provider for the real list with the key.
// The model field stays editable ("Other") for any model the list does not show.
import { csrfToken, esc, pageData } from '../lib/dom';
import { on } from '../lib/page';

const OTHER = '__other';
const provider = document.getElementById('ai_provider');
const select = document.getElementById('ai_model_select');
const model = document.getElementById('ai_model');
const load = document.getElementById('aiLoadModels');
const status = document.getElementById('aiModelsStatus');
const baseUrl = document.getElementById('ai_base_url');
const apiKey = document.getElementById('ai_api_key');
const fields = [
    [baseUrl, 'baseUrl'],
    [document.getElementById('ai_price_input'), 'priceIn'],
    [document.getElementById('ai_price_output'), 'priceOut'],
];
const defaultHelp = status?.textContent ?? '';

/** Fill the list: [{id, name}] from the provider, or the suggestions. */
function fillModels(models, live) {
    const current = model.value.trim();
    const label = live ? select.dataset.available : select.dataset.suggested;
    const options = models
        .map((m) => `<option value="${esc(m.id)}">${esc(m.name && m.name !== m.id ? `${m.name} (${m.id})` : m.id)}</option>`)
        .join('');
    select.innerHTML = `${options ? `<optgroup label="${esc(label)}">${options}</optgroup>` : ''}<option value="${OTHER}">${esc(select.dataset.other)}</option>`;

    const known = models.some((m) => m.id === current);
    if (known) {
        select.value = current;
    } else if (!current && models.length) {
        select.value = models[0].id;
        model.value = models[0].id;
    } else {
        select.value = OTHER;
    }
    model.hidden = select.value !== OTHER;
}

const suggested = () => (pageData('ai-models')[provider.value] ?? []).map((id) => ({ id, name: id }));

async function loadModels() {
    load.disabled = true;
    status.textContent = load.dataset.loading;
    status.classList.remove('text-danger', 'text-success');
    try {
        const level = document.querySelector('#aiSettingsForm input[name="level"]')?.value;
        const response = await fetch(load.dataset.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ provider: provider.value, base_url: baseUrl.value || null, api_key: apiKey.value || null, level }),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            status.textContent = data.error ?? Object.values(data.errors ?? {})[0]?.[0] ?? load.dataset.error;
            status.classList.add('text-danger');
            return;
        }
        fillModels(data.models, true);
        status.textContent = load.dataset.loaded.replace(':count', data.models.length);
        status.classList.add('text-success');
    } catch {
        status.textContent = load.dataset.error;
        status.classList.add('text-danger');
    } finally {
        load.disabled = false;
    }
}

if (provider && select && model) {
    let previous = { ...provider.selectedOptions[0].dataset };
    fillModels(suggested(), false);

    on(provider, 'change', () => {
        const preset = provider.selectedOptions[0].dataset;
        fields.forEach(([input, key]) => {
            if (input && (input.value === '' || input.value === (previous[key] ?? ''))) input.value = preset[key] ?? '';
        });
        previous = { ...preset };
        // Another provider: its models (the one typed belongs to the previous provider).
        model.value = '';
        status.textContent = defaultHelp;
        status.classList.remove('text-danger', 'text-success');
        fillModels(suggested(), false);
    });

    on(select, 'change', () => {
        if (select.value === OTHER) {
            model.hidden = false;
            model.value = '';
            model.focus();
        } else {
            model.value = select.value;
            model.hidden = true;
        }
    });

    on(load, 'click', loadModels);
}
