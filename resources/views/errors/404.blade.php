@extends('layouts.guest')

@section('title', __('Page not found'))

@section('content')
    <x-error-page
        code="404"
        :title="__('Page not found')"
        :message="__('The page you requested does not exist. Check the address, or go back to the dashboard.')"
    />
@endsection
