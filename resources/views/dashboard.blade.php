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

    @if ($metrics)
        <form method="GET" class="mt-6 flex flex-wrap items-center gap-4 rounded-xl border border-slate-200 bg-white px-4 py-3">
            @include('reports.partials.business-days-filter', ['businessDaysOnly' => $businessDaysOnly ?? false])
            <button class="btn-secondary text-sm">{{ __('Apply') }}</button>
        </form>
        @if ($businessDaysOnly ?? false)
            <p class="mt-2 text-sm text-brand-700" data-testid="business-days-active">{{ __('Month KPIs exclude weekly holidays.') }}</p>
        @endif
        <section class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-testid="dashboard-metrics">
            <div class="card">
                <p class="text-xs font-medium uppercase text-slate-500">{{ __('Today sales') }}</p>
                <p class="mt-1 font-mono text-lg font-semibold">{{ format_money($metrics['today_sales']) }}</p>
            </div>
            <div class="card">
                <p class="text-xs font-medium uppercase text-slate-500">{{ __('Month sales') }}</p>
                <p class="mt-1 font-mono text-lg font-semibold">{{ format_money($metrics['month_sales']) }}</p>
            </div>
            <div class="card">
                <p class="text-xs font-medium uppercase text-slate-500">{{ __('Gross margin') }}</p>
                <p class="mt-1 font-mono text-lg font-semibold">{{ local_digits($metrics['month_gross_margin_pct']) }}%</p>
            </div>
            <div class="card">
                <p class="text-xs font-medium uppercase text-slate-500">{{ __('Net profit (month)') }}</p>
                <p class="mt-1 font-mono text-lg font-semibold">{{ format_money($metrics['month_net_profit']) }}</p>
            </div>
            <div class="card">
                <p class="text-xs font-medium uppercase text-slate-500">{{ __('Stock value') }}</p>
                <p class="mt-1 font-mono text-lg font-semibold">{{ format_money($metrics['stock_value']) }}</p>
            </div>
            <div class="card">
                <p class="text-xs font-medium uppercase text-slate-500">{{ __('Receivables due') }}</p>
                <p class="mt-1 font-mono text-lg font-semibold">{{ format_money($metrics['receivables_due']) }}</p>
            </div>
        </section>

        @if ($metrics['top_products'] !== [] || $metrics['top_retailers'] !== [])
            <section class="mt-6 grid gap-4 lg:grid-cols-2">
                @if ($metrics['top_products'] !== [])
                    <div class="card">
                        <h2 class="font-semibold text-slate-900">{{ __('Top products (month)') }}</h2>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($metrics['top_products'] as $row)
                                <li class="flex justify-between gap-2">
                                    <span>{{ $row['product']?->name_en }}</span>
                                    <span class="font-mono">{{ format_money($row['revenue']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($metrics['top_retailers'] !== [])
                    <div class="card">
                        <h2 class="font-semibold text-slate-900">{{ __('Top retailers (month)') }}</h2>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($metrics['top_retailers'] as $row)
                                <li class="flex justify-between gap-2">
                                    <span>{{ $row['retailer']?->name }}</span>
                                    <span class="font-mono">{{ format_money($row['revenue']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        @endif
    @endif

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
        <p class="mt-1 text-sm text-slate-600">{{ __('Sign-in through P&L, dashboard metrics, month lock, list import/export, returns, claims, payments and the books are ready.') }}</p>
        @can('reports.view')
            <p class="mt-2"><a href="{{ route('reports.pl') }}" class="text-brand-700 hover:underline">{{ __('Open profit and loss') }}</a></p>
        @endcan
    </section>
@endsection
