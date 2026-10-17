@extends('layouts.app')

@section('title', __('System health'))

@php
    $pending = $migrations ? count($migrations['pending']) : null;
    $rows = [
        ['key' => 'version', 'label' => __('Version'), 'status' => 'info', 'value' => $version['version'].($version['commit'] ? ' · '.substr($version['commit'], 0, 7) : '').($version['built_at'] ? ' · '.$version['built_at'] : '')],
        ['key' => 'php', 'label' => __('PHP version'), 'status' => 'info', 'value' => $php],
        ['key' => 'database', 'label' => __('Database'), 'status' => $database['connected'] ? 'ok' : 'fail',
            'value' => $database['connected'] ? $database['server_type'].' '.$database['server_version'] : __('Not connected')],
        ['key' => 'migrations', 'label' => __('Migrations'), 'status' => $pending === 0 ? 'ok' : 'warn',
            'value' => $pending === null ? '—' : ($pending === 0 ? __('Up to date') : __(':count pending: open the Maintenance page and press "Run migrations"', ['count' => $pending]))],
        ['key' => 'cron', 'label' => __('Last cron heartbeat'), 'status' => $cron_ok ? 'ok' : 'warn',
            'value' => $heartbeat ? $heartbeat->ran_at->format('Y-m-d H:i:s').' ('.$heartbeat->ran_at->diffForHumans().')' : __('No heartbeat yet')],
        ['key' => 'queue', 'label' => __('Background jobs'), 'status' => $queue && $queue['failed'] > 0 ? 'warn' : 'info',
            'value' => $queue ? __(':pending waiting, :failed failed', ['pending' => $queue['pending'], 'failed' => $queue['failed']]).($queue['oldest_seconds'] !== null ? ' · '.__('oldest :seconds s', ['seconds' => $queue['oldest_seconds']]) : '') : '—'],
        ['key' => 'disk', 'label' => __('Free disk space'), 'status' => 'info',
            'value' => $disk ? $disk['free'].($disk['total'] ? ' / '.$disk['total'] : '') : __('Not available on this server')],
    ];
@endphp

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('System health') }}</h1>

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <ul class="divide-y divide-slate-100">
            @foreach ($rows as $row)
                <li class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:gap-4" data-health="{{ $row['key'] }}" data-status="{{ $row['status'] }}">
                    <x-status :status="$row['status']" class="shrink-0" />
                    <span class="text-sm font-medium text-slate-800 sm:w-56 sm:shrink-0">{{ $row['label'] }}</span>
                    <span class="text-sm break-all text-slate-600">{{ $row['value'] }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
