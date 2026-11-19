<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\WalletStatement;
use App\Models\WalletStatementLine;
use App\Services\MobileMoney\WalletReconciliation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Wallet statements: import and match against the recorded mobile payments. */
class WalletReconciliationController extends Controller
{
    public function __construct(private WalletReconciliation $reconciliation) {}

    public function index(): View
    {
        return view("mobile-payments.reconciliation.index", [
            "statements" => WalletStatement::with("user:id,name")->latest("id")->paginate(20),
            "pending" => collect(WalletReconciliation::PROVIDERS)->mapWithKeys(fn ($m) => [$m => [
                "count" => Payment::where("method", $m)->whereNull("reconciled_at")->count(),
                "amount" => (float) Payment::where("method", $m)->whereNull("reconciled_at")->sum("amount"),
            ]]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            "provider" => ["required", Rule::in(WalletReconciliation::PROVIDERS)],
            "file" => "required|file|max:10240|mimes:csv,txt,xlsx,xls",
        ]);

        $file = $request->file("file");

        try {
            $statement = $this->reconciliation->import($file, $data["provider"], $request->user());
        } catch (ValidationException $e) {
            $headers = $this->reconciliation->headers($file);
            if (count($headers) < 2) {
                throw $e;
            }

            // Columns not recognised: keep the file and ask which column is which.
            $token = Str::random(32);
            Storage::disk("local")->putFileAs("statement-uploads", $file, $token . "." . strtolower($file->getClientOriginalExtension()));
            session()->put("statement_upload.$token", ["name" => $file->getClientOriginalName(), "ext" => strtolower($file->getClientOriginalExtension()), "provider" => $data["provider"], "headers" => $headers]);

            return redirect()->route("wallet-reconciliation.mapping", $token)->with("warning", $e->getMessage());
        }

        return redirect()->route("wallet-reconciliation.show", $statement)
            ->with("success", __("app.statement_imported", ["lines" => $statement->lines_count, "matched" => $statement->matched_count]));
    }

    public function mapping(string $token): View
    {
        $upload = session("statement_upload.$token");
        abort_unless($upload, 404);

        return view("mobile-payments.reconciliation.mapping", ["token" => $token, "upload" => $upload, "guesses" => WalletReconciliation::COLUMN_GUESSES]);
    }

    public function map(Request $request, string $token): RedirectResponse
    {
        $upload = session("statement_upload.$token");
        abort_unless($upload, 404);

        $data = $request->validate([
            "columns.ref" => "required|integer|min:0",
            "columns.amount" => "required|integer|min:0",
            "columns.date" => "nullable|integer|min:0",
            "columns.phone" => "nullable|integer|min:0",
        ]);

        $path = Storage::disk("local")->path("statement-uploads/{$token}.{$upload["ext"]}");
        abort_unless(is_file($path), 404);

        $statement = $this->reconciliation->import(new UploadedFile($path, $upload["name"], null, null, true), $upload["provider"], $request->user(), $data["columns"]);

        Storage::disk("local")->delete("statement-uploads/{$token}.{$upload["ext"]}");
        session()->forget("statement_upload.$token");

        return redirect()->route("wallet-reconciliation.show", $statement)
            ->with("success", __("app.statement_imported", ["lines" => $statement->lines_count, "matched" => $statement->matched_count]));
    }

    public function show(WalletStatement $statement): View
    {
        return view("mobile-payments.reconciliation.show", [
            "statement" => $statement->load("user:id,name"),
            "lines" => $statement->lines()->orderByRaw("CASE status WHEN 'amount_mismatch' THEN 0 WHEN 'unmatched' THEN 1 WHEN 'matched' THEN 2 ELSE 3 END")->orderBy("occurred_at")->get(),
            "missing" => $this->reconciliation->missingPayments($statement),
        ]);
    }

    public function ignore(WalletStatementLine $line): RedirectResponse
    {
        $line->update(["status" => $line->status === "ignored" ? "unmatched" : "ignored"]);

        return back()->with("success", __("app.statement_line_updated"));
    }
}
