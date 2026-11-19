@extends("layouts.admin")
@section("title", __('app.complaint_new'))
@section("page_title", __('app.complaint_new'))

@section("content")
<form action="{{ route("complaints.store") }}" method="POST" class="card mw-820px">
    @csrf
    <div class="card-body">
        <div class="row g-3">
            @if($order)
                <input type="hidden" name="order_id" value="{{ $order->id }}">
                <div class="col-12">
                    <div class="border rounded p-2 small">
                        <i class="bi bi-receipt"></i> <strong>{{ $order->order_number }}</strong> · {{ $order->customer?->name }} · {{ $order->received_at->format(date_fmt()) }}
                    </div>
                </div>
                @if($order->pieces->isNotEmpty())
                <div class="col-md-6">
                    <label class="form-label" for="order_piece_id">{{ __('app.complaint_piece') }}</label>
                    <select id="order_piece_id" name="order_piece_id" class="form-select">
                        <option value="">{{ __('app.complaint_whole_order') }}</option>
                        @foreach($order->pieces as $p)
                            <option value="{{ $p->id }}" @selected((int) old("order_piece_id", request("piece")) === $p->id)>{{ $p->code }} · {{ $p->description }}{{ $p->color ? " " . $p->color : "" }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            @else
                <div class="col-md-6">
                    <label class="form-label" for="customer_id">{{ __('app.customer') }}</label>
                    <select id="customer_id" name="customer_id" class="form-select @error("customer_id") is-invalid @enderror" required>
                        <option value="">—</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" @selected((int) old("customer_id") === $c->id)>{{ $c->name }}{{ $c->phone ? " · " . $c->phone : "" }}</option>
                        @endforeach
                    </select>
                    @error("customer_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">{{ __('app.complaint_from_order_tip') }}</div>
                </div>
            @endif

            <div class="col-md-3 col-6">
                <label class="form-label" for="category">{{ __('app.complaint_category') }}</label>
                <select id="category" name="category" class="form-select" required>
                    @foreach(\App\Models\Complaint::CATEGORIES as $cat)
                        <option value="{{ $cat }}" @selected(old("category") === $cat)>{{ __('app.complaint_category_' . $cat) }}</option>
                    @endforeach
                </select>
            </div>
            @if($order)
            <div class="col-md-3 col-6">
                <label class="form-label" for="stage">{{ __('app.complaint_stage') }}</label>
                <select id="stage" name="stage" class="form-select">
                    <option value="">{{ __('app.unknown') }}</option>
                    @foreach(\App\Models\Order::PRODUCTION_STAGES as $st)
                        <option value="{{ $st }}" @selected(old("stage") === $st)>{{ __('app.status_' . $st) }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-md-3 col-6">
                <label class="form-label" for="channel">{{ __('app.complaint_channel') }}</label>
                <select id="channel" name="channel" class="form-select" required>
                    @foreach(\App\Models\Complaint::CHANNELS as $ch)
                        <option value="{{ $ch }}" @selected(old("channel", "counter") === $ch)>{{ __('app.complaint_channel_' . $ch) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">{{ __('app.complaint_description') }}</label>
                <textarea id="description" name="description" rows="4" maxlength="2000" class="form-control @error("description") is-invalid @enderror" required placeholder="{{ __('app.complaint_description_placeholder') }}">{{ old("description") }}</textarea>
                @error("description") <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">{{ __('app.complaint_deadline_help', ["days" => \App\Models\Complaint::DEADLINE_DAYS]) }}</div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between bg-transparent">
        <a href="{{ $order ? route("orders.show", $order) : route("complaints.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('app.complaint_register') }}</button>
    </div>
</form>
@endsection
