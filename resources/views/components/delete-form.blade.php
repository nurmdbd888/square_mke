@props([
    'action',
    'confirm' => null,
    'label' => null,
])

@php
    $confirm ??= __('Delete this record? This cannot be undone easily.');
    $label ??= __('Delete');
@endphp

<form method="POST" action="{{ $action }}" data-confirm="{{ $confirm }}" {{ $attributes->class(['inline']) }}>
    @csrf
    @method('DELETE')
    <button type="submit" class="btn-danger px-2.5 py-1 text-xs">{{ $label }}</button>
</form>
