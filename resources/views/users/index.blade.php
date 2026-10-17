@extends('layouts.app')

@section('title', __('Users'))

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('Users') }}</h1>
        @can('users.manage')
            <a href="{{ route('users.create') }}" class="btn-primary">{{ __('Add user') }}</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('users.index') }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_12rem_10rem_auto]"
          hx-get="{{ route('users.index') }}" hx-target="#users-table" hx-push-url="true" hx-trigger="input changed delay:400ms from:input[name=q], change from:select">
        <label class="block">
            <span class="sr-only">{{ __('Search') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search name, user name, e-mail or phone') }}" class="input mt-0">
        </label>
        <label class="block">
            <span class="sr-only">{{ __('Role') }}</span>
            <select name="role" class="input mt-0">
                <option value="">{{ __('All roles') }}</option>
                @foreach (\App\Support\Access\PermissionRegistry::ROLES as $role)
                    <option value="{{ $role }}" @selected(($filters['role'] ?? null) === $role)>{{ __('roles.'.$role) }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="sr-only">{{ __('Status') }}</span>
            <select name="status" class="input mt-0">
                @foreach (['active' => __('Active'), 'inactive' => __('Deactivated'), 'all' => __('All')] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn-secondary">{{ __('Filter') }}</button>
    </form>

    <div id="users-table" class="mt-4">
        @include('users._table')
    </div>
@endsection
