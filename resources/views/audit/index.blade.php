@extends('layouts.app')

@section('title', __('Audit log'))

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('Audit log') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Who did what and when. Entries cannot be changed or deleted.') }}</p>

    <form method="GET" action="{{ route('audit.index') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6"
          hx-get="{{ route('audit.index') }}" hx-target="#audit-table" hx-push-url="true" hx-trigger="change">
        <label class="block">
            <span class="label">{{ __('User') }}</span>
            <select name="user" class="input">
                <option value="">{{ __('Everyone') }}</option>
                @foreach ($users as $option)
                    <option value="{{ $option->id }}" @selected((string) ($filters['user'] ?? '') === (string) $option->id)>{{ $option->username }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="label">{{ __('Action') }}</span>
            <select name="event" class="input">
                <option value="">{{ __('All actions') }}</option>
                @foreach (['auth.*', 'two_factor.*', 'user.*', 'settings.*', 'maintenance.*'] as $prefix)
                    <option value="{{ $prefix }}" @selected(($filters['event'] ?? null) === $prefix)>{{ \App\Support\Audit\AuditEvents::label($prefix) }}</option>
                @endforeach
                @foreach ($events as $event)
                    <option value="{{ $event }}" @selected(($filters['event'] ?? null) === $event)>· {{ \App\Support\Audit\AuditEvents::label($event) }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="label">{{ __('Record type') }}</span>
            <select name="type" class="input">
                <option value="">{{ __('All') }}</option>
                @foreach (\App\Support\Audit\AuditEvents::TYPES as $type)
                    <option value="{{ $type }}" @selected(($filters['type'] ?? null) === $type)>{{ \App\Support\Audit\AuditEvents::typeLabel($type) }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="label">{{ __('Source') }}</span>
            <select name="source" class="input">
                <option value="">{{ __('All') }}</option>
                @foreach (['web', 'maintenance', 'console'] as $source)
                    <option value="{{ $source }}" @selected(($filters['source'] ?? null) === $source)>{{ __('audit.sources.'.$source) }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="label">{{ __('From date') }}</span>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input">
        </label>
        <label class="block">
            <span class="label">{{ __('To date') }}</span>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input">
        </label>
        <noscript><button class="btn-secondary">{{ __('Filter') }}</button></noscript>
    </form>

    <div id="audit-table" class="mt-4">
        @include('audit._table')
    </div>
@endsection
