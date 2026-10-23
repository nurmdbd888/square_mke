@props([
    'mode' => 'receipt', // receipt|invoice
])

<div
    class="card space-y-3"
    data-testid="ocr-scan-panel"
    data-ocr-mode="{{ $mode }}"
    x-data="ocrScanPanel({{ \Illuminate\Support\Js::from(['mode' => $mode]) }})"
>
    <div>
        <h2 class="font-semibold text-slate-900">{{ $mode === 'receipt' ? __('Scan slip (OCR)') : __('Scan invoice (OCR)') }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ __('Runs in your browser with Tesseract — no AI API. Review the fields, then apply before saving.') }}</p>
    </div>

    <label class="block">
        <span class="label">{{ __('Photo or scan') }}</span>
        <input
            type="file"
            accept="image/*"
            capture="environment"
            class="input min-h-12 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-800"
            data-testid="ocr-scan-input"
            @change="onFile($event)"
            :disabled="busy"
        >
    </label>

    <p x-show="busy || status" x-cloak class="text-sm text-slate-600" data-testid="ocr-scan-status">
        <span x-text="status"></span>
        <span x-show="busy" class="ml-2 font-mono" x-text="progress + '%'"></span>
    </p>
    <p x-show="error" x-cloak class="text-sm text-rose-700" data-testid="ocr-scan-error" x-text="error"></p>

    <div x-show="confirmOpen" x-cloak class="space-y-3 rounded-lg border border-amber-200 bg-amber-50 p-3" data-testid="ocr-confirm-box">
        <p class="text-sm font-semibold text-amber-950">{{ __('Confirm before apply') }}</p>
        <div class="grid gap-2 sm:grid-cols-2">
            @if ($mode === 'receipt')
                <label class="block text-sm">
                    <span class="label">{{ __('Amount') }}</span>
                    <input type="text" class="input font-mono" x-model="preview.amount" data-testid="ocr-field-amount">
                </label>
                <label class="block text-sm">
                    <span class="label">{{ __('Date') }}</span>
                    <input type="text" class="input font-mono" x-model="preview.date" placeholder="YYYY-MM-DD" data-testid="ocr-field-date">
                </label>
                <label class="block text-sm sm:col-span-2">
                    <span class="label">{{ __('Reference / invoice') }}</span>
                    <input type="text" class="input font-mono" x-model="preview.reference" data-testid="ocr-field-reference">
                </label>
            @else
                <label class="block text-sm">
                    <span class="label">{{ __('Invoice number') }}</span>
                    <input type="text" class="input font-mono" x-model="preview.invoice_number" data-testid="ocr-field-invoice">
                </label>
                <label class="block text-sm">
                    <span class="label">{{ __('Date') }}</span>
                    <input type="text" class="input font-mono" x-model="preview.date" placeholder="YYYY-MM-DD" data-testid="ocr-field-date">
                </label>
                <label class="block text-sm sm:col-span-2">
                    <span class="label">{{ __('Party (hint)') }}</span>
                    <input type="text" class="input" x-model="preview.party" data-testid="ocr-field-party">
                </label>
            @endif
        </div>
        <details class="text-xs text-slate-600">
            <summary class="cursor-pointer">{{ __('Raw OCR text') }}</summary>
            <pre class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap rounded bg-white p-2 font-mono" x-text="rawText" data-testid="ocr-raw-text"></pre>
        </details>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn-primary" data-testid="ocr-apply" @click="applyToForm()">{{ __('Apply to form') }}</button>
            <button type="button" class="btn-secondary" data-testid="ocr-dismiss" @click="dismiss()">{{ __('Discard') }}</button>
        </div>
    </div>
</div>

@once
    @push('scripts')
        {{-- Loaded after Alpine; recognize() only runs on user click. --}}
        <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js" defer></script>
    @endpush
@endonce
