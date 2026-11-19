<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Active login sessions (database session driver). */
class UserSessions
{
    public static function supported(): bool
    {
        return config("session.driver") === "database";
    }

    public function forUser(User $user, ?string $currentId = null): Collection
    {
        if (!self::supported()) {
            return collect();
        }

        return DB::table(config("session.table", "sessions"))->where("user_id", $user->id)->orderByDesc("last_activity")->get()
            ->map(fn ($s) => (object) [
                "id" => $s->id,
                "ip" => $s->ip_address,
                "agent" => self::describeAgent((string) $s->user_agent),
                "last_activity" => Carbon::createFromTimestamp($s->last_activity),
                "current" => $s->id === $currentId,
            ]);
    }

    /** Ends every session of the user except the current one (or all, when null). */
    public function revoke(User $user, ?string $exceptId = null): int
    {
        if (!self::supported()) {
            return 0;
        }

        return DB::table(config("session.table", "sessions"))->where("user_id", $user->id)
            ->when($exceptId, fn ($q) => $q->where("id", "!=", $exceptId))
            ->delete();
    }

    public static function describeAgent(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, "Edg/") => "Edge",
            str_contains($agent, "OPR/") => "Opera",
            str_contains($agent, "Chrome/") => "Chrome",
            str_contains($agent, "Firefox/") => "Firefox",
            str_contains($agent, "Safari/") => "Safari",
            default => __("app.unknown_browser"),
        };
        $os = match (true) {
            str_contains($agent, "Android") => "Android",
            str_contains($agent, "iPhone") || str_contains($agent, "iPad") => "iOS",
            str_contains($agent, "Windows") => "Windows",
            str_contains($agent, "Mac OS") => "macOS",
            str_contains($agent, "Linux") => "Linux",
            default => "",
        };

        return trim("$browser $os");
    }
}
