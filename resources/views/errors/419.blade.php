@extends('layouts.guest')

@section('title', __('Page expired'))

@section('content')
    <x-error-page
        code="419"
        :title="__('Page expired')"
        :message="__('Your session timed out for safety. Go back and try again — your work on the previous page may still be there.')"
        :action-href="url()->previous() !== url()->current() ? url()->previous() : url('/')"
        :action-label="__('Try again')"
    />
@endsection
