@php
    $entity = $entity ?? null;
    $exportKey = $exportKey ?? null;
    $def = $entity ? \App\Domain\Import\EntityImportRegistry::get($entity) : null;
@endphp
@if ($entity && $def)
    @can($def['permission'])
        <a href="{{ route('imports.entity.index', $entity) }}" class="btn-secondary" data-testid="list-import">{{ __('Import') }}</a>
    @endcan
@endif
@if ($exportKey)
    @include('export.partials.actions', ['exportKey' => $exportKey])
@elseif ($entity && $def)
    @can($def['permission'])
        <a href="{{ route('imports.entity.export', $entity) }}" class="btn-secondary" data-testid="list-export-download">{{ __('Export') }}</a>
    @endcan
@endif
