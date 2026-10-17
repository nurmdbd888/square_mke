<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\LoginThrottle;
use App\Support\Auth\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Second step of sign-in for users with an authenticator app. The password
 * step stored only the user id in the session; nobody is signed in yet.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly TwoFactor $twoFactor,
        private readonly LoginThrottle $throttle,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->withErrors(['login' => __('Your sign-in expired. Please start again.')]);
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:20'],
        ]);

        if ($seconds = $this->throttle->blockedFor($request, $user->username, 'two-factor')) {
            return back()->withErrors(['code' => __('Too many attempts. Try again in :seconds seconds.', ['seconds' => $seconds])]);
        }

        $recovery = trim((string) ($data['recovery_code'] ?? ''));

        $ok = $recovery !== ''
            ? $this->twoFactor->useRecoveryCode($user, $recovery)
            : $this->twoFactor->verify($user, (string) ($data['code'] ?? ''));

        if (! $ok) {
            $this->throttle->hit($request, $user->username, 'two-factor');
            AuditLogger::log('two_factor.failed', $user, actor: $user);

            return back()->withErrors([$recovery !== '' ? 'recovery_code' : 'code' => __('That code is not correct.')]);
        }

        $this->throttle->clear($request, $user->username, 'two-factor');
        $request->session()->forget(['login.id', 'login.expires']);
        $request->session()->regenerate();

        if ($recovery !== '') {
            AuditLogger::log('two_factor.recovery_used', $user, null, ['remaining' => count($user->two_factor_recovery_codes ?? [])], actor: $user);
        }

        Auth::login($user);

        $home = $user->hasRole('collector') && ! $user->isOwner()
            ? route('collector.index')
            : route('dashboard');

        return redirect()->intended($home);
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('login.id');
        $expires = (int) $request->session()->get('login.expires', 0);

        if (! $id || $expires < now()->getTimestamp()) {
            return null;
        }

        return User::query()->find($id);
    }
}
