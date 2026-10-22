@extends('layouts.guest')

@section('title', __('Not allowed'))

@section('content')
    <x-error-page
        code="403"
        :title="__('Not allowed')"
        :message="__('You do not have permission to do that. Sign in with a different account, or ask an owner.')"
    />
@endsection
