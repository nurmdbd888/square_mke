@php
    $bulkAllowed = ($canCollect ?? false) || ($canManage ?? false);
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
                    <td>{{ local_digits($payment->payment_date->format('d-m-Y')) }}</td>
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
                    <td class="text-right"><a href="{{ route('payments.show', $payment) }}" class="text-brand-700 underline">{{ __('Open') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="{{ $span }}" class="py-8 text-center text-slate-500">{{ __('No payments yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($bulkAllowed)
    @php
        $bulkActions = ['post' => __('Post drafts')];
        if ($canManage ?? false) {
            $bulkActions['void'] = __('Void');
        }
    @endphp
    <x-bulk-action-bar :actions="$bulkActions" />
    </form>
@endif
{{ $payments->links() }}
