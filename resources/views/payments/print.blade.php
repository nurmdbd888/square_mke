@extends('print.layout')

@push('print-styles')
    <style>@page { size: 80mm auto; margin: 4mm; } body { font-size: 11px; }</style>
@endpush

@section('title', $payment->number)

@section('content')
    <div class="firm">{{ setting('firm_name') }}</div>
    <p class="meta">{{ $payment->isReceipt() ? __('Collection receipt') : __('Payment voucher') }}</p>
    <p class="mono" style="font-size: 14px; font-weight: 700;">{{ $payment->number }}</p>
    <p class="meta">{{ format_app_date($payment->payment_date) }} · {{ \App\Domain\Payments\Methods::label($payment->method) }}</p>
    <p><strong>{{ $payment->partyLabel() }}</strong></p>
    <p class="mono" style="font-size: 16px; font-weight: 700;">{{ format_money($payment->amount) }}</p>
    @if ($payment->allocations->isNotEmpty())
        <table class="lines" style="margin-top: 10px;">
            <thead><tr><th>{{ __('Invoice') }}</th><th class="text-right">{{ __('Amount') }}</th></tr></thead>
            <tbody>
                @foreach ($payment->allocations as $row)
                    <tr>
                        <td class="mono">{{ $row->document?->number }}</td>
                        <td class="text-right mono">{{ format_money($row->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    @if ($payment->reference)
        <p class="meta">{{ __('Reference') }}: {{ $payment->reference }}</p>
    @endif
@endsection
