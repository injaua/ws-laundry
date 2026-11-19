@extends("layouts.admin")
@section("title", __('app.new_transaction'))
@section("page_title", __('app.new_transaction'))

@section("content")
<form action="{{ route("orders.store") }}" method="POST" id="order-form" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="total_price" id="total-price" value="{{ old("total_price", 0) }}">
    @if($pickup)
    <input type="hidden" name="pickup_job_id" value="{{ $pickup->id }}">
    <div class="alert alert-info d-flex align-items-center gap-2">
        <i class="bi bi-box-arrow-in-down fs-4"></i>
        <div>{{ __('app.order_from_pickup', ["date" => $pickup->completed_at?->format(datetime_fmt()), "fee" => money($pickup->fee)]) }}@if($pickup->zone?->free_from !== null) {{ __('app.free_from_amount', ["amount" => money($pickup->zone->free_from)]) }}@endif</div>
    </div>
    @error("pickup_job_id") <div class="alert alert-danger">{{ $message }}</div> @enderror
    @endif

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
                                <option value="{{ $c->id }}" data-credit="{{ $c->is_credit_customer ? 1 : 0 }}" data-terms="{{ $c->credit_terms_days ?? 30 }}" data-limit="{{ $c->credit_limit ?? 0 }}" data-business="{{ $c->isBusiness() ? 1 : 0 }}" @selected(old("customer_id", $pickup?->customer_id) == $c->id)>{{ $c->name }} · {{ $c->phone }}@if($c->is_credit_customer) ★@endif</option>
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
                        <div id="promiseHint" class="form-text"></div>
                    </div>
                    <div id="creditOption" class="mb-3 d-none">
                        <div class="border rounded p-2 bg-body-tertiary">
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_credit" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_credit" name="is_credit" value="1" @checked(old("is_credit") || old("credit_override_note"))>
                                <label class="form-check-label fw-semibold" for="is_credit">{{ __('app.bill_on_credit') }}</label>
                            </div>
                            <div class="small text-muted mt-1" id="creditHint"></div>
                            <div class="mt-2 d-none" id="creditOverrideWrap">
                                <label class="form-label small" for="credit_override_note">{{ __('app.credit_override_note') }}</label>
                                <textarea name="credit_override_note" id="credit_override_note" rows="2" class="form-control form-control-sm @error("credit_override_note") is-invalid @enderror">{{ old("credit_override_note") }}</textarea>
                                @error("credit_override_note") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">{{ __('app.credit_override_note_hint') }}</div>
                            </div>
                            @error("is_credit") <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div id="purchaseOrderWrap" class="mb-3 d-none">
                        <label class="form-label" for="purchase_order_ref">{{ __('app.purchase_order_ref') }}</label>
                        <input type="text" name="purchase_order_ref" id="purchase_order_ref" maxlength="60" class="form-control @error("purchase_order_ref") is-invalid @enderror" value="{{ old("purchase_order_ref") }}">
                        @error("purchase_order_ref") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">{{ __('app.purchase_order_ref_hint') }}</div>
                    </div>
                    <div id="pricingNotice" class="alert alert-info py-2 small d-none"><i class="bi bi-tags"></i> {{ __('app.customer_special_prices_applied') }}</div>
                    @if($express["enabled"])
                    <div class="mb-3 border rounded p-2">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_express" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_express" name="is_express" value="1" @checked(old("is_express"))>
                            <label class="form-check-label fw-semibold" for="is_express"><i class="bi bi-lightning-charge text-warning"></i> {{ __('app.express_service') }}</label>
                        </div>
                        <div class="small text-muted mt-1">{{ __('app.express_hint', ["fee" => $express["type"] === "percent" ? qty($express["value"]) . "%" : money($express["value"]), "days" => $express["days"]]) }}</div>
                    </div>
                    @endif
                    <div>
                        <label class="form-label" for="notes">{{ __('app.notes') }}</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3">{{ old("notes") }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Intake inspection: care instructions, damage already present, photos --}}
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-clipboard-check me-1"></i> {{ __('app.intake_inspection') }}</div>
                <div class="card-body">
                    <div class="row g-1 mb-2">
                        @foreach(\App\Models\Order::CARE_FLAGS as $flag)
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="care_flags[]" value="{{ $flag }}" id="care_{{ $flag }}" @checked(in_array($flag, old("care_flags", [])))>
                                    <label class="form-check-label small" for="care_{{ $flag }}">{{ __('app.care_' . $flag) }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <label class="form-label small mb-0" for="intake_notes">{{ __('app.intake_notes') }}</label>
                    <textarea name="intake_notes" id="intake_notes" rows="2" maxlength="1000" class="form-control form-control-sm mb-2" placeholder="{{ __('app.intake_notes_placeholder') }}">{{ old("intake_notes") }}</textarea>
                    <label class="form-label small mb-0" for="photos">{{ __('app.intake_photos') }}</label>
                    <input type="file" name="photos[]" id="photos" accept="image/*" capture="environment" multiple class="form-control form-control-sm @error("photos.*") is-invalid @enderror">
                    @error("photos.*") <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            {{-- Summary --}}
            <div class="card order-summary-card">
                <div class="card-body">
                    <div class="summary-row">
                        <span>{{ __('app.service_items') }}</span>
                        <strong id="summary-items">0</strong>
                    </div>
                    @if($pickup)
                    <div class="summary-row" id="pickup-row">
                        <span><i class="bi bi-truck"></i> {{ __('app.delivery_fee') }}</span>
                        <strong id="pickup-display">{{ money(0) }}</strong>
                    </div>
                    @endif
                    <div class="summary-row d-none" id="express-row">
                        <span><i class="bi bi-lightning-charge text-warning"></i> {{ __('app.express_fee') }}</span>
                        <strong id="express-display">{{ money(0) }}</strong>
                    </div>
                    @if(($vatRate ?? 0) > 0)
                    <div class="summary-row">
                        <span>{{ __('app.subtotal') }}</span>
                        <strong id="subtotal-display">{{ money(0) }}</strong>
                    </div>
                    <div class="summary-row">
                        <span>{{ __('app.vat') }} {{ qty($vatRate) }}%</span>
                        <strong id="vat-display">{{ money(0) }}</strong>
                    </div>
                    @endif
                    <div class="summary-row">
                        <span>{{ __('app.total') }}</span>
                        <strong class="summary-total" id="total-display">{{ money(0) }}</strong>
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
                    @include("orders._item-template")

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
    @vite("resources/css/pages/order-form.css")
@endpush

@push("scripts")
    <x-page-data name="order-form" :data="$formData"/>
    @vite("resources/js/pages/order-form.js")
@endpush
