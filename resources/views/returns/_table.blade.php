@php
    $canManage = $canManage ?? false;
    $canDelete = $canDelete ?? false;
    $bulkAllowed = $canManage || $canDelete;
    $span = 7 + ($bulkAllowed ? 1 : 0);
@endphp
@if ($bulkAllowed)
    <form method="POST" action="{{ route('returns.bulk') }}">
        @csrf
@endif
<div class="table-wrap">
    <table class="data-table" data-testid="returns-table">
        <thead>
            <tr>
                @if ($bulkAllowed)
                    <th scope="col"><x-bulk-select-all /></th>
                @endif
                <th>{{ __('Number') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Retailer') }}</th>
                <th class="text-right">{{ __('Total') }}</th>
                <th>{{ __('Status') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($notes as $note)
                <tr>
                    @if ($bulkAllowed)
                        <td>
                            <input type="checkbox" name="ids[]" value="{{ $note->id }}" class="rounded border-slate-300" aria-label="{{ $note->number }}">
                        </td>
                    @endif
                    <td class="font-mono">{{ $note->number }}</td>
                    <td>{{ local_digits($note->doc_date->format('d-m-Y')) }}</td>
                    <td>{{ __(str_replace('_', ' ', ucfirst($note->type))) }}</td>
                    <td>{{ $note->retailer?->name ?? '—' }}</td>
                    <td class="text-right font-mono">{{ format_money($note->net_total) }}</td>
                    <td><span class="badge">{{ __(ucfirst($note->status)) }}</span></td>
                    <td class="text-right">
                        <x-row-actions
                            :view="route('returns.show', $note)"
                            :view-label="__('Open')"
                            :edit="$canManage && ! $note->isVoid() ? route('returns.edit', $note) : null"
                        >
                            @if ($canDelete && ! $note->isVoid())
                                <x-delete-form
                                    :action="route('returns.destroy', $note)"
                                    :confirm="$note->isDraft() ? __('Delete this draft?') : __('Void this note? Stock will be recomputed.')"
                                    :label="$note->isDraft() ? __('Delete') : __('Void')"
                                />
                            @endif
                        </x-row-actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $span }}" class="py-8 text-center text-slate-500">{{ __('No stock notes yet.') }}</td></tr>
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
{{ $notes->links() }}
