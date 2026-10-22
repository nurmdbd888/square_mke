<?php

namespace Tests\Feature\Import;

use App\Domain\Import\EntityImportService;
use App\Models\EntityImportBatch;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\SalesRep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EntityImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_template_and_import_upsert(): void
    {
        $owner = $this->owner();
        $csv = "Product code,Product name,Buy price (TP)\nIMP01,Import Soap,27.40\n";
        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $result = app(EntityImportService::class)->preview('products', $file, $owner);
        $batch = $result['batch'];
        $this->assertSame(EntityImportBatch::STATUS_PREVIEW, $batch->status);
        $this->assertGreaterThan(0, $batch->rows()->count());

        app(EntityImportService::class)->confirm($batch, EntityImportBatch::MODE_UPDATE, $owner);
        $this->assertSame('IMP01', Product::query()->where('code', 'IMP01')->value('code'));
    }

    public function test_product_list_filename_maps_master_export_columns(): void
    {
        $owner = $this->owner();
        $path = base_path('docs/samples/master-exports/ProductList17102026_1621.xls');
        if (! is_file($path)) {
            $this->markTestSkipped('Sample file missing');
        }
        $file = new UploadedFile($path, 'ProductList17102026_1621.xls', null, null, true);
        $batch = app(EntityImportService::class)->preview('products', $file, $owner)['batch'];
        $this->assertGreaterThan(3, $batch->rows()->count());
        $first = $batch->rows()->orderBy('row_no')->first();
        $this->assertSame('CHBAC102', $first->parsed['code'] ?? null);
    }

    public function test_skip_duplicate_leaves_existing_product(): void
    {
        $owner = $this->owner();
        Product::query()->create(['code' => 'DUP1', 'name_en' => 'Original', 'unit' => 'Pcs']);
        $csv = "Product code,Product name\nDUP1,Changed Name\n";
        $file = UploadedFile::fake()->createWithContent('dup.csv', $csv);
        $batch = app(EntityImportService::class)->preview('products', $file, $owner)['batch'];
        app(EntityImportService::class)->confirm($batch, EntityImportBatch::MODE_SKIP, $owner);
        $this->assertSame('Original', Product::query()->where('code', 'DUP1')->value('name_en'));
    }

    public function test_retailers_import_page_requires_permission(): void
    {
        $entry = $this->userWithRole('viewer');
        $this->actingAs($entry)->get(route('imports.entity.index', 'retailers'))->assertForbidden();
        $this->actingAs($this->userWithRole('data_entry'))->get(route('imports.entity.index', 'retailers'))->assertOk();
    }

    public function test_retailers_csv_import(): void
    {
        $owner = $this->owner();
        $csv = "Retailer code,Name\nR-IMP,Corner Shop Import\n";
        $file = UploadedFile::fake()->createWithContent('retailers.csv', $csv);
        $batch = app(EntityImportService::class)->preview('retailers', $file, $owner)['batch'];
        app(EntityImportService::class)->confirm($batch, EntityImportBatch::MODE_UPDATE, $owner);
        $this->assertSame('Corner Shop Import', Retailer::query()->where('code', 'R-IMP')->value('name'));
    }

    public function test_retailers_confirm_parses_comma_formatted_credit_limits(): void
    {
        $owner = $this->owner();
        SalesRep::query()->create(['code' => '7287', 'name' => 'Field Rep']);
        Retailer::query()->create(['code' => 'EXIST', 'name' => 'Existing', 'credit_limit' => '100.00']);

        $csv = <<<'CSV'
Retailer code,Name,Address,Phone,Route code,SR code,Credit limit
NEW1,New Shop,Road 1,1,,7287,"1,500"
NEW2,Other Shop,,1,,7287,"200,000"
EXIST,Should Skip,,,,7287,"50,000"
CSV;
        $file = UploadedFile::fake()->createWithContent('retailers-import.csv', $csv);
        $batch = app(EntityImportService::class)->preview('retailers', $file, $owner)['batch'];
        $this->assertSame(0, $batch->error_count);

        app(EntityImportService::class)->confirm($batch, EntityImportBatch::MODE_SKIP, $owner);

        $this->assertSame('1500.00', Retailer::query()->where('code', 'NEW1')->value('credit_limit'));
        $this->assertSame('200000.00', Retailer::query()->where('code', 'NEW2')->value('credit_limit'));
        $this->assertSame('100.00', Retailer::query()->where('code', 'EXIST')->value('credit_limit'));
        $this->assertSame('7287', SalesRep::query()->find(Retailer::query()->where('code', 'NEW1')->value('sales_rep_id'))?->code);
    }

    public function test_preview_marks_missing_required_fields_as_errors(): void
    {
        $owner = $this->owner();
        $csv = "Product code,Product name\n,Missing Code Row\nOK01,Ok Product\n";
        $file = UploadedFile::fake()->createWithContent('bad-products.csv', $csv);
        $batch = app(EntityImportService::class)->preview('products', $file, $owner)['batch'];

        $this->assertGreaterThan(0, $batch->error_count);
        $errorRow = $batch->rows()->where('status', 'error')->first();
        $this->assertNotNull($errorRow);
        $this->assertNotEmpty($errorRow->errors);

        $this->actingAs($owner)
            ->get(route('imports.entity.show', ['products', $batch]))
            ->assertOk()
            ->assertSee('data-testid="import-dry-run-banner"', false)
            ->assertSee('data-testid="import-error-banner"', false)
            ->assertSee('data-testid="import-preview-table"', false)
            ->assertDontSee(json_encode($errorRow->parsed, JSON_UNESCAPED_UNICODE), false);

        $this->actingAs($owner)
            ->from(route('imports.entity.show', ['products', $batch]))
            ->post(route('imports.entity.confirm', ['products', $batch]), ['duplicate_mode' => 'update'])
            ->assertRedirect()
            ->assertSessionHasErrors('import');

        $this->assertSame(EntityImportBatch::STATUS_PREVIEW, $batch->fresh()->status);
        $this->assertNull(Product::query()->where('code', 'OK01')->value('code'));
    }

    public function test_retailers_confirm_via_http_does_not_error_on_xlsx_style_amounts(): void
    {
        SalesRep::query()->create(['code' => '7287', 'name' => 'Field Rep']);
        $csv = "Retailer code,Name,Address,Phone,Route code,SR code,Credit limit\nR-XLS,Live Row,Addr,1,,7287,\"1,500\"\n";
        $file = UploadedFile::fake()->createWithContent('retailers-import.csv.xlsx', $csv);

        $user = $this->owner();
        $this->actingAs($user)
            ->post(route('imports.entity.store', 'retailers'), ['file' => $file])
            ->assertRedirect();

        $batch = EntityImportBatch::query()->where('entity', 'retailers')->latest('id')->first();
        $this->assertNotNull($batch);

        $this->actingAs($user)
            ->post(route('imports.entity.confirm', ['retailers', $batch]), ['duplicate_mode' => 'skip'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('1500.00', Retailer::query()->where('code', 'R-XLS')->value('credit_limit'));
    }

    #[DataProvider('listPagesWithImportActions')]
    public function test_list_pages_expose_import_and_export(string $url): void
    {
        $this->actingAs($this->owner())
            ->get($url)
            ->assertOk()
            ->assertSee(__('Import'), false)
            ->assertSee(__('Export'), false);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function listPagesWithImportActions(): array
    {
        return [
            ['/products'],
            ['/retailers'],
            ['/masters/suppliers'],
            ['/masters/routes'],
            ['/masters/sales-reps'],
            ['/masters/vehicles'],
            ['/masters/price-lists'],
        ];
    }
}
