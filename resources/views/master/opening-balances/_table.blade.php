<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th scope="col">{{ __('Kind') }}</th>
                <th scope="col">{{ __('As of') }}</th>
                <th scope="col">{{ __('For') }}</th>
                <th scope="col">{{ __('Quantity') }}</th>
                <th scope="col">{{ __('Amount') }}</th>
                <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($rows as $row)
                <tr>
                    <td>{{ ['stock' => __('Stock'), 'receivable' => __('Receivable'), 'payable' => __('Payable'), 'cash' => __('Cash'), 'bank' => __('Bank')][$row->kind] ?? $row->kind }}</td>
                    <td>{{ local_digits($row->as_of->format('d-m-Y')) }}</td>
                    <td>
                        {{ $row->product?->name_en ?? $row->retailer?->name ?? $row->supplier?->name ?? '—' }}
                        @if ($row->note)
                            <span class="block text-xs text-slate-500">{{ $row->note }}</span>
                        @endif
                    </td>
                    <td>{{ $row->qty !== null ? format_number($row->qty, 2) : '—' }}</td>
                    <td>{{ format_money($row->amount) }}</td>
                    <td class="text-right">
                        @can('masters.manage')
                            <a href="{{ route('masters.opening-balances.edit', $row) }}" class="font-medium text-brand-700 underline">{{ __('Edit') }}</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-slate-500">{{ __('No opening balances yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $rows->links() }}
