@php
    $showRoute = $type === 'purchase' ? 'purchases.show' : 'sales.show';
@endphp

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('Number') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Party') }}</th>
                <th class="text-right">{{ __('Total') }}</th>
                <th>{{ __('Status') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($documents as $document)
                <tr>
                    <td class="font-mono">{{ $document->number }}</td>
                    <td>{{ local_digits($document->doc_date->format('d-m-Y')) }}</td>
                    <td>{{ $document->partyLabel() }}</td>
                    <td class="text-right font-mono">{{ format_money($document->net_total) }}</td>
                    <td>
                        <span @class([
                            'rounded px-2 py-0.5 text-xs font-medium',
                            'bg-amber-50 text-amber-800' => $document->isDraft(),
                            'bg-emerald-50 text-emerald-800' => $document->isPosted(),
                            'bg-slate-100 text-slate-600' => $document->isVoid(),
                        ])>{{ __(ucfirst($document->status)) }}</span>
                    </td>
                    <td class="text-right"><a href="{{ route($showRoute, $document) }}" class="text-brand-700 underline">{{ __('Open') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-slate-500">{{ __('No documents yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $documents->links() }}
