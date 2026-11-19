@extends("layouts.admin")
@section("title", __('app.production_board'))
@section("page_title", __('app.production_board'))
@section("page_subtitle", __('app.production_board_subtitle'))

@php($num = fn ($n) => qty($n, 1))
@php($careIcons = ["delicate" => "feather", "separate_colors" => "palette", "no_softener" => "droplet-half", "low_temperature" => "thermometer-low", "hand_wash" => "hand-index", "dry_clean" => "wind", "no_tumble_dry" => "slash-circle", "stain_treatment" => "bullseye"])
@php($stageColors = ["received" => "secondary", "washing" => "primary", "drying" => "info", "ironing" => "warning", "folding" => "success"])

@push("styles")
    @vite("resources/css/pages/production-board.css")
@endpush

@section("content")
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <a href="{{ route('production.board', ['late' => 1]) }}" class="card text-decoration-none h-100 {{ $lateCount ? 'border-danger' : '' }}">
            <div class="card-body py-2">
                <div class="small text-muted">{{ __('app.late_orders') }}</div>
                <div class="fs-4 fw-bold {{ $lateCount ? 'text-danger' : '' }}">{{ $lateCount }}</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body py-2">
            <div class="small text-muted">{{ __('app.due_today_not_ready') }}</div>
            <div class="fs-4 fw-bold {{ $dueTodayCount ? 'text-warning' : '' }}">{{ $dueTodayCount }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body py-2">
            <div class="small text-muted">{{ __('app.ready_today') }}</div>
            <div class="fs-4 fw-bold text-success">{{ $readyToday }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body py-2">
            <div class="small text-muted">{{ __('app.production_queue') }}</div>
            <div class="fw-bold">{{ $num($queue['kg']) }} kg · {{ $queue['pieces'] }} {{ __('app.pieces_short') }}</div>
            @if($capacity['kg'] || $capacity['pieces'])
                @php($daysKg = $capacity['kg'] ? $queue['kg'] / $capacity['kg'] : 0)
                @php($daysPc = $capacity['pieces'] ? $queue['pieces'] / $capacity['pieces'] : 0)
                <div class="small text-muted">{{ __('app.queue_days', ['days' => $num(max($daysKg, $daysPc))]) }}</div>
            @endif
        </div></div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <form method="GET" class="d-flex gap-2 flex-wrap">
        <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('app.search_order_customer') }}">
        <div class="form-check align-self-center">
            <input class="form-check-input" type="checkbox" name="late" value="1" id="late" @checked($onlyLate) data-auto-submit>
            <label class="form-check-label small" for="late">{{ __('app.only_late_or_today') }}</label>
        </div>
        <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <select id="board-employee" class="form-select form-select-sm mw-200px" title="{{ __('app.operator') }}">
            <option value="">{{ __('app.operator') }}: —</option>
            @foreach($employees as $e)
                <option value="{{ $e->id }}" @selected($myEmployeeId === $e->id)>{{ $e->name }}</option>
            @endforeach
        </select>
        <a href="{{ route('production.loads.create', ['stage' => 'washing']) }}" class="btn btn-primary btn-sm"><i class="bi bi-arrow-repeat"></i> {{ __('app.new_load') }}</a>
        <a href="{{ route('production.loads.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list-ul"></i> {{ __('app.machine_loads') }}</a>
        @if(auth()->user()->hasRole(['admin', 'owner']))
            <a href="{{ route('production.report') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-graph-up"></i> {{ __('app.production_report') }}</a>
        @endif
    </div>
</div>

<div class="prod-board">
    @foreach($columns as $stage)
        @php($list = $orders->get($stage, collect()))
        <div class="prod-col">
            <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                <span class="fw-semibold"><span class="badge bg-{{ $stageColors[$stage] }} me-1">&nbsp;</span>{{ __('app.status_' . $stage) }}</span>
                <span class="small text-muted">{{ $list->count() }} · {{ $num($list->sum(fn ($o) => $o->weightKg())) }} kg</span>
            </div>
            @forelse($list as $order)
                @php($late = $order->isLate())
                @php($today = $order->isDueToday())
                @php($since = $order->stageSince())
                @php($running = $order->machineLoads->first())
                <div class="card prod-card mb-2 {{ $late ? 'is-late' : ($today ? 'is-today' : '') }} {{ $order->is_express ? 'is-express' : '' }}">
                    <div class="card-body p-2">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('orders.show', $order) }}" class="fw-semibold text-decoration-none">{{ $order->order_number }}</a>
                            <span>
                                @if($order->is_express)<span class="badge bg-danger">{{ __('app.express') }}</span>@endif
                                @if($order->rework_count)<span class="badge bg-warning text-dark" title="{{ __('app.rework') }}"><i class="bi bi-arrow-counterclockwise"></i> {{ $order->rework_count }}</span>@endif
                            </span>
                        </div>
                        <div class="small text-truncate">{{ $order->customer?->name }}</div>
                        <div class="small text-muted">
                            @if($order->weightKg() > 0){{ $num($order->weightKg()) }} kg @endif
                            @if($order->pieceCount() > 0)· {{ $order->pieceCount() }} {{ __('app.pieces_short') }}@endif
                        </div>
                        <div class="small {{ $late ? 'text-danger fw-semibold' : ($today ? 'text-warning fw-semibold' : 'text-muted') }}">
                            <i class="bi bi-calendar-event"></i> {{ $order->delivery_date?->format('d/m') ?? '—' }}
                            @if($late) · {{ __('app.late') }} @elseif($today) · {{ __('app.today') }} @endif
                            @if($since)<span class="text-muted fw-normal" title="{{ __('app.in_stage_since') }}"> · {{ $since->diffForHumans(null, true) }}</span>@endif
                        </div>
                        @if($order->care_flags)
                            <div class="mt-1">
                                @foreach($order->care_flags as $flag)
                                    <span class="badge text-bg-light border" title="{{ __('app.care_' . $flag) }}"><i class="bi bi-{{ $careIcons[$flag] ?? 'info-circle' }}"></i> {{ __('app.care_' . $flag) }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if($running)
                            <div class="small mt-1"><a href="{{ route('production.loads.show', $running) }}" class="badge bg-primary text-decoration-none"><i class="bi bi-arrow-repeat"></i> {{ $running->equipment?->name }} · {{ $running->code }}</a></div>
                        @endif

                        <div class="d-flex gap-1 mt-2">
                            @if($stage === 'folding' && $qcRequired)
                                <form method="POST" action="{{ route('production.qc', $order) }}" class="flex-grow-1 js-with-employee">
                                    @csrf <input type="hidden" name="result" value="pass"><input type="hidden" name="employee_id">
                                    <button class="btn btn-sm btn-success w-100"><i class="bi bi-patch-check"></i> {{ __('app.qc_pass') }}</button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="{{ __('app.qc_fail') }}" data-bs-toggle="modal" data-bs-target="#reworkModal"
                                    data-action="{{ route('production.qc', $order) }}" data-qc="1" data-order="{{ $order->order_number }}"><i class="bi bi-x-octagon"></i></button>
                            @elseif(in_array($stage, ['received', 'washing']) && !$running)
                                <form method="POST" action="{{ route('production.advance', $order) }}" class="flex-grow-1 js-with-employee">
                                    @csrf <input type="hidden" name="employee_id">
                                    <button class="btn btn-sm btn-outline-primary w-100">{{ __('app.status_' . \App\Models\Order::STATUSES[$order->statusRank() + 1]) }} <i class="bi bi-arrow-right"></i></button>
                                </form>
                                <a href="{{ route('production.loads.create', ['stage' => $stage === 'received' ? 'washing' : 'drying', 'orders' => [$order->id]]) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.new_load') }}"><i class="bi bi-arrow-repeat"></i></a>
                            @else
                                <form method="POST" action="{{ route('production.advance', $order) }}" class="flex-grow-1 js-with-employee">
                                    @csrf <input type="hidden" name="employee_id">
                                    <button class="btn btn-sm btn-outline-primary w-100">{{ __('app.status_' . \App\Models\Order::STATUSES[$order->statusRank() + 1]) }} <i class="bi bi-arrow-right"></i></button>
                                </form>
                            @endif
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light border" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#rescheduleModal" data-action="{{ route('production.reschedule', $order) }}" data-order="{{ $order->order_number }}" data-date="{{ $order->delivery_date?->toDateString() }}"><i class="bi bi-calendar-x me-1"></i> {{ __('app.reschedule') }}</a></li>
                                    @if($stage !== 'received')
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#reworkModal" data-action="{{ route('production.rework', $order) }}" data-order="{{ $order->order_number }}"><i class="bi bi-arrow-counterclockwise me-1"></i> {{ __('app.rework') }}</a></li>
                                    @endif
                                    <li><a class="dropdown-item" href="{{ route('orders.label', $order) }}" target="_blank"><i class="bi bi-tag me-1"></i> {{ __('app.label') }}</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center text-muted small py-4">{{ __('app.no_orders_in_stage') }}</div>
            @endforelse
        </div>
    @endforeach
</div>

<div class="modal fade" id="rescheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">{{ __('app.reschedule') }} <span class="js-order"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">{{ __('app.new_delivery_date') }}</label>
                <input type="date" name="delivery_date" class="form-control mb-2" min="{{ today()->toDateString() }}" required>
                <label class="form-label">{{ __('app.delay_reason') }}</label>
                <input type="text" name="reason" class="form-control mb-2" maxlength="255" required>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="notify" value="1" id="notify" checked>
                    <label class="form-check-label" for="notify">{{ __('app.notify_customer_whatsapp') }}</label>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">{{ __('app.save') }}</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="reworkModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="result" value="fail" disabled>
            <div class="modal-header"><h5 class="modal-title">{{ __('app.rework') }} <span class="js-order"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">{{ __('app.rework_help') }}</p>
                <label class="form-label">{{ __('app.reason') }}</label>
                <input type="text" name="reason" class="form-control" maxlength="255" required list="rework-reasons">
                <datalist id="rework-reasons">
                    @foreach(['rework_reason_stain', 'rework_reason_smell', 'rework_reason_wrinkled', 'rework_reason_damp', 'rework_reason_color'] as $r)
                        <option value="{{ __('app.' . $r) }}">
                    @endforeach
                </datalist>
            </div>
            <div class="modal-footer"><button class="btn btn-warning">{{ __('app.rework_send_back') }}</button></div>
        </form>
    </div>
</div>
@endsection

@push("scripts")
    @vite("resources/js/pages/production-board.js")
@endpush
