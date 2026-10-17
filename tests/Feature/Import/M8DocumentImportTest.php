<?php

namespace Tests\Feature\Import;

use App\Domain\Import\DocumentImportService;
use App\Jobs\ProcessDocumentImportJob;
use App\Models\Document;
use App\Models\DocumentImportBatch;
use App\Models\DocumentImportInvoice;
use App\Models\Product;
use App\Models\ProductAlias;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class M8DocumentImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    public function test_purchase_challan_sample_has_seventeen_lines_and_no_line_errors(): void
    {
        $owner = $this->owner();
        $file = new UploadedFile(
            base_path('docs/samples/purchase-challan-6.3.csv'),
            'purchase-challan-6.3.csv',
            'text/csv',
            null,
            true,
        );

        $result = app(DocumentImportService::class)->preview(
            DocumentImportBatch::TYPE_PURCHASE,
            $file,
            $owner,
            null,
            ['invoice_no' => 'INV-ADU/4344/1026', 'doc_date' => '2026-10-15'],
        );

        $batch = $result['batch']->fresh(['invoices.lines']);
        $this->assertFalse($result['queued']);
        $this->assertSame(17, $batch->line_count);
        $this->assertSame(0, $batch->error_count);

        $lines = $batch->invoices->first()->lines;
        $qtySum = $lines->sum(fn ($l) => (float) $l->qty);
        $this->assertEqualsWithDelta(5188, $qtySum, 0.001);
        $valueSum = $lines->sum(fn ($l) => (float) $l->line_value);
        $this->assertEqualsWithDelta(463810.63, $valueSum, 0.02);
    }

    public function test_sales_invoice_sample_line_count_and_net_header_fields(): void
    {
        $owner = $this->owner();
        $path = $this->buildSaleWorkbook();
        $file = new UploadedFile($path, 'sales-invoice.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $result = app(DocumentImportService::class)->preview(DocumentImportBatch::TYPE_SALE, $file, $owner);
        $batch = $result['batch']->fresh(['invoices.lines']);

        $this->assertSame(13, $batch->line_count);
        $header = $batch->invoices->first()->header;
        $this->assertSame('419.73', (string) ($header['slab_adjustment'] ?? ''));
    }

    public function test_confirm_and_undo_purchase_batch(): void
    {
        $owner = $this->owner();
        $file = new UploadedFile(
            base_path('docs/samples/purchase-challan-6.3.csv'),
            'purchase-challan-6.3.csv',
            'text/csv',
            null,
            true,
        );
        $service = app(DocumentImportService::class);
        $batch = $service->preview(
            DocumentImportBatch::TYPE_PURCHASE,
            $file,
            $owner,
            null,
            ['invoice_no' => 'INV-TEST-4344', 'doc_date' => '2026-10-15'],
        )['batch'];

        $service->confirm($batch->fresh(['invoices.lines']), $owner);
        $this->assertSame(DocumentImportBatch::STATUS_POSTED, $batch->fresh()->status);
        $this->assertSame(1, Document::query()->where('number', 'INV-TEST-4344')->count());

        $service->undo($batch->fresh(), $owner);
        $this->assertSame(DocumentImportBatch::STATUS_UNDONE, $batch->fresh()->status);
        $this->assertSame(0, Document::query()->where('number', 'INV-TEST-4344')->where('status', '!=', Document::STATUS_VOID)->count());
    }

    public function test_duplicate_document_is_flagged_on_reimport(): void
    {
        $owner = $this->owner();
        $service = app(DocumentImportService::class);
        $meta = ['invoice_no' => 'INV-DUP-1', 'doc_date' => '2026-10-15'];
        $file = new UploadedFile(base_path('docs/samples/purchase-challan-6.3.csv'), 'a.csv', 'text/csv', null, true);
        $batch = $service->preview(DocumentImportBatch::TYPE_PURCHASE, $file, $owner, null, $meta)['batch'];
        $service->confirm($batch->fresh(['invoices.lines']), $owner);

        $batch2 = $service->preview(DocumentImportBatch::TYPE_PURCHASE, $file, $owner, null, $meta)['batch']->fresh(['invoices']);
        $this->assertSame(DocumentImportInvoice::STATUS_DUPLICATE, $batch2->invoices->first()->status);
    }

    public function test_product_correction_creates_alias(): void
    {
        $owner = $this->owner();
        $service = app(DocumentImportService::class);
        $file = new UploadedFile(base_path('docs/samples/purchase-challan-6.3.csv'), 'a.csv', 'text/csv', null, true);
        $batch = $service->preview(
            DocumentImportBatch::TYPE_PURCHASE,
            $file,
            $owner,
            null,
            ['invoice_no' => 'INV-ALIAS', 'doc_date' => '2026-10-15'],
        )['batch']->fresh(['invoices.lines']);

        $line = $batch->invoices->first()->lines->first();
        $label = $line->product_label;
        $other = Product::query()->where('code', 'P02')->firstOrFail();
        $service->updateLine($line, ['product_id' => $other->id], $owner);

        $this->assertTrue(ProductAlias::query()->where('product_id', $other->id)->where('name', $label)->exists());
    }

    public function test_large_import_is_queued(): void
    {
        Queue::fake();
        config(['sdm.document_import_inline_limit' => 5]);
        $owner = $this->owner();
        $file = new UploadedFile(base_path('docs/samples/purchase-challan-6.3.csv'), 'a.csv', 'text/csv', null, true);
        $result = app(DocumentImportService::class)->preview(
            DocumentImportBatch::TYPE_PURCHASE,
            $file,
            $owner,
            null,
            ['invoice_no' => 'INV-Q', 'doc_date' => '2026-10-15'],
        );
        $this->assertTrue($result['queued']);
        Queue::assertPushed(ProcessDocumentImportJob::class);
    }

    private function buildSaleWorkbook(): string
    {
        $headerJson = json_decode((string) file_get_contents(base_path('docs/samples/sales-invoice-report-header.json')), true);
        $linesCsv = array_map('str_getcsv', file(base_path('docs/samples/sales-invoice-report.csv')));
        $lineHeader = array_shift($linesCsv);

        $spreadsheet = new Spreadsheet;
        $h = $spreadsheet->getActiveSheet()->setTitle('Header');
        $headerCols = ['invoice_no', 'invoice_date', 'delivery_date', 'distributor_name', 'retailer_name', 'retailer_code', 'subtotal', 'slab_adjustment', 'rounding_adjustment', 'net_total'];
        foreach ($headerCols as $i => $col) {
            $h->setCellValue([$i + 1, 1], $col);
        }
        $map = [
            'invoice_no' => $headerJson['invoice_no'],
            'invoice_date' => $headerJson['invoice_date'],
            'delivery_date' => $headerJson['delivery_date'],
            'distributor_name' => $headerJson['distributor']['name'],
            'retailer_name' => $headerJson['retailer']['name_bn'],
            'retailer_code' => $headerJson['retailer']['code'],
            'subtotal' => $headerJson['subtotal_invoice_lines'],
            'slab_adjustment' => $headerJson['slab_adjustment'],
            'rounding_adjustment' => $headerJson['computed']['rounding_to_net'],
            'net_total' => $headerJson['net_total_amt'],
        ];
        foreach ($headerCols as $i => $col) {
            $h->setCellValue([$i + 1, 2], $map[$col] ?? '');
        }

        $l = $spreadsheet->createSheet()->setTitle('Lines');
        foreach ($lineHeader as $i => $col) {
            $l->setCellValue([$i + 1, 1], $col);
        }
        foreach ($linesCsv as $r => $row) {
            foreach ($row as $c => $val) {
                $l->setCellValue([$c + 1, $r + 2], $val);
            }
        }

        $path = sys_get_temp_dir().'/sale-invoice-test-'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
