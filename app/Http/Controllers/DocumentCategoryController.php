<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Categorys of the document archive (route: can:manage-documents). */
class DocumentCategoryController extends Controller
{
    public function store(Request $request)
    {
        $category = DocumentCategory::create($this->validated($request));

        return redirect()->route("documents-archive.index", ["category" => $category->id])
            ->with("success", __("app.doc_category_created"));
    }

    public function update(Request $request, DocumentCategory $category)
    {
        $category->update($this->validated($request, $category));

        return back()->with("success", __("app.doc_category_updated"));
    }

    /** Its documents stay in the archive, without category. */
    public function destroy(DocumentCategory $category)
    {
        $category->delete();

        return redirect()->route("documents-archive.index")
            ->with("success", __("app.doc_category_deleted"));
    }

    private function validated(Request $request, ?DocumentCategory $ignore = null): array
    {
        $data = $request->validateWithBag("category", [
            "name" => ["required", "string", "max:100", Rule::unique("document_categories", "name")->ignore($ignore)],
            "color" => ["nullable", Rule::in(DocumentCategory::COLORS)],
        ]);
        $data["color"] ??= DocumentCategory::COLORS[0];

        return $data;
    }
}
