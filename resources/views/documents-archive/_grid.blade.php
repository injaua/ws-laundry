{{-- Documents as cards (layout=grid). --}}
                    <div class="doc-grid">
                        @foreach($documents as $document)
                        <div class="doc-card">
                            <div class="doc-card-top">
                                <span class="doc-file-icon tint-{{ $document->color() }}"><i class="bi {{ $document->icon() }}"></i></span>
                                <span class="doc-ext">@if($document->is_private)<i class="bi bi-lock-fill me-1" title="{{ __('app.doc_private') }}"></i>@endif{{ strtoupper($document->extension) }}</span>
                            </div>
                            <a href="{{ route("documents-archive.preview", $document) }}" target="_blank" rel="noopener" class="doc-card-title" title="{{ $document->title }}">{{ $document->title }}</a>
                            <div class="doc-card-meta">
                                {{ \App\Models\Document::formatSize($document->size) }} · {{ ($document->document_date ?? $document->created_at)->format(date_fmt()) }}
                            </div>
                            @if($document->reference)<div class="doc-card-meta text-truncate">{{ __('app.reference') }}: {{ $document->reference }}</div>@endif
                            <div class="doc-card-badges">
                                @if($document->folder)<span class="badge text-bg-light border"><i class="bi bi-folder-fill text-{{ $document->folder->color }}"></i> {{ $document->folder->name }}</span>@endif
                                @if($document->category)<span class="badge bg-{{ $document->category->color }}">{{ $document->category->name }}</span>@endif
                                @include("documents-archive._expiry", ["document" => $document, "badge" => true])
                                @foreach(array_slice($document->tags ?? [], 0, 3) as $tag)
                                    <a href="{{ $navTo(["tag" => $tag]) }}" class="badge doc-tag">#{{ $tag }}</a>
                                @endforeach
                            </div>
                            <div class="doc-card-actions">@include("documents-archive._actions", ["document" => $document])</div>
                        </div>
                        @endforeach
                    </div>
