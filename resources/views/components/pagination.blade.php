@if ($paginator->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-3 text-sm" aria-label="{{ __('Pages') }}">
        <p class="text-slate-600">
            {{ __('Showing :first to :last of :total', [
                'first' => local_digits($paginator->firstItem()),
                'last' => local_digits($paginator->lastItem()),
                'total' => local_digits($paginator->total()),
            ]) }}
        </p>
        <div class="flex flex-wrap items-center justify-end gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn-secondary pointer-events-none opacity-50">{{ __('Previous') }}</span>
            @else
                <a class="btn-secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('Previous') }}</a>
            @endif
            @if ($paginator->hasMorePages())
                <a class="btn-secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Next') }}</a>
            @else
                <span class="btn-secondary pointer-events-none opacity-50">{{ __('Next') }}</span>
            @endif
            <form method="GET" action="{{ url()->current() }}" class="flex items-center gap-1">
                @foreach (request()->query() as $key => $value)
                    @if ($key !== 'page' && ! is_array($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @elseif ($key !== 'page' && is_array($value))
                        @foreach ($value as $nestedKey => $nestedValue)
                            @if (! is_array($nestedValue))
                                <input type="hidden" name="{{ $key }}[{{ $nestedKey }}]" value="{{ $nestedValue }}">
                            @endif
                        @endforeach
                    @endif
                @endforeach
                <label class="sr-only" for="jump-page-{{ md5($paginator->path()) }}">{{ __('Jump to page') }}</label>
                <input id="jump-page-{{ md5($paginator->path()) }}" type="number" name="page" min="1" max="{{ $paginator->lastPage() }}" value="{{ $paginator->currentPage() }}" class="input w-20 py-2" aria-label="{{ __('Jump to page') }}">
                <button class="btn-secondary px-3 py-2">{{ __('Go') }}</button>
            </form>
        </div>
    </nav>
@endif
