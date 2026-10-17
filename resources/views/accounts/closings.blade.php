@extends('layouts.app')

@section('title', __('Daily closing'))

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('Daily closing') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Closing balances carry forward to the next day.') }}</p>

    @if ($canManage)
        <form method="POST" action="{{ route('accounts.closings.store') }}" class="mt-6 card grid gap-4 sm:grid-cols-3">
            @csrf
            <label class="block">
                <span class="label">{{ __('Close day') }}</span>
                <input type="date" name="closed_on" value="{{ $previewDate }}" required class="input">
            </label>
            <label class="block sm:col-span-2">
                <span class="label">{{ __('Notes') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                <input type="text" name="notes" maxlength="500" class="input">
            </label>
            <div class="sm:col-span-3">
                <button class="btn-primary">{{ __('Close day') }}</button>
            </div>
        </form>
    @endif

    <div class="mt-6 table-wrap">
        <table class="data-table" data-testid="closings-table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th class="text-right">{{ __('Cash closing') }}</th>
                    <th class="text-right">{{ __('Bank closing') }}</th>
                    <th>{{ __('Notes') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($closings as $closing)
                    <tr>
                        <td>{{ local_digits($closing->closed_on->format('d-m-Y')) }}</td>
                        <td class="text-right font-mono">{{ format_money($closing->cash_closing) }}</td>
                        <td class="text-right font-mono">{{ format_money($closing->bank_closing) }}</td>
                        <td>{{ $closing->notes ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-slate-500">{{ __('No days closed yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $closings->links() }}
@endsection
