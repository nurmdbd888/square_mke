@extends('layouts.guest')

@section('title', __('Sign in'))

@section('content')
    <section class="mx-auto max-w-md">
        @if ($setup === 'no_database')
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert" data-testid="setup-notice">
                {{ __('The database is not ready. Open the Maintenance page to check the settings and run migrations.') }}
                <a href="{{ route('maintenance') }}" class="font-semibold underline">{{ __('Open the Maintenance page') }}</a>
            </div>
        @elseif ($setup === 'no_owner')
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert" data-testid="setup-notice">
                {{ __('No owner account exists yet. Create it on the Maintenance page.') }}
                <a href="{{ route('maintenance') }}" class="font-semibold underline">{{ __('Open the Maintenance page') }}</a>
            </div>
        @endif

        <div class="card">
            <h1 class="text-xl font-semibold text-slate-900">{{ __('Sign in') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Use the user name and password the owner gave you.') }}</p>

            <x-flash />

            <form method="POST" action="{{ route('login.attempt') }}" class="mt-4 space-y-4">
                @csrf
                <label class="block">
                    <span class="label">{{ __('User name or e-mail') }}</span>
                    <input type="text" name="login" value="{{ old('login') }}" required autofocus autocomplete="username" autocapitalize="none" class="input">
                </label>
                <label class="block">
                    <span class="label">{{ __('Password') }}</span>
                    <input type="password" name="password" required autocomplete="current-password" class="input">
                </label>
                <button type="submit" class="btn-primary w-full">{{ __('Sign in') }}</button>
            </form>

            <p class="mt-4 text-center text-sm">
                <a href="{{ route('password.request') }}" class="text-brand-700 underline">{{ __('Forgot your password?') }}</a>
            </p>
        </div>
    </section>
@endsection
