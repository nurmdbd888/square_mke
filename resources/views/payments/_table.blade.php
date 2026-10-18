@php
    $canManage = $canManage ?? false;
    $canDelete = $canDelete ?? false;
    $canCollect = $canCollect ?? false;
    $bulkAllowed = $canCollect || $canManage || $canDelete;
    $span = 8 + ($bulkAllowed ? 1 : 0);
@endphp
@if ($bulkAllowed)
    <form method="POST" action="{{ route('payments.bulk') }}">
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
                <th>{{ __('Kind') }}</th>
                <th>{{ __('Party') }}</th>
                <th>{{ __('Method') }}</th>
                <th class="text-right">{{ __('Amount') }}</th>
                <th>{{ __('Status') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $payment)
                <tr>
                    @if ($bulkAllowed)
                        <td>
                            <input type="checkbox" name="ids[]" value="{{ $payment->id }}" class="rounded border-slate-300" aria-label="{{ $payment->number }}">
                        </td>
                    @endif
                    <td class="font-mono">{{ $payment->number }}</td>
                    <td>{{ format_app_date($payment->payment_date) }}</td>
                    <td>{{ $payment->isReceipt() ? __('Receipt') : __('Payment') }}</td>
                    <td>{{ $payment->partyLabel() }}</td>
                    <td>{{ \App\Domain\Payments\Methods::label($payment->method) }}
                        @if ($payment->isPdc())
                            <span class="ml-1 rounded bg-slate-100 px-1.5 text-xs">{{ __(ucfirst((string) $payment->clearing_status)) }}</span>
                        @endif
                    </td>
                    <td class="text-right font-mono">{{ format_money($payment->amount) }}</td>
                    <td>
                        <span @class([
                            'rounded px-2 py-0.5 text-xs font-medium',
                            'bg-amber-50 text-amber-800' => $payment->isDraft(),
                            'bg-emerald-50 text-emerald-800' => $payment->isPosted(),
                            'bg-slate-100 text-slate-600' => $payment->isVoid(),
                        ])>{{ __(ucfirst($payment->status)) }}</span>
                    </td>
                    <td class="text-right">
                        <x-row-actions
                            :view="route('payments.show', $payment)"
                            :view-label="__('Open')"
                            :edit="$canManage || ($canCollect && $payment->isReceipt() && $payment->isDraft()) ? route('payments.edit', $payment) : null"
                        >
                            @if ($payment->isPosted())
                                <a href="{{ route('payments.print', $payment) }}" class="btn-secondary px-2.5 py-1 text-xs" target="_blank" rel="noopener">{{ __('Print') }}</a>
                            @endif
                            @if ($canDelete && ! $payment->isVoid())
                                <x-delete-form
                                    :action="route('payments.destroy', $payment)"
                                    :confirm="$payment->isDraft() ? __('Delete this draft?') : __('Void this payment? Allocations will reverse.')"
                                    :label="$payment->isDraft() ? __('Delete') : __('Void')"
                                />
                            @endif
                        </x-row-actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $span }}" class="py-8 text-center text-slate-500">{{ __('No payments yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($bulkAllowed)
    @php
        $bulkActions = [];
        if ($canCollect || $canManage) {
            $bulkActions['post'] = __('Post drafts');
        }
        if ($canDelete) {
            $bulkActions['void'] = __('Void');
        }
    @endphp
    <x-bulk-action-bar :actions="$bulkActions" />
    </form>
@endif
{{ $payments->links() }}
