@if ($paginator->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-3 text-sm" aria-label="{{ __('Pages') }}">
        <p class="text-slate-600">
            {{ __('Showing :first to :last of :total', [
                'first' => local_digits($paginator->firstItem()),
                'last' => local_digits($paginator->lastItem()),
                'total' => local_digits($paginator->total()),
            ]) }}
        </p>
        <div class="flex gap-2">
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
        </div>
    </nav>
@endif
