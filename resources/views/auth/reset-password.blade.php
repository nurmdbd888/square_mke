@extends('layouts.guest')

@section('title', __('Choose a new password'))

@section('content')
    <section class="mx-auto max-w-md card">
        <h1 class="text-xl font-semibold text-slate-900">{{ __('Choose a new password') }}</h1>

        <x-flash />

        <form method="POST" action="{{ route('password.update') }}" class="mt-4 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="block">
                <span class="label">{{ __('E-mail') }}</span>
                <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email" class="input">
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
            <button type="submit" class="btn-primary w-full">{{ __('Save new password') }}</button>
        </form>
    </section>
@endsection
