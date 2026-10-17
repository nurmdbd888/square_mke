@php
    $manage = auth()->user()->can('users.manage');
    $span = 6 + ($manage ? 1 : 0);
@endphp
@if ($manage)
    <form method="POST" action="{{ route('users.bulk') }}">
        @csrf
@endif
<div class="table-wrap">
    <table class="data-table" data-testid="users-table">
        <thead>
            <tr>
                @if ($manage)
                    <th scope="col"><x-bulk-select-all /></th>
                @endif
                <th scope="col">{{ __('Name') }}</th>
                <th scope="col">{{ __('User name') }}</th>
                <th scope="col">{{ __('Role') }}</th>
                <th scope="col" class="hidden md:table-cell">{{ __('Two-factor') }}</th>
                <th scope="col" class="hidden md:table-cell">{{ __('Last sign-in') }}</th>
                <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($users as $row)
                <tr data-user="{{ $row->username }}" @class(['bg-slate-50 text-slate-400' => $row->trashed()])>
                    @if ($manage)
                        <td>
                            <input type="checkbox" name="ids[]" value="{{ $row->id }}" class="rounded border-slate-300" aria-label="{{ $row->username }}">
                        </td>
                    @endif
                    <td>
                        <span class="font-medium text-slate-900">{{ $row->name }}</span>
                        @if ($row->trashed())
                            <span class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-xs text-slate-600">{{ __('Deactivated') }}</span>
                        @endif
                        @if ($row->email)
                            <span class="block text-xs text-slate-500">{{ $row->email }}</span>
                        @endif
                    </td>
                    <td class="font-mono">{{ $row->username }}</td>
                    <td>{{ __('roles.'.$row->roleName()) }}</td>
                    <td class="hidden md:table-cell">{{ $row->hasTwoFactorEnabled() ? __('On') : __('Off') }}</td>
                    <td class="hidden md:table-cell">{{ $row->last_login_at ? local_digits($row->last_login_at->format('d-m-Y H:i')) : '—' }}</td>
                    <td class="text-right">
                        @can('users.manage')
                            @if (! $row->isOwner() || auth()->user()->isOwner())
                                <a href="{{ route('users.edit', $row) }}" class="font-medium text-brand-700 underline">{{ __('Edit') }}</a>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $span }}" class="py-6 text-center text-slate-500">{{ __('No users match.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($manage)
    <div class="mt-3 flex flex-wrap items-end gap-3">
        <input type="hidden" name="field" value="role">
        <label class="block">
            <span class="label">{{ __('Field') }}</span>
            <select class="input" disabled aria-hidden="true">
                <option>{{ __('Role') }}</option>
            </select>
        </label>
        <label class="block">
            <span class="label">{{ __('Value') }}</span>
            <select name="value" class="input" required>
                @foreach (\App\Support\Access\PermissionRegistry::ROLES as $role)
                    <option value="{{ $role }}">{{ __('roles.'.$role) }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="btn-secondary" data-confirm="{{ __('Change role for selected users?') }}">{{ __('Apply to selected') }}</button>
        <x-bulk-limit-note />
    </div>
    </form>
@endif
{{ $users->links() }}
