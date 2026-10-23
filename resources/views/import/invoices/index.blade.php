@extends('layouts.app')

@section('title', __('Invoice import'))

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('Invoice import') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Upload Square sales or purchase invoices as a spreadsheet, image, or PDF. Scans are read in your browser; review the dry-run preview and correct any extracted details before confirming.') }}</p>

    <x-flash />

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="card">
            <h2 class="font-semibold">{{ __('Templates') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Header + Lines workbook, or flat one-row-per-line sheet.') }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (['purchase', 'sale'] as $type)
                    @foreach (['header_lines', 'flat'] as $layout)
                        <a href="{{ route('imports.invoices.template', [$type, $layout, 'format' => 'xlsx']) }}" class="btn-secondary text-sm">{{ ucfirst($type) }} {{ str_replace('_', '+', $layout) }} (Excel)</a>
                    @endforeach
                @endforeach
            </div>
        </section>

        <section class="card">
            <h2 class="font-semibold">{{ __('Upload') }}</h2>
            <form method="POST" action="{{ route('imports.invoices.store') }}" enctype="multipart/form-data" class="mt-3 space-y-3" data-invoice-ocr-form>
                @csrf
                <label class="block">
                    <span class="label">{{ __('Document type') }}</span>
                    <select name="doc_type" class="input" required>
                        <option value="purchase">{{ __('Purchase') }}</option>
                        <option value="sale">{{ __('Sale') }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="label">{{ __('Spreadsheet') }}</span>
                    <input type="file" name="file" accept=".csv,.xlsx,.xls" class="input" data-invoice-spreadsheet>
                </label>
                <label class="block">
                    <span class="label">{{ __('Invoice images or PDFs') }}</span>
                    <input type="file" name="scan_files[]" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" multiple class="input" data-invoice-scans>
                    <span class="mt-1 block text-xs text-slate-500">{{ __('Choose one PDF or multiple images (up to 100 pages per batch). Processing stays on this device.') }}</span>
                </label>
                <input type="hidden" name="ocr_pages" data-invoice-ocr-pages>
                <p class="hidden text-sm text-slate-700" aria-live="polite" data-invoice-ocr-status></p>
                <p class="hidden text-sm text-red-700" role="alert" data-invoice-ocr-error></p>
                <label class="block">
                    <span class="label">{{ __('Invoice number (lines-only files)') }}</span>
                    <input type="text" name="invoice_no" class="input" placeholder="INV-ADU/4344/1026">
                </label>
                <label class="block">
                    <span class="label">{{ __('Document date (lines-only files)') }}</span>
                    <input type="text" name="doc_date" class="input" placeholder="2026-10-15">
                </label>
                @if ($profiles->isNotEmpty())
                    <label class="block">
                        <span class="label">{{ __('Mapping profile') }}</span>
                        <select name="mapping_profile_id" class="input">
                            <option value="">{{ __('Default columns') }}</option>
                            @foreach ($profiles as $profile)
                                <option value="{{ $profile->id }}">{{ $profile->name }} ({{ $profile->entity }})</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <button class="btn-primary" data-invoice-ocr-submit>{{ __('Upload and preview') }}</button>
            </form>
        </section>
    </div>

    <section class="mt-8">
        <h2 class="font-semibold">{{ __('Recent batches') }}</h2>
        <ul class="mt-2 divide-y rounded-xl border border-slate-200 bg-white">
            @forelse ($batches as $batch)
                <li class="flex items-center justify-between px-4 py-3 text-sm">
                    <span>#{{ $batch->id }} · {{ $batch->doc_type }} · {{ $batch->original_name }} · {{ $batch->status }}</span>
                    <a href="{{ route('imports.invoices.show', $batch) }}" class="text-brand-700 underline">{{ __('Open') }}</a>
                </li>
            @empty
                <li class="px-4 py-6 text-center text-slate-500">{{ __('No imports yet.') }}</li>
            @endforelse
        </ul>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/document-import-ocr.js') }}?v={{ filemtime(public_path('js/document-import-ocr.js')) }}" defer></script>
@endpush
