@extends('layouts.app')

@section('content')
    <section class="rounded-2xl bg-gradient-to-br from-brand-700 to-brand-500 px-6 py-10 text-white shadow-sm sm:px-10">
        <p class="text-sm font-medium uppercase tracking-wide text-brand-100">{{ __('Square Dealer Manager') }}</p>
        <h1 class="mt-2 text-3xl font-bold sm:text-4xl">{{ $firmName }}</h1>
        <p class="mt-3 max-w-xl text-brand-50">
            {{ __('Purchases, sales, stock, dues and profit for a Square Toiletries distributor.') }}
        </p>
        <p class="mt-6 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-medium">
            {{ __('Setup in progress: deploy smoke test (M0)') }}
        </p>
    </section>

    <section class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-medium uppercase text-slate-500">{{ __('Version') }}</p>
            <p class="mt-1 font-mono text-lg font-semibold text-slate-900" data-testid="version">{{ $version }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-medium uppercase text-slate-500">{{ __('Database') }}</p>
            <p class="mt-1 flex items-center gap-2 text-lg font-semibold" data-testid="database">
                <x-status :status="$databaseOk ? 'ok' : 'fail'" />
                <span>{{ $databaseOk ? __('Connected') : __('Not connected') }}</span>
            </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-medium uppercase text-slate-500">{{ __('Cron') }}</p>
            <p class="mt-1 flex items-center gap-2 text-lg font-semibold" data-testid="cron">
                <x-status :status="$cronOk ? 'ok' : 'warn'" />
                <span>{{ $heartbeat ? $heartbeat->ran_at->diffForHumans() : __('No heartbeat yet') }}</span>
            </p>
        </div>
    </section>

    <p class="mt-8 text-sm text-slate-500">
        {{ __('Owner tools') }}: <a href="{{ route('maintenance') }}" class="font-medium text-brand-700 underline">{{ __('Maintenance page') }}</a>
    </p>
@endsection
