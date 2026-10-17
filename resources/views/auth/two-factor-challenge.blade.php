@extends('layouts.guest')

@section('title', __('Two-factor sign-in'))

@section('content')
    <section class="mx-auto max-w-md card" x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">
        <h1 class="text-xl font-semibold text-slate-900">{{ __('Two-factor sign-in') }}</h1>

        <x-flash />

        <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-4 space-y-4">
            @csrf
            <div x-show="! recovery">
                <p class="text-sm text-slate-600">{{ __('Open your authenticator app and type the 6-digit code for Square Distributor Manager.') }}</p>
                <label class="mt-3 block">
                    <span class="label">{{ __('Code') }}</span>
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" autofocus class="input text-center font-mono text-lg tracking-widest">
                </label>
            </div>
            <div x-cloak x-show="recovery">
                <p class="text-sm text-slate-600">{{ __('Lost your phone? Type one of the recovery codes you saved. Each code works once.') }}</p>
                <label class="mt-3 block">
                    <span class="label">{{ __('Recovery code') }}</span>
                    <input type="text" name="recovery_code" autocomplete="off" autocapitalize="characters" class="input font-mono">
                </label>
            </div>
            <button type="submit" class="btn-primary w-full">{{ __('Continue') }}</button>
        </form>

        <button type="button" class="mt-4 w-full text-center text-sm text-brand-700 underline" @click="recovery = ! recovery">
            <span x-show="! recovery">{{ __('Use a recovery code instead') }}</span>
            <span x-cloak x-show="recovery">{{ __('Use the authenticator app instead') }}</span>
        </button>
    </section>
@endsection
