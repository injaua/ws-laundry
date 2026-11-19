<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Security\Backups;
use App\Services\Security\SecurityCheck;
use App\Services\Security\SecuritySettings;
use App\Services\Security\UserSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Security & compliance: configuration review, policies, backups, staff accounts. */
class SecurityController extends Controller
{
    public function index(SecurityCheck $check, Backups $backups): View
    {
        $checks = $check->run();

        return view("admin.security.index", [
            "checks" => $checks,
            "score" => $check->score($checks),
            "backups" => Backups::supported() ? $backups->list() : collect(),
            "backupsSupported" => Backups::supported(),
            "users" => User::orderByRaw("is_active desc")->orderBy("role")->orderBy("name")->get(),
            "twoFactorRoles" => SecuritySettings::twoFactorRoles(),
            "idleMinutes" => SecuritySettings::idleMinutes(),
            "backupRetention" => SecuritySettings::backupRetentionDays(),
            "fiscalRetention" => SecuritySettings::fiscalRetentionYears(),
            "events" => ActivityLog::with("actor")->whereIn("action", ActivityLog::SECURITY_ACTIONS)->latest("id")->limit(15)->get(),
            "failedLast24h" => ActivityLog::where("action", "login_failed")->where("created_at", ">=", now()->subDay())->count(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            "two_factor_roles" => "nullable|array",
            "two_factor_roles.*" => [Rule::in(SecuritySettings::ROLES)],
            "idle_minutes" => "required|integer|min:0|max:1440",
            "backup_retention_days" => "required|integer|min:1|max:3650",
            "fiscal_retention_years" => "required|integer|min:1|max:30",
        ]);

        SecuritySettings::save($data);

        return back()->with("success", __("app.security_settings_saved"));
    }

    public function runBackup(Request $request, Backups $backups): RedirectResponse
    {
        try {
            $result = $backups->run();
        } catch (Throwable $e) {
            return back()->with("error", __("app.backup_failed", ["error" => $e->getMessage()]));
        }

        ActivityLog::security("backup_created", null, "Backup created: {$result["file"]}", $request->user());

        return back()->with("success", __("app.backup_created", ["file" => $result["file"]]));
    }

    public function downloadBackup(Request $request, Backups $backups, string $name): BinaryFileResponse
    {
        $path = $backups->path($name);
        abort_unless($path, 404);

        ActivityLog::security("backup_downloaded", null, "Backup downloaded: {$name}", $request->user());

        return response()->download($path, $name);
    }

    public function resetTwoFactor(Request $request, User $user): RedirectResponse
    {
        TwoFactorController::clear($user);
        app(UserSessions::class)->revoke($user);
        ActivityLog::security("two_factor_disabled", $user, "Two-factor reset by administrator: {$user->email}", $request->user());

        return back()->with("success", __("app.two_factor_reset_done", ["name" => $user->name]));
    }

    public function revokeSessions(Request $request, User $user, UserSessions $sessions): RedirectResponse
    {
        $count = $sessions->revoke($user, $user->is($request->user()) ? $request->session()->getId() : null);
        ActivityLog::security("sessions_revoked", $user, "Sessions ended by administrator: {$user->email} ({$count})", $request->user());

        return back()->with("success", __("app.sessions_revoked", ["count" => $count]));
    }
}
