@extends('layouts.app')

@section('title', __('Stock comparison'))

@section('content')
    <x-back-link :href="route('stock.index')">{{ __('Back to stock') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ __('Stock comparison') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Upload a CSV export from your master software (code, name, quantity columns).') }}</p>

    @if ($canCompare)
        <form method="POST" action="{{ route('stock.comparison.store') }}" enctype="multipart/form-data" class="mt-4 card max-w-xl space-y-3">
            @csrf
            <label class="block">
                <span class="label">{{ __('CSV file') }}</span>
                <input type="file" name="file" accept=".csv,text/csv" required class="input">
            </label>
            <button class="btn-primary">{{ __('Upload and compare') }}</button>
        </form>
    @endif

    <h2 class="mt-8 text-lg font-semibold">{{ __('Recent comparisons') }}</h2>
    <ul class="mt-2 divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white">
        @forelse ($recent as $row)
            <li class="flex items-center justify-between px-4 py-2 text-sm">
                <span>{{ $row->original_name }} · {{ $row->lines_count }} {{ __('rows') }}</span>
                <a href="{{ route('stock.comparison.show', $row) }}" class="text-brand-700 underline">{{ __('Open') }}</a>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-slate-500">{{ __('No comparisons yet.') }}</li>
        @endforelse
    </ul>
@endsection
