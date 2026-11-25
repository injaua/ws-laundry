{{-- Received / sent box of the exchange: one row per document sent (view=received|sent). --}}
@php($received = $filters["view"] === "received")
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 doc-table doc-exchange">
                            <thead>
                                <tr>
                                    <th>{{ __('app.doc_name') }}</th>
                                    <th>{{ $received ? __('app.doc_from') : __('app.doc_to') }}</th>
                                    <th>{{ __('app.doc_message') }}</th>
                                    <th>{{ __('app.date') }}</th>
                                    <th>{{ __('app.status') }}</th>
                                    <th class="text-end">{{ __('app.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($shares as $share)
                                @php($document = $share->document)
                                @php($person = $received ? $share->sender : $share->recipient)
                                <tr class="{{ $received && !$share->isRead() ? "doc-unread" : "" }}">
                                    <td class="min-w-0">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="doc-file-icon sm tint-{{ $document->color() }}"><i class="bi {{ $document->icon() }}"></i></span>
                                            <div class="min-w-0">
                                                <a href="{{ route("documents-archive.preview", $document) }}" target="_blank" rel="noopener" class="fw-medium text-reset text-decoration-none d-block text-truncate doc-title-link" title="{{ $document->title }}">
                                                    @if($document->is_private)<i class="bi bi-lock-fill text-muted me-1" title="{{ __('app.doc_private') }}"></i>@endif{{ $document->title }}
                                                </a>
                                                <div class="small text-muted text-truncate">{{ $document->original_name }} · {{ \App\Models\Document::formatSize($document->size) }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="small">
                                        <div class="fw-medium">{{ $person?->name ?? "—" }}</div>
                                        @if($person)<div class="text-muted">{{ __('app.role_' . $person->role) }}</div>@endif
                                    </td>
                                    <td class="small doc-share-message">{{ $share->message ?: "—" }}</td>
                                    <td class="small text-nowrap">{{ $share->created_at->format(date_fmt() . " H:i") }}</td>
                                    <td class="small text-nowrap">
                                        @if($share->isRead())
                                            <span class="text-success" title="{{ $share->read_at->format(date_fmt() . " H:i") }}"><i class="bi bi-check2-all"></i> {{ __('app.doc_read') }}</span>
                                        @else
                                            <span class="badge bg-primary">{{ $received ? __('app.doc_new') : __('app.doc_not_read') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route("documents-archive.download", $document) }}" download data-no-nav class="btn btn-sm btn-outline-secondary" title="{{ __('app.download') }}" aria-label="{{ __('app.download') }}">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <a href="{{ route("documents-archive.edit", $document) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.doc_details') }}" aria-label="{{ __('app.doc_details') }}">
                                            <i class="bi bi-info-circle"></i>
                                        </a>
                                        @if($received && $share->sender && $recipients->contains("id", $share->sender_id))
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#exchangeModal"
                                                    data-doc-reply="{{ $share->sender_id }}" title="{{ __('app.doc_reply') }}" aria-label="{{ __('app.doc_reply') }}">
                                                <i class="bi bi-reply"></i>
                                            </button>
                                        @endif
                                        @if($received && !$share->isRead())
                                            <form action="{{ route("documents-archive.shares.read", $share) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-success" title="{{ __('app.doc_mark_read') }}" aria-label="{{ __('app.doc_mark_read') }}"><i class="bi bi-check2"></i></button>
                                            </form>
                                        @endif
                                        <form action="{{ route("documents-archive.shares.destroy", $share) }}" method="POST" class="d-inline"
                                              data-confirm="{{ $received ? ($document->is_private ? __('app.doc_remove_received_private_confirm') : __('app.doc_remove_received_confirm')) : __('app.doc_cancel_send_confirm', ['name' => $person?->name ?? '—']) }}">
                                            @csrf @method("DELETE")
                                            <button class="btn btn-sm btn-outline-danger" title="{{ $received ? __('app.doc_remove_received') : __('app.doc_cancel_send') }}" aria-label="{{ $received ? __('app.doc_remove_received') : __('app.doc_cancel_send') }}"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
