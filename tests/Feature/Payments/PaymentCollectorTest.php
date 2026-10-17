<?php

namespace Tests\Feature\Payments;

use App\Domain\Documents\DocumentWriter;
use App\Domain\Payments\PartyBalance;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Retailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentCollectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_collector_can_post_partial_receipt_with_slip_photo(): void
    {
        Storage::fake('local');
        $collector = $this->userWithRole('collector');
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'sell_price' => '20']);
        $retailer = Retailer::query()->create(['name' => 'Corner Shop', 'credit_limit' => '50000']);

        $invoice = app(DocumentWriter::class)->save('sale', [
            'number' => 'S-COL-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-01',
            'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '100', 'line_value' => '1000']], $owner);

        $photo = UploadedFile::fake()->image('slip.jpg', 800, 600);

        $this->actingAs($collector)
            ->post(route('payments.store'), [
                'kind' => 'receipt',
                'number' => 'RC-COL-1',
                'retailer_id' => $retailer->id,
                'method' => 'cash',
                'amount' => '400',
                'payment_date' => '2026-10-17',
                'action' => 'post',
                'allocations' => [
                    ['document_id' => $invoice->id, 'amount' => '400'],
                ],
                'slip_photos' => [$photo],
            ])
            ->assertRedirect();

        $payment = Payment::query()->where('number', 'RC-COL-1')->first();
        $this->assertNotNull($payment);
        $this->assertTrue($payment->isPosted());
        $this->assertCount(1, $payment->attachments);

        $open = app(PartyBalance::class)->openInvoices('retailer', $retailer->id);
        $this->assertSame('600.00', $open->firstWhere('number', 'S-COL-1')->remaining);

        $this->actingAs($collector)
            ->get(route('payments.attachments.show', [$payment, $payment->attachments->first()]))
            ->assertOk();
    }

    public function test_collector_cannot_create_supplier_payment(): void
    {
        $this->actingAs($this->userWithRole('collector'))
            ->get(route('payments.create', ['kind' => 'payment']))
            ->assertForbidden();
    }

    public function test_collector_cannot_void_posted_receipt(): void
    {
        $collector = $this->userWithRole('collector');
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $payment = Payment::query()->create([
            'kind' => 'receipt',
            'number' => 'RC-V',
            'retailer_id' => $retailer->id,
            'method' => 'cash',
            'amount' => '100',
            'payment_date' => '2026-10-17',
            'status' => Payment::STATUS_POSTED,
            'posted_at' => now(),
        ]);

        $this->actingAs($collector)
            ->delete(route('payments.destroy', $payment))
            ->assertForbidden();
    }
}
