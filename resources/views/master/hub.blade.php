@extends('layouts.app')

@section('title', __('Masters'))

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('Masters') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Suppliers, routes, sales reps, vehicles, price lists, opening balances and custom fields.') }}</p>

    <ul class="mt-6 grid gap-3 sm:grid-cols-2">
        @foreach ($links as $link)
            <li>
                <a href="{{ $link['url'] }}" class="card block hover:bg-slate-50">
                    <span class="font-semibold text-slate-900">{{ $link['label'] }}</span>
                    <span class="mt-1 block text-sm text-slate-500">{{ local_digits($link['count']) }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@endsection
