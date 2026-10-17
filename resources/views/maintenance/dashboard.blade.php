@extends('layouts.guest')

@section('title', __('Maintenance'))

@php
    $groups = [
        'php' => __('PHP'),
        'extensions' => __('PHP extensions'),
        'config' => __('Settings file'),
        'folders' => __('Folders'),
        'database' => __('Database'),
        'cron' => __('Cron'),
        'server' => __('Server'),
    ];
    $byGroup = collect($results)->groupBy('group');
    $allGreen = $summary['fail'] === 0 && $summary['warn'] === 0;
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ __('Maintenance page') }}</h1>
            <p class="text-sm text-slate-500">{{ __('Version') }} {{ $version['version'] }}@if ($version['commit']) · {{ substr($version['commit'], 0, 7) }}@endif @if ($version['built_at']) · {{ $version['built_at'] }}@endif</p>
        </div>
        <form method="POST" action="{{ route('maintenance.logout') }}">
            <button class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100">{{ __('Log out') }}</button>
        </form>
    </div>

    <section @class([
        'mt-6 rounded-xl border p-4',
        'border-emerald-200 bg-emerald-50 text-emerald-900' => $allGreen,
        'border-red-200 bg-red-50 text-red-900' => $summary['fail'] > 0,
        'border-amber-200 bg-amber-50 text-amber-900' => ! $allGreen && $summary['fail'] === 0,
    ]) data-testid="summary">
        <p class="font-semibold">
            @if ($allGreen)
                {{ __('All checks are green.') }}
            @else
                {{ __(':fail to fix, :warn to check, :ok OK', ['fail' => $summary['fail'], 'warn' => $summary['warn'], 'ok' => $summary['ok']]) }}
            @endif
        </p>
    </section>

    @if (collect($results)->contains(fn ($row) => $row->key === 'docroot' && $row->status === 'warn'))
        <section class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900" data-testid="docroot-warning">
            <p class="font-semibold">{{ __('The subdomain points at the wrong folder.') }}</p>
            <p class="mt-1 text-sm">{{ __('In cPanel > Domains > Manage, set its document root to :path, then choose PHP 8.4 again in MultiPHP Manager. See DEPLOY.md, "Problems and fixes".', ['path' => $paths['public']]) }}</p>
        </section>
    @endif

    @if ($action)
        <section @class([
            'mt-4 rounded-xl border p-4',
            'border-emerald-200 bg-white' => $action['success'],
            'border-red-200 bg-white' => ! $action['success'],
        ]) data-testid="action-result">
            <h2 class="font-semibold text-slate-900">{{ $action['title'] }}</h2>
            <p class="mt-1 text-sm text-slate-700">{{ $action['message'] }}</p>
            @if ($action['output'])
                <pre class="mt-3 max-h-80 overflow-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">{{ $action['output'] }}</pre>
            @endif
        </section>
    @endif

    @include('maintenance.partials.owner')

    <section class="mt-6 flex flex-wrap gap-3">
        @foreach ([
            ['route' => 'maintenance.generate-key', 'label' => __('Generate APP_KEY')],
            ['route' => 'maintenance.migrate', 'label' => __('Run migrations')],
            ['route' => 'maintenance.clear-caches', 'label' => __('Clear caches')],
            ['route' => 'maintenance.backup', 'label' => __('Backup now')],
        ] as $button)
            <form method="POST" action="{{ route($button['route']) }}">
                <input type="hidden" name="_maintenance_token" value="{{ $formToken }}">
                <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ $button['label'] }}</button>
            </form>
        @endforeach
        <a href="{{ route('maintenance') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">{{ __('Check again') }}</a>
    </section>

    <section class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4" data-testid="restore-backup">
        <h2 class="font-semibold text-amber-900">{{ __('Restore from backup') }}</h2>
        <p class="mt-1 text-sm text-amber-800">{{ __('Overwrites the live database and private uploads. Take a backup first. Type RESTORE to confirm.') }}</p>
        @if (count($backups ?? []) === 0)
            <p class="mt-2 text-sm text-amber-900">{{ __('No local backup files yet. Press Backup now first.') }}</p>
        @else
            <form method="POST" action="{{ route('maintenance.restore') }}" class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                <input type="hidden" name="_maintenance_token" value="{{ $formToken }}">
                <label class="block flex-1">
                    <span class="text-sm font-medium text-amber-900">{{ __('Backup file') }}</span>
                    <select name="basename" class="input mt-1" required>
                        @foreach ($backups as $file)
                            <option value="{{ $file }}">{{ $file }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-amber-900">{{ __('Type RESTORE') }}</span>
                    <input type="text" name="confirm" class="input mt-1 font-mono" required autocomplete="off">
                </label>
                <button class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">{{ __('Restore backup') }}</button>
            </form>
        @endif
    </section>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-4">
        <h2 class="font-semibold text-slate-900">{{ __('Cron command') }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ __('cPanel > Cron Jobs > Common Settings: Once Per Minute. Paste this into "Command":') }}</p>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <code id="cron-command" class="block flex-1 overflow-x-auto rounded-lg bg-slate-900 px-3 py-2 font-mono text-xs whitespace-nowrap text-slate-100" data-testid="cron-command">{{ $cron['command'] }}</code>
            <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-100"
                    onclick="navigator.clipboard.writeText(document.getElementById('cron-command').textContent); this.textContent='{{ __('Copied') }}';">{{ __('Copy') }}</button>
        </div>
        @if ($heartbeat)
            <p class="mt-3 text-sm text-slate-600">
                {{ __('Last cron heartbeat') }}: <strong>{{ $heartbeat->ran_at->format('Y-m-d H:i:s') }}</strong> ({{ $heartbeat->ran_at->diffForHumans() }}), PHP {{ $heartbeat->php_version }}
            </p>
        @endif
    </section>

    @foreach ($groups as $group => $title)
        @continue(! $byGroup->has($group))
        <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700">{{ $title }}</h2>
            <ul class="divide-y divide-slate-100">
                @foreach ($byGroup[$group] as $row)
                    <li class="flex flex-col gap-1 px-4 py-2 sm:flex-row sm:items-start sm:gap-4" data-check="{{ $row->key }}" data-status="{{ $row->status }}">
                        <x-status :status="$row->status" class="shrink-0" />
                        <span class="text-sm font-medium text-slate-800 sm:w-64 sm:shrink-0">{{ $row->label }}</span>
                        <span class="text-sm break-all text-slate-600">{{ $row->detail }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-4 text-sm">
        <h2 class="font-semibold text-slate-900">{{ __('Folders in use') }}</h2>
        <dl class="mt-2 grid gap-x-4 gap-y-1 sm:grid-cols-[10rem_1fr]">
            @foreach ($paths as $name => $path)
                <dt class="text-slate-500">{{ $name }}</dt>
                <dd class="font-mono text-xs break-all text-slate-700">{{ $path }}</dd>
            @endforeach
        </dl>
    </section>
@endsection
