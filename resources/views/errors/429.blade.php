@extends('layouts.guest')

@section('title', __('Too many attempts'))

@section('content')
    <x-error-page
        code="429"
        :title="__('Too many attempts')"
        :message="__('Please wait a minute, then try again.')"
    />
@endsection
