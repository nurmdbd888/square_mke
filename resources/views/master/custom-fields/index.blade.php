@extends('layouts.app')

@section('title', __('Custom fields'))

@section('content')
    <a href="{{ route('masters.index') }}" class="text-sm text-brand-700 underline">{{ __('Back to masters') }}</a>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('Custom fields') }}</h1>
        @can('masters.manage')
            <a href="{{ route('masters.custom-fields.create') }}" class="btn-primary">{{ __('Add custom field') }}</a>
        @endcan
    </div>
    <p class="mt-1 text-sm text-slate-600">{{ __('Extra questions on the product and retailer forms. Removing a field does not erase answers already saved.') }}</p>

    <div class="table-wrap mt-4">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('Used on') }}</th>
                    <th scope="col">{{ __('Label') }}</th>
                    <th scope="col">{{ __('Key') }}</th>
                    <th scope="col">{{ __('Type') }}</th>
                    <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($fields as $field)
                    <tr>
                        <td>{{ $field->entity === 'product' ? __('Product') : __('Retailer') }}</td>
                        <td class="font-medium text-slate-900">{{ $field->label }}</td>
                        <td class="font-mono">{{ $field->key }}</td>
                        <td>{{ $field->type === 'number' ? __('Number') : __('Text') }}</td>
                        <td class="text-right">
                            @can('masters.manage')
                                <a href="{{ route('masters.custom-fields.edit', $field) }}" class="font-medium text-brand-700 underline">{{ __('Edit') }}</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-500">{{ __('No custom fields yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $fields->links() }}
@endsection
