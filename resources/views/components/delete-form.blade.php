@props([
    'action',
    'confirm' => null,
    'label' => null,
    'id' => null,
])

@php
    $confirm ??= __('Delete this record? This cannot be undone easily.');
    $label ??= __('Delete');
    // Stable id from action URL so the button and remote form stay paired across HTMX swaps.
    $formId = $id ?? 'delete-'.substr(hash('xxh128', $action), 0, 16);
@endphp

{{--
  Button only inside the parent (often a bulk <form>). The real DELETE <form> is pushed to
  @stack('remote-forms') so it is never nested — nested </form> would close the bulk form early
  and leave _method=DELETE on the bulk POST (405 on /sales/bulk).
--}}
<button
    type="submit"
    form="{{ $formId }}"
    {{ $attributes->class(['btn-danger px-2.5 py-1 text-xs']) }}
>{{ $label }}</button>

@push('remote-forms')
    <form id="{{ $formId }}" method="POST" action="{{ $action }}" class="hidden" data-confirm="{{ $confirm }}">
        @csrf
        @method('DELETE')
    </form>
@endpush
