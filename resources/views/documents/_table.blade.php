@php
    $showRoute = $type === 'purchase' ? 'purchases.show' : 'sales.show';
    $bulkRoute = $type === 'purchase' ? 'purchases.bulk' : 'sales.bulk';
    $span = 6 + ($canManage ? 1 : 0);
@endphp
@if ($canManage)
    <form method="POST" action="{{ route($bulkRoute) }}">
        @csrf
@endif
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                @if ($canManage)
                    <th scope="col"><x-bulk-select-all /></th>
                @endif
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
                    @if ($canManage)
                        <td>
                            <input type="checkbox" name="ids[]" value="{{ $document->id }}" class="rounded border-slate-300" aria-label="{{ $document->number }}">
                        </td>
                    @endif
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
                <tr><td colspan="{{ $span }}" class="py-8 text-center text-slate-500">{{ __('No documents yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($canManage)
    <x-bulk-action-bar :actions="[
        'post' => __('Post drafts'),
        'void' => __('Void or delete'),
    ]" />
    </form>
@endif
{{ $documents->links() }}
