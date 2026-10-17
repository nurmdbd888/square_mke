@extends('layouts.app')

@section('title', $payment->number)

@section('content')
    <a href="{{ route('payments.index') }}" class="text-sm text-brand-700 underline">{{ __('Back to payments') }}</a>

    <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900 font-mono">{{ $payment->number }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ local_digits($payment->payment_date->format('d-m-Y')) }}
                · {{ $payment->isReceipt() ? __('Receipt') : __('Payment') }}
                · {{ \App\Domain\Payments\Methods::label($payment->method) }}
                · {{ $payment->partyLabel() }}
                · <span @class([
                    'font-medium',
                    'text-amber-700' => $payment->isDraft(),
                    'text-emerald-700' => $payment->isPosted(),
                    'text-slate-500' => $payment->isVoid(),
                ])>{{ __(ucfirst($payment->status)) }}</span>
                @if ($payment->isPdc())
                    · {{ __('Clearing') }}: {{ __(ucfirst((string) $payment->clearing_status)) }}
                @endif
            </p>
        </div>
        @if (($canWrite ?? $canManage) && ! $payment->isVoid())
            <div class="flex flex-wrap gap-2">
                @unless ($payment->isPosted() && $payment->isPdc() && $payment->clearing_status === 'cleared')
                    <a href="{{ route('payments.edit', $payment) }}" class="btn-secondary">{{ __('Edit') }}</a>
                @endunless
                @if ($payment->isDraft())
                    <form method="POST" action="{{ route('payments.post', $payment) }}">
                        @csrf
                        <button class="btn-primary">{{ __('Post') }}</button>
                    </form>
                @endif
                @if ($payment->isPosted() && $payment->isPdc() && $payment->clearing_status !== 'cleared')
                    <form method="POST" action="{{ route('payments.clear', $payment) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <label class="block">
                            <span class="label">{{ __('Cleared on') }}</span>
                            <input type="date" name="cleared_on" value="{{ now()->toDateString() }}" class="input">
                        </label>
                        <button class="btn-primary">{{ __('Mark cleared') }}</button>
                    </form>
                @endif
                @if ($canManage)
                    <form method="POST" action="{{ route('payments.destroy', $payment) }}" data-confirm="{{ __('Void this payment? Allocations will reverse.') }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn-danger">{{ $payment->isDraft() ? __('Delete draft') : __('Void') }}</button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    <dl class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card"><dt class="text-xs uppercase text-slate-500">{{ __('Amount') }}</dt><dd class="mt-1 font-mono text-lg font-semibold" data-testid="payment-amount">{{ format_money($payment->amount) }}</dd></div>
        <div class="card"><dt class="text-xs uppercase text-slate-500">{{ __('Allocated') }}</dt><dd class="mt-1 font-mono text-lg font-semibold">{{ format_money($payment->allocations->sum('amount')) }}</dd></div>
        <div class="card"><dt class="text-xs uppercase text-slate-500">{{ __('Advance') }}</dt><dd class="mt-1 font-mono text-lg font-semibold">{{ format_money(\App\Domain\Money\Exact::of($payment->amount)->minus(\App\Domain\Money\Exact::of($payment->allocations->sum('amount')))) }}</dd></div>
        <div class="card"><dt class="text-xs uppercase text-slate-500">{{ __('Method') }}</dt><dd class="mt-1 font-semibold">{{ \App\Domain\Payments\Methods::label($payment->method) }}</dd></div>
    </dl>

    @if ($payment->attachments->isNotEmpty())
        <section class="mt-6 card">
            <h2 class="font-semibold text-slate-900">{{ __('Payment slip photos') }}</h2>
            <div class="mt-3 flex flex-wrap gap-3">
                @foreach ($payment->attachments as $photo)
                    @if ($photo->isImage())
                        <a href="{{ route('payments.attachments.show', [$payment, $photo]) }}" class="block overflow-hidden rounded-lg border border-slate-200">
                            <img src="{{ route('payments.attachments.show', [$payment, $photo]) }}" alt="{{ $photo->original_name }}" class="size-24 object-cover sm:size-32" loading="lazy">
                        </a>
                    @else
                        <a href="{{ route('payments.attachments.show', [$payment, $photo]) }}" class="text-sm text-brand-700 underline">{{ $photo->original_name }}</a>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    @if ($payment->allocations->isNotEmpty())
        <div class="mt-6 table-wrap">
            <table class="data-table" data-testid="allocations">
                <thead>
                    <tr>
                        <th>{{ __('Document') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th class="text-right">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payment->allocations as $allocation)
                        <tr>
                            <td class="font-mono">{{ $allocation->document?->number }}</td>
                            <td>{{ $allocation->document?->doc_date ? local_digits($allocation->document->doc_date->format('d-m-Y')) : '—' }}</td>
                            <td class="text-right font-mono">{{ format_money($allocation->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($payment->notes)
        <p class="mt-4 text-sm text-slate-600"><span class="font-medium">{{ __('Notes') }}:</span> {{ $payment->notes }}</p>
    @endif
@endsection
