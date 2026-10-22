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
  The native type=date control is overlaid on the calendar icon (real size,
  opacity 0) so a click opens the browser picker. Calling showPicker() on a
  zero-size input fails silently in Chromium — do not shrink this control.
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
        {{ $attributes->class(['input', 'pr-10']) }}
        :class="invalid ? 'border-red-500 ring-2 ring-red-200' : ''"
        :aria-invalid="invalid ? 'true' : 'false'"
    >
    <div class="absolute inset-y-0 right-0 w-10" data-testid="date-picker-hit">
        <input
            type="date"
            x-ref="picker"
            class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
            tabindex="-1"
            :value="iso"
            @change="fromPicker($event)"
            @click="preparePicker()"
            aria-label="{{ $pickLabel }}"
        >
        <span class="pointer-events-none absolute inset-0 z-0 flex items-center justify-center text-slate-500" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18h-10.5A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c0-.69.56-1.25 1.25-1.25h10.5c.69 0 1.25.56 1.25 1.25v8.5c0 .69-.56 1.25-1.25 1.25h-10.5c-.69 0-1.25-.56-1.25-1.25v-8.5Z" clip-rule="evenodd" />
            </svg>
        </span>
    </div>
</div>
