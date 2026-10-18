<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeletePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_page_lists_delete_permissions(): void
    {
        $this->actingAs($this->owner())->get('/roles')
            ->assertOk()
            ->assertSee('sales.delete', false)
            ->assertSee('payments.delete', false)
            ->assertSee('products.delete', false)
            ->assertSee(__('permissions.names.sales.delete'));
    }

    public function test_viewer_cannot_delete_sale_draft(): void
    {
        $viewer = $this->userWithRole('viewer');
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $document = Document::query()->create([
            'type' => Document::TYPE_SALE,
            'number' => 'S-DEL-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-17',
            'status' => Document::STATUS_DRAFT,
            'subtotal' => '0',
            'net_total' => '0',
        ]);

        $this->actingAs($viewer)
            ->delete(route('sales.destroy', $document))
            ->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => Document::STATUS_DRAFT]);
    }

    public function test_accountant_can_void_sale_draft(): void
    {
        $accountant = $this->userWithRole('accountant');
        $this->assertTrue($accountant->can('sales.delete'));

        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $document = Document::query()->create([
            'type' => Document::TYPE_SALE,
            'number' => 'S-DEL-2',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-17',
            'status' => Document::STATUS_DRAFT,
            'subtotal' => '0',
            'net_total' => '0',
        ]);

        $this->actingAs($accountant)
            ->delete(route('sales.destroy', $document))
            ->assertRedirect(route('sales.index'));

        // Drafts are soft-deleted; posted documents are voided instead.
        $this->assertSoftDeleted($document);
    }

    public function test_collector_cannot_void_payment_without_delete_permission(): void
    {
        $collector = $this->userWithRole('collector');
        $this->assertFalse($collector->can('payments.delete'));

        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $payment = Payment::query()->create([
            'kind' => Payment::KIND_RECEIPT,
            'number' => 'RC-DEL-1',
            'retailer_id' => $retailer->id,
            'method' => 'cash',
            'amount' => '50',
            'payment_date' => '2026-10-17',
            'status' => Payment::STATUS_DRAFT,
        ]);

        $this->actingAs($collector)
            ->delete(route('payments.destroy', $payment))
            ->assertForbidden();
    }

    public function test_accountant_can_delete_open_claim(): void
    {
        $accountant = $this->userWithRole('accountant');
        $supplier = Supplier::query()->create(['name' => 'Square']);
        $claim = Claim::query()->create([
            'number' => 'CL-DEL-1',
            'doc_date' => '2026-10-17',
            'supplier_id' => $supplier->id,
            'amount' => '100',
            'status' => Claim::STATUS_OPEN,
        ]);

        $this->actingAs($accountant)
            ->delete(route('claims.destroy', $claim))
            ->assertRedirect(route('claims.index'));

        $this->assertSoftDeleted($claim);
    }

    public function test_data_entry_can_deactivate_product(): void
    {
        $entry = $this->userWithRole('data_entry');
        $product = Product::query()->create([
            'code' => 'DEL-P1',
            'name_en' => 'Delete Me',
            'unit' => 'Pcs',
        ]);

        $this->actingAs($entry)
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'));

        $this->assertSoftDeleted($product);
    }

    public function test_sales_list_shows_delete_when_permitted(): void
    {
        $accountant = $this->userWithRole('accountant');
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        Document::query()->create([
            'type' => Document::TYPE_SALE,
            'number' => 'S-UI-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-17',
            'status' => Document::STATUS_DRAFT,
            'subtotal' => '0',
            'net_total' => '0',
        ]);

        $this->actingAs($accountant)
            ->get(route('sales.index'))
            ->assertOk()
            ->assertSee(__('Delete'), false);

        $viewer = $this->userWithRole('viewer');
        $this->actingAs($viewer)
            ->get(route('sales.index'))
            ->assertOk()
            ->assertDontSee('btn-danger', false);
    }

    public function test_owner_can_grant_payments_delete_to_collector(): void
    {
        $collectorRole = Role::findByName('collector');
        $this->assertFalse($collectorRole->hasPermissionTo('payments.delete'));

        $perms = [
            'collector' => ['payments.view', 'payments.collect', 'retailers.view', 'payments.delete'],
            'sr' => Role::findByName('sr')->permissions->pluck('name')->all(),
            'accountant' => Role::findByName('accountant')->permissions->pluck('name')->all(),
            'data_entry' => Role::findByName('data_entry')->permissions->pluck('name')->all(),
            'viewer' => Role::findByName('viewer')->permissions->pluck('name')->all(),
        ];

        $this->actingAs($this->owner())
            ->put('/roles', ['permissions' => $perms])
            ->assertRedirect(route('roles.index'));

        $this->assertTrue($collectorRole->fresh()->hasPermissionTo('payments.delete'));
        $this->assertTrue($this->userWithRole('collector')->can('payments.delete'));
    }
}
