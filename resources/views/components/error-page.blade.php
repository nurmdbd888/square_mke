@props([
    'code' => null,
    'title',
    'message',
    'actionHref' => null,
    'actionLabel' => null,
])

@php
    $actionHref ??= \App\Support\SafeAccess::routeExists('dashboard') ? route('dashboard') : url('/');
    $actionLabel ??= __('Back to dashboard');
@endphp

<div class="mx-auto max-w-lg" data-testid="error-page" @if ($code) data-error-code="{{ $code }}" @endif>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-br from-brand-700 to-brand-500 px-5 py-6 text-white sm:px-8">
            <p class="text-xs font-semibold tracking-widest uppercase text-brand-100">{{ __('Square Distributor Manager') }}</p>
            @if ($code)
                <p class="mt-2 font-mono text-sm text-white/80">{{ $code }}</p>
            @endif
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">{{ $title }}</h1>
        </div>
        <div class="space-y-4 px-5 py-6 sm:px-8">
            <p class="text-sm leading-relaxed text-slate-600">{{ $message }}</p>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $actionHref }}" class="btn-primary min-h-11">{{ $actionLabel }}</a>
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : $actionHref }}" class="btn-secondary min-h-11">{{ __('Go back') }}</a>
            </div>
        </div>
    </div>
</div>
