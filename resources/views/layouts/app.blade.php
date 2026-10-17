<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('Square Dealer Manager')) · {{ config('sdm.firm_name') }}</title>
    @php($css = public_path('css/app.css'))
    <link rel="stylesheet" href="{{ asset('css/app.css') }}{{ is_file($css) ? '?v='.filemtime($css) : '' }}">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-lg bg-brand-600 text-lg font-bold text-white">S</span>
                <span class="leading-tight">
                    <span class="block text-sm font-semibold text-slate-900">{{ __('Square Dealer Manager') }}</span>
                    <span class="block text-xs text-slate-500">{{ config('sdm.firm_name') }}</span>
                </span>
            </a>
            <nav class="flex items-center gap-1 text-sm" aria-label="{{ __('Language') }}">
                @foreach (config('sdm.supported_locales') as $code => $label)
                    <a href="{{ route('locale.switch', $code) }}" lang="{{ $code }}"
                       @class([
                           'rounded-md px-2.5 py-1',
                           'bg-brand-50 font-semibold text-brand-700' => app()->getLocale() === $code,
                           'text-slate-600 hover:bg-slate-100' => app()->getLocale() !== $code,
                       ])
                       @if (app()->getLocale() === $code) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        @yield('content')
    </main>

    <footer class="mx-auto max-w-5xl px-4 pb-8 text-xs text-slate-400">
        {{ config('sdm.firm_name') }} · {{ __('Square Toiletries Ltd. distributor') }} {{ config('sdm.distributor_code') }}
    </footer>
</body>
</html>
