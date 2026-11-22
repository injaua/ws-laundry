<?php

namespace App\Http\Controllers;

use App\Models\EmployeeSalary;
use App\Models\SiteSetting;
use App\Support\CurrentStore;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pagamentos ao Estado: what the store owes the State for a month and what it has paid.
 *  - IVA: output VAT (4.4.3.3) minus deductible VAT (4.4.3.2 / legacy 4.4.3.1) booked by the
 *    month's documents, plus what was already settled into 4.4.3.7 IVA a pagar and not paid.
 *  - INSS: worked out from the month's payroll — the employee's share (withheld) and the
 *    employer's share, both on the remuneration (base + bonus − absences).
 * Payments are the debits on 4.4.3.7 and 4.4.9 (manual journal entries against Caixa/Banco).
 */
class StatePaymentsController extends Controller
{
    private const OUTPUT_VAT = ["4.4.3.3"];

    private const INPUT_VAT = ["4.4.3.2", "4.4.3.1"];

    private const VAT_PAYABLE = "4.4.3.7";

    private const INSS = "4.4.9";

    public function index(Request $request)
    {
        $month = min(12, max(1, (int) ($request->query("month") ?? now()->month)));
        $year = min(2100, max(2000, (int) ($request->query("year") ?? now()->year)));
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        // VAT of the month from the documents themselves (manual entries, such as the
        // settlement that moves these balances to 4.4.3.7, are left out).
        $outputVat = $this->movement(self::OUTPUT_VAT, $start, $end, creditNatural: true);
        $inputVat = $this->movement(self::INPUT_VAT, $start, $end, creditNatural: false);
        $monthVat = Money::sub($outputVat, $inputVat);
        $vatPayableBalance = $this->balance(self::VAT_PAYABLE, $end, creditNatural: true);

        $employeeRate = (float) SiteSetting::get("inss_employee_rate", 3);
        $employerRate = (float) SiteSetting::get("inss_employer_rate", 4);
        $inssRows = $this->inssRows($year, $month, $employeeRate, $employerRate);
        $inss = [
            "base" => Money::sum($inssRows->pluck("base")),
            "employee" => Money::sum($inssRows->pluck("employee")),
            "employer" => Money::sum($inssRows->pluck("employer")),
        ];
        $inss["total"] = Money::add($inss["employee"], $inss["employer"]);
        $inssBalance = $this->balance(self::INSS, $end, creditNatural: true);

        $payments = $this->payments($start, $end);
        $paidVat = Money::sum($payments->where("code", self::VAT_PAYABLE)->pluck("amount"));
        $paidInss = Money::sum($payments->where("code", self::INSS)->pluck("amount"));

        return view("accounting.state-payments", [
            "month" => $month,
            "year" => $year,
            "outputVat" => $outputVat,
            "inputVat" => $inputVat,
            "monthVat" => $monthVat,
            "vatPayableBalance" => $vatPayableBalance,
            "vatDue" => $end->copy()->addMonthNoOverflow()->endOfMonth(),
            "employeeRate" => $employeeRate,
            "employerRate" => $employerRate,
            "inssRows" => $inssRows,
            "inss" => $inss,
            "inssBalance" => $inssBalance,
            "inssDue" => $end->copy()->addDay()->setDay(10),
            "payments" => $payments,
            "paidVat" => $paidVat,
            "paidInss" => $paidInss,
        ]);
    }

    /** @return Collection<int, object{name: string, status: string, base: float, employee: float, employer: float, total: float}> */
    private function inssRows(int $year, int $month, float $employeeRate, float $employerRate): Collection
    {
        return EmployeeSalary::with("employee:id,name")
            ->where("period_year", $year)
            ->where("period_month", $month)
            ->get()
            ->map(function (EmployeeSalary $salary) use ($employeeRate, $employerRate) {
                $base = Money::max(0, Money::sub(Money::add($salary->base_salary, $salary->bonus), $salary->absence_deduction));
                $employee = Money::percent($base, $employeeRate);
                $employer = Money::percent($base, $employerRate);

                return (object) [
                    "name" => $salary->employee?->name ?? "—",
                    "status" => $salary->status,
                    "base" => $base,
                    "employee" => $employee,
                    "employer" => $employer,
                    "total" => Money::add($employee, $employer),
                ];
            })
            ->sortBy("name")
            ->values();
    }

    /** Net movement of the accounts in [start, end], automatic entries only. */
    private function movement(array $codes, Carbon $start, Carbon $end, bool $creditNatural): float
    {
        $sums = $this->lines($codes)
            ->where("journal_entries.source", "!=", "manual")
            ->whereBetween("journal_entries.entry_date", [$start->toDateTimeString(), $end->copy()->endOfDay()->toDateTimeString()])
            ->selectRaw("COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c")
            ->first();

        $difference = Money::sub($sums->c, $sums->d);

        return $creditNatural ? $difference : -$difference;
    }

    /** Balance of the account up to $end (all entries). */
    private function balance(string $code, Carbon $end, bool $creditNatural): float
    {
        $sums = $this->lines([$code])
            ->where("journal_entries.entry_date", "<=", $end->copy()->endOfDay()->toDateTimeString())
            ->selectRaw("COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c")
            ->first();

        $difference = Money::sub($sums->c, $sums->d);

        return $creditNatural ? $difference : -$difference;
    }

    /**
     * Payments to the State in the month: debits on IVA a pagar / INSS in entries that pay
     * out of cash or bank (a class 1 credit) — so the VAT settlement itself is not counted.
     */
    private function payments(Carbon $start, Carbon $end): Collection
    {
        return $this->lines([self::VAT_PAYABLE, self::INSS])
            ->where("journal_lines.debit", ">", 0)
            ->whereExists(function ($q) {
                $q->selectRaw("1")
                    ->from("journal_lines as paid_from")
                    ->join("accounts as cash", "cash.id", "=", "paid_from.account_id")
                    ->whereColumn("paid_from.journal_entry_id", "journal_entries.id")
                    ->where("paid_from.credit", ">", 0)
                    ->where(fn ($c) => $c->where("cash.code", "1")->orWhere("cash.code", "like", "1.%"));
            })
            ->whereBetween("journal_entries.entry_date", [$start->toDateTimeString(), $end->copy()->endOfDay()->toDateTimeString()])
            ->orderBy("journal_entries.entry_date")
            ->orderBy("journal_entries.id")
            ->get(["accounts.code", "accounts.name", "journal_entries.entry_number", "journal_entries.description", "journal_entries.entry_date", "journal_lines.debit as amount"])
            ->map(function ($row) {
                $row->amount = (float) $row->amount;

                return $row;
            });
    }

    private function lines(array $codes)
    {
        return DB::table("journal_lines")
            ->join("journal_entries", "journal_entries.id", "=", "journal_lines.journal_entry_id")
            ->join("accounts", "accounts.id", "=", "journal_lines.account_id")
            ->where("journal_entries.store_id", CurrentStore::id())
            ->whereIn("accounts.code", $codes);
    }
}
