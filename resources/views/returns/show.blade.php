@extends('layouts.app')

@section('title', $note->number)

@section('content')
    <a href="{{ route('returns.index') }}" class="text-sm text-brand-700 underline">{{ __('Back to returns') }}</a>
    <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $note->number }}</h1>
            <p class="text-sm text-slate-600">{{ local_date($note->doc_date) }} · {{ __(str_replace('_', ' ', ucfirst($note->type))) }} · {{ __(ucfirst($note->status)) }}</p>
        </div>
        @if ($canManage && ! $note->isVoid())
            <div class="flex flex-wrap gap-2">
                @if ($note->isDraft())
                    <form method="POST" action="{{ route('returns.post', $note) }}">@csrf<button class="btn-primary">{{ __('Post') }}</button></form>
                @endif
                <a href="{{ route('returns.edit', $note) }}" class="btn-secondary">{{ __('Edit') }}</a>
                <form method="POST" action="{{ route('returns.destroy', $note) }}" onsubmit="return confirm(@js(__('Delete this note?')))">@csrf @method('DELETE')<button class="btn-secondary">{{ __('Delete') }}</button></form>
            </div>
        @endif
    </div>

    @if ($note->retailer)
        <p class="mt-3 text-sm"><span class="text-slate-500">{{ __('Retailer') }}:</span> {{ $note->retailer->name }}</p>
    @endif

    <div class="mt-4 table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Product') }}</th>
                    <th>{{ __('Condition') }}</th>
                    <th class="text-right">{{ __('Qty') }}</th>
                    <th class="text-right">{{ __('Restore cost') }}</th>
                    <th class="text-right">{{ __('Loss cost') }}</th>
                    <th class="text-right">{{ __('Value') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($note->lines as $line)
                    <tr>
                        <td>{{ $line->product->name_en }}</td>
                        <td>{{ __(ucfirst($line->line_condition)) }}</td>
                        <td class="text-right font-mono">{{ local_digits($line->qty) }}</td>
                        <td class="text-right font-mono">{{ $line->restore_cost !== null ? format_money($line->restore_cost) : '—' }}</td>
                        <td class="text-right font-mono">{{ $line->loss_cost !== null ? format_money($line->loss_cost) : '—' }}</td>
                        <td class="text-right font-mono">{{ format_money($line->line_value) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-sm text-slate-600">{{ __('Stock loss total') }}: <span class="font-mono">{{ format_money($note->stock_loss_total) }}</span></p>
@endsection
