@php
    $businessDaysOnly = $businessDaysOnly ?? false;
@endphp
<label class="flex items-center gap-2 text-sm">
    <input type="hidden" name="business_days" value="0">
    <input type="checkbox" name="business_days" value="1" class="size-4 accent-brand-600" @checked($businessDaysOnly) data-testid="business-days-filter">
    <span>{{ __('Exclude weekly holidays') }}</span>
</label>
<p class="text-xs text-slate-500">{{ __('Uses weekly holidays and exception dates from Settings.') }}</p>
