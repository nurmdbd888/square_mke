<input
    type="checkbox"
    class="rounded border-slate-300"
    aria-label="{{ __('Select all on this page') }}"
    @change="$el.closest('form')?.querySelectorAll('input[name=\'ids[]\']').forEach(cb => { cb.checked = $el.checked; })"
>
