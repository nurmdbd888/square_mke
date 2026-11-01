@extends('layouts.app')

@section('title', __('Product types'))

@section('content')
    <x-back-link :href="route('masters.index')">{{ __('Back to masters') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ __('Product types') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Create the second level of the catalog under each brand.') }}</p>

    <section class="card mt-6">
        <h2 class="font-semibold text-slate-900">{{ __('Add product type') }}</h2>
        <form method="POST" action="{{ route('masters.product-types.store') }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
            @csrf
            <label>
                <span class="label">{{ __('Brand') }}</span>
                <select name="brand_id" required class="input">
                    <option value="">{{ __('Choose brand') }}</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->id }}" @selected((string) old('brand_id') === (string) $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">{{ __('Product type') }}</span>
                <input name="name" value="{{ old('name') }}" required maxlength="80" class="input">
            </label>
            <button class="btn-primary self-end">{{ __('Add product type') }}</button>
        </form>
    </section>

    <div class="mt-6 grid gap-4">
        @forelse ($brands as $brand)
            <section class="card">
                <h2 class="font-semibold text-slate-900">{{ $brand->name }}</h2>
                <div class="mt-3 divide-y divide-slate-100">
                    @forelse ($brand->productTypes as $type)
                        <form method="POST" action="{{ route('masters.product-types.update', $type) }}" class="grid gap-3 py-3 sm:grid-cols-[1fr_auto]">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="brand_id" value="{{ $brand->id }}">
                            <label>
                                <span class="sr-only">{{ __('Product type') }}</span>
                                <input name="name" value="{{ $type->name }}" required maxlength="80" class="input">
                            </label>
                            <button class="btn-secondary">{{ __('Save') }}</button>
                        </form>
                    @empty
                        <p class="py-3 text-sm text-slate-500">{{ __('No product types yet.') }}</p>
                    @endforelse
                </div>
            </section>
        @empty
            <p class="card text-sm text-slate-500">{{ __('Add a brand before creating product types.') }}</p>
        @endforelse
    </div>
@endsection
