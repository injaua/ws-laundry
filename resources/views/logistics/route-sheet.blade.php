@extends("layouts.document")
@section("title", __('app.route_sheet') . " " . $date->format(date_fmt()))
@section("toolbar")
    <a href="{{ route("logistics.index", ["date" => $date->toDateString()]) }}" class="btn btn-sm btn-outline-secondary">{{ __('app.back') }}</a>
@endsection
@section("doc_type", __('app.route_sheet'))
@section("doc_meta")
    <div>{{ __('app.date') }}: <strong>{{ $date->format(date_fmt()) }}</strong></div>
    <div>{{ __('app.courier') }}: <strong>{{ $courier?->name ?? __('app.all_couriers') }}</strong></div>
@endsection

@section("content")
<table class="lines">
    <thead>
        <tr>
            <th class="w-4pct">#</th>
            <th class="w-10pct">{{ __('app.type') }}</th>
            <th>{{ __('app.customer') }} / {{ __('app.address') }}</th>
            <th class="w-12pct">{{ __('app.time_window') }}</th>
            <th class="r w-14pct">{{ __('app.to_collect') }}</th>
            <th class="w-16pct">{{ __('app.signature') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($jobs as $i => $job)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ __('app.job_type_' . $job->type) }}</td>
            <td>
                <strong>{{ $job->customer?->name ?? $job->contact_name }}</strong> · {{ $job->contact_phone }}<br>
                {{ $job->address }}@if($job->zone) ({{ $job->zone->name }})@endif
                @if($job->address_reference)<br><small>{{ $job->address_reference }}</small>@endif
                @if($job->order)<br><small>{{ $job->order->order_number }}</small>@endif
                @if($job->notes)<br><small><em>{{ $job->notes }}</em></small>@endif
                @if(!$courier && $job->courier)<br><small>{{ __('app.courier') }}: {{ $job->courier->name }}</small>@endif
            </td>
            <td>{{ __('app.window_' . $job->time_window) }}</td>
            <td class="r">{{ $job->amountToCollect() > 0 ? money($job->amountToCollect()) : "—" }}</td>
            <td></td>
        </tr>
        @empty
        <tr><td class="text-center" colspan="6">{{ __('app.no_trips') }}</td></tr>
        @endforelse
    </tbody>
</table>
@if($jobs->isNotEmpty())
<p class="mt-12px"><strong>{{ __('app.total_to_collect') }}:</strong> {{ money($jobs->sum(fn ($j) => $j->amountToCollect())) }}</p>
@endif
@endsection
