<?php

namespace App\View\Composers;

use App\Models\Order;
use App\Services\PieceTracker;
use Illuminate\View\View;

/** Piece-by-piece tracking box of an order (orders._pieces). Needs $order. */
class PieceTrackingComposer
{
    public const STAGE_COLORS = [
        "received" => "secondary", "washing" => "info", "drying" => "info", "ironing" => "primary",
        "folding" => "primary", "ready" => "success", "delivering" => "warning", "completed" => "dark",
    ];

    public function __construct(private PieceTracker $tracker) {}

    public function compose(View $view): void
    {
        /** @var Order $order */
        $order = $view->getData()["order"];
        $pieces = $order->pieces;

        $view->with([
            "pieces" => $pieces,
            "readyCount" => $pieces->filter->isReady()->count(),
            "stageCounts" => $this->tracker->stageCounts($pieces),
            "stageColors" => self::STAGE_COLORS,
            "canManage" => (bool) auth()->user()?->can("edit", $order),
        ]);
    }
}
