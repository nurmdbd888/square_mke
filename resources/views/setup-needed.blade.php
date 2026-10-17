@extends('layouts.app')

@section('title', __('Setup needed'))

@section('content')
    <section class="rounded-xl border border-amber-200 bg-amber-50 p-6">
        <h1 class="text-xl font-semibold text-amber-900">{{ __('Setup is not finished yet') }}</h1>
        <p class="mt-2 text-amber-900">
            {{ __('The app key (APP_KEY) is missing from the settings file. Open the Maintenance page and press "Generate APP_KEY".') }}
        </p>
        <a href="{{ route('maintenance') }}" class="mt-4 inline-flex rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
            {{ __('Open the Maintenance page') }}
        </a>
    </section>
@endsection
