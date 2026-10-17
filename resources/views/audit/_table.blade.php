@php
    $summary = function ($log) {
        $parts = [];
        foreach ($log->changedKeys() as $key) {
            $old = $log->old_values[$key] ?? null;
            $new = $log->new_values[$key] ?? null;
            $show = fn ($v) => is_array($v) ? implode(', ', $v) : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);
            $parts[] = $log->old_values !== null && $log->new_values !== null
                ? $key.': '.$show($old).' → '.$show($new)
                : $key.': '.$show($new ?? $old);
        }

        return \Illuminate\Support\Str::limit(implode('; ', $parts), 140);
    };
@endphp
<div class="table-wrap">
    <table class="data-table" data-testid="audit-table">
        <thead>
            <tr>
                <th scope="col">{{ __('When') }}</th>
                <th scope="col">{{ __('User') }}</th>
                <th scope="col">{{ __('Action') }}</th>
                <th scope="col">{{ __('Record') }}</th>
                <th scope="col" class="hidden lg:table-cell">{{ __('Changes') }}</th>
                <th scope="col" class="hidden md:table-cell">{{ __('IP address') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($logs as $log)
                <tr data-event="{{ $log->event }}">
                    <td class="whitespace-nowrap"><a href="{{ route('audit.show', $log) }}" class="text-brand-700 underline">{{ local_digits($log->created_at->format('d-m-Y H:i:s')) }}</a></td>
                    <td>{{ $log->user_name ?? __('audit.sources.'.$log->source) }}</td>
                    <td>{{ \App\Support\Audit\AuditEvents::label($log->event) }}</td>
                    <td class="whitespace-nowrap">{{ \App\Support\Audit\AuditEvents::typeLabel($log->auditable_type) }}@if ($log->auditable_id) #{{ local_digits($log->auditable_id) }}@endif</td>
                    <td class="hidden max-w-md font-mono text-xs break-words lg:table-cell">{{ $summary($log) }}</td>
                    <td class="hidden font-mono text-xs md:table-cell">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-slate-500">{{ __('No entries match.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $logs->links() }}
