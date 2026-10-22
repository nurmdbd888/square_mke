@extends('layouts.app')

@section('title', $batch->original_name)

@section('content')
    @php
        $columns = $def['columns'] ?? [];
        $errorsOnly = request()->boolean('errors');
        $rows = $batch->rows;
        if ($errorsOnly) {
            $rows = $rows->filter(fn ($row) => $row->hasErrors())->values();
        }
        $shown = $rows->count();
        $totalLoaded = $batch->rows->count();
    @endphp
    <x-back-link :href="route('imports.entity.index', $entity)">{{ __('Back to import') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold">{{ $batch->original_name }}</h1>
    <p class="text-sm text-slate-600">{{ __($def['title']) }} · {{ __($batch->status) }} · {{ local_digits($batch->row_count) }} {{ __('rows') }}</p>

    @if ($batch->status === 'preview')
        <div class="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" data-testid="import-dry-run-banner">
            <p class="font-semibold">{{ __('Dry-run preview') }}</p>
            <p class="mt-1">{{ __('No master data is saved until you confirm. Review every row below first.') }}</p>
        </div>
    @endif

    @if ((int) $batch->error_count > 0)
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" data-testid="import-error-banner" role="alert">
            <p class="font-semibold">{{ __(':count row errors — confirm is blocked', ['count' => local_digits($batch->error_count)]) }}</p>
            <p class="mt-1">{{ __('Fix the file and upload again, or filter to errors only below.') }}</p>
        </div>
    @endif

    @if ($batch->status === 'preview')
        <form method="POST" action="{{ route('imports.entity.confirm', [$entity, $batch]) }}" class="mt-4 card max-w-lg space-y-3" data-testid="import-confirm-form">
            @csrf
            <fieldset>
                <legend class="label">{{ __('When a row matches an existing record') }}</legend>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="duplicate_mode" value="update" checked> {{ __('Update existing rows') }}</label>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="duplicate_mode" value="skip"> {{ __('Skip duplicates') }}</label>
            </fieldset>
            <button
                class="btn-primary"
                data-testid="import-confirm-button"
                @disabled((int) $batch->error_count > 0)
            >{{ __('Confirm import') }}</button>
            @if ((int) $batch->error_count > 0)
                <p class="text-xs text-rose-700">{{ __('Confirm stays off while any row has errors.') }}</p>
            @endif
        </form>
    @elseif ($batch->status === 'posted')
        <form method="POST" action="{{ route('imports.entity.undo', [$entity, $batch]) }}" class="mt-4">@csrf<button class="btn-secondary">{{ __('Undo batch') }}</button></form>
        @if ($batch->summary)
            <p class="mt-2 text-sm text-slate-600">{{ __('Created') }}: {{ $batch->summary['created'] ?? 0 }}, {{ __('Updated') }}: {{ $batch->summary['updated'] ?? 0 }}, {{ __('Skipped') }}: {{ $batch->summary['skipped'] ?? 0 }}</p>
        @endif
    @endif

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <h2 class="font-semibold text-slate-900">{{ __('Preview rows') }}</h2>
        <p class="text-sm text-slate-600" data-testid="import-row-caption">
            {{ __('Showing :shown of :loaded loaded (batch :total).', [
                'shown' => local_digits($shown),
                'loaded' => local_digits($totalLoaded),
                'total' => local_digits($batch->row_count),
            ]) }}
        </p>
        <div class="flex flex-wrap gap-2 sm:ml-auto">
            <a
                href="{{ route('imports.entity.show', [$entity, $batch]) }}"
                @class(['btn-secondary text-xs', 'ring-2 ring-brand-200' => ! $errorsOnly])
            >{{ __('All rows') }}</a>
            <a
                href="{{ route('imports.entity.show', [$entity, $batch, 'errors' => 1]) }}"
                @class(['btn-secondary text-xs', 'ring-2 ring-rose-200' => $errorsOnly])
                data-testid="import-errors-only"
            >{{ __('Errors only') }}</a>
        </div>
    </div>

    <div class="mt-3 table-wrap max-h-[32rem] overflow-auto">
        <table class="data-table text-sm" data-testid="import-preview-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Status') }}</th>
                    @foreach ($columns as $col)
                        <th>{{ __($col['label']) }}</th>
                    @endforeach
                    <th>{{ __('Errors') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr @class(['bg-rose-50' => $row->hasErrors(), 'bg-amber-50' => $row->status === 'skipped']) data-testid="import-row-{{ $row->row_no }}">
                        <td class="font-mono">{{ local_digits($row->row_no) }}</td>
                        <td>
                            @if ($row->hasErrors())
                                <span class="rounded bg-rose-100 px-1.5 py-0.5 text-xs font-medium text-rose-800">{{ __('Error') }}</span>
                            @elseif ($row->status === 'skipped')
                                <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-900">{{ __('Skipped') }}</span>
                            @else
                                <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-800">{{ __('OK') }}</span>
                            @endif
                        </td>
                        @foreach ($columns as $col)
                            <td class="font-mono text-xs">{{ $row->parsed[$col['key']] ?? '—' }}</td>
                        @endforeach
                        <td class="text-rose-800">{{ $row->errors }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 3 + count($columns) }}" class="py-8 text-center text-slate-500">{{ __('No rows to show.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
