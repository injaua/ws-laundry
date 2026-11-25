<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentShare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Document exchange between admin, owner and accountant of the store: send new files or a
 * document of the archive to one or more colleagues, with a message. Sent as private, the
 * document is seen only by the sender and the recipients; otherwise it also stays in the
 * store's archive. The recipient finds it in "Received" (unread until opened).
 */
class DocumentExchangeController extends Controller
{
    /** Sends new files: they are added to the archive (private unless kept for everyone). */
    public function send(Request $request)
    {
        Gate::authorize("create", Document::class);

        $data = $request->validateWithBag("exchange", [
            "files" => "required|array|min:1|max:10",
            "files.*" => ["required", "file", "max:" . DocumentController::MAX_KB, "mimes:" . implode(",", Document::extensions())],
            "title" => "nullable|string|max:255",
            "folder_id" => ["nullable", Rule::exists("document_folders", "id")],
            "category_id" => ["nullable", Rule::exists("document_categories", "id")],
            "share_with_store" => "nullable|boolean",
            ...$this->recipientRules($request),
        ], [
            "files.*.mimes" => __("app.doc_invalid_type"),
            "files.*.max" => __("app.doc_file_too_large", ["size" => DocumentController::MAX_KB / 1024]),
        ]);

        $files = $data["files"];
        $single = count($files) === 1;

        DB::transaction(function () use ($files, $data, $single, $request) {
            foreach ($files as $file) {
                $document = Document::create(Document::storeUpload($file) + [
                    "title" => ($single ? ($data["title"] ?? null) : null) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    "folder_id" => $data["folder_id"] ?? null,
                    "category_id" => $data["category_id"] ?? null,
                    "is_private" => !$request->boolean("share_with_store"),
                ]);
                $this->share($document, $data, (int) $request->user()->id);
            }
        });

        return back()->with("success", trans_choice("app.doc_sent", count($data["recipients"]), ["count" => count($data["recipients"])]));
    }

    /** Sends a document already in the archive. */
    public function forward(Request $request, Document $document)
    {
        Gate::authorize("send", $document);

        $data = $request->validateWithBag("forward", $this->recipientRules($request));

        DB::transaction(fn () => $this->share($document, $data, (int) $request->user()->id));

        return back()->with("success", trans_choice("app.doc_sent", count($data["recipients"]), ["count" => count($data["recipients"])]));
    }

    public function read(Request $request, DocumentShare $share)
    {
        abort_unless($share->recipient_id === (int) $request->user()->id, 403);

        $share->update(["read_at" => $share->read_at ?? now()]);

        return back()->with("success", __("app.doc_marked_read"));
    }

    /** The recipient removes it from "Received"; the sender takes the sending back. */
    public function destroy(Request $request, DocumentShare $share)
    {
        $userId = (int) $request->user()->id;
        abort_unless($userId === $share->recipient_id || $userId === $share->sender_id, 403);

        $share->delete();

        return back()->with("success", __("app.doc_share_removed"));
    }

    /** @return array<string, mixed> */
    private function recipientRules(Request $request): array
    {
        $allowed = DocumentShare::recipientsFor($request->user())->modelKeys();

        return [
            "recipients" => "required|array|min:1|max:20",
            "recipients.*" => ["required", "integer", "distinct", Rule::in($allowed)],
            "message" => "nullable|string|max:1000",
        ];
    }

    private function share(Document $document, array $data, int $senderId): void
    {
        foreach (array_unique(array_map("intval", $data["recipients"])) as $recipientId) {
            $document->shares()->create([
                "sender_id" => $senderId,
                "recipient_id" => $recipientId,
                "message" => $data["message"] ?? null,
            ]);
        }
    }
}
