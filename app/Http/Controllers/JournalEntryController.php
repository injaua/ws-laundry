<?php

namespace App\Http\Controllers;

use App\Http\Requests\JournalEntryRequest;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Support\Documents\ReportDownload;
use App\Support\Documents\ReportTable;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize("viewAny", JournalEntry::class);

        $query = $this->filtered($request);

        if ($format = ReportDownload::requested($request)) {
            return ReportDownload::make($this->journalTable($query->oldest("entry_date")->oldest("id")->get(), $request), $format);
        }

        $entries = $query->latest("entry_date")->latest("id")->paginate(15)->withQueryString();
        $accounts = Account::where("is_group", false)->orderBy("code")->get();

        return view("accounting.journal-entries.index", compact("entries", "accounts"));
    }

    /** The journal filtered like the index page (search, source, account, dates). */
    private function filtered(Request $request)
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

        if ($from = request_date("from")) {
            $query->whereDate("entry_date", ">=", $from->toDateString());
        }

        if ($to = request_date("to")) {
            $query->whereDate("entry_date", "<=", $to->toDateString());
        }

        return $query;
    }

    private function journalTable($entries, Request $request): ReportTable
    {
        $period = collect([request_date("from"), request_date("to")])->filter()->map(fn ($d) => $d->format(date_fmt()))->implode(" – ");
        $table = new ReportTable(__("app.journal_entries"), $period ?: __("app.all"), [
            [__("app.date"), "date"], [__("app.entry_number"), "text"], [__("app.description"), "text"], [__("app.source"), "text"],
            [__("app.code"), "text"], [__("app.account_name"), "text"], [__("app.debit"), "money"], [__("app.credit"), "money"],
        ], __("app.journal_entries") . " " . now()->format("Y-m-d"));

        $debits = $credits = [];
        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                $table->row([
                    $entry->entry_date->format(date_fmt()), $entry->entry_number, $entry->description, __("app.source_" . $entry->source),
                    $line->account?->code, $line->account?->name, (float) $line->debit ?: null, (float) $line->credit ?: null,
                ]);
                $debits[] = $line->debit;
                $credits[] = $line->credit;
            }
        }

        return $table->row(["", "", "", "", "", __("app.total"), Money::sum($debits), Money::sum($credits)], "total");
    }

    public function create()
    {
        Gate::authorize("create", JournalEntry::class);

        $accounts = Account::where("is_group", false)->where("is_active", true)->orderBy("code")->get();
        $defaults = [];

        return view("accounting.journal-entries.create", compact("accounts", "defaults"));
    }

    public function store(JournalEntryRequest $request)
    {
        // Header and lines together: an entry is never saved without its lines.
        DB::transaction(function () use ($request) {
            $entry = JournalEntry::create([
                ...$request->entryData(),
                "entry_number" => "TMP-" . Str::upper(Str::random(12)),
                "source" => "manual",
                "posted_by" => auth()->id(),
            ]);
            $entry->update(["entry_number" => JournalEntry::number($entry->id)]);

            $this->writeLines($entry, $request->pairs());
        });

        return redirect()->route("accounting.journal-entries.index")
            ->with("success", __("app.journal_created"));
    }

    public function edit(JournalEntry $journal_entry)
    {
        if (!$journal_entry->isManual()) {
            return redirect()->route("accounting.journal-entries.index")
                ->with("error", __("app.journal_auto_locked"));
        }

        Gate::authorize("update", $journal_entry);

        $accounts = Account::where("is_group", false)->orderBy("code")->get();

        return view("accounting.journal-entries.edit", [
            "entry" => $journal_entry,
            "accounts" => $accounts,
            "defaults" => $this->pairDefaults($journal_entry),
        ]);
    }

    public function update(JournalEntryRequest $request, JournalEntry $journal_entry)
    {
        if (!$journal_entry->isManual()) {
            return redirect()->route("accounting.journal-entries.index")
                ->with("error", __("app.journal_auto_locked"));
        }

        DB::transaction(function () use ($request, $journal_entry) {
            $journal_entry->update($request->entryData());
            $journal_entry->lines()->delete();
            $this->writeLines($journal_entry, $request->pairs());
        });

        return redirect()->route("accounting.journal-entries.index")
            ->with("success", __("app.journal_updated"));
    }

    public function destroy(JournalEntry $journal_entry)
    {
        if (!$journal_entry->isManual()) {
            return redirect()->route("accounting.journal-entries.index")
                ->with("error", __("app.journal_auto_locked"));
        }

        Gate::authorize("delete", $journal_entry);

        if ($period = AccountingPeriod::closedOn($journal_entry->entry_date)) {
            return redirect()->route("accounting.journal-entries.index")
                ->with("error", __("app.period_closed", ["period" => $period->label()]));
        }

        $journal_entry->delete();

        return redirect()->route("accounting.journal-entries.index")
            ->with("success", __("app.journal_deleted"));
    }

    private function writeLines(JournalEntry $entry, array $rows): void
    {
        foreach ($rows as $row) {
            $entry->lines()->create([
                "account_id" => $row["debit_account_id"],
                "debit" => $row["debit_amount"],
                "credit" => 0,
            ]);
            $entry->lines()->create([
                "account_id" => $row["credit_account_id"],
                "debit" => 0,
                "credit" => $row["credit_amount"],
            ]);
        }
    }

    private function pairDefaults(JournalEntry $entry): array
    {
        $debits = $entry->lines()->where("debit", ">", 0)->get();
        $credits = $entry->lines()->where("credit", ">", 0)->get();

        return $debits->map(fn ($line, $i) => [
            "debit_account_id" => $line->account_id,
            "debit_amount" => (float) $line->debit,
            "credit_account_id" => $credits[$i]->account_id ?? null,
            "credit_amount" => isset($credits[$i]) ? (float) $credits[$i]->credit : null,
        ])->values()->all();
    }
}
