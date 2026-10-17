@extends('layouts.app')

@section('title', __('My profile'))

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('My profile') }}</h1>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="card">
            <h2 class="font-semibold text-slate-900">{{ __('Your details') }}</h2>
            <form method="POST" action="{{ route('profile.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')
                <p class="text-sm text-slate-600">{{ __('User name') }}: <strong class="font-mono">{{ $user->username }}</strong> · {{ __('Role') }}: <strong>{{ __('roles.'.$user->roleName()) }}</strong></p>
                <label class="block">
                    <span class="label">{{ __('Full name') }}</span>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('E-mail') }} <span class="font-normal text-slate-500">({{ __('optional, for password reset links') }})</span></span>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="191" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('Mobile number') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                    <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="20" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('Language') }}</span>
                    <select name="locale" class="input">
                        <option value="">{{ __('Firm default') }}</option>
                        @foreach (config('sdm.supported_locales') as $code => $label)
                            <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="btn-primary">{{ __('Save') }}</button>
            </form>
        </section>

        <section class="card" id="password">
            <h2 class="font-semibold text-slate-900">{{ __('Change password') }}</h2>
            @if ($user->must_change_password)
                <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900" data-testid="must-change">{{ __('You are using a temporary password. Choose your own password now.') }}</p>
            @endif
            <form method="POST" action="{{ route('profile.password') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')
                <label class="block">
                    <span class="label">{{ __('Current password') }}</span>
                    <input type="password" name="current_password" required autocomplete="current-password" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('New password') }}</span>
                    <input type="password" name="password" required minlength="10" autocomplete="new-password" class="input">
                    <span class="mt-1 block text-xs text-slate-500">{{ __('At least 10 characters.') }}</span>
                </label>
                <label class="block">
                    <span class="label">{{ __('New password again') }}</span>
                    <input type="password" name="password_confirmation" required autocomplete="new-password" class="input">
                </label>
                <button class="btn-primary">{{ __('Change password') }}</button>
            </form>
        </section>

        <section class="card xl:col-span-2" id="two-factor" data-testid="two-factor">
            <h2 class="font-semibold text-slate-900">{{ __('Two-factor sign-in') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('After your password, the app asks for a 6-digit code from an authenticator app on your phone (Google Authenticator, Microsoft Authenticator, 2FAS and others). Someone who learns your password still cannot sign in.') }}</p>

            @if ($user->hasTwoFactorEnabled())
                <p class="mt-4 flex items-center gap-2 text-sm font-medium text-emerald-800"><x-status status="ok" /> {{ __('On since :date', ['date' => local_digits($user->two_factor_confirmed_at->format('d-m-Y'))]) }}</p>

                @if ($recoveryCodes)
                    <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4" data-testid="recovery-codes">
                        <p class="text-sm font-semibold text-amber-900">{{ __('Recovery codes: write them down or print this page now. They are shown only once.') }}</p>
                        <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4">
                            @foreach ($recoveryCodes as $code)
                                <li class="rounded bg-white px-2 py-1 text-center">{{ $code }}</li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <p class="mt-2 text-sm text-slate-600">{{ __(':count recovery codes left.', ['count' => local_digits(count($user->two_factor_recovery_codes ?? []))]) }}</p>
                @endif

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <form method="POST" action="{{ route('profile.two-factor.recovery') }}" class="space-y-2">
                        @csrf
                        <label class="block">
                            <span class="label">{{ __('Current password') }}</span>
                            <input type="password" name="current_password" required autocomplete="current-password" class="input">
                        </label>
                        <button class="btn-secondary">{{ __('Make new recovery codes') }}</button>
                    </form>
                    <form method="POST" action="{{ route('profile.two-factor.disable') }}" class="space-y-2" data-confirm="{{ __('Turn off two-factor sign-in?') }}">
                        @csrf
                        @method('DELETE')
                        <label class="block">
                            <span class="label">{{ __('Current password') }}</span>
                            <input type="password" name="current_password" required autocomplete="current-password" class="input">
                        </label>
                        <button class="btn-danger">{{ __('Turn off two-factor sign-in') }}</button>
                    </form>
                </div>
            @elseif ($setupSecret)
                @php($twoFactor = app(\App\Support\Auth\TwoFactor::class))
                <div class="mt-4 grid gap-6 sm:grid-cols-[auto_1fr]">
                    <div class="w-52 rounded-lg border border-slate-200 bg-white p-2" data-testid="qr-code">{!! $twoFactor->qrSvg($twoFactor->otpauthUrl($user, $setupSecret)) !!}</div>
                    <div>
                        <ol class="list-inside list-decimal space-y-1 text-sm text-slate-700">
                            <li>{{ __('Open the authenticator app and add an account.') }}</li>
                            <li>{{ __('Scan the QR code, or type this key:') }} <code class="font-mono break-all text-slate-900" data-testid="setup-secret">{{ trim(chunk_split($setupSecret, 4, ' ')) }}</code></li>
                            <li>{{ __('Type the 6-digit code the app shows.') }}</li>
                        </ol>
                        <form method="POST" action="{{ route('profile.two-factor.confirm') }}" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end">
                            @csrf
                            <label class="block">
                                <span class="label">{{ __('Code') }}</span>
                                <input type="text" name="code" inputmode="numeric" maxlength="7" autocomplete="one-time-code" required class="input w-40 font-mono tracking-widest">
                            </label>
                            <button class="btn-primary">{{ __('Turn on') }}</button>
                        </form>
                    </div>
                </div>
            @else
                <p class="mt-4 flex items-center gap-2 text-sm text-slate-700"><x-status status="info" /> {{ __('Off') }}</p>
                <form method="POST" action="{{ route('profile.two-factor.start') }}" class="mt-3">
                    @csrf
                    <button class="btn-primary">{{ __('Set up two-factor sign-in') }}</button>
                </form>
            @endif
        </section>
    </div>
@endsection
