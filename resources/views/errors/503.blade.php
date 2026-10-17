@extends('layouts.guest')

@section('title', __('Service unavailable'))

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('Service unavailable') }}</h1>
    <p class="mt-2 text-sm text-slate-600">{{ __('The app is temporarily down for maintenance. Try again in a few minutes.') }}</p>
@endsection
