<?php

namespace Tests\Feature\Import;

use App\Domain\Import\DocumentImportService;
use App\Domain\Import\InvoiceSpreadsheetReader;
use App\Domain\Import\SquareInvoiceOcrParser;
use App\Jobs\ProcessDocumentImportJob;
use App\Models\Document;
use App\Models\DocumentImportBatch;
use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentImportOcrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    public function test_purchase_challan_pages_are_grouped_and_keep_batch_and_bonus_quantities(): void
    {
        $parser = app(SquareInvoiceOcrParser::class);
        $result = $parser->parse(DocumentImportBatch::TYPE_PURCHASE, [
            ['text' => implode("\n", [
                'Invoice No: CH-001',
                'Date: 14/10/2026',
                'Supplier: Square Toiletries Ltd.',
                'Buyer: Khaza Drug House',
                'SKU Code Product Name Pack Size Issued Qty Bonus Qty Box Size Total Box/Pcs Total Qty MRP Batch No Batch Qty',
                '1 P02 Sepnil Hand Wash 200 ml 10 2 12 1 Box, 0 Pcs 12 250 BATCH00001 10 1 Box, 0 Pcs',
            ])],
            ['text' => implode("\n", [
                'Page 2 of 2',
                '2 P02 Sepnil Hand Wash 200 ml 8 0 12 0 Box, 8 Pcs 8 250 BATCH00002 8 0 Box, 8 Pcs',
            ])],
        ]);

        $this->assertSame('ocr_challan', $result['layout']);
        $this->assertCount(1, $result['invoices']);
        $this->assertSame('CH-001', $result['invoices'][0]['invoice_no']);
        $this->assertCount(2, $result['invoices'][0]['lines']);
        $this->assertSame('10', $result['invoices'][0]['lines'][0]['qty']);
        $this->assertSame('2', $result['invoices'][0]['lines'][0]['bonus_qty']);
        $this->assertSame('BATCH00002', $result['invoices'][0]['lines'][1]['batch_no']);
        $this->assertTrue($result['invoices'][0]['lines'][0]['use_product_buy_price']);
    }

    public function test_cost_bearing_purchase_invoice_extracts_discount_and_invoice_adjustments(): void
    {
        $parser = app(SquareInvoiceOcrParser::class);
        $result = $parser->parse(DocumentImportBatch::TYPE_PURCHASE, [[
            'text' => implode("\n", [
                'Invoice No: RUP-261000510',
                'Invoice Date: 10/10/2026',
                'Supplier Name: Square Toiletries Limited',
                'Buyer: Khaza Drug House',
                'Product Pack Qty Price Discount Amount Bonus Amount Net Price',
                '1 P02 Milk Soap 25 gm 1440 9.17 627.23 0.00 12577.57',
                'Loading Adjustment: 134.00',
                'TDS: 2477.71',
                'Total Payable: 499189.35',
            ]),
        ]]);

        $invoice = $result['invoices'][0];
        $this->assertSame('ocr_supplier_invoice', $result['layout']);
        $this->assertSame('9.17', $invoice['lines'][0]['unit_price']);
        $this->assertSame('627.23', $invoice['lines'][0]['line_discount']);
        $this->assertSame('12577.57', $invoice['lines'][0]['line_value']);
        $this->assertSame('134.00', $invoice['header']['slab_adjustment']);
        $this->assertSame('2477.71', $invoice['header']['tds_amount']);
    }

    public function test_store_unit_import_uses_product_buy_price_not_mrp_and_posts_batch(): void
    {
        Storage::fake(config('filesystems.default'));
        $product = Product::query()->where('code', 'P02')->firstOrFail();
        $product->update(['buy_price' => '4.2500', 'buy_price_exact' => '4.2500']);
        $page = implode("\n", [
            'Invoice No: CH-POST',
            'Date: 14/10/2026',
            'Supplier: Square Toiletries Ltd.',
            'Buyer: Khaza Drug House',
            'SKU Code Product Name Pack Size Issued Qty Bonus Qty Box Size Total Box/Pcs Total Qty MRP Batch No Batch Qty',
            '1 P02 Sample Product 200 ml 10 2 12 1 Box, 0 Pcs 12 250 BATCH00001 10 1 Box, 0 Pcs',
        ]);

        $service = app(DocumentImportService::class);
        $result = $service->previewOcr(
            DocumentImportBatch::TYPE_PURCHASE,
            [UploadedFile::fake()->create('challan.pdf', 100, 'application/pdf')],
            [['text' => $page]],
            $this->owner(),
        );
        $batch = $result['batch']->fresh(['invoices.lines']);
        $line = $batch->invoices->firstOrFail()->lines->firstOrFail();

        $this->assertFalse($result['queued']);
        $this->assertEqualsWithDelta(4.25, (float) $line->unit_price, 0.0001);
        $this->assertEqualsWithDelta(42.50, (float) $line->line_value, 0.01);
        $this->assertTrue($line->provisional_cost);
        $this->assertSame('BATCH00001', $line->batch_no);
        $this->assertEqualsWithDelta(250.00, (float) $line->mrp, 0.01);

        $service->confirm($batch, $this->owner());
        $document = Document::query()->where('number', 'CH-POST')->firstOrFail();
        $this->assertEqualsWithDelta(4.25, (float) $document->lines->first()->unit_price, 0.0001);
        $this->assertSame('BATCH00001', $document->lines->first()->batch_no);
    }

    public function test_missing_product_buy_price_blocks_purchase_confirmation(): void
    {
        Storage::fake(config('filesystems.default'));
        Product::query()->where('code', 'P02')->update(['buy_price' => null, 'buy_price_exact' => null]);
        $result = app(DocumentImportService::class)->previewOcr(
            DocumentImportBatch::TYPE_PURCHASE,
            [UploadedFile::fake()->create('challan.pdf', 100, 'application/pdf')],
            [['text' => implode("\n", [
                'Invoice No: CH-NOCOST',
                'Date: 14/10/2026',
                'Supplier: Square Toiletries Ltd.',
                'Buyer: Khaza Drug House',
                'SKU Code Product Name Pack Size Issued Qty Bonus Qty Box Size Total Box/Pcs Total Qty MRP Batch No Batch Qty',
                '1 P02 Sample Product 200 ml 10 0 12 1 Box, 0 Pcs 10 250 BATCH00001 10 1 Box, 0 Pcs',
            ])]],
            $this->owner(),
        );

        $this->assertGreaterThan(0, $result['batch']->error_count);
        $this->expectException(ValidationException::class);
        app(DocumentImportService::class)->confirm($result['batch'], $this->owner());
    }

    public function test_supplier_invoice_costs_and_adjustments_are_posted_without_provisional_fallback(): void
    {
        Storage::fake(config('filesystems.default'));
        $page = implode("\n", [
            'Invoice No: RUP-TEST-1',
            'Invoice Date: 10/10/2026',
            'Supplier Name: Square Toiletries Limited',
            'Buyer: Khaza Drug House',
            'Product Pack Qty Price Discount Amount Bonus Amount Net Price',
            '1 P02 Milk Soap 25 gm 10 9.17 2.00 0.00 89.70',
            'Loading Adjustment: 1.00',
            'TDS: 0.50',
            'Total Payable: 89.20',
        ]);
        $service = app(DocumentImportService::class);
        $result = $service->previewOcr(
            DocumentImportBatch::TYPE_PURCHASE,
            [UploadedFile::fake()->create('supplier-invoice.pdf', 100, 'application/pdf')],
            [['text' => $page]],
            $this->owner(),
        );
        $invoice = $result['batch']->invoices->firstOrFail();
        $line = $invoice->lines->firstOrFail();

        $this->assertFalse($line->provisional_cost);
        $this->assertEqualsWithDelta(9.17, (float) $line->unit_price, 0.0001);
        $this->assertEqualsWithDelta(89.70, (float) $line->line_value, 0.01);

        $service->updateInvoice($invoice, [
            'invoice_no' => 'RUP-TEST-UPDATED',
            'doc_date' => '2026-10-11',
            'supplier_name' => 'Verified Supplier',
            'slab_adjustment' => '1.00',
            'tds_amount' => '0.50',
        ]);
        $service->confirm($result['batch']->fresh(['invoices.lines']), $this->owner());

        $document = Document::query()->where('number', 'RUP-TEST-UPDATED')->firstOrFail();
        $this->assertSame('Verified Supplier', $document->supplier->name);
        $this->assertEqualsWithDelta(1.00, (float) $document->slab_adjustment, 0.01);
        $this->assertEqualsWithDelta(0.50, (float) $document->tds_amount, 0.01);
        $this->assertEqualsWithDelta(89.70, (float) $document->lines->first()->line_value, 0.01);
    }

    public function test_sales_report_pages_are_separate_invoices(): void
    {
        $result = app(SquareInvoiceOcrParser::class)->parse(DocumentImportBatch::TYPE_SALE, [
            ['text' => "Invoice No: SALE-01\nDate: 10/10/2026\nRetailer Code: 4831\n1 Milk Soap 25 gm 100 9.17 0 0 917.00"],
            ['text' => "Invoice No: SALE-02\nDate: 10/10/2026\nRetailer Code: 4831\n1 Milk Soap 25 gm 50 9.17 0 0 458.50"],
        ]);

        $this->assertSame('ocr_sales', $result['layout']);
        $this->assertCount(2, $result['invoices']);
        $this->assertSame('SALE-02', $result['invoices'][1]['invoice_no']);
        $this->assertSame('4831', $result['invoices'][0]['header']['retailer_code']);
        $this->assertSame('100', $result['invoices'][0]['lines'][0]['qty']);

        $withBonusQty = app(SquareInvoiceOcrParser::class)->parse(DocumentImportBatch::TYPE_SALE, [[
            'text' => "Invoice No: SALE-BONUS\nBonus Qty Price Discount Amount\n1 Soap 20 g 5 2.00 0.50 1 7.50",
        ]]);
        $this->assertSame('1', $withBonusQty['invoices'][0]['lines'][0]['bonus_qty']);

        $withoutBonusColumn = app(SquareInvoiceOcrParser::class)->parse(DocumentImportBatch::TYPE_SALE, [[
            'text' => "Invoice No: SALE-FOUR\nProduct Qty Price Discount Net Amount\n1 Soap 20 g 5 2.00 0.50 9.50",
        ]]);
        $this->assertSame('9.50', $withoutBonusColumn['invoices'][0]['lines'][0]['line_value']);
    }

    public function test_upload_endpoint_creates_a_preview_from_browser_extracted_ocr(): void
    {
        Storage::fake(config('filesystems.default'));
        $owner = $this->owner();
        $page = implode("\n", [
            'Invoice No: CH-UPLOAD',
            'Date: 14/10/2026',
            'Supplier: Square Toiletries Ltd.',
            'Buyer: Khaza Drug House',
            'SKU Code Product Name Pack Size Issued Qty Bonus Qty Box Size Total Box/Pcs Total Qty MRP Batch No Batch Qty',
            '1 P02 Sample Product 200 ml 10 0 12 1 Box, 0 Pcs 10 250 BATCH00001 10 1 Box, 0 Pcs',
        ]);

        $response = $this->asMaintenanceUser()->actingAs($owner)->post(route('imports.invoices.store'), [
            'doc_type' => 'purchase',
            'scan_files' => [
                UploadedFile::fake()->createWithContent('challan.pdf', "%PDF-1.4\n1 0 obj <</Type/Catalog>> endobj\n%%EOF"),
            ],
            'ocr_pages' => json_encode([['text' => $page]], JSON_THROW_ON_ERROR),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('document_import_batches', [
            'doc_type' => 'purchase',
            'original_name' => 'challan.pdf',
            'layout' => 'ocr_challan',
            'status' => DocumentImportBatch::STATUS_PREVIEW,
        ]);
        $batch = DocumentImportBatch::query()->latest('id')->firstOrFail();
        $this->actingAs($owner)->get(route('imports.invoices.show', $batch))
            ->assertOk()
            ->assertSee('CH-UPLOAD');

    }

    public function test_large_ocr_batches_queue_the_parsed_payload_for_processing(): void
    {
        Storage::fake(config('filesystems.default'));
        Queue::fake();
        config(['sdm.document_import_inline_limit' => 1]);
        $service = app(DocumentImportService::class);
        $result = $service->previewOcr(
            DocumentImportBatch::TYPE_SALE,
            [UploadedFile::fake()->create('sales.pdf', 100, 'application/pdf')],
            [['text' => implode("\n", [
                'Invoice No: SALE-QUEUE',
                'Date: 10/10/2026',
                'Retailer Code: 4831',
                '1 First Product 25 gm 10 9.17 0 0 91.70',
                '2 Second Product 50 gm 10 9.17 0 0 91.70',
            ])]],
            $this->owner(),
        );

        $this->assertTrue($result['queued']);
        $this->assertSame(DocumentImportBatch::STATUS_PROCESSING, $result['batch']->status);
        Queue::assertPushed(ProcessDocumentImportJob::class, function (ProcessDocumentImportJob $job) use ($service): bool {
            $job->handle(app(InvoiceSpreadsheetReader::class), $service);

            return true;
        });

        $batch = $result['batch']->fresh(['invoices.lines']);
        $this->assertSame(DocumentImportBatch::STATUS_PREVIEW, $batch->status);
        $this->assertSame(2, $batch->line_count);
        $this->assertFalse(Storage::exists($batch->meta['ocr_payload_path']));
    }
}
