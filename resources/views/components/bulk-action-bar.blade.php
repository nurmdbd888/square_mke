@props(['actions' => []])
<div class="mt-3 flex flex-wrap items-end gap-3">
    <label class="block">
        <span class="label">{{ __('Action') }}</span>
        <select name="action" class="input" required>
            @foreach ($actions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <button type="submit" class="btn-secondary" data-confirm="{{ __('Apply to selected rows?') }}">{{ __('Apply to selected') }}</button>
    <x-bulk-limit-note />
</div>
