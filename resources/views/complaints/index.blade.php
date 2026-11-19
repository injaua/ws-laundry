@extends("layouts.admin")
@section("title", __('app.complaints'))
@section("page_title", __('app.complaints'))

@php($statusColors = ["open" => "danger", "in_progress" => "warning", "resolved" => "success", "rejected" => "secondary"])

@section("content")
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">{{ __('app.complaints_pending') }}</div>
            <div class="fs-4 fw-bold">{{ $stats["pending"] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100 {{ $stats["late"] ? "border-danger" : "" }}"><div class="card-body">
            <div class="small text-muted">{{ __('app.complaints_late') }}</div>
            <div class="fs-4 fw-bold {{ $stats["late"] ? "text-danger" : "" }}">{{ $stats["late"] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">{{ __('app.complaints_avg_time') }}</div>
            <div class="fs-4 fw-bold">{{ $stats["avg_days"] !== null ? $stats["avg_days"] . " " . __('app.days') : "—" }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">{{ __('app.complaints_top_causes') }}</div>
            @forelse($stats["by_category"]->take(3) as $cat => $total)
                <div class="small d-flex justify-content-between"><span>{{ __('app.complaint_category_' . $cat) }}</span><span class="fw-semibold">{{ $total }}</span></div>
            @empty
                <div class="small text-muted">—</div>
            @endforelse
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <form class="d-flex flex-wrap gap-2" method="GET">
            <select name="status" class="form-select form-select-sm w-auto" aria-label="{{ __('app.status') }}" data-auto-submit>
                <option value="pending" @selected($status === "pending")>{{ __('app.complaints_pending') }}</option>
                <option value="late" @selected($status === "late")>{{ __('app.complaints_late') }}</option>
                @foreach(\App\Models\Complaint::STATUSES as $st)
                    <option value="{{ $st }}" @selected($status === $st)>{{ __('app.complaint_status_' . $st) }}</option>
                @endforeach
                <option value="all" @selected($status === "all")>{{ __('app.all') }}</option>
            </select>
            <input type="text" name="search" class="form-control form-control-sm w-auto" value="{{ $search }}" placeholder="{{ __('app.search') }}" aria-label="{{ __('app.search') }}">
            <button class="btn btn-sm btn-outline-primary" aria-label="{{ __('app.search') }}"><i class="bi bi-search"></i></button>
        </form>
        <a href="{{ route("complaints.create") }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('app.complaint_new') }}</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('app.number') }}</th>
                    <th>{{ __('app.customer') }}</th>
                    <th>{{ __('app.complaint_category') }}</th>
                    <th>{{ __('app.status') }}</th>
                    <th>{{ __('app.complaint_deadline') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($complaints as $c)
                <tr>
                    <td class="text-nowrap">
                        <a href="{{ route("complaints.show", $c) }}" class="font-monospace small">{{ $c->number }}</a>
                        <div class="small text-muted">{{ $c->created_at->format(date_fmt()) }}</div>
                    </td>
                    <td>
                        {{ $c->customer?->name }}
                        @if($c->order)<div class="small"><a href="{{ route("orders.show", $c->order_id) }}">{{ $c->order->order_number }}</a></div>@endif
                    </td>
                    <td>
                        {{ __('app.complaint_category_' . $c->category) }}
                        <div class="small text-muted text-truncate mw-280px">{{ $c->description }}</div>
                    </td>
                    <td><span class="badge bg-{{ $statusColors[$c->status] }}">{{ __('app.complaint_status_' . $c->status) }}</span></td>
                    <td class="text-nowrap {{ $c->isLate() ? "text-danger fw-semibold" : "" }}">
                        @if($c->isClosed())
                            <span class="text-muted small">{{ $c->resolved_at?->format(date_fmt()) }}</span>
                        @else
                            {{ $c->due_at->format(date_fmt()) }}
                            @if($c->isLate())<i class="bi bi-alarm" title="{{ __('app.complaints_late') }}"></i>@endif
                        @endif
                    </td>
                    <td class="text-end"><a href="{{ route("complaints.show", $c) }}" class="btn btn-sm btn-outline-primary py-0">{{ __('app.open') }}</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">{{ __('app.complaints_none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @if($complaints->hasPages())<div class="card-footer bg-transparent">{{ $complaints->links() }}</div>@endif
</div>
@endsection
