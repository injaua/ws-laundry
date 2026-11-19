@extends("layouts.admin")
@section("title", $load->code)
@section("page_title", __('app.load') . " " . $load->code)
@section("page_subtitle", $load->equipment?->name)

@php($num = fn ($n) => qty($n, 3))

@section("content")
<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
    <a href="{{ route('production.loads.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> {{ __('app.machine_loads') }}</a>
    @if($load->status === 'in_progress')
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('production.loads.cancel', $load) }}" data-confirm="{{ __('app.load_cancel_confirm') }}">
                @csrf <button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> {{ __('app.cancel') }}</button>
            </form>
            <form method="POST" action="{{ route('production.loads.finish', $load) }}">
                @csrf <button class="btn btn-success"><i class="bi bi-check2-circle"></i> {{ __('app.finish_load') }}</button>
            </form>
        </div>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5">{{ __('app.status') }}</dt><dd class="col-7"><span class="badge bg-{{ $load->statusColor() }}">{{ __('app.load_status_' . $load->status) }}</span></dd>
                    <dt class="col-5">{{ __('app.machine') }}</dt><dd class="col-7">@if($load->equipment)<a href="{{ route('resources.equipments.show', $load->equipment) }}">{{ $load->equipment->name }}</a>@else — @endif</dd>
                    <dt class="col-5">{{ __('app.stage') }}</dt><dd class="col-7">{{ __('app.status_' . $load->stage) }}</dd>
                    <dt class="col-5">{{ __('app.program') }}</dt><dd class="col-7">{{ $load->program ?: '—' }}</dd>
                    <dt class="col-5">{{ __('app.weight') }}</dt><dd class="col-7">{{ $num($load->weight_kg) }} kg @if($load->utilization() !== null)({{ $load->utilization() }}%)@endif</dd>
                    <dt class="col-5">{{ __('app.operator') }}</dt><dd class="col-7">{{ $load->employee?->name ?? '—' }}</dd>
                    <dt class="col-5">{{ __('app.started_at') }}</dt><dd class="col-7">{{ $load->started_at?->format(datetime_fmt()) }} · {{ $load->user?->name }}</dd>
                    <dt class="col-5">{{ __('app.finished_at') }}</dt><dd class="col-7">{{ $load->finished_at?->format(datetime_fmt()) ?? '—' }}@if($load->durationMinutes() !== null) · {{ $load->durationMinutes() }} min @endif</dd>
                    @if($load->overload_note)
                        <dt class="col-5 text-danger">{{ __('app.overload') }}</dt><dd class="col-7">{{ $load->overload_note }}</dd>
                    @endif
                    @if($load->notes)
                        <dt class="col-5">{{ __('app.notes') }}</dt><dd class="col-7">{{ $load->notes }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">{{ __('app.orders') }}</div>
            <table class="table table-sm mb-0 align-middle">
                <thead><tr><th>{{ __('app.order') }}</th><th>{{ __('app.customer') }}</th><th>{{ __('app.status') }}</th><th class="text-end">kg</th></tr></thead>
                <tbody>
                @foreach($load->orders as $order)
                    <tr>
                        <td><a href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a></td>
                        <td>{{ $order->customer?->name }}</td>
                        <td>{{ $order->status_label }}</td>
                        <td class="text-end">{{ $num($order->pivot->weight_kg) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($movements->isNotEmpty())
        <div class="card">
            <div class="card-header">{{ __('app.load_consumption') }}</div>
            <ul class="list-group list-group-flush">
                @foreach($movements as $m)
                    <li class="list-group-item d-flex justify-content-between small"><span>{{ $m->item?->name }}</span><span>{{ $num($m->quantity) }} {{ $m->item?->unit }}</span></li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
@endsection
