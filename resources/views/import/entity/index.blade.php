@extends('layouts.app')

@section('title', __('Import :entity', ['entity' => __($def['title'])]))

@section('content')
    @php
        $listParams = $def['list_route_params'] ?? [];
    @endphp
    <x-back-link :href="route($def['list_route'], ...$listParams)">{{ __('Back to list') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold">{{ __('Import :entity', ['entity' => __($def['title'])]) }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Download the template, fill rows, then upload for preview. PDF is not supported for generic import (use stock comparison for stock PDFs).') }}</p>

    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ route('imports.entity.template', [$entity, 'format' => 'csv']) }}" class="btn-secondary">{{ __('Download CSV template') }}</a>
        <a href="{{ route('imports.entity.template', [$entity, 'format' => 'xlsx']) }}" class="btn-secondary">{{ __('Download Excel template') }}</a>
    </div>

    <form method="POST" action="{{ route('imports.entity.store', $entity) }}" enctype="multipart/form-data" class="mt-4 card max-w-xl space-y-3">
        @csrf
        <label class="block"><span class="label">{{ __('File') }}</span><input type="file" name="file" required class="input" accept=".csv,.xls,.xlsx"></label>
        @if ($profiles->isNotEmpty())
            <label class="block">
                <span class="label">{{ __('Mapping profile') }}</span>
                <select name="mapping_profile_id" class="input">
                    <option value="">{{ __('Match template column headers') }}</option>
                    @foreach ($profiles as $profile)
                        <option value="{{ $profile->id }}">{{ $profile->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <button class="btn-primary">{{ __('Upload for preview') }}</button>
    </form>

    <form method="POST" action="{{ route('imports.entity.profiles.store', $entity) }}" class="mt-6 card max-w-xl space-y-3">
        @csrf
        <h2 class="font-semibold">{{ __('Save mapping profile') }}</h2>
        <p class="text-xs text-slate-500">{{ __('Optional: JSON map of spreadsheet column label → field key for non-standard exports.') }}</p>
        <label class="block"><span class="label">{{ __('Profile name') }}</span><input type="text" name="name" required class="input" maxlength="120"></label>
        <button class="btn-secondary">{{ __('Save empty profile') }}</button>
    </form>

    <h2 class="mt-8 font-semibold">{{ __('Recent batches') }}</h2>
    <ul class="mt-2 divide-y rounded-lg border bg-white">
        @forelse ($batches as $batch)
            <li class="flex justify-between px-4 py-2 text-sm">
                <span>{{ $batch->original_name }} · {{ $batch->status }} · {{ $batch->row_count }} {{ __('rows') }}</span>
                <a href="{{ route('imports.entity.show', [$entity, $batch]) }}" class="text-brand-700 underline">{{ __('Open') }}</a>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-slate-500">{{ __('No imports yet.') }}</li>
        @endforelse
    </ul>
@endsection
