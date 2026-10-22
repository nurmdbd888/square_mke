@extends('layouts.guest')

@section('title', __('Something went wrong'))

@section('content')
    <x-error-page
        code="500"
        :title="__('Something went wrong')"
        :message="__('The app hit an unexpected error. Try again in a moment. If it keeps happening, open Maintenance and press Clear caches, or tell your support contact.')"
    />
@endsection
