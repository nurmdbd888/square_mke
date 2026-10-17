@php
    $span = 6 + ($canManage ? 1 : 0);
@endphp
@if ($canManage)
    <form method="POST" action="{{ route('claims.bulk') }}">
        @csrf
@endif
<div class="table-wrap">
    <table class="data-table" data-testid="claims-table">
        <thead>
            <tr>
                @if ($canManage)
                    <th scope="col"><x-bulk-select-all /></th>
                @endif
                <th>{{ __('Number') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Supplier') }}</th>
                <th class="text-right">{{ __('Amount') }}</th>
                <th>{{ __('Status') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($claims as $claim)
                <tr>
                    @if ($canManage)
                        <td>
                            <input type="checkbox" name="ids[]" value="{{ $claim->id }}" class="rounded border-slate-300" aria-label="{{ $claim->number }}">
                        </td>
                    @endif
                    <td class="font-mono">{{ $claim->number }}</td>
                    <td>{{ local_date($claim->doc_date) }}</td>
                    <td>{{ $claim->supplier?->name ?? '—' }}</td>
                    <td class="text-right font-mono">{{ format_money($claim->amount) }}</td>
                    <td>{{ __(ucfirst($claim->status)) }}</td>
                    <td class="text-right"><a href="{{ route('claims.show', $claim) }}" class="text-brand-700 underline">{{ __('Open') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="{{ $span }}" class="py-8 text-center text-slate-500">{{ __('No claims yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($canManage)
    <div class="mt-3 flex flex-wrap items-end gap-3" x-data="{ field: 'status' }">
        <label class="block">
            <span class="label">{{ __('Field') }}</span>
            <select name="field" class="input" x-model="field">
                <option value="status">{{ __('Status') }}</option>
            </select>
        </label>
        <label class="block">
            <span class="label">{{ __('Value') }}</span>
            <select name="value" class="input">
                @foreach (['open', 'submitted', 'approved', 'settled', 'rejected'] as $s)
                    <option value="{{ $s }}">{{ __(ucfirst($s)) }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="btn-secondary" data-confirm="{{ __('Apply to selected rows?') }}">{{ __('Apply to selected') }}</button>
        <x-bulk-limit-note />
    </div>
    </form>
@endif
{{ $claims->links() }}
