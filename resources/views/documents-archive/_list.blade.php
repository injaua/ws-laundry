{{-- Documents as a table (layout=list). --}}
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 doc-table">
                            <thead>
                                <tr>
                                    <th>{{ __('app.doc_name') }}</th>
                                    <th>{{ __('app.doc_folder') }}</th>
                                    <th>{{ __('app.category') }}</th>
                                    <th class="text-end">{{ __('app.doc_size') }}</th>
                                    <th>{{ __('app.date') }}</th>
                                    <th>{{ __('app.doc_expires_at') }}</th>
                                    <th class="text-end">{{ __('app.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($documents as $document)
                                <tr>
                                    <td class="min-w-0">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="doc-file-icon sm tint-{{ $document->color() }}"><i class="bi {{ $document->icon() }}"></i></span>
                                            <div class="min-w-0">
                                                <a href="{{ route("documents-archive.preview", $document) }}" target="_blank" rel="noopener" class="fw-medium text-reset text-decoration-none d-block text-truncate doc-title-link" title="{{ $document->title }}">@if($document->is_private)<i class="bi bi-lock-fill text-muted me-1" title="{{ __('app.doc_private') }}"></i>@endif{{ $document->title }}</a>
                                                <div class="small text-muted text-truncate">{{ $document->original_name }}@if($document->reference) · {{ $document->reference }}@endif</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="small">{{ $document->folder?->name ?? "—" }}</td>
                                    <td>@if($document->category)<span class="badge bg-{{ $document->category->color }}">{{ $document->category->name }}</span>@else<span class="text-muted small">—</span>@endif</td>
                                    <td class="small text-end text-nowrap">{{ \App\Models\Document::formatSize($document->size) }}</td>
                                    <td class="small text-nowrap">{{ ($document->document_date ?? $document->created_at)->format(date_fmt()) }}</td>
                                    <td class="small text-nowrap">@include("documents-archive._expiry", ["document" => $document])</td>
                                    <td class="text-end text-nowrap">@include("documents-archive._actions", ["document" => $document])</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
