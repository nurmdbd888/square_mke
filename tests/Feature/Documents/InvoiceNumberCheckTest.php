<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\Retailer;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_and_purchase_number_checks_report_only_active_duplicates_of_the_same_type(): void
    {
        $owner = $this->owner();
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $supplier = Supplier::query()->create(['name' => 'Supplier']);
        Document::query()->create([
            'type' => Document::TYPE_SALE,
            'number' => 'CHECK-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-25',
            'status' => Document::STATUS_POSTED,
            'subtotal' => '10',
            'net_total' => '10',
        ]);

        $this->actingAs($owner)
            ->getJson(route('sales.number-check', ['number' => 'CHECK-1']))
            ->assertOk()
            ->assertJson([
                'duplicate' => true,
                'message' => __('A non-void document with this number already exists.'),
            ]);

        $this->actingAs($owner)
            ->getJson(route('sales.number-check', ['number' => 'AVAILABLE']))
            ->assertOk()
            ->assertJson(['duplicate' => false, 'message' => null]);

        $this->actingAs($owner)
            ->getJson(route('purchases.number-check', ['number' => 'CHECK-1']))
            ->assertOk()
            ->assertJson(['duplicate' => false, 'message' => null]);

        Document::query()->create([
            'type' => Document::TYPE_PURCHASE,
            'number' => 'VOID-CHECK',
            'supplier_id' => $supplier->id,
            'doc_date' => '2026-10-25',
            'status' => Document::STATUS_VOID,
            'voided_at' => now(),
            'subtotal' => '10',
            'net_total' => '10',
        ]);

        $this->actingAs($owner)
            ->getJson(route('purchases.number-check', ['number' => 'VOID-CHECK']))
            ->assertOk()
            ->assertJson(['duplicate' => false, 'message' => null]);
    }

    public function test_edit_number_check_ignores_the_current_document_but_not_other_documents(): void
    {
        $owner = $this->owner();
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $first = Document::query()->create([
            'type' => Document::TYPE_SALE,
            'number' => 'EDIT-CHECK',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-25',
            'status' => Document::STATUS_POSTED,
            'subtotal' => '10',
            'net_total' => '10',
        ]);
        $second = Document::query()->create([
            'type' => Document::TYPE_SALE,
            'number' => 'OTHER-CHECK',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-25',
            'status' => Document::STATUS_DRAFT,
            'subtotal' => '10',
            'net_total' => '10',
        ]);

        $this->actingAs($owner)
            ->getJson(route('sales.documents.number-check', ['documentId' => $first->id, 'number' => 'EDIT-CHECK']))
            ->assertOk()
            ->assertJson(['duplicate' => false, 'message' => null]);

        $this->actingAs($owner)
            ->getJson(route('sales.documents.number-check', ['documentId' => $first->id, 'number' => 'OTHER-CHECK']))
            ->assertOk()
            ->assertJson(['duplicate' => true]);

        $this->actingAs($owner)
            ->getJson(route('purchases.documents.number-check', ['documentId' => $second->id, 'number' => 'OTHER-CHECK']))
            ->assertNotFound();
    }

    public function test_users_without_document_management_permission_cannot_check_invoice_numbers(): void
    {
        $viewer = $this->userWithRole('viewer');

        $this->actingAs($viewer)
            ->getJson(route('sales.number-check', ['number' => 'CHECK-1']))
            ->assertForbidden();
    }
}
