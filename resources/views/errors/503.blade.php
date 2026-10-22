@extends('layouts.guest')

@section('title', __('Service unavailable'))

@section('content')
    <x-error-page
        code="503"
        :title="__('Service unavailable')"
        :message="__('The app is temporarily down for maintenance. Try again in a few minutes.')"
        :action-href="url('/')"
        :action-label="__('Try again')"
    />
@endsection
