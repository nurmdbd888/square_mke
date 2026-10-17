<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Access\PermissionRegistry;
use App\Support\Auth\LoginThrottle;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Throwable;

class LoginController extends Controller
{
    public function __construct(private readonly LoginThrottle $throttle) {}

    public function show(): View
    {
        return view('auth.login', ['setup' => $this->setupState()]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'max:191'],
        ]);

        $login = mb_strtolower(trim($data['login']));

        if ($seconds = $this->throttle->blockedFor($request, $login)) {
            return $this->failed(__('Too many sign-in attempts. Try again in :seconds seconds.', ['seconds' => $seconds]));
        }

        $user = User::query()->where('username', $login)->orWhere('email', $login)->first();

        // Hash even when the user does not exist, so timing does not reveal valid user names.
        $valid = Hash::check($data['password'], $user?->password ?? sprintf('$2y$%02d$%s', config('hashing.bcrypt.rounds', 12), str_repeat('a', 53)));

        if (! $user || ! $valid) {
            $this->throttle->hit($request, $login);
            event(new Failed('web', $user, ['login' => $login]));

            return $this->failed(__('The user name or password is not correct.'));
        }

        $this->throttle->clear($request, $login);
        $request->session()->regenerate();

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('login.id', $user->getKey());
            $request->session()->put('login.expires', now()->addMinutes(5)->getTimestamp());

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('You have signed out.'));
    }

    private function failed(string $message): RedirectResponse
    {
        return back()->withInput(request()->only('login'))->withErrors(['login' => $message]);
    }

    /**
     * Tells the owner on the login page when setup is not finished, instead of a database error.
     */
    private function setupState(): ?string
    {
        try {
            return User::query()->whereHas('roles', fn ($q) => $q->where('name', PermissionRegistry::OWNER))->exists() ? null : 'no_owner';
        } catch (Throwable) {
            return 'no_database';
        }
    }
}
