@extends('layouts.guest')

@section('title', __('Method not allowed'))

@section('content')
    <x-error-page
        code="405"
        :title="__('Method not allowed')"
        :message="__('That action cannot be done this way. Go back to the list and use the buttons there — or Clear caches on Maintenance if a bulk Delete just failed.')"
    />
@endsection
