<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        $query = JournalEntry::with(["lines.account", "poster"]);

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where("entry_number", "like", "%{$search}%")
                  ->orWhere("description", "like", "%{$search}%");
            });
        }

        if ($source = $request->source) {
            $query->where("source", $source);
        }

        if ($accountId = $request->account_id) {
            $query->whereHas("lines", fn ($q) => $q->where("account_id", $accountId));
        }

        if ($from = $request->from) {
            $query->whereDate("entry_date", ">=", $from);
        }

        if ($to = $request->to) {
            $query->whereDate("entry_date", "<=", $to);
        }

        $entries = $query->latest("entry_date")->latest("id")->paginate(15)->withQueryString();
        $accounts = Account::where("is_group", false)->orderBy("code")->get();

        return view("accounting.journal-entries.index", compact("entries", "accounts"));
    }

    public function create()
    {
        $accounts = Account::where("is_group", false)->where("is_active", true)->orderBy("code")->get();
        $defaults = [];

        return view("accounting.journal-entries.create", compact("accounts", "defaults"));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "entry_date" => "required|date",
            "description" => "required|string|max:255",
        ]);

        $rows = $this->collectPairs($request);

        if (($errors = $this->pairErrors($rows)) !== []) {
            return back()->withInput()->withErrors($errors);
        }

        $entry = JournalEntry::create([
            ...$validated,
            "entry_number" => "TMP-" . Str::upper(Str::random(12)),
            "source" => "manual",
            "posted_by" => auth()->id(),
        ]);
        $entry->update(["entry_number" => JournalEntry::number($entry->id)]);

        $this->writeLines($entry, $rows);

        return redirect()->route("accounting.journal-entries.index")
            ->with("success", __("app.journal_created"));
    }

    public function edit(JournalEntry $journal_entry)
    {
        if (!$journal_entry->isManual()) {
            return redirect()->route("accounting.journal-entries.index")
                ->with("error", __("app.journal_auto_locked"));
        }

        $accounts = Account::where("is_group", false)->orderBy("code")->get();

        return view("accounting.journal-entries.edit", [
            "entry" => $journal_entry,
            "accounts" => $accounts,
            "defaults" => $this->pairDefaults($journal_entry),
        ]);
    }

    public function update(Request $request, JournalEntry $journal_entry)
    {
        if (!$journal_entry->isManual()) {
            return redirect()->route("accounting.journal-entries.index")
                ->with("error", __("app.journal_auto_locked"));
        }

        $validated = $request->validate([
            "entry_date" => "required|date",
            "description" => "required|string|max:255",
        ]);

        $rows = $this->collectPairs($request);

        if (($errors = $this->pairErrors($rows)) !== []) {
            return back()->withInput()->withErrors($errors);
        }

        $journal_entry->update([...$validated, "posted_by" => auth()->id()]);
        $journal_entry->lines()->delete();
        $this->writeLines($journal_entry, $rows);

        return redirect()->route("accounting.journal-entries.index")
            ->with("success", __("app.journal_updated"));
    }

    public function destroy(JournalEntry $journal_entry)
    {
        if (!$journal_entry->isManual()) {
            return redirect()->route("accounting.journal-entries.index")
                ->with("error", __("app.journal_auto_locked"));
        }

        $journal_entry->delete();

        return redirect()->route("accounting.journal-entries.index")
            ->with("success", __("app.journal_deleted"));
    }

    private function collectPairs(Request $request): array
    {
        return collect($request->input("pairs", []))
            ->filter(fn ($row) => collect($row)->contains(fn ($v) => trim((string) $v) !== ""))
            ->values()
            ->all();
    }

    private function pairErrors(array $rows): array
    {
        if ($rows === []) {
            return ["pairs" => __("app.journal_rows_required")];
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($rows as $i => $row) {
            $debitAccount = isset($row["debit_account_id"]) ? Account::find($row["debit_account_id"]) : null;
            $creditAccount = isset($row["credit_account_id"]) ? Account::find($row["credit_account_id"]) : null;
            $debitAmount = (float) ($row["debit_amount"] ?? 0);
            $creditAmount = (float) ($row["credit_amount"] ?? 0);

            if (!$debitAccount?->isManualUsable()) {
                return ["pairs" => __("app.journal_account_invalid", ["row" => $i + 1])];
            }
            if (!$creditAccount?->isManualUsable()) {
                return ["pairs" => __("app.journal_account_invalid", ["row" => $i + 1])];
            }
            if ($debitAmount < 1 || $creditAmount < 1) {
                return ["pairs" => __("app.journal_amount_invalid", ["row" => $i + 1])];
            }
            if ($debitAccount->id === $creditAccount->id) {
                return ["pairs" => __("app.journal_same_account", ["row" => $i + 1])];
            }

            $totalDebit += $debitAmount;
            $totalCredit += $creditAmount;
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return ["pairs" => __("app.journal_not_balanced")];
        }

        return [];
    }

    private function writeLines(JournalEntry $entry, array $rows): void
    {
        foreach ($rows as $row) {
            $entry->lines()->create([
                "account_id" => $row["debit_account_id"],
                "debit" => (float) $row["debit_amount"],
                "credit" => 0,
            ]);
            $entry->lines()->create([
                "account_id" => $row["credit_account_id"],
                "debit" => 0,
                "credit" => (float) $row["credit_amount"],
            ]);
        }
    }

    private function pairDefaults(JournalEntry $entry): array
    {
        $debits = $entry->lines()->where("debit", ">", 0)->get();
        $credits = $entry->lines()->where("credit", ">", 0)->get();

        return $debits->map(fn ($line, $i) => [
            "debit_account_id" => $line->account_id,
            "debit_amount" => (int) $line->debit,
            "credit_account_id" => $credits[$i]->account_id ?? null,
            "credit_amount" => isset($credits[$i]) ? (int) $credits[$i]->credit : null,
        ])->values()->all();
    }
}
