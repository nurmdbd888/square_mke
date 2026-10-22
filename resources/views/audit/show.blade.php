@extends('layouts.app')

@section('title', __('Audit entry'))

@php
    $show = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (is_bool($v) ? ($v ? 'true' : 'false') : ($v === null ? '—' : (string) $v));
@endphp

@section('content')
    <x-back-link :href="url()->previous() !== url()->current() ? url()->previous() : route('audit.index')">{{ __('Back to the audit log') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ \App\Support\Audit\AuditEvents::label($log->event) }}</h1>

    <section class="card mt-6">
        <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[10rem_1fr]">
            <dt class="text-slate-500">{{ __('When') }}</dt><dd>{{ local_digits($log->created_at->format('d-m-Y H:i:s')) }}</dd>
            <dt class="text-slate-500">{{ __('User') }}</dt><dd>{{ $log->user ? $log->user->name.' ('.$log->user->username.')' : ($log->user_name ?? '—') }}</dd>
            <dt class="text-slate-500">{{ __('Source') }}</dt><dd>{{ __('audit.sources.'.$log->source) }}</dd>
            <dt class="text-slate-500">{{ __('Record') }}</dt><dd>{{ \App\Support\Audit\AuditEvents::typeLabel($log->auditable_type) }}@if ($log->auditable_id) #{{ $log->auditable_id }}@endif</dd>
            <dt class="text-slate-500">{{ __('IP address') }}</dt><dd class="font-mono">{{ $log->ip_address ?? '—' }}</dd>
            <dt class="text-slate-500">{{ __('Page') }}</dt><dd class="font-mono break-all">{{ $log->url ?? '—' }}</dd>
            <dt class="text-slate-500">{{ __('Browser') }}</dt><dd class="break-all text-slate-600">{{ $log->user_agent ?? '—' }}</dd>
        </dl>
    </section>

    @if ($log->changedKeys() !== [])
        <div class="table-wrap mt-6">
            <table class="data-table" data-testid="audit-changes">
                <thead><tr><th scope="col">{{ __('Field') }}</th><th scope="col">{{ __('Old value') }}</th><th scope="col">{{ __('New value') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($log->changedKeys() as $key)
                        <tr>
                            <td class="font-mono text-xs">{{ $key }}</td>
                            <td class="font-mono text-xs break-all text-red-800">{{ $log->old_values === null ? '—' : $show($log->old_values[$key] ?? null) }}</td>
                            <td class="font-mono text-xs break-all text-emerald-800">{{ $log->new_values === null ? '—' : $show($log->new_values[$key] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
