<?php

namespace App\Services\Ai\Tools;

use App\Models\CourierJob;
use App\Models\User;

class CourierRouteTool extends AiTool
{
    public function name(): string
    {
        return "courier_route";
    }

    public function description(): string
    {
        return "Pickups and deliveries of a day (default today): type, status, address, contact and order. A courier only sees their own jobs.";
    }

    public function ability(): ?string
    {
        return "drive-routes";
    }

    protected function properties(): array
    {
        return ["date" => ["type" => "string", "description" => "Day YYYY-MM-DD (default today)"]];
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        [$day] = self::period(["from" => $input["date"] ?? null, "to" => $input["date"] ?? null], today()->toImmutable());
        $query = CourierJob::with(["order:id,order_number", "courier:id,name"])->whereDate("scheduled_date", $day);
        if ($user->isCourier()) {
            $query->where("courier_id", $user->id);
        }
        $jobs = $query->orderBy("id")->limit(50)->get();

        return [
            "date" => $day->toDateString(),
            "by_status" => $jobs->countBy("status")->all(),
            "jobs" => $jobs->map(fn (CourierJob $j) => [
                "type" => $j->type,
                "status" => $j->status,
                "address" => $j->address,
                "contact" => $j->contact_name,
                "order" => $j->order?->order_number,
                "courier" => $j->courier?->name,
            ])->all(),
            "link" => $user->isCourier() ? route("courier.route") : route("logistics.index"),
        ];
    }
}
