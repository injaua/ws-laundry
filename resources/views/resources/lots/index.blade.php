@extends("layouts.admin")
@section("title", __('app.lots'))
@section("page_title", __('app.lots'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $lots->total()]) }}</span>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("resources.lots.index") }}" method="GET" class="row g-2">
                <div class="col-md-4">
                    <select name="item_id" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.item') }}</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" {{ request("item_id") == $item->id ? "selected" : "" }}>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="kind" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.type') }}</option>
                        @foreach(["product", "material"] as $kd)
                            <option value="{{ $kd }}" {{ request("kind") == $kd ? "selected" : "" }}>{{ __('app.' . $kd . 's') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.status') }}</option>
                        <option value="ok" {{ request("status") == "ok" ? "selected" : "" }}>{{ __('app.lot_status_ok') }}</option>
                        <option value="expiring" {{ request("status") == "expiring" ? "selected" : "" }}>{{ __('app.expiring_soon') }}</option>
                        <option value="expired" {{ request("status") == "expired" ? "selected" : "" }}>{{ __('app.expired') }}</option>
                    </select>
                </div>
                <div class="col-auto">
                    <a href="{{ route("resources.lots.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.item') }}</th>
                        <th>{{ __('app.lot_number') }}</th>
                        <th class="text-end">{{ __('app.quantity') }}</th>
                        <th class="text-end">{{ __('app.lot_remaining') }}</th>
                        <th>{{ __('app.received_at') }}</th>
                        <th>{{ __('app.expiry_date') }}</th>
                        <th>{{ __('app.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lots as $lot)
                    <tr>
                        <td>
                            <a href="{{ route("resources.items.show", $lot->item) }}" class="text-decoration-none fw-medium">{{ $lot->item->name }}</a>
                            <small class="text-muted d-block">{{ __('app.' . $lot->item->kind . 's') }}</small>
                        </td>
                        <td class="small">{{ $lot->lot_number }}</td>
                        <td class="text-end small">{{ qty($lot->quantity) }} {{ __('app.unit_' . $lot->item->unit) }}</td>
                        <td class="text-end">{{ qty($lot->remaining_qty) }} {{ __('app.unit_' . $lot->item->unit) }}</td>
                        <td class="small">{{ $lot->received_at?->format(date_fmt()) ?? "—" }}</td>
                        <td class="small">
                            @if($lot->expires_at)
                                <span class="{{ $lot->isExpired() ? "text-danger fw-semibold" : ($lot->isExpiringSoon() ? "text-warning fw-semibold" : "") }}">{{ $lot->expires_at->format(date_fmt()) }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td><span class="badge badge-status bg-{{ $lot->status_color }}">{{ __('app.lot_status_' . ($lot->isExpired() ? "expired" : ($lot->isExpiringSoon() ? "expiring" : "ok"))) }}</span></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-boxes fs-1 d-block mb-2"></i>
                            {{ __('app.no_lots') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $lots->links() }}</div>
@endsection
