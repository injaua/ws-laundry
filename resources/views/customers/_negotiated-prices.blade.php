{{-- Prices negotiated with this customer (not for cashiers; the accountant only reads them). --}}
@if(!auth()->user()->isCashier())
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-tags me-1"></i> {{ __('app.negotiated_prices') }}</div>
    <div class="card-body">
        @if((float) $customer->discount_percent > 0)
            <div class="alert alert-info py-2 small mb-3">
                {{ __('app.customer_discount_active', ["percent" => qty((float) $customer->discount_percent)]) }}
            </div>
        @endif
        @if($customer->prices->isNotEmpty())
        <table class="table table-sm mb-3">
            <tbody>
                @foreach($customer->prices as $price)
                <tr>
                    <td class="small">{{ $price->item()?->name ?? "-" }}</td>
                    <td class="small text-end text-muted text-decoration-line-through">{{ num($price->catalogPrice()) }}</td>
                    <td class="small text-end fw-semibold">{{ money((float) $price->price) }}</td>
                    <td class="text-end">
                        @if(auth()->user()->isManager())
                        <form action="{{ route("customers.prices.destroy", [$customer, $price]) }}" method="POST" data-confirm="{{ __('app.confirm_delete') }}">
                            @csrf @method("DELETE")
                            <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
            <p class="small text-muted">{{ __('app.no_negotiated_prices') }}</p>
        @endif
        @if(auth()->user()->isManager())
        <form action="{{ route("customers.prices.store", $customer) }}" method="POST" class="row g-2">
            @csrf
            <div class="col-7">
                <select name="item" class="form-select form-select-sm @error("item") is-invalid @enderror" required>
                    <option value="">{{ __('app.select_service') }}</option>
                    <optgroup label="{{ __('app.services') }}">
                        @foreach($services as $service)
                            <option value="s{{ $service->id }}">{{ $service->name }} · {{ num((float) $service->price) }}</option>
                        @endforeach
                    </optgroup>
                    @if($packages->isNotEmpty())
                    <optgroup label="{{ __('app.packages') }}">
                        @foreach($packages as $package)
                            <option value="p{{ $package->id }}">{{ $package->name }} · {{ num((float) $package->price) }}</option>
                        @endforeach
                    </optgroup>
                    @endif
                </select>
            </div>
            <div class="col-5">
                <div class="input-group input-group-sm">
                    <input type="number" name="price" min="0" step="1" class="form-control @error("price") is-invalid @enderror" placeholder="{{ __('app.price') }}" required>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus"></i></button>
                </div>
            </div>
            @error("item") <div class="col-12 text-danger small">{{ $message }}</div> @enderror
            @error("price") <div class="col-12 text-danger small">{{ $message }}</div> @enderror
        </form>
        @endif
    </div>
</div>
@endif
