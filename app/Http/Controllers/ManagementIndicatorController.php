<?php

namespace App\Http\Controllers;

use App\Services\ManagementIndicators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Management indicators: KPIs by area, against the previous period and the targets. */
class ManagementIndicatorController extends Controller
{
    public const PRESETS = ["this_month", "last_month", "last_30_days", "this_quarter", "this_year", "custom"];

    public function __construct(private ManagementIndicators $indicators) {}

    public function index(Request $request): View
    {
        [$from, $to, $preset] = $this->period($request);

        return view("management.index", [
            "data" => $this->indicators->build($from, $to),
            "trend" => $this->indicators->monthlyTrend(12),
            "preset" => $preset,
            "presets" => self::PRESETS,
        ]);
    }

    public function targets(): View
    {
        return view("management.targets", [
            "targets" => ManagementIndicators::targets(),
            "indicators" => ManagementIndicators::INDICATORS,
        ]);
    }

    public function saveTargets(Request $request): RedirectResponse
    {
        $data = $request->validate([
            "targets" => "nullable|array",
            "targets.*" => "nullable|numeric|min:0|max:1000000000000",
        ]);

        ManagementIndicators::saveTargets($data["targets"] ?? []);

        return redirect()->route("management.index")->with("success", __("app.kpi_targets_saved"));
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $data = $this->indicators->build($from, $to);
        $filename = "indicadores-" . $from->format("Ymd") . "-" . $to->format("Ymd") . ".csv";

        return response()->streamDownload(function () use ($data) {
            $out = fopen("php://output", "w");
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, [__("app.kpi_group"), __("app.indicator"), __("app.value"), __("app.previous_period"), __("app.change_pct"), __("app.target"), __("app.status")], ";");
            foreach ($data["indicators"] as $key => $kpi) {
                fputcsv($out, [
                    __("app.kpi_group_" . $kpi["group"]),
                    __("app.kpi_" . $key),
                    $kpi["value"],
                    $kpi["previous"],
                    $kpi["change"],
                    $kpi["target"],
                    $kpi["status"] ? __("app.kpi_status_" . $kpi["status"]) : "",
                ], ";");
            }
            fclose($out);
        }, $filename, ["Content-Type" => "text/csv; charset=UTF-8"]);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function period(Request $request): array
    {
        $preset = in_array($request->input("period"), self::PRESETS, true) ? $request->input("period") : "this_month";

        [$from, $to] = match ($preset) {
            "last_month" => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            "last_30_days" => [today()->subDays(29), today()],
            "this_quarter" => [today()->firstOfQuarter(), today()],
            "this_year" => [today()->startOfYear(), today()],
            "custom" => [
                $request->filled("from") ? Carbon::parse($request->input("from")) : today()->startOfMonth(),
                $request->filled("to") ? Carbon::parse($request->input("to")) : today(),
            ],
            default => [today()->startOfMonth(), today()],
        };

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->copy()->startOfDay(), $to->copy()->startOfDay(), $preset];
    }
}
