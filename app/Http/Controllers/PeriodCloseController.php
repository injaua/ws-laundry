<?php

namespace App\Http\Controllers;

use App\Exports\TrialBalanceExport;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\TrialBalanceArchive;
use App\Services\TrialBalanceService;
use App\Support\CurrentStore;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PeriodCloseController extends Controller
{
    public function index(Request $request)
    {
        $year = min(2100, max(2000, (int) $request->query("year", now()->year)));

        $periods = AccountingPeriod::where("year", $year)->get()->keyBy("month");
        $archives = TrialBalanceArchive::where("year", $year)->get()->keyBy("month");

        $entries = DB::table("journal_entries")
            ->where("store_id", CurrentStore::id())
            ->whereBetween("entry_date", [
                Carbon::create($year, 1, 1)->toDateString(),
                Carbon::create($year, 12, 31)->toDateString(),
            ])
            ->get(["id", "entry_date", "source", "description"]);

        $byMonth = $entries->groupBy(fn ($e) => (int) Carbon::parse($e->entry_date)->month);
        $label = fn (int $m) => sprintf("%02d/%04d", $m, $year);

        $next = $this->nextCloseable();

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $group = $byMonth->get($m, collect());
            $months[$m] = [
                "period" => $periods->get($m),
                "archive" => $archives->get($m),
                "entries" => $group->count(),
                "vat_settled" => $group
                    ->filter(fn ($e) => $e->source === "manual" && str_contains((string) $e->description, $label($m)))
                    ->isNotEmpty(),
                "closable" => $next && $next["year"] === $year && $next["month"] === $m,
            ];
        }

        $allArchives = TrialBalanceArchive::with("period.closer")->orderByDesc("year")->orderByDesc("month")->limit(24)->get();

        return view("accounting.period-close.index", compact("year", "months", "allArchives", "next"));
    }

    public function close(Request $request)
    {
        $validated = $request->validate([
            "month" => "required|integer|min:1|max:12",
            "year" => "required|integer|min:2000|max:2100",
        ]);

        [$month, $year] = [(int) $validated["month"], (int) $validated["year"]];
        $target = Carbon::create($year, $month, 1);

        if ($target->gt(now())) {
            return back()->with("error", __("app.period_future"));
        }

        $next = $this->nextCloseable();
        if (!$next || $next["year"] !== $year || $next["month"] !== $month) {
            return back()->with("error", __("app.period_not_sequential"));
        }

        $period = AccountingPeriod::firstOrNew(["year" => $year, "month" => $month]);
        $period->fill([
            "status" => "closed",
            "closed_by" => auth()->id(),
            "closed_at" => now(),
            "reopened_by" => null,
            "reopened_at" => null,
        ]);
        $period->save();

        $archive = app(TrialBalanceService::class)->archive($period);

        return redirect()->route("accounting.period-close.archive", $archive)
            ->with("success", __("app.period_closed_ok", ["period" => $period->label()]));
    }

    public function show(TrialBalanceArchive $archive)
    {
        $archive->load(["lines", "period.closer", "creator"]);

        return view("accounting.period-close.archive", compact("archive"));
    }

    public function export(TrialBalanceArchive $archive)
    {
        return Excel::download(
            new TrialBalanceExport($archive),
            sprintf("trial-balance-%04d-%02d.xlsx", $archive->year, $archive->month)
        );
    }

    public function reopen(AccountingPeriod $period)
    {
        if (!$period->isClosed()) {
            return redirect()->route("accounting.period-close")
                ->with("error", __("app.period_not_closed"));
        }

        DB::transaction(function () use ($period) {
            $period->archive?->delete();
            $period->update([
                "status" => "reopened",
                "reopened_by" => auth()->id(),
                "reopened_at" => now(),
            ]);
        });

        return redirect()->route("accounting.period-close", ["year" => $period->year])
            ->with("success", __("app.period_reopened", ["period" => $period->label()]));
    }

    /**
     * First month (from the year of the oldest journal entry) still open, or null
     * when nothing can be closed yet (future).
     */
    private function nextCloseable(): ?array
    {
        $oldest = JournalEntry::orderBy("entry_date")->value("entry_date");
        $startYear = $oldest ? Carbon::parse($oldest)->year : now()->year;

        $closed = AccountingPeriod::where("status", "closed")
            ->get()
            ->map(fn ($p) => $p->year * 100 + $p->month)
            ->flip();

        $cursor = Carbon::create($startYear, 1, 1);
        $last = Carbon::create(now()->year, now()->month, 1);

        while ($cursor->lte($last)) {
            if (!isset($closed[$cursor->year * 100 + $cursor->month])) {
                return ["year" => $cursor->year, "month" => $cursor->month];
            }
            $cursor->addMonthNoOverflow();
        }

        return null;
    }
}
