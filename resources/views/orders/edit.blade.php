@extends("layouts.admin")
@section("title", __('app.edit_order'))
@section("page_title", __('app.edit_order') . " #" . $order->order_number)

@section("content")
<form action="{{ route("orders.update", $order) }}" method="POST">
    @csrf @method("PUT")
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person me-1"></i> {{ __('app.order_info') }}</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.customer') }}</label>
                        <select name="customer_id" class="form-select @error("customer_id") is-invalid @enderror" required>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ old("customer_id", $order->customer_id) == $c->id ? "selected" : "" }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.status') }}</label>
                        <select name="status" class="form-select">
                            @foreach($order->allowedStatuses() as $st)
                                <option value="{{ $st }}" {{ old("status", $order->status) == $st ? "selected" : "" }}>
                                    {{ __('app.status_' . $st) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.entry_date') }}</label>
                        <input type="date" name="received_at" class="form-control @error("received_at") is-invalid @enderror" value="{{ old("received_at", $order->received_at->format("Y-m-d")) }}" required>
                        @error("received_at") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.delivery_date') }}</label>
                        <input type="date" name="delivery_date" id="delivery_date" class="form-control @error("delivery_date") is-invalid @enderror" value="{{ old("delivery_date", $order->delivery_date?->format("Y-m-d") ?? "") }}">
                        @error("delivery_date") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.completion_date') }}</label>
                        <input type="date" name="completed_at" id="completed_at" class="form-control @error("completed_at") is-invalid @enderror" value="{{ old("completed_at", ($order->completed_at ?? $order->delivery_date)?->format("Y-m-d") ?? "") }}">
                        @error("completed_at") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.notes') }}</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old("notes", $order->notes) }}</textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-cart me-1"></i> {{ __('app.service_items') }}</div>
                <div class="card-body">
                    @foreach($services as $svc)
                    <div class="mb-2">
                        <label class="d-flex justify-content-between">
                            <span>{{ $svc->name }} <small class="text-muted">({{ $svc->type === 'by_weight' ? __('app.by_weight') : __('app.by_piece') }})</small></span>
                            <span class="fw-semibold">{{ number_format($svc->price, 0, ".", ",") }} Mts</span>
                        </label>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('app.update') }}</button>
        <a href="{{ route("orders.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
    </div>
</form>
@endsection

@push("scripts")
<script>
// The delivery date follows the estimated / completion date unless typed by hand
const deliveryInput = document.getElementById("delivery_date");
const completionInput = document.getElementById("completed_at");
let manualDelivery = deliveryInput.value !== "";

deliveryInput.addEventListener("change", () => { manualDelivery = deliveryInput.value !== ""; });
completionInput.addEventListener("change", () => {
    if (!manualDelivery && completionInput.value) deliveryInput.value = completionInput.value;
});
</script>
@endpush
