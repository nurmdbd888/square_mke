@extends('layouts.guest')

@section('title', __('Maintenance'))

@section('content')
    <section class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold text-slate-900">{{ __('Maintenance page') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Type the secret phrase (MAINTENANCE_TOKEN) from your settings file.') }}</p>

        @if ($error)
            <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ $error }}</p>
        @endif

        <form method="POST" action="{{ route('maintenance.login') }}" class="mt-4 space-y-4">
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ __('Secret phrase') }}</span>
                <input type="password" name="token" required autofocus autocomplete="current-password"
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-brand-500 focus:ring-2 focus:ring-brand-200 focus:outline-none">
            </label>
            <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 font-semibold text-white hover:bg-brand-700">
                {{ __('Open') }}
            </button>
        </form>
    </section>
@endsection
