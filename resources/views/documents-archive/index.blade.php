@extends("layouts.admin")
@section("title", __('app.doc_archive'))
@section("page_title", __('app.doc_archive'))

@push("styles")
    @vite("resources/css/pages/documents.css")
@endpush

@section("content")
@php
    $current = fn (array $merge = []) => array_filter(array_merge(
        collect($filters)->only(["q", "type", "tag", "from", "to", "sort", "layout"])->all(),
        $merge,
    ), fn ($v) => $v !== null && $v !== "" && $v !== "all" && $v !== "recent" && $v !== "grid");
    $navTo = fn (array $params) => route("documents-archive.index", $current($params));
    $activeFolder = $filters["folder"] ? $folders->firstWhere("id", $filters["folder"]) : null;
    $activeCategory = $filters["category"] ? $categories->firstWhere("id", $filters["category"]) : null;
    $isHome = $filters["view"] === "all" && !$filters["folder"] && !$filters["category"];
    $hasSearch = $filters["q"] || $filters["type"] || $filters["tag"] || $filters["from"] || $filters["to"];
    $user = auth()->user();
@endphp

{{-- Header --}}
<div class="doc-header">
    <div>
        <h2 class="doc-header-title">{{ __('app.doc_archive') }}</h2>
        <p class="doc-header-sub">{{ __('app.doc_archive_subtitle') }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-outline-secondary doc-btn" data-bs-toggle="modal" data-bs-target="#folderModal" data-doc-new>
            <i class="bi bi-folder-plus"></i> {{ __('app.doc_new_folder') }}
        </button>
        <button type="button" class="btn btn-outline-primary doc-btn" data-bs-toggle="modal" data-bs-target="#exchangeModal" @disabled($recipients->isEmpty())
                @if($recipients->isEmpty()) title="{{ __('app.doc_no_recipients') }}" @endif>
            <i class="bi bi-send"></i> {{ __('app.doc_send_to_colleague') }}
        </button>
        <button type="button" class="btn btn-primary doc-btn" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="bi bi-cloud-arrow-up"></i> {{ __('app.doc_upload') }}
        </button>
    </div>
</div>

{{-- Summary --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <a href="{{ route("documents-archive.index") }}" class="doc-stat {{ $isHome ? "active" : "" }}">
            <span class="doc-stat-icon tint-primary"><i class="bi bi-folder2-open"></i></span>
            <span class="doc-stat-body">
                <span class="doc-stat-value">{{ $stats["documents"] }}</span>
                <span class="doc-stat-label">{{ __('app.doc_documents') }}</span>
            </span>
        </a>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="doc-stat">
            <span class="doc-stat-icon tint-info"><i class="bi bi-hdd-stack"></i></span>
            <span class="doc-stat-body">
                <span class="doc-stat-value">{{ \App\Models\Document::formatSize($stats["size"]) }}</span>
                <span class="doc-stat-label">{{ __('app.doc_storage_used') }}</span>
            </span>
            <span class="doc-stat-extra">{{ trans_choice('app.doc_folders_count', $stats["folders"], ["count" => $stats["folders"]]) }}</span>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <a href="{{ route("documents-archive.index", ["view" => "expiring"]) }}" class="doc-stat {{ $filters["view"] === "expiring" ? "active" : "" }}">
            <span class="doc-stat-icon tint-warning"><i class="bi bi-clock-history"></i></span>
            <span class="doc-stat-body">
                <span class="doc-stat-value">{{ $stats["expiring"] }}</span>
                <span class="doc-stat-label">{{ __('app.doc_expiring_30') }}</span>
            </span>
        </a>
    </div>
    <div class="col-xl-3 col-sm-6">
        <a href="{{ route("documents-archive.index", ["view" => "favorites"]) }}" class="doc-stat {{ $filters["view"] === "favorites" ? "active" : "" }}">
            <span class="doc-stat-icon tint-gold"><i class="bi bi-star-fill"></i></span>
            <span class="doc-stat-body">
                <span class="doc-stat-value">{{ $stats["favorites"] }}</span>
                <span class="doc-stat-label">{{ __('app.doc_favorites') }}</span>
            </span>
        </a>
    </div>
</div>

<div class="row g-3">
    {{-- Library: views, folders, categories --}}
    <div class="col-lg-3">
@include("documents-archive._sidebar")
    </div>

    {{-- Documents --}}
    <div class="col-lg-9">
        <div class="card doc-panel">
            <div class="card-body">
                <form action="{{ route("documents-archive.index") }}" method="GET" class="doc-filters">
                    @foreach(["view" => $filters["view"] !== "all" ? $filters["view"] : null, "folder" => $filters["folder"], "category" => $filters["category"]] as $name => $value)
                        @if($value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
                    @endforeach
                    {{-- Keeps the layout on search; a layout button sent later in the form wins. --}}
                    <input type="hidden" name="layout" value="{{ $filters["layout"] }}">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="search" name="q" class="form-control" value="{{ $filters["q"] }}" placeholder="{{ __('app.doc_search_placeholder') }}" aria-label="{{ __('app.search') }}">
                                <button class="btn btn-outline-secondary" type="submit" title="{{ __('app.search') }}"><i class="bi bi-search"></i></button>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <select name="type" class="form-select" data-auto-submit aria-label="{{ __('app.doc_all_types') }}">
                                <option value="">{{ __('app.doc_all_types') }}</option>
                                @foreach(array_keys(\App\Models\Document::TYPES) as $type)
                                    <option value="{{ $type }}" @selected($filters["type"] === $type)>{{ __('app.doc_type_' . $type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 col-6">
                            <select name="tag" class="form-select" data-auto-submit aria-label="{{ __('app.doc_all_tags') }}">
                                <option value="">{{ __('app.doc_all_tags') }}</option>
                                @foreach($tags as $tag)
                                    <option value="{{ $tag }}" @selected($filters["tag"] === $tag)>{{ $tag }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <div class="input-group doc-range">
                                <input type="date" name="from" class="form-control" value="{{ $filters["from"] }}" aria-label="{{ __('app.doc_start_date') }}" title="{{ __('app.doc_start_date') }}" data-auto-submit>
                                <span class="input-group-text"><i class="bi bi-arrow-right"></i></span>
                                <input type="date" name="to" class="form-control" value="{{ $filters["to"] }}" aria-label="{{ __('app.doc_end_date') }}" title="{{ __('app.doc_end_date') }}" data-auto-submit>
                            </div>
                        </div>
                        <div class="col-md-4 col-7">
                            <select name="sort" class="form-select" data-auto-submit aria-label="{{ __('app.doc_sort') }}">
                                @foreach(\App\Http\Controllers\DocumentController::SORTS as $sort)
                                    <option value="{{ $sort }}" @selected($filters["sort"] === $sort)>{{ __('app.doc_sort_' . $sort) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 col-5">
                            <div class="doc-layout" role="group" aria-label="{{ __('app.doc_layout') }}">
                                <button type="submit" name="layout" value="grid" class="{{ $filters["layout"] === "grid" ? "active" : "" }}" aria-pressed="{{ $filters["layout"] === "grid" ? "true" : "false" }}">
                                    <i class="bi bi-grid-3x3-gap"></i> {{ __('app.doc_grid') }}
                                </button>
                                <button type="submit" name="layout" value="list" class="{{ $filters["layout"] === "list" ? "active" : "" }}" aria-pressed="{{ $filters["layout"] === "list" ? "true" : "false" }}">
                                    <i class="bi bi-list-ul"></i> {{ __('app.doc_list') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                {{-- Where we are --}}
                @if($activeFolder || $activeCategory || !$isHome || $hasSearch)
                <div class="doc-context">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        @if($activeFolder)
                            <i class="bi bi-folder-fill text-{{ $activeFolder->color }} fs-5"></i>
                            <strong class="text-truncate">{{ $activeFolder->name }}</strong>
                        @elseif($activeCategory)
                            <i class="bi bi-tag-fill text-{{ $activeCategory->color }} fs-5"></i>
                            <strong class="text-truncate">{{ $activeCategory->name }}</strong>
                        @elseif(!$isHome)
                            <strong>{{ __('app.doc_view_' . $filters["view"]) }}</strong>
                        @else
                            <strong>{{ __('app.doc_all_documents') }}</strong>
                        @endif
                        <span class="text-muted small text-nowrap">· {{ trans_choice('app.doc_results', $documents->total(), ["count" => $documents->total()]) }}</span>
                    </div>
                    <div class="d-flex gap-1">
                        @if($activeFolder)
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#folderModal"
                                    data-doc-edit="{{ route("documents-archive.folders.update", $activeFolder) }}" data-name="{{ $activeFolder->name }}" data-color="{{ $activeFolder->color }}">
                                <i class="bi bi-pencil"></i> {{ __('app.doc_rename') }}
                            </button>
                            <form action="{{ route("documents-archive.folders.destroy", $activeFolder) }}" method="POST" data-confirm="{{ __('app.doc_folder_delete_confirm') }}">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        @elseif($activeCategory)
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#categoryModal"
                                    data-doc-edit="{{ route("documents-archive.categories.update", $activeCategory) }}" data-name="{{ $activeCategory->name }}" data-color="{{ $activeCategory->color }}">
                                <i class="bi bi-pencil"></i> {{ __('app.edit') }}
                            </button>
                            <form action="{{ route("documents-archive.categories.destroy", $activeCategory) }}" method="POST" data-confirm="{{ __('app.doc_category_delete_confirm') }}">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                        @if($hasSearch)
                            <a href="{{ route("documents-archive.index", array_filter(["view" => $filters["view"] !== "all" ? $filters["view"] : null, "folder" => $filters["folder"], "category" => $filters["category"]])) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-x-lg"></i> {{ __('app.doc_clear_filters') }}
                            </a>
                        @endif
                    </div>
                </div>
                @endif

                @if($shares !== null && $documents->isEmpty())
                    <div class="doc-empty">
                        <div class="doc-empty-art"><i class="bi {{ $filters["view"] === "received" ? "bi-box-arrow-in-down" : "bi-send" }}"></i></div>
                        <p>{{ $hasSearch ? __('app.doc_empty_results') : __('app.doc_empty_' . $filters["view"]) }}</p>
                        @if($recipients->isNotEmpty())
                            <button type="button" class="btn btn-primary doc-btn" data-bs-toggle="modal" data-bs-target="#exchangeModal">
                                <i class="bi bi-send"></i> {{ __('app.doc_send_to_colleague') }}
                            </button>
                        @endif
                    </div>
                @elseif($shares !== null)
@include("documents-archive._exchange")
                @elseif($documents->isEmpty())
                    <div class="doc-empty">
                        <div class="doc-empty-art"><i class="bi bi-file-earmark-text"></i><span class="doc-empty-bubble"><i class="bi bi-three-dots"></i></span></div>
                        @if($stats["documents"] === 0)
                            <p>{{ __('app.doc_empty_archive') }}</p>
                            <button type="button" class="btn btn-primary doc-btn" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                <i class="bi bi-cloud-arrow-up"></i> {{ __('app.doc_upload_first') }}
                            </button>
                        @else
                            <p>{{ __('app.doc_empty_results') }}</p>
                            @if($activeFolder || $activeCategory)
                                <button type="button" class="btn btn-primary doc-btn" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                    <i class="bi bi-cloud-arrow-up"></i> {{ __('app.doc_upload_here') }}
                                </button>
                            @endif
                        @endif
                    </div>
                @elseif($filters["layout"] === "list")
@include("documents-archive._list")
                @else
@include("documents-archive._grid")
                @endif

                @if($documents->hasPages())
                    <div class="mt-3">{{ $documents->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

@include("documents-archive._modals")
@endsection

@push("scripts")
    @vite("resources/js/pages/documents.js")
@endpush
