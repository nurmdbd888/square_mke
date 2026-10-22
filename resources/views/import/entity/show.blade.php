@extends('layouts.app')

@section('title', $batch->original_name)

@section('content')
    <x-back-link :href="route('imports.entity.index', $entity)">{{ __('Back to import') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold">{{ $batch->original_name }}</h1>
    <p class="text-sm text-slate-600">{{ __($def['title']) }} · {{ $batch->status }} · {{ $batch->row_count }} {{ __('rows') }} · {{ $batch->error_count }} {{ __('errors') }}</p>

    @if ($batch->status === 'preview')
        <form method="POST" action="{{ route('imports.entity.confirm', [$entity, $batch]) }}" class="mt-4 card max-w-lg space-y-3">
            @csrf
            <fieldset>
                <legend class="label">{{ __('When a row matches an existing record') }}</legend>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="duplicate_mode" value="update" checked> {{ __('Update existing rows') }}</label>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="duplicate_mode" value="skip"> {{ __('Skip duplicates') }}</label>
            </fieldset>
            <button class="btn-primary">{{ __('Confirm import') }}</button>
        </form>
    @elseif ($batch->status === 'posted')
        <form method="POST" action="{{ route('imports.entity.undo', [$entity, $batch]) }}" class="mt-4">@csrf<button class="btn-secondary">{{ __('Undo batch') }}</button></form>
        @if ($batch->summary)
            <p class="mt-2 text-sm text-slate-600">{{ __('Created') }}: {{ $batch->summary['created'] ?? 0 }}, {{ __('Updated') }}: {{ $batch->summary['updated'] ?? 0 }}, {{ __('Skipped') }}: {{ $batch->summary['skipped'] ?? 0 }}</p>
        @endif
    @endif

    <div class="mt-4 table-wrap max-h-[32rem] overflow-auto">
        <table class="data-table text-sm">
            <thead><tr><th>#</th><th>{{ __('Status') }}</th><th>{{ __('Parsed') }}</th><th>{{ __('Errors') }}</th></tr></thead>
            <tbody>
                @foreach ($batch->rows as $row)
                    <tr @class(['bg-rose-50' => $row->hasErrors(), 'bg-amber-50' => $row->status === 'skipped'])>
                        <td>{{ $row->row_no }}</td>
                        <td>{{ $row->status }}</td>
                        <td class="font-mono text-xs">{{ json_encode($row->parsed, JSON_UNESCAPED_UNICODE) }}</td>
                        <td>{{ $row->errors }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
