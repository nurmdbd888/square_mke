<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * E-mail reset links work only when the user has an e-mail address and SMTP
 * is set up in .env. Otherwise the owner resets the password from Users, and
 * an owner who is locked out uses owner recovery on the Maintenance page.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password', ['mailEnabled' => self::mailEnabled()]);
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:191']]);

        if (self::mailEnabled()) {
            Password::sendResetLink(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        }

        // Same answer whether or not the address exists.
        return back()->with('status', __('If that e-mail address belongs to a user, a reset link is on its way. No e-mail? Ask the owner to reset your password.'));
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            ['email' => mb_strtolower(trim((string) $request->input('email')))] + $request->only('password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', __($status));
    }

    public static function mailEnabled(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }
}
