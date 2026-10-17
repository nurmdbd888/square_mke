<?php

namespace Tests\Feature\Master;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListToolbarExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_list_has_one_export_control_and_import(): void
    {
        $html = $this->actingAs($this->owner())
            ->get(route('products.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'data-testid="list-export-menu"'));
        $this->assertSame(0, substr_count($html, 'data-testid="list-export-download"'));
        $this->assertSame(1, substr_count($html, 'data-testid="list-import"'));
    }

    public function test_suppliers_directory_has_single_export_link(): void
    {
        $html = $this->actingAs($this->owner())
            ->get(route('masters.directory.index', 'suppliers'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'data-testid="list-export-download"'));
        $this->assertSame(0, substr_count($html, 'data-testid="list-export-menu"'));
        $this->assertSame(1, substr_count($html, 'data-testid="list-import"'));
    }
}
