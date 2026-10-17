@extends('layouts.app')

@section('title', $list->name)

@section('content')
    <a href="{{ route('masters.price-lists.index') }}" class="text-sm text-brand-700 underline">{{ __('Back to price lists') }}</a>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $list->name }}</h1>

    @if ($list->trashed())
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <span>{{ __('This price list is deactivated.') }}</span>
            @can('masters.manage')
                <form method="POST" action="{{ route('masters.price-lists.restore', $list) }}">
                    @csrf
                    <button class="btn-secondary">{{ __('Reactivate') }}</button>
                </form>
            @endcan
        </div>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-[2fr_1fr]">
        <section class="card">
            <h2 class="font-semibold text-slate-900">{{ __('Prices') }}</h2>
            <div class="table-wrap mt-3">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Product') }}</th>
                            <th scope="col">{{ __('Price') }}</th>
                            <th scope="col">{{ __('Effective from') }}</th>
                            <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($list->items as $item)
                            <tr>
                                <td>
                                    <span class="font-medium text-slate-900">{{ $item->product?->name_en }}</span>
                                    @if ($item->product?->code)
                                        <span class="block font-mono text-xs text-slate-500">{{ $item->product->code }}</span>
                                    @endif
                                </td>
                                <td>{{ format_number($item->price, 2) }}</td>
                                <td>{{ local_digits($item->effective_from->format('d-m-Y')) }}</td>
                                <td class="text-right">
                                    @can('masters.manage')
                                        @unless ($list->trashed())
                                            <form method="POST" action="{{ route('masters.price-lists.items.destroy', [$list, $item]) }}" data-confirm="{{ __('Remove this price?') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="font-medium text-red-700 underline">{{ __('Remove') }}</button>
                                            </form>
                                        @endunless
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-slate-500">{{ __('No prices yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @can('masters.manage')
            <aside class="space-y-6">
                @unless ($list->trashed())
                    <section class="card">
                        <h2 class="font-semibold text-slate-900">{{ __('Add price') }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ __('Saving the same product and date again replaces that price.') }}</p>
                        <form method="POST" action="{{ route('masters.price-lists.items.store', $list) }}" class="mt-3 grid gap-3">
                            @csrf
                            <label class="block">
                                <span class="label">{{ __('Product') }}</span>
                                <select name="product_id" class="input" required>
                                    <option value="">{{ __('Choose') }}</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>{{ $product->code ? $product->code.' — ' : '' }}{{ $product->name_en }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="label">{{ __('Price') }}</span>
                                <input type="text" name="price" value="{{ old('price') }}" required inputmode="decimal" class="input">
                            </label>
                            <label class="block">
                                <span class="label">{{ __('Effective from') }}</span>
                                <input type="date" name="effective_from" value="{{ old('effective_from', now()->toDateString()) }}" required class="input">
                            </label>
                            <button class="btn-primary w-fit">{{ __('Save price') }}</button>
                        </form>
                    </section>
                    <section class="card">
                        <h2 class="font-semibold text-slate-900">{{ __('Rename') }}</h2>
                        <form method="POST" action="{{ route('masters.price-lists.update', $list) }}" class="mt-3 grid gap-3">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ old('name', $list->name) }}" required maxlength="120" aria-label="{{ __('Name') }}" class="input">
                            <button class="btn-secondary w-fit">{{ __('Save') }}</button>
                        </form>
                    </section>
                    <section class="card">
                        <h2 class="font-semibold text-slate-900">{{ __('Deactivate') }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ __('Retailers keep the link. You can reactivate the list later.') }}</p>
                        <form method="POST" action="{{ route('masters.price-lists.destroy', $list) }}" class="mt-3" data-confirm="{{ __('Deactivate this price list?') }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn-danger">{{ __('Deactivate') }}</button>
                        </form>
                    </section>
                @endunless
            </aside>
        @endcan
    </div>
@endsection
