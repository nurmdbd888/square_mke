<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th scope="col">{{ __('Name') }}</th>
                <th scope="col">{{ __('Prices') }}</th>
                <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($lists as $row)
                <tr @class(['bg-slate-50 text-slate-400' => $row->trashed()])>
                    <td>
                        <a href="{{ route('masters.price-lists.show', $row) }}" class="font-medium text-brand-700 underline">{{ $row->name }}</a>
                        @if ($row->trashed())
                            <span class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-xs text-slate-600">{{ __('Deactivated') }}</span>
                        @endif
                    </td>
                    <td>{{ local_digits($row->items_count) }}</td>
                    <td class="text-right">
                        <a href="{{ route('masters.price-lists.show', $row) }}" class="font-medium text-brand-700 underline">{{ __('Open') }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-6 text-center text-slate-500">{{ __('No price lists match.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $lists->links() }}
