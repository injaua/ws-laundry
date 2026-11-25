<?php

namespace App\Http\Controllers;

use App\Models\AiInsight;
use App\Models\AiInsightFeedback;
use App\Services\Ai\Insights\InsightGenerator;
use App\Support\CurrentStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Automatic insights for the owner (their store) and the admin (the company, and the store
 * worked in): alerts and daily briefings, with the switch to receive them or not.
 */
class InsightController extends Controller
{
    public const FILTERS = ["open", "briefing", "alert", "resolved", "all"];

    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = in_array($request->query("filter"), self::FILTERS, true) ? $request->query("filter") : "open";
        $severity = in_array($request->query("severity"), AiInsight::SEVERITIES, true) ? $request->query("severity") : null;

        $base = fn () => AiInsight::visibleTo($user, CurrentStore::id());
        $query = $base()->with("store:id,name");
        match ($filter) {
            "open" => $query->open(),
            "briefing", "alert" => $query->where("kind", $filter),
            "resolved" => $query->whereIn("status", ["resolved", "dismissed"]),
            default => null,
        };
        $query->when($severity, fn ($q) => $q->where("severity", $severity));

        $insights = $query->latest("period_date")->orderByRaw("FIELD(severity, 'critical', 'warning', 'info')")->latest("id")->paginate(20)->withQueryString();
        $feedback = AiInsightFeedback::where("user_id", $user->id)->whereIn("insight_id", $insights->pluck("id"))->pluck("useful", "insight_id");

        // Opening the page marks what it shows as seen.
        $base()->whereIn("id", $insights->pluck("id"))->where("status", "new")->update(["status" => "seen"]);

        return view("insights.index", [
            "insights" => $insights,
            "feedback" => $feedback,
            "filter" => $filter,
            "severity" => $severity,
            "counts" => [
                "open" => $base()->open()->count(),
                "critical" => $base()->open()->where("severity", "critical")->count(),
            ],
            "enabled" => (bool) $user->ai_insights_enabled,
        ]);
    }

    /** Receive insights or not (the owner, and the admin). */
    public function toggle(Request $request): RedirectResponse
    {
        $user = $request->user();
        $current = (bool) $user->newQuery()->whereKey($user->id)->value("ai_insights_enabled");
        $user->forceFill(["ai_insights_enabled" => !$current])->save();

        return back()->with("success", __($user->ai_insights_enabled ? "app.ai_insights_on" : "app.ai_insights_off"));
    }

    public function status(Request $request, AiInsight $insight): RedirectResponse
    {
        $this->authorizeInsight($request, $insight);
        $data = $request->validate(["status" => ["required", Rule::in(["resolved", "dismissed", "seen"])]]);
        $insight->update(["status" => $data["status"]]);

        return back()->with("success", __("app.ai_insight_updated"));
    }

    public function feedback(Request $request, AiInsight $insight): RedirectResponse
    {
        $this->authorizeInsight($request, $insight);
        $data = $request->validate(["useful" => ["required", "boolean"]]);
        AiInsightFeedback::updateOrCreate(
            ["insight_id" => $insight->id, "user_id" => $request->user()->id],
            ["useful" => (bool) $data["useful"]],
        );

        return back()->with("success", __("app.ai_feedback_thanks"));
    }

    /** Create today's insights now (instead of waiting for the 06:00 run). */
    public function generate(Request $request, InsightGenerator $generator): RedirectResponse
    {
        @set_time_limit(300);
        $user = $request->user();
        $store = CurrentStore::store();

        // The admin: every store and the company briefing; the owner: their store.
        if ($user->isAdmin()) {
            $result = $generator->run(CarbonImmutable::today());
        } else {
            abort_unless($store !== null, 403);
            $result = $generator->run(CarbonImmutable::today(), only: $store);
        }

        return back()->with("success", __("app.ai_insights_generated", ["count" => $result["insights"]]));
    }

    private function authorizeInsight(Request $request, AiInsight $insight): void
    {
        abort_unless(AiInsight::visibleTo($request->user(), CurrentStore::id())->whereKey($insight->id)->exists(), 404);
    }
}
