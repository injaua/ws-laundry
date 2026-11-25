{{-- Library panel: views, folders and categories with their counts. --}}
        <div class="card doc-panel doc-side">
            <div class="card-body">
                <nav class="doc-side-nav">
                    @foreach([
                        ["all", "bi-inbox", __('app.doc_all_documents'), $stats["documents"]],
                        ["received", "bi-box-arrow-in-down", __('app.doc_view_received'), $stats["unread"] ?: $stats["received"]],
                        ["sent", "bi-send", __('app.doc_view_sent'), $stats["sent"]],
                        ["favorites", "bi-star", __('app.doc_favorites'), $stats["favorites"]],
                        ["expiring", "bi-clock", __('app.doc_expiring_soon'), $stats["expiring"]],
                        ["uncategorized", "bi-file-earmark", __('app.doc_uncategorized'), $stats["uncategorized"]],
                    ] as [$view, $icon, $label, $count])
                        @php($active = $filters["view"] === $view && !$filters["folder"] && !$filters["category"])
                        <a href="{{ route("documents-archive.index", $view === "all" ? [] : ["view" => $view]) }}" class="doc-side-link {{ $active ? "active" : "" }}">
                            <i class="bi {{ $icon }}"></i>
                            <span class="flex-grow-1 text-truncate">{{ $label }}</span>
                            <span class="doc-side-count {{ $view === "received" && $stats["unread"] ? "unread" : "" }}" @if($view === "received" && $stats["unread"]) title="{{ __('app.doc_unread_received') }}" @endif>{{ $count }}</span>
                        </a>
                    @endforeach
                </nav>

                {{-- Folders --}}
                <div class="doc-side-section">
                    <span>{{ __('app.doc_folders') }}</span>
                    <button type="button" class="btn btn-sm btn-link doc-side-add" data-bs-toggle="modal" data-bs-target="#folderModal" data-doc-new title="{{ __('app.doc_new_folder') }}" aria-label="{{ __('app.doc_new_folder') }}">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                @forelse($folders as $folder)
                    <a href="{{ route("documents-archive.index", ["folder" => $folder->id]) }}" class="doc-side-link {{ $activeFolder?->id === $folder->id ? "active" : "" }}">
                        <i class="bi bi-folder-fill text-{{ $folder->color }}"></i>
                        <span class="flex-grow-1 text-truncate" title="{{ $folder->name }}">{{ $folder->name }}</span>
                        <span class="doc-side-count">{{ $folder->documents_count }}</span>
                    </a>
                @empty
                    <div class="doc-side-empty">
                        <i class="bi bi-inbox"></i>
                        <div>{{ __('app.doc_no_folders') }}</div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#folderModal" data-doc-new>{{ __('app.doc_create_one') }}</button>
                    </div>
                @endforelse

                {{-- Categories --}}
                <div class="doc-side-section">
                    <span>{{ __('app.doc_categories') }}</span>
                    <button type="button" class="btn btn-sm btn-link doc-side-add" data-bs-toggle="modal" data-bs-target="#categoryModal" data-doc-new title="{{ __('app.doc_new_category') }}" aria-label="{{ __('app.doc_new_category') }}">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                @forelse($categories as $category)
                    <a href="{{ route("documents-archive.index", ["category" => $category->id]) }}" class="doc-side-link {{ $activeCategory?->id === $category->id ? "active" : "" }}">
                        <i class="bi bi-tag-fill text-{{ $category->color }}"></i>
                        <span class="flex-grow-1 text-truncate" title="{{ $category->name }}">{{ $category->name }}</span>
                        <span class="doc-side-count">{{ $category->documents_count }}</span>
                    </a>
                @empty
                    <div class="doc-side-empty">
                        <i class="bi bi-tags"></i>
                        <div>{{ __('app.doc_no_categories') }}</div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#categoryModal" data-doc-new>{{ __('app.doc_create_one') }}</button>
                    </div>
                @endforelse
            </div>
        </div>
