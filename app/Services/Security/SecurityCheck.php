<?php

namespace App\Services\Security;

use App\Models\ActivityLog;
use App\Models\User;

/** Configuration review: what is safe, what needs attention before / in production. */
class SecurityCheck
{
    /**
     * @return array<int, array{key: string, ok: bool, level: string, detail: ?string}>
     *                                                                                  level: "critical" | "warning" | "info"
     */
    public function run(): array
    {
        $production = app()->environment("production");
        $https = str_starts_with((string) config("app.url"), "https://");
        $admins = User::whereIn("role", ["admin", "owner"])->where("is_active", true)->get();
        $withoutTwoFactor = $admins->reject->hasTwoFactor();
        $backup = Backups::supported() ? app(Backups::class)->latest() : null;
        $chain = ActivityLog::verifyChain();
        $required = SecuritySettings::twoFactorRoles();

        return [
            ["key" => "debug_off", "ok" => !config("app.debug"), "level" => $production ? "critical" : "warning", "detail" => null],
            ["key" => "env_production", "ok" => $production, "level" => "warning", "detail" => app()->environment()],
            ["key" => "https", "ok" => $https, "level" => $production ? "critical" : "warning", "detail" => config("app.url")],
            ["key" => "secure_cookie", "ok" => (bool) config("session.secure") || !$https && !$production, "level" => "warning", "detail" => null],
            ["key" => "app_key", "ok" => filled(config("app.key")), "level" => "critical", "detail" => null],
            ["key" => "two_factor_admins", "ok" => $withoutTwoFactor->isEmpty(), "level" => "warning", "detail" => $withoutTwoFactor->pluck("name")->implode(", ") ?: null],
            ["key" => "two_factor_required", "ok" => in_array("admin", $required, true) && in_array("owner", $required, true), "level" => "info", "detail" => $required ? implode(", ", $required) : null],
            ["key" => "idle_timeout", "ok" => SecuritySettings::idleMinutes() > 0 && SecuritySettings::idleMinutes() <= 120, "level" => "warning", "detail" => (string) SecuritySettings::idleMinutes()],
            ["key" => "backup_recent", "ok" => $backup !== null && $backup->created_at->gt(now()->subDays(2)), "level" => "critical", "detail" => $backup?->created_at->format(datetime_fmt())],
            ["key" => "audit_chain", "ok" => $chain["ok"], "level" => "critical", "detail" => $chain["ok"] ? (string) $chain["checked"] : "#" . $chain["broken_id"]],
        ];
    }

    public function score(array $checks): array
    {
        $failed = collect($checks)->reject(fn ($c) => $c["ok"]);

        return [
            "critical" => $failed->where("level", "critical")->count(),
            "warning" => $failed->where("level", "warning")->count(),
            "ok" => collect($checks)->where("ok", true)->count(),
            "total" => count($checks),
        ];
    }
}
