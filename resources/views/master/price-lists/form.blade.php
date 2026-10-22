@extends('layouts.app')

@section('title', __('Add price list'))

@section('content')
    <x-back-link :href="route('masters.price-lists.index')">{{ __('Back to price lists') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ __('Add price list') }}</h1>

    <section class="card mt-6 max-w-lg">
        <form method="POST" action="{{ route('masters.price-lists.store') }}" class="grid gap-4">
            @csrf
            <label class="block">
                <span class="label">{{ __('Name') }}</span>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="120" class="input">
            </label>
            <button class="btn-primary w-fit">{{ __('Add price list') }}</button>
        </form>
    </section>
@endsection
