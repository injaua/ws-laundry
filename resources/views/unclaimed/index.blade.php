@extends("layouts.admin")
@section("title", __('app.unclaimed'))
@section("page_title", __('app.unclaimed'))

@section("content")
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link {{ $tab === "waiting" ? "active" : "" }}" href="{{ route("unclaimed.index") }}">{{ __('app.unclaimed_waiting') }}</a></li>
    <li class="nav-item"><a class="nav-link {{ $tab === "abandoned" ? "active" : "" }}" href="{{ route("unclaimed.index", ["tab" => "abandoned"]) }}">{{ __('app.unclaimed_abandoned_tab') }}</a></li>
</ul>

@if($tab === "waiting")
<div class="row g-2 mb-3">
    @foreach(\App\Http\Controllers\UnclaimedController::BUCKETS as $i => $b)
        <div class="col-6 col-md">
            <a href="{{ route("unclaimed.index", ["bucket" => (string) $bucket === (string) $i ? null : $i]) }}"
               class="card h-100 text-decoration-none {{ (string) $bucket === (string) $i ? "border-primary border-2" : "" }} {{ $b[0] >= $abandonDays ? "border-danger" : "" }}">
                <div class="card-body py-2">
                    <div class="small text-muted">{{ $b[1] === null ? __('app.unclaimed_bucket_over', ["days" => $b[0]]) : __('app.unclaimed_bucket', ["from" => $b[0], "to" => $b[1]]) }}</div>
                    <div class="fs-4 fw-bold {{ $b[0] >= 30 && $counts[$i] ? "text-danger" : "text-body" }}">{{ $counts[$i] }}</div>
                </div>
            </a>
        </div>
    @endforeach
</div>
<p class="small text-muted">{{ __('app.unclaimed_value', ["amount" => money($waitingValue)]) }}
    @unless($reminderReady) · <span class="text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> {{ __('app.unclaimed_no_template') }}</span>@endunless
</p>
@endif

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('app.transactions') }}</th>
                    <th>{{ __('app.customer') }}</th>
                    @if($tab === "waiting")
                        <th>{{ __('app.unclaimed_ready_since') }}</th>
                        <th>{{ __('app.unclaimed_last_reminder') }}</th>
                        <th class="text-end">{{ __('app.remaining') }}</th>
                        <th></th>
                    @else
                        <th>{{ __('app.unclaimed_destination') }}</th>
                        <th>{{ __('app.date') }}</th>
                        <th></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                @php($days = $o->ready_at ? (int) $o->ready_at->diffInDays(now()) : null)
                <tr>
                    <td class="text-nowrap">
                        <a href="{{ route("orders.show", $o) }}">{{ $o->order_number }}</a>
                        <div class="small text-muted">{{ trans_choice('app.unclaimed_pieces', $o->pieces_count, ["count" => $o->pieces_count]) }}</div>
                    </td>
                    <td>
                        {{ $o->customer?->name }}
                        <div class="small text-muted">{{ $o->customer?->phone }}</div>
                    </td>
                    @if($tab === "waiting")
                        <td class="text-nowrap {{ $days !== null && $days >= $abandonDays ? "text-danger fw-semibold" : "" }}">
                            {{ $o->ready_at?->format(date_fmt()) ?? "—" }}
                            @if($days !== null)<div class="small">{{ trans_choice('app.unclaimed_days', $days, ["count" => $days]) }}</div>@endif
                        </td>
                        <td class="small">
                            @if($r = $lastReminder->get($o->id))
                                {{ \Illuminate\Support\Carbon::parse($r->at)->format(date_fmt()) }} <span class="text-muted">({{ $r->total }}×)</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">{{ money($o->remainingBalance()) }}</td>
                        <td class="text-end text-nowrap">
                            @if($reminderReady && $o->customer && !$o->customer->whatsapp_opt_out)
                            <form action="{{ route("unclaimed.remind", $o) }}" method="POST" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-success py-0" title="{{ __('app.unclaimed_remind') }}" aria-label="{{ __('app.unclaimed_remind') }} {{ $o->order_number }}"><i class="bi bi-whatsapp"></i></button>
                            </form>
                            @endif
                            @if($canManage && $days !== null && $days >= $abandonDays)
                            <button class="btn btn-sm btn-outline-danger py-0" data-bs-toggle="modal" data-bs-target="#abandonModal"
                                    data-modal-action="{{ route("unclaimed.abandon", $o) }}" data-order="{{ $o->order_number }}">{{ __('app.unclaimed_dispose') }}</button>
                            @endif
                        </td>
                    @else
                        <td>
                            <span class="badge bg-secondary">{{ __('app.abandon_' . $o->abandon_destination) }}</span>
                            @if($o->abandon_note)<div class="small text-muted">{{ $o->abandon_note }}</div>@endif
                        </td>
                        <td class="small text-nowrap">{{ $o->abandoned_at->format(date_fmt()) }}<div class="text-muted">{{ $o->abandoner?->name }}</div></td>
                        <td class="text-end">
                            @if($canManage)
                            <form action="{{ route("unclaimed.restore", $o) }}" method="POST" class="d-inline" data-confirm="{{ __('app.unclaimed_restore_confirm') }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary py-0">{{ __('app.unclaimed_restore') }}</button>
                            </form>
                            @endif
                        </td>
                    @endif
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">{{ $tab === "waiting" ? __('app.unclaimed_none') : __('app.unclaimed_none_abandoned') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @if($orders->hasPages())<div class="card-footer bg-transparent">{{ $orders->links() }}</div>@endif
</div>

@if($canManage)
<form action="{{ route("unclaimed.settings") }}" method="POST" class="card mt-3 mw-560px">
    @csrf
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div class="flex-grow-1">
            <label class="form-label small mb-0" for="unclaimed_abandon_days">{{ __('app.unclaimed_abandon_days') }}</label>
            <input type="number" id="unclaimed_abandon_days" name="unclaimed_abandon_days" min="7" max="730" class="form-control form-control-sm" value="{{ $abandonDays }}">
            <div class="form-text">{{ __('app.unclaimed_abandon_days_help') }}</div>
        </div>
        <button class="btn btn-sm btn-outline-primary mb-4">{{ __('app.save') }}</button>
    </div>
</form>

<div class="modal fade" id="abandonModal" tabindex="-1" aria-labelledby="abandonTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="abandonForm">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="abandonTitle">{{ __('app.unclaimed_dispose') }} <span id="abandonOrder" data-fill="order"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.close') }}"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="abandon_destination">{{ __('app.unclaimed_destination') }}</label>
                <select id="abandon_destination" name="abandon_destination" class="form-select mb-2" required>
                    @foreach(\App\Models\Order::ABANDON_DESTINATIONS as $d)
                        <option value="{{ $d }}">{{ __('app.abandon_' . $d) }}</option>
                    @endforeach
                </select>
                <label class="form-label" for="abandon_note">{{ __('app.notes') }}</label>
                <input type="text" id="abandon_note" name="abandon_note" maxlength="255" class="form-control">
                <div class="form-text">{{ __('app.unclaimed_dispose_help') }}</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                <button type="submit" class="btn btn-danger">{{ __('app.confirm') }}</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

