@extends('layouts.app')

@section('title', $claim->number)

@section('content')
    <a href="{{ route('claims.index') }}" class="text-sm text-brand-700 underline">{{ __('Back to claims') }}</a>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $claim->number }}</h1>
    <p class="text-sm text-slate-600">{{ local_date($claim->doc_date) }} · {{ __(ucfirst($claim->status)) }}</p>
    <dl class="mt-4 grid max-w-lg gap-2 text-sm">
        <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Supplier') }}</dt><dd>{{ $claim->supplier?->name ?? '—' }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Amount') }}</dt><dd class="font-mono">{{ format_money($claim->amount) }}</dd></div>
    </dl>
    @if ($claim->description)
        <p class="mt-4 text-sm">{{ $claim->description }}</p>
    @endif
    @if (($canManage || ($canDelete ?? false)) && ! in_array($claim->status, ['settled', 'rejected'], true))
        <div class="mt-4 flex flex-wrap gap-2">
            @if ($canManage)
                <a href="{{ route('claims.edit', $claim) }}" class="btn-secondary inline-flex">{{ __('Edit') }}</a>
            @endif
            @if ($canDelete ?? false)
                <x-delete-form
                    :action="route('claims.destroy', $claim)"
                    :confirm="__('Delete this claim?')"
                />
            @endif
        </div>
    @endif
@endsection
