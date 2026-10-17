@php
    $firmName = setting('firm_name');
    $user = auth()->user();
    $nav = array_filter([
        ['route' => 'dashboard', 'label' => __('Dashboard'), 'active' => 'dashboard', 'can' => null],
        ['route' => 'products.index', 'label' => __('Products'), 'active' => 'products.*', 'can' => 'products.view'],
        ['route' => 'retailers.index', 'label' => __('Retailers'), 'active' => 'retailers.*', 'can' => 'retailers.view'],
        ['route' => 'purchases.index', 'label' => __('Purchases'), 'active' => 'purchases.*', 'can' => 'purchases.view'],
        ['route' => 'sales.index', 'label' => __('Sales'), 'active' => 'sales.*', 'can' => 'sales.view'],
        ['route' => 'stock.index', 'label' => __('Stock'), 'active' => 'stock.*', 'can' => 'stock.view'],
        ['route' => 'returns.index', 'label' => __('Returns'), 'active' => 'returns.*', 'can' => 'returns.view'],
        ['route' => 'claims.index', 'label' => __('Claims'), 'active' => 'claims.*', 'can' => 'claims.view'],
        ['route' => 'payments.index', 'label' => __('Payments'), 'active' => 'payments.*', 'can' => 'payments.view'],
        ['route' => 'accounts.receivables', 'label' => __('Receivables'), 'active' => 'accounts.receivables', 'can' => 'accounts.view'],
        ['route' => 'accounts.payables', 'label' => __('Payables'), 'active' => 'accounts.payables', 'can' => 'accounts.view'],
        ['route' => 'accounts.cash-book', 'label' => __('Cash book'), 'active' => 'accounts.cash-book', 'can' => 'accounts.view'],
        ['route' => 'accounts.bank-book', 'label' => __('Bank book'), 'active' => 'accounts.bank-book', 'can' => 'accounts.view'],
        ['route' => 'accounts.day-book', 'label' => __('Day book'), 'active' => 'accounts.day-book', 'can' => 'accounts.view'],
        ['route' => 'accounts.closings', 'label' => __('Daily closing'), 'active' => 'accounts.closings', 'can' => 'accounts.view'],
        ['route' => 'reports.pl', 'label' => __('Reports'), 'active' => 'reports.*', 'can' => 'reports.view'],
        ['route' => 'exports.downloads', 'label' => __('Downloads'), 'active' => 'exports.*', 'can' => 'exports.view'],
        ['route' => 'masters.index', 'label' => __('Masters'), 'active' => 'masters.*', 'can' => 'masters.view'],
        ['route' => 'users.index', 'label' => __('Users'), 'active' => 'users.*', 'can' => 'users.view'],
        ['route' => 'roles.index', 'label' => __('Roles and permissions'), 'active' => 'roles.*', 'can' => 'roles.manage'],
        ['route' => 'settings.index', 'label' => __('Settings'), 'active' => 'settings.*', 'can' => 'settings.view'],
        ['route' => 'audit.index', 'label' => __('Audit log'), 'active' => 'audit.*', 'can' => 'audit.view'],
        ['route' => 'health', 'label' => __('System health'), 'active' => 'health', 'can' => 'health.view'],
    ], fn ($item) => $item['can'] === null || $user->can($item['can']));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Square Distributor Manager')) · {{ $firmName }}</title>
    @include('layouts.partials.assets')
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased" x-data="{ menu: false }">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:m-2 focus:rounded focus:bg-white focus:p-2">{{ __('Skip to content') }}</a>

    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white">
        <div class="flex items-center justify-between gap-3 px-4 py-2.5">
            <div class="flex items-center gap-3">
                <button type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" @click="menu = !menu"
                        :aria-expanded="menu.toString()" aria-controls="sidebar" aria-label="{{ __('Menu') }}">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-lg bg-brand-600 font-bold text-white">S</span>
                    <span class="leading-tight">
                        <span class="block text-sm font-semibold text-slate-900">{{ __('Square Distributor Manager') }}</span>
                        <span class="block text-xs text-slate-500" data-testid="firm-name">{{ $firmName }}</span>
                    </span>
                </a>
            </div>

            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1 text-sm" role="group" aria-label="{{ __('Language') }}">
                    @foreach (config('sdm.supported_locales') as $code => $label)
                        <form method="POST" action="{{ route('profile.locale', $code) }}">
                            @csrf
                            <button lang="{{ $code }}" @class([
                                'rounded-md px-2 py-1',
                                'bg-brand-50 font-semibold text-brand-700' => app()->getLocale() === $code,
                                'text-slate-600 hover:bg-slate-100' => app()->getLocale() !== $code,
                            ]) @if (app()->getLocale() === $code) aria-current="true" @endif>{{ $label }}</button>
                        </form>
                    @endforeach
                </div>

                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-100" @click="open = !open" :aria-expanded="open.toString()">
                        <span class="grid size-8 place-items-center rounded-full bg-slate-200 text-xs font-semibold uppercase text-slate-700">{{ mb_substr($user->name, 0, 1) }}</span>
                        <span class="hidden text-left leading-tight sm:block">
                            <span class="block font-medium text-slate-800">{{ $user->name }}</span>
                            <span class="block text-xs text-slate-500">{{ __('roles.'.$user->roleName()) }}</span>
                        </span>
                    </button>
                    <div x-cloak x-show="open" x-transition class="absolute right-0 mt-1 w-48 rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg">
                        <a href="{{ route('profile') }}" class="block px-4 py-2 hover:bg-slate-50">{{ __('My profile') }}</a>
                        @if ($user->isOwner())
                            <a href="{{ route('maintenance') }}" class="block px-4 py-2 hover:bg-slate-50">{{ __('Maintenance page') }}</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="block w-full px-4 py-2 text-left text-red-700 hover:bg-red-50">{{ __('Sign out') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="lg:flex">
        <div x-cloak x-show="menu" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden" @click="menu = false"></div>
        <nav id="sidebar" aria-label="{{ __('Main menu') }}"
             :class="menu ? 'translate-x-0' : '-translate-x-full'"
             class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto border-r border-slate-200 bg-white px-3 py-4 transition-transform lg:sticky lg:top-[57px] lg:h-[calc(100vh-57px)] lg:translate-x-0">
            <ul class="space-y-1">
                @foreach ($nav as $item)
                    <li>
                        <a href="{{ route($item['route']) }}" data-nav="{{ $item['route'] }}"
                           @class([
                               'block rounded-lg px-3 py-2 text-sm font-medium',
                               'bg-brand-50 text-brand-700' => request()->routeIs($item['active']),
                               'text-slate-700 hover:bg-slate-100' => ! request()->routeIs($item['active']),
                           ])
                           @if (request()->routeIs($item['active'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <main id="main" class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <x-flash />
            @yield('content')
        </main>
    </div>
</body>
</html>
