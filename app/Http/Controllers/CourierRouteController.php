<?php

namespace App\Http\Controllers;

use App\Models\CourierJob;
use App\Models\Payment;
use App\Services\Logistics;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The courier's day on the phone: today's trips (plus anything left over), and
 * the actions on the road. Counter staff can record the same actions on a
 * courier's behalf; couriers only act on trips assigned to them.
 */
class CourierRouteController extends Controller
{
    public function __construct(private Logistics $logistics) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $jobs = CourierJob::with(["customer:id,name,phone", "order", "order.customer", "zone:id,name"])
            ->where("courier_id", $user->id)
            ->whereIn("status", ["scheduled", "on_the_way"])
            ->whereDate("scheduled_date", "<=", today())
            ->orderBy("scheduled_date")
            ->orderByRaw("CASE status WHEN 'on_the_way' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE time_window WHEN 'morning' THEN 1 WHEN 'afternoon' THEN 2 WHEN 'evening' THEN 3 ELSE 4 END")
            ->orderBy("id")
            ->get();

        $doneToday = CourierJob::with(["customer:id,name", "order:id,order_number"])
            ->where("courier_id", $user->id)
            ->whereIn("status", ["done", "failed"])
            ->where(fn ($q) => $q->whereDate("completed_at", today())->orWhere(fn ($q) => $q->where("status", "failed")->whereDate("updated_at", today())))
            ->latest("updated_at")->get();

        $upcoming = CourierJob::with(["customer:id,name", "zone:id,name"])
            ->where("courier_id", $user->id)->where("status", "scheduled")
            ->whereDate("scheduled_date", ">", today())->whereDate("scheduled_date", "<=", today()->addDays(7))
            ->orderBy("scheduled_date")->get();

        $cashHeld = (float) Payment::pendingHandover()->where("user_id", $user->id)->sum("amount");

        return view("logistics.my-route", compact("jobs", "doneToday", "upcoming", "cashHeld"));
    }

    public function start(Request $request, CourierJob $courierJob)
    {
        $this->authorizeJob($request, $courierJob);
        $this->logistics->start($courierJob, $request->user());

        return $this->back($request, $courierJob, __("app.courier_on_the_way_saved"));
    }

    public function complete(Request $request, CourierJob $courierJob)
    {
        $this->authorizeJob($request, $courierJob);

        if ($courierJob->isPickup()) {
            $data = $request->validate(["outcome_notes" => "nullable|string|max:500"]);
            $this->logistics->completePickup($courierJob, $request->user(), $data["outcome_notes"] ?? null);

            return $this->back($request, $courierJob, __("app.pickup_done_saved"));
        }

        $data = $request->validate([
            "amount" => "nullable|numeric|min:0",
            "method" => ["nullable", Rule::in(Payment::courierMethods())],
            "outcome_notes" => "nullable|string|max:500",
        ] + (((float) $request->input("amount", 0)) > 0 ? Payment::referenceRules() : []));
        if ((float) ($data["amount"] ?? 0) > 0) {
            $data = Payment::validateReference($data);
        }

        $result = $this->logistics->completeDelivery(
            $courierJob,
            $request->user(),
            (float) ($data["amount"] ?? 0),
            $data["method"] ?? null,
            $data["outcome_notes"] ?? null,
            $data["transaction_ref"] ?? null,
            $data["payer_phone"] ?? null,
        );

        $redirect = $this->back($request, $courierJob, __("app.delivery_done_saved"));

        return $result["warnings"] ? $redirect->with("warning", implode(" ", $result["warnings"])) : $redirect;
    }

    public function fail(Request $request, CourierJob $courierJob)
    {
        $this->authorizeJob($request, $courierJob);
        $data = $request->validate(["failure_reason" => "required|string|max:255"]);

        $this->logistics->fail($courierJob, $request->user(), $data["failure_reason"]);

        return $this->back($request, $courierJob, __("app.courier_job_failed_saved"));
    }

    private function authorizeJob(Request $request, CourierJob $job): void
    {
        $user = $request->user();

        abort_if($user->isCourier() && (int) $job->courier_id !== (int) $user->id, 403);
    }

    private function back(Request $request, CourierJob $job, string $message)
    {
        return ($request->user()->isCourier()
            ? redirect()->route("courier.route")
            : redirect()->route("logistics.show", $job))->with("success", $message);
    }
}
