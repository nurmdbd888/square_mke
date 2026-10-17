@extends('layouts.app')

@section('title', __('Roles and permissions'))

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('Roles and permissions') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Tick what each role may do. The owner can always do everything. Each user has one role (Users page).') }}</p>

    <form method="POST" action="{{ route('roles.update') }}" class="mt-6">
        @csrf
        @method('PUT')
        <div class="table-wrap">
            <table class="data-table" data-testid="permission-matrix">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Permission') }}</th>
                        @foreach ($roles as $role)
                            <th scope="col" class="text-center">{{ __('roles.'.$role->name) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($groups as $group => $permissions)
                        <tr><th colspan="{{ $roles->count() + 1 }}" scope="colgroup" class="bg-slate-100">{{ __('permissions.groups.'.$group) }}</th></tr>
                        @foreach ($permissions as $permission => $defaults)
                            <tr>
                                <td>
                                    <span class="font-medium text-slate-900">{{ __('permissions.names.'.$permission) }}</span>
                                    <span class="block font-mono text-xs text-slate-400">{{ $permission }}</span>
                                </td>
                                @foreach ($roles as $role)
                                    <td class="text-center">
                                        @if ($role->name === 'owner')
                                            <input type="checkbox" checked disabled class="size-4" aria-label="{{ __('roles.owner') }}: {{ __('permissions.names.'.$permission) }}">
                                        @else
                                            <input type="checkbox" name="permissions[{{ $role->name }}][]" value="{{ $permission }}" class="size-4 accent-brand-600"
                                                   @checked($role->permissions->contains('name', $permission))
                                                   aria-label="{{ __('roles.'.$role->name) }}: {{ __('permissions.names.'.$permission) }}">
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
        <button class="btn-primary mt-4">{{ __('Save permissions') }}</button>
    </form>
@endsection
