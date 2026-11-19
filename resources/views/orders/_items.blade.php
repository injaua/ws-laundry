{{-- Order lines: services / packages, quantities, prices, totals. --}}
{{-- Service items --}}
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-box-seam me-1"></i> {{ __('app.service_items') }}</div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>{{ __('app.service') }}</th>
                    <th>{{ __('app.type') }}</th>
                    <th>{{ __('app.weight') }} / {{ __('app.quantity') }}</th>
                    <th>{{ __('app.unit_price') }}</th>
                    <th>{{ __('app.subtotal') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->item_name }}</td>
                    <td><span class="badge bg-{{ ["by_weight" => "info", "by_piece" => "warning", "by_package" => "success"][$item->type] ?? "secondary" }}">{{ $item->type === 'by_weight' ? __('app.by_weight') : ($item->type === 'by_package' ? __('app.by_package') : __('app.by_piece')) }}</span></td>
                    <td>
                        @if($item->type == "by_weight")
                            {{ $item->weight }} kg
                        @elseif($item->type == "by_package")
                            {{ $item->amount }} × {{ round($item->weight / max(1, $item->amount), 2) }} kg
                        @else
                            {{ $item->amount }} {{ __('app.pieces') }}
                        @endif
                    </td>
                    <td>
                        {{ money($item->unit_price) }}
                        @if($item->list_price !== null && (float) $item->list_price > (float) $item->unit_price)
                            <div class="small text-muted"><del>{{ money($item->list_price) }}</del></div>
                        @endif
                        @if($item->promotion)
                            <span class="badge bg-danger-subtle text-danger-emphasis"><i class="bi bi-megaphone"></i> {{ $item->promotion->name }}</span>
                        @endif
                    </td>
                    <td>{{ money($item->subtotal) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                @if((float) $order->express_fee > 0)
                <tr>
                    <td colspan="4" class="text-end"><i class="bi bi-lightning-charge text-warning"></i> {{ __('app.express_fee') }}:</td>
                    <td>{{ money($order->express_fee) }}</td>
                </tr>
                @endif
                @if((float) $order->delivery_fee > 0)
                <tr>
                    <td colspan="4" class="text-end"><i class="bi bi-truck"></i> {{ __('app.delivery_fee') }}:</td>
                    <td>{{ money($order->delivery_fee) }}</td>
                </tr>
                @endif
                @if((float) ($order->vat_amount ?? 0) > 0)
                <tr>
                    <td colspan="4" class="text-end">{{ __('app.subtotal') }}:</td>
                    <td>{{ money($order->subtotal ?? $order->total_price) }}</td>
                </tr>
                <tr>
                    <td colspan="4" class="text-end">{{ __('app.vat') }} {{ qty((float) $order->vat_rate) }}%:</td>
                    <td>{{ money($order->vat_amount) }}</td>
                </tr>
                @endif
                <tr class="table-active">
                    <th colspan="4" class="text-end">{{ __('app.total') }}:</th>
                    <th class="fw-bold">{{ money($order->total_price) }}</th>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>
</div>
