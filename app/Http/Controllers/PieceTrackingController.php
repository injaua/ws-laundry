<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPiece;
use App\Models\OrderPieceEvent;
use App\Services\PieceTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Milon\Barcode\DNS1D;

/** Piece-by-piece tracking: the scan station, piece tags and per-piece details. */
class PieceTrackingController extends Controller
{
    public function __construct(private PieceTracker $tracker) {}

    /** Scan station: pick the stage, then scan tags (USB reader, camera or typing). */
    public function index(): View
    {
        Gate::authorize("viewAny", Order::class);

        $today = OrderPieceEvent::whereDate("created_at", today())->whereNotNull("from_status");

        return view("tracking.index", [
            "stages" => Order::STATUSES,
            "todayByStage" => (clone $today)->selectRaw("to_status, count(*) as total")->groupBy("to_status")->pluck("total", "to_status"),
            "inProgress" => OrderPiece::whereHas("order", fn ($q) => $q->whereNull("deleted_at")->where("status", "!=", "completed"))
                ->selectRaw("status, count(*) as total")->groupBy("status")->pluck("total", "status"),
            "withIssues" => OrderPiece::with("order:id,order_number")->whereNotNull("issue")->where("status", "!=", "completed")
                ->whereHas("order", fn ($q) => $q->whereNull("deleted_at"))->latest("updated_at")->limit(10)->get(),
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        Gate::authorize("viewAny", Order::class);

        // The station talks JSON; this app only renders validation errors as JSON under api/*.
        $validator = Validator::make($request->all(), [
            "code" => "required|string|max:60",
            "stage" => ["required", Rule::in(Order::STATUSES)],
        ]);
        if ($validator->fails()) {
            return response()->json([
                "ok" => false,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
                "warnings" => [],
                "order" => null,
            ], 422);
        }
        $data = $validator->validated();

        $result = $this->tracker->scan($data["code"], $data["stage"], $request->user());
        $order = $result["order"];

        return response()->json([
            "ok" => $result["ok"],
            "message" => $result["message"],
            "warnings" => $result["warnings"],
            "order" => $order ? [
                "number" => $order->order_number,
                "customer" => $order->customer?->name,
                "status" => __("app.status_" . $order->status),
                "url" => route("orders.show", $order),
                "progress" => $this->progress($order),
            ] : null,
        ], $result["ok"] ? 200 : 422);
    }

    /** History of one piece, for the lookup mode of the station. */
    public function lookup(Request $request): JsonResponse
    {
        Gate::authorize("viewAny", Order::class);

        $piece = OrderPiece::findByCode((string) $request->query("code"));
        if (!$piece) {
            return response()->json(["message" => __("app.scan_unknown_code", ["code" => mb_strtoupper(trim((string) $request->query("code")))])], 404);
        }

        $piece->load(["order.customer", "events.user:id,name"]);

        return response()->json([
            "code" => $piece->code,
            "description" => trim($piece->description . " " . $piece->color . " " . $piece->brand),
            "status" => __("app.status_" . $piece->status),
            "intake_notes" => $piece->intake_notes,
            "issue" => $piece->issue,
            "order" => [
                "number" => $piece->order->order_number,
                "customer" => $piece->order->customer?->name,
                "url" => route("orders.show", $piece->order),
                "progress" => $this->progress($piece->order),
            ],
            "events" => $piece->events->map(fn ($e) => [
                "at" => $e->created_at->format("d/m H:i"),
                "stage" => __("app.status_" . $e->to_status),
                "user" => $e->user?->name,
                "note" => $e->note,
            ]),
        ]);
    }

    /** Add pieces: e.g. the 14 garments of a 6 kg wash, counted at the counter. */
    public function store(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize("update", $order);

        $data = $request->validate([
            "order_item_id" => ["nullable", "integer", Rule::exists("order_items", "id")->where("order_id", $order->id)],
            "count" => "required|integer|min:1|max:" . PieceTracker::MAX_BATCH,
            "description" => "nullable|string|max:120",
        ]);

        $pieces = $this->tracker->add(
            $order,
            $order->items()->find($data["order_item_id"] ?? null),
            (int) $data["count"],
            $data["description"] ?? null,
            $request->user(),
        );

        return redirect()->route("orders.show", $order)->withFragment("pieces")
            ->with("success", __("app.pieces_added", ["count" => $pieces->count()]));
    }

    /** Details of a piece (description, colour, damage at intake, problem) and manual stage change. */
    public function update(Request $request, Order $order, OrderPiece $piece): RedirectResponse
    {
        Gate::authorize("update", $order);

        $data = $request->validate([
            "description" => "required|string|max:120",
            "color" => "nullable|string|max:40",
            "brand" => "nullable|string|max:60",
            "intake_notes" => "nullable|string|max:1000",
            "issue" => "nullable|string|max:255",
            "status" => ["nullable", Rule::in(Order::STATUSES)],
        ]);

        $piece->update([
            "description" => $data["description"],
            "color" => $data["color"] ?? null,
            "brand" => $data["brand"] ?? null,
            "intake_notes" => $data["intake_notes"] ?? null,
            "issue" => $data["issue"] ?? null,
        ]);

        $redirect = redirect()->route("orders.show", $order)->withFragment("pieces");

        if (!empty($data["status"]) && $data["status"] !== $piece->status) {
            $result = $this->tracker->move($piece, $data["status"], $request->user(), __("app.piece_event_manual"));
            if (!$result["ok"]) {
                return $redirect->with("error", $result["message"]);
            }
            if ($result["warnings"]) {
                $redirect->with("warning", implode(" ", $result["warnings"]));
            }
        }

        return $redirect->with("success", __("app.piece_saved", ["code" => $piece->code]));
    }

    /** Remove a piece created by mistake. Management only, never after delivery. */
    public function destroy(Order $order, OrderPiece $piece): RedirectResponse
    {
        Gate::authorize("edit", $order);

        if ($order->status === "completed") {
            return back()->with("error", __("app.order_locked_error"));
        }

        $code = $piece->code;
        $piece->delete();

        return redirect()->route("orders.show", $order)->withFragment("pieces")
            ->with("success", __("app.piece_deleted", ["code" => $code]));
    }

    /** Tags to attach to each garment: all pieces, or one (?piece=ID). */
    public function labels(Request $request, Order $order): View
    {
        Gate::authorize("view", $order);

        $pieces = $order->pieces()
            ->when($request->integer("piece"), fn ($q, $id) => $q->whereKey($id))
            ->get();

        $barcode = new DNS1D;
        $barcodes = $pieces->mapWithKeys(function ($piece) use ($barcode) {
            try {
                return [$piece->id => $barcode->getBarcodeSVG($piece->code, "C128", 1.4, 34, "black", false)];
            } catch (\Throwable) {
                return [$piece->id => null];
            }
        });

        return view("tracking.labels", [
            "order" => $order->load("customer"),
            "pieces" => $pieces,
            "barcodes" => $barcodes,
            "total" => $order->pieces()->count(),
            "thermal" => $request->query("type") === "thermal",
        ]);
    }

    /** "8/12 prontas" and the count per stage. */
    private function progress(Order $order): array
    {
        $pieces = $order->pieces()->get(["id", "status"]);

        return [
            "total" => $pieces->count(),
            "ready" => $pieces->filter->isReady()->count(),
            "stages" => collect($this->tracker->stageCounts($pieces))
                ->map(fn ($count, $stage) => ["label" => __("app.status_" . $stage), "count" => $count])->values(),
        ];
    }
}
