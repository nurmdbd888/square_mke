@extends('layouts.app')

@section('title', __('Maintenance'))

@section('content')
    <section class="mx-auto max-w-xl rounded-xl border border-amber-200 bg-amber-50 p-6">
        <h1 class="text-xl font-semibold text-amber-900">{{ __('The Maintenance page is locked') }}</h1>
        <p class="mt-2 text-amber-900">
            {{ __('Set MAINTENANCE_TOKEN to a secret phrase of at least :min characters in the settings file, save it, then reload this page.', ['min' => $min]) }}
        </p>
        <p class="mt-3 break-all font-mono text-sm text-amber-900">{{ $envPath }}</p>
    </section>
@endsection
