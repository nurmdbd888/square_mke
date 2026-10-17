@props(['status'])
<span {{ $attributes->class([
    'inline-flex min-w-14 justify-center rounded-full px-2 py-0.5 text-xs font-semibold uppercase',
    'bg-emerald-100 text-emerald-800' => $status === 'ok',
    'bg-amber-100 text-amber-800' => $status === 'warn',
    'bg-red-100 text-red-800' => $status === 'fail',
    'bg-slate-100 text-slate-600' => $status === 'info',
]) }}>{{ match ($status) { 'ok' => __('OK'), 'warn' => __('Check'), 'fail' => __('Fix'), default => __('Info') } }}</span>
