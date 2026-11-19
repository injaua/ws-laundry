// New order (orders/create): item lines, live prices, totals, delivery promise, credit options.
// Server data: <x-page-data name="order-form">. Line markup: <template id="order-item-template">.
import { getJson, pageData } from '../lib/dom';
import { fmtMoney } from '../lib/money';
import { addDays, createPricing, orderTotals } from '../orders/pricing';

const data = pageData('order-form');
const { services = [], packages = [], express = {}, pickup = null, vatRate = 0, planning = false, urls = {}, texts = {} } = data;
const pricing = createPricing(data);

const $ = (id) => document.getElementById(id);
const container = $('items-container');
const template = $('order-item-template');
const customerSelect = $('customer_id');
const receivedInput = $('received_at');
const deliveryInput = $('delivery_date');
const expressCheck = $('is_express');
const creditCheck = $('is_credit');

let itemSeq = 0;
let deliveryManual = deliveryInput.value !== '';
let planTimer = null;

const quote = (kind, id, base) => pricing.quote({ customerId: customerSelect.value, date: receivedInput.value }, kind, id, base);
const unitLabel = (type) => (type === 'by_weight' ? 'kg' : type === 'by_package' ? texts.byPackage : texts.pieces);

// ---- Catalogue options (built with the DOM: names are never parsed as HTML)
function catalogueOption(kind, item, selectedId) {
    const q = quote(kind, item.id, item.price);
    const opt = document.createElement('option');
    opt.value = (kind === 'service' ? 's' : 'p') + item.id;
    Object.assign(opt.dataset, {
        kind,
        id: item.id,
        type: kind === 'service' ? item.type : 'by_package',
        list: item.price,
        price: q.price,
        days: kind === 'service' ? item.estimated_days || 0 : '',
    });
    const measure = kind === 'service' ? `${fmtMoney(q.price)}/${unitLabel(item.type)}` : `${item.weight_kg} kg · ${fmtMoney(q.price)}`;
    opt.textContent = `${item.name} · ${measure}${q.promo ? ` 🏷 ${q.promo}` : ''}`;
    opt.selected = String(selectedId) === String(item.id);

    return opt;
}

function fillOptions(select, serviceId, packageId) {
    const blank = document.createElement('option');
    blank.value = '';
    blank.textContent = texts.selectService;
    const groups = [blank];

    const serviceGroup = document.createElement('optgroup');
    serviceGroup.label = texts.service;
    services.forEach((s) => serviceGroup.append(catalogueOption('service', s, serviceId)));
    groups.push(serviceGroup);

    if (packages.length) {
        const packageGroup = document.createElement('optgroup');
        packageGroup.label = texts.packages;
        packages.forEach((p) => packageGroup.append(catalogueOption('package', p, packageId)));
        groups.push(packageGroup);
    }
    select.replaceChildren(...groups);
}

// ---- Item lines
function addItem(item = null) {
    const idx = itemSeq++;
    const row = template.content.firstElementChild.cloneNode(true);
    row.querySelectorAll('[name]').forEach((input) => {
        input.name = input.name.replace('__IDX__', idx);
    });
    if (item) {
        row.querySelector('.service-id-input').value = item.service_id || '';
        row.querySelector('.package-id-input').value = item.package_id || '';
        row.querySelector('.weight-input').value = item.weight || '';
        row.querySelectorAll('.amount-input').forEach((i) => {
            i.value = item.amount || 1;
        });
        row.querySelector('.price-input').value = item.unit_price ?? '';
        row.querySelector('.subtotal-input').value = item.subtotal || 0;
    }

    const select = row.querySelector('.item-select');
    fillOptions(select, item?.service_id ?? null, item?.package_id ?? null);
    select.addEventListener('change', () => {
        const opt = select.selectedOptions[0];
        const isPackage = opt?.dataset.kind === 'package';
        row.querySelector('.service-id-input').value = isPackage ? '' : (opt?.dataset.id ?? '');
        row.querySelector('.package-id-input').value = isPackage ? opt.dataset.id : '';
        updateItem(row);
    });
    row.querySelectorAll('.weight-input, .amount-input').forEach((i) => i.addEventListener('input', () => calcSubtotal(row)));
    row.querySelector('[data-remove]').addEventListener('click', () => removeItem(row));

    container.appendChild(row);
    if (item && (item.service_id || item.package_id)) updateItem(row);
    refreshChrome();
}

function updateItem(row) {
    const opt = row.querySelector('.item-select').selectedOptions[0];
    const type = opt?.dataset.type || '';
    const boxes = { by_weight: '.measure-weight', by_piece: '.measure-piece', by_package: '.measure-package' };

    // Disabled inputs are not submitted: each row has two amount inputs (piece / package)
    // and the hidden one would otherwise overwrite the real value.
    Object.entries(boxes).forEach(([kind, selector]) => {
        const box = row.querySelector(selector);
        box.classList.toggle('d-none', type !== kind);
        box.querySelector('input').disabled = type !== kind;
    });
    row.querySelector('.measure-placeholder').classList.toggle('d-none', Boolean(type));

    const priceInput = row.querySelector('.price-input');
    const suffix = row.querySelector('.unit-suffix');
    if (!type) {
        priceInput.value = '';
        suffix.textContent = '';
    } else {
        const price = parseFloat(opt.dataset.price) || 0;
        priceInput.value = price;
        suffix.textContent = `${fmtMoney(price)}/${unitLabel(type)}`;
        const q = quote(opt.dataset.kind, opt.dataset.id, opt.dataset.list);
        if (q.promo) {
            const del = document.createElement('del');
            del.className = 'ms-1';
            del.textContent = fmtMoney(q.list);
            const badge = document.createElement('span');
            badge.className = 'badge bg-danger-subtle text-danger-emphasis ms-1';
            badge.textContent = q.promo;
            suffix.append(del, badge);
        }
    }
    calcSubtotal(row);
}

function calcSubtotal(row) {
    const type = row.querySelector('.item-select').selectedOptions[0]?.dataset.type || '';
    const price = parseFloat(row.querySelector('.price-input').value) || 0;
    let subtotal = 0;
    if (type === 'by_weight') {
        subtotal = price * (parseFloat(row.querySelector('.weight-input').value) || 0);
    } else if (type === 'by_piece' || type === 'by_package') {
        const box = type === 'by_package' ? '.measure-package' : '.measure-piece';
        subtotal = price * (parseInt(row.querySelector(`${box} .amount-input`).value, 10) || 0);
    }
    row.dataset.subtotal = Math.round(subtotal);
    row.querySelector('.subtotal-input').value = Math.round(subtotal);
    row.querySelector('.item-subtotal').textContent = fmtMoney(subtotal);
    updateTotal();
}

function removeItem(row) {
    row.remove();
    updateTotal();
    refreshChrome();
}

function rows() {
    return [...container.querySelectorAll('.item-card')];
}

function refreshChrome() {
    rows().forEach((r, i) => {
        r.querySelector('[data-index-label]').textContent = i + 1;
    });
    $('items-empty').classList.toggle('d-none', rows().length > 0);
}

// Re-price every line: negotiated prices are per customer, promotions per entry date.
function repriceItems() {
    rows().forEach((row) => {
        fillOptions(
            row.querySelector('.item-select'),
            row.querySelector('.service-id-input').value || null,
            row.querySelector('.package-id-input').value || null,
        );
        updateItem(row);
    });
}

// ---- Totals
const setText = (id, text) => {
    const el = $(id);
    if (el) el.textContent = text;
};

function updateTotal() {
    const itemsSubtotal = rows().reduce((sum, r) => sum + (parseInt(r.dataset.subtotal, 10) || 0), 0);
    const t = orderTotals({ itemsSubtotal, express, expressOn: expressCheck?.checked, pickup, vatRate });

    setText('pickup-display', fmtMoney(t.pickupFee));
    $('express-row')?.classList.toggle('d-none', !t.expressApplies);
    setText('express-display', fmtMoney(t.expressFee));
    setText('subtotal-display', fmtMoney(t.subtotal));
    setText('vat-display', fmtMoney(t.vat));
    setText('total-display', fmtMoney(t.total));
    setText('summary-items', rows().length);
    $('total-price').value = t.total;
    estimateDelivery();
}

// ---- Delivery date: entry date + longest lead time (or the express days), unless typed by hand.
function estimateDelivery() {
    if (deliveryManual) return;
    if (express.enabled && expressCheck?.checked && receivedInput.value) {
        deliveryInput.value = addDays(receivedInput.value, express.days);
        return;
    }
    const days = rows().reduce(
        (max, r) => Math.max(max, parseInt(r.querySelector('.item-select').selectedOptions[0]?.dataset.days, 10) || 0),
        0,
    );
    if (!days || !receivedInput.value) {
        deliveryInput.value = '';
        return;
    }
    deliveryInput.value = addDays(receivedInput.value, days);
    planDelivery(days);
}

// Daily capacity: the production queue may push the promise date back.
function planDelivery(days) {
    const hint = $('promiseHint');
    if (!planning || !receivedInput.value) {
        hint.textContent = '';
        return;
    }
    let kg = 0;
    let pieces = 0;
    rows().forEach((row) => {
        const type = row.querySelector('.item-select').selectedOptions[0]?.dataset.type;
        if (type === 'by_weight') kg += parseFloat(row.querySelector('.weight-input').value) || 0;
        if (type === 'by_piece') pieces += parseInt(row.querySelector('.measure-piece .amount-input').value, 10) || 0;
    });
    clearTimeout(planTimer);
    planTimer = setTimeout(() => {
        const params = new URLSearchParams({ received_at: receivedInput.value, kg, pieces, lead_days: days });
        getJson(`${urls.promise}?${params}`)
            .then((p) => {
                if (!p?.enabled) return;
                if (!deliveryManual && p.date > deliveryInput.value) deliveryInput.value = p.date;
                hint.textContent = texts.promiseHint
                    .replace(':kg', p.queue_kg)
                    .replace(':pieces', p.queue_pieces)
                    .replace(':date', p.capacity_date.split('-').reverse().join('/'));
            })
            .catch(() => {});
    }, 400);
}

// ---- Customer: credit billing, purchase order, special prices
function syncCustomerOptions() {
    const opt = customerSelect.selectedOptions[0];
    const isCredit = opt?.dataset.credit === '1';
    $('creditOption').classList.toggle('d-none', !isCredit);
    if (!isCredit) creditCheck.checked = false;
    if (isCredit) {
        const terms = parseInt(opt.dataset.terms, 10) || 30;
        const limit = parseFloat(opt.dataset.limit) || 0;
        $('creditHint').textContent =
            `${texts.creditTerms}: ${terms} · ${texts.creditLimit}: ${limit > 0 ? fmtMoney(limit) : texts.unlimited}`;
    }
    $('creditOverrideWrap').classList.toggle('d-none', !creditCheck.checked);
    $('purchaseOrderWrap').classList.toggle('d-none', opt?.dataset.business !== '1');
    $('pricingNotice').classList.toggle('d-none', !pricing.hasSpecialPrices(customerSelect.value));
}

// The customer list may change in another tab (new customer): reload just the options.
$('refreshCustomersBtn').addEventListener('click', function () {
    this.disabled = true;
    fetch(urls.create)
        .then((r) => r.text())
        .then((html) => {
            const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('select[name="customer_id"]');
            const keep = customerSelect.value;
            customerSelect.replaceChildren(...fresh.children);
            if ([...customerSelect.options].some((o) => o.value === keep)) customerSelect.value = keep;
        })
        .finally(() => {
            this.disabled = false;
            syncCustomerOptions();
        });
});

customerSelect.addEventListener('change', () => {
    syncCustomerOptions();
    repriceItems();
});
creditCheck.addEventListener('change', () => $('creditOverrideWrap').classList.toggle('d-none', !creditCheck.checked));
deliveryInput.addEventListener('change', () => {
    deliveryManual = deliveryInput.value !== '';
});
receivedInput.addEventListener('change', () => {
    repriceItems();
    estimateDelivery();
});
expressCheck?.addEventListener('change', updateTotal);
$('addItemBtn').addEventListener('click', () => addItem());
$('addFirstItemBtn').addEventListener('click', () => addItem());

// Repopulate after validation errors, else start with one blank line.
const oldItems = Object.values(data.oldItems ?? {}).filter((it) => it && (it.service_id || it.package_id));
if (oldItems.length) {
    oldItems.forEach((it) =>
        addItem({
            service_id: it.service_id,
            package_id: it.package_id,
            weight: it.weight,
            amount: it.amount,
            unit_price: it.price,
            subtotal: it.subtotal,
        }),
    );
} else {
    addItem();
}
syncCustomerOptions();
updateTotal();
