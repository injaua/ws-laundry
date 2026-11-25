<?php

namespace App\Http\Controllers;

use App\Models\DocumentFolder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Folders of the document archive (route: can:manage-documents). */
class DocumentFolderController extends Controller
{
    public function store(Request $request)
    {
        $folder = DocumentFolder::create($this->validated($request));

        return redirect()->route("documents-archive.index", ["folder" => $folder->id])
            ->with("success", __("app.doc_folder_created"));
    }

    public function update(Request $request, DocumentFolder $folder)
    {
        $folder->update($this->validated($request, $folder));

        return back()->with("success", __("app.doc_folder_updated"));
    }

    /** Its documents stay in the archive, without folder. */
    public function destroy(DocumentFolder $folder)
    {
        $folder->delete();

        return redirect()->route("documents-archive.index")
            ->with("success", __("app.doc_folder_deleted"));
    }

    private function validated(Request $request, ?DocumentFolder $ignore = null): array
    {
        $data = $request->validateWithBag("folder", [
            "name" => ["required", "string", "max:100", Rule::unique("document_folders", "name")->ignore($ignore)],
            "color" => ["nullable", Rule::in(DocumentFolder::COLORS)],
        ]);
        $data["color"] ??= DocumentFolder::COLORS[0];

        return $data;
    }
}
