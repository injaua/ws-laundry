@extends("layouts.admin")
@section("title", __('app.customers'))
@section("page_title", __('app.customers'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_customers_label', ['count' => $customers->total()]) }}</span>
    @unless(auth()->user()->isAccountant())
    <a href="{{ route("customers.create") }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.add_customer') }}
    </a>
    @endunless
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("customers.index") }}" method="GET" class="row g-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="{{ __('app.search_name_phone') }}" value="{{ request("search") }}">
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
                    <a href="{{ route("customers.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('app.customer_name') }}</th>
                        <th>{{ __('app.phone') }}</th>
                        <th>{{ __('app.address') }}</th>
                        <th>{{ __('app.total_orders') }}</th>
                        <th>{{ __('app.joined') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                    <tr>
                        <td>{{ $loop->iteration + ($customers->currentPage() - 1) * $customers->perPage() }}</td>
                        <td class="fw-medium"><a href="{{ route("customers.show", $customer) }}" class="text-decoration-none">{{ $customer->name }}</a>@if($customer->isBusiness()) <span class="badge bg-dark" title="{{ $customer->legal_name }}"><i class="bi bi-building"></i> {{ __('app.customer_type_business') }}</span>@endif</td>
                        <td>{{ $customer->phone }}</td>
                        <td class="text-muted small">{{ Str::limit($customer->address, 40) ?? "-" }}</td>
                        <td><span class="badge bg-secondary">{{ $customer->orders_count ?? 0 }}</span></td>
                        <td class="small text-muted">{{ $customer->created_at->format(date_fmt()) }}</td>
                        <td class="text-center">
                            @if(auth()->user()->isManager())
                            <a href="{{ route("customers.edit", $customer) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route("customers.destroy", $customer) }}" method="POST" class="d-inline" data-confirm="{{ __('app.confirm_delete') }}">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-people fs-1 d-block mb-2"></i>
                            {{ __('app.no_customers') }}@unless(auth()->user()->isAccountant()) <a href="{{ route("customers.create") }}">{{ __('app.no_customers_yet') }}</a>@endunless.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $customers->links() }}
</div>
@endsection
