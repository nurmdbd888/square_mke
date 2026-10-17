@php
    $exportKey = $exportKey ?? null;
    $query = request()->query();
@endphp
@if ($exportKey)
    <details class="relative" data-testid="list-export-menu">
        <summary class="btn-secondary cursor-pointer list-none">{{ __('Export') }}</summary>
        <div class="absolute right-0 z-20 mt-1 min-w-[12rem] rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
            @foreach (['csv', 'xlsx', 'pdf'] as $format)
                <form method="POST" action="{{ route('exports.store', $exportKey) }}" class="block">
                    @csrf
                    @foreach ($query as $k => $v)
                        @if (is_scalar($v))
                            <input type="hidden" name="filters[{{ $k }}]" value="{{ $v }}">
                        @endif
                    @endforeach
                    <input type="hidden" name="format" value="{{ $format }}">
                    <button type="submit" class="block w-full rounded px-2 py-1.5 text-left text-sm hover:bg-slate-100">{{ strtoupper($format) }}</button>
                </form>
            @endforeach
            @can('google.manage')
                @if (\App\Models\GoogleConnection::current())
                    <form method="POST" action="{{ route('google.sheets.store', $exportKey) }}" class="mt-1 border-t border-slate-100 pt-1">
                        @csrf
                        @foreach ($query as $k => $v)
                            @if (is_scalar($v))
                                <input type="hidden" name="filters[{{ $k }}]" value="{{ $v }}">
                            @endif
                        @endforeach
                        <input type="hidden" name="title" value="{{ setting('firm_name') }} — {{ $exportKey }}">
                        <button type="submit" class="block w-full rounded px-2 py-1.5 text-left text-sm hover:bg-slate-100">{{ __('Google Sheet') }}</button>
                    </form>
                @endif
            @endcan
        </div>
    </details>
@endif
