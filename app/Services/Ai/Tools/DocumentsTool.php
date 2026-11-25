<?php

namespace App\Services\Ai\Tools;

use App\Models\Document;
use App\Models\User;

class DocumentsTool extends AiTool
{
    public function name(): string
    {
        return "documents_search";
    }

    public function description(): string
    {
        return "Search the document archive (contracts, invoices, licences...) by title, reference or tag, or list the ones expiring soon. "
            . "Returns title, folder, category, type, dates and link. The content of the files is not read.";
    }

    public function ability(): ?string
    {
        return "manage-documents";
    }

    protected function properties(): array
    {
        return [
            "query" => ["type" => "string", "description" => "Part of the title, reference, description or a tag"],
            "expiring" => ["type" => "boolean", "description" => "Only documents expiring in the next " . Document::EXPIRING_DAYS . " days or already expired"],
            ...self::limitProperty(),
        ];
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        $query = Document::query()->visibleTo($user)->with(["folder:id,name", "category:id,name"]);
        $term = self::text($input, "query");
        if ($term !== "") {
            $like = "%" . addcslashes($term, "%_\\") . "%";
            $query->where(fn ($q) => $q->where("title", "like", $like)->orWhere("reference", "like", $like)
                ->orWhere("description", "like", $like)->orWhere("tags", "like", $like));
        }
        if (!empty($input["expiring"])) {
            $query->whereNotNull("expires_at")->where("expires_at", "<=", today()->addDays(Document::EXPIRING_DAYS))->orderBy("expires_at");
        } else {
            $query->latest();
        }

        return [
            "documents" => $query->limit(self::limit($input))->get()->map(fn (Document $d) => [
                "title" => $d->title,
                "reference" => $d->reference,
                "folder" => $d->folder?->name,
                "category" => $d->category?->name,
                "type" => $d->type(),
                "date" => self::date($d->document_date),
                "expires" => self::date($d->expires_at),
                "expired" => $d->isExpired(),
                "link" => route("documents-archive.edit", $d),
            ])->all(),
        ];
    }
}
