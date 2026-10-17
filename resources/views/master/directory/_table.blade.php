<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                @foreach ($meta['columns'] as $label)
                    <th scope="col">{{ $label }}</th>
                @endforeach
                <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($rows as $row)
                <tr @class(['bg-slate-50 text-slate-400' => $row->trashed()])>
                    @foreach ($meta['columns'] as $key => $label)
                        <td>
                            @if ($key === 'user')
                                {{ $row->user?->name ?: '—' }}
                            @elseif ($key === 'phone' && $row->phone)
                                {{ local_digits($row->phone) }}
                            @else
                                {{ $row->{$key} ?: '—' }}
                            @endif
                            @if ($loop->first && $row->trashed())
                                <span class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-xs text-slate-600">{{ __('Deactivated') }}</span>
                            @endif
                        </td>
                    @endforeach
                    <td class="text-right">
                        @can('masters.manage')
                            <a href="{{ route('masters.directory.edit', [$kind, $row]) }}" class="font-medium text-brand-700 underline">{{ __('Edit') }}</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ count($meta['columns']) + 1 }}" class="py-6 text-center text-slate-500">{{ $meta['empty'] }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $rows->links() }}
