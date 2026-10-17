@extends('layouts.guest')

@section('title', __('Maintenance'))

@section('content')
    <section class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm" data-testid="owner-login-needed">
        <h1 class="text-xl font-semibold text-slate-900">{{ __('Maintenance page') }}</h1>
        <p class="mt-2 text-sm text-slate-700">{{ __('The secret phrase is correct. Because an owner account exists, you must also be signed in to the app as an owner.') }}</p>
        <a href="{{ route('login') }}" class="btn-primary mt-4 w-full">{{ __('Sign in as owner') }}</a>
        <p class="mt-4 text-xs text-slate-500">{{ __('Owner forgot the password? See "Owner forgot the password" in DEPLOY.md.') }}</p>
    </section>
@endsection
