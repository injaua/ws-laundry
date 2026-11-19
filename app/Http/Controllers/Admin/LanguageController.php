<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Translation;
use App\Support\Languages;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Admin → System settings → Languages: create languages and translate the system's texts. */
class LanguageController extends Controller
{
    private const PER_PAGE = 50;

    public function index(): View
    {
        $languages = Language::orderByDesc("is_system")->orderBy("name")->get();
        $total = collect(Language::GROUPS)->sum(fn ($g) => count(Languages::baseLines("en", $g)));
        $counts = Translation::select("locale", DB::raw("count(*) as n"))->groupBy("locale")->pluck("n", "locale");

        return view("admin.languages.index", [
            "languages" => $languages,
            "total" => $total,
            "counts" => $counts,
            "default" => SystemSettings::defaultLocale(),
        ]);
    }

    public function create(): View
    {
        return view("admin.languages.form", ["language" => new Language(["direction" => "ltr", "base_locale" => "pt", "is_active" => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $language = Language::create($data + ["is_system" => false]);

        return redirect()->route("admin.languages.translations", $language)->with("success", __("app.language_created", ["name" => $language->name]));
    }

    public function edit(Language $language): View
    {
        return view("admin.languages.form", ["language" => $language]);
    }

    public function update(Request $request, Language $language): RedirectResponse
    {
        $data = $this->validated($request, $language);
        if (!$data["is_active"] && $language->code === SystemSettings::defaultLocale()) {
            return back()->withInput()->with("error", __("app.language_default_cannot_deactivate"));
        }
        $language->update($data);

        return redirect()->route("admin.languages.index")->with("success", __("app.language_saved"));
    }

    public function destroy(Language $language): RedirectResponse
    {
        if ($language->is_system || $language->code === SystemSettings::defaultLocale()) {
            return back()->with("error", __("app.language_cannot_delete"));
        }

        DB::transaction(function () use ($language) {
            Translation::where("locale", $language->code)->delete();
            $language->delete();
        });

        return redirect()->route("admin.languages.index")->with("success", __("app.language_deleted"));
    }

    // ---------- Translation editor ----------

    public function translations(Request $request, Language $language): View
    {
        $group = in_array($request->query("group"), Language::GROUPS, true) ? $request->query("group") : "app";
        $base = Languages::baseLines($language->code, $group);
        $current = Languages::currentLines($language->code, $group);
        $overrides = Languages::overrides($language->code, $group);
        $search = trim((string) $request->query("q"));
        $filter = $request->query("filter");

        $rows = collect($base)->map(fn ($text, $key) => (object) [
            "key" => $key,
            "base" => $text,
            "value" => $current[$key] ?? $text,
            "translated" => array_key_exists($key, $overrides),
        ]);
        if ($search !== "") {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r->key . " " . $r->base . " " . $r->value), $needle));
        }
        if ($filter === "missing") {
            $rows = $rows->reject->translated;
        } elseif ($filter === "done") {
            $rows = $rows->filter->translated;
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator($rows->values()->forPage($page, self::PER_PAGE), $rows->count(), self::PER_PAGE, $page, [
            "path" => $request->url(), "query" => $request->query(),
        ]);

        return view("admin.languages.translations", [
            "language" => $language,
            "group" => $group,
            "rows" => $paginator,
            "progress" => ["done" => count($overrides), "total" => count($base)],
            "baseName" => Languages::find(Languages::baseOf($language->code))?->name ?? Languages::baseOf($language->code),
        ]);
    }

    public function saveTranslations(Request $request, Language $language): RedirectResponse
    {
        $group = $request->validate(["group" => ["required", Rule::in(Language::GROUPS)], "t" => "nullable|array", "t.*" => "nullable|string|max:5000"])["group"];
        $warnings = $this->store_($language, $group, $request->input("t", []));

        $redirect = back()->with("success", __("app.translations_saved"));

        return $warnings ? $redirect->with("warning", __("app.translations_placeholders", ["keys" => implode(", ", array_slice($warnings, 0, 10))])) : $redirect;
    }

    /** Download every text of the language (to translate with another tool). */
    public function export(Language $language): Response
    {
        $data = [];
        foreach (Language::GROUPS as $group) {
            $data[$group] = Languages::currentLines($language->code, $group);
        }

        return response()->json($data, 200, [
            "Content-Disposition" => "attachment; filename=\"translations-{$language->code}.json\"",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function import(Request $request, Language $language): RedirectResponse
    {
        $request->validate(["file" => "required|file|max:4096|mimetypes:application/json,text/plain"]);
        $data = json_decode((string) file_get_contents($request->file("file")->getRealPath()), true);
        if (!is_array($data)) {
            return back()->with("error", __("app.translations_import_invalid"));
        }

        $count = 0;
        $warnings = [];
        foreach (Language::GROUPS as $group) {
            if (isset($data[$group]) && is_array($data[$group])) {
                $lines = array_filter($data[$group], fn ($v) => is_string($v));
                $count += count($lines);
                $warnings = array_merge($warnings, $this->store_($language, $group, $lines));
            }
        }

        $redirect = back()->with("success", __("app.translations_imported", ["count" => $count]));

        return $warnings ? $redirect->with("warning", __("app.translations_placeholders", ["keys" => implode(", ", array_slice($warnings, 0, 10))])) : $redirect;
    }

    /**
     * Saves texts of one group. A text equal to the original (or empty) is not stored, so
     * the key keeps following the base. Returns keys whose :placeholders differ.
     */
    private function store_(Language $language, string $group, array $texts): array
    {
        // The original text: the language's own file for pt / en, the base language for others.
        $base = Languages::baseLines($language->code, $group);
        $warnings = [];

        DB::transaction(function () use ($language, $group, $texts, $base, &$warnings) {
            foreach ($texts as $key => $value) {
                $key = (string) $key;
                if (!array_key_exists($key, $base)) {
                    continue; // unknown key: ignored
                }
                $value = trim((string) $value);
                $where = ["locale" => $language->code, "group" => $group, "key" => $key];

                if ($value === "" || $value === $base[$key]) {
                    Translation::where($where)->delete();

                    continue;
                }
                Translation::updateOrCreate($where, ["value" => $value]);

                preg_match_all('/:[a-z_]+/', $base[$key], $a);
                preg_match_all('/:[a-z_]+/', $value, $b);
                if (array_diff($a[0], $b[0])) {
                    $warnings[] = $key;
                }
            }
        });

        Languages::flush($language->code);

        return $warnings;
    }

    private function validated(Request $request, ?Language $language = null): array
    {
        $data = $request->validate([
            "code" => $language
                ? ["prohibited"]
                : ["required", "string", "max:10", "regex:/^[a-z]{2,3}(_[A-Z]{2})?$/", Rule::unique("languages", "code")],
            "name" => "required|string|max:60",
            "flag" => "nullable|string|max:16",
            "direction" => "required|in:ltr,rtl",
            "base_locale" => $language?->is_system ? ["prohibited"] : ["required", Rule::in(Language::BASES)],
            "is_active" => "nullable|boolean",
        ]);
        $data["is_active"] = $request->boolean("is_active");

        return $data;
    }
}
