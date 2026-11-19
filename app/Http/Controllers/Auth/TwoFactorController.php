<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Security\TwoFactor;
use App\Services\Security\UserSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Two-factor authentication: login challenge, set-up, recovery codes, active sessions. */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactor $twoFactor) {}

    // ---------- Login challenge (guest) ----------

    public function challenge(Request $request): View|RedirectResponse
    {
        return $this->pendingUser($request) ? view('auth.two-factor-challenge') : redirect()->route('login');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (!$user) {
            return redirect()->route('login')->with('error', __('app.two_factor_expired'));
        }

        $data = $request->validate(['code' => 'nullable|string|max:20', 'recovery_code' => 'nullable|string|max:20']);
        $key = 'two-factor:' . $user->id . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $request->session()->forget('login.2fa');
            throw ValidationException::withMessages(['code' => __('app.two_factor_too_many')]);
        }

        $usedRecovery = false;
        $ok = filled($data['code'] ?? null) && $this->twoFactor->verify($user->two_factor_secret, $data['code']);
        if (!$ok && filled($data['recovery_code'] ?? null)) {
            $ok = $usedRecovery = $this->twoFactor->useRecoveryCode($user, $data['recovery_code']);
        }

        if (!$ok) {
            RateLimiter::hit($key, 300);
            ActivityLog::security('two_factor_failed', $user, "Invalid two-factor code: {$user->email}", null);
            throw ValidationException::withMessages(['code' => __('app.two_factor_invalid')]);
        }

        RateLimiter::clear($key);
        $remember = (bool) $request->session()->pull('login.2fa')['remember'];

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        if ($usedRecovery) {
            ActivityLog::security('recovery_code_used', $user, "Recovery code used: {$user->email}", $user);
            $request->session()->flash('warning', __('app.two_factor_recovery_used', ['left' => count($user->two_factor_recovery_codes ?? [])]));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('login.2fa');
        if (!$pending || $pending['expires'] < now()->timestamp) {
            $request->session()->forget('login.2fa');

            return null;
        }

        $user = User::find($pending['id']);

        return $user && $user->is_active && $user->hasTwoFactor() ? $user : null;
    }

    // ---------- Profile: security page ----------

    public function show(Request $request, UserSessions $sessions): View
    {
        $user = $request->user();
        $pending = $request->session()->get('two_factor.pending_secret');

        return view('profile.security', [
            'user' => $user,
            'pendingSecret' => $pending,
            'qr' => $pending ? $this->twoFactor->qrSvg($this->twoFactor->otpauthUrl($user, $pending)) : null,
            'sessions' => $sessions->forUser($user, $request->session()->getId()),
            'sessionsSupported' => UserSessions::supported(),
            'recoveryCodes' => $request->session()->get('two_factor.recovery_codes'),
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $request->session()->put('two_factor.pending_secret', $this->twoFactor->generateSecret());

        return redirect()->route('profile.security')->withFragment('two-factor');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => 'required|string|max:10']);
        $secret = $request->session()->get('two_factor.pending_secret');

        if (!$secret || !$this->twoFactor->verify($secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => __('app.two_factor_invalid')]);
        }

        $codes = $this->twoFactor->recoveryCodes();
        $user = $request->user();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes['hashed'],
            'two_factor_confirmed_at' => now(),
        ])->saveQuietly();
        $request->session()->forget('two_factor.pending_secret');

        ActivityLog::security('two_factor_enabled', $user, "Two-factor enabled: {$user->email}", $user);

        return redirect()->route('profile.security')->withFragment('two-factor')
            ->with('success', __('app.two_factor_enabled'))
            ->with('two_factor.recovery_codes', $codes['plain']);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget('two_factor.pending_secret');

        return redirect()->route('profile.security');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();

        if ($user->twoFactorRequired()) {
            return back()->with('error', __('app.two_factor_required_for_role'));
        }

        $this->clear($user);
        ActivityLog::security('two_factor_disabled', $user, "Two-factor disabled: {$user->email}", $user);

        return redirect()->route('profile.security')->with('success', __('app.two_factor_disabled'));
    }

    public function regenerateCodes(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $codes = $this->twoFactor->recoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => $codes['hashed']])->saveQuietly();

        return redirect()->route('profile.security')->withFragment('two-factor')->with('two_factor.recovery_codes', $codes['plain']);
    }

    public function revokeSessions(Request $request, UserSessions $sessions): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $count = $sessions->revoke($request->user(), $request->session()->getId());
        ActivityLog::security('sessions_revoked', $request->user(), "Other sessions ended: {$count}", $request->user());

        return back()->with('success', __('app.sessions_revoked', ['count' => $count]));
    }

    public static function clear(User $user): void
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->saveQuietly();
    }
}
