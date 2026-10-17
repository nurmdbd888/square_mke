<div class="table-wrap">
    <table class="data-table" data-testid="users-table">
        <thead>
            <tr>
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
                <tr><td colspan="6" class="py-6 text-center text-slate-500">{{ __('No users match.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $users->links() }}
