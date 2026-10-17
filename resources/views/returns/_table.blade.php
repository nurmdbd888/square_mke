@php
    $span = 7 + ($canManage ? 1 : 0);
@endphp
@if ($canManage)
    <form method="POST" action="{{ route('returns.bulk') }}">
        @csrf
@endif
<div class="table-wrap">
    <table class="data-table" data-testid="returns-table">
        <thead>
            <tr>
                @if ($canManage)
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
                    @if ($canManage)
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
                    <td class="text-right"><a href="{{ route('returns.show', $note) }}" class="text-brand-700 underline">{{ __('Open') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="{{ $span }}" class="py-8 text-center text-slate-500">{{ __('No stock notes yet.') }}</td></tr>
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
{{ $notes->links() }}
