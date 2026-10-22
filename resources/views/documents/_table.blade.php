@php
    $showRoute = $type === 'purchase' ? 'purchases.show' : 'sales.show';
    $editRoute = $type === 'purchase' ? 'purchases.edit' : 'sales.edit';
    $destroyRoute = $type === 'purchase' ? 'purchases.destroy' : 'sales.destroy';
    $bulkRoute = $type === 'purchase' ? 'purchases.bulk' : 'sales.bulk';
    $isSale = $type === 'sale';
    $canManage = $canManage ?? false;
    $canDelete = $canDelete ?? false;
    $bulkAllowed = $canManage || $canDelete;
    $span = 6 + ($isSale ? 1 : 0) + ($bulkAllowed ? 1 : 0);
    $collection = app(\App\Domain\Payments\SaleCollectionStatus::class);
    $collectionReady = $collection->columnsReady();
@endphp
@if ($bulkAllowed)
    <form method="POST" action="{{ route($bulkRoute) }}" id="documents-bulk-form">
        @csrf
@endif
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                @if ($bulkAllowed)
                    <th scope="col"><x-bulk-select-all /></th>
                @endif
                <th>{{ __('Number') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Party') }}</th>
                <th class="text-right">{{ __('Total') }}</th>
                <th>{{ __('Status') }}</th>
                @if ($isSale)
                    <th>{{ __('Collection') }}</th>
                @endif
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($documents as $document)
                <tr>
                    @if ($bulkAllowed)
                        <td>
                            <input type="checkbox" name="ids[]" value="{{ $document->id }}" class="rounded border-slate-300" aria-label="{{ $document->number }}">
                        </td>
                    @endif
                    <td class="font-mono">{{ $document->number }}</td>
                    <td>{{ format_app_date($document->doc_date) }}</td>
                    <td>{{ $document->partyLabel() }}</td>
                    <td class="text-right font-mono">{{ format_money($document->net_total) }}</td>
                    <td>
                        <span @class([
                            'rounded px-2 py-0.5 text-xs font-medium',
                            'bg-amber-50 text-amber-800' => $document->isDraft(),
                            'bg-emerald-50 text-emerald-800' => $document->isPosted(),
                            'bg-slate-100 text-slate-600' => $document->isVoid(),
                        ])>{{ __(ucfirst($document->status)) }}</span>
                        @if ($isSale && $document->isPosted() && $document->doc_date->lt(now()->subDays(30)))
                            @php
                                try {
                                    $rem = $collection->remainingAmount($document);
                                    $isOverdue = $rem->isPositive();
                                } catch (\Throwable) {
                                    $isOverdue = false;
                                }
                            @endphp
                            @if ($isOverdue)
                                <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800">{{ __('Overdue') }}</span>
                            @endif
                        @endif
                    </td>
                    @if ($isSale)
                        <td>
                            @if ($document->isPosted() && $collectionReady)
                                <span @class([
                                    'rounded px-2 py-0.5 text-xs font-medium',
                                    'bg-red-50 text-red-800' => in_array($document->collection_status, [null, 'due'], true),
                                    'bg-amber-50 text-amber-900' => $document->collection_status === 'partial',
                                    'bg-emerald-50 text-emerald-800' => $document->collection_status === 'completed',
                                ])>{{ $collection->label($document->collection_status) }}</span>
                            @else
                                —
                            @endif
                        </td>
                    @endif
                    <td class="text-right">
                        <x-row-actions
                            :view="route($showRoute, $document)"
                            :view-label="__('Open')"
                            :edit="$canManage && ! $document->isVoid() ? route($editRoute, $document) : null"
                        >
                            @if ($canDelete && ! $document->isVoid())
                                <x-delete-form
                                    :action="route($destroyRoute, $document)"
                                    :confirm="$document->isDraft() ? __('Delete this draft?') : __('Void this document? Posted stock will be recomputed.')"
                                    :label="$document->isDraft() ? __('Delete') : __('Void')"
                                />
                            @endif
                        </x-row-actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $span }}" class="py-8 text-center text-slate-500">{{ __('No documents yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($bulkAllowed)
    @php
        $bulkActions = [];
        if ($canManage) {
            $bulkActions['post'] = __('Post drafts');
        }
        if ($canDelete) {
            $bulkActions['void'] = __('Void or delete');
        }
    @endphp
    <x-bulk-action-bar :actions="$bulkActions" />
    </form>
@endif
{{ $documents->links() }}
