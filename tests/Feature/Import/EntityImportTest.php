<?php

namespace Tests\Feature\Import;

use App\Domain\Import\EntityImportService;
use App\Models\EntityImportBatch;
use App\Models\Product;
use App\Models\Retailer;
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
