@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
    <section class="rounded-2xl bg-gradient-to-br from-brand-700 to-brand-500 px-6 py-8 text-white shadow-sm sm:px-8">
        <p class="text-sm font-medium text-brand-100">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</p>
        <h1 class="mt-1 text-2xl font-bold sm:text-3xl">{{ setting('firm_name') }}</h1>
        <p class="mt-3 flex flex-wrap gap-2 text-sm">
            <span class="rounded-full bg-white/15 px-3 py-1">{{ __('Today') }}: {{ local_digits(now()->format('d-m-Y')) }}</span>
            <span class="rounded-full bg-white/15 px-3 py-1" data-testid="fiscal-year">{{ __('Fiscal year') }} {{ $fiscalYear->label(app()->getLocale()) }}</span>
            <span class="rounded-full bg-white/15 px-3 py-1">{{ __('roles.'.auth()->user()->roleName()) }}</span>
        </p>
    </section>

    @if ($health)
        <section class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="card">
                <p class="text-xs font-medium uppercase text-slate-500">{{ __('Version') }}</p>
                <p class="mt-1 font-mono text-lg font-semibold text-slate-900" data-testid="version">{{ $health['version']['version'] }}</p>
            </div>
            <div class="card">
                <p class="text-xs font-medium uppercase text-slate-500">{{ __('Database') }}</p>
                <p class="mt-1 flex items-center gap-2 text-lg font-semibold" data-testid="database">
                    <x-status :status="$health['database']['connected'] ? 'ok' : 'fail'" />
                    <span>{{ $health['database']['connected'] ? __('Connected') : __('Not connected') }}</span>
                </p>
            </div>
            <div class="card">
                <p class="text-xs font-medium uppercase text-slate-500">{{ __('Cron') }}</p>
                <p class="mt-1 flex items-center gap-2 text-lg font-semibold" data-testid="cron">
                    <x-status :status="$health['cron_ok'] ? 'ok' : 'warn'" />
                    <span>{{ $health['heartbeat'] ? $health['heartbeat']->ran_at->diffForHumans() : __('No heartbeat yet') }}</span>
                </p>
            </div>
        </section>
    @endif

    <section class="mt-6 card">
        <h2 class="font-semibold text-slate-900">{{ __('What is ready') }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ __('Sign-in through stock costing, payments, receivables, payables and the cash and bank books are ready. Returns and claims come in the next updates.') }}</p>
    </section>
@endsection
