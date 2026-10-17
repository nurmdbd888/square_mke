@extends('layouts.guest')

@section('title', __('Page not found'))

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('Page not found') }}</h1>
    <p class="mt-2 text-sm text-slate-600">{{ __('The page you requested does not exist.') }}</p>
    <a href="{{ route('dashboard') }}" class="btn-primary mt-4 inline-flex">{{ __('Back to dashboard') }}</a>
@endsection
