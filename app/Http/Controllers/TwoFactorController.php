<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Turning the user's own two-factor sign-in on and off. The new secret stays
 * in the session until the user proves the app works by typing a code.
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactor $twoFactor) {}

    public function start(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('profile');
        }

        $request->session()->put('two_factor.setup_secret', $this->twoFactor->generateSecret());

        return redirect()->to(route('profile').'#two-factor');
    }

    public function confirm(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $secret = $request->session()->get('two_factor.setup_secret');

        $request->validate(['code' => ['required', 'string', 'max:10']]);

        if (! is_string($secret)) {
            return redirect()->route('profile');
        }

        if (! $this->twoFactor->verify($user, (string) $request->input('code'), $secret)) {
            return redirect()->to(route('profile').'#two-factor')->withErrors(['code' => __('That code is not correct.')]);
        }

        $codes = $this->twoFactor->newRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->saveQuietly();

        $request->session()->forget('two_factor.setup_secret');
        AuditLogger::log('two_factor.enabled', $user);

        return redirect()->to(route('profile').'#two-factor')
            ->with('two_factor.recovery_codes', $codes)
            ->with('status', __('Two-factor sign-in is on.'));
    }

    public function regenerate(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $request->validate(['current_password' => ['required', 'current_password']]);

        abort_unless($user->hasTwoFactorEnabled(), 404);

        $codes = $this->twoFactor->newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->saveQuietly();
        AuditLogger::log('two_factor.recovery_regenerated', $user);

        return redirect()->to(route('profile').'#two-factor')->with('two_factor.recovery_codes', $codes);
    }

    public function disable(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $request->validate(['current_password' => ['required', 'current_password']]);

        $this->twoFactor->disable($user);
        AuditLogger::log('two_factor.disabled', $user);

        return redirect()->to(route('profile').'#two-factor')->with('status', __('Two-factor sign-in is off.'));
    }
}
