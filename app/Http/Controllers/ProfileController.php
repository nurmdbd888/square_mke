<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile.show', [
            'user' => $request->user(),
            'setupSecret' => $request->session()->get('two_factor.setup_secret'),
            'recoveryCodes' => $request->session()->get('two_factor.recovery_codes'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->getKey())],
            'phone' => ['nullable', 'string', 'max:20'],
            'locale' => ['nullable', Rule::in(array_keys(config('sdm.supported_locales')))],
        ]);

        $user->update($data);

        return redirect()->route('profile')->with('status', __('Saved.'));
    }

    public function password(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        // The "auth.session" middleware signs out the user's other browsers,
        // whose sessions still hold the old password hash.
        $user->forceFill(['password' => $request->input('password'), 'must_change_password' => false])->saveQuietly();
        AuditLogger::log('auth.password_changed', $user);

        return redirect()->route('profile')->with('status', __('Your password was changed.'));
    }

    public function locale(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('sdm.supported_locales')), 404);

        /** @var User $user */
        $user = $request->user();
        $user->update(['locale' => $locale]);

        return back()->withCookie(cookie(SetLocale::COOKIE, $locale, 60 * 24 * 365, secure: $request->isSecure(), httpOnly: true, sameSite: 'lax'));
    }
}
