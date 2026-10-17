@extends('layouts.guest')

@section('title', __('Forgot your password?'))

@section('content')
    <section class="mx-auto max-w-md card">
        <h1 class="text-xl font-semibold text-slate-900">{{ __('Forgot your password?') }}</h1>

        <x-flash />

        @if ($mailEnabled)
            <p class="mt-1 text-sm text-slate-600">{{ __('Type the e-mail address of your account and we will send you a link to choose a new password.') }}</p>
            <form method="POST" action="{{ route('password.email') }}" class="mt-4 space-y-4">
                @csrf
                <label class="block">
                    <span class="label">{{ __('E-mail') }}</span>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="input">
                </label>
                <button type="submit" class="btn-primary w-full">{{ __('Send reset link') }}</button>
            </form>
        @else
            <p class="mt-2 text-sm text-slate-700" data-testid="no-mail">{{ __('E-mail is not set up for this app. Ask the owner to reset your password from the Users page.') }}</p>
            <p class="mt-2 text-sm text-slate-700">{{ __('Owner locked out? See "Owner forgot the password" in DEPLOY.md.') }}</p>
        @endif

        <p class="mt-4 text-center text-sm"><a href="{{ route('login') }}" class="text-brand-700 underline">{{ __('Back to sign in') }}</a></p>
    </section>
@endsection
