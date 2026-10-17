@props([
    'view' => null,
    'edit' => null,
    'delete' => null,
    'viewLabel' => __('View'),
    'editLabel' => __('Edit'),
])

<div class="flex flex-wrap justify-end gap-1.5">
    @if ($view)
        <a href="{{ $view }}" class="btn-secondary px-2.5 py-1 text-xs">{{ $viewLabel }}</a>
    @endif
    @if ($edit)
        <a href="{{ $edit }}" class="btn-secondary px-2.5 py-1 text-xs">{{ $editLabel }}</a>
    @endif
    @if ($delete)
        {!! $delete !!}
    @endif
    {{ $slot }}
</div>
