<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFolder;
use App\Models\DocumentShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Document archive of the store (admin, owner, accountant): upload Word, Excel, PowerPoint
 * and PDF files, organise them in folders and categories, tag them, mark favourites and
 * follow the ones about to expire. Files stay on the private disk and are only served here.
 * The "received" and "sent" views are the exchange between colleagues (DocumentExchangeController).
 */
class DocumentController extends Controller
{
    /** Largest file accepted, in KB. */
    public const MAX_KB = 20480;

    public const SORTS = ["recent", "oldest", "title", "size", "expiry"];

    public const VIEWS = ["all", "received", "sent", "favorites", "expiring", "uncategorized"];

    public function index(Request $request)
    {
        Gate::authorize("viewAny", Document::class);

        $user = $request->user();
        $userId = (int) $user->id;
        $filters = $this->filters($request);
        $visible = fn () => Document::query()->visibleTo($user);

        $shares = null;
        if (in_array($filters["view"], ["received", "sent"], true)) {
            $shares = $this->shares($filters, $user);
            $documents = $shares;
        } else {
            $query = $visible()->with(["folder", "category"])
                ->withExists(["favoritedBy as is_favorite" => fn ($q) => $q->whereKey($userId)]);
            $this->applyFilters($query, $filters, $userId);
            $this->applySort($query, $filters["sort"]);
            $documents = $query->paginate(24)->withQueryString();
        }

        $countVisible = ["documents" => fn ($q) => $q->visibleTo($user)];
        $folders = DocumentFolder::withCount($countVisible)->orderBy("name")->get();
        $categories = DocumentCategory::withCount($countVisible)->orderBy("name")->get();

        $stats = [
            "documents" => $visible()->count(),
            "size" => (int) $visible()->sum("size"),
            "folders" => $folders->count(),
            "expiring" => $visible()->expiringSoon()->count(),
            "favorites" => $visible()->favoriteOf($userId)->count(),
            "uncategorized" => $visible()->whereNull("category_id")->count(),
            "received" => DocumentShare::where("recipient_id", $userId)->count(),
            "unread" => DocumentShare::where("recipient_id", $userId)->unread()->count(),
            "sent" => DocumentShare::where("sender_id", $userId)->count(),
        ];

        $tags = $visible()->whereNotNull("tags")->pluck("tags")->flatten()->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
        $recipients = DocumentShare::recipientsFor($user);

        return view("documents-archive.index", compact("documents", "shares", "folders", "categories", "stats", "tags", "filters", "recipients"));
    }

    public function store(Request $request)
    {
        Gate::authorize("create", Document::class);

        $data = $request->validate([
            "files" => "required|array|min:1|max:20",
            "files.*" => ["required", "file", "max:" . self::MAX_KB, "mimes:" . implode(",", Document::extensions())],
            ...$this->metadataRules(titleRequired: false),
        ], [
            "files.*.mimes" => __("app.doc_invalid_type"),
            "files.*.max" => __("app.doc_file_too_large", ["size" => self::MAX_KB / 1024]),
        ]);

        $files = $data["files"];
        $single = count($files) === 1;

        DB::transaction(function () use ($files, $data, $single) {
            foreach ($files as $file) {
                $this->storeFile($file, $data, $single ? ($data["title"] ?? null) : null);
            }
        });

        return back()->with("success", trans_choice("app.doc_uploaded", count($files), ["count" => count($files)]));
    }

    public function edit(Request $request, Document $document)
    {
        Gate::authorize("view", $document);

        $userId = (int) $request->user()->id;
        DocumentShare::markRead($document, $userId);

        return view("documents-archive.edit", [
            "document" => $document->load(["folder", "category", "uploader"]),
            // The exchange of this document the user took part in (all of it for the uploader).
            "shares" => $document->shares()->with(["sender:id,name,role", "recipient:id,name,role"])
                ->when((int) $document->uploaded_by !== $userId, fn ($q) => $q->where(fn ($w) => $w->where("sender_id", $userId)->orWhere("recipient_id", $userId)))
                ->latest()->get(),
            "folders" => DocumentFolder::orderBy("name")->get(),
            "categories" => DocumentCategory::orderBy("name")->get(),
        ]);
    }

    public function update(Request $request, Document $document)
    {
        Gate::authorize("update", $document);

        $data = $request->validate([
            ...$this->metadataRules(titleRequired: true),
            "file" => ["nullable", "file", "max:" . self::MAX_KB, "mimes:" . implode(",", Document::extensions())],
            "is_private" => "nullable|boolean",
        ], [
            "file.mimes" => __("app.doc_invalid_type"),
            "file.max" => __("app.doc_file_too_large", ["size" => self::MAX_KB / 1024]),
        ]);

        $attributes = $this->metadata($data);
        // Only the uploader decides who sees the document.
        if ((int) $document->uploaded_by === (int) $request->user()->id) {
            $attributes["is_private"] = $request->boolean("is_private");
        }

        // Replacing the file keeps the record (title, folder, tags…) and removes the old file.
        $oldPath = null;
        if ($request->hasFile("file")) {
            $oldPath = $document->path;
            $attributes += Document::storeUpload($request->file("file"));
        }

        $document->update($attributes);

        if ($oldPath && $oldPath !== $document->path) {
            Storage::disk(Document::DISK)->delete($oldPath);
        }

        return redirect()->route("documents-archive.index", $request->only("folder"))
            ->with("success", __("app.doc_updated"));
    }

    public function destroy(Document $document)
    {
        Gate::authorize("delete", $document);

        $document->delete();

        // Deleted from its own page: that page is gone, go back to the archive.
        $back = url()->previous();
        if (str_contains($back, "/document-archive/{$document->id}/")) {
            $back = route("documents-archive.index", array_filter(["folder" => $document->folder_id]));
        }

        return redirect($back)->with("success", __("app.doc_deleted"));
    }

    public function download(Request $request, Document $document)
    {
        Gate::authorize("view", $document);

        DocumentShare::markRead($document, (int) $request->user()->id);
        abort_unless(Storage::disk(Document::DISK)->exists($document->path), 404);

        return Storage::disk(Document::DISK)->download($document->path, $document->original_name);
    }

    /** PDFs open in the browser; other types are downloaded. */
    public function preview(Request $request, Document $document)
    {
        Gate::authorize("view", $document);

        if (!$document->isPdf()) {
            return $this->download($request, $document);
        }

        DocumentShare::markRead($document, (int) $request->user()->id);

        abort_unless(Storage::disk(Document::DISK)->exists($document->path), 404);

        return response()->file(Storage::disk(Document::DISK)->path($document->path), [
            "Content-Type" => "application/pdf",
            "Content-Disposition" => 'inline; filename="' . addcslashes($document->original_name, '"\\') . '"',
            "X-Content-Type-Options" => "nosniff",
        ]);
    }

    public function favorite(Request $request, Document $document)
    {
        Gate::authorize("view", $document);

        $result = $document->favoritedBy()->toggle([$request->user()->id]);
        $added = !empty($result["attached"]);

        if ($request->expectsJson()) {
            return response()->json(["favorite" => $added]);
        }

        return back()->with("success", $added ? __("app.doc_favorite_added") : __("app.doc_favorite_removed"));
    }

    /** @return array<string, mixed> */
    private function metadataRules(bool $titleRequired): array
    {
        return [
            "title" => [$titleRequired ? "required" : "nullable", "string", "max:255"],
            "reference" => "nullable|string|max:100",
            "description" => "nullable|string|max:2000",
            "tags" => "nullable|string|max:500",
            "document_date" => "nullable|date",
            "expires_at" => "nullable|date",
            "folder_id" => ["nullable", Rule::exists("document_folders", "id")],
            "category_id" => ["nullable", Rule::exists("document_categories", "id")],
        ];
    }

    /** @return array<string, mixed> */
    private function metadata(array $data): array
    {
        $attributes = [
            "reference" => $data["reference"] ?? null,
            "description" => $data["description"] ?? null,
            "tags" => self::parseTags($data["tags"] ?? null),
            "document_date" => $data["document_date"] ?? null,
            "expires_at" => $data["expires_at"] ?? null,
            "folder_id" => $data["folder_id"] ?? null,
            "category_id" => $data["category_id"] ?? null,
        ];
        if (!empty($data["title"])) {
            $attributes["title"] = $data["title"];
        }

        return $attributes;
    }

    private function storeFile(UploadedFile $file, array $data, ?string $title): Document
    {
        $attributes = $this->metadata($data) + Document::storeUpload($file);
        $attributes["title"] = $title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        return Document::create($attributes);
    }

    /** The received or sent box: one row per document sent, newest first. */
    private function shares(array $filters, User $user)
    {
        $received = $filters["view"] === "received";
        // The search, type, tag and date filters apply to the documents sent.
        $documents = Document::query()->select("id");
        $this->applyFilters($documents, ["view" => "all"] + $filters, (int) $user->id);

        return DocumentShare::query()
            ->with(["document.folder", "document.category", "sender:id,name,role", "recipient:id,name,role"])
            ->where($received ? "recipient_id" : "sender_id", $user->id)
            ->whereIn("document_id", $documents)
            ->latest()->orderByDesc("id")
            ->paginate(24)->withQueryString();
    }

    /** "contrato, RH ,  contrato" => ["contrato", "RH"] */
    public static function parseTags(?string $tags): ?array
    {
        $list = collect(preg_split("/[,;]+/", (string) $tags))
            ->map(fn ($t) => mb_substr(trim($t), 0, 40))
            ->filter()
            ->unique(fn ($t) => mb_strtolower($t))
            ->values()
            ->all();

        return $list ?: null;
    }

    /** Query string filters, ignoring anything malformed. @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $string = fn (string $key) => is_string($request->query($key)) ? trim($request->query($key)) : "";
        $date = function (string $key) use ($string): ?string {
            $value = $string($key);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return null;
            }
            [$y, $m, $d] = array_map("intval", explode("-", $value));

            return checkdate($m, $d, $y) ? $value : null;
        };
        $id = fn (string $key) => ctype_digit($string($key)) ? (int) $string($key) : null;

        return [
            "view" => in_array($string("view"), self::VIEWS, true) ? $string("view") : "all",
            "folder" => $id("folder"),
            "category" => $id("category"),
            "q" => mb_substr($string("q"), 0, 100),
            "type" => array_key_exists($string("type"), Document::TYPES) ? $string("type") : "",
            "tag" => mb_substr($string("tag"), 0, 40),
            "from" => $date("from"),
            "to" => $date("to"),
            "sort" => in_array($string("sort"), self::SORTS, true) ? $string("sort") : "recent",
            "layout" => $string("layout") === "list" ? "list" : "grid",
        ];
    }

    /** @param Builder<Document> $query */
    private function applyFilters(Builder $query, array $filters, int $userId): void
    {
        match ($filters["view"]) {
            "favorites" => $query->favoriteOf($userId),
            "expiring" => $query->expiringSoon(),
            "uncategorized" => $query->whereNull("category_id"),
            default => null,
        };

        $query->when($filters["folder"], fn ($q, $id) => $q->where("folder_id", $id))
            ->when($filters["category"], fn ($q, $id) => $q->where("category_id", $id))
            ->when($filters["type"], fn ($q, $type) => $q->ofType($type))
            ->when($filters["tag"], fn ($q, $tag) => $q->whereJsonContains("tags", $tag))
            ->when($filters["from"], fn ($q, $from) => $q->whereRaw("COALESCE(document_date, DATE(created_at)) >= ?", [$from]))
            ->when($filters["to"], fn ($q, $to) => $q->whereRaw("COALESCE(document_date, DATE(created_at)) <= ?", [$to]))
            ->when($filters["q"], function ($q, $term) {
                $like = "%" . addcslashes($term, "%_\\") . "%";
                $q->where(fn ($w) => $w->where("title", "like", $like)
                    ->orWhere("original_name", "like", $like)
                    ->orWhere("reference", "like", $like)
                    ->orWhere("description", "like", $like)
                    ->orWhereRaw("LOWER(CAST(tags AS CHAR)) LIKE ?", [mb_strtolower($like)]));
            });
    }

    /** @param Builder<Document> $query */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            "oldest" => $query->orderBy("created_at")->orderBy("id"),
            "title" => $query->orderBy("title")->orderBy("id"),
            "size" => $query->orderByDesc("size")->orderByDesc("id"),
            "expiry" => $query->orderByRaw("expires_at IS NULL")->orderBy("expires_at")->orderByDesc("id"),
            default => $query->orderByDesc("created_at")->orderByDesc("id"),
        };
    }
}
