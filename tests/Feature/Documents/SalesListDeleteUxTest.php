<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\Retailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesListDeleteUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_index_shows_delete_beside_add_when_permitted(): void
    {
        $owner = $this->owner();
        $this->assertTrue($owner->can('sales.delete'));

        $this->actingAs($owner)->get(route('sales.index'))
            ->assertOk()
            ->assertSee(__('Add sale'), false)
            ->assertSee('data-testid="documents-bulk-delete"', false)
            ->assertSee('data-bulk-delete-action="void"', false)
            ->assertSee(__('Delete'), false)
            ->assertSee('id="documents-bulk-form"', false)
            ->assertSee(__('Void / remove'), false);
    }

    public function test_viewer_does_not_see_sales_delete_button(): void
    {
        $viewer = $this->userWithRole('viewer');
        $this->assertFalse($viewer->can('sales.delete'));

        $this->actingAs($viewer)->get(route('sales.index'))
            ->assertOk()
            ->assertDontSee('data-testid="documents-bulk-delete"', false);
    }

    public function test_sale_show_uses_back_link_button(): void
    {
        $owner = $this->owner();
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $document = Document::query()->create([
            'type' => Document::TYPE_SALE,
            'number' => 'S-BACK-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-17',
            'status' => Document::STATUS_DRAFT,
            'subtotal' => '0',
            'net_total' => '0',
        ]);

        $this->actingAs($owner)->get(route('sales.show', $document))
            ->assertOk()
            ->assertSee('data-testid="back-link"', false)
            ->assertSee(__('Back to sales'), false)
            ->assertSee('btn-secondary', false)
            ->assertDontSee('text-sm text-brand-700 underline">'.__('Back to sales'), false);
    }

    public function test_purchase_index_also_has_header_delete_when_permitted(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('purchases.index'))
            ->assertOk()
            ->assertSee('data-testid="documents-bulk-delete"', false);
    }

    public function test_get_sales_bulk_redirects_to_index_not_404(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get('/sales/bulk')
            ->assertRedirect(route('sales.index'))
            ->assertSessionHas('status', __('Select rows on the list, then use Delete or Apply.'));

        $this->actingAs($owner)
            ->get('/purchases/bulk')
            ->assertRedirect(route('purchases.index'));
    }

    public function test_post_sales_bulk_void_path_succeeds_not_404(): void
    {
        $owner = $this->owner();
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $document = Document::query()->create([
            'type' => Document::TYPE_SALE,
            'number' => 'S-BULK-404',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-17',
            'status' => Document::STATUS_VOID,
            'voided_at' => now(),
            'subtotal' => '10',
            'net_total' => '10',
        ]);

        $this->actingAs($owner)
            ->post('/sales/bulk', [
                'action' => 'void',
                'ids' => [$document->id],
            ])
            ->assertRedirect(route('sales.index'))
            ->assertSessionHas('status')
            ->assertSessionMissing('errors');

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }

    public function test_sales_show_rejects_non_numeric_document_segment(): void
    {
        $owner = $this->owner();

        // "bulk" must never bind as sales/{document} after the route fix.
        $this->actingAs($owner)
            ->get('/sales/not-a-document-id')
            ->assertNotFound();
    }

    public function test_bulk_delete_removes_already_void_sales_from_list(): void
    {
        $owner = $this->owner();
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $a = Document::query()->create([
            'type' => Document::TYPE_SALE,
            'number' => '2610050698555',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-17',
            'status' => Document::STATUS_VOID,
            'voided_at' => now(),
            'subtotal' => '100',
            'net_total' => '100',
        ]);
        $b = Document::query()->create([
            'type' => Document::TYPE_SALE,
            'number' => '2610050652897',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-15',
            'status' => Document::STATUS_VOID,
            'voided_at' => now(),
            'subtotal' => '50',
            'net_total' => '50',
        ]);

        $this->actingAs($owner)
            ->post(route('sales.bulk'), [
                'action' => 'void',
                'ids' => [$a->id, $b->id],
            ])
            ->assertRedirect(route('sales.index'))
            ->assertSessionHas('status', __('Removed :count from the list.', ['count' => 2]));

        $this->assertSoftDeleted('documents', ['id' => $a->id]);
        $this->assertSoftDeleted('documents', ['id' => $b->id]);

        $this->actingAs($owner)->get(route('sales.index'))
            ->assertOk()
            ->assertDontSee('2610050698555', false)
            ->assertDontSee('2610050652897', false);
    }

    public function test_bulk_delete_voids_posted_and_removes_void(): void
    {
        $owner = $this->owner();
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $posted = Document::query()->create([
            'type' => Document::TYPE_SALE,
            'number' => 'S-POST-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-17',
            'status' => Document::STATUS_POSTED,
            'posted_at' => now(),
            'subtotal' => '10',
            'net_total' => '10',
        ]);
        $voided = Document::query()->create([
            'type' => Document::TYPE_SALE,
            'number' => 'S-VOID-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-16',
            'status' => Document::STATUS_VOID,
            'voided_at' => now(),
            'subtotal' => '10',
            'net_total' => '10',
        ]);

        $this->actingAs($owner)
            ->post(route('sales.bulk'), [
                'action' => 'void',
                'ids' => [$posted->id, $voided->id],
            ])
            ->assertRedirect(route('sales.index'))
            ->assertSessionHas('status', __('Voided :voided. Removed :removed.', [
                'voided' => 1,
                'removed' => 1,
            ]));

        $this->assertSame(Document::STATUS_VOID, $posted->fresh()->status);
        $this->assertSoftDeleted('documents', ['id' => $voided->id]);
    }

    public function test_single_destroy_removes_voided_sale(): void
    {
        $owner = $this->owner();
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $document = Document::query()->create([
            'type' => Document::TYPE_SALE,
            'number' => 'S-VOID-DEL',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-17',
            'status' => Document::STATUS_VOID,
            'voided_at' => now(),
            'subtotal' => '10',
            'net_total' => '10',
        ]);

        $this->actingAs($owner)
            ->delete(route('sales.destroy', $document))
            ->assertRedirect(route('sales.index'))
            ->assertSessionHas('status', __('Document :number was removed from the list.', [
                'number' => 'S-VOID-DEL',
            ]));

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }
}
