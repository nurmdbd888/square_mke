@php
    $def = \App\Domain\Import\EntityImportRegistry::get($entity);
@endphp
@can($def['permission'])
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('imports.entity.export', $entity) }}" class="btn-secondary">{{ __('Export') }}</a>
        <a href="{{ route('imports.entity.index', $entity) }}" class="btn-secondary">{{ __('Import') }}</a>
    </div>
@endcan
