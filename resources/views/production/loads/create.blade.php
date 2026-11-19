@extends("layouts.admin")
@section("title", __('app.new_load'))
@section("page_title", $stage === 'washing' ? __('app.new_wash_load') : __('app.new_dry_load'))
@section("page_subtitle", __('app.new_load_subtitle'))

@php($num = fn ($n) => qty($n, 2))

@section("content")
<form method="POST" action="{{ route('production.loads.store') }}" id="load-form">
    @csrf
    <input type="hidden" name="stage" value="{{ $stage }}">
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="btn-group w-100 mb-3">
                        <a href="{{ route('production.loads.create', ['stage' => 'washing']) }}" class="btn btn-sm {{ $stage === 'washing' ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('app.status_washing') }}</a>
                        <a href="{{ route('production.loads.create', ['stage' => 'drying']) }}" class="btn btn-sm {{ $stage === 'drying' ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('app.status_drying') }}</a>
                    </div>
                    <label class="form-label">{{ __('app.machine') }} <span class="text-danger">*</span></label>
                    <select name="equipment_id" id="machine" class="form-select @error('equipment_id') is-invalid @enderror" required>
                        <option value="">—</option>
                        @foreach($machines as $m)
                            <option value="{{ $m->id }}" data-capacity="{{ (float) $m->capacity_kg }}" @selected(old('equipment_id') == $m->id)>{{ $m->name }}@if($m->capacity_kg) ({{ $num($m->capacity_kg) }} kg)@endif</option>
                        @endforeach
                    </select>
                    @error('equipment_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @if($machines->isEmpty())<div class="small text-danger mt-1">{{ __('app.no_free_machines') }}</div>@endif

                    <label class="form-label mt-3">{{ __('app.program') }}</label>
                    <input type="text" name="program" class="form-control" maxlength="60" value="{{ old('program') }}" list="programs" placeholder="{{ __('app.program_placeholder') }}">
                    <datalist id="programs">
                        @foreach(['Algodão 40°', 'Algodão 60°', 'Sintéticos 30°', 'Delicados 20°', 'Lã', 'Brancos 90°', 'Secagem normal', 'Secagem baixa temp.'] as $p)<option value="{{ $p }}">@endforeach
                    </datalist>

                    <label class="form-label mt-3">{{ __('app.operator') }}</label>
                    <select name="employee_id" class="form-select">
                        <option value="">—</option>
                        @foreach($employees as $e)
                            <option value="{{ $e->id }}" @selected(old('employee_id', $myEmployeeId) == $e->id)>{{ $e->name }}</option>
                        @endforeach
                    </select>

                    <label class="form-label mt-3">{{ __('app.notes') }}</label>
                    <textarea name="notes" rows="2" class="form-control" maxlength="1000">{{ old('notes') }}</textarea>

                    <div class="mt-3 p-2 rounded bg-light">
                        <div class="d-flex justify-content-between"><span>{{ __('app.total') }}</span><strong><span id="total-kg">0</span> kg</strong></div>
                        <div class="progress mt-1 h-8px"><div id="cap-bar" class="progress-bar w-00pct"></div></div>
                        <div class="small text-muted mt-1" id="cap-text"></div>
                    </div>

                    <div id="overload" class="mt-2 {{ $errors->has('overload_note') ? '' : 'd-none' }}">
                        <label class="form-label small text-danger">{{ __('app.overload_justify') }}</label>
                        <input type="text" name="overload_note" class="form-control form-control-sm @error('overload_note') is-invalid @enderror" maxlength="255" value="{{ old('overload_note') }}">
                        @error('overload_note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    @error('orders') <div class="alert alert-danger small mt-2 mb-0">{{ $message }}</div> @enderror
                    <button class="btn btn-primary w-100 mt-3"><i class="bi bi-play-circle"></i> {{ __('app.start_load') }}</button>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">{{ __('app.orders_for_load') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th></th><th>{{ __('app.order') }}</th><th>{{ __('app.customer') }}</th><th>{{ __('app.status') }}</th><th>{{ __('app.delivery_date') }}</th><th class="w-110px">kg</th></tr></thead>
                        <tbody>
                        @forelse($orders as $order)
                            @php($kg = $order->weightKg())
                            @php($checked = in_array($order->id, $selected, true) || old('orders.' . $order->id))
                            <tr class="{{ $order->isLate() ? 'table-danger' : '' }}">
                                <td><input type="checkbox" class="form-check-input js-pick" data-id="{{ $order->id }}" @checked($checked)></td>
                                <td><a href="{{ route('orders.show', $order) }}" target="_blank">{{ $order->order_number }}</a>
                                    @if($order->is_express)<span class="badge bg-danger">{{ __('app.express') }}</span>@endif
                                    @if($order->care_flags)<i class="bi bi-exclamation-diamond text-warning" title="{{ collect($order->care_flags)->map(fn ($f) => __('app.care_' . $f))->join(', ') }}"></i>@endif
                                </td>
                                <td class="small">{{ $order->customer?->name }}</td>
                                <td class="small">{{ __('app.status_' . $order->status) }}</td>
                                <td class="small">{{ $order->delivery_date?->format('d/m') }}</td>
                                <td><input type="number" step="0.1" min="0" name="orders[{{ $order->id }}]" class="form-control form-control-sm js-kg" data-id="{{ $order->id }}"
                                    value="{{ old('orders.' . $order->id, $checked ? $num($kg ?: 1) : '') }}" data-default="{{ $num($kg ?: 1) }}" placeholder="{{ $num($kg) }}"></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">{{ __('app.no_orders_for_load') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push("scripts")
    @vite("resources/js/pages/machine-load-form.js")
@endpush
