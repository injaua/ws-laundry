{{-- Piece-by-piece tracking --}}
{{-- $pieces, $readyCount, $stageCounts, $stageColors, $canManage: App\View\Composers\PieceTrackingComposer --}}
<div class="card mb-3" id="pieces">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span><i class="bi bi-upc-scan me-1"></i> {{ __('app.piece_tracking') }} ({{ $pieces->count() }})</span>
        @if($pieces->isNotEmpty())
        <div class="btn-group btn-group-sm">
            <a href="{{ route("orders.pieces.labels", $order) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-tags"></i> {{ __('app.piece_labels') }} A4</a>
            <a href="{{ route("orders.pieces.labels", [$order, "type" => "thermal"]) }}" target="_blank" class="btn btn-outline-secondary">{{ __('app.thermal_label') }}</a>
        </div>
        @endif
    </div>
    <div class="card-body">
        @if($pieces->isNotEmpty())
            <div class="d-flex flex-wrap gap-1 justify-content-between small mb-1">
                <span>{{ __('app.pieces_ready_of', ["ready" => $readyCount, "total" => $pieces->count()]) }}</span>
                <span>
                    @foreach($stageCounts as $stage => $count)
                        <span class="badge bg-{{ $stageColors[$stage] ?? "secondary" }}">{{ __('app.status_' . $stage) }}: {{ $count }}</span>
                    @endforeach
                </span>
            </div>
            <div class="progress mb-3 h-8px" role="progressbar" aria-label="{{ __('app.pieces_ready_of', ["ready" => $readyCount, "total" => $pieces->count()]) }}" aria-valuenow="{{ $readyCount }}" aria-valuemin="0" aria-valuemax="{{ $pieces->count() }}">
                <div class="progress-bar bg-success" style="width: {{ round($readyCount / max(1, $pieces->count()) * 100) }}%"></div>
            </div>

            <div class="table-responsive">
            <table class="table table-sm align-middle mb-3">
                <thead>
                    <tr>
                        <th>{{ __('app.piece_code') }}</th>
                        <th>{{ __('app.piece') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($pieces as $piece)
                    <tr>
                        <td class="font-monospace small text-nowrap">{{ $piece->code }}</td>
                        <td>
                            {{ $piece->description }}
                            @if($piece->color || $piece->brand)<span class="text-muted small">· {{ trim($piece->color . " " . $piece->brand) }}</span>@endif
                            @if($piece->intake_notes)<div class="small text-info-emphasis"><i class="bi bi-info-circle"></i> {{ $piece->intake_notes }}</div>@endif
                            @if($piece->issue)<div class="small text-danger"><i class="bi bi-exclamation-triangle"></i> {{ $piece->issue }}</div>@endif
                        </td>
                        <td class="text-nowrap">
                            <span class="badge bg-{{ $stageColors[$piece->status] ?? "secondary" }}">{{ __('app.status_' . $piece->status) }}</span>
                            <div class="small text-muted">{{ $piece->status_at?->format("d/m H:i") }}</div>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route("orders.pieces.labels", [$order, "piece" => $piece->id, "type" => "thermal"]) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-1" title="{{ __('app.print_label') }}" aria-label="{{ __('app.print_label') }} {{ $piece->code }}"><i class="bi bi-tag"></i></a>
                            <button class="btn btn-sm btn-outline-primary py-0 px-1" type="button" data-bs-toggle="collapse" data-bs-target="#piece{{ $piece->id }}" aria-expanded="false" aria-controls="piece{{ $piece->id }}" title="{{ __('app.edit') }}" aria-label="{{ __('app.edit') }} {{ $piece->code }}"><i class="bi bi-pencil"></i></button>
                            @if($canManage && $order->status !== "completed")
                            <form action="{{ route("orders.pieces.destroy", [$order, $piece]) }}" method="POST" class="d-inline" data-confirm="{{ __('app.piece_delete_confirm', ['code' => $piece->code]) }}">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger py-0 px-1" title="{{ __('app.delete') }}" aria-label="{{ __('app.delete') }} {{ $piece->code }}"><i class="bi bi-trash"></i></button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    <tr class="collapse" id="piece{{ $piece->id }}">
                        <td colspan="4" class="bg-body-tertiary">
                            <form action="{{ route("orders.pieces.update", [$order, $piece]) }}" method="POST" class="row g-2 p-2">
                                @csrf @method("PUT")
                                <div class="col-md-5">
                                    <label class="form-label small mb-0" for="pd{{ $piece->id }}">{{ __('app.description') }}</label>
                                    <input type="text" id="pd{{ $piece->id }}" name="description" class="form-control form-control-sm" value="{{ $piece->description }}" maxlength="120" required>
                                </div>
                                <div class="col-md-3 col-6">
                                    <label class="form-label small mb-0" for="pc{{ $piece->id }}">{{ __('app.color') }}</label>
                                    <input type="text" id="pc{{ $piece->id }}" name="color" class="form-control form-control-sm" value="{{ $piece->color }}" maxlength="40">
                                </div>
                                <div class="col-md-4 col-6">
                                    <label class="form-label small mb-0" for="pb{{ $piece->id }}">{{ __('app.piece_brand') }}</label>
                                    <input type="text" id="pb{{ $piece->id }}" name="brand" class="form-control form-control-sm" value="{{ $piece->brand }}" maxlength="60">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small mb-0" for="pn{{ $piece->id }}">{{ __('app.piece_intake_notes') }}</label>
                                    <input type="text" id="pn{{ $piece->id }}" name="intake_notes" class="form-control form-control-sm" value="{{ $piece->intake_notes }}" maxlength="1000" placeholder="{{ __('app.piece_intake_placeholder') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small mb-0" for="pi{{ $piece->id }}">{{ __('app.piece_issue') }}</label>
                                    <input type="text" id="pi{{ $piece->id }}" name="issue" class="form-control form-control-sm" value="{{ $piece->issue }}" maxlength="255" placeholder="{{ __('app.piece_issue_placeholder') }}">
                                </div>
                                @if($order->status !== "completed")
                                <div class="col-md-6">
                                    <label class="form-label small mb-0" for="ps{{ $piece->id }}">{{ __('app.status') }}</label>
                                    <select id="ps{{ $piece->id }}" name="status" class="form-select form-select-sm">
                                        @foreach(array_slice(\App\Models\Order::STATUSES, $piece->statusRank()) as $st)
                                            <option value="{{ $st }}" @selected($piece->status === $st)>{{ __('app.status_' . $st) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif
                                <div class="col-md-6 d-flex align-items-end">
                                    <button type="submit" class="btn btn-sm btn-primary">{{ __('app.save') }}</button>
                                </div>
                                <div class="col-12">
                                    <div class="small fw-semibold">{{ __('app.piece_history') }}</div>
                                    <ul class="small text-muted mb-0 ps-3">
                                        @foreach($piece->events as $event)
                                            <li>{{ $event->created_at->format("d/m H:i") }} — <strong>{{ __('app.status_' . $event->to_status) }}</strong>{{ $event->user ? " · " . $event->user->name : "" }}{{ $event->note ? " (" . $event->note . ")" : "" }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        @else
            <p class="text-muted small">{{ __('app.no_pieces_help') }}</p>
        @endif

        <form action="{{ route("orders.pieces.store", $order) }}" method="POST" class="row g-2 align-items-end border-top pt-3">
            @csrf
            <div class="col-md-4">
                <label class="form-label small mb-0" for="newPieceItem">{{ __('app.service') }}</label>
                <select id="newPieceItem" name="order_item_id" class="form-select form-select-sm">
                    @foreach($order->items as $item)
                        <option value="{{ $item->id }}">{{ $item->item_name }}</option>
                    @endforeach
                    <option value="">{{ __('app.piece_no_item') }}</option>
                </select>
            </div>
            <div class="col-md-2 col-4">
                <label class="form-label small mb-0" for="newPieceCount">{{ __('app.quantity') }}</label>
                <input type="number" id="newPieceCount" name="count" class="form-control form-control-sm" min="1" max="{{ \App\Services\PieceTracker::MAX_BATCH }}" value="1" required>
            </div>
            <div class="col-md-4 col-8">
                <label class="form-label small mb-0" for="newPieceDesc">{{ __('app.description') }}</label>
                <input type="text" id="newPieceDesc" name="description" class="form-control form-control-sm" maxlength="120" placeholder="{{ __('app.piece_description_placeholder') }}">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-plus-lg"></i> {{ __('app.add_pieces') }}</button>
            </div>
        </form>
    </div>
</div>
