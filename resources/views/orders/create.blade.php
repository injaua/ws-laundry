@extends("layouts.admin")
@section("title", __('app.new_transaction'))
@section("page_title", __('app.new_transaction'))

@section("content")
<form action="{{ route("orders.store") }}" method="POST" id="order-form">
    @csrf
    <input type="hidden" name="total_price" id="total-price" value="{{ old("total_price", 0) }}">

    <div class="row g-3">
        <div class="col-lg-4">
            {{-- Customer --}}
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person me-1"></i> {{ __('app.customer_data') }}</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="customer_id">{{ __('app.customer') }} <span class="text-danger">*</span></label>
                        <select name="customer_id" id="customer_id" class="form-select @error("customer_id") is-invalid @enderror" required>
                            <option value="">{{ __('app.select_customer') }}</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" @selected(old("customer_id") == $c->id)>{{ $c->name }} · {{ $c->phone }}</option>
                            @endforeach
                        </select>
                        @error("customer_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="d-flex align-items-center gap-3 mt-2">
                            <a href="{{ route("customers.create") }}" target="_blank" class="small">
                                <i class="bi bi-plus-circle"></i> {{ __('app.new_customer') }}
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0" id="refreshCustomersBtn" title="{{ __('app.refresh_customers') }}">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="received_at">{{ __('app.entry_date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="received_at" id="received_at" class="form-control @error("received_at") is-invalid @enderror" value="{{ old("received_at", date("Y-m-d")) }}" required>
                        @error("received_at") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="delivery_date">{{ __('app.delivery_date') }}</label>
                        <input type="date" name="delivery_date" id="delivery_date" class="form-control @error("delivery_date") is-invalid @enderror" value="{{ old("delivery_date") }}">
                        @error("delivery_date") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="form-label" for="notes">{{ __('app.notes') }}</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3">{{ old("notes") }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Summary --}}
            <div class="card order-summary-card">
                <div class="card-body">
                    <div class="summary-row">
                        <span>{{ __('app.service_items') }}</span>
                        <strong id="summary-items">0</strong>
                    </div>
                    @if(($ivaRate ?? 0) > 0)
                    <div class="summary-row">
                        <span>{{ __('app.subtotal') }}</span>
                        <strong id="subtotal-display">0 Mts</strong>
                    </div>
                    <div class="summary-row">
                        <span>{{ __('app.iva') }} {{ rtrim(rtrim(number_format($ivaRate, 2, ".", ","), "0"), ".") }}%</span>
                        <strong id="iva-display">0 Mts</strong>
                    </div>
                    @endif
                    <div class="summary-row">
                        <span>{{ __('app.total') }}</span>
                        <strong class="summary-total" id="total-display">0 Mts</strong>
                    </div>
                    <div class="d-grid gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> {{ __('app.save_transaction') }}
                        </button>
                        <a href="{{ route("orders.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Items --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <span><i class="bi bi-cart me-1"></i> {{ __('app.service_items') }}</span>
                    <button type="button" class="btn btn-sm btn-primary" id="addItemBtn">
                        <i class="bi bi-plus-lg"></i> {{ __('app.add_item') }}
                    </button>
                </div>
                <div class="card-body">
                    <div id="items-container" class="d-flex flex-column gap-3"></div>

                    <div class="empty-items d-none" id="items-empty">
                        <i class="bi bi-cart-plus"></i>
                        <p>{{ __('app.no_items_yet') }}</p>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addFirstItemBtn">{{ __('app.add_first_item') }}</button>
                    </div>

                    @error("items") <div class="text-danger small mt-3">{{ $message }}</div> @enderror
                    @error("items.*.service_id")
                        <div class="text-danger small mt-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('app.select_service_in_every_item') }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push("styles")
<style>
    .item-card {
        border: 1px solid #e9ecef; border-radius: 12px; padding: 1rem 1.1rem;
        background: #fbfcfe; transition: box-shadow .2s, border-color .2s;
    }
    [data-bs-theme="dark"] .item-card { background: #232336; border-color: #2d2d2d; }
    .item-card:focus-within { border-color: var(--bs-primary); box-shadow: 0 0 0 3px rgba(26,35,126,.09); }
    .item-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .75rem; }
    .item-index {
        font-size: .78rem; font-weight: 700;
        display: flex; align-items: center; gap: .4rem;
    }
    .item-index .pill {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 22px; height: 22px; padding: 0 .45rem; border-radius: 100px;
        background: var(--bs-primary); color: #fff; font-size: .72rem; font-weight: 700;
    }
    [data-bs-theme="dark"] .item-index .pill { background: #3949ab; }
    .item-subtotal { font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .field-label { display: block; font-size: .72rem; font-weight: 600; color: #6c757d; margin-bottom: .3rem; }
    [data-bs-theme="dark"] .field-label { color: var(--text-muted, #9e9e9e); }
    .order-summary-card .summary-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: .35rem 0; font-size: .9rem; color: #6c757d;
    }
    .order-summary-card .summary-total { font-size: 1.4rem; color: var(--bs-primary); font-variant-numeric: tabular-nums; }
    [data-bs-theme="dark"] .order-summary-card .summary-total { color: #90caf9; }
    .empty-items {
        border: 2px dashed #d7dce6; border-radius: 12px; padding: 2.5rem 1rem;
        text-align: center; color: #6c757d;
    }
    [data-bs-theme="dark"] .empty-items { border-color: #3a3a4a; }
    .empty-items i { font-size: 2rem; display: block; margin-bottom: .6rem; }
    .empty-items p { margin-bottom: .9rem; font-size: .9rem; }
</style>
@endpush

@push("scripts")
<script>
const services = @json($services);
const packages = @json($packages);
const LBL = {
    item: @json(__('app.item')),
    service: @json(__('app.service')),
    price: @json(__('app.price')),
    weight: @json(__('app.weight')),
    quantity: @json(__('app.quantity')),
    pieces: @json(__('app.pieces')),
    packages: @json(__('app.packages')),
    by_package: @json(__('app.by_package')),
    select_service: @json(__('app.select_service')),
    pick_service_first: @json(__('app.pick_service_first')),
    delete: @json(__('app.delete')),
};
let itemSeq = 0;
const container = document.getElementById("items-container");

function fmt(n) {
    return Math.round(n).toLocaleString("en-US");
}

function itemOptions(serviceId, packageId) {
    let html = '<option value="">' + LBL.select_service + "</option>";
    html += '<optgroup label="' + LBL.service + '">';
    services.forEach(s => {
        const sel = String(serviceId) === String(s.id) ? " selected" : "";
        html += '<option value="s' + s.id + '" data-kind="service" data-id="' + s.id + '" data-type="' + s.type + '" data-price="' + s.price + '" data-days="' + (s.estimated_days || 0) + '"' + sel + ">"
            + s.name + " · " + fmt(s.price) + " Mts/" + (s.type === "by_weight" ? "kg" : LBL.pieces)
            + "</option>";
    });
    html += "</optgroup>";
    if (packages.length) {
        html += '<optgroup label="' + LBL.packages + '">';
        packages.forEach(p => {
            const sel = String(packageId) === String(p.id) ? " selected" : "";
            html += '<option value="p' + p.id + '" data-kind="package" data-id="' + p.id + '" data-type="by_package" data-price="' + p.price + '"' + sel + ">"
                + p.name + " · " + p.weight_kg + " kg · " + fmt(p.price) + " Mts"
                + "</option>";
        });
        html += "</optgroup>";
    }
    return html;
}

function addItem(data = null) {
    const idx = itemSeq++;
    const row = document.createElement("div");
    row.className = "item-card";
    row.innerHTML = `
        <div class="item-head">
            <span class="item-index"><span class="pill" data-index-label></span> ${LBL.item}</span>
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove aria-label="${LBL.delete}">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="row g-3">
            <div class="col-lg-5">
                <label class="field-label">${LBL.service} / ${LBL.packages} <span class="text-danger">*</span></label>
                <select class="form-select item-select" data-item-select required>
                    ${itemOptions(data ? data.service_id : null, data ? data.package_id : null)}
                </select>
                <input type="hidden" name="items[${idx}][service_id]" class="service-id-input" value="${data && data.service_id ? data.service_id : ""}">
                <input type="hidden" name="items[${idx}][package_id]" class="package-id-input" value="${data && data.package_id ? data.package_id : ""}">
            </div>
            <div class="col-lg-3">
                <div class="measure-weight d-none">
                    <label class="field-label">${LBL.weight} (kg) <span class="text-danger">*</span></label>
                    <input type="number" name="items[${idx}][weight]" class="form-control weight-input" step="0.5" min="0.5" placeholder="0.5" value="${data ? (data.weight || "") : ""}">
                </div>
                <div class="measure-piece d-none">
                    <label class="field-label">${LBL.quantity} (${LBL.pieces}) <span class="text-danger">*</span></label>
                    <input type="number" name="items[${idx}][amount]" class="form-control amount-input" min="1" value="${data ? (data.amount || 1) : 1}">
                </div>
                <div class="measure-package d-none">
                    <label class="field-label">${LBL.quantity} (${LBL.by_package}) <span class="text-danger">*</span></label>
                    <input type="number" name="items[${idx}][amount]" class="form-control amount-input" min="1" value="${data ? (data.amount || 1) : 1}">
                </div>
                <div class="measure-placeholder text-muted small">${LBL.pick_service_first}</div>
            </div>
            <div class="col-lg-4">
                <label class="field-label">${LBL.price}</label>
                <div class="input-group">
                    <input type="number" name="items[${idx}][price]" class="form-control price-input" readonly tabindex="-1" value="${data ? data.unit_price : ""}">
                    <span class="input-group-text">Mts</span>
                </div>
                <input type="hidden" name="items[${idx}][subtotal]" class="subtotal-input" value="${data ? (data.subtotal || 0) : 0}">
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="unit-suffix text-muted small"></span>
                    <span class="item-subtotal">0 Mts</span>
                </div>
            </div>
        </div>
    `;
    container.appendChild(row);
    row.querySelector(".item-select").addEventListener("change", () => {
        const opt = row.querySelector(".item-select").selectedOptions[0];
        const isPkg = opt && opt.dataset.kind === "package";
        row.querySelector(".service-id-input").value = isPkg ? "" : (opt ? opt.dataset.id : "");
        row.querySelector(".package-id-input").value = isPkg ? opt.dataset.id : "";
        updateItem(row);
    });
    row.querySelectorAll(".weight-input, .amount-input").forEach(i => i.addEventListener("input", () => calcSubtotal(row)));
    row.querySelector("[data-remove]").addEventListener("click", () => removeItem(row));

    if (data && (data.service_id || data.package_id)) updateItem(row);
    refreshChrome();
}

function updateItem(row) {
    const opt = row.querySelector(".item-select").selectedOptions[0];
    const type = opt?.dataset.type || "";
    const mw = row.querySelector(".measure-weight");
    const mp = row.querySelector(".measure-piece");
    const mpk = row.querySelector(".measure-package");
    const ph = row.querySelector(".measure-placeholder");

    // Disabled inputs are not submitted; each row has two amount inputs (piece/package)
    // and the browser would otherwise send both, with the hidden one overwriting the real value.
    const wi = row.querySelector(".weight-input");
    const pieceInput = mp.querySelector(".amount-input");
    const pkgInput = mpk.querySelector(".amount-input");
    wi.disabled = type !== "by_weight";
    pieceInput.disabled = type !== "by_piece";
    pkgInput.disabled = type !== "by_package";

    if (!type) {
        mw.classList.add("d-none");
        mp.classList.add("d-none");
        mpk.classList.add("d-none");
        ph.classList.remove("d-none");
        row.querySelector(".price-input").value = "";
        row.querySelector(".unit-suffix").textContent = "";
    } else {
        ph.classList.add("d-none");
        const price = parseFloat(opt.dataset.price) || 0;
        row.querySelector(".price-input").value = price;
        row.querySelector(".unit-suffix").textContent = fmt(price) + " Mts/" + (type === "by_weight" ? "kg" : (type === "by_package" ? LBL.by_package : LBL.pieces));
        mw.classList.toggle("d-none", type !== "by_weight");
        mp.classList.toggle("d-none", type !== "by_piece");
        mpk.classList.toggle("d-none", type !== "by_package");
    }
    calcSubtotal(row);
}

function calcSubtotal(row) {
    const opt = row.querySelector(".item-select").selectedOptions[0];
    const type = opt?.dataset.type || "";
    const price = parseFloat(row.querySelector(".price-input").value) || 0;
    let subtotal = 0;

    if (type === "by_weight") {
        subtotal = price * (parseFloat(row.querySelector(".weight-input").value) || 0);
    } else if (type === "by_piece" || type === "by_package") {
        const scope = type === "by_package" ? ".measure-package" : ".measure-piece";
        subtotal = price * (parseInt(row.querySelector(scope + " .amount-input").value) || 0);
    }
    row.dataset.subtotal = Math.round(subtotal);
    row.querySelector(".subtotal-input").value = Math.round(subtotal);
    row.querySelector(".item-subtotal").textContent = fmt(subtotal) + " Mts";
    updateTotal();
}

function removeItem(row) {
    row.remove();
    updateTotal();
    refreshChrome();
}

function refreshChrome() {
    const rs = Array.from(container.querySelectorAll(".item-card"));
    rs.forEach((r, i) => {
        r.querySelector("[data-index-label]").textContent = i + 1;
    });
    document.getElementById("items-empty").classList.toggle("d-none", rs.length > 0);
}

function updateTotal() {
    const rs = Array.from(container.querySelectorAll(".item-card"));
    const subtotal = rs.reduce((sum, r) => sum + (parseInt(r.dataset.subtotal) || 0), 0);
    const iva = Math.round(subtotal * {{ $ivaRate ?? 0 }} / 100);
    const total = subtotal + iva;
    const sub = document.getElementById("subtotal-display");
    const ivaEl = document.getElementById("iva-display");
    if (sub) sub.textContent = fmt(subtotal) + " Mts";
    if (ivaEl) ivaEl.textContent = fmt(iva) + " Mts";
    document.getElementById("total-display").textContent = fmt(total) + " Mts";
    document.getElementById("total-price").value = total;
    document.getElementById("summary-items").textContent = rs.length;
    estimateDelivery();
}

// Delivery date defaults to the expected completion date (entry date + longest service lead time)
const deliveryInput = document.getElementById("delivery_date");
const receivedInput = document.getElementById("received_at");
let deliveryManual = deliveryInput.value !== "";

function estimateDelivery() {
    if (deliveryManual) return;
    let days = 0;
    container.querySelectorAll(".item-select").forEach(sel => {
        const opt = sel.selectedOptions[0];
        if (opt && opt.dataset.days) days = Math.max(days, parseInt(opt.dataset.days) || 0);
    });
    if (!days || !receivedInput.value) { deliveryInput.value = ""; return; }
    const d = new Date(receivedInput.value + "T00:00:00");
    d.setDate(d.getDate() + days);
    deliveryInput.value = d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
}

deliveryInput.addEventListener("change", () => { deliveryManual = deliveryInput.value !== ""; });
receivedInput.addEventListener("change", estimateDelivery);

// Repopulate after validation errors, else start with one blank row
const oldItems = @json(old("items"));
if (oldItems && Object.keys(oldItems).length) {
    Object.values(oldItems).forEach(it => {
        if (!it || (!it.service_id && !it.package_id)) return;
        addItem({ service_id: it.service_id, package_id: it.package_id, weight: it.weight, amount: it.amount, unit_price: it.price, subtotal: it.subtotal });
    });
} else {
    addItem();
}

document.getElementById("addItemBtn").addEventListener("click", () => addItem());
document.getElementById("addFirstItemBtn").addEventListener("click", () => addItem());

document.getElementById("refreshCustomersBtn").addEventListener("click", function() {
    const btn = this;
    btn.disabled = true;
    fetch("{{ route("orders.create") }}").then(r => r.text()).then(html => {
        const src = new DOMParser().parseFromString(html, "text/html").querySelector("select[name='customer_id']");
        const cur = document.getElementById("customer_id");
        const keep = cur.value;
        cur.innerHTML = src.innerHTML;
        if ([...cur.options].some(o => o.value === keep)) cur.value = keep;
    }).finally(() => { btn.disabled = false; });
});
</script>
@endpush
