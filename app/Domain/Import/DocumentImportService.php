<?php

namespace App\Domain\Import;

use App\Domain\Documents\DocumentWriter;
use App\Domain\Money\Exact;
use App\Jobs\ProcessDocumentImportJob;
use App\Models\Document;
use App\Models\DocumentImportBatch;
use App\Models\DocumentImportInvoice;
use App\Models\DocumentImportLine;
use App\Models\DocumentLine;
use App\Models\ImportMappingProfile;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\SalesRep;
use App\Models\SalesRoute;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentImportService
{
    public function __construct(
        private readonly InvoiceSpreadsheetReader $reader,
        private readonly InvoiceLineValidator $validator,
        private readonly ProductMatcher $matcher,
        private readonly DocumentWriter $documents,
        private readonly SquareInvoiceOcrParser $ocrParser,
    ) {}

    public function templateDownload(string $docType, string $layout, string $format = 'xlsx'): StreamedResponse
    {
        $this->assertDocType($docType);
        $spreadsheet = new Spreadsheet;

        if ($layout === 'header_lines') {
            $headerSheet = $spreadsheet->getActiveSheet()->setTitle('Header');
            foreach (InvoiceImportTemplates::headerColumns($docType) as $i => $col) {
                $headerSheet->setCellValue([$i + 1, 1], $col);
            }
            $lineSheet = $spreadsheet->createSheet()->setTitle('Lines');
            foreach (InvoiceImportTemplates::lineColumns($docType) as $i => $col) {
                $lineSheet->setCellValue([$i + 1, 1], $col);
            }
        } else {
            $sheet = $spreadsheet->getActiveSheet();
            foreach (InvoiceImportTemplates::flatColumns($docType) as $i => $col) {
                $sheet->setCellValue([$i + 1, 1], $col);
            }
        }

        $filename = "{$docType}-invoice-{$layout}-template.{$format}";

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'xlsx' ? new XlsxWriter($spreadsheet) : new CsvWriter($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv',
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{batch: DocumentImportBatch, queued: bool}
     */
    public function preview(
        string $docType,
        UploadedFile $file,
        ?User $by = null,
        ?int $profileId = null,
        array $meta = [],
    ): array {
        $this->assertDocType($docType);
        $stored = $file->store('document-imports');
        $path = Storage::path($stored);
        $profile = $profileId ? ImportMappingProfile::query()->find($profileId)?->mapping : null;

        $batch = DocumentImportBatch::query()->create([
            'doc_type' => $docType,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $stored,
            'status' => DocumentImportBatch::STATUS_PREVIEW,
            'mapping_profile_id' => $profileId,
            'meta' => $meta,
            'created_by' => $by?->id,
        ]);

        $parsed = $this->reader->read($path, $docType, $profile, $this->defaultsFromMeta($docType, $meta));
        $lineCount = array_sum(array_map(fn ($inv) => count($inv['lines']), $parsed['invoices']));
        $inline = (int) config('sdm.document_import_inline_limit', 500);

        $batch->update(['layout' => $parsed['layout']]);

        if ($lineCount > $inline) {
            ProcessDocumentImportJob::dispatch($batch->id);
            $batch->update(['status' => DocumentImportBatch::STATUS_PROCESSING]);

            return ['batch' => $batch, 'queued' => true];
        }

        $this->persistParsed($batch, $parsed['invoices']);

        return ['batch' => $batch->fresh(['invoices.lines']), 'queued' => false];
    }

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<array{text: string}>  $pages
     * @param  array<string, mixed>  $meta
     * @return array{batch: DocumentImportBatch, queued: bool}
     */
    public function previewOcr(
        string $docType,
        array $files,
        array $pages,
        ?User $by = null,
        array $meta = [],
    ): array {
        $this->assertDocType($docType);
        $parsed = $this->ocrParser->parse($docType, $pages);
        $lineCount = array_sum(array_map(fn ($invoice) => count($invoice['lines']), $parsed['invoices']));
        $inline = (int) config('sdm.document_import_inline_limit', 500);
        $storedPaths = [];
        $payloadPath = null;
        $batch = null;

        try {
            foreach ($files as $file) {
                $stored = $file->store('document-imports');
                if (! is_string($stored) || $stored === '') {
                    throw new \RuntimeException('Unable to store an invoice source file.');
                }
                $storedPaths[] = $stored;
            }

            $names = array_map(fn (UploadedFile $file) => $file->getClientOriginalName(), $files);
            $originalName = $names[0] ?? 'invoice-scan';
            if (count($names) > 1) {
                $originalName .= ' + '.(count($names) - 1).' '.__('more files');
            }

            if ($lineCount > $inline) {
                $payloadPath = 'document-imports/parsed/ocr-'.Str::uuid().'.json';
                if (! Storage::put($payloadPath, json_encode($parsed['invoices'], JSON_THROW_ON_ERROR))) {
                    throw new \RuntimeException('Unable to store parsed invoice data.');
                }
            }

            $batchMeta = array_merge($meta, [
                'source_files' => array_map(
                    fn (int $index) => ['name' => $names[$index], 'path' => $storedPaths[$index]],
                    array_keys($storedPaths),
                ),
            ]);
            if ($payloadPath !== null) {
                $batchMeta['ocr_payload_path'] = $payloadPath;
            }

            $batch = DocumentImportBatch::query()->create([
                'doc_type' => $docType,
                'layout' => $parsed['layout'],
                'original_name' => mb_strimwidth($originalName, 0, 255),
                'stored_path' => $storedPaths[0] ?? null,
                'status' => $lineCount > $inline
                    ? DocumentImportBatch::STATUS_PROCESSING
                    : DocumentImportBatch::STATUS_PREVIEW,
                'meta' => $batchMeta,
                'created_by' => $by?->id,
            ]);

            if ($lineCount > $inline) {
                ProcessDocumentImportJob::dispatch($batch->id);

                return ['batch' => $batch, 'queued' => true];
            }

            $this->persistParsed($batch, $parsed['invoices']);

            return ['batch' => $batch->fresh(['invoices.lines']), 'queued' => false];
        } catch (\Throwable $exception) {
            if ($batch instanceof DocumentImportBatch) {
                $batch->update(['status' => DocumentImportBatch::STATUS_FAILED]);
                if ($payloadPath !== null) {
                    Storage::delete($payloadPath);
                }
            } else {
                Storage::delete($storedPaths);
                if ($payloadPath !== null) {
                    Storage::delete($payloadPath);
                }
            }
            throw $exception;
        }
    }

    /**
     * @param  list<array{invoice_no: string, header: array<string, mixed>, lines: list<array<string, mixed>>}>  $invoices
     */
    public function persistParsed(DocumentImportBatch $batch, array $invoices): void
    {
        $errors = 0;
        $lines = 0;
        $headerWarnings = [];

        DB::transaction(function () use ($batch, $invoices, &$errors, &$lines, &$headerWarnings) {
            $sort = 0;
            foreach ($invoices as $payload) {
                $sort++;
                $header = $this->normalizeHeader($batch->doc_type, $payload['header'], $payload['invoice_no']);
                $invoiceErrors = [];
                $invoiceWarnings = $this->partyWarnings($batch->doc_type, $header);
                $headerWarnings = array_merge($headerWarnings, $invoiceWarnings);

                if ($this->findDuplicateDocument($batch->doc_type, $header) !== null) {
                    $invoiceErrors[] = __('A document with this number, party and total already exists.');
                }

                $invoice = DocumentImportInvoice::query()->create([
                    'document_import_batch_id' => $batch->id,
                    'invoice_no' => $payload['invoice_no'],
                    'header' => $header,
                    'warnings' => $invoiceWarnings,
                    'errors' => $invoiceErrors,
                    'status' => $invoiceErrors === [] ? DocumentImportInvoice::STATUS_OK : DocumentImportInvoice::STATUS_DUPLICATE,
                    'sort_order' => $sort,
                ]);

                if ($invoiceErrors !== []) {
                    $errors++;
                }

                $lineNo = 0;
                foreach ($payload['lines'] as $fields) {
                    $lineNo++;
                    $lines++;
                    $product = $this->matcher->match(
                        $fields['product_code'] ?? null,
                        (string) ($fields['product_label'] ?? ''),
                        $fields['pack_size'] ?? null,
                    );
                    $unitPrice = $fields['unit_price'] ?? null;
                    $lineValue = $fields['line_value'] ?? null;
                    $lineWarnings = [];
                    if (
                        $batch->isPurchase()
                        && ($fields['use_product_buy_price'] ?? false)
                        && $unitPrice === null
                        && $product !== null
                    ) {
                        $unitPrice = $product->buy_price ?? $product->buy_price_exact;
                        if ($unitPrice !== null) {
                            $lineValue = Exact::money(
                                Exact::of($fields['qty'] ?? 0)
                                    ->multipliedBy(Exact::of($unitPrice))
                                    ->minus(Exact::of($fields['line_discount'] ?? 0)),
                            );
                            $lineWarnings[] = __('Provisional cost uses this product’s current buy price; verify it against the purchase invoice.');
                        }
                    }
                    $validation = $this->validator->validate($batch->doc_type, array_merge($fields, [
                        'unit_price' => $unitPrice,
                        'line_value' => $lineValue,
                        'line_discount' => $fields['line_discount'] ?? '0',
                    ]));
                    $lineErrors = $validation['errors'];
                    if ($batch->isPurchase() && ($fields['use_product_buy_price'] ?? false) && $unitPrice === null) {
                        $lineErrors[] = __('No current buy price is available. Enter the purchase cost before confirming.');
                    }
                    if ($product === null && ($fields['line_type'] ?? '') !== DocumentImportLine::TYPE_BONUS) {
                        $lineErrors[] = __('Product not matched.');
                    }

                    if ($lineErrors !== []) {
                        $errors++;
                        $invoice->update(['status' => DocumentImportInvoice::STATUS_ERROR]);
                    }

                    DocumentImportLine::query()->create([
                        'document_import_invoice_id' => $invoice->id,
                        'line_no' => $lineNo,
                        'line_type' => $fields['line_type'] ?? DocumentImportLine::TYPE_INVOICE,
                        'section' => $fields['section'] ?? null,
                        'product_id' => $product?->id,
                        'product_code' => $fields['product_code'] ?? null,
                        'product_label' => (string) ($fields['product_label'] ?? ''),
                        'pack_size' => $fields['pack_size'] ?? null,
                        'batch_no' => $fields['batch_no'] ?? null,
                        'mrp' => $fields['mrp'] ?? null,
                        'qty' => $fields['qty'] ?? 0,
                        'bonus_qty' => $fields['bonus_qty'] ?? 0,
                        'unit_price' => $unitPrice,
                        'line_discount' => $fields['line_discount'] ?? 0,
                        'line_value' => $lineValue,
                        'sd_pct' => $fields['sd_pct'] ?? 0,
                        'sd_amount' => $fields['sd_amount'] ?? 0,
                        'vat_pct' => $fields['vat_pct'] ?? 0,
                        'vat_amount' => $fields['vat_amount'] ?? 0,
                        'total_incl_tax' => $fields['total_incl_tax'] ?? null,
                        'provisional_cost' => $batch->isPurchase()
                            && ($fields['use_product_buy_price'] ?? false)
                            && $unitPrice !== null,
                        'errors' => $lineErrors,
                        'warnings' => array_values(array_unique(array_merge($validation['warnings'], $lineWarnings))),
                    ]);
                }
            }

            $batch->update([
                'invoice_count' => count($invoices),
                'line_count' => $lines,
                'error_count' => $errors,
                'header_warnings' => array_values(array_unique($headerWarnings)),
                'status' => DocumentImportBatch::STATUS_PREVIEW,
            ]);
        });
    }

    public function updateLine(DocumentImportLine $line, array $input, ?User $by = null): DocumentImportLine
    {
        if ($line->invoice->batch->status !== DocumentImportBatch::STATUS_PREVIEW) {
            throw ValidationException::withMessages(['batch' => __('This import batch can no longer be edited.')]);
        }

        $productId = $input['product_id'] ?? $line->product_id;
        $product = $productId ? Product::query()->find($productId) : null;
        if ($product !== null && $productId != $line->product_id) {
            $this->matcher->rememberAlias($product, $line->product_label, $by);
        }
        $provisionalCost = $line->provisional_cost;
        if (
            $provisionalCost
            && array_key_exists('unit_price', $input)
            && ! Exact::of($input['unit_price'] ?? 0)->isEqualTo(Exact::of($line->unit_price ?? 0))
        ) {
            $provisionalCost = false;
        }

        $line->fill([
            'product_id' => $product?->id,
            'qty' => $input['qty'] ?? $line->qty,
            'bonus_qty' => $input['bonus_qty'] ?? $line->bonus_qty,
            'unit_price' => $input['unit_price'] ?? $line->unit_price,
            'line_discount' => $input['line_discount'] ?? $line->line_discount,
            'line_value' => $input['line_value'] ?? $line->line_value,
            'batch_no' => array_key_exists('batch_no', $input) ? $input['batch_no'] : $line->batch_no,
            'provisional_cost' => $provisionalCost,
        ]);

        $validation = $this->validator->validate($line->invoice->batch->doc_type, [
            'qty' => $line->qty,
            'unit_price' => $line->unit_price,
            'line_discount' => $line->line_discount,
            'line_value' => $line->line_value,
            'line_type' => $line->line_type,
            'sd_pct' => $line->sd_pct,
            'sd_amount' => $line->sd_amount,
            'vat_pct' => $line->vat_pct,
            'vat_amount' => $line->vat_amount,
        ]);
        $errors = $validation['errors'];
        if ($product === null && $line->line_type !== DocumentImportLine::TYPE_BONUS) {
            $errors[] = __('Product not matched.');
        }
        $line->errors = $errors;
        $line->warnings = $validation['warnings'];
        $line->save();

        $this->refreshBatchErrorCounts($line->invoice->batch);

        return $line->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateInvoice(DocumentImportInvoice $invoice, array $input): DocumentImportInvoice
    {
        $batch = $invoice->batch;
        if ($batch->status !== DocumentImportBatch::STATUS_PREVIEW) {
            throw ValidationException::withMessages(['batch' => __('This import batch can no longer be edited.')]);
        }

        $header = $invoice->header;
        foreach ([
            'invoice_no', 'doc_date', 'invoice_date', 'supplier_name', 'buyer_name',
            'retailer_code', 'retailer_name', 'header_discount', 'slab_adjustment', 'combo_bonus_policy',
            'combo_bonus_discount', 'tds_amount', 'rounding_adjustment', 'net_total',
        ] as $key) {
            if (array_key_exists($key, $input)) {
                $header[$key] = $input[$key];
            }
        }
        $header['invoice_no'] = trim((string) ($header['invoice_no'] ?? ''));
        if ($header['invoice_no'] === '') {
            throw ValidationException::withMessages(['invoice_no' => __('Invoice number is required.')]);
        }

        $dateKey = $batch->isPurchase() ? 'doc_date' : 'invoice_date';
        $parsedDate = InvoiceText::parseDate((string) ($header[$dateKey] ?? ''));
        if ($parsedDate === null) {
            throw ValidationException::withMessages([$dateKey => __('Enter a valid invoice date.')]);
        }
        $header[$dateKey] = $parsedDate;
        if (! $batch->isPurchase()) {
            $header['doc_date'] = $parsedDate;
        }
        foreach ([
            'header_discount', 'slab_adjustment', 'combo_bonus_policy', 'combo_bonus_discount',
            'tds_amount', 'rounding_adjustment', 'net_total',
        ] as $key) {
            if (isset($header[$key])) {
                $header[$key] = Exact::money(Exact::of($header[$key]));
            }
        }

        $duplicate = $this->findDuplicateDocument($batch->doc_type, $header);
        $errors = $duplicate === null ? [] : [__('A document with this number, party and total already exists.')];
        $warnings = $this->partyWarnings($batch->doc_type, $header);
        $invoice->update([
            'invoice_no' => $header['invoice_no'],
            'header' => $header,
            'errors' => $errors,
            'warnings' => $warnings,
            'status' => $errors === [] ? DocumentImportInvoice::STATUS_OK : DocumentImportInvoice::STATUS_DUPLICATE,
        ]);
        $this->refreshBatchErrorCounts($batch);
        $batch->update([
            'header_warnings' => array_values(array_unique(
                $batch->invoices()->get()->flatMap(fn ($item) => $item->warnings ?? [])->all(),
            )),
        ]);

        return $invoice->fresh();
    }

    public function confirm(DocumentImportBatch $batch, ?User $by = null): DocumentImportBatch
    {
        if ($batch->status !== DocumentImportBatch::STATUS_PREVIEW) {
            throw ValidationException::withMessages(['status' => __('This batch cannot be posted.')]);
        }

        if ($batch->error_count > 0) {
            throw ValidationException::withMessages(['errors' => __('Fix all row errors before posting.')]);
        }

        $postedIds = [];

        DB::transaction(function () use ($batch, $by, &$postedIds) {
            foreach ($batch->invoices()->with('lines')->get() as $invoice) {
                if ($invoice->status === DocumentImportInvoice::STATUS_DUPLICATE) {
                    continue;
                }
                $header = $invoice->header;
                $doc = $this->postInvoice($batch, $invoice, $by);
                $postedIds[] = $doc->id;
                $invoice->update(['document_id' => $doc->id, 'status' => DocumentImportInvoice::STATUS_POSTED]);
            }

            $batch->update([
                'status' => DocumentImportBatch::STATUS_POSTED,
                'posted_document_ids' => $postedIds,
                'posted_at' => now(),
            ]);
        });

        return $batch->fresh(['invoices.lines']);
    }

    public function undo(DocumentImportBatch $batch, ?User $by = null): DocumentImportBatch
    {
        if ($batch->status !== DocumentImportBatch::STATUS_POSTED) {
            throw ValidationException::withMessages(['status' => __('Only a posted batch can be undone.')]);
        }

        DB::transaction(function () use ($batch, $by) {
            foreach ($batch->posted_document_ids ?? [] as $docId) {
                $doc = Document::query()->find($docId);
                if ($doc !== null) {
                    $this->documents->void($doc, $by);
                }
            }
            $batch->update([
                'status' => DocumentImportBatch::STATUS_UNDONE,
                'undone_at' => now(),
            ]);
        });

        return $batch->fresh();
    }

    private function postInvoice(DocumentImportBatch $batch, DocumentImportInvoice $invoice, ?User $by): Document
    {
        $header = $invoice->header;
        $lines = [];
        foreach ($invoice->lines as $line) {
            if ($line->product_id === null) {
                continue;
            }
            $kind = $line->isComboBonus() ? DocumentLine::KIND_COMBO_BONUS : DocumentLine::KIND_NORMAL;
            $lines[] = [
                'product_id' => $line->product_id,
                'batch_no' => $line->batch_no,
                'qty' => $line->qty,
                'bonus_qty' => $line->bonus_qty,
                'unit_price' => $line->unit_price ?? 0,
                'line_discount' => $line->line_discount,
                'line_value' => $line->line_value ?? 0,
                'sd_pct' => $line->sd_pct,
                'sd_amount' => $line->sd_amount,
                'vat_pct' => $line->vat_pct,
                'vat_amount' => $line->vat_amount,
                'kind' => $kind,
            ];
        }

        if ($batch->isPurchase()) {
            $supplier = $this->resolveSupplier($header);

            return $this->documents->save(Document::TYPE_PURCHASE, [
                'number' => $invoice->invoice_no,
                'supplier_id' => $supplier->id,
                'doc_date' => $header['doc_date'] ?? now()->toDateString(),
                'header_discount' => $header['header_discount'] ?? 0,
                'slab_adjustment' => $header['slab_adjustment'] ?? 0,
                'combo_bonus_discount' => $header['combo_bonus_discount'] ?? 0,
                'tds_amount' => $header['tds_amount'] ?? 0,
                'rounding_adjustment' => $header['rounding_adjustment'] ?? 0,
                'action' => 'post',
            ], $lines, $by);
        }

        $parties = $this->resolveSaleParties($header);

        return $this->documents->save(Document::TYPE_SALE, [
            'number' => $invoice->invoice_no,
            'retailer_id' => $parties['retailer']->id,
            'doc_date' => $header['invoice_date'] ?? $header['doc_date'] ?? now()->toDateString(),
            'delivery_date' => $header['delivery_date'] ?? null,
            'sales_rep_id' => $parties['sales_rep']?->id,
            'route_id' => $parties['route']?->id,
            'vehicle_id' => $parties['vehicle']?->id,
            'slab_adjustment' => $header['slab_adjustment'] ?? 0,
            'combo_bonus_discount' => $header['combo_bonus_policy'] ?? 0,
            'rounding_adjustment' => $header['rounding_adjustment'] ?? 0,
            'action' => 'post',
        ], $lines, $by);
    }

    /**
     * @param  array<string, mixed>  $header
     * @return list<string>
     */
    private function partyWarnings(string $docType, array $header): array
    {
        $warnings = [];
        $firm = mb_strtolower((string) config('sdm.firm_name'));

        if ($docType === DocumentImportBatch::TYPE_PURCHASE) {
            $buyer = mb_strtolower((string) ($header['buyer_name'] ?? ''));
            if ($buyer !== '' && ! str_contains($buyer, 'khaza') && ! str_contains($buyer, $firm)) {
                $warnings[] = __('Buyer name does not look like your firm — confirm this is a purchase.');
            }
        } else {
            $dist = mb_strtolower((string) ($header['distributor_name'] ?? ''));
            if ($dist !== '' && ! str_contains($dist, 'khaza') && ! str_contains($dist, $firm)) {
                $warnings[] = __('Distributor name does not match your firm — confirm this is a sale.');
            }
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function defaultsFromMeta(string $docType, array $meta): array
    {
        $out = [];
        if (! empty($meta['invoice_no'])) {
            $out['invoice_no'] = (string) $meta['invoice_no'];
        }
        $dateKey = $docType === DocumentImportBatch::TYPE_PURCHASE ? 'doc_date' : 'invoice_date';
        if (! empty($meta['doc_date'])) {
            $out[$dateKey] = InvoiceText::parseDate((string) $meta['doc_date']) ?? $meta['doc_date'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array<string, mixed>
     */
    private function normalizeHeader(string $docType, array $header, string $invoiceNo): array
    {
        $header['invoice_no'] = $invoiceNo;
        if ($docType === DocumentImportBatch::TYPE_PURCHASE) {
            $header['doc_date'] = InvoiceText::parseDate($header['doc_date'] ?? null) ?? ($header['doc_date'] ?? null);
            foreach (['header_discount', 'slab_adjustment', 'combo_bonus_discount', 'tds_amount', 'rounding_adjustment', 'net_total', 'source_total'] as $k) {
                if (isset($header[$k]) && InvoiceText::parseNumber((string) $header[$k]) !== null) {
                    $header[$k] = Exact::money(Exact::of(InvoiceText::parseNumber((string) $header[$k])));
                }
            }
        } else {
            $header['invoice_date'] = InvoiceText::parseDate($header['invoice_date'] ?? null) ?? ($header['invoice_date'] ?? null);
            $header['delivery_date'] = InvoiceText::parseDate($header['delivery_date'] ?? null);
            foreach (['slab_adjustment', 'combo_bonus_policy', 'rounding_adjustment', 'net_total', 'subtotal', 'rep_collection'] as $k) {
                if (isset($header[$k]) && InvoiceText::parseNumber((string) $header[$k]) !== null) {
                    $header[$k] = Exact::money(Exact::of(InvoiceText::parseNumber((string) $header[$k])));
                }
            }
        }

        return $header;
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function findDuplicateDocument(string $docType, array $header): ?Document
    {
        $number = (string) ($header['invoice_no'] ?? '');
        if ($number === '') {
            return null;
        }

        $query = Document::query()
            ->where('type', $docType)
            ->where('number', $number)
            ->where('status', '!=', Document::STATUS_VOID);

        return $query->first();
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function resolveSupplier(array $header): Supplier
    {
        $name = trim((string) ($header['supplier_name'] ?? 'Square Toiletries Ltd.'));

        return Supplier::query()->firstOrCreate(['name' => $name]);
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array{retailer: Retailer, sales_rep: ?SalesRep, route: ?SalesRoute, vehicle: ?Vehicle}
     */
    private function resolveSaleParties(array $header): array
    {
        $code = trim((string) ($header['retailer_code'] ?? ''));
        $name = trim((string) ($header['retailer_name'] ?? 'Imported retailer'));
        $retailer = $code !== ''
            ? Retailer::query()->firstOrCreate(['code' => $code], ['name' => $name])
            : Retailer::query()->firstOrCreate(['name' => $name], ['code' => null]);

        $route = null;
        if (! empty($header['route_code'])) {
            $route = SalesRoute::query()->firstOrCreate(
                ['code' => (string) $header['route_code']],
                ['name' => (string) ($header['route_name'] ?? $header['route_code'])],
            );
        }

        $salesRep = null;
        if (! empty($header['sr_code'])) {
            $salesRep = SalesRep::query()->firstOrCreate(
                ['code' => (string) $header['sr_code']],
                ['name' => (string) ($header['sr_name'] ?? $header['sr_code'])],
            );
        }

        return ['retailer' => $retailer, 'sales_rep' => $salesRep, 'route' => $route, 'vehicle' => null];
    }

    private function refreshBatchErrorCounts(DocumentImportBatch $batch): void
    {
        $batch->load('invoices.lines');
        $errors = 0;
        foreach ($batch->invoices as $invoice) {
            if (($invoice->errors ?? []) !== []) {
                $errors++;
            }
            foreach ($invoice->lines as $line) {
                if (($line->errors ?? []) !== []) {
                    $errors++;
                }
            }
        }
        $batch->update(['error_count' => $errors]);
    }

    private function assertDocType(string $docType): void
    {
        if (! in_array($docType, [DocumentImportBatch::TYPE_PURCHASE, DocumentImportBatch::TYPE_SALE], true)) {
            throw ValidationException::withMessages(['doc_type' => __('Invalid document type.')]);
        }
    }
}
