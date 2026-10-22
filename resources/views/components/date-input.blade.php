@props([
    'name',
    'value' => null,
    'required' => false,
])

@php
    $raw = old($name, $value);
    if ($raw instanceof \Carbon\CarbonInterface || $raw instanceof \DateTimeInterface) {
        $iso = $raw->format('Y-m-d');
    } else {
        $iso = is_string($raw) ? trim($raw) : '';
    }
    if ($iso !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) !== 1) {
        $iso = parse_app_date($iso) ?? '';
    }
    $format = app_date_format();
    $placeholder = app_date_placeholder();
    $display = $iso !== '' ? \Illuminate\Support\Carbon::parse($iso)->format($format) : '';
    $pickLabel = __('Pick date');
@endphp

{{--
  Visible text follows Settings date format; a hidden field posts Y-m-d.

  Chromium only treats a tiny calendar glyph inside <input type="date"> as
  clickable — an opacity-0 overlay still leaves most of the chrome dead.
  The visible control is therefore a full-size button-like hit target that
  calls showPicker() on a sibling type=date input. That input keeps a
  non-zero layout box (required for showPicker) but pointer-events:none.
--}}
<div
    class="relative"
    data-app-date-input
    data-testid="date-input-{{ $name }}"
    x-data="appDateInput({{ \Illuminate\Support\Js::from([
        'iso' => $iso,
        'display' => $display,
        'format' => $format,
        'placeholder' => $placeholder,
        'required' => (bool) $required,
    ]) }})"
>
    <input type="hidden" name="{{ $name }}" x-model="iso" x-ref="isoInput" value="{{ $iso }}">
    <input
        type="text"
        x-ref="textInput"
        x-model="display"
        @blur="syncFromDisplay()"
        @change="syncFromDisplay()"
        inputmode="numeric"
        autocomplete="off"
        placeholder="{{ $placeholder }}"
        :placeholder="placeholder"
        @if ($required) required @endif
        {{ $attributes->class(['input', 'pr-12']) }}
        :class="invalid ? 'border-red-500 ring-2 ring-red-200' : ''"
        :aria-invalid="invalid ? 'true' : 'false'"
    >
    {{-- Full calendar chrome is the click target (inline width so purge cannot drop it). --}}
    <div
        role="button"
        tabindex="0"
        class="absolute inset-y-0 right-0 z-10 flex cursor-pointer items-center justify-center rounded-r-lg border-l border-slate-300 text-slate-500 hover:bg-slate-50 hover:text-slate-700 focus-visible:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500"
        style="width: 2.75rem; min-width: 2.75rem; min-height: 2.25rem"
        data-testid="date-picker-hit"
        aria-label="{{ $pickLabel }}"
        @click.prevent="openPicker()"
        @keydown.enter.prevent="openPicker()"
        @keydown.space.prevent="openPicker()"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="pointer-events-none h-5 w-5" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18h-10.5A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c0-.69.56-1.25 1.25-1.25h10.5c.69 0 1.25.56 1.25 1.25v8.5c0 .69-.56 1.25-1.25 1.25h-10.5c-.69 0-1.25-.56-1.25-1.25v-8.5Z" clip-rule="evenodd" />
        </svg>
    </div>
    {{-- Sized, non-interactive; exists only so showPicker() is allowed. Kept off the hit path. --}}
    <input
        type="date"
        x-ref="picker"
        tabindex="-1"
        aria-hidden="true"
        class="pointer-events-none absolute opacity-0"
        style="width: 2.75rem; height: 2.25rem; min-width: 2.75rem; min-height: 2.25rem; right: 0; bottom: 0"
        data-testid="date-picker-native"
        :value="iso"
        @change="fromPicker($event)"
    >
</div>
