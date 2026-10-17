@if (session('status'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status" data-testid="flash-status">
        {{ session('status') }}
        @if (session('print_receipt') && session('print_receipt_id'))
            <a href="{{ route('payments.print', session('print_receipt_id')) }}" class="ml-2 font-semibold underline" target="_blank" rel="noopener">{{ __('Print receipt') }}</a>
        @endif
    </div>
@endif
@if (session('warning'))
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert" data-testid="flash-warning">{{ session('warning') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert" data-testid="flash-errors">
        <ul class="list-inside list-disc space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
