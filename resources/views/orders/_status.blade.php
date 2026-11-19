{{-- Status change of the order. --}}
{{-- Status Update --}}
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-arrow-repeat me-1"></i> {{ __('app.update_status') }}</div>
    <div class="card-body">
        @if($order->status === "completed")
            <div class="text-center py-3">
                <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center mx-auto mb-3 avatar-64"
                   >
                    <i class="bi bi-check-lg"></i>
                </div>
                <h5 class="text-success fw-bold mb-1">{{ __('app.transaction_completed') }}</h5>
                <p class="text-muted small mb-0">{{ __('app.status_locked_notice') }}</p>
            </div>
        @else
            <form action="{{ route("orders.update", $order) }}" method="POST">
                @csrf @method("PUT")
                <input type="hidden" name="customer_id" value="{{ $order->customer_id }}">
                <input type="hidden" name="received_at" value="{{ $order->received_at->format("Y-m-d") }}">
                <div class="mb-3">
                    <label class="form-label">{{ __('app.status') }}</label>
                    <select name="status" class="form-select">
                        @foreach($order->allowedStatuses() as $st)
                            <option value="{{ $st }}" {{ $order->status == $st ? "selected" : "" }}>
                                {{ __('app.status_' . $st) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('app.completion_date') }}</label>
                    <input type="date" name="completed_at" class="form-control" value="{{ old("completed_at", ($order->completed_at ?? $order->delivery_date)?->format("Y-m-d") ?? "") }}">
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-check-lg"></i> {{ __('app.update_status') }}
                </button>
            </form>
        @endif
    </div>
</div>
